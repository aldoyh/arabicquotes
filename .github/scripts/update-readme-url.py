import re, os

url = os.environ.get('SITE_URL', '')
if not url:
    print("No SITE_URL set, skipping README update")
    exit(0)

label = url.removeprefix('https://').rstrip('/')
replacement = (
    '<!-- HERENOW_URL:START -->\n'
    f'🌐 [{label}]({url})\n'
    '<!-- HERENOW_URL:END -->'
)

with open('README.md') as f:
    content = f.read()

updated = re.sub(
    r'<!-- HERENOW_URL:START -->.*?<!-- HERENOW_URL:END -->',
    replacement,
    content,
    flags=re.DOTALL,
)

with open('README.md', 'w') as f:
    f.write(updated)

print(f"Updated README with URL: {url}")
