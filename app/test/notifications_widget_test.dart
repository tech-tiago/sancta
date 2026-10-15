import 'dart:typed_data';

import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:sancta/core/models/models.dart';
import 'package:sancta/core/providers.dart';
import 'package:sancta/core/utils/links.dart';
import 'package:sancta/features/notifications/live_alerts.dart';
import 'package:sancta/features/notifications/local_timezone.dart';
import 'package:sancta/features/notifications/notification_center.dart';
import 'package:sancta/features/notifications/notification_channels.dart';
import 'package:sancta/features/notifications/reminders.dart';
import 'package:sancta/features/notifications/trial_reminder.dart';
import 'package:sancta/features/widget/widget_content.dart';
import 'package:sancta/features/widget/widget_launch.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:timezone/timezone.dart' as tz;

/// Central de notificações falsa: registra agendamentos e simula a permissão do Android 13+.
class FakeCenter extends NotificationCenter {
  FakeCenter({this.granted = true});

  bool granted;
  int permissionRequests = 0;
  final scheduled = <int, ({tz.TZDateTime at, String title, String payload, bool daily})>{};

  @override
  bool get supported => true;

  @override
  Future<bool> areEnabled() async => granted;

  @override
  Future<bool> requestPermission() async {
    permissionRequests++;
    return granted;
  }

  @override
  Future<void> schedule({
    required int id,
    required tz.TZDateTime at,
    required String title,
    required String body,
    required String payload,
    bool daily = false,
  }) async => scheduled[id] = (at: at, title: title, payload: payload, daily: daily);

  @override
  Future<void> cancel(int id) async => scheduled.remove(id);
}

class FakeTopics extends TopicMessaging {
  final subscribed = <String>{};
  final calls = <String>[];

  @override
  bool get available => true;

  @override
  Future<void> subscribe(String topic) async {
    calls.add('+$topic');
    subscribed.add(topic);
  }

  @override
  Future<void> unsubscribe(String topic) async {
    calls.add('-$topic');
    subscribed.remove(topic);
  }
}

Future<ProviderContainer> makeContainer({FakeCenter? center, FakeTopics? topics, Map<String, Object> prefs = const {}}) async {
  SharedPreferences.setMockInitialValues(prefs);
  final shared = await SharedPreferences.getInstance();
  final container = ProviderContainer(
    overrides: [
      sharedPreferencesProvider.overrideWithValue(shared),
      notificationCenterProvider.overrideWithValue(center ?? FakeCenter()),
      topicMessagingProvider.overrideWithValue(topics ?? FakeTopics()),
    ],
  );
  addTearDown(container.dispose);
  return container;
}

