// Quadros-chave da abertura (390 × 844, fontes reais). Atualize com:
// flutter test test/motion/intro_golden_test.dart --update-goldens
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sancta/features/splash/sancta_intro.dart';

import 'motion_test_utils.dart';

void main() {
  setUp(SanctaIntro.debugReset);

  testWidgets('abertura: quadros-chave (0 → 1500 ms) e luz respirando na espera', (tester) async {
    await loadMotionFonts();
    usePhoneScreen(tester);
    await tester.pumpWidget(
      const MaterialApp(
        debugShowCheckedModeBanner: false,
        home: SanctaIntro(ready: false, child: ColoredBox(color: Colors.white)),
      ),
    );
    final frames = <int>[0, 300, 600, 900, 1200, 1500];
    var now = 0;
    for (final ms in frames) {
      await tester.pump(Duration(milliseconds: ms - now));
      now = ms;
      await expectLater(find.byType(SanctaIntro), matchesGoldenFile('png/abertura_${ms.toString().padLeft(4, '0')}.png'));
    }
    // Espera (app ainda carregando): a abertura do "n" respira luz — pico ~1,2 s depois.
    for (var i = 0; i < 12; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
    await expectLater(find.byType(SanctaIntro), matchesGoldenFile('png/abertura_espera.png'));
  });
}
