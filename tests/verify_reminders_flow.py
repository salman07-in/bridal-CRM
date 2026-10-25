import sys
from pathlib import Path
from datetime import date, timedelta

BASE_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BASE_DIR))

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

from app import create_app
from app.config import Config
from app.extensions import db
from app.models.customer import Customer
from app.models.reminder_log import ReminderLog
from app.services.anniversary import AnniversaryService
from app.services.reminder_service import ReminderService


def test_anniversary_reminders():
    print("[*] Testing Anniversary Reminders Engine with Sample Data...")
    app = create_app(Config)

    with app.app_context():
        today = AnniversaryService.get_kolkata_today()

        # Seed sample brides
        # Bride 1: Today is 3rd anniversary, consent = True
        b1 = Customer(
            customer_code="SAF-ANNIV-01",
            full_name="Samreen Taj",
            mobile_number="9876588801",
            wedding_date=today.replace(year=today.year - 3),
            whatsapp_consent=True,
            service_package="Bridal HD - Nars",
        )
        # Bride 2: Today is 1st anniversary, consent = False
        b2 = Customer(
            customer_code="SAF-ANNIV-02",
            full_name="Farzana Anjum",
            mobile_number="9876588802",
            wedding_date=today.replace(year=today.year - 1),
            whatsapp_consent=False,
            service_package="Bridal HD - Kryolan",
        )
        # Bride 3: Anniversary in 5 days
        b3 = Customer(
            customer_code="SAF-ANNIV-03",
            full_name="Bushra Khan",
            mobile_number="9876588803",
            wedding_date=(today + timedelta(days=5)).replace(year=today.year - 2),
            whatsapp_consent=True,
        )
        # Bride 4: Anniversary TOMORROW (1-Day Prior / Eve)
        tomorrow_date = today + timedelta(days=1)
        b4 = Customer(
            customer_code="SAF-ANNIV-04",
            full_name="Zeenat Begum",
            mobile_number="9876588804",
            wedding_date=tomorrow_date.replace(year=tomorrow_date.year - 2),
            whatsapp_consent=True,
            service_package="Bridal Airbrush - M.A.C",
        )

        db.session.add_all([b1, b2, b3, b4])
        db.session.commit()

        # 1. Evaluate today's and tomorrow's anniversaries
        todays = AnniversaryService.get_todays_anniversaries(today)
        tomorrows = AnniversaryService.get_tomorrows_anniversaries(today)
        print(f"[+] Found {len(todays)} bride(s) celebrating today, {len(tomorrows)} celebrating tomorrow (Eve).")
        assert len(todays) >= 2
        assert len(tomorrows) >= 1

        # 2. Process daily reminders
        result1 = ReminderService.process_daily_reminders()
        print(f"[+] Processed daily reminders: {result1}")
        assert result1["todays_count"] >= 2
        assert result1["tomorrows_count"] >= 1

        # Verify idempotency keys in ReminderLog
        log_b1 = ReminderLog.query.filter_by(
            customer_id=b1.id,
            anniversary_year=today.year,
            channel="whatsapp",
            reminder_type="today_greeting",
        ).first()
        assert log_b1 is not None
        assert log_b1.idempotency_key == f"CUST_{b1.id}_Y{today.year}_whatsapp_today_greeting"

        log_b4 = ReminderLog.query.filter_by(
            customer_id=b4.id,
            anniversary_year=tomorrow_date.year,
            channel="whatsapp",
            reminder_type="eve_1day_greeting",
        ).first()
        assert log_b4 is not None
        assert log_b4.idempotency_key == f"CUST_{b4.id}_Y{tomorrow_date.year}_whatsapp_eve_1day_greeting"

        log_b2 = ReminderLog.query.filter_by(
            customer_id=b2.id,
            anniversary_year=today.year,
            channel="whatsapp",
            reminder_type="today_greeting",
        ).first()
        assert log_b2 is not None
        assert "consent not granted" in (log_b2.error_message or "").lower()

        # 3. Test Double-Run Protection: Run reminder engine again immediately
        logs_count_before = ReminderLog.query.count()
        result2 = ReminderService.process_daily_reminders()
        logs_count_after = ReminderLog.query.count()
        assert logs_count_before == logs_count_after, "FATAL: Duplicate reminder logs were created on second run!"
        print("[+] Verified Idempotency: Zero duplicate reminders created on re-run (Both Today & Eve).")

        # Cleanup test brides and logs
        for b in [b1, b2, b3, b4]:
            ReminderLog.query.filter_by(customer_id=b.id).delete()
            db.session.delete(b)
        db.session.commit()
        print("[+] Cleaned up test anniversary sample records.")

    print("[+] SUCCESS: Anniversary reminders engine and duplicate prevention verified completely!")


if __name__ == "__main__":
    test_anniversary_reminders()
