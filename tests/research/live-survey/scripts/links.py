import sys, re, html, collections, urllib.parse
# usage: links.py file [file...]  -> prints p= routes with counts and example hrefs
for fn in sys.argv[1:]:
    s = open(fn, encoding='utf-8', errors='replace').read()
    hrefs = re.findall(r'''(?:href|src|action)\s*=\s*["']([^"']+)["']''', s, re.I)
    routes = collections.OrderedDict()
    other = collections.OrderedDict()
    for h in hrefs:
        h2 = html.unescape(h)
        m = re.search(r'[?&]p=([^&#]*)', h2)
        if m:
            r = m.group(1).lower()
            routes.setdefault(r, []).append(h2)
        else:
            other.setdefault(h2, 0); other[h2]+=1
    print("=====", fn, len(s), "bytes")
    themes = sorted(set(re.findall(r'themes/([\w\-]+)/', s)))
    print("themes referenced:", themes)
    for r, l in routes.items():
        ex = sorted(set(l), key=len)
        print(f"  p={r:40s} x{len(l):3d}  e.g. {ex[0][:150]}" + (f" | {ex[-1][:150]}" if len(ex)>1 else ""))
    print("  -- non-p links:")
    for h,c in list(other.items())[:60]:
        print("    ", c, h[:160])
