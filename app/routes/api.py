from functools import wraps
from datetime import datetime, timezone
from flask import Blueprint, request, jsonify, current_app
from app.models.setting import Setting
from app.models.reminder_log import ReminderLog
from app.models.customer import Customer
from app.services.anniversary import AnniversaryService
from app.services.reminder_service import ReminderService
from app.extensions import db, csrf

api_bp = Blueprint("api", __name__, url_prefix="/api/v1")

# Disable CSRF for external machine-to-machine API calls
csrf.exempt(api_bp)


def require_api_key(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        # Look in X-API-KEY header, Authorization Bearer, or query param
        api_key = request.headers.get("X-API-KEY")
        if not api_key:
            auth_header = request.headers.get("Authorization", "")
            if auth_header.startswith("Bearer "):
                api_key = auth_header.replace("Bearer ", "").strip()
        if not api_key:
            api_key = request.args.get("api_key")

        # Configured key
        expected_key = Setting.get_value("crm_api_key", current_app.config.get("CRM_API_KEY", ""))

        if not expected_key or not api_key or api_key != expected_key:
            return jsonify({
                "status": "error",
                "message": "Unauthorized. A valid X-API-KEY header or Bearer token is required.",
            }), 401

        return f(*args, **kwargs)
    return decorated_function


@api_bp.route("/health", methods=["GET"])
def health():
    return jsonify({
        "status": "healthy",
        "service": "Safiya's Makeover Bridal CRM",
        "timestamp": datetime.now(timezone.utc).isoformat(),
    }), 200


@api_bp.route("/stats", methods=["GET"])
@require_api_key
def get_stats():
    summary = AnniversaryService.get_dashboard_summary()
    return jsonify({
        "status": "success",
        "today": summary["today"].isoformat(),
        "total_brides": summary["total_brides"],
        "todays_anniversaries_count": summary["today_count"],
        "upcoming_7day_count": summary["upcoming_7day_count"],
        "missing_wedding_dates_count": summary["missing_dates_count"],
        "pending_reminders_count": summary["pending_reminders_count"],
    }), 200


@api_bp.route("/reminders/due", methods=["GET"])
@require_api_key
def get_due_reminders():
    """Fetch pending reminders ready for n8n or external notification workflows."""
    today = AnniversaryService.get_kolkata_today()

    # Automatically scan & queue today's reminders if not queued yet
    ReminderService.process_daily_reminders()

    channel = request.args.get("channel")

    # Query pending logs scheduled for today or earlier
    query = ReminderLog.query.filter(
        ReminderLog.status.in_(["pending", "failed"]),
        ReminderLog.scheduled_for <= today,
    )
    if channel:
        query = query.filter(ReminderLog.channel == channel)
    else:
        # Exclude internal owner digest from client messaging feed by default
        query = query.filter(ReminderLog.channel != "owner_digest")

    due_logs = query.all()

    data = []
    for log in due_logs:
        cust = log.customer
        if not cust:
            continue
        data.append({
            "reminder_id": log.id,
            "idempotency_key": log.idempotency_key,
            "channel": log.channel,
            "reminder_type": log.reminder_type,
            "scheduled_for": log.scheduled_for.isoformat(),
            "retry_count": log.retry_count,
            "customer": {
                "id": cust.id,
                "customer_code": cust.customer_code,
                "full_name": cust.full_name,
                "clean_phone": cust.clean_phone,
                "international_phone": cust.international_phone,
                "wedding_date": cust.wedding_date.isoformat() if cust.wedding_date else None,
                "years_married": cust.get_anniversary_year_count(log.anniversary_year),
                "whatsapp_consent": cust.whatsapp_consent,
                "service_package": cust.service_package,
                "salon_branch": cust.salon_branch,
            },
        })

    return jsonify({
        "status": "success",
        "count": len(data),
        "reminders": data,
    }), 200


@api_bp.route("/reminders/mark-sent", methods=["POST"])
@require_api_key
def mark_reminder_sent():
    """Webhook callback for n8n or external services after dispatching a message."""
    payload = request.get_json(silent=True) or {}
    reminder_id = payload.get("reminder_id")
    idempotency_key = payload.get("idempotency_key")
    metadata = payload.get("metadata", "")

    if not reminder_id and not idempotency_key:
        return jsonify({"status": "error", "message": "reminder_id or idempotency_key is required"}), 400

    if reminder_id:
        log = ReminderLog.query.get(reminder_id)
    else:
        log = ReminderLog.query.filter_by(idempotency_key=idempotency_key).first()

    if not log:
        return jsonify({"status": "error", "message": "Reminder log not found"}), 404

    log.mark_sent(metadata=str(metadata) if metadata else None)
    db.session.commit()

    return jsonify({
        "status": "success",
        "message": "Reminder marked as sent",
        "reminder": log.to_dict(),
    }), 200


@api_bp.route("/reminders/trigger-daily", methods=["POST"])
@require_api_key
def trigger_daily_reminders():
    """Endpoint for scheduled cron jobs (e.g. Oracle Linux crontab or n8n cron node)."""
    result = ReminderService.process_daily_reminders()
    return jsonify({
        "status": "success",
        "result": result,
    }), 200
