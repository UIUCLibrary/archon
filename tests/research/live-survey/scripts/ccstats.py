import re,html,glob,sys
from collections import defaultdict,Counter
pat=re.compile(r"<(?:span|p|dt|div|b|strong)[^>]*class=['\"](?:ccardlabel|ccardsublabel|label)['\"][^>]*>(.*?)</(?:span|p|dt|div|b|strong)>",re.S|re.I)
stats=defaultdict(lambda: defaultdict(list)); ncards=Counter()
for fn in sorted(glob.glob('raw/*/controlcard_*.html')):
    if '_print' in fn: continue
    inst=fn.split('/')[1]; ncards[inst]+=1
    s=open(fn,encoding='utf-8',errors='replace').read()
    s2=re.sub(r'(?is)<script\b.*?</script\b[^>]*>','',s)
    s2=re.sub(r'(?is)<table.*?</table>',' ',s2)
    ms=list(pat.finditer(s2))
    seen=set()
    for i,m in enumerate(ms):
        lab=html.unescape(re.sub(r'<[^>]+>','',m.group(1))).strip().rstrip(':').strip()
        if lab in seen: continue
        seen.add(lab)
        end=ms[i+1].start() if i+1<len(ms) else min(len(s2),m.end()+3000)
        raw=s2[m.end():end]
        raw=re.split(r'Log In|Submit request|Printer-friendly|id=.loginform',raw)[0]
        val=re.sub(r'<[^>]+>',' ',raw); val=html.unescape(val); val=re.sub(r'\s+',' ',val).strip()
        stats[inst][lab].append(len(val))
for inst in ['ihlc','libweb','sousa2','rbml','ala']:
    print(f'== {inst} ({ncards[inst]} cards)')
    for lab,L in sorted(stats[inst].items(),key=lambda x:-len(x[1])):
        L=sorted(L); print(f'   {len(L):2d}x  {lab[:45]:45s} len med {L[len(L)//2]:5d} max {L[-1]:5d}')
