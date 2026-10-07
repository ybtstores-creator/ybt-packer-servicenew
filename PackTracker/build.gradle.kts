// Top-level build file. Plugin versions are managed here and applied in :app.
plugins {
    id("com.android.application") version "8.5.2" apply false
    id("org.jetbrains.kotlin.android") version "1.9.24" apply false
    // FCM: google-services plugin version. Only applied in :app when
    // google-services.json exists (conditional in app/build.gradle.kts).
    id("com.google.gms.google-services") version "4.4.2" apply false
}
