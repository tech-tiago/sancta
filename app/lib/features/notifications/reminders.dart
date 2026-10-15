import 'dart:async';
import 'dart:convert';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timezone/timezone.dart' as tz;

import '../../core/models/models.dart';
import '../../core/providers.dart';
import '../../core/state/session_controller.dart';
import 'local_timezone.dart';
import 'notification_center.dart';

/// Lembretes diários de oração (locais): manhã e noite, cada um com liga/desliga e horário.
/// Ficam no aparelho (funcionam sem internet e para visitantes) e, com conta, também em
/// `notification_preferences` (PATCH /me) — assim voltam ao entrar num aparelho novo.
enum ReminderSlot { manha, noite }

@immutable
class ReminderTime {
  const ReminderTime(this.hour, this.minute) : assert(hour >= 0 && hour < 24 && minute >= 0 && minute < 60);

  final int hour;
  final int minute;

  /// "HH:mm" (formato aceito pela API).
  String format() => '${hour.toString().padLeft(2, '0')}:${minute.toString().padLeft(2, '0')}';

  static ReminderTime? parse(Object? value) {
    final match = RegExp(r'^([01]\d|2[0-3]):([0-5]\d)$').firstMatch('${value ?? ''}');
    return match == null ? null : ReminderTime(int.parse(match.group(1)!), int.parse(match.group(2)!));
  }

  @override
  bool operator ==(Object other) => other is ReminderTime && other.hour == hour && other.minute == minute;

  @override
  int get hashCode => Object.hash(hour, minute);

  @override
  String toString() => format();
}

@immutable
class ReminderSettings {
  const ReminderSettings({this.morningOn = false, this.morning = defaultMorning, this.eveningOn = false, this.evening = defaultEvening});

  static const defaultMorning = ReminderTime(7, 0);
  static const defaultEvening = ReminderTime(21, 0);

  /// Chaves de `notification_preferences` que pertencem aos lembretes.
  static const accountKeys = {'daily_reminder', 'reminder_time', 'evening_reminder', 'evening_reminder_time'};

  /// Todas as chaves aceitas pelo servidor em `notification_preferences` (PATCH /me substitui o objeto inteiro).
  static const allAccountKeys = {...accountKeys, 'liturgy', 'news', 'churches'};

  final bool morningOn;
  final ReminderTime morning;
  final bool eveningOn;
  final ReminderTime evening;

  bool get anyOn => morningOn || eveningOn;

  bool isOn(ReminderSlot slot) => slot == ReminderSlot.manha ? morningOn : eveningOn;

  ReminderTime timeOf(ReminderSlot slot) => slot == ReminderSlot.manha ? morning : evening;

  ReminderSettings copyWithSlot(ReminderSlot slot, {bool? on, ReminderTime? time}) => slot == ReminderSlot.manha
      ? ReminderSettings(morningOn: on ?? morningOn, morning: time ?? morning, eveningOn: eveningOn, evening: evening)
      : ReminderSettings(morningOn: morningOn, morning: morning, eveningOn: on ?? eveningOn, evening: time ?? evening);

  Map<String, Object> toJson() => {'morning_on': morningOn, 'morning': morning.format(), 'evening_on': eveningOn, 'evening': evening.format()};

  factory ReminderSettings.fromJson(Map<String, dynamic> j) => ReminderSettings(
    morningOn: j['morning_on'] == true,
    morning: ReminderTime.parse(j['morning']) ?? defaultMorning,
    eveningOn: j['evening_on'] == true,
    evening: ReminderTime.parse(j['evening']) ?? defaultEvening,
  );

  /// Formato da conta: o lembrete da manhã usa as chaves já existentes (`daily_reminder`/`reminder_time`).
  Map<String, Object> toAccountPrefs() => {
    'daily_reminder': morningOn,
    'reminder_time': morning.format(),
    'evening_reminder': eveningOn,
    'evening_reminder_time': evening.format(),
  };

