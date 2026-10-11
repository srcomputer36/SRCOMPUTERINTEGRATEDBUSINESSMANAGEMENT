#!/bin/bash
# Run when ZIP arrives: bash docs/audit_zip.sh /tmp/pcc_legacy
DIR=${1:-/tmp/pcc_legacy}
echo "=== Listing ZIP contents ==="
find "$DIR" -type f | sort
echo "=== Grep for key patterns ==="
grep -R "SPREADSHEET_ID\|DriveApp\|appendRow\|google.script.run\|doGet\|doPost" "$DIR" 2>/dev/null | head -n 100
echo "=== HTML files ==="
find "$DIR" -name "*.html" -exec ls -l {} \;
echo "=== GS files ==="
find "$DIR" -name "*.gs" -o -name "appsscript.json" | xargs ls -l 2>/dev/null
