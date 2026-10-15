import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import '../providers.dart';

/// Configuração remota (painel → Configurações). Usa a última cópia salva
/// enquanto busca a nova, para o app abrir rápido mesmo sem internet.
class ConfigController extends AsyncNotifier<AppConfig> {
  static const _prefsKey = 'sancta.config';

  @override
  Future<AppConfig> build() async {
    final prefs = ref.read(sharedPreferencesProvider);
    try {
      final data = await ref.read(apiClientProvider).get('/config');
      await prefs.setString(_prefsKey, jsonEncode(data));
      return AppConfig.fromJson(Map<String, dynamic>.from(data as Map));
    } catch (_) {
      final cached = prefs.getString(_prefsKey);
      if (cached != null) return AppConfig.fromJson(Map<String, dynamic>.from(jsonDecode(cached) as Map));
      return AppConfig.fallback();
    }
  }

  Future<void> refresh() async {
    state = await AsyncValue.guard(build);
  }
}

final configProvider = AsyncNotifierProvider<ConfigController, AppConfig>(ConfigController.new);

/// Acesso síncrono (usa o padrão enquanto carrega).
final configValueProvider = Provider<AppConfig>((ref) => ref.watch(configProvider).valueOrNull ?? AppConfig.fallback());
