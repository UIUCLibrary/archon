import sys, re, html
# usage: txt.py file [maxchars] [start_marker]
fn=sys.argv[1]; mx=int(sys.argv[2]) if len(sys.argv)>2 else 4000
s=open(fn,encoding='utf-8',errors='replace').read()
t=re.sub(r'(?is)<script\b.*?</script\b[^>]*>|<style\b.*?</style\b[^>]*>|<head>.*?</head>','',s)
t=re.sub(r'(?i)<br\s*/?>|</p>|</div>|</li>|</tr>|</h\d>|<dt>|<dd>','\n',t)
t=re.sub(r'<[^>]+>',' ',t); t=html.unescape(t)
t=re.sub(r'[ \t\r\f\v]+',' ',t); t=re.sub(r'\n\s*\n+','\n',t)
if len(sys.argv)>3:
    i=t.find(sys.argv[3]); t=t[i:] if i>=0 else t
print(t[:mx])
