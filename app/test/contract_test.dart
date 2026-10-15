// Contrato API ↔ app.
//
// As fixtures em `test/fixtures/api/*.json` são respostas REAIS do backend, gravadas pelo teste
// `backend/tests/Feature/Contract/ApiFixturesTest.php` (seeders do projeto + dados de teste, como visitante,
// usuário gratuito e assinante). Aqui elas passam pelos MESMOS modelos/funções que as telas usam, e os campos
// que as telas leem são conferidos. Se o backend mudar um formato, este teste quebra antes do app.
//
// Regerar as fixtures (pasta `backend/`):
//   SANCTA_WRITE_FIXTURES=1 php artisan test --filter=ApiFixturesTest
// Cada arquivo traz `_contract` (método, rota, status HTTP) e `body` (a resposta).
import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:sancta/core/models/models.dart';
import 'package:sancta/core/network/api_exception.dart';
import 'package:sancta/core/state/content_providers.dart';
import 'package:sancta/core/theme/palette.dart';
import 'package:sancta/core/utils/links.dart';
import 'package:sancta/features/notifications/reminders.dart';
import 'package:sancta/features/notifications/trial_reminder.dart';
import 'package:sancta/features/widget/widget_content.dart';

const _dir = 'test/fixtures/api';

Map<String, dynamic> _file(String name) => Map<String, dynamic>.from(jsonDecode(File('$_dir/$name.json').readAsStringSync()) as Map);

/// Corpo da resposta gravada.
dynamic body(String name) => _file(name)['body'];

/// Corpo como objeto JSON (falha se o servidor mandou outra coisa).
Map<String, dynamic> obj(String name) {
  final b = body(name);
  expect(b, isA<Map>(), reason: '$name deve ser um objeto JSON');
  return Map<String, dynamic>.from(b as Map);
}

Map<String, dynamic> _map(dynamic v) => Map<String, dynamic>.from(v as Map);

/// Status HTTP gravado.
int status(String name) => (_file(name)['_contract'] as Map)['status'] as int;

/// Como o app trata um erro HTTP (mesma conversão do ApiClient).
ApiException apiError(String name) {
  final options = RequestOptions(path: '${(_file(name)['_contract'] as Map)['route']}');
  return ApiException.fromDio(
    DioException(
      requestOptions: options,
      response: Response(requestOptions: options, statusCode: status(name), data: body(name)),
    ),
  );
}

/// Tipos de bloco que `ContentBlocksView` sabe desenhar (o padrão é parágrafo).
const _blockTypes = {
  'paragraph',
  'heading',
  'quote',
  'list',
  'ordered_list',
  'image',
  'divider',
  'rubric',
  'priest',
  'assembly',
  'dialogue',
  'postures',
  'expandable',
};

final _slug = RegExp(r'^[a-z0-9][a-z0-9-]*$');

