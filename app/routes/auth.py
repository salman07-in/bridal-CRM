from datetime import datetime, timezone
from flask import Blueprint, render_template, redirect, url_for, flash, request
from flask_login import login_user, logout_user, login_required, current_user
from app.models.user import User
from app.models.audit_log import AuditLog
from app.forms.auth_forms import LoginForm, ChangePasswordForm
from app.extensions import db, limiter

auth_bp = Blueprint("auth", __name__, url_prefix="/auth")


@auth_bp.route("/login", methods=["GET", "POST"])
@limiter.limit("5 per minute", methods=["POST"])
def login():
    if current_user.is_authenticated:
        return redirect(url_for("dashboard.index"))

    form = LoginForm()
    if form.validate_on_submit():
        user_input = form.username.data.strip()
        user = User.query.filter(
            (User.username == user_input) | (User.email == user_input.lower())
        ).first()

        if user and user.check_password(form.password.data):
            if not user.is_active:
                flash("This account is currently deactivated. Please contact your studio administrator.", "danger")
                return render_template("auth/login.html", form=form)

            login_user(user, remember=form.remember_me.data)
            user.last_login = datetime.now(timezone.utc)
            
            # Log audit
            AuditLog.log(
                action="login",
                entity_type="user",
                entity_id=user.id,
                details={"username": user.username},
                user_id=user.id,
                ip_address=request.remote_addr,
            )
            db.session.commit()

            flash(f"Welcome back, {user.full_name}! Successfully logged in.", "success")
            next_page = request.args.get("next")
            if not next_page or not next_page.startswith("/"):
                next_page = url_for("dashboard.index")
            return redirect(next_page)
        else:
            flash("Invalid username or password. Please verify credentials.", "danger")

    return render_template("auth/login.html", form=form)


@auth_bp.route("/logout")
@login_required
def logout():
    uid = current_user.id
    uname = current_user.username
    logout_user()
    AuditLog.log(
        action="logout",
        entity_type="user",
        entity_id=uid,
        details={"username": uname},
        user_id=uid,
        ip_address=request.remote_addr,
    )
    db.session.commit()
    flash("You have been signed out safely.", "info")
    return redirect(url_for("auth.login"))


@auth_bp.route("/profile", methods=["GET", "POST"])
@login_required
def profile():
    form = ChangePasswordForm()
    if form.validate_on_submit():
        if not current_user.check_password(form.current_password.data):
            flash("Current password is incorrect.", "danger")
        else:
            current_user.set_password(form.new_password.data)
            AuditLog.log(
                action="change_password",
                entity_type="user",
                entity_id=current_user.id,
                user_id=current_user.id,
                ip_address=request.remote_addr,
            )
            db.session.commit()
            flash("Password updated successfully.", "success")
            return redirect(url_for("dashboard.index"))

    return render_template("auth/profile.html", form=form)
