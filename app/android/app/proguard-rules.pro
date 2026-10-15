# Regras R8 do Sancta
# Mantém classes usadas por reflexão nos plugins.
-keep class io.flutter.** { *; }
-keep class com.google.firebase.** { *; }
-keep class com.android.billingclient.** { *; }
# Lembretes agendados: o flutter_local_notifications grava os modelos em JSON (Gson) e os relê após
# reiniciar/atualizar o app — nomes de campos precisam ser estáveis entre versões (e o
# TimezoneChangedReceiver lê "scheduledDateTime"/"timeZoneName").
-keep class com.dexterous.flutterlocalnotifications.models.** { *; }
-dontwarn com.google.android.play.core.**
-dontwarn io.flutter.embedding.engine.deferredcomponents.**
