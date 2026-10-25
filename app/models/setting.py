from datetime import datetime, timezone
from app.extensions import db


class Setting(db.Model):
    __tablename__ = "settings"

    id = db.Column(db.Integer, primary_key=True)
    key = db.Column(db.String(64), unique=True, nullable=False, index=True)
    value = db.Column(db.Text, nullable=True)
    description = db.Column(db.String(255), nullable=True)
    updated_at = db.Column(
        db.DateTime,
        default=lambda: datetime.now(timezone.utc),
        onupdate=lambda: datetime.now(timezone.utc),
        nullable=False,
    )

    @classmethod
    def get_value(cls, key: str, default=None):
        record = cls.query.filter_by(key=key).first()
        if record and record.value is not None:
            return record.value
        return default

    @classmethod
    def get_bool(cls, key: str, default=False) -> bool:
        val = cls.get_value(key)
        if val is None:
            return default
        return str(val).strip().lower() in ("true", "1", "yes", "on")

    @classmethod
    def get_int(cls, key: str, default=0) -> int:
        val = cls.get_value(key)
        if val is None:
            return default
        try:
            return int(val)
        except ValueError:
            return default

    @classmethod
    def set_value(cls, key: str, value, description: str = None):
        record = cls.query.filter_by(key=key).first()
        if not record:
            record = cls(key=key, value=str(value) if value is not None else None, description=description)
            db.session.add(record)
        else:
            record.value = str(value) if value is not None else None
            if description:
                record.description = description
        return record

    def __repr__(self):
        return f"<Setting key='{self.key}' value='{self.value}'>"
