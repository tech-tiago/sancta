import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// Famílias tipográficas embutidas (declaradas no pubspec.yaml; nada é baixado em tempo de execução).
abstract final class BrandFonts {
  /// Figtree (OFL): 700 marca/títulos · 500 interface · 400 texto · 600 opcional · 800 destaques.
  static const String figtree = 'Figtree';

  /// EB Garamond (OFL): opção de leitura "Clássica" (400/500). Cormorant Garamond é proibida.
  static const String ebGaramond = 'EB Garamond';
}

bool _registered = false;

/// Registra as licenças OFL das fontes embutidas na tela de licenças do app (chame uma vez no `main`).
void registerBrandFontLicenses() {
  if (_registered) return;
  _registered = true;
  LicenseRegistry.addLicense(() async* {
    yield LicenseEntryWithLineBreaks(const ['Figtree'], await rootBundle.loadString('assets/fonts/OFL-Figtree.txt'));
    yield LicenseEntryWithLineBreaks(const ['EB Garamond'], await rootBundle.loadString('assets/fonts/OFL-EBGaramond.txt'));
  });
}
