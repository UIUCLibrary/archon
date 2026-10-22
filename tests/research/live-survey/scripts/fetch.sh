#!/usr/bin/env bash
# Polite GET-only fetcher. usage: fetch.sh <instance> <name> <url>
# body -> raw/<inst>/<name>.html ; headers -> raw/<inst>/<name>.hdr ; log -> requests.log
set -u
OUT="${SURVEY_OUT:-$PWD/live-survey-out}"
mkdir -p "$OUT/cookies"
inst="$1"; name="$2"; url="$3"; shift 3; extra=("$@")
# safety rules: no state-changing f=, no admin/login/account pages
if echo "$url" | grep -Eiq '[?&]f=|/admin|p=admin|core/login|core/account|core/register|core/editprofile|logout|unsetall'; then
  echo "BLOCKED by rule: $url" >&2; exit 2
fi
mkdir -p "$OUT/raw/$inst"
cnt=$(cat "$OUT/.count" 2>/dev/null || echo 0)
if [ "$cnt" -ge 400 ]; then echo "REQUEST BUDGET EXHAUSTED ($cnt)" >&2; exit 3; fi
# rate limit: >=1.2s between any two requests (global)
now=$(date +%s.%N); last=$(cat "$OUT/.last" 2>/dev/null || echo 0)
wait=$(python3 -c "print(max(0.0, 1.2-($now-$last)))")
sleep "$wait"
echo $((cnt+1)) > "$OUT/.count"
res=$(curl -sS --max-time 30 --compressed -b "$OUT/cookies/$inst.txt" -c "$OUT/cookies/$inst.txt" \
  -D "$OUT/raw/$inst/$name.hdr" -o "$OUT/raw/$inst/$name.html" \
  -w '%{http_code}\t%{size_download}\t%{time_total}\t%{content_type}' "${extra[@]}" "$url" 2>&1)
date +%s.%N > "$OUT/.last"
printf '%s\t%s\t%s\t%s\t%s\t%s\n' "$(date -u +%FT%TZ)" "$((cnt+1))" "$inst" "$name" "$res" "$url" >> "$OUT/requests.log"
echo "#$((cnt+1)) $inst/$name -> $res"
