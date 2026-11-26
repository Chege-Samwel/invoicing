# Adomin Invoices — Android app

The app you install on a phone is this thin Android shell around the hosted
CodeIgniter app, so there is exactly one source of truth (the server: same
invoices, same PDFs, same login) and the Play Store requirement for an `.aab`
is satisfied. It is a Capacitor project: `server.url` points at your Render
(installable-PWA alternative lives in the repo root: `manifest.json` + `sw.js`).

Two ways to ship, pick one:

| Path | Effort | What users get |
| --- | --- | --- |
| **PWA only** | zero build | Chrome on Android: *Add to home screen*, offline shell, share links, install banner. Nothing to publish. |
| **Capacitor APK/AAB** (this folder) | Node + Android Studio | A real icon, splash screen, no browser chrome, Play Store listing, `AdominApp/1.0` user agent, native share sheet. |

## 1. Point the shell at your deployment

Edit `capacitor.config.json`:

```jsonc
"server": { "url": "https://your-app.onrender.com" },
"android": { "allowNavigation": ["your-app.onrender.com"] }
```

Same host in both places, `https://` only (Render gives you the certificate).

## 2. Build a debug APK (testing on your own phone)

```bash
cd android-app
npm install
npx cap add android          # creates ./android the first time
npx cap sync android
npm run build:debug          # ./gradlew assembleDebug
adb install -r android/app/build/outputs/apk/debug/app-debug.apk
```

`npx cap open android` opens the same project in Android Studio when you want
logcat, a layout preview, or Play-target API-level tweaks.

Requirements: Node 20+, JDK 17, Android Studio (Platform-Tools + Build-Tools),
Android SDK 34. Capacitor 6 needs `compileSdk 34` — the generated project
already targets it.

## 3. Release build for the Play Store

```bash
# one-off: a keystore you must back up (losing it means losing the listing)
keytool -genkey -v -keystore adomin-invoices.keystore \
  -alias adomin -keyalg RSA -keysize 2048 -validity 10000

# sign the bundle (never commit the keystore or the passwords)
export STORE_FILE=$PWD/adomin-invoices.keystore
export STORE_PASSWORD='...'
export KEY_ALIAS=adomin
export KEY_PASSWORD='...'
cd android && ./gradlew bundleRelease   # -> app/build/outputs/bundle/release/app-release.aab
```

Play Console checklist: app name *Adomin Invoices*, short description
"Invoicing, PDF delivery and stock for Kenyan SMEs", category Business,
privacy policy URL (required even though the app sends data only to your own
server), data-safety declaration (your server + Google Play sign-in if you add
it), content rating questionnaire, and 2 phone screenshots + a 512×512 icon
(already in `resources/`). Upload the `.aab`; the signing key can be managed by
Play (App Signing by Play) — recommended.

## 4. Icons and splash

`resources/icon.png` (512×512), `resources/icon-only.png` (1024×1024) and
`resources/splash.png` (2732×2732) are generated from
`assets/public/imgs/icons/icon-512.png`. Regenerate all densities with:

```bash
npm run icons                # npx @capacitor/assets generate --android --ios
```

`@capacitor/assets` needs a `resources/icon-only.png` on a white background
(that is why the source icon is flattened); the adaptive icon gets the brand
blue as its background layer.

## 5. What the native layer adds

* **Splash screen** in brand blue with the Adomin mark, so the ~1 minute cold
  start of a sleeping Render free instance looks intentional instead of broken.
* **Status bar** tinted `#0b6bcb`, light icons (`plugins.StatusBar`).
* **Share sheet** (`@capacitor/share`) — the *Send via link* sheet in the web
  app calls `navigator.share`, which Capacitor answers with the native
  WhatsApp/SMS/Drive chooser. No code change needed on the web side.
* **Browser** (`@capacitor/browser`) for e-mail/`mailto:` hand-off.
* **User agent** gets `AdominApp/1.0 (Android)`, which is how the PHP side can
  tell it is inside the shell (`window.Capacitor` in JS,
  `strpos($_SERVER['HTTP_USER_AGENT'], 'AdominApp')` in PHP) if you ever want
  app-only UI.

## 6. Keeping it honest

The shell has no business logic: no duplicated invoice forms, no offline queue,
no second implementation of numbering or VAT. If the web app works, the phone
app works. Deep changes (native camera receipt scan, offline draft invoices)
belong in a Capacitor plugin under `android-app/native/` — a stub folder is
there for that, and nothing in it is wired up yet.

## 7. Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| Blank white screen | `server.url` host is not in `allowNavigation`, or the app slept: pull to refresh, or check `curl -I https://your-app.onrender.com` |
| "net::ERR_SSL_VERSION..." | you used `http://` in `server.url`; Render redirects to https |
| Login works in a browser, not in the app | cookies are fine by default, but a session cookie with `SameSite=None` needs `https`; keep the scheme https |
| Keyboard covers the invoice form | Android 15 edge-to-edge — set `android:windowSoftInputMode="adjustResize"` in `android/app/src/main/AndroidManifest.xml` |
| Splash stays forever | `launchAutoHide: true` and the site must return 200 for `/` (the health check path in `render.yaml`) |
