import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../config/env.dart';
import '../models/models.dart';
import '../network/api_exception.dart';
import '../providers.dart';
import 'config_controller.dart';

/// Estado da sessão: carregando → visitante/desconectado → autenticado.
@immutable
class SessionState {
  const SessionState({this.user, this.guest = false, this.initialized = false});

  final AppUser? user;
  final bool guest;
  final bool initialized;

  bool get isAuthenticated => user != null;
  bool get isPremium => user?.isPremium ?? false;
  bool get canEnterApp => isAuthenticated || guest;
}

class SessionController extends Notifier<SessionState> {
  static const _guestKey = 'sancta.guest';
  bool _googleReady = false;

  @override
  SessionState build() => const SessionState();

  /// Executado na abertura do app.
  Future<void> bootstrap() async {
    final api = ref.read(apiClientProvider);
    final prefs = ref.read(sharedPreferencesProvider);
    api.onUnauthorized = () => state = SessionState(initialized: true, guest: prefs.getBool(_guestKey) ?? false);

    await api.loadToken();
    AppUser? user;
    if (api.hasToken) {
      try {
        user = AppUser.fromJson(Map<String, dynamic>.from(await api.get('/me') as Map));
      } on ApiException catch (e) {
        if (e.isUnauthorized) await api.clearSession();
      }
    }

    state = SessionState(user: user, guest: user == null && (prefs.getBool(_guestKey) ?? false), initialized: true);
  }

  Future<void> continueAsGuest() async {
    await ref.read(sharedPreferencesProvider).setBool(_guestKey, true);
    state = const SessionState(guest: true, initialized: true);
  }

  Future<void> loginWithEmail(String email, String password) async {
    final data = await ref.read(apiClientProvider).post('/auth/login', data: {'email': email.trim(), 'password': password, 'device_name': _deviceName()});
    await _persist(data);
  }

  Future<void> register({required String name, required String email, required String password, required bool marketing}) async {
    final data = await ref
        .read(apiClientProvider)
        .post(
          '/auth/register',
          data: {
            'name': name.trim(),
            'email': email.trim(),
            'password': password,
            'device_name': _deviceName(),
            'accept_terms': true,
            'marketing_opt_in': marketing,
          },
        );
    await _persist(data);
  }

  /// Login com Google: o app obtém o id_token e o SERVIDOR valida assinatura,
  /// audiência e e-mail verificado antes de emitir o token do Sancta.
  Future<bool> loginWithGoogle() async {
    final google = GoogleSignIn.instance;
    if (!_googleReady) {
      // Client ID definido no painel (Integrações e segurança); sem ele, o do build (--dart-define).
      final clientId = ref.read(configValueProvider).googleServerClientId ?? (Env.googleServerClientId.isEmpty ? null : Env.googleServerClientId);
      await google.initialize(serverClientId: clientId);
      _googleReady = true;
    }

    try {
      final account = await google.authenticate();
      final idToken = account.authentication.idToken;
      if (idToken == null) throw ApiException('Não foi possível obter a autorização do Google.');

      final data = await ref.read(apiClientProvider).post('/auth/google', data: {'id_token': idToken, 'device_name': _deviceName()});
      await _persist(data);
      return true;
    } on GoogleSignInException catch (e) {
      if (e.code == GoogleSignInExceptionCode.canceled) return false;
      throw ApiException('Não foi possível entrar com o Google agora.');
    }
  }

  Future<void> forgotPassword(String email) => ref.read(apiClientProvider).post('/auth/forgot-password', data: {'email': email.trim()});

  Future<void> refreshUser() async {
    if (!state.isAuthenticated) return;
    try {
      final data = await ref.read(apiClientProvider).get('/me');
      state = SessionState(user: AppUser.fromJson(Map<String, dynamic>.from(data as Map)), initialized: true);
    } catch (_) {}
  }

  void updateUser(AppUser user) => state = SessionState(user: user, initialized: true);

  Future<void> logout() async {
    final api = ref.read(apiClientProvider);
    try {
      await api.post('/auth/logout');
    } catch (_) {}
    await api.clearSession();
    await ref.read(cacheStoreProvider).clear();
    try {
      await GoogleSignIn.instance.signOut();
    } catch (_) {}
    await ref.read(sharedPreferencesProvider).setBool(_guestKey, false);
    state = const SessionState(initialized: true);
  }

  Future<void> deleteAccount({String? password}) async {
    await ref.read(apiClientProvider).delete('/me', data: {'confirmation': 'EXCLUIR', 'password': password});
    await ref.read(apiClientProvider).clearSession();
    await ref.read(cacheStoreProvider).clear();
    state = const SessionState(initialized: true);
  }

  Future<void> _persist(dynamic data) async {
    final map = Map<String, dynamic>.from(data as Map);
    await ref.read(apiClientProvider).saveToken('${map['token']}');
    await ref.read(sharedPreferencesProvider).setBool(_guestKey, false);
    state = SessionState(user: AppUser.fromJson(Map<String, dynamic>.from(map['user'] as Map)), initialized: true);
  }

  String _deviceName() => switch (defaultTargetPlatform) {
    TargetPlatform.android => 'Android',
    TargetPlatform.iOS => 'iPhone',
    _ => 'App',
  };
}

final sessionProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);

final isPremiumProvider = Provider<bool>((ref) => ref.watch(sessionProvider).isPremium);
