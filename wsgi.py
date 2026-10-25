import os
from app import create_app

# Instantiate production application
app = create_app()

if __name__ == "__main__":
    app.run()
