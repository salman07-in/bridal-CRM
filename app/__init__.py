import os
from datetime import datetime, date
from flask import Flask, render_template, jsonify, request
from app.config import Config, DevelopmentConfig, ProductionConfig
from app.extensions import db, migrate, login_manager, csrf, limiter
from app.models.user import User
from app.models.setting import Setting
from app.models.reminder_log import ReminderLog


@login_manager.user_loader
def load_user(user_id):
    return db.session.get(User, int(user_id))


def format_inr(number):
    """Formats a number in Indian currency style (e.g. 1,25,000.00)."""
    if number is None:
        return "₹ 0"
    try:
        val = float(number)
    except (ValueError, TypeError):
        return f"₹ {number}"

    # Split integer and decimal parts
    is_negative = val < 0
    val = abs(val)
    parts = f"{val:.2f}".split(".")
    int_part = parts[0]
    dec_part = parts[1]

    if len(int_part) <= 3:
        formatted = int_part
    else:
        last3 = int_part[-3:]
        remaining = int_part[:-3]
        # Group remaining digits in sets of 2 from right to left
        groups = []
        while len(remaining) > 2:
            groups.append(remaining[-2:])
            remaining = remaining[:-2]
        if remaining:
            groups.append(remaining)
        groups.reverse()
        formatted = ",".join(groups) + "," + last3

    res = f"₹ {formatted}"
    if dec_part != "00":
        res += f".{dec_part}"
    return f"-{res}" if is_negative else res


def create_app(config_class=None):
    """Application factory for Safiya's Makeover Bridal CRM."""
    app = Flask(__name__)

    if config_class is None:
        env = os.getenv("FLASK_ENV", "production").lower()
        if env == "development":
            config_class = DevelopmentConfig
        else:
            config_class = ProductionConfig

    app.config.from_object(config_class)

    # Initialize extensions
    db.init_app(app)
    migrate.init_app(app, db)
    login_manager.init_app(app)
    csrf.init_app(app)
    limiter.init_app(app)

    # Register Jinja custom filters
    @app.template_filter("date_in")
    def date_in_filter(val, fmt="%d/%m/%Y"):
        """Format date into Indian standard DD/MM/YYYY."""
        if not val:
            return ""
        if isinstance(val, (datetime, date)):
            return val.strftime(fmt)
        return str(val)

    @app.template_filter("datetime_in")
    def datetime_in_filter(val, fmt="%d/%m/%Y %I:%M %p"):
        """Format datetime into Indian standard DD/MM/YYYY hh:mm AM/PM."""
        if not val:
            return ""
        if isinstance(val, (datetime, date)):
            return val.strftime(fmt)
        return str(val)

    @app.template_filter("inr")
    def inr_filter(val):
        """Format currency into INR."""
        return format_inr(val)

    @app.template_filter("phone_display")
    def phone_display_filter(val):
        """Format phone nicely for display."""
        if not val:
            return ""
        digits = "".join(filter(str.isdigit, str(val)))
        if len(digits) == 10:
            return f"+91 {digits[:5]} {digits[5:]}"
        return str(val)

    # Global Context Processor
    @app.context_processor
    def inject_global_data():
        def get_salon_name():
            return Setting.get_value("salon_name", app.config.get("SALON_NAME", "Safiya's Makeover"))

        def get_pending_count():
            try:
                return ReminderLog.query.filter_by(status="pending").count()
            except Exception:
                return 0

        return {
            "app_name": get_salon_name(),
            "salon_tagline": Setting.get_value("salon_tagline", app.config.get("SALON_TAGLINE", "Bridal & Makeover Studio")),
            "salon_phone": Setting.get_value("salon_phone", app.config.get("SALON_PHONE", "+91 98765 43210")),
            "pending_reminders_count": get_pending_count(),
            "current_year": datetime.now().year,
        }

    # Register blueprints
    from app.routes import (
        auth_bp,
        dashboard_bp,
        customers_bp,
        calendar_bp,
        import_export_bp,
        reminders_bp,
        settings_bp,
        api_bp,
    )

    app.register_blueprint(auth_bp)
    app.register_blueprint(dashboard_bp)
    app.register_blueprint(customers_bp)
    app.register_blueprint(calendar_bp)
    app.register_blueprint(import_export_bp)
    app.register_blueprint(reminders_bp)
    app.register_blueprint(settings_bp)
    app.register_blueprint(api_bp)

    # Error handlers
    @app.errorhandler(404)
    def not_found_error(error):
        if request.path.startswith("/api/"):
            return jsonify({"status": "error", "message": "Resource not found"}), 404
        return render_template("errors/404.html"), 404

    @app.errorhandler(429)
    def ratelimit_error(error):
        if request.path.startswith("/api/"):
            return jsonify({"status": "error", "message": "Rate limit exceeded"}), 429
        return render_template("errors/429.html"), 429

    # Auto-migrate SQLite columns if table exists
    with app.app_context():
        try:
            from sqlalchemy import inspect, text
            inspector = inspect(db.engine)
            if "customers" in inspector.get_table_names():
                cols = [c["name"] for c in inspector.get_columns("customers")]
                if "slip_number" not in cols:
                    db.session.execute(text("ALTER TABLE customers ADD COLUMN slip_number VARCHAR(50)"))
                if "advance_amount" not in cols:
                    db.session.execute(text("ALTER TABLE customers ADD COLUMN advance_amount FLOAT DEFAULT 0.0"))
                if "booking_type" not in cols:
                    db.session.execute(text("ALTER TABLE customers ADD COLUMN booking_type VARCHAR(50) DEFAULT 'Bridal Makeup'"))
                if "pre_makeup_reminder_enabled" not in cols:
                    db.session.execute(text("ALTER TABLE customers ADD COLUMN pre_makeup_reminder_enabled BOOLEAN DEFAULT 1"))
                db.session.commit()
        except Exception:
            db.session.rollback()

    return app
