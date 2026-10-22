import sys,re,html
for fn in sys.argv[1:]:
    s=open(fn,encoding='utf-8',errors='replace').read()
    print("=====",fn,len(s))
    m=re.search(r"<div id='digcontentfiles'.*?(?=<div id='digcontentmetadata')",s,re.S)
    if m:
        f=m.group(0)
        print("  FILES block:", re.sub(r'\s+',' ',html.unescape(re.sub(r'<[^>]+>',' ',f))).strip()[:400])
        print("  file links:", sorted(set(re.findall(r"getfile&amp;id=\d+(?:&amp;preview=\w+)?",f)))[:8], "imgs:", re.findall(r"<img[^>]*alt='([^']*)'",f)[:5])
    for lab,val in re.findall(r"<div class='digcontentlabel'>(.*?)</div>\s*<div class='digcontentdata'>(.*?)</div>\s*(?=<div class='digcontent|</div>)",s,re.S):
        v=re.sub(r'(?i)<br\s*/?>|</p>',' / ',val); v=re.sub(r'<[^>]+>',' ',v); v=re.sub(r'\s+',' ',html.unescape(v)).strip()
        print(f"  [{html.unescape(re.sub('<[^>]+>','',lab)).strip()}] len={len(v)} :: {v[:300]}")
