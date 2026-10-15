/// Configuração de ambiente injetada na compilação (nunca coloque segredos aqui:
/// tudo que vai no APK pode ser extraído).
///
/// Exemplo:
///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
///   flutter build appbundle --dart-define=API_BASE_URL=https://sancta.app.br
///
/// O client ID do Google e os IDs das assinaturas vêm do painel (configuração remota);
/// os valores abaixo são só reserva para quando o servidor não informar.
class Env {
  Env._();

  static const String apiBaseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'https://sancta.app.br');

  /// Reserva do client ID OAuth "Web" (o painel → Integrações e segurança tem prioridade).
  static const String googleServerClientId = String.fromEnvironment('GOOGLE_SERVER_CLIENT_ID');

  /// Reserva do ID da assinatura no Google Play (os planos do painel têm prioridade).
  static const String playSubscriptionId = String.fromEnvironment('PLAY_SUBSCRIPTION_ID', defaultValue: 'sancta_plus');

  static String get apiUrl => '$apiBaseUrl/api/v1';

  static bool get isSecure => apiBaseUrl.startsWith('https://');
}
