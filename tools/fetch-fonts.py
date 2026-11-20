# Downloads the heading font (Plus Jakarta Sans, Latin letters, weights 600-800) from Google Fonts
# into public/fonts, so the website serves it itself: no waiting on a second server before the
# page can show. Run on the build machine before the copy to the server.
import re, sys, urllib.request

UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36'
api = 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600..800&display=swap'
css = urllib.request.urlopen(urllib.request.Request(api, headers={'User-Agent': UA}), timeout=30).read().decode()
block = re.search(r'/\* latin \*/\s*@font-face\s*\{(.*?)\}', css, re.S)
if not block:
    sys.exit('no latin block in the Google Fonts answer')
url = re.search(r'url\((https://[^)]+\.woff2)\)', block.group(1)).group(1)
data = urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': UA}), timeout=30).read()
open('public/fonts/jakarta-latin.woff2', 'wb').write(data)
print('saved public/fonts/jakarta-latin.woff2', len(data), 'bytes')
