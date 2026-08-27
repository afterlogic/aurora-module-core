# Aurora Core module
System module that provides core functionality such as User management, Tenants management.

# Development
This repository has a pre-commit hook. To make it work you need to configure git to use the particular hooks folder.

`git config --local core.hooksPath .githooks/`

# Firebase App Check

The `FirebaseAppCheck` module setting (`data/settings/modules/Core.config.json`) lets the backend reject mobile-client login requests that don't carry a valid Firebase App Check token, protecting the `Login` API method from being called by anything other than a genuine build of the mobile app.

Verification runs in `Subscriptions::onBeforeLogin()`: if the `X-Firebase-AppCheck` request header is present, it's validated as a JWT against Firebase's public JWKS (`https://firebaseappcheck.googleapis.com/v1/jwks`), then checked against every entry configured below (`Api::Log` records why validation failed). If `FirebaseAppCheck` is left empty (the default), the check is skipped entirely — no enforcement.

The setting is an array of project configs, so it supports several Firebase projects/App IDs at once (e.g. several package-name variants of the same brand, or several brands sharing this backend) — add one array entry per project:

```json
"FirebaseAppCheck": [
    {
        "ProjectNumber": "<Firebase project number>",
        "AppIds": {
            "ios": "<iOS app's Firebase App ID>",
            "android": "<Android app's Firebase App ID>",
            "web": "<Web app's Firebase App ID>"
        }
    }
]
```

Where to find each value — they're identifiers, not secrets, and come from the same Firebase config files the mobile app ships with:

* **ProjectNumber** — Firebase Console → Project settings → General → "Project number". Also `project_info.project_number` in `google-services.json`, or `GCM_SENDER_ID` in `GoogleService-Info.plist`.
* **AppIds.android** — Firebase Console → Project settings → General → Your apps → the Android app card → "App ID" (format `1:<project_number>:android:<hash>`). Also `client[].client_info.mobilesdk_app_id` in `google-services.json` — there's one entry per registered Android package name, so pick the one matching the `packageName` actually shipped by that build.
* **AppIds.ios** — same page, the iOS app card → "App ID". Also `GOOGLE_APP_ID` in `GoogleService-Info.plist`.
* **AppIds.web** — same page, the Web app card → "App ID", if one is registered. Leave empty ("") when there's no web app to check.

Leave an `AppIds` field empty to skip matching on that platform for a given project entry.

Note: `ProjectNumber` and `AppIds` are not sensitive — they're baked into the public APK/IPA anyone can download, so treat them as regular config, not secrets.

# License
This module is licensed under AGPLv3 license if free version of the product is used or Afterlogic Software License if commercial version of the product was purchased.
