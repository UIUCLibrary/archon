import sys,re,html,collections
from html.parser import HTMLParser
# Analyze Archon finding-aid content (<dl>/<dt class=faitem>) nesting
class P(HTMLParser):
    def __init__(s):
        super().__init__(convert_charrefs=True); s.depth=0; s.items=[]; s.in_dt=False; s.buf=''; s.cur=None; s.in_dd_text=False; s.notes=[]; s.ddbuf=None; s.dddepth=[]; s.links=[]
    def handle_starttag(s,tag,a):
        a=dict(a)
        if tag=='dl': s.depth+=1
        if tag=='dt' and 'faitem' in (a.get('class') or ''):
            s.in_dt=True; s.buf=''; s.cur={'depth':s.depth}
        if tag=='a' and a.get('href'):
            s.links.append(a['href'])
            if s.in_dt: s.cur.setdefault('links',[]).append(a['href'])
        if tag=='dd':
            if 'faitemcontent' in (a.get('class') or '') and s.ddbuf is None:
                s.ddbuf=''; s.ddlevel=0
            if s.ddbuf is not None: s.ddlevel+=1
        if tag=='img' and s.in_dt: s.cur.setdefault('imgs',[]).append(a.get('src',''))
        if tag=='br' and s.in_dt: s.buf+=' / '
    def handle_endtag(s,tag):
        if tag=='dl': s.depth-=1
        if tag=='dd' and s.ddbuf is not None:
            s.ddlevel-=1
            if s.ddlevel==0:
                s.notes.append(re.sub(r'\s+',' ',s.ddbuf).strip()); s.ddbuf=None
        if tag=='dt' and s.in_dt:
            s.in_dt=False; s.cur['text']=re.sub(r'\s+',' ',s.buf).strip(); s.items.append(s.cur)
    def handle_data(s,d):
        if s.in_dt: s.buf+=d
        if s.ddbuf is not None: s.ddbuf+=d+' '
for fn in sys.argv[1:]:
    raw=open(fn,encoding='utf-8',errors='replace').read()
    p=P(); p.feed(raw)
    it=p.items
    print("=====",fn,len(raw),"bytes; faitems:",len(it))
    if not it: continue
    md=max(i['depth'] for i in it); mind=min(i['depth'] for i in it)
    print(" depth range:",mind,"..",md, " depth histogram:",dict(sorted(collections.Counter(i['depth']-mind+1 for i in it).items())))
    lab=collections.Counter()
    for i in it:
        m=re.match(r"([A-Za-z][A-Za-z\- ]{0,25}?)\s*[\d#]",i['text'])
        lab[m.group(1).strip() if m else '(no-label: '+i['text'][:20]+')']+=1
    print(" level labels:",lab.most_common(25))
    lens=sorted(len(i['text']) for i in it)
    print(" text length: median",lens[len(lens)//2],"p95",lens[int(len(lens)*.95)],"max",lens[-1])
    print(" longest:",max(it,key=lambda i:len(i['text']))['text'][:400])
    dl=[i for i in it if any('digitalcontent' in l or 'getfile' in l for l in i.get('links',[]))]
    print(" items with digital content links:",len(dl), [ (i['text'][:60], [l for l in i['links'] if 'digital' in l][:1]) for i in dl[:3]])
    rest=[i for i in it if 'information restricted' in i['text'].lower() or 'restricted' in i['text'].lower()]
    print(" items mentioning restricted:",len(rest),[i['text'][:80] for i in rest[:3]])
    nl=p.notes
    print(" item notes (dd.faitemcontent):",len(nl))
    if nl:
        nls=sorted(len(x) for x in nl); print("   note len median",nls[len(nls)//2],"max",nls[-1])
        kinds=collections.Counter(re.match(r'^([A-Za-z /]{2,40}?):',x).group(1) if re.match(r'^([A-Za-z /]{2,40}?):',x) else '(free text)' for x in nl)
        print("   note kinds:",kinds.most_common(10))
        for x in nl[:6]: print("   note:",x[:200])
    print(" samples by depth:")
    seen=collections.Counter()
    for i in it:
        d=i['depth']-mind+1
        if seen[d]<4: seen[d]+=1; print(f"   d{d}: {i['text'][:160]}")
