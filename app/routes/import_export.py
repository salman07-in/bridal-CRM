import json
from flask import (
    Blueprint,
    render_template,
    redirect,
    url_for,
    flash,
    request,
    session,
    send_file,
    Response,
)
from flask_login import login_required, current_user
from app.models.customer import Customer
from app.models.audit_log import AuditLog
from app.forms.import_forms import ImportUploadForm
from app.services.excel_service import ExcelService
from app.extensions import db

import_export_bp = Blueprint("import_export", __name__, url_prefix="/import-export")


@import_export_bp.route("/import", methods=["GET", "POST"])
@login_required
def import_wizard():
    form = ImportUploadForm()

    if form.validate_on_submit():
        file = form.file.data
        filename = file.filename

        try:
            rows = ExcelService.parse_file_to_rows(file.stream, filename)
            if not rows:
                flash("The uploaded file appears to be empty. Please verify data exists.", "warning")
                return render_template("import_export/import.html", form=form)

            # Validate and check duplicates
            preview_report = ExcelService.validate_and_preview_import(rows)

            # Store validated rows in session for safe final confirmation
            # Convert date objects to ISO strings for JSON serialization
            serializable_valid = []
            for r in preview_report["valid_records"]:
                r_copy = dict(r)
                if r_copy.get("wedding_date"):
                    r_copy["wedding_date"] = r_copy["wedding_date"].isoformat()
                if r_copy.get("makeup_date"):
                    r_copy["makeup_date"] = r_copy["makeup_date"].isoformat()
                serializable_valid.append(r_copy)

            session["pending_import_records"] = serializable_valid
            session["pending_import_filename"] = filename

            return render_template(
                "import_export/preview.html",
                filename=filename,
                report=preview_report,
            )

        except Exception as e:
            flash(f"Error parsing file: {str(e)}", "danger")

    return render_template("import_export/import.html", form=form)


@import_export_bp.route("/import/commit", methods=["POST"])
@login_required
def commit_import():
    pending = session.get("pending_import_records", [])
    filename = session.get("pending_import_filename", "uploaded_file")

    if not pending:
        flash("No valid records found in session to import. Please upload a file again.", "warning")
        return redirect(url_for("import_export.import_wizard"))

    # Parse ISO strings back to dates
    records_to_commit = []
    for r in pending:
        r_copy = dict(r)
        if r_copy.get("wedding_date"):
            r_copy["wedding_date"] = ExcelService.parse_flexible_date(r_copy["wedding_date"])
        if r_copy.get("makeup_date"):
            r_copy["makeup_date"] = ExcelService.parse_flexible_date(r_copy["makeup_date"])
        records_to_commit.append(r_copy)

    try:
        inserted = ExcelService.execute_import(records_to_commit, current_user_id=current_user.id)
        
        # Audit log
        AuditLog.log(
            action="import_customers",
            entity_type="customer",
            entity_id=None,
            details={"filename": filename, "imported_count": inserted},
            user_id=current_user.id,
            ip_address=request.remote_addr,
        )
        db.session.commit()

        # Clear session
        session.pop("pending_import_records", None)
        session.pop("pending_import_filename", None)

        flash(f"Success! {inserted} bridal customer records were imported into the database.", "success")
        return redirect(url_for("customers.index"))

    except Exception as e:
        db.session.rollback()
        flash(f"Database error during import: {str(e)}", "danger")
        return redirect(url_for("import_export.import_wizard"))


@import_export_bp.route("/export")
@login_required
def export_view():
    total_customers = Customer.query.count()
    branches = [b[0] for b in db.session.query(Customer.salon_branch).distinct().all() if b[0]]
    return render_template("import_export/export.html", total_customers=total_customers, branches=branches)


@import_export_bp.route("/export/excel")
@login_required
def export_excel():
    branch = request.args.get("branch", "").strip()
    missing_date = request.args.get("missing_date", "").strip()

    query = Customer.query
    if branch:
        query = query.filter(Customer.salon_branch == branch)
    if missing_date == "1":
        query = query.filter(Customer.wedding_date.is_(None))
    elif missing_date == "0":
        query = query.filter(Customer.wedding_date.isnot(None))

    customers = query.order_by(Customer.created_at.desc()).all()

    excel_stream = ExcelService.export_customers_to_excel(customers)

    AuditLog.log(
        action="export_customers_excel",
        entity_type="customer",
        details={"count": len(customers), "branch": branch, "missing_date": missing_date},
        user_id=current_user.id,
        ip_address=request.remote_addr,
    )
    db.session.commit()

    return send_file(
        excel_stream,
        as_attachment=True,
        download_name=f"safiya_bridal_customers_{len(customers)}.xlsx",
        mimetype="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    )


@import_export_bp.route("/export/csv")
@login_required
def export_csv():
    branch = request.args.get("branch", "").strip()
    missing_date = request.args.get("missing_date", "").strip()

    query = Customer.query
    if branch:
        query = query.filter(Customer.salon_branch == branch)
    if missing_date == "1":
        query = query.filter(Customer.wedding_date.is_(None))
    elif missing_date == "0":
        query = query.filter(Customer.wedding_date.isnot(None))

    customers = query.order_by(Customer.created_at.desc()).all()
    csv_data = ExcelService.export_customers_to_csv(customers)

    AuditLog.log(
        action="export_customers_csv",
        entity_type="customer",
        details={"count": len(customers), "branch": branch},
        user_id=current_user.id,
        ip_address=request.remote_addr,
    )
    db.session.commit()

    return Response(
        csv_data,
        mimetype="text/csv",
        headers={"Content-Disposition": f"attachment;filename=safiya_bridal_customers_{len(customers)}.csv"},
    )
