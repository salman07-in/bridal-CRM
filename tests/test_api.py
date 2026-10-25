from datetime import date
from app.models.customer import Customer
from app.models.reminder_log import ReminderLog
from app.extensions import db


def test_api_health(client):
    res = client.get("/api/v1/health")
    assert res.status_code == 200
    data = res.get_json()
    assert data["status"] == "healthy"


def test_api_unauthorized_without_key(client):
    res = client.get("/api/v1/reminders/due")
    assert res.status_code == 401
    data = res.get_json()
    assert "Unauthorized" in data["message"]


def test_api_get_due_reminders(client, app):
    with app.app_context():
        today = date.today()
        cust = Customer(
            customer_code="SAF-API-01",
            full_name="API Bride",
            mobile_number="9876540001",
            wedding_date=today.replace(year=today.year - 2),
            whatsapp_consent=True,
        )
        db.session.add(cust)
        db.session.commit()

    headers = {"X-API-KEY": "test-api-key-12345"}
    res = client.get("/api/v1/reminders/due", headers=headers)
    assert res.status_code == 200
    data = res.get_json()
    assert data["status"] == "success"
    assert data["count"] >= 1
    matched = [r for r in data["reminders"] if r["customer"]["full_name"] == "API Bride"]
    assert len(matched) == 1
    assert matched[0]["customer"]["years_married"] == 2


def test_api_mark_reminder_sent(client, app):
    with app.app_context():
        cust = Customer(
            customer_code="SAF-API-02",
            full_name="Mark Sent Bride",
            mobile_number="9876540002",
        )
        db.session.add(cust)
        db.session.commit()

        log = ReminderLog(
            idempotency_key="CUST_MARK_SENT_TEST_2024",
            customer_id=cust.id,
            anniversary_year=2024,
            channel="whatsapp",
            reminder_type="today_greeting",
            status="pending",
            scheduled_for=date.today(),
        )
        db.session.add(log)
        db.session.commit()
        log_id = log.id

    headers = {"X-API-KEY": "test-api-key-12345"}
    res = client.post(
        "/api/v1/reminders/mark-sent",
        json={"reminder_id": log_id, "metadata": "Delivered via n8n test"},
        headers=headers,
    )
    assert res.status_code == 200
    data = res.get_json()
    assert data["status"] == "success"

    with app.app_context():
        updated_log = ReminderLog.query.get(log_id)
        assert updated_log.status == "sent"
        assert updated_log.sent_at is not None
        assert "Delivered via n8n test" in updated_log.metadata_json
