from datetime import date, timedelta
from app.models.customer import Customer
from app.services.anniversary import AnniversaryService
from app.extensions import db


def test_customer_slip_and_advance_balance(auth_client, app):
    """Test manual slip number, advance amount entry and balance due auto-calculation."""
    res = auth_client.post(
        "/customers/new",
        data={
            "full_name": "Zara Sheikh",
            "mobile_number": "9811122233",
            "slip_number": "SLIP-9988",
            "booking_type": "Bridal Makeup",
            "service_package": "Bridal HD - Bobby Brown",
            "amount": "20000",
            "advance_amount": "5000",
            "wedding_date": "2026-11-15",
            "makeup_date": "2026-11-15",
            "pre_makeup_reminder_enabled": "y",
            "whatsapp_consent": "y",
        },
        follow_redirects=True,
    )
    assert res.status_code == 200

    with app.app_context():
        c = Customer.query.filter_by(mobile_number="9811122233").first()
        assert c is not None
        assert c.slip_number == "SLIP-9988"
        assert c.booking_type == "Bridal Makeup"
        assert c.amount == 20000.0
        assert c.advance_amount == 5000.0
        assert c.advance_paid == 5000.0
        assert c.balance_due == 15000.0
        assert c.payment_status == "Partial"
        assert c.pre_makeup_reminder_enabled is True

        # Test WhatsApp URL generation
        wa_balance_url = c.get_balance_reminder_whatsapp_url("Safiya's Makeover")
        assert "wa.me/919811122233" in wa_balance_url
        assert "15%2C000" in wa_balance_url or "15,000" in wa_balance_url or "SLIP-9988" in wa_balance_url

        wa_pre_url = c.get_pre_makeup_whatsapp_url("Safiya's Makeover")
        assert "wa.me/919811122233" in wa_pre_url
        assert "Zara" in wa_pre_url


def test_customer_payment_status_variations(app):
    """Test fully paid, partial, and unpaid balance calculations."""
    with app.app_context():
        # Unpaid
        c_unpaid = Customer(
            customer_code="SAF-UNPAID-1",
            full_name="Client Unpaid",
            mobile_number="9800000001",
            amount=15000.0,
            advance_amount=0.0,
        )
        assert c_unpaid.balance_due == 15000.0
        assert c_unpaid.payment_status == "Unpaid"

        # Partial
        c_partial = Customer(
            customer_code="SAF-PARTIAL-1",
            full_name="Client Partial",
            mobile_number="9800000002",
            amount=15000.0,
            advance_amount=5000.0,
        )
        assert c_partial.balance_due == 10000.0
        assert c_partial.payment_status == "Partial"

        # Fully Paid
        c_paid = Customer(
            customer_code="SAF-PAID-1",
            full_name="Client Paid",
            mobile_number="9800000003",
            amount=15000.0,
            advance_amount=15000.0,
        )
        assert c_paid.balance_due == 0.0
        assert c_paid.payment_status == "Paid"


def test_booking_categories_and_tabs(auth_client, app):
    """Test separation of upcoming bookings vs completed makeovers and category filters."""
    today = date.today()
    past_date = today - timedelta(days=30)
    future_date = today + timedelta(days=10)

    with app.app_context():
        # Completed bridal makeup
        c_completed = Customer(
            customer_code="SAF-COMP-1",
            full_name="Ayesha Past",
            mobile_number="9800000011",
            booking_type="Bridal Makeup",
            makeup_date=past_date,
            wedding_date=past_date,
            amount=10000.0,
            advance_amount=10000.0,
        )

        # Upcoming engagement booking
        c_upcoming_eng = Customer(
            customer_code="SAF-UP-ENG",
            full_name="Sana Engagement",
            mobile_number="9800000012",
            slip_number="SLIP-ENG-01",
            booking_type="Engagement Makeup",
            makeup_date=future_date,
            wedding_date=future_date + timedelta(days=60),
            amount=8000.0,
            advance_amount=2000.0,
        )

        # Upcoming party makeup
        c_upcoming_party = Customer(
            customer_code="SAF-UP-PTY",
            full_name="Rukhsar Party",
            mobile_number="9800000013",
            slip_number="SLIP-PTY-02",
            booking_type="Party Makeup",
            makeup_date=future_date,
            amount=3500.0,
            advance_amount=1000.0,
        )

        db.session.add_all([c_completed, c_upcoming_eng, c_upcoming_party])
        db.session.commit()

    # Test Upcoming tab
    res_upcoming = auth_client.get("/customers/?tab=upcoming")
    assert res_upcoming.status_code == 200
    assert b"Sana Engagement" in res_upcoming.data
    assert b"Rukhsar Party" in res_upcoming.data
    assert b"Ayesha Past" not in res_upcoming.data

    # Test Completed tab
    res_completed = auth_client.get("/customers/?tab=completed")
    assert res_completed.status_code == 200
    assert b"Ayesha Past" in res_completed.data
    assert b"Sana Engagement" not in res_completed.data

    # Test Category filter (Party Makeup)
    res_party = auth_client.get("/customers/?booking_type=Party+Makeup")
    assert res_party.status_code == 200
    assert b"Rukhsar Party" in res_party.data
    assert b"Sana Engagement" not in res_party.data

    # Test Pending Balance tab
    res_pending = auth_client.get("/customers/?tab=pending_balance")
    assert res_pending.status_code == 200
    assert b"Sana Engagement" in res_pending.data
    assert b"Rukhsar Party" in res_pending.data
    assert b"Ayesha Past" not in res_pending.data  # Past was fully paid


