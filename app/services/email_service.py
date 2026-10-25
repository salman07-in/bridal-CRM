import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from email.utils import formataddr
from typing import Dict, Any, List, Optional
from flask import current_app
from app.models.setting import Setting


class EmailService:
    """Production-grade SMTP email service for notifications and daily digests.
    
    Dynamically loads credentials from the Setting database table (configured via UI),
    falling back to application environment configuration if not set in DB.
    """

    @classmethod
    def get_smtp_config(cls) -> Dict[str, Any]:
        """Resolves active SMTP configuration from database or environment."""
        cfg = current_app.config
        return {
            "enabled": Setting.get_bool("smtp_enabled", cfg.get("SMTP_ENABLED", False)),
            "host": Setting.get_value("smtp_host", cfg.get("SMTP_HOST", "smtp.gmail.com")),
            "port": Setting.get_int("smtp_port", cfg.get("SMTP_PORT", 587)),
            "use_tls": Setting.get_bool("smtp_use_tls", cfg.get("SMTP_USE_TLS", True)),
            "use_ssl": Setting.get_bool("smtp_use_ssl", cfg.get("SMTP_USE_SSL", False)),
            "username": Setting.get_value("smtp_username", cfg.get("SMTP_USERNAME", "")),
            "password": Setting.get_value("smtp_password", cfg.get("SMTP_PASSWORD", "")),
            "from_email": Setting.get_value("smtp_from_email", cfg.get("SMTP_FROM_EMAIL", "notifications@safiyasmakeover.com")),
            "from_name": Setting.get_value("smtp_from_name", cfg.get("SMTP_FROM_NAME", "Safiya's Makeover")),
            "owner_email": Setting.get_value("salon_owner_email", cfg.get("SALON_OWNER_EMAIL", "owner@safiyasmakeover.com")),
        }

    @classmethod
    def send_email(cls, recipient: str, subject: str, html_body: str, text_body: Optional[str] = None) -> Dict[str, Any]:
        """Sends an HTML/text multipart email via configured SMTP server."""
        config = cls.get_smtp_config()

        if not config["enabled"]:
            return {"success": False, "error": "SMTP email service is disabled in settings"}

        if not config["host"] or not recipient:
            return {"success": False, "error": "Missing SMTP host or recipient email"}

        msg = MIMEMultipart("alternative")
        msg["Subject"] = subject
        msg["From"] = formataddr((config["from_name"], config["from_email"]))
        msg["To"] = recipient

        if text_body:
            msg.attach(MIMEText(text_body, "plain", "utf-8"))
        if html_body:
            msg.attach(MIMEText(html_body, "html", "utf-8"))

        try:
            if config["use_ssl"]:
                server = smtplib.SMTP_SSL(config["host"], config["port"], timeout=15)
            else:
                server = smtplib.SMTP(config["host"], config["port"], timeout=15)
                if config["use_tls"]:
                    server.starttls()

            if config["username"] and config["password"]:
                server.login(config["username"], config["password"])

            server.sendmail(config["from_email"], [recipient], msg.as_string())
            server.quit()
            return {"success": True, "error": None}

        except Exception as e:
            current_app.logger.error(f"SMTP send failure to {recipient}: {str(e)}")
            return {"success": False, "error": str(e)}

    @classmethod
    def send_test_email(cls, recipient: str) -> Dict[str, Any]:
        """Sends a verification email to test SMTP connectivity and credentials."""
        subject = "✨ Test Email: Safiya's Makeover CRM Connected"
        html = f"""
        <div style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #F1D4DB; border-radius: 8px; background-color: #FFFFFF;">
            <div style="text-align: center; margin-bottom: 24px;">
                <h2 style="color: #D4738C; margin: 0;">Safiya's Makeover</h2>
                <p style="color: #718096; font-size: 14px; margin: 4px 0 0 0;">Bridal CRM &amp; Anniversary Reminder System</p>
            </div>
            <div style="background-color: #FDF7F8; padding: 18px; border-radius: 6px; border-left: 4px solid #D4738C; margin-bottom: 20px;">
                <h3 style="color: #2D3748; margin: 0 0 8px 0; font-size: 16px;">SMTP Configuration Verified!</h3>
                <p style="color: #4A5568; font-size: 14px; margin: 0;">
                    Your SMTP email server configuration is working properly. The salon will receive automated daily anniversary digests and alert notifications seamlessly.
                </p>
            </div>
            <p style="font-size: 12px; color: #A0AEC0; text-align: center; margin-top: 24px;">
                Sent from Safiya's Makeover Bridal CRM &bull; Oracle Cloud Infrastructure
            </p>
        </div>
        """
        return cls.send_email(recipient, subject, html, "SMTP Configuration Verified! Your Safiya's Makeover CRM can now send emails.")

    @classmethod
    def send_owner_daily_digest(
        cls,
        todays_anniversaries: List[Dict[str, Any]],
        upcoming_anniversaries: List[Dict[str, Any]],
        missing_dates_count: int,
    ) -> Dict[str, Any]:
        """Compiles and sends the daily summary digest to the salon owner."""
        config = cls.get_smtp_config()
        owner_email = config["owner_email"]

        if not owner_email:
            return {"success": False, "error": "Salon owner email is not configured"}

        today_rows = ""
        if todays_anniversaries:
            for item in todays_anniversaries:
                c = item["customer"]
                today_rows += f"""
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #EDF2F7; font-weight: 600; color: #2D3748;">{c.full_name}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #EDF2F7; color: #4A5568;">{c.mobile_number}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #EDF2F7; color: #D4738C; font-weight: bold;">{item['years']} Year(s)</td>
                    <td style="padding: 10px; border-bottom: 1px solid #EDF2F7; color: #4A5568;">{c.service_package or 'Bridal'}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #EDF2F7; text-align: center;">
                        <a href="{c.whatsapp_click_url}" style="background-color: #25D366; color: white; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 12px;">WhatsApp</a>
                    </td>
                </tr>
                """
        else:
            today_rows = "<tr><td colspan='5' style='padding: 16px; text-align: center; color: #A0AEC0;'>No wedding anniversaries today.</td></tr>"

        upcoming_rows = ""
        if upcoming_anniversaries:
            for item in upcoming_anniversaries[:10]: # Top 10 upcoming
                c = item["customer"]
                anniv_fmt = item["anniversary_date"].strftime("%d/%m/%Y")
                upcoming_rows += f"""
                <tr>
                    <td style="padding: 8px 10px; border-bottom: 1px solid #EDF2F7; color: #2D3748;">{c.full_name}</td>
                    <td style="padding: 8px 10px; border-bottom: 1px solid #EDF2F7; color: #4A5568;">{anniv_fmt} (In {item['days_away']}d)</td>
                    <td style="padding: 8px 10px; border-bottom: 1px solid #EDF2F7; color: #718096;">{c.mobile_number}</td>
                </tr>
                """
        else:
            upcoming_rows = "<tr><td colspan='3' style='padding: 12px; text-align: center; color: #A0AEC0;'>No upcoming anniversaries in the next 7 days.</td></tr>"

        subject = f"💍 Bridal Anniversary Digest ({len(todays_anniversaries)} Today, {len(upcoming_anniversaries)} Upcoming)"

        html = f"""
        <div style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 650px; margin: 0 auto; padding: 20px; border: 1px solid #E2E8F0; border-radius: 8px; background-color: #FFFFFF;">
            <div style="background: linear-gradient(135deg, #D4738C 0%, #B8536C 100%); padding: 18px; border-radius: 6px; color: white; text-align: center; margin-bottom: 20px;">
                <h2 style="margin: 0; font-size: 20px; letter-spacing: 0.5px;">Safiya's Makeover</h2>
                <p style="margin: 4px 0 0 0; font-size: 13px; opacity: 0.9;">Daily Wedding Anniversary &amp; Bridal Digest</p>
            </div>

            <div style="margin-bottom: 24px;">
                <h3 style="color: #2D3748; font-size: 16px; border-bottom: 2px solid #FDF7F8; padding-bottom: 8px; margin-bottom: 12px;">
                    🎉 Today's Wedding Anniversaries ({len(todays_anniversaries)})
                </h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background-color: #F8FAFC; color: #718096; text-align: left;">
                            <th style="padding: 8px 10px;">Bride</th>
                            <th style="padding: 8px 10px;">Mobile</th>
                            <th style="padding: 8px 10px;">Anniversary</th>
                            <th style="padding: 8px 10px;">Package</th>
                            <th style="padding: 8px 10px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {today_rows}
                    </tbody>
                </table>
            </div>

            <div style="margin-bottom: 24px;">
                <h3 style="color: #2D3748; font-size: 15px; border-bottom: 2px solid #FDF7F8; padding-bottom: 6px; margin-bottom: 10px;">
                    📅 Upcoming 7-Day Anniversaries ({len(upcoming_anniversaries)})
                </h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background-color: #F8FAFC; color: #718096; text-align: left;">
                            <th style="padding: 6px 10px;">Bride</th>
                            <th style="padding: 6px 10px;">Anniversary Date</th>
                            <th style="padding: 6px 10px;">Mobile</th>
                        </tr>
                    </thead>
                    <tbody>
                        {upcoming_rows}
                    </tbody>
                </table>
            </div>

            {f'<div style="background-color: #FFF5F5; border-left: 4px solid #E53E3E; padding: 10px 14px; border-radius: 4px; margin-top: 16px;"><p style="margin: 0; color: #C53030; font-size: 13px;"><strong>Notice:</strong> There are <strong>{missing_dates_count}</strong> customer record(s) with missing wedding dates requiring salon review.</p></div>' if missing_dates_count > 0 else ''}

            <p style="font-size: 11px; color: #A0AEC0; text-align: center; margin-top: 24px; border-top: 1px solid #EDF2F7; padding-top: 12px;">
                Safiya's Makeover Bridal CRM &bull; Automated Daily Notification
            </p>
        </div>
        """

        return cls.send_email(owner_email, subject, html)
