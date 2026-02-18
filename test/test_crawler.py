"""
Quick test to check if OSHA.gov allows scraping
"""
import httpx
from bs4 import BeautifulSoup

def test_osha_access():
    """Test if we can access OSHA.gov"""

    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8",
        "Accept-Language": "en-US,en;q=0.9",
        "Accept-Encoding": "gzip, deflate, br",
        "DNT": "1",
        "Connection": "keep-alive",
        "Upgrade-Insecure-Requests": "1",
        "Sec-Fetch-Dest": "document",
        "Sec-Fetch-Mode": "navigate",
        "Sec-Fetch-Site": "none",
        "Sec-Fetch-User": "?1",
        "Cache-Control": "max-age=0",
        "Referer": "https://www.google.com/",
    }

    url = "https://www.osha.gov/laws-regs"

    print(f"Testing access to: {url}")
    print("-" * 60)

    try:
        response = httpx.get(url, headers=headers, timeout=30, follow_redirects=True)

        print(f"Status Code: {response.status_code}")
        print(f"Headers: {dict(response.headers)}")
        print("-" * 60)

        if response.status_code == 200:
            print("✅ SUCCESS! Page accessible")
            soup = BeautifulSoup(response.text, "html.parser")
            title = soup.find("title")
            print(f"Page Title: {title.get_text() if title else 'No title'}")
            print(f"Content Length: {len(response.text)} chars")

            # Show first 500 chars
            print("\nFirst 500 chars of content:")
            print(response.text[:500])

        elif response.status_code == 403:
            print("❌ BLOCKED! 403 Forbidden")
            print("\nResponse body:")
            print(response.text[:500])

        else:
            print(f"⚠️  Unexpected status: {response.status_code}")
            print(response.text[:500])

    except Exception as e:
        print(f"❌ ERROR: {e}")

if __name__ == "__main__":
    test_osha_access()
