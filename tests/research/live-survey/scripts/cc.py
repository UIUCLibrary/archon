import sys,re,html
# parse controlcard: label -> value
for fn in sys.argv[1:]:
    s=open(fn,encoding='utf-8',errors='replace').read()
    s2=re.sub(r'(?is)<script.*?</script>','',s)
    # cut location/request table noise
    s2=re.sub(r'(?is)<table[^>]*location.*?</table>','[LOCATIONTABLE]',s2)
    pat=re.compile(r"<(?:span|p|dt|div|b|strong)[^>]*class=['\"](?:ccardlabel|ccardsublabel|label)['\"][^>]*>(.*?)</(?:span|p|dt|div|b|strong)>",re.S|re.I)
    ms=list(pat.finditer(s2))
    print("=====",fn,len(s),"bytes;",len(ms),"labels")
    for i,m in enumerate(ms):
        lab=html.unescape(re.sub(r'<[^>]+>','',m.group(1))).strip()
        end=ms[i+1].start() if i+1<len(ms) else min(len(s2),m.end()+3000)
        raw=s2[m.end():end]
        val=re.sub(r'(?i)<br\s*/?>',' / ',raw); val=re.sub(r'<[^>]+>',' ',val); val=html.unescape(val); val=re.sub(r'\s+',' ',val).strip()
        print(f"  [{lab}] len={len(val)} :: {val[:260]}")
