import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import 'notification_center.dart';

export 'notification_center.dart' show NotificationPermissionDenied;

/// Assinatura de tópicos FCM (substituível nos testes).
class TopicMessaging {
  const TopicMessaging();

  bool get available => Firebase.apps.isNotEmpty;

  Future<void> subscribe(String topic) => FirebaseMessaging.instance.subscribeToTopic(topic);

  Future<void> unsubscribe(String topic) => FirebaseMessaging.instance.unsubscribeFromTopic(topic);
}

final topicMessagingProvider = Provider<TopicMessaging>((ref) => const TopicMessaging());

/// "Me avise" dos canais ao vivo: assina o tópico FCM do canal (`sancta_live_<id>`, campo `topic`
/// da API /live-channels). O push chega no canal Android "Ao vivo" (`sancta_ao_vivo`).
/// Interface estável usada pela tela Ao vivo: `isOn(topic)` e `toggle(topic)`.
final liveAlertsProvider = NotifierProvider<LiveAlertsController, Set<String>>(LiveAlertsController.new);

class LiveAlertsController extends Notifier<Set<String>> {
  static const _key = 'sancta.live_alerts';
  static final _validTopic = RegExp(r'^sancta_live_\d{1,10}$');

  @override
  Set<String> build() => (ref.read(sharedPreferencesProvider).getStringList(_key) ?? const <String>[]).where(_validTopic.hasMatch).toSet();

  bool isOn(String topic) => state.contains(topic);

  /// Liga/desliga o aviso do canal. Retorna o novo estado.
  ///
  /// Ao ligar, pede a permissão de notificação (Android 13+) antes de assinar; se negada, lança
  /// [NotificationPermissionDenied] (com mensagem pronta para o usuário) e nada muda.
  /// Falha de rede ao (des)assinar propaga a exceção, também sem mudar o estado.
  Future<bool> toggle(String topic) async {
    if (!_validTopic.hasMatch(topic)) return false;
    final on = !state.contains(topic);

    if (on && !await ref.read(notificationCenterProvider).requestPermission()) {
      throw const NotificationPermissionDenied('Para avisar quando o canal entrar ao vivo, permita as notificações do Sancta nas configurações do aparelho.');
    }

    final messaging = ref.read(topicMessagingProvider);
    if (messaging.available) {
      on ? await messaging.subscribe(topic) : await messaging.unsubscribe(topic);
    }

    state = on ? {...state, topic} : ({...state}..remove(topic));
    await ref.read(sharedPreferencesProvider).setStringList(_key, state.toList());
    return on;
  }

  /// Assina de novo todos os tópicos salvos (o token FCM pode ter mudado: reinstalação,
  /// restauração, troca de conta). Chamado na abertura, na renovação do token e no login/logout.
  Future<void> resync() async {
    final messaging = ref.read(topicMessagingProvider);
    if (!messaging.available) return;
    for (final topic in state) {
      try {
        await messaging.subscribe(topic);
      } catch (e) {
        debugPrint('Falha ao reassinar o aviso ao vivo: $e');
      }
    }
  }
}
