from datetime import datetime, date, timezone
from app.extensions import db


class Customer(db.Model):
    __tablename__ = "customers"

    id = db.Column(db.Integer, primary_key=True)
    customer_code = db.Column(db.String(30), unique=True, nullable=False, index=True)
    full_name = db.Column(db.String(150), nullable=False, index=True)
    mobile_number = db.Column(db.String(25), nullable=False, index=True)
    
    # Dates are strictly distinct
    # wedding_date is the sole trigger for wedding anniversaries
    wedding_date = db.Column(db.Date, nullable=True, index=True)
    
    # makeup_date is operational date when service was provided
    makeup_date = db.Column(db.Date, nullable=True, index=True)
    
    service_package = db.Column(db.String(120), nullable=True)
    amount = db.Column(db.Float, nullable=True)
    advance_amount = db.Column(db.Float, default=0.0, nullable=True)
    slip_number = db.Column(db.String(50), nullable=True, index=True)
    booking_type = db.Column(db.String(50), default="Bridal Makeup", nullable=False, index=True)
    salon_branch = db.Column(db.String(80), nullable=True, index=True)
    notes = db.Column(db.Text, nullable=True)
    
    # Pre-makeup appointment reminder toggle (1 day before service)
    pre_makeup_reminder_enabled = db.Column(db.Boolean, default=True, nullable=False)

    # WhatsApp consent status
    whatsapp_consent = db.Column(db.Boolean, default=False, nullable=False, index=True)
    
    # Timestamps
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc), nullable=False, index=True)
    updated_at = db.Column(
        db.DateTime,
        default=lambda: datetime.now(timezone.utc),
        onupdate=lambda: datetime.now(timezone.utc),
        nullable=False,
    )

    # Relationships
    reminders = db.relationship(
        "ReminderLog",
        backref="customer",
        lazy="dynamic",
        cascade="all, delete-orphan",
        order_by="desc(ReminderLog.created_at)",
    )

    @classmethod
    def generate_next_code(cls, year: int = None) -> str:
        """Generate a sequential unique customer code like SAF-2024-0001."""
        if not year:
            year = datetime.now().year
        prefix = f"SAF-{year}-"
        last_cust = (
            cls.query.filter(cls.customer_code.like(f"{prefix}%"))
            .order_by(cls.id.desc())
            .first()
        )
        if last_cust and last_cust.customer_code.startswith(prefix):
            try:
                seq = int(last_cust.customer_code.split("-")[-1]) + 1
            except (ValueError, IndexError):
                seq = cls.query.count() + 1
        else:
            seq = 1
        return f"{prefix}{seq:04d}"

    @property
    def has_wedding_date(self) -> bool:
        return self.wedding_date is not None

    @property
    def clean_phone(self) -> str:
        """Returns clean 10-digit number for Indian phone formats."""
        digits = "".join(filter(str.isdigit, self.mobile_number or ""))
        if len(digits) == 12 and digits.startswith("91"):
            return digits[2:]
        if len(digits) == 11 and digits.startswith("0"):
            return digits[1:]
        return digits

    @property
    def international_phone(self) -> str:
        """Returns full E.164 phone string for WhatsApp API and click-to-chat."""
        p = self.clean_phone
        return f"+91{p}" if len(p) == 10 else f"+{p}"

    @property
    def whatsapp_click_url(self) -> str:
        """Returns direct WhatsApp Web / app click-to-chat link."""
        digits = "".join(filter(str.isdigit, self.international_phone))
        return f"https://wa.me/{digits}"

    def get_eve_whatsapp_url(self, years: int, salon_name: str = "Safiya's Makeover") -> str:
        """Returns WhatsApp click-to-chat URL with pre-filled 1-day prior (Eve) greeting + pampering offer."""
        import urllib.parse
        digits = "".join(filter(str.isdigit, self.international_phone))
        text = (
            f"Dear {self.full_name} Ji! 💖\n\n"
            f"Kal aapki {years}th Wedding Anniversary hai! {salon_name} ki poori team ki taraf se dher saari advance mubarakbaad. 🎉💐\n\n"
            f"Aapke is special din ke celebration ke liye hamari taraf se exclusive 20% Pampering Gift Voucher aapke liye available hai (Hair Spa / Facial / Glow treatment).\n\n"
            f"Wishing you and your family a wonderful celebration tomorrow!"
        )
        return f"https://wa.me/{digits}?text={urllib.parse.quote(text)}"

    def get_today_whatsapp_url(self, years: int, salon_name: str = "Safiya's Makeover") -> str:
        """Returns WhatsApp click-to-chat URL with pre-filled today's anniversary celebration greeting."""
        import urllib.parse
        digits = "".join(filter(str.isdigit, self.international_phone))
        text = (
            f"Dear {self.full_name} Ji! 💐💍\n\n"
            f"Happy {years}th Wedding Anniversary! May your bond grow stronger and more beautiful with each passing year.\n\n"
            f"Warmest wishes and love from {salon_name} family! ✨"
        )
        return f"https://wa.me/{digits}?text={urllib.parse.quote(text)}"

    @property
    def total_amount(self) -> float:
        return float(self.amount or 0.0)

    @property
    def advance_paid(self) -> float:
        return float(self.advance_amount or 0.0)

    @property
    def balance_due(self) -> float:
        return max(0.0, self.total_amount - self.advance_paid)

    @property
    def payment_status(self) -> str:
        """Returns: 'Paid', 'Partial', or 'Unpaid'."""
        if self.total_amount <= 0:
            return "Unpaid"
        if self.balance_due <= 0:
            return "Paid"
        if self.advance_paid > 0:
            return "Partial"
        return "Unpaid"

    @property
    def is_makeup_completed(self) -> bool:
        """Returns True if the customer's makeup date or wedding date is in the past."""
        try:
            from zoneinfo import ZoneInfo
            today = datetime.now(ZoneInfo("Asia/Kolkata")).date()
        except Exception:
            today = date.today()

        if self.makeup_date:
            return self.makeup_date < today
        if self.wedding_date:
            return self.wedding_date < today
        return False

    def get_pre_makeup_whatsapp_url(self, salon_name: str = "Safiya's Makeover") -> str:
        """Returns WhatsApp click-to-chat URL with pre-filled 1-day prior pre-makeup appointment reminder."""
        import urllib.parse
        digits = "".join(filter(str.isdigit, self.international_phone))
        slip_text = f"Slip No: #{self.slip_number}\n" if self.slip_number else ""
        bal_text = f"₹ {self.balance_due:,.2f}" if self.balance_due > 0 else "NIL (Fully Paid)"
        
        text = (
            f"Dear {self.full_name} Ji! 💖\n\n"
            f"Kal aapka {self.booking_type or 'Bridal'} Makeup Appointment hai at {salon_name}! ✨\n\n"
            f"{slip_text}"
            f"💄 Package: {self.service_package or 'Bridal Makeover'}\n"
            f"💰 Total Amount: ₹ {self.total_amount:,.2f}\n"
            f"💵 Advance Paid: ₹ {self.advance_paid:,.2f}\n"
            f"⚠️ Balance Due: {bal_text}\n"
            f"🏠 Branch / Location: {self.salon_branch or 'Main Studio'}\n\n"
            f"Please reach on your scheduled time with washed hair & clean face. Our team is excited to give you your dream look!\n\n"
            f"— Team {salon_name}"
        )
        return f"https://wa.me/{digits}?text={urllib.parse.quote(text)}"

    def get_balance_reminder_whatsapp_url(self, salon_name: str = "Safiya's Makeover") -> str:
        """Returns WhatsApp click-to-chat URL with pre-filled pending payment reminder."""
        import urllib.parse
        digits = "".join(filter(str.isdigit, self.international_phone))
        slip_text = f" (Slip #{self.slip_number})" if self.slip_number else ""
        text = (
            f"Dear {self.full_name} Ji! ✨\n\n"
            f"Greetings from {salon_name}! This is a gentle reminder regarding your {self.booking_type or 'Bridal'} booking{slip_text}.\n\n"
            f"💄 Package: {self.service_package or 'Bridal Makeover'}\n"
            f"💰 Total Amount: ₹ {self.total_amount:,.2f}\n"
            f"💵 Advance Paid: ₹ {self.advance_paid:,.2f}\n"
            f"⚠️ Pending Balance Due: ₹ {self.balance_due:,.2f}\n\n"
            f"Kindly settle the pending balance at your earliest convenience via Cash / UPI / Card.\n\n"
            f"Thank you for choosing {salon_name}! 💐"
        )
        return f"https://wa.me/{digits}?text={urllib.parse.quote(text)}"

    def get_anniversary_year_count(self, target_year: int) -> int:
        """Calculate how many years of marriage on target_year."""
        if not self.wedding_date:
            return 0
        return max(0, target_year - self.wedding_date.year)

    def to_dict(self):
        return {
            "id": self.id,
            "customer_code": self.customer_code,
            "slip_number": self.slip_number,
            "full_name": self.full_name,
            "mobile_number": self.mobile_number,
            "booking_type": self.booking_type,
            "wedding_date": self.wedding_date.isoformat() if self.wedding_date else None,
            "makeup_date": self.makeup_date.isoformat() if self.makeup_date else None,
            "service_package": self.service_package,
            "amount": self.amount,
            "advance_amount": self.advance_amount,
            "balance_due": self.balance_due,
            "payment_status": self.payment_status,
            "is_makeup_completed": self.is_makeup_completed,
            "salon_branch": self.salon_branch,
            "notes": self.notes,
            "pre_makeup_reminder_enabled": self.pre_makeup_reminder_enabled,
            "whatsapp_consent": self.whatsapp_consent,
            "created_at": self.created_at.isoformat() if self.created_at else None,
            "updated_at": self.updated_at.isoformat() if self.updated_at else None,
        }

    def __repr__(self):
        return f"<Customer id={self.id} code='{self.customer_code}' name='{self.full_name}'>"
