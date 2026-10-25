from datetime import date, timedelta
from app.services.anniversary import AnniversaryService
from app.models.customer import Customer
from app.extensions import db


def test_leap_year_feb_29_calculation():
    # Leap year wedding: Feb 29, 2020
    wedding = date(2020, 2, 29)

    # In 2024 (leap year) -> should be Feb 29, 2024
    anniv_2024 = AnniversaryService.get_anniversary_date_for_year(wedding, 2024)
    assert anniv_2024 == date(2024, 2, 29)

    # In 2025 (non-leap year) -> should map to Feb 28, 2025
    anniv_2025 = AnniversaryService.get_anniversary_date_for_year(wedding, 2025)
    assert anniv_2025 == date(2025, 2, 28)

    # In 2026 (non-leap year) -> should map to Feb 28, 2026
    anniv_2026 = AnniversaryService.get_anniversary_date_for_year(wedding, 2026)
    assert anniv_2026 == date(2026, 2, 28)

    # In 2028 (leap year) -> should be Feb 29, 2028
    anniv_2028 = AnniversaryService.get_anniversary_date_for_year(wedding, 2028)
    assert anniv_2028 == date(2028, 2, 29)


def test_anniversary_strictly_from_wedding_date_not_makeup_date(app):
    with app.app_context():
        # Bride had makeup on Oct 20, but wedding on Nov 25
        cust = Customer(
            customer_code="SAF-DATE-TEST",
            full_name="Date Test Bride",
            mobile_number="9876541111",
            wedding_date=date(2021, 11, 25),
            makeup_date=date(2021, 10, 20),
        )
        db.session.add(cust)
        db.session.commit()

        # On Oct 20, it is NOT an anniversary (makeup date must not trigger anniversary)
        oct_20 = date(2024, 10, 20)
        todays_oct = AnniversaryService.get_todays_anniversaries(reference_date=oct_20)
        matched_oct = [x for x in todays_oct if x["customer"].id == cust.id]
        assert len(matched_oct) == 0

        # On Nov 25, it IS an anniversary
        nov_25 = date(2024, 11, 25)
        todays_nov = AnniversaryService.get_todays_anniversaries(reference_date=nov_25)
        matched_nov = [x for x in todays_nov if x["customer"].id == cust.id]
        assert len(matched_nov) == 1
        assert matched_nov[0]["years"] == 3


def test_missing_wedding_dates_never_inferred(app):
    with app.app_context():
        # Bride with only makeup_date, no wedding_date
        cust = Customer(
            customer_code="SAF-NO-WED",
            full_name="Missing Wedding Date Bride",
            mobile_number="9876542222",
            wedding_date=None,
            makeup_date=date(2022, 5, 10),
        )
        db.session.add(cust)
        db.session.commit()

        # Check today's list on May 10
        may_10 = date(2024, 5, 10)
        todays = AnniversaryService.get_todays_anniversaries(reference_date=may_10)
        assert cust.id not in [x["customer"].id for x in todays]

        # Check missing dates list
        missing = AnniversaryService.get_missing_wedding_dates()
        assert cust.id in [x.id for x in missing]


def test_upcoming_7_days_anniversaries(app):
    ref_date = date(2024, 6, 10)

    with app.app_context():
        # Bride celebrating in 4 days: June 14
        cust = Customer(
            customer_code="SAF-UPCOMING",
            full_name="Upcoming Bride",
            mobile_number="9876543333",
            wedding_date=date(2020, 6, 14),
        )
        db.session.add(cust)
        db.session.commit()

        upcoming = AnniversaryService.get_upcoming_anniversaries(days=7, reference_date=ref_date)
        matched = [x for x in upcoming if x["customer"].id == cust.id]
        assert len(matched) == 1
        assert matched[0]["days_away"] == 4
        assert matched[0]["years"] == 4
