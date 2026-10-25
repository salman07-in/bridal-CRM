import json
from datetime import datetime, date, timezone
from typing import Dict, Any, List, Optional
import requests
from flask import current_app
from app.models.customer import Customer
from app.models.reminder_log import ReminderLog
from app.models.setting import Setting
from app.services.anniversary import AnniversaryService
from app.services.email_service import EmailService
from app.extensions import db


class ReminderService:
    """Manages reminder queueing, idempotency checks, automated dispatch, and status updates."""

    @classmethod
    def get_or_create_reminder_log(
        cls,
        customer_id: int,
        anniversary_year: int,
        channel: str,
        reminder_type: str,
        scheduled_for: date,
    ) -> ReminderLog:
        """Finds existing reminder by idempotency key or initializes a new pending log."""
        key = ReminderLog.build_idempotency_key(customer_id, anniversary_year, channel, reminder_type)
        existing = ReminderLog.query.filter_by(idempotency_key=key).first()
        if existing:
            return existing

        log = ReminderLog(
            idempotency_key=key,
            customer_id=customer_id,
            anniversary_year=anniversary_year,
            channel=channel,
            reminder_type=reminder_type,
            status="pending",
            scheduled_for=scheduled_for,
            retry_count=0,
        )
        db.session.add(log)
        db.session.flush()
        return log

    @classmethod
    def process_daily_reminders(cls) -> Dict[str, Any]:
        """Core automated routine run daily (via cron or n8n).
        
        Steps:
        1. Query today's anniversaries and upcoming 7-day anniversaries.
        2. Create pending ReminderLog entries for each with strict idempotency keys.
        3. Send Owner Daily Digest email (if enabled in settings).
        4. Attempt automated WhatsApp dispatch ONLY if:
           - Customer has explicitly granted whatsapp_consent = True
           - WhatsApp API configuration is valid and enabled
        """
        today = AnniversaryService.get_kolkata_today()
        todays_anniversaries = AnniversaryService.get_todays_anniversaries(today)
        upcoming_anniversaries = AnniversaryService.get_upcoming_anniversaries(7, today)
        missing_dates = Customer.query.filter(Customer.wedding_date.is_(None)).count()

        created_logs = 0
        owner_digest_status = "skipped"
        whatsapp_dispatches = 0

        # 1. Queue today's anniversary greetings
        for item in todays_anniversaries:
            cust = item["customer"]
            target_year = item["target_year"]

            # Queue WhatsApp reminder
            wa_log = cls.get_or_create_reminder_log(
                customer_id=cust.id,
                anniversary_year=target_year,
                channel="whatsapp",
                reminder_type="today_greeting",
                scheduled_for=today,
            )
            created_logs += 1

            # If WhatsApp Cloud API enabled and customer consented, attempt dispatch
            if wa_log.status in ("pending", "failed"):
                if cust.whatsapp_consent:
                    wa_res = cls.send_whatsapp_greeting(cust, item["years"], wa_log)
                    if wa_res.get("success"):
                        whatsapp_dispatches += 1
                else:
                    # WhatsApp consent not granted: mark for manual review
                    wa_log.error_message = "WhatsApp consent not granted by customer. Available for manual phone/message."

        # 2. Queue upcoming 7-day reminders
        for item in upcoming_anniversaries:
            cust = item["customer"]
            target_year = item["target_year"]
            cls.get_or_create_reminder_log(
                customer_id=cust.id,
                anniversary_year=target_year,
                channel="internal_alert",
                reminder_type="advance_7day_notice",
                scheduled_for=item["anniversary_date"],
            )
            created_logs += 1

        # 3. Owner Daily Digest Email
        owner_digest_enabled = Setting.get_bool("send_owner_daily_digest", True)
        if owner_digest_enabled and (todays_anniversaries or upcoming_anniversaries or missing_dates > 0):
            # Check if owner digest already sent today
            owner_key = f"OWNER_DIGEST_{today.isoformat()}"
            existing_owner_log = ReminderLog.query.filter_by(idempotency_key=owner_key).first()

            if not existing_owner_log or existing_owner_log.status != "sent":
                if not existing_owner_log:
                    # Dummy customer ID 1 or first customer for FK constraint, or nullable FK
                    first_cust = Customer.query.first()
                    first_id = first_cust.id if first_cust else None
                    if first_id:
                        existing_owner_log = ReminderLog(
                            idempotency_key=owner_key,
                            customer_id=first_id,
                            anniversary_year=today.year,
                            channel="owner_digest",
                            reminder_type="daily_digest",
                            status="pending",
                            scheduled_for=today,
                        )
                        db.session.add(existing_owner_log)

                email_res = EmailService.send_owner_daily_digest(
                    todays_anniversaries,
                    upcoming_anniversaries,
                    missing_dates,
                )
                if email_res.get("success"):
                    owner_digest_status = "sent"
                    if existing_owner_log:
                        existing_owner_log.mark_sent("Owner digest email dispatched successfully")
                else:
                    owner_digest_status = f"failed: {email_res.get('error')}"
                    if existing_owner_log:
                        existing_owner_log.mark_failed(email_res.get("error", "Failed"))

        db.session.commit()

        return {
            "today": today.isoformat(),
            "todays_count": len(todays_anniversaries),
            "upcoming_count": len(upcoming_anniversaries),
            "created_or_found_logs": created_logs,
            "owner_digest_status": owner_digest_status,
            "whatsapp_dispatches": whatsapp_dispatches,
        }

    @classmethod
    def send_whatsapp_greeting(cls, customer: Customer, years: int, log: ReminderLog) -> Dict[str, Any]:
        """Dispatches an anniversary template via official WhatsApp Cloud API (Meta).
        Requires customer.whatsapp_consent == True.
        """
        if not customer.whatsapp_consent:
            log.mark_failed("Cannot send WhatsApp: Customer has not provided WhatsApp consent")
            return {"success": False, "error": "No WhatsApp consent"}

        token = Setting.get_value("whatsapp_access_token", current_app.config.get("WHATSAPP_ACCESS_TOKEN", ""))
        phone_id = Setting.get_value("whatsapp_phone_number_id", current_app.config.get("WHATSAPP_PHONE_NUMBER_ID", ""))
        template_name = Setting.get_value("whatsapp_template_name", current_app.config.get("WHATSAPP_TEMPLATE_NAME", "anniversary_greeting_v1"))
        enabled = Setting.get_bool("whatsapp_enabled", current_app.config.get("WHATSAPP_ENABLED", False))

        if not enabled or not token or not phone_id:
            # Leave log as pending with clear configuration note
            log.error_message = "WhatsApp Cloud API not enabled or credentials not configured"
            return {"success": False, "error": "WhatsApp API not configured"}

        url = f"https://graph.facebook.com/v19.0/{phone_id}/messages"
        headers = {
            "Authorization": f"Bearer {token}",
            "Content-Type": "application/json",
        }

        # Format recipient phone: 919876543210
        recipient_phone = "91" + customer.clean_phone

        payload = {
            "messaging_product": "whatsapp",
            "to": recipient_phone,
            "type": "template",
            "template": {
                "name": template_name,
                "language": {"code": "en"},
                "components": [
                    {
                        "type": "body",
                        "parameters": [
                            {"type": "text", "text": customer.full_name},
                            {"type": "text", "text": str(years)},
                            {"type": "text", "text": Setting.get_value("salon_name", "Safiya's Makeover")},
                        ],
                    }
                ],
            },
        }

        try:
            res = requests.post(url, headers=headers, json=payload, timeout=12)
            if res.status_code in (200, 201):
                res_data = res.json()
                log.mark_sent(json.dumps(res_data))
                return {"success": True, "data": res_data}
            else:
                err_msg = f"HTTP {res.status_code}: {res.text}"
                log.mark_failed(err_msg)
                return {"success": False, "error": err_msg}
        except Exception as e:
            log.mark_failed(str(e))
            return {"success": False, "error": str(e)}

    @classmethod
    def retry_reminder(cls, log_id: int) -> Dict[str, Any]:
        """Manually trigger retry for a failed or pending reminder from the UI."""
        log = db.session.get(ReminderLog, log_id)
        if not log:
            return {"success": False, "error": "Reminder log not found"}

        cust = log.customer
        if not cust:
            return {"success": False, "error": "Customer record no longer exists"}

        if log.channel == "whatsapp":
            years = cust.get_anniversary_year_count(log.anniversary_year)
            result = cls.send_whatsapp_greeting(cust, years, log)
            db.session.commit()
            return result
        elif log.channel in ("email", "owner_digest"):
            # Attempt email re-dispatch
            cfg = EmailService.get_smtp_config()
            res = EmailService.send_email(
                cfg["owner_email"],
                f"💍 Manual Retry: Wedding Anniversary for {cust.full_name}",
                f"<p>Anniversary reminder for <strong>{cust.full_name}</strong> (Mobile: {cust.mobile_number})</p>",
            )
            if res.get("success"):
                log.mark_sent("Manual email retry sent")
            else:
                log.mark_failed(res.get("error", "Failed"))
            db.session.commit()
            return res

        return {"success": False, "error": f"Unsupported channel {log.channel}"}
