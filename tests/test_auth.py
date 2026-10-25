from app.models.user import User


def test_login_success(client):
    res = client.post("/auth/login", data={"username": "admin", "password": "Admin@12345"}, follow_redirects=True)
    assert res.status_code == 200
    assert b"Studio Dashboard" in res.data or b"Dashboard" in res.data


def test_login_invalid_password(client):
    res = client.post("/auth/login", data={"username": "admin", "password": "WrongPassword"}, follow_redirects=True)
    assert res.status_code == 200
    assert b"Invalid username or password" in res.data


def test_logout(auth_client):
    res = auth_client.get("/auth/logout", follow_redirects=True)
    assert res.status_code == 200
    assert b"Sign In" in res.data


def test_protected_route_redirects_anonymous(client):
    res = client.get("/customers/", follow_redirects=False)
    assert res.status_code == 302
    assert "/auth/login" in res.headers["Location"]
