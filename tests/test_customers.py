from datetime import date
from app.models.customer import Customer
from app.models.audit_log import AuditLog
from app.extensions import db


def test_create_customer(auth_client, app):
    res = auth_client.post(
        "/customers/new",
        data={
            "full_name": "Fatima Begum",
            "mobile_number": "9876512345",
            "wedding_date": "2022-11-20",
            "makeup_date": "2022-11-20",
            "service_package": "Bridal HD - M.A.C",
            "amount": "12000",
            "salon_branch": "Main Studio",
            "notes": "Skin sensitive to heavy fragrance",
            "whatsapp_consent": "y",
        },
        follow_redirects=True,
    )
    assert res.status_code == 200

    with app.app_context():
        cust = Customer.query.filter_by(mobile_number="9876512345").first()
        assert cust is not None
        assert cust.full_name == "Fatima Begum"
        assert cust.wedding_date == date(2022, 11, 20)
        assert cust.amount == 12000.0
        assert cust.whatsapp_consent is True

        # Check audit log
        audit = AuditLog.query.filter_by(action="create_customer", entity_id=str(cust.id)).first()
        assert audit is not None


def test_customer_duplicate_mobile_prevented(auth_client, app):
    # Create first bride
    with app.app_context():
        c1 = Customer(
            customer_code=Customer.generate_next_code(),
            full_name="Amina Khan",
            mobile_number="9876543210",
        )
        db.session.add(c1)
        db.session.commit()

    # Try creating second bride with same phone
    res = auth_client.post(
        "/customers/new",
        data={
            "full_name": "Amina Duplicate",
            "mobile_number": "9876543210",
        },
        follow_redirects=True,
    )
    assert res.status_code == 200
    assert b"already registered" in res.data


def test_edit_customer(auth_client, app):
    with app.app_context():
        c = Customer(
            customer_code="SAF-TEST-001",
            full_name="Original Name",
            mobile_number="9876500001",
        )
        db.session.add(c)
        db.session.commit()
        cid = c.id

    res = auth_client.post(
        f"/customers/{cid}/edit",
        data={
            "full_name": "Updated Name",
            "mobile_number": "9876500001",
            "service_package": "Bridal Airbrush - Huda Beauty",
            "wedding_date": "2023-01-15",
            "whatsapp_consent": "y",
        },
        follow_redirects=True,
    )
    assert res.status_code == 200

    with app.app_context():
        updated = db.session.get(Customer, cid)
        assert updated.full_name == "Updated Name"
        assert updated.wedding_date == date(2023, 1, 15)


def test_delete_customer(auth_client, app):
    with app.app_context():
        c = Customer(
            customer_code="SAF-DEL-001",
            full_name="To Delete",
            mobile_number="9876599999",
        )
        db.session.add(c)
        db.session.commit()
        cid = c.id

    res = auth_client.post(f"/customers/{cid}/delete", follow_redirects=True)
    assert res.status_code == 200

    with app.app_context():
        deleted = db.session.get(Customer, cid)
        assert deleted is None
