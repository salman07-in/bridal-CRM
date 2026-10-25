from flask import Blueprint, render_template
from flask_login import login_required
from app.services.anniversary import AnniversaryService

dashboard_bp = Blueprint("dashboard", __name__)


@dashboard_bp.route("/")
@login_required
def index():
    summary = AnniversaryService.get_dashboard_summary()
    missing_records = AnniversaryService.get_missing_wedding_dates(limit=5)
    return render_template(
        "dashboard/index.html",
        summary=summary,
        missing_records=missing_records,
    )
