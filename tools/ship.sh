#!/usr/bin/env bash
# Rebuild page.json + payloads, commit, push, and deploy to the live site.
# Usage: bash ship.sh "commit message"
set -e
MSG="${1:-Update Entire RCM site}"
export PATH="$LOCALAPPDATA/hermes/tools/gh-2.102.0-win32-x64/bin:$LOCALAPPDATA/hermes/tools/git-2.53.0+3-win32-x64/cmd:$PATH"
B="$LOCALAPPDATA/hermes/cache/scratch/entirercm/build"
S="$LOCALAPPDATA/hermes/cache/scratch/entirercm/stage"
PHP="$LOCALAPPDATA/../Local/Temp/php74/php.exe"

cd "$B" && python build_page.py
cp "$B/page.json"                  "$S/entire-rcm/payload/elementor/"
cp "$B/payload/css/design.css"     "$S/entire-rcm/payload/css/"
cp "$B/payload/js/theme.js"        "$S/entire-rcm/payload/js/"
cp "$B/payload/img/entire-rcm-logo.png" "$S/entire-rcm/payload/img/"
cp "$B/build_page.py"              "$S/tools/"

if [ -x "$TMPDIR/php74/php.exe" ]; then
  "$TMPDIR/php74/php.exe" -l "$(cygpath -w "$S/entire-rcm-boot.php")"
  "$TMPDIR/php74/php.exe" -l "$(cygpath -w "$S/entire-rcm/inc/installer.php")"
fi

cd "$S"
git add -A
git diff --cached --quiet || git commit -q -m "$MSG"
git push -q origin main
echo "SHIPPED: $MSG"
