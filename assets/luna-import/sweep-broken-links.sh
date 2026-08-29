#!/bin/bash
set -e

SITE="https://lunacibarcelona.com"
SEEDS=(
  "$SITE/"
  "$SITE/es/"
  "$SITE/shop/"
  "$SITE/tienda/"
  "$SITE/product-category/face/"
  "$SITE/product-category/eyes/"
  "$SITE/product-category/lips/"
  "$SITE/product-category/nails/"
  "$SITE/about-us/"
  "$SITE/contact/"
  "$SITE/shipping/"
  "$SITE/returns/"
  "$SITE/privacy-policy/"
  "$SITE/terms-of-service/"
)

TMPDIR=$(mktemp -d)
ALL_LINKS="$TMPDIR/all_links.txt"
: > "$ALL_LINKS"

for url in "${SEEDS[@]}"; do
  page="$TMPDIR/page.html"
  code=$(curl -s -o "$page" -w "%{http_code}" -L "$url?nocache=$(date +%s)")
  echo "SEED $url -> $code"
  if [ "$code" != "200" ]; then
    echo "  WARNING: seed page itself returned $code"
  fi
  grep -oE 'href="https://lunacibarcelona\.com[^"]*"' "$page" | sed -E 's/href="//; s/"$//' >> "$ALL_LINKS" || true
  grep -oE "href='https://lunacibarcelona\.com[^']*'" "$page" | sed -E "s/href='//; s/'$//" >> "$ALL_LINKS" || true
done

sort -u "$ALL_LINKS" -o "$ALL_LINKS"
TOTAL=$(wc -l < "$ALL_LINKS")
echo ""
echo "--- unique internal links found: $TOTAL ---"

echo ""
echo "--- checking status of each unique link ---"
BAD_COUNT=0
while IFS= read -r link; do
  [ -z "$link" ] && continue
  code=$(curl -s -o /dev/null -w "%{http_code}" -L --max-time 15 "$link")
  if [ "$code" != "200" ] && [ "$code" != "301" ] && [ "$code" != "302" ]; then
    echo "BROKEN ($code): $link"
    BAD_COUNT=$((BAD_COUNT+1))
  fi
done < "$ALL_LINKS"

echo ""
echo "--- sweep complete: $BAD_COUNT broken link(s) out of $TOTAL checked ---"
rm -rf "$TMPDIR"
