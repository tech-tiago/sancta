import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../core/providers.dart';
import '../../core/state/session_controller.dart';
import '../../core/utils/links.dart';
import '../widget/home_widget_sync.dart';
import '../widget/widget_launch.dart';
import 'live_alerts.dart';
import 'notification_center.dart';
import 'reminders.dart';
import 'trial_reminder.dart';

/// Central de notificações do app (chamada uma vez pela `AppShell`):
///  - push (Firebase Cloud Messaging): permissão, token do aparelho na conta, tópicos por público,
///    exibição em primeiro plano (o FCM não mostra nada com o app aberto) e toque → deep link;
///  - notificações locais: canais, lembretes de oração, aviso do fim do teste grátis;
///  - toques no widget "Oração do dia";
///  - login/logout: tópicos, "Me avise" ao vivo, lembretes da conta e aviso do teste.
/// Todo deep link é validado por [deepLinkToRoute] (só rotas internas permitidas).
class PushService {
  PushService(this.ref);

  final Ref ref;
  bool _initialized = false;
  final _subs = <StreamSubscription<Object?>>[];

  bool get _available => Firebase.apps.isNotEmpty;

  NotificationCenter get _center => ref.read(notificationCenterProvider);

  Future<void> initialize(BuildContext context) async {
    if (_initialized) return;
    _initialized = true;

    // Captura a navegação antes de qualquer espera assíncrona.
    final router = GoRouter.of(context);

    // Reage a login/logout/assinatura.
    ref.listen<SessionState>(sessionProvider, (prev, next) => unawaited(_onSessionChanged(prev, next)));

    // Notificações locais: canais + toques (app aberto, em segundo plano ou fechado).
    await _center.ensureInitialized();
    _subs.add(_center.taps.listen((payload) => unawaited(_openPayload(router, payload))));
    final launchPayload = await _center.launchPayload();
    if (launchPayload != null) await _openPayload(router, launchPayload);

    // Widget "Oração do dia".
    await WidgetLaunch.attach((route) => _navigate(router, route));

    // Lembretes (reagenda: cobre atualização do app e troca de fuso) e aviso do teste grátis.
    final reminders = ref.read(remindersProvider.notifier);
    await reminders.onAppStart();
    final user = ref.read(sessionProvider).user;
    if (user != null) await reminders.onLogin(user);
    await TrialReminder.sync(_center, user);

    if (_available) await _initializeFcm(router);
  }

  Future<void> _initializeFcm(GoRouter router) async {
    final messaging = FirebaseMessaging.instance;
    final prefs = ref.read(sharedPreferencesProvider);

    try {
      if (!(prefs.getBool('sancta.push_asked') ?? false)) {
        await prefs.setBool('sancta.push_asked', true);
        await messaging.requestPermission(alert: true, badge: true, sound: true);
      }

      // Toque na notificação do sistema (app em segundo plano / fechado).
      _subs.add(FirebaseMessaging.onMessageOpenedApp.listen((m) => _openRemote(router, m)));
      // App aberto: exibe como notificação local, no canal enviado pelo servidor.
      _subs.add(FirebaseMessaging.onMessage.listen((m) => unawaited(_center.showRemote(m))));
      final initial = await messaging.getInitialMessage();
      if (initial != null) _openRemote(router, initial);

      _subs.add(
        messaging.onTokenRefresh.listen((token) async {
          await _registerToken(token);
          await syncTopics();
          await ref.read(liveAlertsProvider.notifier).resync();
        }),
      );

      await syncTopics();
      await ref.read(liveAlertsProvider.notifier).resync();
      await _registerToken(await messaging.getToken());
    } catch (e) {
      debugPrint('Push indisponível agora: $e');
    }
  }

  Future<void> _onSessionChanged(SessionState? prev, SessionState next) async {
    final authChanged = prev?.isAuthenticated != next.isAuthenticated;

    if (_available && (authChanged || prev?.isPremium != next.isPremium)) {
      await syncTopics();
      if (next.isAuthenticated) {
        try {
          await _registerToken(await FirebaseMessaging.instance.getToken());
        } catch (_) {}
      }
    }
    if (authChanged) await ref.read(liveAlertsProvider.notifier).resync();

    final user = next.user;
    if (prev?.user == null && user != null) await ref.read(remindersProvider.notifier).onLogin(user);

    final before = prev?.user?.subscription;
    final after = user?.subscription;
    if (authChanged || before?.status != after?.status || before?.endsAt != after?.endsAt) await TrialReminder.sync(_center, user);
  }

  Future<void> syncTopics() async {
    if (!_available) return;
    final messaging = FirebaseMessaging.instance;
    final premium = ref.read(sessionProvider).isPremium;
    try {
      await messaging.subscribeToTopic('sancta_all');
      await messaging.subscribeToTopic(premium ? 'sancta_premium' : 'sancta_free');
      await messaging.unsubscribeFromTopic(premium ? 'sancta_free' : 'sancta_premium');
    } catch (e) {
      debugPrint('Falha ao atualizar tópicos: $e');
    }
  }

  Future<void> _registerToken(String? token) async {
    if (token == null || !ref.read(sessionProvider).isAuthenticated) return;
    try {
      final info = await PackageInfo.fromPlatform();
      await ref
          .read(apiClientProvider)
          .post(
            '/me/devices',
            data: {'fcm_token': token, 'platform': defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android', 'app_version': info.version},
          );
    } catch (_) {}
  }

  Future<void> unregister() async {
    if (!_available) return;
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await ref.read(apiClientProvider).delete('/me/devices', data: {'fcm_token': token});
    } catch (_) {}
  }

  void _openRemote(GoRouter router, RemoteMessage message) {
    final link = message.data['deep_link'];
    final route = deepLinkToRoute(link is String ? link : null);
    if (route != null) _navigate(router, route);
  }

  /// Toque numa notificação local. Lembretes (`?lembrete=manha|noite`) abrem a oração do
  /// período quando o app já a conhece (última sincronização de Hoje); senão, a tela Hoje.
  Future<void> _openPayload(GoRouter router, String payload) async {
    final slot = Uri.tryParse(payload)?.queryParameters['lembrete'];
    if (slot != null) {
      final prayer = await HomeWidgetSync.routeFor(slot == ReminderSlot.noite.name ? WidgetPeriod.noite : WidgetPeriod.manha);
      if (prayer != null && prayer.startsWith('/oracao/')) {
        router.go('/hoje');
        router.push(prayer);
        return;
      }
    }
    final route = deepLinkToRoute(payload);
    if (route != null) _navigate(router, route);
  }

  /// Abas (Hoje, Orar, Bíblia, Igrejas, Você) trocam de aba; as demais telas entram por cima.
  static const _tabs = {'/hoje', '/orar', '/biblia', '/igrejas', '/voce'};

  void _navigate(GoRouter router, String route) => _tabs.contains(route) ? router.go(route) : router.push(route);

  void dispose() {
    for (final sub in _subs) {
      sub.cancel();
    }
  }
}

final pushServiceProvider = Provider<PushService>((ref) {
  final service = PushService(ref);
  ref.onDispose(service.dispose);
  return service;
});
