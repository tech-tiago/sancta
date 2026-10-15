import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:home_widget/home_widget.dart';

import '../../core/utils/links.dart';

/// Toques no widget "Oração do dia": o Android abre o app com a ação do home_widget e o deep link
/// `sancta://` no `data` da Intent. Aqui o link é VALIDADO (só rotas internas permitidas) antes
/// de navegar; qualquer outro valor é ignorado.
abstract final class WidgetLaunch {
  static StreamSubscription<Uri?>? _clicks;

  /// Rota interna para um link recebido do widget (ou `null` se não for permitido).
  static String? routeFor(Uri? uri) => uri == null ? null : deepLinkToRoute(uri.toString());

  /// Trata a abertura inicial (app fechado) e os toques seguintes (app em segundo plano).
  static Future<void> attach(void Function(String route) open) async {
    if (kIsWeb || defaultTargetPlatform != TargetPlatform.android) return;
    try {
      _clicks ??= HomeWidget.widgetClicked.listen((uri) {
        final route = routeFor(uri);
        if (route != null) open(route);
      });
      final route = routeFor(await HomeWidget.initiallyLaunchedFromHomeWidget());
      if (route != null) open(route);
    } catch (e) {
      debugPrint('Widget: abertura não tratada ($e)');
    }
  }
}
