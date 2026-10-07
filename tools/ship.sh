#!/usr/bin/env bash
# Rebuild the payloads, commit and push. The caller then runs the Hostinger deploy.
set -e
MSG="${1:-Update Entire RCM site}"
export PATH="$LOCALAPPDATA/hermes/tools/gh-2.102.0-win32-x64/bin:$LOCALAPPDATA/hermes/tools/git-2.53.0+3-win32-x64/cmd:$PATH"
B="$LOCALAPPDATA/hermes/cache/scratch/entirercm/build"
S="$LOCALAPPDATA/hermes/cache/scratch/entirercm/stage"

# page.json is produced by the Stitch DOM converter (re-run through the browser);
# the stylesheet is Tailwind compiled from the Stitch export, bundled with the
# Elementor reconciliation layers.
python "$LOCALAPPDATA/hermes/cache/scratch/entirercm/build/mkpage.py"
bash "$LOCALAPPDATA/hermes/cache/scratch/entirercm/tw/bundle.sh"
cp "$B/page.json"                             "$S/entire-rcm/payload/elementor/page.json"
cp "$B/entire-rcm/payload/css/entire-rcm.css" "$S/entire-rcm/payload/css/entire-rcm.css"
cp "$B/theme-new.js"                          "$S/entire-rcm/payload/js/theme.js"
cp "$B/payload/img/entire-rcm-logo.png"       "$S/entire-rcm/payload/img/entire-rcm-logo.png"

if [ -x "$TMPDIR/php74/php.exe" ]; then
  "$TMPDIR/php74/php.exe" -l "$(cygpath -w "$S/entire-rcm-boot.php")"
  "$TMPDIR/php74/php.exe" -l "$(cygpath -w "$S/entire-rcm/inc/installer.php")"
fi

cd "$S"
git add -A
git diff --cached --quiet || git commit -q -m "$MSG"
git push -q origin main
echo "SHIPPED: $MSG"
