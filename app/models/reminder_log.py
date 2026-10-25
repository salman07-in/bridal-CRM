from datetime import datetime, timezone, date
from app.extensions import db


class ReminderLog(db.Model):
    __tablename__ = "reminder_logs"

    id = db.Column(db.Integer, primary_key=True)
    # Strict unique idempotency key: prevents double sending
    # Format: CUST_{customer_id}_Y{anniversary_year}_{channel}_{reminder_type}
    idempotency_key = db.Column(db.String(120), unique=True, nullable=False, index=True)
    
    customer_id = db.Column(db.Integer, db.ForeignKey("customers.id", ondelete="CASCADE"), nullable=False, index=True)
    anniversary_year = db.Column(db.Integer, nullable=False, index=True)
    
    # channel: 'email', 'whatsapp', 'owner_digest'
    channel = db.Column(db.String(30), nullable=False, index=True)
    
    # reminder_type: 'today_greeting', 'advance_7day_notice', 'owner_digest'
    reminder_type = db.Column(db.String(40), nullable=False, index=True)
    
    # status: 'pending', 'sent', 'failed', 'cancelled'
    status = db.Column(db.String(25), default="pending", nullable=False, index=True)
    
    # Date for which the reminder is scheduled
    scheduled_for = db.Column(db.Date, nullable=False, index=True)
    sent_at = db.Column(db.DateTime, nullable=True)
    retry_count = db.Column(db.Integer, default=0, nullable=False)
    error_message = db.Column(db.Text, nullable=True)
    metadata_json = db.Column(db.Text, nullable=True) # JSON details, recipient, payload response
    
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc), nullable=False)
    updated_at = db.Column(
        db.DateTime,
        default=lambda: datetime.now(timezone.utc),
        onupdate=lambda: datetime.now(timezone.utc),
        nullable=False,
    )

    @classmethod
    def build_idempotency_key(cls, customer_id: int, anniversary_year: int, channel: str, reminder_type: str) -> str:
        """Construct the canonical unique idempotency key."""
        return f"CUST_{customer_id}_Y{anniversary_year}_{channel.lower().strip()}_{reminder_type.lower().strip()}"

    def mark_sent(self, metadata: str = None):
        self.status = "sent"
        self.sent_at = datetime.now(timezone.utc)
        self.error_message = None
        if metadata:
            self.metadata_json = metadata

    def mark_failed(self, error_message: str):
        self.status = "failed"
        self.retry_count += 1
        self.error_message = error_message

    def to_dict(self):
        return {
            "id": self.id,
            "idempotency_key": self.idempotency_key,
            "customer_id": self.customer_id,
            "customer_name": self.customer.full_name if self.customer else None,
            "customer_code": self.customer.customer_code if self.customer else None,
            "anniversary_year": self.anniversary_year,
            "channel": self.channel,
            "reminder_type": self.reminder_type,
            "status": self.status,
            "scheduled_for": self.scheduled_for.isoformat() if self.scheduled_for else None,
            "sent_at": self.sent_at.isoformat() if self.sent_at else None,
            "retry_count": self.retry_count,
            "error_message": self.error_message,
            "created_at": self.created_at.isoformat() if self.created_at else None,
        }

    def __repr__(self):
        return f"<ReminderLog id={self.id} key='{self.idempotency_key}' status='{self.status}'>"
