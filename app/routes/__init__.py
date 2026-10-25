from app.routes.auth import auth_bp
from app.routes.dashboard import dashboard_bp
from app.routes.customers import customers_bp
from app.routes.calendar import calendar_bp
from app.routes.import_export import import_export_bp
from app.routes.reminders import reminders_bp
from app.routes.settings import settings_bp
from app.routes.api import api_bp

__all__ = [
    "auth_bp",
    "dashboard_bp",
    "customers_bp",
    "calendar_bp",
    "import_export_bp",
    "reminders_bp",
    "settings_bp",
    "api_bp",
]
