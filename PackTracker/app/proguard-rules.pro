# PackTracker ProGuard rules.
# Release builds currently disable minification; rules kept for when it is enabled.

# Retrofit
-keepattributes Signature, InnerClasses, EnclosingMethod, RuntimeVisibleAnnotations, RuntimeVisibleParameterAnnotations, AnnotationDefault
-keepclassmembers,allowshrinking,allowobfuscation interface * {
    @retrofit2.http.* <methods>;
}
-dontwarn okhttp3.**
-dontwarn okio.**
-dontwarn javax.annotation.**

# Gson models
-keep class com.packtracker.models.** { *; }
-keepattributes *Annotation*
