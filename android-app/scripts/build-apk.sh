#!/usr/bin/env bash
# Adomin Invoices — one-shot debug APK build.
#
#   ./scripts/build-apk.sh https://your-app.onrender.com
#
# Writes ./AdominInvoices-debug.apk next to this script's parent folder. Requires
# Node 20+, JDK 17 and an Android SDK (ANDROID_HOME or Android Studio defaults).
set -euo pipefail

HOST="${1:-}"
if [[ -z "$HOST" ]]; then
  echo "usage: $0 https://your-app.onrender.com" >&2
  exit 2
fi

cd "$(dirname "$0")/.."
APP_DIR="$(pwd)"

# point the shell at the deployment before anything else
node -e '
  const fs = require("fs");
  const file = "capacitor.config.json";
  const cfg = JSON.parse(fs.readFileSync(file, "utf8"));
  const host = process.argv[1].replace(/^https?:\/\//, "").replace(/\/$/, "");
  cfg.server.url = "https://" + host;
  cfg.android.allowNavigation = [host];
  fs.writeFileSync(file, JSON.stringify(cfg, null, 2) + "\n");
  console.log("capacitor: -> https://" + host);
' "$HOST"

[[ -d node_modules ]] || npm install
npx cap add android >/dev/null 2>&1 || true
npx cap sync android

# brand assets (adaptive icon + splash) — skipped if the tool cannot run offline
npx @capacitor/assets generate --android >/dev/null 2>&1 || \
  echo "note: icon generation skipped; re-run 'npm run icons' when online"

( cd android && ./gradlew --quiet assembleDebug )

OUT="android/app/build/outputs/apk/debug/app-debug.apk"
if [[ -f "$OUT" ]]; then
  cp "$OUT" "$APP_DIR/AdominInvoices-debug.apk"
  echo
  echo "APK ready: $APP_DIR/AdominInvoices-debug.apk"
  echo "install:   adb install -r AdominInvoices-debug.apk"
else
  echo "gradle produced no apk; run 'npx cap open android' and read the errors" >&2
  exit 1
fi
