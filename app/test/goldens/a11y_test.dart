// Acessibilidade das telas principais: texto a 1,3×, 1,6× e 2,0× (o máximo aceito pelo app — ver
// `maxTextScale` em lib/app/app.dart) sem estouro de layout, alvos de toque ≥ 48 dp e ações com rótulo
// para leitores de tela.
import 'package:flutter_test/flutter_test.dart';
import 'package:sancta/app/app.dart' show maxTextScale;

import 'harness.dart';

void main() {
  const scales = [1.3, 1.6, maxTextScale];

  const routes = {
    'Hoje': '/hoje',
    'Orar': '/orar',
    'Bíblia': '/biblia',
    'Igrejas': '/igrejas',
    'Você': '/voce',
    'Ao vivo': '/ao-vivo',
    'Sancta+': '/planos',
    'Ajustes': '/ajustes',
    'Oração': '/oracao/oracao-da-manha',
    'Oração bloqueada': '/oracao/lectio-divina',
    'Jornada': '/jornada/novena-aparecida',
    'Capítulo': '/biblia/joao/3',
    'Liturgia': '/liturgia',
    'Igreja': '/igreja/c-1',
    'Notícias': '/noticias',
    'Intenções': '/intencoes',
    'Busca na Bíblia': '/biblia-busca',
    'Anotações': '/anotacoes',
    'Sugerir igreja': '/sugerir-igreja',
    'Notícia': '/noticia/2',
    'Página': '/pagina/sobre',
    'Favoritos': '/favoritos',
    'Ajuda': '/ajuda',
  };

  for (final entry in routes.entries) {
    for (final scale in scales) {
      testWidgets('${entry.key}: texto a $scale× sem estouro', (tester) async {
        await pumpApp(tester, location: entry.value, textScale: scale);
        expect(tester.takeException(), isNull);
      });
    }

    testWidgets('${entry.key}: alvos de toque e rótulos', (tester) async {
      final handle = tester.ensureSemantics();
      await pumpApp(tester, location: entry.value);
      await expectLater(tester, meetsGuideline(androidTapTargetGuideline));
      await expectLater(tester, meetsGuideline(labeledTapTargetGuideline));
      handle.dispose();
    });
  }

  for (final location in ['/entrar', '/cadastrar', '/boas-vindas', '/esqueci-senha']) {
    for (final scale in scales) {
      testWidgets('$location (visitante): $scale× sem estouro e alvos de toque', (tester) async {
        final handle = tester.ensureSemantics();
        await pumpApp(tester, location: location, loggedIn: false, textScale: scale);
        expect(tester.takeException(), isNull);
        await expectLater(tester, meetsGuideline(androidTapTargetGuideline));
        handle.dispose();
      });
    }
  }

  // Tela pequena (360 × 640, aparelhos de entrada) com a maior escala.
  for (final location in ['/hoje', '/orar', '/planos', '/voce', '/ajustes', '/ao-vivo', '/liturgia', '/oracao/oracao-da-manha', '/jornada/novena-aparecida']) {
    testWidgets('$location em tela pequena a $maxTextScale×', (tester) async {
      await pumpApp(tester, location: location, textScale: maxTextScale, height: 640, width: 360);
      expect(tester.takeException(), isNull);
    });
  }
  for (final location in ['/boas-vindas', '/entrar', '/cadastrar']) {
    testWidgets('$location (visitante) em tela pequena a $maxTextScale×', (tester) async {
      await pumpApp(tester, location: location, loggedIn: false, textScale: maxTextScale, height: 640, width: 360);
      expect(tester.takeException(), isNull);
    });
  }
}
