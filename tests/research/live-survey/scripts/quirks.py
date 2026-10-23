import sys, re, html, glob, collections, unicodedata
# usage: quirks.py inst [glob]
inst=sys.argv[1]; pat=sys.argv[2] if len(sys.argv)>2 else '*'
files=sorted(glob.glob(f'raw/{inst}/{pat}.html'))
nonascii=collections.Counter(); ctx={}
qmark=collections.OrderedDict(); moj=collections.OrderedDict(); bb=collections.OrderedDict(); dbl=collections.OrderedDict(); rawtags=collections.OrderedDict()
seen=set()
for fn in files:
    s=open(fn,encoding='utf-8',errors='replace').read()
    body=re.sub(r'(?is)<script\b.*?</script\b[^>]*>|<style\b.*?</style\b[^>]*>|<head>.*?</head>','',s)
    for m in re.finditer(r'&amp;(amp|quot|lt|gt|#\d+|[a-z]+);',body):
        k=body[max(0,m.start()-40):m.end()+20].replace('\n',' ')
        dbl.setdefault(m.group(0),k)
    # text with tags removed
    t=re.sub(r'<[^>]+>',' ',body); t=html.unescape(t)
    for line in t.split('\n'):
        line=line.strip()
        if not line or line in seen: continue
        seen.add(line)
        for ch in set(line):
            if ord(ch)>127:
                nonascii[ch]+=1
                if ch not in ctx:
                    i=line.index(ch); ctx[ch]=(fn.split('/')[-1], line[max(0,i-50):i+30])
        for m in re.finditer(r'\w\?\w',line):
            qmark.setdefault(line[max(0,m.start()-40):m.end()+30], fn.split('/')[-1])
        for m in re.finditer(r'(Ã.|Â.|â€.|Ã¢|Ã©|Ã¨|\ufffd)',line):
            moj.setdefault(line[max(0,m.start()-40):m.end()+30], fn.split('/')[-1])
        for m in re.finditer(r'\[/?(b|i|u|url|quote|color|size|img|center|br|indent|list|\*)(=[^\]]*)?\]',line,re.I):
            bb.setdefault(m.group(0).lower(),(fn.split('/')[-1], line[max(0,m.start()-40):m.end()+40]))
        for m in re.finditer(r'<\s*/?\s*(a|b|i|em|strong|br|p|div|span|u|font)\b[^>]{0,60}>',line,re.I):
            rawtags.setdefault(m.group(0)[:30],(fn.split('/')[-1], line[max(0,m.start()-40):m.end()+40]))
print(f"### {inst}: {len(files)} files, {len(seen)} distinct text lines")
print("non-ASCII chars (char U+code name count | example):")
for ch,c in nonascii.most_common(60):
    try: name=unicodedata.name(ch)
    except: name='?'
    print(f"  {ch!r} U+{ord(ch):04X} {name} x{c} | {ctx[ch][0]}: {ctx[ch][1]!r}")
print("word?word (possible lost chars):", len(qmark))
for k,v in list(qmark.items())[:25]: print("   ",v,"|",k)
print("mojibake-like:", len(moj))
for k,v in list(moj.items())[:25]: print("   ",v,"|",k)
print("BBCode-like tags:", len(bb))
for k,v in list(bb.items())[:25]: print("   ",k,"|",v)
print("literal HTML tags in text (escaped in source):", len(rawtags))
for k,v in list(rawtags.items())[:15]: print("   ",k,"|",v)
print("double-escaped entities:", len(dbl))
for k,v in list(dbl.items())[:15]: print("   ",k,"|",v)
