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
    salon_branch = db.Column(db.String(80), nullable=True, index=True)
    notes = db.Column(db.Text, nullable=True)
    
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

    def get_anniversary_year_count(self, target_year: int) -> int:
        """Calculate how many years of marriage on target_year."""
        if not self.wedding_date:
            return 0
        return max(0, target_year - self.wedding_date.year)

    def to_dict(self):
        return {
            "id": self.id,
            "customer_code": self.customer_code,
            "full_name": self.full_name,
            "mobile_number": self.mobile_number,
            "wedding_date": self.wedding_date.isoformat() if self.wedding_date else None,
            "makeup_date": self.makeup_date.isoformat() if self.makeup_date else None,
            "service_package": self.service_package,
            "amount": self.amount,
            "salon_branch": self.salon_branch,
            "notes": self.notes,
            "whatsapp_consent": self.whatsapp_consent,
            "created_at": self.created_at.isoformat() if self.created_at else None,
            "updated_at": self.updated_at.isoformat() if self.updated_at else None,
        }

    def __repr__(self):
        return f"<Customer id={self.id} code='{self.customer_code}' name='{self.full_name}'>"
