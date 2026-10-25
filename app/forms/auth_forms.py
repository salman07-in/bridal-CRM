from flask_wtf import FlaskForm
from wtforms import StringField, PasswordField, BooleanField, SubmitField
from wtforms.validators import DataRequired, Length, Email, EqualTo


class LoginForm(FlaskForm):
    username = StringField(
        "Username or Email",
        validators=[DataRequired(message="Please enter your username or email."), Length(min=3, max=100)],
        render_kw={"placeholder": "e.g. admin", "autofocus": True, "class": "form-control"},
    )
    password = PasswordField(
        "Password",
        validators=[DataRequired(message="Please enter your password.")],
        render_kw={"placeholder": "••••••••", "class": "form-control"},
    )
    remember_me = BooleanField("Keep me logged in for 30 days")
    submit = SubmitField("Sign In to Studio Portal")


class ChangePasswordForm(FlaskForm):
    current_password = PasswordField(
        "Current Password",
        validators=[DataRequired()],
        render_kw={"class": "form-control"},
    )
    new_password = PasswordField(
        "New Password",
        validators=[DataRequired(), Length(min=8, message="Password must be at least 8 characters long.")],
        render_kw={"class": "form-control"},
    )
    confirm_password = PasswordField(
        "Confirm New Password",
        validators=[DataRequired(), EqualTo("new_password", message="Passwords must match.")],
        render_kw={"class": "form-control"},
    )
    submit = SubmitField("Update Password")
