from flask import Blueprint, render_template, redirect, url_for, flash, request, current_app
from flask_login import login_required, current_user
from app.models.setting import Setting
from app.models.audit_log import AuditLog
from app.forms.settings_forms import SettingsForm, TestEmailForm
from app.services.email_service import EmailService
from app.extensions import db

settings_bp = Blueprint("settings", __name__, url_prefix="/settings")


@settings_bp.route("/", methods=["GET", "POST"])
@login_required
def index():
    # Only admin users can modify salon and SMTP settings
    if not current_user.is_admin:
        flash("You need Administrator permissions to access salon configuration settings.", "warning")
        return redirect(url_for("dashboard.index"))

    form = SettingsForm()
    test_email_form = TestEmailForm()

    if request.method == "POST" and "submit" in request.form and form.validate_on_submit():
        # Salon Info
        Setting.set_value("salon_name", form.salon_name.data.strip(), "Salon display name")
        Setting.set_value("salon_tagline", form.salon_tagline.data.strip() if form.salon_tagline.data else "", "Salon subtitle")
        Setting.set_value("salon_phone", form.salon_phone.data.strip(), "Studio phone")
        Setting.set_value("salon_branch", form.salon_branch.data.strip() if form.salon_branch.data else "", "Primary branch")
        Setting.set_value("salon_owner_email", form.salon_owner_email.data.strip().lower(), "Owner notification email")

        # Reminder Rules
        Setting.set_value("reminder_advance_days", form.reminder_advance_days.data, "Upcoming reminder days forecast")
        Setting.set_value("send_owner_daily_digest", form.send_owner_daily_digest.data, "Enable owner daily digest email")

        # SMTP Email
        Setting.set_value("smtp_enabled", form.smtp_enabled.data, "Enable SMTP email")
        Setting.set_value("smtp_host", form.smtp_host.data.strip() if form.smtp_host.data else "", "SMTP server host")
        Setting.set_value("smtp_port", form.smtp_port.data if form.smtp_port.data else 587, "SMTP server port")
        Setting.set_value("smtp_use_tls", form.smtp_use_tls.data, "Use TLS")
        Setting.set_value("smtp_use_ssl", form.smtp_use_ssl.data, "Use SSL")
        Setting.set_value("smtp_username", form.smtp_username.data.strip() if form.smtp_username.data else "", "SMTP username")
        if form.smtp_password.data:
            Setting.set_value("smtp_password", form.smtp_password.data, "SMTP password")
        Setting.set_value("smtp_from_email", form.smtp_from_email.data.strip() if form.smtp_from_email.data else "", "From email")
        Setting.set_value("smtp_from_name", form.smtp_from_name.data.strip() if form.smtp_from_name.data else "", "From name")

        # WhatsApp Settings
        Setting.set_value("whatsapp_enabled", form.whatsapp_enabled.data, "WhatsApp API enabled")
        Setting.set_value("whatsapp_phone_number_id", form.whatsapp_phone_number_id.data.strip() if form.whatsapp_phone_number_id.data else "", "WhatsApp phone ID")
        if form.whatsapp_access_token.data:
            Setting.set_value("whatsapp_access_token", form.whatsapp_access_token.data.strip(), "WhatsApp API access token")
        Setting.set_value("whatsapp_template_name", form.whatsapp_template_name.data.strip() if form.whatsapp_template_name.data else "anniversary_greeting_v1", "WhatsApp template")

        # API Key
        if form.crm_api_key.data:
            Setting.set_value("crm_api_key", form.crm_api_key.data.strip(), "Internal API Key for n8n")

        AuditLog.log(
            action="update_settings",
            entity_type="setting",
            user_id=current_user.id,
            ip_address=request.remote_addr,
        )
        db.session.commit()

        flash("Salon settings and reminder preferences updated successfully!", "success")
        return redirect(url_for("settings.index"))

    elif request.method == "GET":
        cfg = current_app.config
        form.salon_name.data = Setting.get_value("salon_name", cfg.get("SALON_NAME", "Safiya's Makeover"))
        form.salon_tagline.data = Setting.get_value("salon_tagline", cfg.get("SALON_TAGLINE", "Bridal & Makeover Studio"))
        form.salon_phone.data = Setting.get_value("salon_phone", cfg.get("SALON_PHONE", "+91 98765 43210"))
        form.salon_branch.data = Setting.get_value("salon_branch", cfg.get("SALON_BRANCH", "Main Branch"))
        form.salon_owner_email.data = Setting.get_value("salon_owner_email", cfg.get("SALON_OWNER_EMAIL", "owner@safiyasmakeover.com"))

        form.reminder_advance_days.data = Setting.get_int("reminder_advance_days", cfg.get("REMINDER_ADVANCE_DAYS", 7))
        form.send_owner_daily_digest.data = Setting.get_bool("send_owner_daily_digest", cfg.get("SEND_OWNER_DAILY_DIGEST", True))

        form.smtp_enabled.data = Setting.get_bool("smtp_enabled", cfg.get("SMTP_ENABLED", False))
        form.smtp_host.data = Setting.get_value("smtp_host", cfg.get("SMTP_HOST", "smtp.gmail.com"))
        form.smtp_port.data = Setting.get_int("smtp_port", cfg.get("SMTP_PORT", 587))
        form.smtp_use_tls.data = Setting.get_bool("smtp_use_tls", cfg.get("SMTP_USE_TLS", True))
        form.smtp_use_ssl.data = Setting.get_bool("smtp_use_ssl", cfg.get("SMTP_USE_SSL", False))
        form.smtp_username.data = Setting.get_value("smtp_username", cfg.get("SMTP_USERNAME", ""))
        form.smtp_from_email.data = Setting.get_value("smtp_from_email", cfg.get("SMTP_FROM_EMAIL", "notifications@safiyasmakeover.com"))
        form.smtp_from_name.data = Setting.get_value("smtp_from_name", cfg.get("SMTP_FROM_NAME", "Safiya's Makeover"))

        form.whatsapp_enabled.data = Setting.get_bool("whatsapp_enabled", cfg.get("WHATSAPP_ENABLED", False))
        form.whatsapp_phone_number_id.data = Setting.get_value("whatsapp_phone_number_id", cfg.get("WHATSAPP_PHONE_NUMBER_ID", ""))
        form.whatsapp_template_name.data = Setting.get_value("whatsapp_template_name", cfg.get("WHATSAPP_TEMPLATE_NAME", "anniversary_greeting_v1"))

        form.crm_api_key.data = Setting.get_value("crm_api_key", cfg.get("CRM_API_KEY", ""))

    return render_template(
        "settings/index.html",
        form=form,
        test_email_form=test_email_form,
    )


@settings_bp.route("/test-email", methods=["POST"])
@login_required
def test_email():
    """Send a verification test email to verify SMTP host, port, and credentials."""
    if not current_user.is_admin:
        flash("Admin permissions required.", "danger")
        return redirect(url_for("settings.index"))

    form = TestEmailForm()
    if form.validate_on_submit():
        recipient = form.test_recipient.data.strip()
        result = EmailService.send_test_email(recipient)
        if result.get("success"):
            flash(f"Test email sent successfully to {recipient}! Please check inbox/spam.", "success")
        else:
            flash(f"Failed to send test email: {result.get('error')}", "danger")
    else:
        flash("Invalid recipient email address.", "warning")

    return redirect(url_for("settings.index"))
