import pytest
from datetime import date
from sqlalchemy.exc import IntegrityError
from app.models.customer import Customer
from app.models.reminder_log import ReminderLog
from app.services.reminder_service import ReminderService
from app.extensions import db


def test_idempotency_key_construction():
    key = ReminderLog.build_idempotency_key(
        customer_id=42,
        anniversary_year=2024,
        channel="WhatsApp",
        reminder_type="Today_Greeting",
    )
    assert key == "CUST_42_Y2024_whatsapp_today_greeting"


def test_idempotency_database_uniqueness(app):
    with app.app_context():
        cust = Customer(
            customer_code="SAF-IDEM-01",
            full_name="Idempotency Bride",
            mobile_number="9876549991",
            wedding_date=date(2021, 5, 1),
        )
        db.session.add(cust)
        db.session.commit()

        log1 = ReminderLog(
            idempotency_key="CUST_1_Y2024_whatsapp_today_greeting",
            customer_id=cust.id,
            anniversary_year=2024,
            channel="whatsapp",
            reminder_type="today_greeting",
            status="pending",
            scheduled_for=date(2024, 5, 1),
        )
        db.session.add(log1)
        db.session.commit()

        # Attempt inserting a second record with identical idempotency key
        log2 = ReminderLog(
            idempotency_key="CUST_1_Y2024_whatsapp_today_greeting",
            customer_id=cust.id,
            anniversary_year=2024,
            channel="whatsapp",
            reminder_type="today_greeting",
            status="pending",
            scheduled_for=date(2024, 5, 1),
        )
        db.session.add(log2)

        with pytest.raises(IntegrityError):
            db.session.commit()

        db.session.rollback()


def test_reminder_service_prevents_duplicate_queueing(app):
    with app.app_context():
        cust = Customer(
            customer_code="SAF-IDEM-02",
            full_name="Double Send Guard Bride",
            mobile_number="9876549992",
            wedding_date=date(2020, 8, 12),
        )
        db.session.add(cust)
        db.session.commit()

        # First call creates pending log
        log_a = ReminderService.get_or_create_reminder_log(
            customer_id=cust.id,
            anniversary_year=2024,
            channel="whatsapp",
            reminder_type="today_greeting",
            scheduled_for=date(2024, 8, 12),
        )
        db.session.commit()
        first_id = log_a.id

        # Second call returns existing log without creating another row
        log_b = ReminderService.get_or_create_reminder_log(
            customer_id=cust.id,
            anniversary_year=2024,
            channel="whatsapp",
            reminder_type="today_greeting",
            scheduled_for=date(2024, 8, 12),
        )
        assert log_b.id == first_id

        # Verify exactly 1 record exists in the table
        count = ReminderLog.query.filter_by(
            customer_id=cust.id,
            anniversary_year=2024,
            channel="whatsapp",
            reminder_type="today_greeting",
        ).count()
        assert count == 1


def test_eve_and_today_reminders_coexist_idempotently(app):
    with app.app_context():
        cust = Customer(
            customer_code="SAF-IDEM-EVE",
            full_name="Eve and Today Bride",
            mobile_number="9876549993",
            wedding_date=date(2022, 9, 20),
        )
        db.session.add(cust)
        db.session.commit()

        # Day -1: Eve reminder queued
        eve_log = ReminderService.get_or_create_reminder_log(
            customer_id=cust.id,
            anniversary_year=2024,
            channel="whatsapp",
            reminder_type="eve_1day_greeting",
            scheduled_for=date(2024, 9, 19),
        )
        db.session.commit()

        # Day 0: Actual day greeting queued
        today_log = ReminderService.get_or_create_reminder_log(
            customer_id=cust.id,
            anniversary_year=2024,
            channel="whatsapp",
            reminder_type="today_greeting",
            scheduled_for=date(2024, 9, 20),
        )
        db.session.commit()

        # Both exist independently with unique idempotency keys
        assert eve_log.id != today_log.id
        assert eve_log.idempotency_key == f"CUST_{cust.id}_Y2024_whatsapp_eve_1day_greeting"
        assert today_log.idempotency_key == f"CUST_{cust.id}_Y2024_whatsapp_today_greeting"

        # Re-running eve doesn't create duplicate
        eve_log_retry = ReminderService.get_or_create_reminder_log(
            customer_id=cust.id,
            anniversary_year=2024,
            channel="whatsapp",
            reminder_type="eve_1day_greeting",
            scheduled_for=date(2024, 9, 19),
        )
        assert eve_log_retry.id == eve_log.id
