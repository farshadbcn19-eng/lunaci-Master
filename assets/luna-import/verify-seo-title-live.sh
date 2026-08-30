#!/bin/bash
set -e

echo "--- EN homepage <title> ---"
curl -s "https://lunacibarcelona.com/?nocache=$(date +%s)" | grep -oE "<title>.*</title>"

echo ""
echo "--- ES homepage <title> ---"
curl -s "https://lunacibarcelona.com/es/?nocache=$(date +%s)" | grep -oE "<title>.*</title>"

echo ""
echo "--- EN homepage meta description ---"
curl -s "https://lunacibarcelona.com/?nocache=$(date +%s)" | grep -oE '<meta name="description"[^>]*>'

echo ""
echo "--- ES homepage meta description ---"
curl -s "https://lunacibarcelona.com/es/?nocache=$(date +%s)" | grep -oE '<meta name="description"[^>]*>'
