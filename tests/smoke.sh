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
check overgrown "$BASE/overgrown" "200"
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
check tests "$BASE/tests/catalog_rules.php" "403|404"
check notes-txt "$BASE/SETUP-NOTES.txt" "403|404"

body() {
  curl -fsS --max-time 20 "$1" || true
}
home=$(body "$BASE/")
contains() {
  name="$1"
  needle="$2"
  haystack="$3"
  if printf '%s' "$haystack" | grep -q "$needle"; then
    echo "PASS $name"
  else
    echo "FAIL $name missing $needle"
    fail=1
  fi
}
contains best-sellers 'id="best-sellers"' "$home"
contains new-arrivals 'id="new-arrivals"' "$home"
contains offers 'id="offers"' "$home"
contains offers-promo 'promo-card' "$home"
if printf '%s' "$home" | grep -q 'More offers soon'; then
  echo "FAIL offers-honest still shows More offers soon"
  fail=1
else
  echo "PASS offers-honest"
fi
contains new-cosmos '>Cosmos<' "$home"
contains new-aster '>Aster<' "$home"
contains price-list-link 'Full price list' "$home"
shop=$(body "$BASE/shop?q=marigold&sort=price&page=1")
contains shop-search '>Marigold<' "$shop"
product=$(body "$BASE/product/cosmos")
contains product-new 'new-badge' "$product"
contains product-price 'per seedling' "$product"
if printf '%s' "$product" | grep -q 'class="compare"'; then
  echo "FAIL product-no-fake-sale cineraria shows a comparison price"
  fail=1
else
  echo "PASS product-no-fake-sale"
fi
sitemap=$(body "$BASE/sitemap.xml")
contains sitemap-cineraria '/product/cineraria' "$sitemap"
if [ "$fail" = 0 ]; then
  echo "PASS smoke"
  exit 0
fi
echo "FAIL smoke"
exit 1
