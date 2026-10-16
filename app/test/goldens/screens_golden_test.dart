// Goldens das telas principais (390 × 844, fontes reais, dados falsos de fixtures.dart).
// Atualize com: flutter test test/goldens/screens_golden_test.dart --update-goldens
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'harness.dart';

void main() {
  final screens = <({String name, String location, bool dark, bool loggedIn})>[
    (name: 'hoje_claro', location: '/hoje', dark: false, loggedIn: true),
    (name: 'hoje_escuro', location: '/hoje', dark: true, loggedIn: true),
    (name: 'voce_escuro', location: '/voce', dark: true, loggedIn: true),
    (name: 'ao_vivo_claro', location: '/ao-vivo', dark: false, loggedIn: true),
    (name: 'sancta_plus_claro', location: '/planos', dark: false, loggedIn: true),
    (name: 'entrar_claro', location: '/entrar', dark: false, loggedIn: false),
  ];

  for (final s in screens) {
    testWidgets('golden ${s.name}', (tester) async {
      await pumpApp(tester, location: s.location, dark: s.dark, loggedIn: s.loggedIn);
      await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 500)));
      await tester.pump();
      expect(tester.takeException(), isNull);
      await expectLater(find.byType(MaterialApp), matchesGoldenFile('png/${s.name}.png'));
    });
  }
}
