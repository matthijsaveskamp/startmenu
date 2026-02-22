from playwright.sync_api import sync_playwright
import time

def verify():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Test homepage
        page.goto("http://localhost:8000/")
        page.screenshot(path="homepage.png")
        print("Homepage screenshot taken")

        # Test admin page
        page.goto("http://localhost:8000/admin.php")
        page.screenshot(path="admin_login.png")
        print("Admin login screenshot taken")

        # Try to access protected file
        response = page.goto("http://localhost:8000/data/links.json")
        print(f"Access to /data/links.json status: {response.status}")

        browser.close()

if __name__ == "__main__":
    verify()
