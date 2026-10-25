import sys
from pathlib import Path
from datetime import date

BASE_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BASE_DIR))

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

from app import create_app
from app.config import Config
from app.extensions import db
from app.models.customer import Customer
from sqlalchemy import text


def test_sqlite_persistence():
    print("[*] STEP 1: Creating customer in process #1...")
    app1 = create_app(Config)
    test_mobile = "9876599988"
    cust_code = None

    with app1.app_context():
        # Clean existing test record if any
        existing = Customer.query.filter_by(mobile_number=test_mobile).first()
        if existing:
            db.session.delete(existing)
            db.session.commit()

        cust = Customer(
            customer_code=Customer.generate_next_code(2025),
            full_name="Persistence Test Bride",
            mobile_number=test_mobile,
            wedding_date=date(2022, 12, 10),
            makeup_date=date(2022, 12, 10),
            service_package="Signature Bridal HD",
            amount=45000.0,
            salon_branch="Main Studio",
            whatsapp_consent=True,
        )
        db.session.add(cust)
        db.session.commit()
        cust_code = cust.customer_code
        print(f"[+] Saved customer #{cust_code} in SQLite instance database.")

    # Completely teardown app1 context
    del app1

    print("[*] STEP 2: Re-launching application in fresh context (Process restart simulation)...")
    app2 = create_app(Config)
    with app2.app_context():
        persisted = Customer.query.filter_by(mobile_number=test_mobile).first()
        assert persisted is not None, "FATAL: Customer did not persist across application restart!"
        assert persisted.customer_code == cust_code
        assert persisted.full_name == "Persistence Test Bride"
        assert persisted.wedding_date == date(2022, 12, 10)
        assert persisted.amount == 45000.0
        assert persisted.whatsapp_consent is True

        # Verify WAL mode
        res = db.session.execute(text("PRAGMA journal_mode;")).scalar()
        print(f"[+] Verified SQLite Journal Mode: {res.upper()}")
        assert res.upper() == "WAL", f"Expected WAL mode, got {res}"

        # Clean test record
        db.session.delete(persisted)
        db.session.commit()

    print("[+] SUCCESS: Customer records persist across process restarts with WAL mode verified!")


if __name__ == "__main__":
    test_sqlite_persistence()
