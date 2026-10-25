import calendar
from datetime import datetime, date
from flask import Blueprint, render_template, request, jsonify
from flask_login import login_required
from app.services.anniversary import AnniversaryService

calendar_bp = Blueprint("calendar", __name__, url_prefix="/calendar")


@calendar_bp.route("/")
@login_required
def index():
    today = AnniversaryService.get_kolkata_today()
    year = request.args.get("year", today.year, type=int)
    month = request.args.get("month", today.month, type=int)

    # Validate month
    if month < 1:
        month = 12
        year -= 1
    elif month > 12:
        month = 1
        year += 1

    anniversaries = AnniversaryService.get_month_anniversaries(year, month)

    # Group by day: {day_num: [events]}
    events_by_day = {}
    for item in anniversaries:
        d = item["day"]
        if d not in events_by_day:
            events_by_day[d] = []
        events_by_day[d].append(item)

    # Calendar month matrix (weeks of days)
    cal = calendar.Calendar(firstweekday=6) # Sunday first
    month_days = cal.monthdayscalendar(year, month)
    month_name = calendar.month_name[month]

    # Prev / Next navigation
    prev_month = month - 1 if month > 1 else 12
    prev_year = year if month > 1 else year - 1
    next_month = month + 1 if month < 12 else 1
    next_year = year if month < 12 else year + 1

    return render_template(
        "calendar/index.html",
        year=year,
        month=month,
        month_name=month_name,
        month_days=month_days,
        events_by_day=events_by_day,
        total_month_anniversaries=len(anniversaries),
        today=today,
        prev_month=prev_month,
        prev_year=prev_year,
        next_month=next_month,
        next_year=next_year,
    )


@calendar_bp.route("/events")
@login_required
def events_feed():
    """JSON feed for interactive JS calendar or external widgets."""
    today = AnniversaryService.get_kolkata_today()
    year = request.args.get("year", today.year, type=int)
    month = request.args.get("month", today.month, type=int)

    items = AnniversaryService.get_month_anniversaries(year, month)
    data = []
    for it in items:
        c = it["customer"]
        data.append({
            "title": f"💍 {c.full_name} ({it['years']}y)",
            "start": it["date"].isoformat(),
            "customer_id": c.id,
            "customer_code": c.customer_code,
            "phone": c.mobile_number,
            "years": it["years"],
            "url": f"/customers/{c.id}",
        })

    return jsonify({"year": year, "month": month, "events": data})
