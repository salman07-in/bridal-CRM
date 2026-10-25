import sys
from pathlib import Path

# Add project root directory to sys.path
BASE_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BASE_DIR))

import pytest
from app import create_app
from app.config import TestingConfig
from app.extensions import db
from app.models.user import User
from app.models.customer import Customer
from app.models.setting import Setting


@pytest.fixture
def app():
    """Create and configure a Flask app for testing with in-memory database."""
    app = create_app(TestingConfig)
    with app.app_context():
        db.create_all()
        # Seed test admin
        admin = User(
            username="admin",
            email="admin@safiyasmakeover.com",
            full_name="Safiya Admin",
            role="admin",
            is_active=True,
        )
        admin.set_password("Admin@12345")
        db.session.add(admin)

        # Seed test settings
        Setting.set_value("crm_api_key", "test-api-key-12345", "Test Key")
        Setting.set_value("salon_name", "Safiya's Makeover", "Test Studio")
        db.session.commit()

        yield app

        db.session.remove()
        db.drop_all()


@pytest.fixture
def client(app):
    """Test HTTP client."""
    return app.test_client()


@pytest.fixture
def auth_client(client):
    """Client logged in as test admin."""
    client.post("/auth/login", data={"username": "admin", "password": "Admin@12345"})
    return client
