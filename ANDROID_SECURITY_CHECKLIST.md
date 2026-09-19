# Android Security Checklist — Next Release

> Generated from APK security audit (August 2026)
> These fixes must be applied in the **Kotlin/Java source project** and rebuilt via Android Studio.

---

## 🔴 P0 — Critical (Block Release)

### 1. Remove Plaintext Password Storage
- [ ] **Remove** `stored_password` key from `LoginActivity` SharedPreferences
- [ ] **Remove** any code in `LoginActivity` / `LoginActivity$1` that writes or reads `stored_password`
- [ ] If "remember me" is needed, store only the JWT token (encrypted) — never the password
- [ ] **Verify** no password value is logged via `Log.d()` / `Log.e()`

**Files to edit:** `LoginActivity.kt`, `LoginActivity$1.kt` (or anonymous inner class)

---

### 2. Encrypt JWT Token Storage
- [ ] Add `androidx.security:security-crypto:1.1.0-alpha06` dependency
- [ ] Replace `getSharedPreferences("YBTPackerPrefs", 0)` in `PrefsManager` with `EncryptedSharedPreferences`
- [ ] Use `MasterKey.KeyScheme.AES256_GCM` for the master key
- [ ] Ensure migration: on first launch after update, read from old plain prefs, write to encrypted prefs, then delete old key
- [ ] **Test:** Verify token survives app restart, and is NOT readable via `adb backup` or root

**Files to edit:** `PrefsManager.kt`, `build.gradle` (app)

```kotlin
// Example implementation
val masterKey = MasterKey.Builder(context)
    .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
    .build()

val encryptedPrefs = EncryptedSharedPreferences.create(
    context,
    "YBTPackerPrefs",
    masterKey,
    EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
    EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
)
```

---

## 🟠 P1 — High (Fix Before Widespread Rollout)

### 3. Add Certificate Pinning
- [ ] Obtain SHA-256 pins for your production SSL certificate and backup certificate
- [ ] Add `<pin-set>` to `res/xml/network_security_config.xml`

```xml
<pin-set expiration="2027-08-01">
    <!-- Primary certificate pin -->
    <pin digest="SHA-256">BASE64_PRIMARY_CERT_HASH=</pin>
    <!-- Backup/rotation pin -->
    <pin digest="SHA-256">BASE64_BACKUP_CERT_HASH=</pin>
</pin-set>
```

- [ ] Set a calendar reminder to rotate pins before expiration
- [ ] **Test:** Verify app works with production cert, and fails safely (clear error) with a proxy/MITM cert

---

### 4. Remove Debug Certificate Trust Overrides
- [ ] **Delete** the `<debug-overrides>` block from `res/xml/network_security_config.xml`:

```xml
<!-- REMOVE THIS ENTIRE BLOCK -->
<debug-overrides>
    <trust-anchors>
        <certificates src="user" />
        <certificates src="system" />
    </trust-anchors>
</debug-overrides>
```

- [ ] Alternatively, ensure release builds never have `android:debuggable="true"`
- [ ] Add a CI check: `aapt dump badging app-release.apk | grep debuggable` should return empty

---

### 5. Strengthen Client-Side Password Validation
- [ ] **Current:** Only validates "min 6 chars" (`UserDetailFragment.smali:818`)
- [ ] **New policy** (matching server):

```
- Minimum 8 characters
- At least 1 uppercase letter
- At least 1 lowercase letter
- At least 1 number
- At least 1 special character (!@#$%^&*(),.?":{}|<>)
```

- [ ] Add validation in `UserDetailFragment` before calling the password reset API
- [ ] Add validation in `RegisterActivity` (currently only checks length ≥ 6)
- [ ] Show real-time strength indicator in the password field
- [ ] **Test:** Attempt to set `abc123` as password — should be rejected

**Files to edit:** `UserDetailFragment.kt`, `RegisterActivity.kt`

---

## 🟡 P2 — Medium (Fix Within Sprint)

