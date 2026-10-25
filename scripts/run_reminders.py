import os
import sys
from pathlib import Path
from datetime import datetime, timezone

# Add project root directory to sys.path
BASE_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BASE_DIR))

# Ensure UTF-8 output on Windows consoles
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

from app import create_app
from app.services.reminder_service import ReminderService


def main():
    """Daily cron runner for Safiya's Makeover Bridal CRM."""
    start_time = datetime.now(timezone.utc)
    print(f"[*] Starting Daily Anniversary Check: {start_time.isoformat()}")

    app = create_app()
    with app.app_context():
        try:
            result = ReminderService.process_daily_reminders()
            print(f"[+] Daily check completed successfully:")
            print(f"    - Date: {result['today']}")
            print(f"    - Today's Anniversaries: {result['todays_count']}")
            print(f"    - Upcoming 7 Days: {result['upcoming_count']}")
            print(f"    - WhatsApp Dispatches: {result['whatsapp_dispatches']}")
            print(f"    - Owner Daily Digest: {result['owner_digest_status']}")
            sys.exit(0)
        except Exception as e:
            print(f"[!] Error during daily anniversary check: {str(e)}", file=sys.stderr)
            import traceback
            traceback.print_exc()
            sys.exit(1)


if __name__ == "__main__":
    main()
