import java.io.FileInputStream
import java.util.Properties

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
    id("com.google.gms.google-services")
}

// Credenciais de assinatura ficam FORA do repositório (android/key.properties + keystore).
// Use a MESMA keystore do app atual (upload-keystore.jks) para atualizar o app na Play Store.
val keystoreProperties = Properties()
val keystorePropertiesFile = rootProject.file("key.properties")
if (keystorePropertiesFile.exists()) {
    keystoreProperties.load(FileInputStream(keystorePropertiesFile))
}

// Sem key.properties, o build de RELEASE falha (pentest fase 3 — SEC-18): um APK/AAB assinado com a chave
// de depuração não serve para a Play Store e não pode sair por engano. Só para testes locais, libere com
// SANCTA_ALLOW_DEBUG_SIGNING=true (variável de ambiente ou -PSANCTA_ALLOW_DEBUG_SIGNING=true). Ver docs/IMPLANTACAO.md.
val allowDebugSigning = (System.getenv("SANCTA_ALLOW_DEBUG_SIGNING") ?: findProperty("SANCTA_ALLOW_DEBUG_SIGNING")?.toString())
    .equals("true", ignoreCase = true)

android {
    namespace = "com.maria.sancta"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        // flutter_local_notifications usa java.time (agendamento por fuso) em Android < 8:
        // exige "core library desugaring" no app.
        isCoreLibraryDesugaringEnabled = true
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        // Mesmo ID do app já publicado ("Paróquia Nossa Sra. da Saúde"): o Sancta chega como ATUALIZAÇÃO.
        applicationId = "com.maria.app_igreja"
        // Android 7.0+ (flutter_local_notifications 22 exige minSdk 24). compileSdk/targetSdk = 36 (Flutter 3.41).
        minSdk = maxOf(flutter.minSdkVersion, 24)
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        create("release") {
            if (keystorePropertiesFile.exists()) {
                keyAlias = keystoreProperties["keyAlias"] as String
                keyPassword = keystoreProperties["keyPassword"] as String
                storeFile = file(keystoreProperties["storeFile"] as String)
                storePassword = keystoreProperties["storePassword"] as String
            }
        }
    }

    buildTypes {
        release {
            signingConfig = when {
                keystorePropertiesFile.exists() -> signingConfigs.getByName("release")
                allowDebugSigning -> signingConfigs.getByName("debug") // só teste local, liberado explicitamente
                else -> null // o build falha em preReleaseBuild (abaixo)
            }
            // R8: remove código não usado e ofusca classes (app menor e mais difícil de inspecionar).
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }
}

flutter {
    source = "../.."
}

// assembleRelease/bundleRelease (e flutter build apk/appbundle --release) sem a keystore oficial: falha com
// instrução clara, a menos que a assinatura de depuração tenha sido liberada de propósito.
val releaseHasKeystore = keystorePropertiesFile.exists()
val releaseAllowsDebugSigning = allowDebugSigning
tasks.matching { it.name.startsWith("pre") && it.name.endsWith("ReleaseBuild") }.configureEach {
    doFirst {
        if (!releaseHasKeystore && !releaseAllowsDebugSigning) {
            throw GradleException(
                "Build de release sem android/key.properties: configure a keystore oficial (docs/IMPLANTACAO.md). " +
                    "Somente para teste local, rode com SANCTA_ALLOW_DEBUG_SIGNING=true (assinatura de depuração, não publicável).",
            )
        }
    }
}

dependencies {
    // Versão exigida por flutter_local_notifications 22 (a mesma do plugin).
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
}
