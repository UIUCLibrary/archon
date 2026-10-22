import re,glob,html,urllib.parse as up
from collections import OrderedDict
BASE={'ihlc':'https://archon.library.illinois.edu/ihlc/index.php','libweb':'https://archon.library.illinois.edu/archives/index.php',
      'rbml':'https://archon.library.illinois.edu/rbml/index.php','ala':'https://archon.library.illinois.edu/ala/index.php',
      'sousa2':'https://archon.library.illinois.edu/archives/index.php'}
KEEPVAL={'templateset','disabletheme','preview','f','subjecttypeid'}
def pattern(url):
    u=up.urlsplit(url)
    q=up.parse_qsl(u.query,keep_blank_values=True)
    p=dict(q).get('p','(none: '+(u.path or '/')+')')
    parts=[]
    for k,v in q:
        if k=='p': continue
        if k in KEEPVAL: parts.append(f'{k}={v}')
        elif v=='' : parts.append(k)
        else: parts.append(f'{k}=<{k}>')
    frag='#'+re.sub(r'\d+','N',u.fragment) if u.fragment else ''
    return p+(' &'+'&'.join(sorted(parts)) if parts else '')+frag
fetched=OrderedDict()
for line in open('requests.log',encoding='utf-8'):
    c=line.rstrip('\n').split('\t')
    if len(c)<9 or not c[8].startswith('http'): 
        continue
    inst,status,url=c[2],c[4],c[8]
    key=(inst,pattern(url))
    if key not in fetched: fetched[key]=[url,{},0]
    fetched[key][1][status]=fetched[key][1].get(status,0)+1; fetched[key][2]+=1
    if status=='200' and fetched[key][1].get('200')==1: fetched[key][0]=url
linked=OrderedDict()
for f in sorted(glob.glob('raw/*/*.html')):
    inst=f.split('/')[1]
    b=open(f,'rb').read()
    if b[:2]==b'\xff\xd8': continue
    s=b.decode('utf-8','replace')
    for h in re.findall(r"""href\s*=\s*['"]([^'"]*\?[^'"]*p=[^'"]*)['"]""",s):
        h=html.unescape(h)
        if h.startswith('http') and 'archon.library.illinois.edu' not in h: continue
        absu=up.urljoin(BASE[inst],h)
        key=(inst,pattern(absu))
        if key not in fetched and key not in linked: linked[key]=absu
out=[]
out.append('# Archon live survey: one line per distinct route pattern per instance.')
out.append('# Format: <instance> | <route pattern: p=value &param-shape> | <status> | <example full URL>')
out.append('# status: "fetched HTTP <code> (xN)" = requested during survey; "linked-only" = seen in page links but not requested;')
out.append('#         "linked-only FORBIDDEN" = state-changing (f=...) or admin/login link, deliberately never requested.')
out.append('# Instances: ihlc=/ihlc/ (theme ihlc); libweb=/archives/?settheme=library_web&setrepositoryid=0; rbml=/rbml/ (theme rbml);')
out.append('#            ala=/ala/ (theme ala_redo); sousa2=/archives/?settheme=sousa2&setrepositoryid=2 (same host path & DB as libweb, separate cookie jar).')
for inst in ['ihlc','libweb','rbml','ala','sousa2']:
    out.append('')
    out.append(f'## {inst}')
    rows=[]
    for (i,pat),(url,st,n) in fetched.items():
        if i==inst: rows.append((pat,'fetched x%d (HTTP %s)'%(n,', '.join('%s x%d'%(k,v) for k,v in sorted(st.items()))),url))
    for (i,pat),url in linked.items():
        if i!=inst: continue
        bad=bool(re.search(r'[?&]f=|admin/|core/login|core/account|core/register|logout',url))
        rows.append((pat,'linked-only FORBIDDEN' if bad else 'linked-only',url))
    for pat,st,url in sorted(rows):
        out.append(f'{inst} | {pat} | {st} | {url}')
open('urls.txt','w',encoding='utf-8').write('\n'.join(out)+'\n')
print(len(out),'lines')
