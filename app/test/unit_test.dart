import 'package:flutter_test/flutter_test.dart';
import 'package:sancta/app/status_gate.dart';
import 'package:sancta/core/models/models.dart';
import 'package:sancta/core/network/api_client.dart';
import 'package:sancta/core/network/api_exception.dart';
import 'package:sancta/core/storage/cache_store.dart';
import 'package:sancta/core/storage/secure_store.dart';
import 'package:sancta/core/utils/links.dart';
import 'package:sancta/features/auth/auth_scaffold.dart';
import 'package:sancta/features/bible/bible_screen.dart';

void main() {
  // Achado no QA: o APK de release conversava por HTTP com o servidor local — o network_security_config do
  // Android não se aplica ao HttpClient do Dart. A regra "só HTTPS" agora fica no ApiClient.
  group('HTTPS obrigatório no build de produção', () {
    test('URL sem HTTPS é recusada antes de sair do aparelho', () async {
      final api = ApiClient(secureStore: SecureStore(), cache: CacheStore(), baseUrl: 'http://10.0.2.2:8014/api/v1', requireHttps: true);
      await expectLater(api.get('/config'), throwsA(isA<ApiException>().having((e) => e.isOffline, 'sem resposta do servidor', isTrue)));
      await expectLater(api.post('/auth/login', data: {'email': 'a@b.c'}), throwsA(isA<ApiException>()));
    });

    test('no build de depuração (servidor local) o HTTP continua permitido', () {
      expect(ApiClient(secureStore: SecureStore(), cache: CacheStore()).requireHttps, isFalse);
    });
  });

  group('Deep links (somente sancta:// é aceito)', () {
    test('mapeia rotas internas', () {
      expect(deepLinkToRoute('sancta://liturgia'), '/liturgia');
      expect(deepLinkToRoute('sancta://oracao/pai-nosso'), '/oracao/pai-nosso');
      expect(deepLinkToRoute('sancta://jornada/novena-nossa-senhora-aparecida'), '/jornada/novena-nossa-senhora-aparecida');
      expect(deepLinkToRoute('sancta://planos'), '/planos');
    });

    test('ignora esquemas externos e destinos desconhecidos', () {
      expect(deepLinkToRoute('https://evil.example.com'), isNull);
      expect(deepLinkToRoute('javascript:alert(1)'), isNull);
      expect(deepLinkToRoute('sancta://desconhecido'), isNull);
      expect(deepLinkToRoute(null), isNull);
    });

    test('codifica parâmetros para evitar injeção de rota', () {
      expect(deepLinkToRoute('sancta://oracao/a%2F..%2Fadmin'), '/oracao/a%2F..%2Fadmin');
    });

    test('nomes novos das abas (Orar, Você, Ajustes)', () {
      expect(deepLinkToRoute('sancta://orar'), '/orar');
      expect(deepLinkToRoute('sancta://voce'), '/voce');
      expect(deepLinkToRoute('sancta://ajustes'), '/ajustes');
      expect(deepLinkToRoute('sancta://hoje'), '/hoje');
      expect(deepLinkToRoute('sancta://'), '/hoje');
      expect(deepLinkToRoute('sancta://ao-vivo'), '/ao-vivo');
    });

    test('links antigos continuam abrindo (compatibilidade)', () {
      expect(deepLinkToRoute('sancta://rezar'), '/orar');
      expect(deepLinkToRoute('sancta://oracoes'), '/orar');
      expect(deepLinkToRoute('sancta://perfil'), '/voce');
      expect(deepLinkToRoute('sancta://configuracoes'), '/ajustes');
      expect(deepLinkToRoute('sancta://inicio'), '/hoje');
      expect(deepLinkToRoute('sancta://live'), '/ao-vivo');
      expect(deepLinkToRoute('sancta://sancta-plus'), '/planos');
    });

    test('notícia só com id numérico; destinos sem parâmetro obrigatório são recusados', () {
      expect(deepLinkToRoute('sancta://noticia/42'), '/noticia/42');
      expect(deepLinkToRoute('sancta://noticia/abc'), isNull);
      expect(deepLinkToRoute('sancta://noticia/0'), isNull);
      expect(deepLinkToRoute('sancta://oracao'), isNull);
      expect(deepLinkToRoute('sancta://igreja'), isNull);
      expect(deepLinkToRoute('SANCTA://hoje'), isNull);
      expect(deepLinkToRoute('sancta:/hoje'), isNull);
    });
  });

  group('Versão mínima (forçar atualização)', () {
    test('compara versões semânticas', () {
      expect(StatusGate.isOlder('2.1.2', '3.0.0'), isTrue);
      expect(StatusGate.isOlder('3.0.0', '3.0.0'), isFalse);
      expect(StatusGate.isOlder('3.1.0', '3.0.9'), isFalse);
      expect(StatusGate.isOlder('3.0.10', '3.0.9'), isFalse);
    });
  });

  group('Validações de formulário', () {
    test('e-mail', () {
      expect(validateEmail('maria@exemplo.com'), isNull);
      expect(validateEmail('maria@'), isNotNull);
      expect(validateEmail(''), isNotNull);
    });

    test('senha forte', () {
      expect(validatePassword('Senha123'), isNull);
      expect(validatePassword('curta1'), isNotNull);
      expect(validatePassword('somenteletras'), isNotNull);
    });
  });

  group('Referências bíblicas', () {
    final books = [
      BibleBook.fromJson({'testament': 'NT', 'position': 50, 'name': 'João', 'abbreviation': 'Jo', 'slug': 'joao', 'chapters_count': 21}),
      BibleBook.fromJson({'testament': 'AT', 'position': 21, 'name': 'Salmos', 'abbreviation': 'Sl', 'slug': 'salmos', 'chapters_count': 150}),
      BibleBook.fromJson({'testament': 'NT', 'position': 53, 'name': '1 Coríntios', 'abbreviation': '1Cor', 'slug': '1-corintios', 'chapters_count': 16}),
    ];

    test('resolve abreviações e nomes', () {
      final jo = resolveReference(books, 'Jo 3,16');
      expect(jo?.slug, 'joao');
      expect(jo?.chapter, 3);
      expect(jo?.verse, 16);
      expect(resolveReference(books, 'Salmos 22')?.chapter, 22);
      expect(resolveReference(books, '1Cor 13')?.slug, '1-corintios');
    });

    test('rejeita capítulo inexistente ou texto comum', () {
      expect(resolveReference(books, 'Jo 99'), isNull);
      expect(resolveReference(books, 'amor ao próximo'), isNull);
    });
  });

  group('Modelos tolerantes a dados incompletos', () {
    test('AppConfig com JSON vazio usa padrões seguros', () {
      final config = AppConfig.fromJson(const {});
      expect(config.name, 'Sancta');
      expect(config.maintenance, isFalse);
      expect(config.feature('qualquer'), isTrue);
      expect(config.productIds, isEmpty);
      expect(config.googleServerClientId, isNull);
    });

    test('AppConfig lê do painel o client ID do Google e os IDs das assinaturas', () {
      final config = AppConfig.fromJson(const {
        'billing': {
          'product_ids': ['sancta_plus', ''],
        },
        'auth': {'google_server_client_id': '123-abc.apps.googleusercontent.com'},
      });
      expect(config.productIds, ['sancta_plus']);
      expect(config.googleServerClientId, '123-abc.apps.googleusercontent.com');
    });

    test('Oração bloqueada', () {
      final p = PrayerItem.fromJson({
        'id': 1,
        'slug': 'x',
        'title': 'X',
        'is_premium': true,
        'locked': true,
        'blocks': [
          {'type': 'paragraph', 'text': 'a'},
        ],
      });
      expect(p.locked, isTrue);
      expect(p.blocks, hasLength(1));
      expect(p.audioUrl, isNull);
    });
  });
}
