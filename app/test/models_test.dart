import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:sancta/core/models/models.dart';
import 'package:sancta/core/theme/app_theme.dart';
import 'package:sancta/core/utils/clock.dart';
import 'package:sancta/features/churches/churches_screen.dart';
import 'package:sancta/features/live/live_screen.dart';
import 'package:sancta/features/premium/paywall_screen.dart';

import 'goldens/fixtures.dart';

void main() {
  setUpAll(() => initializeDateFormatting('pt_BR'));

  group('Ao vivo — LiveChannel', () {
    test('lê o contrato novo (/live-channels)', () {
      final c = LiveChannel.fromJson(liveJson.first);
      expect(c.isLive, isTrue);
      expect(c.category, 'missa');
      expect(c.categoryLabel, 'Missa');
      expect(c.live?.title, startsWith('Santa Missa'));
      expect(c.live?.startedAt, DateTime.parse('2026-10-04T08:00:00-03:00'));
      expect(c.onScheduleNow, isTrue);
      expect(c.schedule, hasLength(2));
      expect(c.schedule.first.weekday, isNull);
      expect(c.schedule.last.weekday, 0);
      expect(c.nextAt, DateTime.parse('2026-10-04T18:00:00-03:00'));
      expect(c.topic, 'sancta_live_3');
      expect(c.openUrl, 'https://www.youtube.com/watch?v=LIVEvideo01');
      expect(c.isOnAir, isTrue);
      expect(c.nowTitle, startsWith('Santa Missa'));
    });

    test('tolerante a servidores antigos e campos ausentes', () {
      final old = LiveChannel.fromJson(const {
        'id': 9,
        'name': 'Canal antigo',
        'type': 'channel',
        'url': 'https://youtube.com/@x',
        'thumbnail_url': 'https://i.ytimg.com/x.jpg',
      });
      expect(old.isLive, isFalse);
      expect(old.live, isNull);
      expect(old.upcoming, isNull);
      expect(old.schedule, isEmpty);
      expect(old.nextAt, isNull);
      expect(old.topic, isNull);
      expect(old.category, 'canal');
      expect(old.categoryLabel, 'Canal');
      expect(old.openUrl, 'https://youtube.com/@x');
      expect(old.imageUrl, 'https://i.ytimg.com/x.jpg');

      final empty = LiveChannel.fromJson(const {});
      expect(empty.name, '');
      expect(empty.openUrl, isNull);
    });

    test('rótulo da categoria vem do app quando o servidor não envia', () {
      expect(LiveChannel.fromJson(const {'category': 'terco'}).categoryLabel, 'Terço');
      expect(LiveChannel.fromJson(const {'category': 'adoracao'}).categoryLabel, 'Adoração');
    });

    test('agenda: dia da semana e horário', () {
      final daily = LiveScheduleItem.fromJson(const {'weekday': null, 'time': '07:30', 'duration_minutes': 45});
      final sunday = LiveScheduleItem.fromJson(const {'weekday': 0, 'time': '18:00'});
      final domingo = DateTime(2026, 10, 4); // domingo
      final segunda = DateTime(2026, 10, 5);
      expect(daily.minutes, 7 * 60 + 30);
      expect(daily.occursOn(segunda), isTrue);
      expect(sunday.occursOn(domingo), isTrue);
      expect(sunday.occursOn(segunda), isFalse);
      expect(LiveScheduleItem.fromJson(const {'time': 'xx'}).minutes, 24 * 60);
    });

    test('situação do item da agenda (agora, encerrada, próxima)', () {
      final missa = LiveScheduleItem.fromJson(const {'time': '08:00', 'duration_minutes': 60});
      expect(scheduleStatus(missa, DateTime(2026, 10, 4, 8, 30)), ScheduleStatus.now);
      expect(scheduleStatus(missa, DateTime(2026, 10, 4, 9, 0)), ScheduleStatus.ended);
      expect(scheduleStatus(missa, DateTime(2026, 10, 4, 7, 59)), ScheduleStatus.upcoming);
      // sem duração: considera 60 min
      expect(scheduleStatus(LiveScheduleItem.fromJson(const {'time': '08:00'}), DateTime(2026, 10, 4, 8, 59)), ScheduleStatus.now);
    });

    // Achado no emulador (fuso GMT): a agenda é em horário de Brasília; o aparelho em outro fuso marcava
    // "AGORA" e "encerrada" nos horários errados, diferente do "Ao vivo agora" calculado pelo servidor.
    test('agenda comparada no relógio de Brasília, qualquer que seja o fuso do aparelho', () {
      final terco = LiveScheduleItem.fromJson(const {'time': '15:00', 'duration_minutes': 30});
      final utc = DateTime.utc(2026, 10, 14, 18, 16); // 15:16 em Brasília
      final br = brasiliaWallClock(utc);
      expect([br.year, br.month, br.day, br.hour, br.minute], [2026, 10, 14, 15, 16]);
      expect(scheduleStatus(terco, br), ScheduleStatus.now);
      expect(scheduleStatus(terco, utc), ScheduleStatus.ended, reason: 'sem converter, o horário sairia errado');
      // Virada do dia: 01:00 UTC ainda é o dia anterior em Brasília (dia da semana da agenda).
      expect(brasiliaWallClock(DateTime.utc(2026, 10, 5, 1)).weekday, DateTime.sunday);
    });

    test('próximo horário em português', () {
      final now = DateTime(2026, 10, 4, 8, 30);
      expect(formatNextAt(DateTime(2026, 10, 4, 18), now), 'hoje às 18:00');
      expect(formatNextAt(DateTime(2026, 10, 5, 7), now), 'amanhã às 07:00');
      expect(formatNextAt(DateTime(2026, 10, 9, 7), now), 'sexta às 07:00');
      expect(formatNextAt(DateTime(2026, 10, 20, 7), now), '20/10 às 07:00');
    });

    test('live_now da tela Hoje', () {
      final list = LiveNow.listFrom(homeJson()['live_now']);
      expect(list, hasLength(1));
      expect(list.first.isLive, isTrue);
      expect(list.first.title, 'Santa Missa');
      expect(list.first.watchUrl, contains('youtube.com'));
      expect(list.first.topic, 'sancta_live_3');
      expect(LiveNow.listFrom(null), isEmpty);
      expect(LiveNow.listFrom([const {}]), isEmpty); // sem nome é descartado
    });
  });

  group('Planos Sancta+', () {
    test('equivalente mensal, economia e teste grátis vêm do servidor', () {
      final anual = PlanItem.fromJson(plansJson.first);
      expect(anual.priceCents, 14990);
      expect(anual.monthlyEquivalentCents, 1249);
      expect(anual.monthlyEquivalent, 'R\$ 12,49');
      expect(anual.savingsPercent, 37);
      expect(anual.compareAtCents, 23880);
      expect(anual.trialDays, 7);
      expect(anual.hasTrial, isTrue);
      expect(anual.isFeatured, isTrue);
      expect(anual.badge, 'Mais popular');
      expect(anual.isMonthly, isFalse);
      expect(anual.showMonthlyEquivalent, isTrue);
      expect(anual.playBasePlanId, 'anual');
    });

    test('mensal não mostra equivalente nem economia', () {
      final mensal = PlanItem.fromJson(plansJson.last);
      expect(mensal.isMonthly, isTrue);
      expect(mensal.showMonthlyEquivalent, isFalse);
      expect(mensal.savingsPercent, isNull);
      expect(mensal.hasTrial, isFalse);
    });

    test('valores ausentes, nulos ou em texto não quebram', () {
      final p = PlanItem.fromJson(const {'name': 'Vitalício', 'savings_percent': 0, 'monthly_equivalent_cents': null, 'trial_days': '3', 'price_cents': 99.0});
      expect(p.savingsPercent, isNull); // 0% não é economia
      expect(p.monthlyEquivalentCents, isNull);
      expect(p.trialDays, 3);
      expect(p.priceCents, 99);
      expect(PlanItem.fromJson(const {}).trialDays, 0);
    });

    test('linha do tempo do teste grátis: hoje → dia N-2 → dia N', () {
      final steps = trialTimeline(7);
      expect(steps.map((s) => s.when), ['Hoje', 'Dia 5', 'Dia 7']);
      expect(trialTimeline(2).map((s) => s.when), ['Hoje', 'Dia 2']);
    });
  });

  group('Paywall (/config → premium)', () {
    test('lê headline, benefícios com ícone, prova social, FAQ, garantia e depoimentos', () {
      final paywall = AppConfig.fromJson(configJson).paywall;
      expect(paywall.headline, 'Aprofunde sua vida de oração');
      expect(paywall.subheadline, isNotNull);
      expect(paywall.benefits, hasLength(4));
      expect(paywall.benefits.first.icon, 'headphones');
      expect(paywall.benefits.first.description, isNotNull);
      expect(paywall.socialProof, contains('20 mil'));
      expect(paywall.guarantee, 'Cancele quando quiser');
      expect(paywall.faq, hasLength(2));
      expect(paywall.testimonials.first.rating, 5);
    });

    test('servidor antigo: usa a lista simples de benefícios e textos padrão', () {
      final paywall = AppConfig.fromJson(const {
        'premium': {
          'benefits': ['Orações guiadas', ''],
        },
      }).paywall;
      expect(paywall.benefits.map((b) => b.title), ['Orações guiadas']);
      expect(paywall.benefits.first.icon, 'star');
      expect(paywall.guarantee, 'Cancele quando quiser');
      expect(paywall.headline, isNotEmpty);
      expect(AppConfig.fallback().paywall.benefits, isEmpty);
    });

    test('ícone desconhecido vira estrela; nota fica entre 1 e 5', () {
      final b = PremiumBenefit.fromJson(const {'icon': '<script>', 'title': 'X'});
      expect(b.icon, 'star');
      expect(benefitIcon('desconhecido'), isNotNull);
      expect(benefitIcon('cross'), isNull); // desenhada pelo app
      expect(Testimonial.fromJson(const {'content': 'a', 'rating': 9}).rating, 5);
      expect(Testimonial.fromJson(const {'content': 'a', 'rating': 0}).rating, 1);
    });
  });

  group('Utilidades da interface', () {
    test('distância das igrejas (vírgula decimal)', () {
      expect(formatDistance(0.35), '350 m');
      expect(formatDistance(0.004), '0 m');
      expect(formatDistance(1.24), '1,2 km');
      expect(formatDistance(12.6), '13 km');
    });

    test('período do dia (saudação e oração)', () {
      expect(PrayerPeriod.of(DateTime(2026, 10, 4, 8)).greeting, 'Bom dia');
      expect(PrayerPeriod.of(DateTime(2026, 10, 4, 8)).prayerTitle, 'Oração da manhã');
      expect(PrayerPeriod.of(DateTime(2026, 10, 4, 14)).greeting, 'Boa tarde');
      expect(PrayerPeriod.of(DateTime(2026, 10, 4, 21)).prayerTitle, 'Oração da noite');
      expect(PrayerPeriod.of(DateTime(2026, 10, 4, 3)), PrayerPeriod.noite);
    });

    test('temas mantêm os valores salvos no servidor (light|dark|sepia|system)', () {
      expect(SanctaTheme.values.map((t) => t.name), containsAll(['light', 'dark', 'sepia', 'system']));
      expect(SanctaTheme.fromValue('sepia'), SanctaTheme.sepia);
      expect(SanctaTheme.fromValue('desconhecido'), SanctaTheme.system);
      expect(ReadingFont.fromValue('classica'), ReadingFont.classica);
      expect(ReadingFont.fromValue(null), ReadingFont.padrao);
      expect(ReadingFont.classica.family, 'EB Garamond');
    });
  });
}
