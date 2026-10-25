from datetime import datetime, timezone
import json
from app.extensions import db


class AuditLog(db.Model):
    __tablename__ = "audit_logs"

    id = db.Column(db.Integer, primary_key=True)
    user_id = db.Column(db.Integer, db.ForeignKey("users.id", ondelete="SET NULL"), nullable=True, index=True)
    action = db.Column(db.String(64), nullable=False, index=True)
    entity_type = db.Column(db.String(64), nullable=False, index=True) # customer, user, setting, reminder
    entity_id = db.Column(db.String(64), nullable=True, index=True)
    details = db.Column(db.Text, nullable=True) # JSON details / diff
    ip_address = db.Column(db.String(45), nullable=True)
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc), nullable=False, index=True)

    @classmethod
    def log(cls, action: str, entity_type: str, entity_id=None, details=None, user_id=None, ip_address=None):
        """Helper to create and save an audit log entry."""
        if isinstance(details, (dict, list)):
            details_str = json.dumps(details, default=str)
        else:
            details_str = str(details) if details is not None else None

        entry = cls(
            user_id=user_id,
            action=action,
            entity_type=entity_type,
            entity_id=str(entity_id) if entity_id is not None else None,
            details=details_str,
            ip_address=ip_address,
        )
        db.session.add(entry)
        return entry

    def get_details_dict(self):
        if not self.details:
            return {}
        try:
            return json.loads(self.details)
        except Exception:
            return {"raw": self.details}

    def __repr__(self):
        return f"<AuditLog id={self.id} action='{self.action}' entity='{self.entity_type}'>"
