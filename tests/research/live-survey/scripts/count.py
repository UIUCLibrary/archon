import subprocess, re, sys, os
# usage: count.py inst base route itemregex est
inst, base, route, itemre, est = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4], int(sys.argv[5])
LIMIT=40
cache={}
def probe(page):
    if page in cache: return cache[page]
    if len(cache)>=9 or page>2000: print('ABORT: probe cap reached'); sys.exit(1)
    name=f"{route.replace('/','_')}_browse_p{page}"
    url=f"{base}?p={route}&browse" + (f"&page={page}" if page>1 else "")
    if route=='subjects/subjects': url=f"{base}?p={route}&browse&subjecttypeid=0" + (f"&page={page}" if page>1 else "")
    path=f"raw/{inst}/{name}.html"
    if not os.path.exists(path):
        out=subprocess.run(["./fetch.sh",inst,name,url],capture_output=True,text=True).stdout.strip()
        print(out, flush=True)
        status=out.split('-> ')[1].split('\t')[0]
    else:
        status='200'
        hdr=open(f"raw/{inst}/{name}.hdr").read()
        status=hdr.split()[1]
    if status!='200': cache[page]=0; return 0
    s=open(path,encoding='utf-8',errors='replace').read()
    n=len(set(re.findall(itemre,s)))
    nxt='paginationnextlink' in s
    if n==0: print(f"WARN: status 200 but 0 items matched on page {page}"); n=-1
    cache[page]=n if not nxt else LIMIT+1
    return cache[page]
# find hi such that probe(hi)==0, lo such that probe(lo)>0
lo=1; hi=None; p=max(2,est)
while hi is None:
    if probe(p)!=0: lo=p; p*=2
    else: hi=p
while hi-lo>max(1,int(lo*0.1)):
    mid=(lo+hi)//2
    if probe(mid)!=0: lo=mid
    else: hi=mid
last=probe(lo)
total=(lo-1)*LIMIT+(last if last<=LIMIT else LIMIT)
exact = (hi-lo==1)
print(f"RESULT {inst} {route}: last_page_seen={lo} first_empty={hi} items_on_lo={min(last,LIMIT) if last!=-1 else '?'} total~={total if exact else str((lo-1)*LIMIT)+'..'+str((hi-1)*LIMIT)} exact={exact} probes={len(cache)}")
