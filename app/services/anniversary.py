from datetime import date, datetime, timedelta
import calendar
from zoneinfo import ZoneInfo
from typing import List, Dict, Any, Optional
from sqlalchemy import or_, and_, extract
from app.models.customer import Customer
from app.models.reminder_log import ReminderLog
from app.extensions import db


class AnniversaryService:
    """Core service for computing bridal anniversaries in Asia/Kolkata timezone.
    
    Guiding Rules:
    1. Wedding anniversaries are strictly calculated from customer.wedding_date.
    2. customer.makeup_date is NEVER used to infer or calculate an anniversary.
    3. Missing wedding dates are flagged as requiring salon review.
    4. February 29 leap-year weddings: in non-leap years, the anniversary
       is celebrated on February 28.
    """

    @staticmethod
    def get_kolkata_today() -> date:
        """Returns the current date in the Asia/Kolkata timezone."""
        try:
            tz = ZoneInfo("Asia/Kolkata")
            return datetime.now(tz).date()
        except Exception:
            # Fallback to local date if timezone is unavailable
            return datetime.now().date()

    @classmethod
    def get_anniversary_date_for_year(cls, wedding_date: date, target_year: int) -> date:
        """Compute the calendar date of the anniversary in target_year.
        
        Handles Feb 29 leap years:
        - If target_year is a leap year -> Feb 29
        - If target_year is not a leap year -> Feb 28
        """
        if not wedding_date:
            raise ValueError("Cannot calculate anniversary for null wedding date")

        month = wedding_date.month
        day = wedding_date.day

        if month == 2 and day == 29:
            is_target_leap = calendar.isleap(target_year)
            if is_target_leap:
                return date(target_year, 2, 29)
            else:
                return date(target_year, 2, 28)

        # Standard date
        return date(target_year, month, day)

    @classmethod
    def get_todays_anniversaries(cls, reference_date: Optional[date] = None) -> List[Dict[str, Any]]:
        """Get all customers celebrating their wedding anniversary today."""
        today = reference_date or cls.get_kolkata_today()
        is_today_leap = calendar.isleap(today.year)

        # Base query: only customers with a valid wedding date
        query = Customer.query.filter(Customer.wedding_date.isnot(None))

        # Check for matching month and day
        # If today is Feb 28 in a non-leap year, we also include Feb 29 brides
        if today.month == 2 and today.day == 28 and not is_today_leap:
            query = query.filter(
                Customer.wedding_date.isnot(None),
                extract("month", Customer.wedding_date) == 2,
                extract("day", Customer.wedding_date).in_([28, 29]),
            )
        else:
            query = query.filter(
                Customer.wedding_date.isnot(None),
                extract("month", Customer.wedding_date) == today.month,
                extract("day", Customer.wedding_date) == today.day,
            )

        # Exclude marriages that happened in the current year (anniversaries start at year 1+)
        customers = query.filter(Customer.wedding_date <= date(today.year - 1, 12, 31)).all()

        results = []
        for c in customers:
            years = today.year - c.wedding_date.year
            if years >= 1:
                results.append({
                    "customer": c,
                    "anniversary_date": today,
                    "years": years,
                    "target_year": today.year,
                    "is_today": True,
                    "days_away": 0,
                })

        return sorted(results, key=lambda x: x["customer"].full_name)

    @classmethod
    def get_upcoming_anniversaries(cls, days: int = 7, reference_date: Optional[date] = None) -> List[Dict[str, Any]]:
        """Get customers celebrating anniversaries within the next `days` days (e.g. 1 to 7 days ahead)."""
        today = reference_date or cls.get_kolkata_today()
        upcoming = []

        # Iterate through target window from day 1 to days (excluding today)
        for d in range(1, days + 1):
            target_date = today + timedelta(days=d)
            target_year = target_date.year
            is_target_leap = calendar.isleap(target_year)

            query = Customer.query.filter(Customer.wedding_date.isnot(None))

            if target_date.month == 2 and target_date.day == 28 and not is_target_leap:
                query = query.filter(
                    extract("month", Customer.wedding_date) == 2,
                    extract("day", Customer.wedding_date).in_([28, 29]),
                )
            else:
                query = query.filter(
                    extract("month", Customer.wedding_date) == target_date.month,
                    extract("day", Customer.wedding_date) == target_date.day,
                )

            # Anniversaries start at year 1+
            matched_customers = query.filter(Customer.wedding_date <= date(target_year - 1, 12, 31)).all()

            for c in matched_customers:
                years = target_year - c.wedding_date.year
                if years >= 1:
                    upcoming.append({
                        "customer": c,
                        "anniversary_date": target_date,
                        "years": years,
                        "target_year": target_year,
                        "is_today": False,
                        "days_away": d,
                    })

        return sorted(upcoming, key=lambda x: (x["days_away"], x["customer"].full_name))

    @classmethod
    def get_month_anniversaries(cls, year: int, month: int) -> List[Dict[str, Any]]:
        """Get all anniversaries for a given calendar month and year (for calendar view)."""
        num_days = calendar.monthrange(year, month)[1]
        is_year_leap = calendar.isleap(year)
        
        query = Customer.query.filter(
            Customer.wedding_date.isnot(None),
            Customer.wedding_date <= date(year - 1, 12, 31)
        )

        if month == 2:
            query = query.filter(extract("month", Customer.wedding_date) == 2)
        else:
            query = query.filter(extract("month", Customer.wedding_date) == month)

        customers = query.all()
        results = []

        for c in customers:
            w_month = c.wedding_date.month
            w_day = c.wedding_date.day
            years = year - c.wedding_date.year
            if years < 1:
                continue

            # Handle Feb 29
            if w_month == 2 and w_day == 29:
                cal_day = 29 if is_year_leap else 28
            else:
                cal_day = w_day

            if 1 <= cal_day <= num_days:
                anniv_date = date(year, month, cal_day)
                results.append({
                    "customer": c,
                    "date": anniv_date,
                    "day": cal_day,
                    "years": years,
                    "package": c.service_package,
                    "phone": c.mobile_number,
                })

        return sorted(results, key=lambda x: (x["day"], x["customer"].full_name))

    @classmethod
    def get_missing_wedding_dates(cls, limit: int = 50) -> List[Customer]:
        """Fetch records with missing wedding dates requiring review by salon staff."""
        return Customer.query.filter(Customer.wedding_date.is_(None)).order_by(Customer.created_at.desc()).limit(limit).all()

    @classmethod
    def get_dashboard_summary(cls) -> Dict[str, Any]:
        """Compile complete dashboard KPI metrics and lists."""
        today = cls.get_kolkata_today()
        total_brides = Customer.query.count()
        missing_dates_count = Customer.query.filter(Customer.wedding_date.is_(None)).count()
        todays_anniversaries = cls.get_todays_anniversaries(today)
        upcoming_7days = cls.get_upcoming_anniversaries(7, today)
        pending_reminders_count = ReminderLog.query.filter_by(status="pending").count()
        whatsapp_consented_count = Customer.query.filter_by(whatsapp_consent=True).count()

        return {
            "today": today,
            "total_brides": total_brides,
            "today_count": len(todays_anniversaries),
            "upcoming_7day_count": len(upcoming_7days),
            "missing_dates_count": missing_dates_count,
            "pending_reminders_count": pending_reminders_count,
            "whatsapp_consented_count": whatsapp_consented_count,
            "todays_anniversaries": todays_anniversaries,
            "upcoming_anniversaries": upcoming_7days,
        }
