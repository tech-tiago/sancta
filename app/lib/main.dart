import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'app/app.dart';
import 'core/brand/brand.dart';
import 'core/providers.dart';
import 'core/theme/system_bars.dart';

/// Mensagens recebidas com o app fechado (o sistema já exibe a notificação).
@pragma('vm:entry-point')
Future<void> _firebaseBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);

  // Edge-to-edge em todas as versões do Android (o 15+ já impõe): o app desenha por trás das barras
  // transparentes do sistema e cada tela respeita SafeArea/insets. Ícones das barras: core/theme/system_bars.dart.
  await SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  SystemChrome.setSystemUIOverlayStyle(edgeToEdgeStyle(Brightness.dark));

  // Fontes embutidas (Figtree e EB Garamond, OFL): nada é baixado em tempo de execução.
  registerBrandFontLicenses();

  await initializeDateFormatting('pt_BR');

  try {
    await Firebase.initializeApp();
    FirebaseMessaging.onBackgroundMessage(_firebaseBackgroundHandler);
  } catch (e) {
    // Sem google-services.json (ex.: build de desenvolvimento): o app funciona sem push.
    debugPrint('Firebase indisponível: $e');
  }

  final prefs = await SharedPreferences.getInstance();

  if (kReleaseMode) {
    // Em produção, erros de interface não exibem detalhes técnicos ao usuário.
    ErrorWidget.builder = (_) => const Center(child: Icon(Icons.error_outline_rounded));
  }

  runApp(ProviderScope(overrides: [sharedPreferencesProvider.overrideWithValue(prefs)], child: const SanctaApp()));
}
