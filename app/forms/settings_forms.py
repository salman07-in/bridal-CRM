from flask_wtf import FlaskForm
from wtforms import (
    StringField,
    IntegerField,
    BooleanField,
    PasswordField,
    SubmitField,
)
from wtforms.validators import DataRequired, Optional, Email, NumberRange


class SettingsForm(FlaskForm):
    # Salon Details
    salon_name = StringField("Salon / Studio Name", validators=[DataRequired()], render_kw={"class": "form-control"})
    salon_tagline = StringField("Tagline / Subtitle", validators=[Optional()], render_kw={"class": "form-control"})
    salon_phone = StringField("Contact Phone", validators=[DataRequired()], render_kw={"class": "form-control"})
    salon_branch = StringField("Primary Branch", validators=[Optional()], render_kw={"class": "form-control"})
    salon_owner_email = StringField(
        "Owner Notification Email",
        validators=[DataRequired(), Email()],
        render_kw={"class": "form-control", "placeholder": "owner@safiyasmakeover.com"},
    )

    # Reminder Rules
    reminder_advance_days = IntegerField(
        "Upcoming Reminder Forecast (Days)",
        validators=[DataRequired(), NumberRange(min=1, max=30)],
        default=7,
        render_kw={"class": "form-control"},
    )
    send_owner_daily_digest = BooleanField("Send Automated Daily Digest to Salon Owner", default=True)

    # SMTP Email Server
    smtp_enabled = BooleanField("Enable SMTP Email Sending")
    smtp_host = StringField("SMTP Server Host", validators=[Optional()], render_kw={"class": "form-control", "placeholder": "smtp.gmail.com"})
    smtp_port = IntegerField("SMTP Server Port", validators=[Optional()], default=587, render_kw={"class": "form-control"})
    smtp_use_tls = BooleanField("Use STARTTLS (Port 587)", default=True)
    smtp_use_ssl = BooleanField("Use SSL/TLS (Port 465)", default=False)
    smtp_username = StringField("SMTP Username / Email", validators=[Optional()], render_kw={"class": "form-control"})
    smtp_password = PasswordField("SMTP Password / App Password", validators=[Optional()], render_kw={"class": "form-control"})
    smtp_from_email = StringField("From Sender Email", validators=[Optional(), Email()], render_kw={"class": "form-control"})
    smtp_from_name = StringField("From Sender Display Name", validators=[Optional()], render_kw={"class": "form-control"})

    # WhatsApp Business Cloud API (Official)
    whatsapp_enabled = BooleanField("Enable Official WhatsApp Cloud API Dispatch")
    whatsapp_phone_number_id = StringField("Phone Number ID", validators=[Optional()], render_kw={"class": "form-control"})
    whatsapp_access_token = PasswordField("System User Access Token", validators=[Optional()], render_kw={"class": "form-control"})
    whatsapp_template_name = StringField("Approved Template Name", validators=[Optional()], default="anniversary_greeting_v1", render_kw={"class": "form-control"})

    # API Keys
    crm_api_key = StringField("Internal API Key for n8n / Automated Cron", validators=[Optional()], render_kw={"class": "form-control"})

    submit = SubmitField("Save Salon Settings")


class TestEmailForm(FlaskForm):
    test_recipient = StringField(
        "Send Verification Test Email To",
        validators=[DataRequired(), Email()],
        render_kw={"placeholder": "your-email@domain.com", "class": "form-control"},
    )
    submit_test = SubmitField("Send Test Email")
