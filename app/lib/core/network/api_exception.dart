import 'package:dio/dio.dart';

/// Erro amigável vindo da API (mensagens já em português).
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.fieldErrors = const {}, this.paywall = false});

  final String message;
  final int? statusCode;
  final Map<String, String> fieldErrors;

  /// Conteúdo exige Sancta+ (HTTP 402).
  final bool paywall;

  bool get isUnauthorized => statusCode == 401;
  bool get isOffline => statusCode == null;

  String? field(String name) => fieldErrors[name];

  factory ApiException.fromDio(DioException e) {
    final response = e.response;
    if (response == null) {
      return ApiException('Sem conexão. Verifique sua internet e tente novamente.');
    }

    final data = response.data;
    var message = 'Algo deu errado. Tente novamente.';
    final fields = <String, String>{};
    var paywall = false;

    if (data is Map) {
      if (data['message'] is String && (data['message'] as String).isNotEmpty) message = data['message'] as String;
      paywall = data['paywall'] == true;
      final errors = data['errors'];
      if (errors is Map) {
        errors.forEach((key, value) {
          if (value is List && value.isNotEmpty) fields['$key'] = '${value.first}';
        });
        if (fields.isNotEmpty) message = fields.values.first;
      }
    }

    switch (response.statusCode) {
      case 429:
        if (fields.isEmpty) message = 'Muitas tentativas. Aguarde um pouco e tente de novo.';
      case 500:
      case 502:
      case 503:
        message = 'Nossos servidores estão instáveis. Tente em instantes.';
    }

    return ApiException(message, statusCode: response.statusCode, fieldErrors: fields, paywall: paywall || response.statusCode == 402);
  }

  @override
  String toString() => message;
}
