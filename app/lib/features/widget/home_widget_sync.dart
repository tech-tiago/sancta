import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:home_widget/home_widget.dart';

import '../../core/utils/links.dart';
import 'widget_content.dart';

export 'widget_content.dart' show WidgetContent, WidgetEntry, WidgetPeriod;

/// Atualiza o widget Android "Oração do dia" com os dados da tela Hoje.
/// Interface estável: a tela Hoje chama `HomeWidgetSync.update(home)` (sem aguardar; erros são
/// ignorados pelo chamador) sempre que os dados de /home carregam.
abstract final class HomeWidgetSync {
  /// Chave única (JSON) em `HomeWidgetPreferences`, lida pelo `OracaoDoDiaWidgetProvider`.
  static const dataKey = 'sancta_widget';

  /// Classe do AppWidgetProvider (Kotlin).
  static const androidProvider = 'com.maria.sancta.OracaoDoDiaWidgetProvider';

  static String? _lastSaved;

  static bool get _supported => !kIsWeb && defaultTargetPlatform == TargetPlatform.android;

  static Future<void> update(Map<String, dynamic> home) async {
    if (!_supported) return;
    final encoded = WidgetContent.fromHome(home, DateTime.now()).encode();
    if (encoded == _lastSaved) return; // nada mudou: evita redesenhar o widget à toa
    await HomeWidget.saveWidgetData<String>(dataKey, encoded);
    await HomeWidget.updateWidget(qualifiedAndroidName: androidProvider);
    _lastSaved = encoded;
  }

  /// Conteúdo salvo por último (ou `null`).
  static Future<WidgetContent?> saved() async {
    if (!_supported) return null;
    try {
      return WidgetContent.decode(await HomeWidget.getWidgetData<String>(dataKey));
    } catch (_) {
      return null;
    }
  }

  /// Rota da oração de um período conhecida pela última sincronização (usada pelos lembretes).
  static Future<String?> routeFor(WidgetPeriod period) async => deepLinkToRoute((await saved())?.periods[period]?.link);
}