### 6. Remove Development Hints from Production
- [ ] **Remove** or `#if DEBUG` guard the HTTP URL hint text in `SettingsFragment`:

```
"Enter your WordPress server URL:\n\n• Android Emulator: http://10.0.2.2/wordpress/\n• Physical Device: http://192.168.1.x/wordpress/"
```

- [ ] Replace with production-only text:
```
"Enter your WordPress server URL (HTTPS required):"
```

**File to edit:** `SettingsFragment.kt`

---

### 7. Enforce HTTPS for Server URL Input
- [ ] In `SettingsFragment`, validate the entered server URL:
  - Must start with `https://`
  - Reject `http://` with user-visible error: "HTTPS is required for security"
- [ ] Optionally: maintain a whitelist of allowed server domains

**File to edit:** `SettingsFragment.kt`

---

### 8. Replace `commit()` with `apply()` in PrefsManager
- [ ] Change `saveToken()` to use `apply()` instead of `commit()`
- [ ] Change `saveUser()` to use `apply()` instead of `commit()`
- [ ] Change `handleAppUpdate()` to use `apply()` instead of `commit()`
- [ ] `commit()` blocks the UI thread and can cause ANR on slow storage

**File to edit:** `PrefsManager.kt`

---

### 9. Secure Crash Reports
- [ ] Ensure `CrashReportsActivity` does not persist crash data with tokens/passwords
- [ ] Strip sensitive fields (JWT, passwords, FCM tokens) from crash reports before sending
- [ ] Add a data retention policy: auto-delete crash reports after 30 days

**Files to edit:** `CrashReportsActivity.kt`, any crash reporting integration

---

## 🔵 P3 — Low (Backlog)

### 10. Remove Unused Ad Permissions
- [ ] Remove from `AndroidManifest.xml` if not actively using ads:
  - `com.google.android.gms.permission.AD_ID`
  - `android.permission.ACCESS_ADSERVICES_ATTRIBUTION`
  - `android.permission.ACCESS_ADSERVICES_AD_ID`
  - `com.google.android.finsky.permission.BIND_GET_INSTALL_REFERRER_SERVICE`
- [ ] Remove `com.google.android.measurement.AppMeasurementReceiver` and related services if Firebase Analytics is not needed

---

### 11. Disable Firebase Analytics Auto-Collection (GDPR)
- [ ] Add to `AndroidManifest.xml` inside `<application>`:
```xml
<meta-data android:name="firebase_analytics_collection_deactivated" android:value="true"/>
```
- [ ] Only enable analytics after obtaining user consent
- [ ] Implement a consent screen on first launch

---

### 12. Add ProGuard/R8 Rules for Security
- [ ] Ensure `EncryptedSharedPreferences` classes are not stripped by R8
- [ ] Add keep rules for security-critical classes:
```proguard
-keep class androidx.security.crypto.** { *; }
-keep class com.ybt.packerservice.utils.PrefsManager { *; }
```

---

## Verification Checklist

After applying all fixes, verify:

- [ ] **`adb backup`** does not extract JWT token or user credentials
- [ ] **Root test:** Token is not readable from `/data/data/com.ybt.packerservice/shared_prefs/`
- [ ] **MITM test:** App refuses connections when proxy presents invalid certificate
- [ ] **Password test:** Weak passwords rejected on login, register, and admin reset
- [ ] **HTTPS test:** Server URL field rejects `http://` URLs
- [ ] **APK analysis:** Run `apktool d app-release.apk` and verify no plaintext secrets in smali or resources
- [ ] **Release build:** Confirm `android:debuggable="false"` in final APK

---

## Timeline

| Priority | Target | Items |
|----------|--------|-------|
| 🔴 P0 | Before any release | #1, #2 |
| 🟠 P1 | Before widespread rollout | #3, #4, #5 |
| 🟡 P2 | Next sprint | #6, #7, #8, #9 |
| 🔵 P3 | Backlog | #10, #11, #12 |
