import os
from app import create_app
from app.config import DevelopmentConfig

app = create_app(DevelopmentConfig)

if __name__ == "__main__":
    port = int(os.getenv("PORT", 5000))
    print(f"[*] Starting Safiya's Makeover CRM locally on http://127.0.0.1:{port}")
    app.run(host="127.0.0.1", port=port, debug=False, use_reloader=False)
