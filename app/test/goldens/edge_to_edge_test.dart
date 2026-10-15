// Edge-to-edge (Android): barras do sistema transparentes e nenhum conteúdo fixo por trás delas.
// Simula a barra de status (24 dp) e a navegação por 3 botões (48 dp), o pior caso.
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sancta/core/theme/system_bars.dart';

import 'harness.dart';

const _status = 24.0;
const _nav = 48.0;
const _height = 844.0;

Future<void> _pumpWithBars(WidgetTester tester, String location, {bool loggedIn = true}) async {
  tester.view.padding = const FakeViewPadding(top: _status * 2, bottom: _nav * 2);
  tester.view.viewPadding = const FakeViewPadding(top: _status * 2, bottom: _nav * 2);
  await pumpApp(tester, location: location, loggedIn: loggedIn);
}

void _expectAboveNavBar(WidgetTester tester, Finder finder) {
  expect(finder, findsWidgets);
  for (final element in finder.evaluate()) {
    final box = element.renderObject! as RenderBox;
    final bottom = box.localToGlobal(Offset(0, box.size.height)).dy;
    expect(bottom, lessThanOrEqualTo(_height - _nav + .5), reason: '${element.widget} fica atrás da barra de navegação');
  }
}

void _expectBelowStatusBar(WidgetTester tester, Finder finder) {
  final box = tester.renderObject<RenderBox>(finder.first);
  expect(box.localToGlobal(Offset.zero).dy, greaterThanOrEqualTo(_status - .5), reason: 'conteúdo atrás da barra de status');
}

void main() {
  test('barras transparentes com ícones legíveis no claro e no escuro', () {
    final light = edgeToEdgeStyle(Brightness.light);
    final dark = edgeToEdgeStyle(Brightness.dark);
    for (final s in [light, dark]) {
      expect(s.statusBarColor, const Color(0x00000000));
      expect(s.systemNavigationBarColor, const Color(0x00000000));
      expect(s.systemNavigationBarContrastEnforced, isNull, reason: 'mantém o véu de contraste do Android na navegação por 3 botões');
    }
    expect(light.statusBarIconBrightness, Brightness.dark);
    expect(light.systemNavigationBarIconBrightness, Brightness.dark);
    expect(dark.statusBarIconBrightness, Brightness.light);
    expect(dark.systemNavigationBarIconBrightness, Brightness.light);
  });

  testWidgets('Hoje: cabeçalho abaixo da barra de status e abas acima da navegação', (tester) async {
    await _pumpWithBars(tester, '/hoje');
    _expectBelowStatusBar(tester, find.text('Bom dia, Ana'));
    _expectAboveNavBar(tester, find.descendant(of: find.byType(NavigationBar), matching: find.text('Hoje')));
  });

  testWidgets('Sancta+: botão de assinar acima da navegação; herói com ícones claros', (tester) async {
    await _pumpWithBars(tester, '/planos');
    _expectAboveNavBar(tester, find.textContaining('dias grátis'));
    expect(find.byType(DarkSystemBars), findsOneWidget);
    _expectBelowStatusBar(tester, find.byTooltip('Fechar'));
  });

  for (final location in [
    '/ajustes',
    '/liturgia',
    '/anotacoes',
    '/intencoes',
    '/biblia/joao/3',
    '/noticias',
    '/ajuda',
    '/oracao/oracao-da-manha',
    '/jornada/novena-aparecida',
  ]) {
    testWidgets('$location: o fim da lista para acima da navegação', (tester) async {
      await _pumpWithBars(tester, location);
      // Rolagem vertical principal (a maior), até o fim.
      final scrollable = find.byWidgetPredicate((w) => w is Scrollable && axisDirectionToAxis(w.axisDirection) == Axis.vertical);
      final main = scrollable.evaluate().reduce((a, b) => (a.size?.height ?? 0) >= (b.size?.height ?? 0) ? a : b);
      final state = (main as StatefulElement).state as ScrollableState;
      // Listas preguiçosas: o fim estimado cresce conforme os itens são montados.
      for (var i = 0; i < 20 && state.position.extentAfter > 0; i++) {
        state.position.jumpTo(state.position.maxScrollExtent);
        await tester.pump();
      }
      await settle(tester);

      // O último texto do conteúdo termina acima da barra de navegação.
      var last = 0.0;
      for (final e in find.descendant(of: find.byElementPredicate((el) => el == main), matching: find.byType(RichText)).evaluate()) {
        final box = e.renderObject! as RenderBox;
        if (!box.hasSize) continue;
        final bottom = box.localToGlobal(Offset(0, box.size.height)).dy;
        if (bottom <= _height + 1 && bottom > last) last = bottom;
      }
      expect(last, greaterThan(0));
      expect(last, lessThanOrEqualTo(_height - _nav + .5), reason: 'o fim de $location fica atrás da barra de navegação');
    });
  }

  // Achado no emulador: o Scaffold tira o recuo inferior do `bottomSheet`; as ações ficavam sob a barra de gestos.
  testWidgets('Capítulo: barra de ações do versículo acima da navegação', (tester) async {
    await _pumpWithBars(tester, '/biblia/joao/3');
    await tester.tap(find.textContaining('de tal modo Deus amou o mundo'));
    await settle(tester);
    _expectAboveNavBar(tester, find.text('Nota'));
    _expectAboveNavBar(tester, find.text('Compartilhar'));
  });

  testWidgets('boas-vindas: tela inteira em Azul Manto com ícones claros', (tester) async {
    await _pumpWithBars(tester, '/boas-vindas', loggedIn: false);
    expect(find.byType(DarkSystemBars), findsOneWidget);
    _expectAboveNavBar(tester, find.text('Explorar sem conta'));
    _expectBelowStatusBar(tester, find.text('Sua jornada de fé, todos os dias.'));
  });
}