  /// Lê da conta. Retorna `null` quando a conta nunca salvou lembretes por esta tela: o servidor
  /// devolve `daily_reminder: true` como padrão, que não é uma escolha do usuário — por isso só
  /// conta como escolha quando há a chave `evening_reminder` (gravada sempre junto).
  static ReminderSettings? fromAccountPrefs(Map<String, dynamic> prefs) {
    if (!prefs.containsKey('evening_reminder')) return null;
    return ReminderSettings(
      morningOn: prefs['daily_reminder'] == true,
      morning: ReminderTime.parse(prefs['reminder_time']) ?? defaultMorning,
      eveningOn: prefs['evening_reminder'] == true,
      evening: ReminderTime.parse(prefs['evening_reminder_time']) ?? defaultEvening,
    );
  }

  /// Mescla com as preferências mais recentes da conta (o PATCH substitui o objeto inteiro)
  /// e descarta chaves que o servidor não aceita.
  Map<String, dynamic> mergeIntoAccount(Map<String, dynamic> latest) =>
      {...latest, ...toAccountPrefs()}..removeWhere((key, _) => !allAccountKeys.contains(key));

  @override
  bool operator ==(Object other) =>
      other is ReminderSettings && other.morningOn == morningOn && other.morning == morning && other.eveningOn == eveningOn && other.evening == evening;

  @override
  int get hashCode => Object.hash(morningOn, morning, eveningOn, evening);
}

/// Regras de agendamento (puras, testáveis).
abstract final class ReminderSchedule {
  static const morningId = 7101;
  static const eveningId = 7102;

  static int idOf(ReminderSlot slot) => slot == ReminderSlot.manha ? morningId : eveningId;

  /// Próxima ocorrência de [time] no fuso de [now] (hoje, se ainda não passou; senão amanhã).
  static tz.TZDateTime nextInstance(ReminderTime time, tz.TZDateTime now) {
    var at = tz.TZDateTime(now.location, now.year, now.month, now.day, time.hour, time.minute);
    if (!at.isAfter(now)) at = tz.TZDateTime(now.location, now.year, now.month, now.day + 1, time.hour, time.minute);
    return at;
  }

  static String titleOf(ReminderSlot slot) => slot == ReminderSlot.manha ? 'Hora da oração da manhã' : 'Encerre o dia com Deus';

  static String bodyOf(ReminderSlot slot) =>
      slot == ReminderSlot.manha ? 'Comece o dia entregando tudo a Deus. Alguns minutos já bastam.' : 'Um momento de oração e gratidão antes de descansar.';

  /// Toque abre Hoje; o app troca pela oração do período quando a conhece (ver PushService).
  static String payloadOf(ReminderSlot slot) => 'sancta://hoje?lembrete=${slot.name}';
}

/// Resultado de uma mudança feita pelo usuário.
enum ReminderChange { saved, permissionDenied }

class ReminderController extends Notifier<ReminderSettings> {
  static const _key = 'sancta.reminders';
  static const _tzKey = 'sancta.reminders.tz';
  static const _pendingSyncKey = 'sancta.reminders.pending_sync';

  AppLifecycleListener? _lifecycle;

  @override
  ReminderSettings build() {
    ref.onDispose(() => _lifecycle?.dispose());
    final raw = ref.read(sharedPreferencesProvider).getString(_key);
    if (raw == null) return const ReminderSettings();
    try {
      return ReminderSettings.fromJson(Map<String, dynamic>.from(jsonDecode(raw) as Map));
    } catch (_) {
      return const ReminderSettings();
    }
  }

  NotificationCenter get _center => ref.read(notificationCenterProvider);

  /// O usuário já configurou lembretes neste aparelho?
  bool get configuredHere => ref.read(sharedPreferencesProvider).containsKey(_key);

