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


BRIDAL_PACKAGES = [
    # Bridal H.D Makeup
    ("Bridal HD - Kryolan", "Bridal H.D - Kryolan (₹8,000)", 8000),
    ("Bridal HD - Forever 52", "Bridal H.D - Forever 52 (₹9,500)", 9500),
    ("Bridal HD - Charmacy Milano", "Bridal H.D - Charmacy Milano (₹10,000)", 10000),
    ("Bridal HD - P.A.C", "Bridal H.D - P.A.C (₹11,000)", 11000),
    ("Bridal HD - M.A.C", "Bridal H.D - M.A.C (₹12,000)", 12000),
    ("Bridal HD - Huda Beauty", "Bridal H.D - Huda Beauty (₹13,500)", 13500),
    ("Bridal HD - Makeup Studio", "Bridal H.D - Makeup Studio (₹15,000)", 15000),
    ("Bridal HD - Nars", "Bridal H.D - Nars (₹17,500)", 17500),
    ("Bridal HD - Bobby Brown", "Bridal H.D - Bobby Brown (₹20,000)", 20000),

    # Bridal Airbrush Makeup
    ("Bridal Airbrush - Kryolan", "Bridal Airbrush - Kryolan (₹10,000)", 10000),
    ("Bridal Airbrush - Forever 52", "Bridal Airbrush - Forever 52 (₹11,000)", 11000),
    ("Bridal Airbrush - Charmacy Milano", "Bridal Airbrush - Charmacy Milano (₹12,000)", 12000),
    ("Bridal Airbrush - P.A.C", "Bridal Airbrush - P.A.C (₹12,000)", 12000),
    ("Bridal Airbrush - M.A.C", "Bridal Airbrush - M.A.C (₹13,500)", 13500),
    ("Bridal Airbrush - Huda Beauty", "Bridal Airbrush - Huda Beauty (₹15,000)", 15000),
    ("Bridal Airbrush - Makeup Studio", "Bridal Airbrush - Makeup Studio (₹16,500)", 16500),
    ("Bridal Airbrush - Nars", "Bridal Airbrush - Nars (₹20,000)", 20000),
    ("Bridal Airbrush - Bobby Brown", "Bridal Airbrush - Bobby Brown (₹25,000)", 25000),
]

PACKAGE_PRICE_MAP = {pkg[0]: pkg[2] for pkg in BRIDAL_PACKAGES}


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

    slip_number = StringField(
        "Booking Slip / Receipt No.",
        validators=[Optional(), Length(max=50)],
        render_kw={"placeholder": "e.g. SLIP-1042 / BK-089", "class": "form-control", "id": "brideSlipInput"},
    )

    booking_type = SelectField(
        "Booking / Service Category",
        choices=[
            SelectChoice("Bridal Makeup", "Bridal Makeup (Main Wedding)"),
            SelectChoice("Engagement Makeup", "Engagement / Roka Makeup"),
            SelectChoice("Party Makeup", "Party Makeup / Reception Guest"),
        ],
        default="Bridal Makeup",
        validators=[DataRequired(message="Please select service category.")],
        render_kw={"class": "form-select", "id": "brideBookingTypeInput"},
    )

    wedding_date = DateField(
        "Wedding Date (Sole Anniversary Trigger)",
        validators=[Optional()],
        format="%Y-%m-%d",
        render_kw={"class": "form-control", "type": "date", "id": "brideWeddingDateInput"},
    )

    makeup_date = DateField(
        "Makeup / Service Date",
        validators=[Optional()],
        format="%Y-%m-%d",
        render_kw={"class": "form-control", "type": "date", "id": "brideMakeupDateInput"},
    )

    service_package = SelectField(
        "Service / Package",
        choices=[SelectChoice("", "-- Select Bridal Package --")] + [
            SelectChoice(pkg[0], pkg[1]) for pkg in BRIDAL_PACKAGES
        ],
        validators=[Optional()],
        render_kw={"class": "form-select", "id": "bridePackageInput"},
    )

    amount = DecimalField(
        "Total Amount (₹ INR)",
        validators=[Optional()],
        places=2,
        render_kw={"placeholder": "e.g. 15000", "class": "form-control", "step": "500", "id": "brideAmountInput"},
    )

    advance_amount = DecimalField(
        "Advance Paid (₹ INR)",
        validators=[Optional()],
        places=2,
        default=0,
        render_kw={"placeholder": "e.g. 5000", "class": "form-control", "step": "500", "id": "brideAdvanceInput"},
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

    pre_makeup_reminder_enabled = BooleanField(
        "Send 1-day advance appointment reminder before makeup date",
        default=True,
    )

    whatsapp_consent = BooleanField(
        "Client has given WhatsApp consent for reminders and greetings",
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
