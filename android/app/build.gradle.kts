import java.util.Properties
import java.io.FileInputStream

plugins {
    id("com.android.application")
    // START: FlutterFire Configuration
    id("com.google.gms.google-services")
    // END: FlutterFire Configuration
    id("com.google.firebase.crashlytics")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

val keystoreProperties = Properties()
val keystorePropertiesFile = rootProject.file("key.properties")
if (keystorePropertiesFile.exists()) {
    keystoreProperties.load(FileInputStream(keystorePropertiesFile))
}

val localProperties = Properties()
val localPropertiesFile = rootProject.file("local.properties")
if (localPropertiesFile.exists()) {
    localPropertiesFile.inputStream().use { localProperties.load(it) }
}

fun googleMapsAndroidApiKey(): String {
    val fromLocal = localProperties.getProperty("GOOGLE_MAPS_ANDROID_API_KEY")?.trim().orEmpty()
    if (fromLocal.isNotEmpty()) {
        return fromLocal
    }
    val fromEnv = providers
        .gradleProperty("GOOGLE_MAPS_ANDROID_API_KEY")
        .orElse(providers.environmentVariable("GOOGLE_MAPS_ANDROID_API_KEY"))
        .orElse("")
        .get()
    if (fromEnv.isNotEmpty()) {
        return fromEnv
    }
    return "AIzaSyBkWQFINGC5nHupAKloY53boCqmir-6EB4"
}

val resolvedGoogleMapsAndroidApiKey = googleMapsAndroidApiKey()
if (resolvedGoogleMapsAndroidApiKey.isEmpty()) {
    logger.warn(
        "GOOGLE_MAPS_ANDROID_API_KEY is empty. Address map will fail. " +
            "Set it in android/local.properties.",
    )
}

android {
    namespace = "com.alwafaamed.storeapp"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        isCoreLibraryDesugaringEnabled = true
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        applicationId = "com.alwafaamed.storeapp"
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
        multiDexEnabled = true
        manifestPlaceholders["GOOGLE_MAPS_ANDROID_API_KEY"] =
            resolvedGoogleMapsAndroidApiKey
    }

    signingConfigs {
        create("release") {
            keyAlias = keystoreProperties.getProperty("keyAlias")
            keyPassword = keystoreProperties.getProperty("keyPassword")
            storeFile = keystoreProperties.getProperty("storeFile")?.let { file(it) }
            storePassword = keystoreProperties.getProperty("storePassword")
        }
    }

    buildTypes {
        release {
            // Fall back to debug signing when key.properties is absent (local/dev).
            signingConfig = if (keystorePropertiesFile.exists()) {
                signingConfigs.getByName("release")
            } else {
                signingConfigs.getByName("debug")
            }
            isMinifyEnabled = false
            isShrinkResources = false
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
}