def test_tomorrows_makeup_service_query(app):
    """Test AnniversaryService.get_tomorrows_makeup_appointments() for 1-day pre-makeup notice."""
    today = date.today()
    tomorrow = today + timedelta(days=1)

    with app.app_context():
        c_tomorrow = Customer(
            customer_code="SAF-TOM-01",
            full_name="Zoya Tomorrow",
            mobile_number="9800000021",
            makeup_date=tomorrow,
            booking_type="Bridal Makeup",
            pre_makeup_reminder_enabled=True,
            amount=15000.0,
            advance_amount=5000.0,
        )
        # Disabled reminder
        c_disabled = Customer(
            customer_code="SAF-DIS-02",
            full_name="Noor Disabled",
            mobile_number="9800000022",
            makeup_date=tomorrow,
            booking_type="Bridal Makeup",
            pre_makeup_reminder_enabled=False,
            amount=15000.0,
            advance_amount=5000.0,
        )
        db.session.add_all([c_tomorrow, c_disabled])
        db.session.commit()

        tomorrows = AnniversaryService.get_tomorrows_makeup_appointments()
        codes = [item["customer"].customer_code for item in tomorrows]
        assert "SAF-TOM-01" in codes
        assert "SAF-DIS-02" in codes

        # Verify pre_makeup_reminder_enabled flag on customer
        cust_tom = next(item["customer"] for item in tomorrows if item["customer"].customer_code == "SAF-TOM-01")
        assert cust_tom.pre_makeup_reminder_enabled is True

        cust_dis = next(item["customer"] for item in tomorrows if item["customer"].customer_code == "SAF-DIS-02")
        assert cust_dis.pre_makeup_reminder_enabled is False


def test_engagement_and_party_package_submission(auth_client, app):
    """Test creating bookings with Engagement and Party Makeup packages and brand rates."""
    # 1. Create Engagement booking with NARS
    res_eng = auth_client.post(
        "/customers/new",
        data={
            "full_name": "Mehwish Roka",
            "mobile_number": "9812345678",
            "slip_number": "ENG-77",
            "booking_type": "Engagement Makeup",
            "service_package": "Engagement HD - Nars",
            "amount": "8000",
            "advance_amount": "3000",
            "makeup_date": "2026-12-05",
            "whatsapp_consent": "y",
        },
        follow_redirects=True,
    )
    assert res_eng.status_code == 200

    # 2. Create Party booking with Bobbi Brown
    res_party = auth_client.post(
        "/customers/new",
        data={
            "full_name": "Sana Guest",
            "mobile_number": "9823456789",
            "slip_number": "PTY-88",
            "booking_type": "Party Makeup",
            "service_package": "Party Airbrush - Bobbi Brown",
            "amount": "6000",
            "advance_amount": "2000",
            "makeup_date": "2026-12-06",
            "whatsapp_consent": "y",
        },
        follow_redirects=True,
    )
    assert res_party.status_code == 200

    with app.app_context():
        c_eng = Customer.query.filter_by(mobile_number="9812345678").first()
        assert c_eng is not None
        assert c_eng.booking_type == "Engagement Makeup"
        assert c_eng.service_package == "Engagement HD - Nars"
        assert c_eng.amount == 8000.0
        assert c_eng.advance_amount == 3000.0
        assert c_eng.balance_due == 5000.0
        assert c_eng.payment_status == "Partial"

        c_pty = Customer.query.filter_by(mobile_number="9823456789").first()
        assert c_pty is not None
        assert c_pty.booking_type == "Party Makeup"
        assert c_pty.service_package == "Party Airbrush - Bobbi Brown"
        assert c_pty.amount == 6000.0
        assert c_pty.advance_amount == 2000.0
        assert c_pty.balance_due == 4000.0
        assert c_pty.payment_status == "Partial"