void main() {
  setUpAll(() => initializeDateFormatting('pt_BR'));

  final fixtures = Directory(_dir).listSync().whereType<File>().where((f) => f.path.endsWith('.json')).toList();

  group('Cobertura do contrato', () {
    test('existem fixtures gravadas pelo backend', () {
      expect(fixtures.length, greaterThan(50), reason: 'Rode no backend: SANCTA_WRITE_FIXTURES=1 php artisan test --filter=ApiFixturesTest');
      for (final f in fixtures) {
        final json = jsonDecode(f.readAsStringSync()) as Map;
        expect(json['_contract'], isA<Map>(), reason: f.path);
        expect(json.containsKey('body'), isTrue, reason: f.path);
      }
    });

    test('todo endpoint chamado pelo app (lib/) tem resposta real gravada', () {
      String norm(String path) => path.split('?').first.replaceAll(RegExp(r'\$\{[^}]*\}|\$[A-Za-z_][A-Za-z0-9_.]*|\{[^}]*\}'), '{}');

      // Chamadas do ApiClient no código do app: .get('/x'…), .post(\n '/x'…) etc.
      final call = RegExp(r"\.(get|post|put|patch|delete)\(\s*'(/[^']*)'", multiLine: true);
      final used = <String>{};
      for (final file in Directory('lib').listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart'))) {
        for (final m in call.allMatches(file.readAsStringSync())) {
          used.add('${m.group(1)!.toUpperCase()} ${norm(m.group(2)!)}');
        }
      }

      final recorded = {
        for (final f in fixtures)
          if ((jsonDecode(f.readAsStringSync()) as Map)['_contract'] case final Map c) '${c['method']} ${norm('${c['route']}')}',
      };

      expect(used.length, greaterThan(40), reason: 'a varredura de lib/ deveria achar as chamadas da API');
      // /auth/google: o id_token é validado com as chaves do Google — a resposta tem o mesmo formato do login.
      expect(used.difference(recorded), isEmpty, reason: 'endpoints usados pelo app sem fixture do servidor');
    });

    test('todos os blocos de conteúdo enviados pelo servidor são desenhados pelo app', () {
      final seen = <String>{};
      void walk(dynamic v) {
        if (v is List) {
          v.forEach(walk);
        } else if (v is Map) {
          if (v['blocks'] is List) {
            for (final b in ContentBlock.listFrom(v['blocks'])) {
              seen.add(b.type);
              seen.addAll(b.children.map((c) => c.type));
            }
          }
          v.values.forEach(walk);
        }
      }

      for (final f in fixtures) {
        walk(jsonDecode(f.readAsStringSync()));
      }
      expect(seen, isNotEmpty);
      expect(seen.difference(_blockTypes), isEmpty);
    });
  });

  group('Configuração (/config)', () {
    test('visitante: textos, recursos, paywall, mapas e Google Play', () {
      final c = AppConfig.fromJson(obj('config_visitante'));
      expect(c.name, 'Sancta');
      expect(c.slogan, isNotEmpty);
      expect(c.minVersion, matches(RegExp(r'^\d+\.\d+\.\d+$')));
      expect(c.maintenance, isFalse);
      for (final key in ['live', 'news', 'churches', 'intentions', 'guest_mode', 'ai_explanations']) {
        expect(c.features.containsKey(key), isTrue, reason: key);
      }
      expect(c.feature('live'), isTrue);
      expect(c.privacyUrl, startsWith('https://'));
      expect(c.termsUrl, startsWith('https://'));
      expect(c.deleteAccountUrl, startsWith('https://'));
      expect(c.tilesLight, contains('{z}'));
      expect(c.productIds, contains('sancta_plus'));
      expect(c.obfuscatedAccountId, isNull, reason: 'visitante não tem conta para vincular a compra');
      expect(c.googleServerClientId, endsWith('.apps.googleusercontent.com'));

      final p = c.paywall;
      expect(p.headline, isNotEmpty);
      expect(p.subheadline, isNotNull);
      expect(p.benefits.length, greaterThanOrEqualTo(3));
      expect(p.benefits.every((b) => b.title.isNotEmpty && b.icon.isNotEmpty), isTrue);
      expect(p.benefits.map((b) => b.icon), isNot(everyElement('star')), reason: 'ícones do painel chegam ao app');
      expect(p.faq, isNotEmpty);
      expect(p.testimonials.single.content, isNotEmpty);
      expect(p.guarantee, isNotEmpty);
      expect(c.premiumBenefits, hasLength(p.benefits.length), reason: 'lista simples continua para o app 3.0');
    });

    test('logado: id ofuscado para vincular a compra do Google Play à conta', () {
      expect(AppConfig.fromJson(obj('config_gratuito')).obfuscatedAccountId, matches(RegExp(r'^[0-9a-f]{64}$')));
    });
  });

  group('Tela Hoje (/home)', () {
    for (final name in ['home_visitante', 'home_gratuito', 'home_assinante']) {
      test('$name: tudo o que a tela lê', () {
        final h = obj(name);
        expect(_map(h['greeting'])['title'], isNotEmpty);

        final verse = _map(h['daily_verse']);
        expect(verse['reference'], isA<String>());
        expect(verse['text'], isA<String>());
        expect(verse['reflection_locked'], isA<bool>());

        final liturgy = _map(h['liturgy']);
        expect(liturgy['gospel_reference'], 'Mt 21,33-43');
        expect(liturgy['title'], isNotEmpty);
        expect(_map(h['saint'])['name'], 'São Francisco de Assis');

        final prayers = (h['featured_prayers'] as List).map((e) => PrayerItem.fromJson(_map(e))).toList();
        expect(prayers, isNotEmpty);
        expect(prayers.every((p) => _slug.hasMatch(p.slug) && p.title.isNotEmpty), isTrue);

        final period = PeriodPrayers.fromJson(h['period_prayers']);
        expect(period.morning?.slug, 'oracao-da-manha');
        expect(period.afternoon?.slug, 'angelus');
        expect(period.night?.slug, 'oracao-da-noite');
        expect(period.morning?.durationMinutes, isNotNull);

        final journeys = (h['featured_journeys'] as List).map((e) => Journey.fromJson(_map(e))).toList();
        expect(journeys.every((j) => j.slug.isNotEmpty && j.stepsCount > 0), isTrue);

        final news = (h['news'] as List).map((e) => NewsItem.fromJson(_map(e))).toList();
        expect(news.every((n) => n.title.isNotEmpty && n.publishedAt != null), isTrue);

        final live = LiveNow.listFrom(h['live_now']);
        expect(live.first.isLive, isTrue);
        expect(live.first.watchUrl, startsWith('https://www.youtube.com/'));
        expect(live.every((l) => l.topic!.startsWith('sancta_live_')), isTrue);

        for (final e in (h['events'] as List).cast<Map>()) {
          expect(DateTime.tryParse('${e['starts_at']}'), isNotNull);
          expect(e['title'], isNotEmpty);
          expect(e['is_premium'], isA<bool>());
        }
      });
    }

    test('visitante × gratuito × assinante', () {
      expect(obj('home_visitante')['continue_journey'], isNull);
      final cont = Journey.fromJson(_map(obj('home_gratuito')['continue_journey']));
      expect(cont.slug, 'novena-nossa-senhora-aparecida');
      expect(cont.completedSteps, 1);
      expect(obj('home_gratuito')['streak'], 1);
      expect(obj('home_assinante')['is_premium'], isTrue);
    });

    test('widget "Oração do dia" a partir da resposta real', () {
      final content = WidgetContent.fromHome(obj('home_visitante'), DateTime(2026, 10, 4, 9));
      expect(content.entryFor(WidgetPeriod.manha).link, 'sancta://oracao/oracao-da-manha');
      expect(content.entryFor(WidgetPeriod.tarde).link, 'sancta://oracao/angelus');
      expect(content.entryFor(WidgetPeriod.noite).link, 'sancta://oracao/oracao-da-noite');
      for (final entry in content.periods.values) {
        expect(deepLinkToRoute(entry.link), isNotNull);
        expect(entry.subtitle, isNotEmpty);
      }
    });
  });

  group('Orações', () {
    test('categorias e listas', () {
      final cats = apiItems(body('categorias')).map(Category.fromJson).toList();
      expect(cats.length, greaterThanOrEqualTo(5));
      expect(cats.every((c) => c.slug.isNotEmpty && c.name.isNotEmpty && c.count >= 0), isTrue);

      final all = apiItems(body('oracoes')).map(PrayerItem.fromJson).toList();
      expect(all.length, greaterThan(20));
      expect(all.every((p) => _slug.hasMatch(p.slug) && p.categoryName != null), isTrue);
      expect(all.where((p) => p.isPremium).every((p) => p.locked), isTrue, reason: 'visitante: Sancta+ bloqueado');

      final filtered = apiItems(body('oracoes_categoria')).map(PrayerItem.fromJson).toList();
      expect(filtered.map((p) => p.categoryName).toSet(), {'Manhã e noite'});
    });

    test('detalhe: texto, áudio, favorito e bloqueio Sancta+', () {
      final open = PrayerItem.fromJson(obj('oracao_visitante'));
      expect(open.locked, isFalse);
      expect(open.blocks, isNotEmpty);
      expect(open.isFavorite, isFalse);
      expect(open.author, isNotNull);

      final liturgical = PrayerItem.fromJson(obj('oracao_liturgica'));
      final types = liturgical.blocks.map((b) => b.type).toSet();
      expect(types, containsAll(['rubric', 'priest', 'assembly', 'dialogue', 'postures', 'expandable']));
      expect(liturgical.blocks.firstWhere((b) => b.type == 'expandable').children, isNotEmpty);
      expect(liturgical.blocks.firstWhere((b) => b.type == 'postures').icons, isNotEmpty);

      for (final name in ['oracao_bloqueada_visitante', 'oracao_bloqueada_gratuito']) {
        final locked = PrayerItem.fromJson(obj(name));
        expect(locked.locked, isTrue, reason: name);
        expect(locked.audioUrl, isNull, reason: name);
        expect(locked.blocks.length, lessThanOrEqualTo(2), reason: 'prévia de 2 blocos ($name)');
      }
      expect(PrayerItem.fromJson(obj('oracao_assinante')).locked, isFalse);
    });

    test('favoritos', () {
      expect(obj('oracao_favoritar')['is_favorite'], isTrue);
      expect(apiItems(body('oracoes_favoritas')).map(PrayerItem.fromJson).single.slug, 'oracao-da-manha');
    });
  });

  group('Jornadas', () {
    test('lista e detalhe (visitante)', () {
      final list = apiItems(body('jornadas_visitante')).map(Journey.fromJson).toList();
      expect(list.length, 3);
      expect(list.every((j) => j.typeLabel.isNotEmpty && j.stepsCount > 0), isTrue);

      final j = Journey.fromJson(obj('jornada_visitante'));
      expect(j.steps, hasLength(j.stepsCount));
      expect(j.steps.map((s) => s.position), List.generate(j.stepsCount, (i) => i + 1));
      expect(j.steps.first.blocks, isNotEmpty);
    });

    test('progresso do usuário e bloqueio Sancta+', () {
      final mine = Journey.fromJson(obj('jornada_gratuito'));
      expect(mine.completedSteps, 1);
      expect(mine.steps.first.completed, isTrue);
      expect(mine.progress, greaterThan(0));
      expect(obj('jornada_concluir_dia'), {'completed_steps': 1, 'steps_count': 9, 'finished': false});
      expect(apiItems(body('jornadas_gratuito')).map(Journey.fromJson).firstWhere((j) => j.slug == mine.slug).completedSteps, 1);

      final locked = Journey.fromJson(obj('jornada_bloqueada_gratuito'));
      expect(locked.locked, isTrue);
      expect(locked.steps.first.locked, isFalse, reason: 'o 1º dia é a prévia');
      expect(locked.steps.skip(1).every((s) => s.locked && s.blocks.isEmpty && s.audioUrl == null), isTrue);

      final open = Journey.fromJson(obj('jornada_assinante'));
      expect(open.locked, isFalse);
      expect(open.steps.every((s) => !s.locked && s.blocks.isNotEmpty), isTrue);
    });
  });

  group('Bíblia', () {
    test('livros, capítulo e navegação', () {
      final b = obj('biblia_livros');
      final books = apiItems(b['books']).map(BibleBook.fromJson).toList();
      expect(books, hasLength(73));
      expect(books.map((x) => x.testament).toSet(), {'AT', 'NT'});
      expect(books.every((x) => _slug.hasMatch(x.slug) && x.chapters > 0), isTrue);
      expect(_map(b['version'])['name'], isNotEmpty);

      final ch = BibleChapter.fromJson(obj('biblia_capitulo_visitante'));
      expect(ch.bookSlug, 'salmos');
      expect(ch.verses, isNotEmpty);
      expect(ch.previous, isNotNull);
      expect(ch.next, isNotNull);
      expect(ch.verses.every((v) => v.highlight == null && !v.bookmarked), isTrue);
    });

    test('anotações do usuário no capítulo e na lista', () {
      final v = BibleChapter.fromJson(obj('biblia_capitulo_gratuito')).verses.first;
      expect(v.highlight, 'gold');
      expect(SanctaPalette.highlights.containsKey(v.highlight), isTrue, reason: 'cor da API existe na paleta do app');
      expect(v.note, isNotEmpty);
      expect(v.bookmarked, isTrue);

      for (final name in ['biblia_anotacoes_notas', 'biblia_anotacoes_destaques', 'biblia_anotacoes_marcadores']) {
        final item = apiItems(body(name)).single;
        for (final key in ['book_slug', 'chapter', 'verse', 'reference', 'text']) {
          expect(item[key], isNotNull, reason: '$name.$key');
        }
      }
    });

    test('progresso de leitura (vazio e salvo) e busca', () {
      final empty = body('biblia_progresso_vazio');
      expect(empty is Map ? empty['book_slug'] : null, isNull, reason: 'sem progresso o app não mostra "Continuar"');
      final saved = obj('biblia_progresso');
      expect(saved['book_slug'], 'joao');
      expect(saved['chapter'], 3);
      expect(saved['book'], 'João');

      final results = ((obj('biblia_busca')['results']) as List).cast<Map>();
      expect(results, isNotEmpty);
      for (final r in results) {
        expect(r['book_slug'], isA<String>());
        expect(r['chapter'], isA<int>());
        expect(r['verse'], isA<int>());
        expect(r['reference'], isNotEmpty);
        expect(r['text'], contains('Nicodemos'));
      }
    });

    test('explicação por IA: assinante recebe; gratuito vê o paywall', () {
      final ok = obj('biblia_explicar');
      expect(ok['explanation'], isNotEmpty);
      expect(ok['disclaimer'], isNotEmpty);
      final e = apiError('biblia_explicar_gratuito');
      expect(e.statusCode, 402);
      expect(e.paywall, isTrue);
      expect(e.message, contains('Sancta+'));
    });
  });

  group('Liturgia (/liturgy)', () {
    test('leituras, orações e homilia (bloqueada para visitante)', () {
      final l = obj('liturgia_visitante');
      final readings = (l['readings'] as List).cast<Map>();
      expect(readings.map((r) => r['label']), contains('Evangelho'));
      expect(readings.every((r) => '${r['text']}'.isNotEmpty), isTrue);
      expect(l['prayers'], isA<Map>());
      expect(_map(l['prayers'])['collect'], isNotEmpty);
      expect(l['color_hex'], matches(RegExp(r'^#[0-9A-Fa-f]{6}$')));
      expect(l['homily'], isNull);
      expect(l['homily_locked'], isTrue);
      expect(obj('liturgia_assinante')['homily'], isNotEmpty);
    });

    test('dia sem orações: `prayers` continua sendo um mapa', () {
      expect(obj('liturgia_sem_oracoes')['prayers'], isA<Map>());
    });
  });

  group('Igrejas', () {
    test('lista, busca e proximidade', () {
      for (final name in ['igrejas', 'igrejas_busca', 'igrejas_proximas']) {
        final list = apiItems(body(name)).map(Church.fromJson).toList();
        expect(list, isNotEmpty, reason: name);
        expect(list.every((c) => c.id.isNotEmpty && c.name.isNotEmpty && c.typeLabel.isNotEmpty), isTrue, reason: name);
      }
      expect(apiItems(body('igrejas')).map(Church.fromJson).where((c) => c.hasLocation), isNotEmpty);
    });

    test('detalhe com horários e contatos', () {
      final c = Church.fromJson(obj('igreja'));
      expect(c.schedules, isNotEmpty);
      expect(c.schedules.values.expand((s) => s).every((s) => s.label.isNotEmpty), isTrue);
      expect(c.contacts, isNotEmpty);
      expect(c.mapsUrl, startsWith('https://'));
      expect(Church.fromJson(obj('igreja_gratuito')).isFavorite, isTrue);
    });

    test('igreja sem horários nem contatos não quebra a tela', () {
      final c = Church.fromJson(obj('igreja_sem_horarios'));
      expect(c.schedules, isEmpty);
      expect(c.contacts, isEmpty);
      expect(c.hasLocation, isFalse);
    });

    test('favoritar, favoritas e sugestão', () {
      expect(obj('igreja_favoritar')['is_favorite'], isTrue);
      expect(apiItems(body('igrejas_favoritas')).map(Church.fromJson), hasLength(1));
      expect(status('igreja_sugestao'), 201);
      expect(obj('igreja_sugestao')['message'], isNotEmpty);
    });
  });

  group('Ao vivo (/live-channels)', () {
    test('canais com status, agenda e "Me avise"', () {
      final list = apiItems(body('ao_vivo')).map(LiveChannel.fromJson).toList();
      expect(list.first.isLive, isTrue, reason: 'ao vivo primeiro');
      expect(list.first.live?.title, isNotEmpty);
      expect(list.first.openUrl, 'https://www.youtube.com/watch?v=LIVEvideo01');
      expect(list.every((c) => c.topic == 'sancta_live_${c.id}' && c.categoryLabel.isNotEmpty), isTrue);
      expect(list.expand((c) => c.schedule).every((s) => s.minutes < 24 * 60), isTrue);
      expect(list.where((c) => c.schedule.isNotEmpty), isNotEmpty);
    });
  });

  group('Sancta+ (/plans)', () {
    test('planos com equivalente mensal, economia, teste grátis e IDs do Play', () {
      final plans = apiItems(body('planos')).map(PlanItem.fromJson).toList();
      expect(plans.map((p) => p.slug), containsAll(['mensal', 'semestral', 'anual']));
      final annual = plans.firstWhere((p) => p.slug == 'anual');
      expect(annual.hasTrial, isTrue);
      expect(annual.trialDays, 7);
      expect(annual.savingsPercent, greaterThan(0));
      expect(annual.showMonthlyEquivalent, isTrue);
      expect(annual.priceFormatted, startsWith('R\$'));
      final monthly = plans.firstWhere((p) => p.slug == 'mensal');
      expect(monthly.isMonthly, isTrue);
      expect(monthly.savingsPercent, isNull);
      expect(plans.every((p) => p.playProductId == 'sancta_plus' && p.playBasePlanId == p.slug), isTrue);
    });
  });

  group('Conta', () {
    test('cadastro, login e Google devolvem token + usuário', () {
      for (final name in ['auth_cadastro', 'auth_login', 'auth_google']) {
        final m = obj(name);
        expect(m['token'], isA<String>(), reason: name);
        final user = AppUser.fromJson(_map(m['user']));
        expect(user.id, isNotEmpty);
        expect(user.firstName, isNotEmpty);
        expect(user.isPremium, isFalse);
      }
      expect(status('auth_cadastro'), 201);
      expect(AppUser.fromJson(_map(obj('auth_google')['user'])).hasPassword, isFalse);
      expect(obj('auth_esqueci_senha')['message'], isNotEmpty);
    });

    test('perfil gratuito, atualização e lembretes da conta', () {
      final free = AppUser.fromJson(obj('me_gratuito'));
      expect(free.isPremium, isFalse);
      expect(free.subscription, isNull);
      expect(free.notificationPreferences['daily_reminder'], isTrue);
      expect(ReminderSettings.fromAccountPrefs(free.notificationPreferences), isNull, reason: 'padrão do servidor não é escolha do usuário');

      final updated = AppUser.fromJson(obj('me_atualizar'));
      expect(updated.theme, 'sepia');
      final reminders = ReminderSettings.fromAccountPrefs(updated.notificationPreferences)!;
      expect(reminders.eveningOn, isTrue);
      expect(reminders.evening.format(), '21:00');
      expect(reminders.morning.format(), '06:30');
      expect(AppUser.fromJson(obj('me_atualizar_nome')).name, 'Ana Clara');
    });

    test('assinante pelo site e pelo Google Play (teste grátis)', () {
      final web = AppUser.fromJson(obj('me_assinante'));
      expect(web.isPremium, isTrue);
      expect(web.subscription!.status, 'active');
      expect(web.subscription!.endsAt, isNotNull);
      expect(web.subscription!.manageUrl, isNotNull);

      final play = AppUser.fromJson(obj('me_assinante_teste_gratis'));
      expect(play.isPremium, isTrue);
      expect(play.subscription!.status, 'trialing');
      expect(play.subscription!.provider, 'google_play');
      expect(play.subscription!.autoRenew, isTrue);
      expect(play.subscription!.manageUrl, startsWith('https://play.google.com/'));
      // Aviso "seu teste termina em 2 dias" agendado a partir da data real da assinatura.
      expect(TrialReminder.fireAtFor(play, DateTime.parse('2026-10-04T08:30:00-03:00')), play.subscription!.endsAt!.subtract(const Duration(days: 2)));

      final verify = obj('billing_verificar_play');
      expect(AppUser.fromJson(_map(verify['user'])).isPremium, isTrue);
      expect(_map(verify['subscription'])['status'], 'trialing');
    });

    test('estatísticas e sessões de oração', () {
      final s = obj('me_estatisticas');
      expect(s['current_streak'], 1);
      final week = (s['last_seven_days'] as List).cast<Map>();
      expect(week, hasLength(7));
      expect(week.every((d) => DateTime.tryParse('${d['date']}') != null && d['seconds'] is num), isTrue);
      expect(obj('oracao_sessao')['current_streak'], 1);
    });

    test('mensagens de aparelho, senha, saída e exclusão', () {
      for (final name in [
        'dispositivo_registrar',
        'dispositivo_remover',
        'me_senha',
        'auth_sair',
        'auth_sair_todos',
        'me_excluir',
        'biblia_anotar',
        'biblia_progresso_salvar',
      ]) {
        expect(obj(name)['message'], isNotEmpty, reason: name);
      }
    });
  });

  group('Comunidade, avisos e notícias', () {
    test('intenções', () {
      final guest = apiItems(body('intencoes_visitante')).map(Intention.fromJson).toList();
      expect(guest, hasLength(2));
      expect(guest.every((i) => !i.prayed && i.text.isNotEmpty), isTrue);
      expect(guest.map((i) => i.author), contains('Anônimo'));
      final rezar = obj('intencao_rezar');
      expect(rezar['prayers_count'], isA<int>(), reason: 'a tela lê `as int?`');
      expect(apiItems(body('intencoes_gratuito')).map(Intention.fromJson).where((i) => i.prayed), hasLength(1));
      expect(status('intencao_enviar'), 201);
    });

    test('avisos e pop-ups por público', () {
      final guest = apiItems(body('avisos_visitante')).map(Announcement.fromJson).toList();
      expect(guest, isNotEmpty);
      for (final a in [...guest, ...apiItems(body('avisos_assinante')).map(Announcement.fromJson)]) {
        expect(a.updatedAt, isNotEmpty);
        expect(['popup', 'banner', 'fullscreen'], contains(a.kind));
        if (a.ctaUrl != null && a.ctaUrl!.startsWith('sancta://')) expect(deepLinkToRoute(a.ctaUrl), isNotNull, reason: a.ctaUrl);
      }
      expect(apiItems(body('avisos_assinante')).map(Announcement.fromJson).map((a) => a.kind), contains('banner'));
      expect(obj('aviso_interacao')['ok'], isTrue);
    });

    test('notícias, página e perguntas frequentes', () {
      final news = apiItems(body('noticias')).map(NewsItem.fromJson).toList();
      expect(news, hasLength(2));
      final detail = NewsItem.fromJson(obj('noticia'));
      expect(detail.hasFullContent, isTrue);
      expect(detail.blocks, isNotEmpty);

      final page = obj('pagina');
      expect(page['title'], isNotEmpty);
      expect(ContentBlock.listFrom(page['blocks']), isNotEmpty);

      final faqs = apiItems(body('faqs'));
      expect(faqs, isNotEmpty);
      expect(faqs.every((f) => '${f['question']}'.isNotEmpty && '${f['answer']}'.isNotEmpty), isTrue);
    });
  });

  group('Servidores antigos (PHP manda `[]` para mapa vazio)', () {
    test('igreja, usuário, configuração e plano não quebram', () {
      final church = Church.fromJson({...obj('igreja_sem_horarios'), 'schedules': const [], 'contacts': const []});
      expect(church.schedules, isEmpty);
      expect(church.contacts, isEmpty);

      final user = AppUser.fromJson({...obj('me_gratuito'), 'notification_preferences': const [], 'stats': const []});
      expect(user.notificationPreferences, isEmpty);
      expect(user.currentStreak, 0);

      final config = AppConfig.fromJson({...obj('config_visitante'), 'features': const [], 'billing': const []});
      expect(config.feature('live'), isTrue, reason: 'sem a chave, o recurso fica ligado');
      expect(config.productIds, isEmpty);

      final plan = PlanItem.fromJson({
        ...apiItems(body('planos')).first,
        'google_play': const [],
        'features': const {'0': 'x'},
      });
      expect(plan.playProductId, isNull);
      expect(plan.features, isEmpty);
    });
  });

  group('Erros que o app entende', () {
    test('401 encerra a sessão', () {
      final e = apiError('erro_401');
      expect(e.isUnauthorized, isTrue);
      expect(e.message, isNotEmpty);
    });

    test('403, 404, 422 e 429 viram mensagens em português', () {
      expect(apiError('erro_403').message, contains('suspensa'));
      expect(apiError('erro_404').statusCode, 404);
      final invalid = apiError('erro_422');
      expect(invalid.field('email'), isNotNull);
      expect(invalid.field('password'), isNotNull);
      expect(invalid.field('accept_terms'), isNotNull);
      final tooMany = apiError('erro_429');
      expect(tooMany.statusCode, 429);
      expect(tooMany.message, contains('tentativas'));
    });
  });
}
