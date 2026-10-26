from flask import Blueprint, render_template, redirect, url_for, flash, request
from flask_login import login_required, current_user
from app.models.reminder_log import ReminderLog
from app.models.audit_log import AuditLog
from app.services.reminder_service import ReminderService
from app.extensions import db

reminders_bp = Blueprint("reminders", __name__, url_prefix="/reminders")


@reminders_bp.route("/")
@login_required
def index():
    status = request.args.get("status", "").strip()
    channel = request.args.get("channel", "").strip()
    page = request.args.get("page", 1, type=int)

    query = ReminderLog.query

    if status:
        query = query.filter(ReminderLog.status == status)
    if channel:
        query = query.filter(ReminderLog.channel == channel)

    query = query.order_by(ReminderLog.created_at.desc())
    pagination = query.paginate(page=page, per_page=20, error_out=False)

    # Counts
    pending_count = ReminderLog.query.filter_by(status="pending").count()
    sent_count = ReminderLog.query.filter_by(status="sent").count()
    failed_count = ReminderLog.query.filter_by(status="failed").count()

    return render_template(
        "reminders/index.html",
        pagination=pagination,
        current_status=status,
        current_channel=channel,
        pending_count=pending_count,
        sent_count=sent_count,
        failed_count=failed_count,
    )


@reminders_bp.route("/retry/<int:id>", methods=["POST"])
@login_required
def retry(id: int):
    log = db.get_or_404(ReminderLog, id)
    cust_name = log.customer.full_name if log.customer else "Unknown"

    result = ReminderService.retry_reminder(id)
    if result.get("success"):
        flash(f"Reminder for {cust_name} retried successfully!", "success")
        AuditLog.log(
            action="retry_reminder",
            entity_type="reminder",
            entity_id=log.id,
            details={"status": "sent", "customer": cust_name},
            user_id=current_user.id,
            ip_address=request.remote_addr,
        )
    else:
        flash(f"Retry attempt failed: {result.get('error')}", "danger")
        AuditLog.log(
            action="retry_reminder",
            entity_type="reminder",
            entity_id=log.id,
            details={"status": "failed", "error": result.get("error")},
            user_id=current_user.id,
            ip_address=request.remote_addr,
        )

    db.session.commit()
    return redirect(request.referrer or url_for("reminders.index"))


@reminders_bp.route("/trigger-daily", methods=["POST"])
@login_required
def trigger_daily():
    """Manual trigger from the web dashboard to process today's reminders."""
    result = ReminderService.process_daily_reminders()
    AuditLog.log(
        action="manual_trigger_daily_reminders",
        entity_type="reminder",
        details=result,
        user_id=current_user.id,
        ip_address=request.remote_addr,
    )
    db.session.commit()

    tom_count = result.get("tomorrows_count", 0)
    pre_count = result.get("pre_makeup_count", 0)
    flash(
        f"Daily reminder check complete! {result['todays_count']} today's anniversaries, "
        f"{tom_count} tomorrow's (Eve) anniversaries, {pre_count} 1-day pre-makeup appointments evaluated. "
        f"{result['whatsapp_dispatches']} WhatsApp dispatched, Owner digest: {result['owner_digest_status']}.",
        "info",
    )
    return redirect(request.referrer or url_for("reminders.index"))
