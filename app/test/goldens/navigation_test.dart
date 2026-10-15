// Navegação: 5 abas, rotas novas e redirecionamento das antigas, com o roteador real do app.
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sancta/app/router.dart';
import 'package:sancta/core/motion/portal_tab_icon.dart';

import 'harness.dart';

void main() {
  testWidgets('barra inferior: Hoje · Orar · Bíblia · Igrejas · Você (Hoje com o Portal)', (tester) async {
    await pumpApp(tester);
    final bar = find.byType(NavigationBar);
    expect(bar, findsOneWidget);
    for (final label in ['Hoje', 'Orar', 'Bíblia', 'Igrejas', 'Você']) {
      expect(
        find.descendant(of: bar, matching: find.text(label)),
        findsOneWidget,
        reason: label,
      );
    }
    expect(find.descendant(of: bar, matching: find.byType(PortalTabIcon)), findsWidgets);
    expect(find.text('Rezar'), findsNothing);
    expect(find.text('Perfil'), findsNothing);
  });

  testWidgets('tocar nas abas troca de tela', (tester) async {
    final container = await pumpApp(tester);
    await tester.tap(find.text('Orar').last);
    await settle(tester);
    expect(routerOf(container).routeInformationProvider.value.uri.path, '/orar');
    await tester.tap(find.text('Você').last);
    await settle(tester);
    expect(routerOf(container).routeInformationProvider.value.uri.path, '/voce');
  });

  for (final entry in legacyRoutes.entries) {
    testWidgets('rota antiga ${entry.key} redireciona para ${entry.value}', (tester) async {
      final container = await pumpApp(tester, location: entry.key);
      expect(routerOf(container).routeInformationProvider.value.uri.path, entry.value);
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('sem conta e sem modo visitante: vai para as boas-vindas', (tester) async {
    final container = await pumpApp(tester, location: '/orar', loggedIn: false, guest: false);
    expect(routerOf(container).routeInformationProvider.value.uri.path, '/boas-vindas');
  });

  testWidgets('modo visitante navega pelas abas', (tester) async {
    final container = await pumpApp(tester, location: '/orar', loggedIn: false);
    expect(routerOf(container).routeInformationProvider.value.uri.path, '/orar');
  });

  // Achado no emulador: o botão marcava o modo visitante, mas a tela de boas-vindas continuava aberta.
  testWidgets('"Explorar sem conta" entra no app como visitante', (tester) async {
    final container = await pumpApp(tester, location: '/boas-vindas', loggedIn: false, guest: false);
    expect(routerOf(container).routeInformationProvider.value.uri.path, '/boas-vindas');
    await tester.tap(find.text('Explorar sem conta'));
    await settle(tester);
    expect(routerOf(container).routeInformationProvider.value.uri.path, '/hoje');
  });
}
