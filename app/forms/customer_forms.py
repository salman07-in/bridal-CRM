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


ALL_PACKAGES = [
    # 1. BRIDAL HD MAKEUP
    ("Bridal HD - Kryolan", "Bridal HD - Kryolan (₹8,000)", 8000, "Bridal Makeup", "1. BRIDAL HD MAKEUP"),
    ("Bridal HD - Forever 52", "Bridal HD - Forever 52 (₹9,500)", 9500, "Bridal Makeup", "1. BRIDAL HD MAKEUP"),
    ("Bridal HD - Charmacy Milano", "Bridal HD - Charmacy Milano (₹10,000)", 10000, "Bridal Makeup", "1. BRIDAL HD MAKEUP"),
    ("Bridal HD - P.A.C", "Bridal HD - P.A.C (₹11,000)", 11000, "Bridal Makeup", "1. BRIDAL HD MAKEUP"),
    ("Bridal HD - M.A.C", "Bridal HD - M.A.C (₹12,000)", 12000, "Bridal Makeup", "1. BRIDAL HD MAKEUP"),
    ("Bridal HD - Huda Beauty", "Bridal HD - Huda Beauty (₹13,500)", 13500, "Bridal Makeup", "1. BRIDAL HD MAKEUP"),
    ("Bridal HD - Makeup Studio", "Bridal HD - Makeup Studio (₹15,000)", 15000, "Bridal Makeup", "1. BRIDAL HD MAKEUP"),
    ("Bridal HD - Nars", "Bridal HD - NARS (₹17,500)", 17500, "Bridal Makeup", "1. BRIDAL HD MAKEUP"),
    ("Bridal HD - Bobbi Brown", "Bridal HD - Bobbi Brown (₹20,000)", 20000, "Bridal Makeup", "1. BRIDAL HD MAKEUP"),
    ("Bridal HD - Bobby Brown", "Bridal HD - Bobbi Brown (₹20,000)", 20000, "Bridal Makeup", "1. BRIDAL HD MAKEUP"),

    # 2. BRIDAL AIRBRUSH MAKEUP
    ("Bridal Airbrush - Kryolan", "Bridal Airbrush - Kryolan (₹10,000)", 10000, "Bridal Makeup", "2. BRIDAL AIRBRUSH MAKEUP"),
    ("Bridal Airbrush - Forever 52", "Bridal Airbrush - Forever 52 (₹11,000)", 11000, "Bridal Makeup", "2. BRIDAL AIRBRUSH MAKEUP"),
    ("Bridal Airbrush - Charmacy Milano", "Bridal Airbrush - Charmacy Milano (₹12,000)", 12000, "Bridal Makeup", "2. BRIDAL AIRBRUSH MAKEUP"),
    ("Bridal Airbrush - P.A.C", "Bridal Airbrush - P.A.C (₹12,000)", 12000, "Bridal Makeup", "2. BRIDAL AIRBRUSH MAKEUP"),
    ("Bridal Airbrush - M.A.C", "Bridal Airbrush - M.A.C (₹13,500)", 13500, "Bridal Makeup", "2. BRIDAL AIRBRUSH MAKEUP"),
    ("Bridal Airbrush - Huda Beauty", "Bridal Airbrush - Huda Beauty (₹15,000)", 15000, "Bridal Makeup", "2. BRIDAL AIRBRUSH MAKEUP"),
    ("Bridal Airbrush - Makeup Studio", "Bridal Airbrush - Makeup Studio (₹16,500)", 16500, "Bridal Makeup", "2. BRIDAL AIRBRUSH MAKEUP"),
    ("Bridal Airbrush - Nars", "Bridal Airbrush - NARS (₹20,000)", 20000, "Bridal Makeup", "2. BRIDAL AIRBRUSH MAKEUP"),
    ("Bridal Airbrush - Bobbi Brown", "Bridal Airbrush - Bobbi Brown (₹25,000)", 25000, "Bridal Makeup", "2. BRIDAL AIRBRUSH MAKEUP"),
    ("Bridal Airbrush - Bobby Brown", "Bridal Airbrush - Bobbi Brown (₹25,000)", 25000, "Bridal Makeup", "2. BRIDAL AIRBRUSH MAKEUP"),

    # 3. ENGAGEMENT HD MAKEUP
    ("Engagement HD - Kryolan", "Engagement HD - Kryolan (₹5,000)", 5000, "Engagement Makeup", "3. ENGAGEMENT HD MAKEUP"),
    ("Engagement HD - Forever 52", "Engagement HD - Forever 52 (₹6,000)", 6000, "Engagement Makeup", "3. ENGAGEMENT HD MAKEUP"),
    ("Engagement HD - Charmacy Milano", "Engagement HD - Charmacy Milano (₹6,500)", 6500, "Engagement Makeup", "3. ENGAGEMENT HD MAKEUP"),
    ("Engagement HD - P.A.C", "Engagement HD - P.A.C (₹6,500)", 6500, "Engagement Makeup", "3. ENGAGEMENT HD MAKEUP"),
    ("Engagement HD - M.A.C", "Engagement HD - M.A.C (₹6,500)", 6500, "Engagement Makeup", "3. ENGAGEMENT HD MAKEUP"),
    ("Engagement HD - Huda Beauty", "Engagement HD - Huda Beauty (₹7,000)", 7000, "Engagement Makeup", "3. ENGAGEMENT HD MAKEUP"),
    ("Engagement HD - Makeup Studio", "Engagement HD - Makeup Studio (₹7,500)", 7500, "Engagement Makeup", "3. ENGAGEMENT HD MAKEUP"),
    ("Engagement HD - Nars", "Engagement HD - NARS (₹8,000)", 8000, "Engagement Makeup", "3. ENGAGEMENT HD MAKEUP"),
    ("Engagement HD - Bobbi Brown", "Engagement HD - Bobbi Brown (₹8,500)", 8500, "Engagement Makeup", "3. ENGAGEMENT HD MAKEUP"),

    # 4. ENGAGEMENT AIRBRUSH MAKEUP
    ("Engagement Airbrush - Kryolan", "Engagement Airbrush - Kryolan (₹7,000)", 7000, "Engagement Makeup", "4. ENGAGEMENT AIRBRUSH MAKEUP"),
    ("Engagement Airbrush - Forever 52", "Engagement Airbrush - Forever 52 (₹7,500)", 7500, "Engagement Makeup", "4. ENGAGEMENT AIRBRUSH MAKEUP"),
    ("Engagement Airbrush - Charmacy Milano", "Engagement Airbrush - Charmacy Milano (₹8,000)", 8000, "Engagement Makeup", "4. ENGAGEMENT AIRBRUSH MAKEUP"),
    ("Engagement Airbrush - P.A.C", "Engagement Airbrush - P.A.C (₹8,000)", 8000, "Engagement Makeup", "4. ENGAGEMENT AIRBRUSH MAKEUP"),
    ("Engagement Airbrush - M.A.C", "Engagement Airbrush - M.A.C (₹8,500)", 8500, "Engagement Makeup", "4. ENGAGEMENT AIRBRUSH MAKEUP"),
    ("Engagement Airbrush - Huda Beauty", "Engagement Airbrush - Huda Beauty (₹9,000)", 9000, "Engagement Makeup", "4. ENGAGEMENT AIRBRUSH MAKEUP"),
    ("Engagement Airbrush - Makeup Studio", "Engagement Airbrush - Makeup Studio (₹10,000)", 10000, "Engagement Makeup", "4. ENGAGEMENT AIRBRUSH MAKEUP"),
    ("Engagement Airbrush - Nars", "Engagement Airbrush - NARS (₹10,000)", 10000, "Engagement Makeup", "4. ENGAGEMENT AIRBRUSH MAKEUP"),
    ("Engagement Airbrush - Bobbi Brown", "Engagement Airbrush - Bobbi Brown (₹11,000)", 11000, "Engagement Makeup", "4. ENGAGEMENT AIRBRUSH MAKEUP"),

    # 5. PARTY NORMAL MAKEUP
    ("Party Normal - Kryolan", "Party Normal - Kryolan (₹2,000)", 2000, "Party Makeup", "5. PARTY NORMAL MAKEUP"),
    ("Party Normal - Forever 52", "Party Normal - Forever 52 (₹2,000)", 2000, "Party Makeup", "5. PARTY NORMAL MAKEUP"),
    ("Party Normal - Charmacy Milano", "Party Normal - Charmacy Milano (₹2,500)", 2500, "Party Makeup", "5. PARTY NORMAL MAKEUP"),
    ("Party Normal - P.A.C", "Party Normal - P.A.C (₹2,500)", 2500, "Party Makeup", "5. PARTY NORMAL MAKEUP"),
    ("Party Normal - M.A.C", "Party Normal - M.A.C (₹3,000)", 3000, "Party Makeup", "5. PARTY NORMAL MAKEUP"),
    ("Party Normal - Huda Beauty", "Party Normal - Huda Beauty (₹3,000)", 3000, "Party Makeup", "5. PARTY NORMAL MAKEUP"),
    ("Party Normal - Makeup Studio", "Party Normal - Makeup Studio (₹3,000)", 3000, "Party Makeup", "5. PARTY NORMAL MAKEUP"),
    ("Party Normal - Nars", "Party Normal - NARS (₹3,000)", 3000, "Party Makeup", "5. PARTY NORMAL MAKEUP"),
    ("Party Normal - Bobbi Brown", "Party Normal - Bobbi Brown (₹3,500)", 3500, "Party Makeup", "5. PARTY NORMAL MAKEUP"),

    # 6. PARTY HD MAKEUP
    ("Party HD - Kryolan", "Party HD - Kryolan (₹3,000)", 3000, "Party Makeup", "6. PARTY HD MAKEUP"),
    ("Party HD - Forever 52", "Party HD - Forever 52 (₹3,000)", 3000, "Party Makeup", "6. PARTY HD MAKEUP"),
    ("Party HD - Charmacy Milano", "Party HD - Charmacy Milano (₹3,500)", 3500, "Party Makeup", "6. PARTY HD MAKEUP"),
    ("Party HD - P.A.C", "Party HD - P.A.C (₹3,000)", 3000, "Party Makeup", "6. PARTY HD MAKEUP"),
    ("Party HD - M.A.C", "Party HD - M.A.C (₹4,000)", 4000, "Party Makeup", "6. PARTY HD MAKEUP"),
    ("Party HD - Huda Beauty", "Party HD - Huda Beauty (₹4,000)", 4000, "Party Makeup", "6. PARTY HD MAKEUP"),
    ("Party HD - Makeup Studio", "Party HD - Makeup Studio (₹4,000)", 4000, "Party Makeup", "6. PARTY HD MAKEUP"),
    ("Party HD - Nars", "Party HD - NARS (₹4,500)", 4500, "Party Makeup", "6. PARTY HD MAKEUP"),
    ("Party HD - Bobbi Brown", "Party HD - Bobbi Brown (₹4,500)", 4500, "Party Makeup", "6. PARTY HD MAKEUP"),

    # 7. PARTY AIRBRUSH MAKEUP
    ("Party Airbrush - Kryolan", "Party Airbrush - Kryolan (₹4,000)", 4000, "Party Makeup", "7. PARTY AIRBRUSH MAKEUP"),
    ("Party Airbrush - Forever 52", "Party Airbrush - Forever 52 (₹4,000)", 4000, "Party Makeup", "7. PARTY AIRBRUSH MAKEUP"),
    ("Party Airbrush - Charmacy Milano", "Party Airbrush - Charmacy Milano (₹4,500)", 4500, "Party Makeup", "7. PARTY AIRBRUSH MAKEUP"),
    ("Party Airbrush - P.A.C", "Party Airbrush - P.A.C (₹3,500)", 3500, "Party Makeup", "7. PARTY AIRBRUSH MAKEUP"),
    ("Party Airbrush - M.A.C", "Party Airbrush - M.A.C (₹4,500)", 4500, "Party Makeup", "7. PARTY AIRBRUSH MAKEUP"),
    ("Party Airbrush - Huda Beauty", "Party Airbrush - Huda Beauty (₹5,000)", 5000, "Party Makeup", "7. PARTY AIRBRUSH MAKEUP"),
    ("Party Airbrush - Makeup Studio", "Party Airbrush - Makeup Studio (₹5,000)", 5000, "Party Makeup", "7. PARTY AIRBRUSH MAKEUP"),
    ("Party Airbrush - Nars Foundation", "Party Airbrush - NARS Foundation (₹5,500)", 5500, "Party Makeup", "7. PARTY AIRBRUSH MAKEUP"),
    ("Party Airbrush - Bobbi Brown", "Party Airbrush - Bobbi Brown (₹6,000)", 6000, "Party Makeup", "7. PARTY AIRBRUSH MAKEUP"),
]

BRIDAL_PACKAGES = [pkg for pkg in ALL_PACKAGES if pkg[3] == "Bridal Makeup"]
ENGAGEMENT_PACKAGES = [pkg for pkg in ALL_PACKAGES if pkg[3] == "Engagement Makeup"]
PARTY_PACKAGES = [pkg for pkg in ALL_PACKAGES if pkg[3] == "Party Makeup"]

PACKAGE_PRICE_MAP = {pkg[0]: pkg[2] for pkg in ALL_PACKAGES}
PACKAGE_PRICE_MAP["Bridal HD - Bobby Brown"] = 20000
PACKAGE_PRICE_MAP["Bridal Airbrush - Bobby Brown"] = 25000


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
        choices=[SelectChoice("", "-- Select Package --")] + [
            SelectChoice(pkg[0], pkg[1]) for pkg in ALL_PACKAGES
        ],
        validate_choice=False,
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