  /// Liga/desliga e/ou muda o horário. Ao ligar, pede a permissão de notificação (Android 13+).
  Future<ReminderChange> change(ReminderSlot slot, {bool? on, ReminderTime? time}) async {
    if (on == true && !state.isOn(slot) && !await _center.requestPermission()) return ReminderChange.permissionDenied;

    final next = state.copyWithSlot(slot, on: on, time: time);
    await _save(next);
    await reschedule();
    unawaited(_pushToAccount());
    return ReminderChange.saved;
  }

  /// Cancela e agenda de novo conforme as preferências e o fuso atual.
  Future<void> reschedule() async {
    if (!_center.supported) return;
    try {
      final zone = await LocalTimezone.refresh();
      final now = tz.TZDateTime.now(tz.local);
      for (final slot in ReminderSlot.values) {
        final id = ReminderSchedule.idOf(slot);
        await _center.cancel(id);
        if (!state.isOn(slot)) continue;
        await _center.schedule(
          id: id,
          at: ReminderSchedule.nextInstance(state.timeOf(slot), now),
          title: ReminderSchedule.titleOf(slot),
          body: ReminderSchedule.bodyOf(slot),
          payload: ReminderSchedule.payloadOf(slot),
          daily: true,
        );
      }
      await ref.read(sharedPreferencesProvider).setString(_tzKey, zone);
    } catch (e) {
      debugPrint('Falha ao agendar lembretes: $e');
    }
  }

  /// Abertura do app: reagenda (cobre atualização do app e troca de fuso) e passa a
  /// conferir o fuso sempre que o app volta ao primeiro plano.
  Future<void> onAppStart() async {
    _lifecycle ??= AppLifecycleListener(onResume: () => unawaited(_checkTimezone()));
    if (state.anyOn) await reschedule();
    if (ref.read(sharedPreferencesProvider).getBool(_pendingSyncKey) ?? false) unawaited(_pushToAccount());
  }

  Future<void> _checkTimezone() async {
    if (!state.anyOn) return;
    final saved = ref.read(sharedPreferencesProvider).getString(_tzKey);
    final zone = await LocalTimezone.refresh();
    if (saved != zone) await reschedule();
  }

  /// Ao entrar numa conta: se este aparelho ainda não tem lembretes, adota os da conta
  /// (agenda só se as notificações já estiverem liberadas — sem pedir permissão fora de contexto;
  /// a tela de Ajustes mostra o aviso para liberar). Se já tem, envia os daqui para a conta.
  Future<void> onLogin(AppUser user) async {
    if (!configuredHere) {
      final fromAccount = ReminderSettings.fromAccountPrefs(user.notificationPreferences);
      if (fromAccount == null) return;
      await _save(fromAccount);
      await reschedule();
      return;
    }
    if (ReminderSettings.fromAccountPrefs(user.notificationPreferences) != state) await _pushToAccount();
  }

  Future<void> _save(ReminderSettings next) async {
    state = next;
    await ref.read(sharedPreferencesProvider).setString(_key, jsonEncode(next.toJson()));
  }

  /// Grava na conta (só autenticado). Falhas ficam pendentes e são reenviadas na próxima abertura.
  Future<void> _pushToAccount() async {
    final prefs = ref.read(sharedPreferencesProvider);
    final user = ref.read(sessionProvider).user;
    if (user == null) {
      await prefs.remove(_pendingSyncKey);
      return;
    }
    try {
      final data = await ref.read(apiClientProvider).patch('/me', data: {'notification_preferences': state.mergeIntoAccount(user.notificationPreferences)});
      await prefs.remove(_pendingSyncKey);
      if (data is Map && data['id'] != null) ref.read(sessionProvider.notifier).updateUser(AppUser.fromJson(Map<String, dynamic>.from(data)));
    } catch (e) {
      await prefs.setBool(_pendingSyncKey, true);
      debugPrint('Lembretes salvos só no aparelho (sincronização pendente).');
    }
  }
}

final remindersProvider = NotifierProvider<ReminderController, ReminderSettings>(ReminderController.new);
