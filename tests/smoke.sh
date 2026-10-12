#!/bin/sh
# Hits the local shop and prints PASS or FAIL. Base URL is the first argument.
set -u
BASE="${1:-http://127.0.0.1:8080}"
fail=0
check() {
  name="$1"
  url="$2"
  expect="$3"
  code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 15 "$url" || echo 000)
  ok=0
  oldifs=$IFS
  IFS='|'
  for want in $expect; do
    if [ "$code" = "$want" ]; then
      ok=1
    fi
  done
  IFS=$oldifs
  if [ "$ok" = 1 ]; then
    echo "PASS $name $code"
  else
    echo "FAIL $name got $code wanted $expect"
    fail=1
  fi
}
check home "$BASE/" "200"
check shop "$BASE/shop" "200"
check cart "$BASE/cart" "200"
check checkout "$BASE/checkout" "200"
check login "$BASE/login" "200"
check robots "$BASE/robots.txt" "200"
check config "$BASE/config/config.php" "403|404"
check includes "$BASE/includes/bootstrap.php" "403|404"
check schema "$BASE/database/schema.sql" "403|404"
check notes "$BASE/SETUP-NOTES.txt" "403|404"
check private "$BASE/uploads/private/x.webp" "403|404"
check cron "$BASE/cron.php" "403|404"
check setup "$BASE/setup" "200|403|404"
if [ "$fail" = 0 ]; then
  echo "PASS smoke"
  exit 0
fi
echo "FAIL smoke"
exit 1
