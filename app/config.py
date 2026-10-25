import os
from pathlib import Path
from dotenv import load_dotenv

# Base directory of the project
BASE_DIR = Path(__file__).resolve().parent.parent

# Load environment variables from .env file
load_dotenv(BASE_DIR / ".env")


class Config:
    """Base configuration with safe defaults."""
    SECRET_KEY = os.getenv("SECRET_KEY", "safiya-default-dev-secret-change-in-production")
    
    # SQLite Database URI
    database_url = os.getenv("DATABASE_URL", "sqlite:///instance/safiya_crm.sqlite3")
    if database_url.startswith("sqlite:///"):
        # Ensure relative path resolves properly inside BASE_DIR
        relative_path = database_url.replace("sqlite:///", "")
        db_file = BASE_DIR / relative_path
        db_file.parent.mkdir(parents=True, exist_ok=True)
        SQLALCHEMY_DATABASE_URI = f"sqlite:///{db_file.as_posix()}"
    else:
        SQLALCHEMY_DATABASE_URI = database_url

    SQLALCHEMY_TRACK_MODIFICATIONS = False
    SQLALCHEMY_ENGINE_OPTIONS = {
        # SQLite connection tuning for WAL mode and timeout
        "connect_args": {"timeout": 30, "check_same_thread": False}
    }

    # Timezone
    TIMEZONE = os.getenv("TIMEZONE", "Asia/Kolkata")

    # Security & Sessions
    SESSION_COOKIE_HTTPONLY = True
    SESSION_COOKIE_SAMESITE = os.getenv("SESSION_COOKIE_SAMESITE", "Lax")
    SESSION_COOKIE_SECURE = os.getenv("SESSION_COOKIE_SECURE", "False").lower() in ("true", "1", "yes")
    PERMANENT_SESSION_LIFETIME = int(os.getenv("PERMANENT_SESSION_LIFETIME", "28800")) # 8 hours
    WTF_CSRF_ENABLED = True
    WTF_CSRF_TIME_LIMIT = None

    # Uploads
    MAX_CONTENT_LENGTH = 16 * 1024 * 1024  # 16 MB max upload
    UPLOAD_FOLDER = BASE_DIR / "uploads"
    ALLOWED_EXTENSIONS = {"xlsx", "xls", "csv"}

    # Salon Branding Defaults
    SALON_NAME = os.getenv("SALON_NAME", "Safiya's Makeover")
    SALON_TAGLINE = os.getenv("SALON_TAGLINE", "Bridal & Makeover Studio")
    SALON_BRANCH = os.getenv("SALON_BRANCH", "Main Branch")
    SALON_PHONE = os.getenv("SALON_PHONE", "+91 98765 43210")
    SALON_OWNER_EMAIL = os.getenv("SALON_OWNER_EMAIL", "owner@safiyasmakeover.com")

    # Reminder Defaults
    REMINDER_ADVANCE_DAYS = int(os.getenv("REMINDER_ADVANCE_DAYS", "7"))
    SEND_OWNER_DAILY_DIGEST = os.getenv("SEND_OWNER_DAILY_DIGEST", "True").lower() in ("true", "1", "yes")

    # SMTP Configuration
    SMTP_ENABLED = os.getenv("SMTP_ENABLED", "False").lower() in ("true", "1", "yes")
    SMTP_HOST = os.getenv("SMTP_HOST", "smtp.gmail.com")
    SMTP_PORT = int(os.getenv("SMTP_PORT", "587"))
    SMTP_USE_TLS = os.getenv("SMTP_USE_TLS", "True").lower() in ("true", "1", "yes")
    SMTP_USE_SSL = os.getenv("SMTP_USE_SSL", "False").lower() in ("true", "1", "yes")
    SMTP_USERNAME = os.getenv("SMTP_USERNAME", "")
    SMTP_PASSWORD = os.getenv("SMTP_PASSWORD", "")
    SMTP_FROM_EMAIL = os.getenv("SMTP_FROM_EMAIL", "notifications@safiyasmakeover.com")
    SMTP_FROM_NAME = os.getenv("SMTP_FROM_NAME", "Safiya's Makeover")

    # API Keys & Automation
    CRM_API_KEY = os.getenv("CRM_API_KEY", "")

    # WhatsApp Settings (Official Meta Cloud API)
    WHATSAPP_ENABLED = os.getenv("WHATSAPP_ENABLED", "False").lower() in ("true", "1", "yes")
    WHATSAPP_PHONE_NUMBER_ID = os.getenv("WHATSAPP_PHONE_NUMBER_ID", "")
    WHATSAPP_ACCESS_TOKEN = os.getenv("WHATSAPP_ACCESS_TOKEN", "")
    WHATSAPP_TEMPLATE_NAME = os.getenv("WHATSAPP_TEMPLATE_NAME", "anniversary_greeting_v1")

    # Rate Limiting
    RATELIMIT_STORAGE_URI = os.getenv("RATELIMIT_STORAGE_URI", "memory://")
    RATELIMIT_DEFAULT = "200 per minute"


class DevelopmentConfig(Config):
    DEBUG = True
    TESTING = False


class ProductionConfig(Config):
    DEBUG = False
    TESTING = False
    # Enforce secure cookie in production when HTTPS is enabled
    SESSION_COOKIE_SECURE = os.getenv("SESSION_COOKIE_SECURE", "True").lower() in ("true", "1", "yes")


class TestingConfig(Config):
    TESTING = True
    DEBUG = True
    SQLALCHEMY_DATABASE_URI = "sqlite:///:memory:"
    WTF_CSRF_ENABLED = False
    RATELIMIT_ENABLED = False
    CRM_API_KEY = "test-api-key-12345"
