// Utilidades dos testes de movimento: fontes reais (Figtree) e tela de celular.
import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

bool _fontsLoaded = false;

/// Carrega as fontes declaradas no pubspec (Figtree, EB Garamond, Material Icons) para os goldens.
Future<void> loadMotionFonts() async {
  if (_fontsLoaded) return;
  _fontsLoaded = true;
  final manifest = jsonDecode(await rootBundle.loadString('FontManifest.json')) as List;
  for (final entry in manifest.cast<Map<String, dynamic>>()) {
    final family = entry['family'] as String;
    final loader = FontLoader(family.startsWith('packages/') ? family.split('/').last : family);
    for (final font in (entry['fonts'] as List).cast<Map<String, dynamic>>()) {
      loader.addFont(rootBundle.load(font['asset'] as String));
    }
    await loader.load();
  }
}

/// Tela de 390 × 844 dp (densidade 2), como os goldens das telas.
void usePhoneScreen(WidgetTester tester, {double width = 390, double height = 844}) {
  tester.view.physicalSize = Size(width * 2, height * 2);
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);
}