AppUser userWith({String? status, DateTime? endsAt, Map<String, dynamic> prefs = const {}}) => AppUser.fromJson({
  'id': 'u1',
  'name': 'Ana',
  'email': 'ana@example.com',
  'notification_preferences': prefs,
  if (status != null) 'subscription': {'status': status, 'ends_at': endsAt?.toIso8601String()},
});

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() async {
    await initializeDateFormatting('pt_BR');
    LocalTimezone.use('America/Sao_Paulo');
  });

  group('Canais de notificação', () {
    test('três canais com IDs do contrato e nomes em pt-BR', () {
      expect(SanctaChannels.all.map((c) => c.id), ['sancta_avisos', 'sancta_ao_vivo', 'sancta_lembretes']);
      expect(SanctaChannels.all.map((c) => c.name), ['Avisos e novidades', 'Ao vivo', 'Lembretes de oração']);
      expect(SanctaChannels.all.every((c) => (c.description ?? '').isNotEmpty), isTrue);
      expect(SanctaChannels.aoVivo.importance, Importance.high);
    });

    test('canal enviado pelo servidor é respeitado; antigo/desconhecido vira Avisos', () {
      expect(SanctaChannels.resolve('sancta_ao_vivo').id, 'sancta_ao_vivo');
      expect(SanctaChannels.resolve('sancta_lembretes').id, 'sancta_lembretes');
      expect(SanctaChannels.resolve('sancta_default').id, 'sancta_avisos');
      expect(SanctaChannels.resolve(null).id, 'sancta_avisos');
      expect(SanctaChannels.legacy, contains('sancta_default'));
    });

    test('detalhes Android: ícone do Portal, Azul Manto e imagem grande', () {
      final plain = NotificationCenter.androidDetails(SanctaChannels.avisos, title: 'T', body: 'Corpo');
      expect(plain.icon, 'ic_notification');
      expect(plain.color, SanctaChannels.accent);
      expect(plain.styleInformation, isA<BigTextStyleInformation>());

      final withImage = NotificationCenter.androidDetails(SanctaChannels.aoVivo, title: 'T', body: 'B', picture: ByteArrayAndroidBitmap(Uint8List(4)));
      expect(withImage.styleInformation, isA<BigPictureStyleInformation>());
      expect(withImage.priority, Priority.high);
    });

    test('imagem de campanha: só HTTPS', () async {
      expect(await NotificationCenter.downloadImage('http://exemplo.com/a.jpg'), isNull);
      expect(await NotificationCenter.downloadImage('file:///etc/passwd'), isNull);
      expect(await NotificationCenter.downloadImage('nada'), isNull);
    });

    test('ID do push é estável e positivo', () {
      expect(NotificationCenter.notificationIdFor('abc'), NotificationCenter.notificationIdFor('abc'));
      expect(NotificationCenter.notificationIdFor('abc'), greaterThanOrEqualTo(0));
    });
  });

  group('Lembretes — regras', () {
    // Achado no emulador (fuso "GMT"): o lembrete das 07:00 era agendado em Brasília (10:00 no aparelho).
    test('fuso do aparelho: apelidos de UTC e fusos desconhecidos usam o deslocamento atual', () {
      addTearDown(() => LocalTimezone.use('America/Sao_Paulo'));
      expect(LocalTimezone.use('GMT', offset: Duration.zero), 'Etc/UTC');
      expect(LocalTimezone.use('UTC', offset: Duration.zero), 'Etc/UTC');
      expect(LocalTimezone.use('America/Manaus'), 'America/Manaus');
      expect(LocalTimezone.use('Fuso/Inventado', offset: const Duration(hours: -4)), 'Etc/GMT+4');
      expect(LocalTimezone.use('Fuso/Inventado', offset: const Duration(hours: 1)), 'Etc/GMT-1');
      expect(LocalTimezone.use('Fuso/Inventado', offset: const Duration(hours: 5, minutes: 30)), LocalTimezone.fallback);
      LocalTimezone.use('GMT', offset: Duration.zero);
      final next = ReminderSchedule.nextInstance(const ReminderTime(7, 0), tz.TZDateTime(tz.local, 2026, 10, 14, 18, 21));
      expect([next.hour, next.timeZoneOffset], [7, Duration.zero], reason: '07:00 no relógio do aparelho');
    });

    test('horário HH:mm', () {
      expect(ReminderTime.parse('07:05'), const ReminderTime(7, 5));
      expect(ReminderTime.parse('7:05'), isNull);
      expect(ReminderTime.parse('24:00'), isNull);
      expect(ReminderTime.parse(null), isNull);
      expect(const ReminderTime(21, 0).format(), '21:00');
    });

    test('próxima ocorrência: hoje se ainda não passou, senão amanhã', () {
      final loc = tz.getLocation('America/Sao_Paulo');
      final morning = tz.TZDateTime(loc, 2026, 10, 13, 6, 59);
      expect(ReminderSchedule.nextInstance(const ReminderTime(7, 0), morning), tz.TZDateTime(loc, 2026, 10, 13, 7));
      final exact = tz.TZDateTime(loc, 2026, 10, 13, 7);
      expect(ReminderSchedule.nextInstance(const ReminderTime(7, 0), exact), tz.TZDateTime(loc, 2026, 10, 14, 7));
      final lastDay = tz.TZDateTime(loc, 2026, 12, 31, 22);
      expect(ReminderSchedule.nextInstance(const ReminderTime(21, 0), lastDay), tz.TZDateTime(loc, 2027, 1, 1, 21));
    });

    test('mantém a hora local na virada do horário de verão', () {
      final ny = tz.getLocation('America/New_York');
      final beforeDst = tz.TZDateTime(ny, 2027, 3, 13, 22); // DST começa em 14/03/2027
      final next = ReminderSchedule.nextInstance(const ReminderTime(7, 0), beforeDst);
      expect([next.day, next.hour, next.minute], [14, 7, 0]);
    });

    test('textos acolhedores e toque abre Hoje (validado)', () {
      expect(ReminderSchedule.titleOf(ReminderSlot.manha), 'Hora da oração da manhã');
      expect(ReminderSchedule.titleOf(ReminderSlot.noite), 'Encerre o dia com Deus');
      for (final slot in ReminderSlot.values) {
        expect(deepLinkToRoute(ReminderSchedule.payloadOf(slot)), '/hoje');
      }
    });

    test('conta: manhã usa as chaves existentes; sem evening_reminder não é escolha do usuário', () {
      const settings = ReminderSettings(morningOn: true, morning: ReminderTime(6, 30), eveningOn: true, evening: ReminderTime(21, 15));
      expect(settings.toAccountPrefs(), {'daily_reminder': true, 'reminder_time': '06:30', 'evening_reminder': true, 'evening_reminder_time': '21:15'});
      expect(ReminderSettings.fromAccountPrefs(settings.toAccountPrefs()), settings);
      // Padrão do servidor (daily_reminder: true) não liga nada sozinho.
      expect(ReminderSettings.fromAccountPrefs({'daily_reminder': true, 'reminder_time': '07:00'}), isNull);
      expect(ReminderSettings.fromJson(settings.toJson()), settings);
    });

    test('mescla com as preferências da conta e descarta chaves desconhecidas', () {
      const settings = ReminderSettings(eveningOn: true);
      final merged = settings.mergeIntoAccount({'liturgy': false, 'news': true, 'churches': true, 'lixo': 1});
      expect(merged['liturgy'], false);
      expect(merged['news'], true);
      expect(merged['evening_reminder'], true);
      expect(merged.containsKey('lixo'), isFalse);
      expect(merged.keys.toSet().difference(ReminderSettings.allAccountKeys), isEmpty);
    });
  });

  group('Lembretes — controlador', () {
    test('ligar pede permissão; negada não muda nada', () async {
      final center = FakeCenter(granted: false);
      final container = await makeContainer(center: center);
      final result = await container.read(remindersProvider.notifier).change(ReminderSlot.manha, on: true);
      expect(result, ReminderChange.permissionDenied);
      expect(center.permissionRequests, 1);
      expect(container.read(remindersProvider).morningOn, isFalse);
      expect(center.scheduled, isEmpty);
    });

    test('ligar com permissão agenda diariamente; desligar cancela; tudo salvo no aparelho', () async {
      final center = FakeCenter();
      final container = await makeContainer(center: center);
      final reminders = container.read(remindersProvider.notifier);

      expect(await reminders.change(ReminderSlot.noite, on: true, time: const ReminderTime(21, 30)), ReminderChange.saved);
      final evening = center.scheduled[ReminderSchedule.eveningId]!;
      expect(evening.daily, isTrue);
      expect([evening.at.hour, evening.at.minute], [21, 30]);
      expect(evening.title, 'Encerre o dia com Deus');
      expect(center.scheduled.containsKey(ReminderSchedule.morningId), isFalse);

      // Mudar só o horário de um lembrete ligado não pede permissão de novo.
      await reminders.change(ReminderSlot.noite, time: const ReminderTime(20, 0));
      expect(center.permissionRequests, 1);
      expect(center.scheduled[ReminderSchedule.eveningId]!.at.hour, 20);

      await reminders.change(ReminderSlot.noite, on: false);
      expect(center.scheduled, isEmpty);

      final saved = container.read(sharedPreferencesProvider).getString('sancta.reminders');
      expect(saved, contains('"evening":"20:00"'));
    });

    test('login: adota os lembretes da conta só se o aparelho ainda não tem', () async {
      final center = FakeCenter();
      final container = await makeContainer(center: center);
      final reminders = container.read(remindersProvider.notifier);

      await reminders.onLogin(userWith(prefs: {'daily_reminder': true, 'reminder_time': '07:00'}));
      expect(container.read(remindersProvider).anyOn, isFalse, reason: 'padrão do servidor não é escolha');

      await reminders.onLogin(userWith(prefs: {'daily_reminder': true, 'reminder_time': '06:00', 'evening_reminder': false, 'evening_reminder_time': '21:00'}));
      expect(container.read(remindersProvider).morningOn, isTrue);
      expect(center.scheduled[ReminderSchedule.morningId]!.at.hour, 6);
    });
  });

  group('Aviso do fim do teste grátis (Dia N-2)', () {
    final now = DateTime.utc(2026, 10, 13, 12);

    test('agenda 2 dias antes do fim quando a assinatura está em teste', () {
      final endsAt = DateTime.utc(2026, 10, 20, 15);
      expect(TrialReminder.fireAtFor(userWith(status: 'trialing', endsAt: endsAt), now), DateTime.utc(2026, 10, 18, 15));
    });

    test('nada a avisar fora do teste, sem data ou em cima da hora', () {
      expect(TrialReminder.fireAtFor(null, now), isNull);
      expect(TrialReminder.fireAtFor(userWith(status: 'active', endsAt: DateTime.utc(2026, 11, 13)), now), isNull);
      expect(TrialReminder.fireAtFor(userWith(status: 'canceled', endsAt: DateTime.utc(2026, 10, 20)), now), isNull);
      expect(TrialReminder.fireAtFor(userWith(status: 'trialing'), now), isNull);
      expect(TrialReminder.fireAtFor(userWith(status: 'trialing', endsAt: DateTime.utc(2026, 10, 14)), now), isNull);
    });

    test('texto claro, com a data da cobrança', () {
      expect(TrialReminder.title(), 'Seu teste grátis termina em 2 dias');
      expect(TrialReminder.body(DateTime(2026, 10, 20, 15)), contains('20 de outubro'));
      expect(TrialReminder.body(DateTime(2026, 10, 20, 15)), contains('cancele antes, sem custo'));
    });

    test('sincroniza: agenda em teste e cancela quando deixa de estar', () async {
      final center = FakeCenter();
      final endsAt = DateTime.now().add(const Duration(days: 7));
      await TrialReminder.sync(center, userWith(status: 'trialing', endsAt: endsAt));
      expect(center.scheduled[TrialReminder.id]!.daily, isFalse);
      expect(deepLinkToRoute(center.scheduled[TrialReminder.id]!.payload), '/voce');

      await TrialReminder.sync(center, userWith(status: 'active', endsAt: endsAt));
      expect(center.scheduled.containsKey(TrialReminder.id), isFalse);
    });
  });

  group('Ao vivo — "Me avise"', () {
    test('pede permissão antes de assinar; negada lança mensagem e não assina', () async {
      final topics = FakeTopics();
      final container = await makeContainer(center: FakeCenter(granted: false), topics: topics);
      final alerts = container.read(liveAlertsProvider.notifier);

      await expectLater(alerts.toggle('sancta_live_3'), throwsA(isA<NotificationPermissionDenied>()));
      expect(topics.calls, isEmpty);
      expect(alerts.isOn('sancta_live_3'), isFalse);
    });

    test('liga/desliga, salva e ressincroniza; tópico inválido é ignorado', () async {
      final topics = FakeTopics();
      final container = await makeContainer(topics: topics);
      final alerts = container.read(liveAlertsProvider.notifier);

      expect(await alerts.toggle('outro_topico'), isFalse);
      expect(await alerts.toggle('sancta_live_3'), isTrue);
      expect(topics.subscribed, {'sancta_live_3'});
      expect(container.read(sharedPreferencesProvider).getStringList('sancta.live_alerts'), ['sancta_live_3']);

      topics.subscribed.clear();
      await alerts.resync();
      expect(topics.subscribed, {'sancta_live_3'});

      expect(await alerts.toggle('sancta_live_3'), isFalse);
      expect(topics.calls.last, '-sancta_live_3');
      expect(alerts.isOn('sancta_live_3'), isFalse);
    });
  });

  group('Widget "Oração do dia"', () {
    final home = <String, dynamic>{
      'greeting': {'title': 'Bom dia, Ana!'},
      'streak': 12,
      'liturgy': {'gospel_reference': 'Mt 21,33-43'},
      'featured_prayers': [
        {'slug': 'oracao-da-manha', 'title': 'Oração da manhã', 'duration_minutes': 8, 'locked': false},
        {'slug': 'exame-de-consciencia', 'title': 'Exame de consciência', 'duration_minutes': 10, 'locked': true},
        {'slug': 'oracao-da-noite', 'title': 'Oração da noite', 'duration_minutes': 2},
        {'slug': 'salve-rainha', 'title': 'Salve-Rainha', 'duration_minutes': null},
      ],
    };

    test('períodos: manhã 05h–11h59, tarde 12h–17h59, noite 18h–04h59', () {
      expect(WidgetPeriod.of(DateTime(2026, 10, 4, 4, 59)), WidgetPeriod.noite);
      expect(WidgetPeriod.of(DateTime(2026, 10, 4, 5)), WidgetPeriod.manha);
      expect(WidgetPeriod.of(DateTime(2026, 10, 4, 11, 59)), WidgetPeriod.manha);
      expect(WidgetPeriod.of(DateTime(2026, 10, 4, 12)), WidgetPeriod.tarde);
      expect(WidgetPeriod.of(DateTime(2026, 10, 4, 18)), WidgetPeriod.noite);
    });

    test('monta manhã/tarde/noite a partir de /home (sem dados pessoais)', () {
      final content = WidgetContent.fromHome(home, DateTime(2026, 10, 4, 9));
      expect(content.date, '2026-10-04');
      expect(content.dateLabel, 'Domingo, 4 de outubro');
      expect(content.entryFor(WidgetPeriod.manha), const WidgetEntry(title: 'Oração da manhã', subtitle: '8 min', link: 'sancta://oracao/oracao-da-manha'));
      expect(content.entryFor(WidgetPeriod.noite).link, 'sancta://oracao/oracao-da-noite');
      final afternoon = content.entryFor(WidgetPeriod.tarde);
      expect(afternoon, const WidgetEntry(title: 'Evangelho do dia', subtitle: 'Mt 21,33-43', link: 'sancta://liturgia', daily: true));

      final encoded = content.encode();
      expect(encoded, isNot(contains('Ana')));
      expect(encoded, isNot(contains('exame-de-consciencia')), reason: 'orações Sancta+ bloqueadas não entram');
    });

    test('usa a oração do período escolhida no servidor (period_prayers); bloqueada é ignorada', () {
      final content = WidgetContent.fromHome({
        ...home,
        'period_prayers': {
          'morning': {'slug': 'salve-rainha', 'title': 'Salve-Rainha', 'duration_minutes': 3, 'locked': false},
          'afternoon': {'slug': 'angelus', 'title': 'Angelus', 'duration_minutes': 4},
          'night': {'slug': 'completas', 'title': 'Completas', 'locked': true},
        },
      }, DateTime(2026, 10, 4, 9));
      expect(content.entryFor(WidgetPeriod.manha), const WidgetEntry(title: 'Salve-Rainha', subtitle: '3 min', link: 'sancta://oracao/salve-rainha'));
      expect(content.entryFor(WidgetPeriod.tarde), const WidgetEntry(title: 'Angelus', subtitle: '4 min', link: 'sancta://oracao/angelus'));
      // Noite Sancta+ (bloqueada): volta à heurística sobre os destaques.
      expect(content.entryFor(WidgetPeriod.noite).link, 'sancta://oracao/oracao-da-noite');
      expect(content.encode(), isNot(contains('completas')));
    });

    test('period_prayers nulo, ausente ou malformado mantém a heurística antiga', () {
      final before = WidgetContent.fromHome(home, DateTime(2026, 10, 4, 9)).periods;
      for (final value in [
        null,
        'texto',
        const {'morning': null, 'afternoon': null, 'night': null},
        const {
          'morning': {'slug': '../x', 'title': 'Oração'},
        },
      ]) {
        expect(WidgetContent.fromHome({...home, 'period_prayers': value}, DateTime(2026, 10, 4, 9)).periods, before, reason: '$value');
      }
    });

    test('sem a oração do período, usa a oração do dia (gratuita); sem nada, o padrão', () {
      final onlyOne = WidgetContent.fromHome({
        'featured_prayers': [
          {'slug': 'salve-rainha', 'title': 'Salve-Rainha'},
        ],
      }, DateTime(2026, 10, 4, 20));
      expect(onlyOne.entryFor(WidgetPeriod.noite), const WidgetEntry(title: 'Salve-Rainha', subtitle: 'Rezar agora', link: 'sancta://oracao/salve-rainha'));

      final empty = WidgetContent.fromHome(const {}, DateTime(2026, 10, 4, 20));
      for (final period in WidgetPeriod.values) {
        expect(empty.entryFor(period), WidgetEntry.fallback(period));
        expect(deepLinkToRoute(empty.entryFor(period).link), '/hoje');
      }
      expect(WidgetEntry.fallback(WidgetPeriod.manha).title, 'Oração da manhã');
      expect(WidgetEntry.fallback(WidgetPeriod.noite).title, 'Oração da noite');
    });

    test('slugs suspeitos são descartados e todo link gravado é um deep link válido', () {
      final content = WidgetContent.fromHome({
        'featured_prayers': [
          {'slug': '../admin', 'title': 'Oração da manhã'},
          {'slug': 'https://evil.example.com', 'title': 'Oração da noite'},
        ],
      }, DateTime(2026, 10, 4, 9));
      for (final period in WidgetPeriod.values) {
        expect(content.entryFor(period), WidgetEntry.fallback(period));
      }
      for (final entry in WidgetContent.fromHome(home, DateTime(2026, 10, 4, 9)).periods.values) {
        expect(deepLinkToRoute(entry.link), isNotNull);
      }
    });

    test('serialização ida e volta; link adulterado volta ao padrão', () {
      final content = WidgetContent.fromHome(home, DateTime(2026, 10, 4, 9));
      final decoded = WidgetContent.decode(content.encode())!;
      expect(decoded.periods, content.periods);
      expect(decoded.date, content.date);

      final tampered = content.encode().replaceAll('sancta://oracao/oracao-da-manha', 'https://evil.example.com');
      expect(WidgetContent.decode(tampered)!.entryFor(WidgetPeriod.manha), WidgetEntry.fallback(WidgetPeriod.manha));
      expect(WidgetContent.decode('{quebrado'), isNull);
      expect(WidgetContent.decode(null), isNull);
    });

    test('toque no widget: só rotas internas', () {
      expect(WidgetLaunch.routeFor(Uri.parse('sancta://oracao/oracao-da-manha')), '/oracao/oracao-da-manha');
      expect(WidgetLaunch.routeFor(Uri.parse('sancta://hoje')), '/hoje');
      expect(WidgetLaunch.routeFor(Uri.parse('https://evil.example.com')), isNull);
      expect(WidgetLaunch.routeFor(Uri()), isNull);
      expect(WidgetLaunch.routeFor(null), isNull);
    });
  });
}
