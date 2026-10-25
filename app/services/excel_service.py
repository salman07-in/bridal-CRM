import csv
import io
import re
from datetime import datetime, date, timedelta
from typing import Dict, List, Any, Tuple, Optional
import openpyxl
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from app.models.customer import Customer
from app.extensions import db


class ExcelService:
    """Robust Excel and CSV parser, validator, duplicate detector, and exporter.
    
    Designed for legacy handwritten salon registers digitized into Excel/CSV.
    Handles varied date formats (DD/MM/YYYY, DD-MM-YYYY, ISO), Excel serials,
    Indian mobile variations (+91, spaces, dashes), and missing fields.
    """

    SUPPORTED_DATE_FORMATS = [
        "%d/%m/%Y", "%d-%m-%Y", "%d.%m.%Y",
        "%Y-%m-%d", "%Y/%m/%d",
        "%d/%m/%y", "%d-%m-%y",
        "%d %b %Y", "%d %B %Y",
    ]

    @classmethod
    def parse_flexible_date(cls, val: Any) -> Optional[date]:
        """Tolerantly parses diverse date formats from Excel/CSV."""
        if val is None or val == "":
            return None

        # If already a datetime or date object
        if isinstance(val, datetime):
            return val.date()
        if isinstance(val, date):
            return val

        # If Excel serial date (integer or float)
        if isinstance(val, (int, float)):
            try:
                # Excel base date is 1899-12-30 due to historical leap year bug
                return (date(1899, 12, 30) + timedelta(days=int(val)))
            except Exception:
                return None

        # Clean string
        val_str = str(val).strip()
        if not val_str or val_str.lower() in ("null", "none", "n/a", "-", "--", "nil"):
            return None

        for fmt in cls.SUPPORTED_DATE_FORMATS:
            try:
                return datetime.strptime(val_str, fmt).date()
            except (ValueError, TypeError):
                continue

        # If ISO format with time part
        try:
            return datetime.fromisoformat(val_str.replace("Z", "")).date()
        except Exception:
            pass

        return None

    @classmethod
    def normalize_indian_mobile(cls, phone_val: Any) -> Tuple[Optional[str], Optional[str]]:
        """Cleans and validates an Indian mobile number.
        Returns (clean_10_digit_str, error_message).
        """
        if phone_val is None or str(phone_val).strip() == "":
            return None, "Mobile number is required"

        raw = str(phone_val).strip()
        # Remove common phone decorators
        digits = re.sub(r"[^\d]", "", raw)

        # Check for +91 / 91 country code prefix
        if len(digits) == 12 and digits.startswith("91"):
            digits = digits[2:]
        # Check for 0 prefix
        elif len(digits) == 11 and digits.startswith("0"):
            digits = digits[1:]

        # Indian mobile numbers must be 10 digits starting with 6, 7, 8, or 9
        if len(digits) != 10:
            return None, f"Invalid mobile number '{raw}': must have 10 digits (found {len(digits)})"

        if digits[0] not in "6789":
            return None, f"Invalid mobile number '{raw}': Indian mobile numbers start with 6, 7, 8, or 9"

        return digits, None

    @classmethod
    def parse_file_to_rows(cls, file_stream, filename: str) -> List[Dict[str, Any]]:
        """Parses an uploaded CSV or XLSX stream into a list of row dictionaries."""
        lower_name = filename.lower()
        rows = []

        if lower_name.endswith(".csv"):
            content = file_stream.read()
            if isinstance(content, bytes):
                # Try UTF-8 with BOM or latin-1 fallback
                try:
                    text = content.decode("utf-8-sig")
                except UnicodeDecodeError:
                    text = content.decode("latin-1")
            else:
                text = content

            reader = csv.reader(io.StringIO(text))
            header = None
            for row in reader:
                if not any(cell.strip() for cell in row):
                    continue
                if header is None:
                    header = [h.strip().lower() for h in row]
                    continue
                row_dict = {}
                for idx, h in enumerate(header):
                    row_dict[h] = row[idx].strip() if idx < len(row) else ""
                rows.append(row_dict)

        elif lower_name.endswith((".xlsx", ".xls")):
            wb = openpyxl.load_workbook(file_stream, data_only=True)
            ws = wb.active
            header = None
            for row in ws.iter_rows(values_only=True):
                if not any(row):
                    continue
                if header is None:
                    header = [str(h).strip().lower() if h is not None else f"col_{i}" for i, h in enumerate(row)]
                    continue
                row_dict = {}
                for idx, h in enumerate(header):
                    val = row[idx] if idx < len(row) else None
                    row_dict[h] = val
                rows.append(row_dict)
        else:
            raise ValueError("Unsupported file format. Please upload .xlsx, .xls, or .csv")

        return rows

    @classmethod
    def map_columns(cls, row: Dict[str, Any]) -> Dict[str, Any]:
        """Tolerantly maps headers to standard Customer attributes."""
        # Find match by known keywords
        result = {
            "full_name": None,
            "mobile_number": None,
            "wedding_date": None,
            "makeup_date": None,
            "service_package": None,
            "amount": None,
            "salon_branch": None,
            "notes": None,
            "whatsapp_consent": False,
        }

        for key, val in row.items():
            k = key.lower().replace("_", " ").replace("-", " ").strip()
            
            # Full Name
            if k in ("name", "bride name", "bride full name", "customer name", "full name", "client name"):
                result["full_name"] = str(val).strip() if val is not None else None
            # Mobile Number
            elif k in ("mobile", "phone", "contact", "mobile number", "phone number", "whatsapp", "contact number"):
                result["mobile_number"] = val
            # Wedding Date
            elif k in ("wedding date", "marriage date", "shaadi date", "anniversary date", "event date"):
                result["wedding_date"] = val
            # Makeup / Service Date
            elif k in ("makeup date", "service date", "booking date", "appointment date", "date of service"):
                result["makeup_date"] = val
            # Package / Service
            elif k in ("service", "package", "service package", "bridal package", "service name"):
                result["service_package"] = str(val).strip() if val is not None else None
            # Amount
            elif k in ("amount", "price", "fee", "cost", "total amount", "package amount", "total"):
                if val is not None:
                    try:
                        clean_amt = re.sub(r"[^\d.]", "", str(val))
                        result["amount"] = float(clean_amt) if clean_amt else None
                    except (ValueError, TypeError):
                        result["amount"] = None
            # Salon Branch
            elif k in ("branch", "salon branch", "location", "outlet", "center"):
                result["salon_branch"] = str(val).strip() if val is not None else None
            # Notes
            elif k in ("notes", "remarks", "comment", "special requests", "details"):
                result["notes"] = str(val).strip() if val is not None else None
            # Consent
            elif k in ("consent", "whatsapp consent", "opt in", "whatsapp opt in", "wa consent"):
                c_str = str(val).strip().lower()
                result["whatsapp_consent"] = c_str in ("yes", "y", "true", "1", "opt-in", "granted")

        return result

    @classmethod
    def validate_and_preview_import(cls, rows: List[Dict[str, Any]]) -> Dict[str, Any]:
        """Validates all rows, detects internal and database duplicates, and produces a preview report."""
        valid_records = []
        duplicate_records = []
        invalid_records = []

        seen_mobiles_in_file = set()

        for idx, row in enumerate(rows, start=2): # 1-indexed Excel header is row 1
            mapped = cls.map_columns(row)
            errors = []

            # Validate Name
            name = mapped.get("full_name")
            if not name:
                errors.append("Bride name is required")

            # Validate Mobile
            clean_mobile, phone_err = cls.normalize_indian_mobile(mapped.get("mobile_number"))
            if phone_err:
                errors.append(phone_err)

            # Parse Wedding Date (nullable)
            raw_w_date = mapped.get("wedding_date")
            parsed_w_date = None
            if raw_w_date is not None and str(raw_w_date).strip() != "":
                parsed_w_date = cls.parse_flexible_date(raw_w_date)
                if not parsed_w_date:
                    errors.append(f"Invalid wedding date '{raw_w_date}' (format must be DD/MM/YYYY or YYYY-MM-DD)")

            # Parse Makeup Date (nullable)
            raw_m_date = mapped.get("makeup_date")
            parsed_m_date = None
            if raw_m_date is not None and str(raw_m_date).strip() != "":
                parsed_m_date = cls.parse_flexible_date(raw_m_date)
                if not parsed_m_date:
                    errors.append(f"Invalid makeup date '{raw_m_date}' (format must be DD/MM/YYYY or YYYY-MM-DD)")

            if errors:
                invalid_records.append({
                    "row_number": idx,
                    "raw_data": row,
                    "errors": errors,
                })
                continue

            # Check Duplicates within the same file
            if clean_mobile in seen_mobiles_in_file:
                duplicate_records.append({
                    "row_number": idx,
                    "full_name": name,
                    "mobile_number": clean_mobile,
                    "reason": "Duplicate mobile number inside this upload file",
                })
                continue
            seen_mobiles_in_file.add(clean_mobile)

            # Check Duplicates against Database
            existing = Customer.query.filter_by(mobile_number=clean_mobile).first()
            if existing:
                duplicate_records.append({
                    "row_number": idx,
                    "full_name": name,
                    "mobile_number": clean_mobile,
                    "existing_customer_code": existing.customer_code,
                    "existing_name": existing.full_name,
                    "reason": f"Mobile already registered to {existing.full_name} ({existing.customer_code})",
                })
                continue

            valid_records.append({
                "row_number": idx,
                "full_name": name,
                "mobile_number": clean_mobile,
                "wedding_date": parsed_w_date,
                "makeup_date": parsed_m_date,
                "service_package": mapped.get("service_package"),
                "amount": mapped.get("amount"),
                "salon_branch": mapped.get("salon_branch"),
                "notes": mapped.get("notes"),
                "whatsapp_consent": mapped.get("whatsapp_consent", False),
            })

        return {
            "total_rows": len(rows),
            "valid_count": len(valid_records),
            "duplicate_count": len(duplicate_records),
            "invalid_count": len(invalid_records),
            "valid_records": valid_records,
            "duplicate_records": duplicate_records,
            "invalid_records": invalid_records,
        }

    @classmethod
    def execute_import(cls, valid_records: List[Dict[str, Any]], current_user_id: Optional[int] = None) -> int:
        """Commits valid records to the database with sequential customer codes."""
        inserted_count = 0
        current_year = datetime.now().year

        for rec in valid_records:
            code = Customer.generate_next_code(current_year)
            cust = Customer(
                customer_code=code,
                full_name=rec["full_name"],
                mobile_number=rec["mobile_number"],
                wedding_date=rec["wedding_date"],
                makeup_date=rec["makeup_date"],
                service_package=rec["service_package"],
                amount=rec["amount"],
                salon_branch=rec["salon_branch"],
                notes=rec["notes"],
                whatsapp_consent=rec["whatsapp_consent"],
            )
            db.session.add(cust)
            db.session.flush() # Populate ID and preserve ordering
            inserted_count += 1

        db.session.commit()
        return inserted_count

    @classmethod
    def export_customers_to_excel(cls, customers: List[Customer]) -> io.BytesIO:
        """Generates a styled Excel workbook with customer data."""
        wb = openpyxl.Workbook()
        ws = wb.active
        ws.title = "Bridal Customers"
        ws.views.sheetView[0].showGridLines = True

        # Header styling (rose gold/champagne salon theme)
        header_fill = PatternFill(start_color="D4738C", end_color="D4738C", fill_type="solid")
        header_font = Font(name="Calibri", size=11, bold=True, color="FFFFFF")
        regular_font = Font(name="Calibri", size=10)
        border_thin = Border(
            left=Side(style='thin', color='E2E8F0'),
            right=Side(style='thin', color='E2E8F0'),
            top=Side(style='thin', color='E2E8F0'),
            bottom=Side(style='thin', color='E2E8F0'),
        )

        headers = [
            "Customer Code", "Bride Full Name", "Mobile Number", "Wedding Date (DD/MM/YYYY)",
            "Service Date (DD/MM/YYYY)", "Package / Service", "Amount (INR)",
            "Salon Branch", "WhatsApp Consent", "Created Date", "Notes"
        ]

        ws.append(headers)
        for col_num in range(1, len(headers) + 1):
            cell = ws.cell(row=1, column=col_num)
            cell.fill = header_fill
            cell.font = header_font
            cell.alignment = Alignment(horizontal="center", vertical="center")

        ws.row_dimensions[1].height = 28

        for row_idx, c in enumerate(customers, start=2):
            w_str = c.wedding_date.strftime("%d/%m/%Y") if c.wedding_date else "Missing (Review)"
            m_str = c.makeup_date.strftime("%d/%m/%Y") if c.makeup_date else ""
            c_str = c.created_at.strftime("%d/%m/%Y") if c.created_at else ""
            consent_str = "Yes" if c.whatsapp_consent else "No"
            amt_val = c.amount if c.amount is not None else ""

            row_data = [
                c.customer_code,
                c.full_name,
                c.mobile_number,
                w_str,
                m_str,
                c.service_package or "",
                amt_val,
                c.salon_branch or "",
                consent_str,
                c_str,
                c.notes or "",
            ]
            ws.append(row_data)

            for col_num in range(1, len(row_data) + 1):
                cell = ws.cell(row=row_idx, column=col_num)
                cell.font = regular_font
                cell.border = border_thin
                if col_num in (1, 3, 4, 5, 8, 9, 10):
                    cell.alignment = Alignment(horizontal="center")
                elif col_num == 7: # Amount
                    cell.alignment = Alignment(horizontal="right")
                    cell.number_format = "₹#,##0.00"

        # Auto-adjust column widths
        for col in ws.columns:
            max_len = 0
            col_letter = col[0].column_letter
            for cell in col:
                val_str = str(cell.value or "")
                max_len = max(max_len, len(val_str))
            ws.column_dimensions[col_letter].width = max(max_len + 4, 12)

        output = io.BytesIO()
        wb.save(output)
        output.seek(0)
        return output

    @classmethod
    def export_customers_to_csv(cls, customers: List[Customer]) -> str:
        """Generates CSV text representation for quick export."""
        output = io.StringIO()
        writer = csv.writer(output)
        writer.writerow([
            "Customer Code", "Bride Full Name", "Mobile Number",
            "Wedding Date", "Makeup Date", "Package", "Amount (INR)",
            "Salon Branch", "WhatsApp Consent", "Created Date", "Notes"
        ])

        for c in customers:
            writer.writerow([
                c.customer_code,
                c.full_name,
                c.mobile_number,
                c.wedding_date.strftime("%d/%m/%Y") if c.wedding_date else "",
                c.makeup_date.strftime("%d/%m/%Y") if c.makeup_date else "",
                c.service_package or "",
                f"{c.amount:.2f}" if c.amount is not None else "",
                c.salon_branch or "",
                "Yes" if c.whatsapp_consent else "No",
                c.created_at.strftime("%d/%m/%Y") if c.created_at else "",
                c.notes or "",
            ])

        return output.getvalue()
