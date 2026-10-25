import re
from flask_wtf import FlaskForm
from wtforms import (
    StringField,
    DateField,
    DecimalField,
    SelectField,
    SelectChoice,
    TextAreaField,
    BooleanField,
    SubmitField,
)
from wtforms.validators import DataRequired, Optional, Length, ValidationError
from app.models.customer import Customer


def validate_indian_mobile(form, field):
    """Validates 10-digit Indian mobile number."""
    val = (field.data or "").strip()
    digits = re.sub(r"[^\d]", "", val)

    if len(digits) == 12 and digits.startswith("91"):
        digits = digits[2:]
    elif len(digits) == 11 and digits.startswith("0"):
        digits = digits[1:]

    if len(digits) != 10:
        raise ValidationError("Mobile number must be exactly 10 digits.")
    if digits[0] not in "6789":
        raise ValidationError("Indian mobile numbers must start with 6, 7, 8, or 9.")


class CustomerForm(FlaskForm):
    full_name = StringField(
        "Bride Full Name *",
        validators=[
            DataRequired(message="Bride's full name is required."),
            Length(min=2, max=150, message="Name must be between 2 and 150 characters."),
        ],
        render_kw={"placeholder": "e.g. Ayesha Khan", "class": "form-control", "autofocus": True},
    )

    mobile_number = StringField(
        "Mobile Number *",
        validators=[
            DataRequired(message="Mobile number is required."),
            validate_indian_mobile,
        ],
        render_kw={"placeholder": "e.g. 9876543210", "class": "form-control"},
    )

    wedding_date = DateField(
        "Wedding Date (Sole Anniversary Trigger)",
        validators=[Optional()],
        format="%Y-%m-%d",
        render_kw={"class": "form-control", "type": "date"},
    )

    makeup_date = DateField(
        "Makeup / Service Date",
        validators=[Optional()],
        format="%Y-%m-%d",
        render_kw={"class": "form-control", "type": "date"},
    )

    service_package = SelectField(
        "Service / Package",
        choices=[
            SelectChoice("", "-- Select Package --"),
            SelectChoice("Signature Bridal HD", "Signature Bridal HD"),
            SelectChoice("Airbrush Royal Bridal", "Airbrush Royal Bridal"),
            SelectChoice("Classic Bridal Makeover", "Classic Bridal Makeover"),
            SelectChoice("Nikah / Reception Glam", "Nikah / Reception Glam"),
            SelectChoice("Pre-Bridal Glow Care", "Pre-Bridal Glow Care"),
            SelectChoice("Engagement / Sangeet Look", "Engagement / Sangeet Look"),
            SelectChoice("Party Makeup", "Party Makeup"),
            SelectChoice("Custom Bridal Package", "Custom Bridal Package"),
        ],
        validators=[Optional()],
        render_kw={"class": "form-select"},
    )

    amount = DecimalField(
        "Total Amount (₹ INR)",
        validators=[Optional()],
        places=2,
        render_kw={"placeholder": "e.g. 25000", "class": "form-control", "step": "500"},
    )

    salon_branch = SelectField(
        "Salon Branch",
        choices=[
            SelectChoice("Main Studio", "Main Studio"),
            SelectChoice("City Centre Branch", "City Centre Branch"),
            SelectChoice("Bridal Lounge", "Bridal Lounge"),
            SelectChoice("On-Location / Destination", "On-Location / Destination"),
        ],
        validators=[Optional()],
        render_kw={"class": "form-select"},
    )

    notes = TextAreaField(
        "Special Notes / Skin Preferences",
        validators=[Optional(), Length(max=2000)],
        render_kw={
            "rows": 3,
            "placeholder": "Notes regarding look, jewellery setting, bridal outfit colors, preferred artist, etc.",
            "class": "form-control",
        },
    )

    whatsapp_consent = BooleanField(
        "Bride has given WhatsApp consent for anniversary greetings and updates",
        default=True,
    )

    submit = SubmitField("Save Customer Record")

    def __init__(self, original_mobile=None, *args, **kwargs):
        super(CustomerForm, self).__init__(*args, **kwargs)
        self.original_mobile = original_mobile

    def validate_mobile_number(self, field):
        """Ensure uniqueness of mobile number in database (except when editing self)."""
        val = (field.data or "").strip()
        digits = re.sub(r"[^\d]", "", val)
        if len(digits) == 12 and digits.startswith("91"):
            digits = digits[2:]
        elif len(digits) == 11 and digits.startswith("0"):
            digits = digits[1:]

        if self.original_mobile and digits == self.original_mobile:
            return

        existing = Customer.query.filter_by(mobile_number=digits).first()
        if existing:
            raise ValidationError(
                f"Mobile number is already registered for bride '{existing.full_name}' ({existing.customer_code})."
            )
