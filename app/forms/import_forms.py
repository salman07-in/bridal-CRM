from flask_wtf import FlaskForm
from flask_wtf.file import FileField, FileRequired, FileAllowed
from wtforms import SubmitField, BooleanField


class ImportUploadForm(FlaskForm):
    file = FileField(
        "Select Excel (.xlsx, .xls) or CSV (.csv) File",
        validators=[
            FileRequired(message="Please select a file to upload."),
            FileAllowed(["xlsx", "xls", "csv"], message="Only Excel (.xlsx, .xls) and CSV (.csv) files are supported."),
        ],
        render_kw={"class": "form-control", "accept": ".xlsx,.xls,.csv"},
    )
    submit = SubmitField("Upload & Inspect Records")
