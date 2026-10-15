import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../config/env.dart';
import '../storage/cache_store.dart';
import '../storage/secure_store.dart';
import 'api_exception.dart';

/// Cliente HTTP do app:
///  - HTTPS obrigatório em produção (o Android também bloqueia tráfego em texto claro);
///  - token Bearer lido do armazenamento seguro;
///  - em 401, limpa a sessão e avisa o app (onUnauthorized);
///  - cache opcional de GETs para funcionar offline.
class ApiClient {
  ApiClient({required this.secureStore, required this.cache, String? baseUrl, bool? requireHttps}) : requireHttps = requireHttps ?? kReleaseMode {
    _dio = Dio(
      BaseOptions(
        baseUrl: baseUrl ?? Env.apiUrl,
        connectTimeout: const Duration(seconds: 12),
        receiveTimeout: const Duration(seconds: 25),
        headers: {'Accept': 'application/json'},
        responseType: ResponseType.json,
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          // HTTPS obrigatório no build de produção. O network_security_config do Android NÃO vale para o
          // HttpClient do Dart (achado no QA: o APK de release falava HTTP com o servidor local), então a
          // regra fica aqui — antes de ler o token, para ele nunca sair sem TLS.
          if (this.requireHttps && options.uri.scheme != 'https') {
            return handler.reject(DioException(requestOptions: options, error: StateError('Conexão sem HTTPS bloqueada.')));
          }
          _token ??= await secureStore.readToken();
          if (_token != null) options.headers['Authorization'] = 'Bearer $_token';
          options.headers['X-App-Version'] = await _appVersion();
          handler.next(options);
        },
        onError: (error, handler) async {
          if (error.response?.statusCode == 401 && _token != null) {
            await clearSession();
            onUnauthorized?.call();
          }
          handler.next(error);
        },
      ),
    );

    if (kDebugMode) {
      _dio.interceptors.add(LogInterceptor(requestBody: false, responseBody: false, logPrint: (o) => debugPrint('$o')));
    }
  }

  final SecureStore secureStore;
  final CacheStore cache;

  /// Recusa URLs sem HTTPS (padrão: ligado no build de release).
  final bool requireHttps;
  late final Dio _dio;
  String? _token;
  String? _version;

  /// Chamado quando a sessão expira (token inválido/revogado).
  VoidCallback? onUnauthorized;

  bool get hasToken => _token != null;

  Future<String> _appVersion() async {
    if (_version != null) return _version!;
    try {
      final info = await PackageInfo.fromPlatform();
      _version = info.version;
    } catch (_) {
      _version = '0.0.0';
    }
    return _version!;
  }

  Future<void> loadToken() async => _token = await secureStore.readToken();

  Future<void> saveToken(String token) async {
    _token = token;
    await secureStore.writeToken(token);
  }

  Future<void> clearSession() async {
    _token = null;
    await secureStore.clear();
  }

  Future<dynamic> get(String path, {Map<String, dynamic>? query, String? cacheKey, Duration? cacheMaxAge}) async {
    try {
      final response = await _dio.get(path, queryParameters: query);
      if (cacheKey != null) unawaited(cache.put(cacheKey, response.data));
      return response.data;
    } on DioException catch (e) {
      // Sem internet: devolve a última versão salva, se houver.
      if (cacheKey != null && e.response == null) {
        final cached = await cache.get(cacheKey, maxAge: cacheMaxAge);
        if (cached != null) return cached;
      }
      throw ApiException.fromDio(e);
    }
  }

  Future<dynamic> post(String path, {Object? data}) => _send(() => _dio.post(path, data: data));

  Future<dynamic> put(String path, {Object? data}) => _send(() => _dio.put(path, data: data));

  Future<dynamic> patch(String path, {Object? data}) => _send(() => _dio.patch(path, data: data));

  Future<dynamic> delete(String path, {Object? data}) => _send(() => _dio.delete(path, data: data));

  Future<dynamic> _send(Future<Response<dynamic>> Function() request) async {
    try {
      return (await request()).data;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
