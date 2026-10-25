import re
from datetime import datetime, timezone
from flask import Blueprint, render_template, redirect, url_for, flash, request, jsonify
from flask_login import login_required, current_user
from sqlalchemy import or_
from app.models.customer import Customer
from app.models.audit_log import AuditLog
from app.models.reminder_log import ReminderLog
from app.forms.customer_forms import CustomerForm
from app.extensions import db

customers_bp = Blueprint("customers", __name__, url_prefix="/customers")


@customers_bp.route("/")
@login_required
def index():
    page = request.args.get("page", 1, type=int)
    search = request.args.get("q", "").strip()
    branch = request.args.get("branch", "").strip()
    filter_missing_date = request.args.get("missing_date", "").strip()
    filter_consent = request.args.get("consent", "").strip()

    query = Customer.query

    # Search filter (name, phone, code)
    if search:
        digits = re.sub(r"[^\d]", "", search)
        conditions = [
            Customer.full_name.ilike(f"%{search}%"),
            Customer.customer_code.ilike(f"%{search}%"),
        ]
        if digits:
            conditions.append(Customer.mobile_number.like(f"%{digits}%"))
        query = query.filter(or_(*conditions))

    # Branch filter
    if branch:
        query = query.filter(Customer.salon_branch == branch)

    # Missing wedding date filter
    if filter_missing_date == "1":
        query = query.filter(Customer.wedding_date.is_(None))
    elif filter_missing_date == "0":
        query = query.filter(Customer.wedding_date.isnot(None))

    # Consent filter
    if filter_consent == "1":
        query = query.filter(Customer.whatsapp_consent == True)
    elif filter_consent == "0":
        query = query.filter(Customer.whatsapp_consent == False)

    # Order by newest first
    query = query.order_by(Customer.created_at.desc())

    pagination = query.paginate(page=page, per_page=15, error_out=False)
    branches = [b[0] for b in db.session.query(Customer.salon_branch).distinct().all() if b[0]]

    return render_template(
        "customers/index.html",
        pagination=pagination,
        search=search,
        branch=branch,
        filter_missing_date=filter_missing_date,
        filter_consent=filter_consent,
        branches=branches,
    )


@customers_bp.route("/new", methods=["GET", "POST"])
@login_required
def create():
    form = CustomerForm()

    if form.validate_on_submit():
        # Clean mobile
        raw_phone = form.mobile_number.data.strip()
        digits = re.sub(r"[^\d]", "", raw_phone)
        if len(digits) == 12 and digits.startswith("91"):
            digits = digits[2:]
        elif len(digits) == 11 and digits.startswith("0"):
            digits = digits[1:]

        code = Customer.generate_next_code()
        customer = Customer(
            customer_code=code,
            full_name=form.full_name.data.strip(),
            mobile_number=digits,
            wedding_date=form.wedding_date.data,
            makeup_date=form.makeup_date.data,
            service_package=form.service_package.data or None,
            amount=float(form.amount.data) if form.amount.data is not None else None,
            salon_branch=form.salon_branch.data or None,
            notes=form.notes.data.strip() if form.notes.data else None,
            whatsapp_consent=form.whatsapp_consent.data,
        )
        db.session.add(customer)
        db.session.flush()

        AuditLog.log(
            action="create_customer",
            entity_type="customer",
            entity_id=customer.id,
            details={"code": customer.customer_code, "name": customer.full_name},
            user_id=current_user.id,
            ip_address=request.remote_addr,
        )
        db.session.commit()

        flash(
            f"Bride '{customer.full_name}' successfully added with ID #{customer.customer_code}!",
            "success",
        )
        # Requirement: "After saving, show a success message and display the newly saved record."
        return redirect(url_for("customers.detail", id=customer.id, newly_saved=1))

    return render_template("customers/form.html", form=form, title="Add New Bride", is_edit=False)


@customers_bp.route("/<int:id>")
@login_required
def detail(id: int):
    customer = Customer.query.get_or_404(id)
    reminders = (
        ReminderLog.query.filter_by(customer_id=customer.id)
        .order_by(ReminderLog.created_at.desc())
        .limit(20)
        .all()
    )
    audits = (
        AuditLog.query.filter_by(entity_type="customer", entity_id=str(customer.id))
        .order_by(AuditLog.created_at.desc())
        .limit(10)
        .all()
    )
    is_newly_saved = request.args.get("newly_saved") == "1"

    # Anniversary timeline details
    anniversary_info = None
    if customer.wedding_date:
        today = datetime.now().date()
        years_married = today.year - customer.wedding_date.year
        anniversary_info = {
            "years": max(0, years_married),
            "wedding_day_formatted": customer.wedding_date.strftime("%d %B %Y"),
        }

    return render_template(
        "customers/detail.html",
        customer=customer,
        reminders=reminders,
        audits=audits,
        is_newly_saved=is_newly_saved,
        anniversary_info=anniversary_info,
    )


@customers_bp.route("/<int:id>/edit", methods=["GET", "POST"])
@login_required
def edit(id: int):
    customer = Customer.query.get_or_404(id)
    form = CustomerForm(original_mobile=customer.clean_phone, obj=customer)

    if form.validate_on_submit():
        raw_phone = form.mobile_number.data.strip()
        digits = re.sub(r"[^\d]", "", raw_phone)
        if len(digits) == 12 and digits.startswith("91"):
            digits = digits[2:]
        elif len(digits) == 11 and digits.startswith("0"):
            digits = digits[1:]

        old_data = customer.to_dict()

        customer.full_name = form.full_name.data.strip()
        customer.mobile_number = digits
        customer.wedding_date = form.wedding_date.data
        customer.makeup_date = form.makeup_date.data
        customer.service_package = form.service_package.data or None
        customer.amount = float(form.amount.data) if form.amount.data is not None else None
        customer.salon_branch = form.salon_branch.data or None
        customer.notes = form.notes.data.strip() if form.notes.data else None
        customer.whatsapp_consent = form.whatsapp_consent.data
        customer.updated_at = datetime.now(timezone.utc)

        AuditLog.log(
            action="update_customer",
            entity_type="customer",
            entity_id=customer.id,
            details={"before": old_data, "after": customer.to_dict()},
            user_id=current_user.id,
            ip_address=request.remote_addr,
        )
        db.session.commit()

        flash(f"Customer details for '{customer.full_name}' updated successfully.", "success")
        return redirect(url_for("customers.detail", id=customer.id))

    return render_template(
        "customers/form.html",
        form=form,
        customer=customer,
        title=f"Edit Customer: {customer.full_name}",
        is_edit=True,
    )


@customers_bp.route("/<int:id>/delete", methods=["POST"])
@login_required
def delete(id: int):
    customer = Customer.query.get_or_404(id)
    cust_name = customer.full_name
    cust_code = customer.customer_code

    AuditLog.log(
        action="delete_customer",
        entity_type="customer",
        entity_id=customer.id,
        details={"code": cust_code, "name": cust_name},
        user_id=current_user.id,
        ip_address=request.remote_addr,
    )

    db.session.delete(customer)
    db.session.commit()

    flash(f"Customer record '{cust_name}' ({cust_code}) has been permanently deleted.", "info")
    return redirect(url_for("customers.index"))
