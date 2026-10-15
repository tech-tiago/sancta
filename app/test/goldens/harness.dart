// Infraestrutura dos testes de tela (goldens): roteador real, provedores falsos, fontes reais.
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:sancta/app/router.dart';
import 'package:sancta/core/models/models.dart';
import 'package:sancta/core/providers.dart';
import 'package:sancta/core/state/config_controller.dart';
import 'package:sancta/core/state/content_providers.dart';
import 'package:sancta/core/state/session_controller.dart';
import 'package:sancta/core/theme/app_theme.dart';
import 'package:sancta/core/theme/palette.dart';
import 'package:sancta/core/utils/clock.dart';
import 'package:sancta/features/announcements/announcement_service.dart';
import 'package:sancta/features/notifications/push_service.dart';
import 'package:sancta/features/premium/billing_service.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'fixtures.dart';

/// Domingo, 4 de outubro de 2026, 8h30 — a mesma cena do mockup aprovado.
final fixedNow = DateTime(2026, 10, 4, 8, 30);

bool _fontsLoaded = false;

/// Carrega TODAS as fontes do app (Figtree, EB Garamond e Material Icons) a partir do FontManifest,
/// para que os goldens mostrem o texto real (sem a fonte "Ahem" dos testes).
Future<void> loadAppFonts() async {
  if (_fontsLoaded) return;
  _fontsLoaded = true;
  await initializeDateFormatting('pt_BR');
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

class FakeSession extends SessionController {
  FakeSession(this.initial);

  final SessionState initial;

  @override
  SessionState build() => initial;

  @override
  Future<void> bootstrap() async {}

  @override
  Future<void> refreshUser() async {}
}

class FakeConfig extends ConfigController {
  FakeConfig(this.config);

  final AppConfig config;

  @override
  Future<AppConfig> build() async => config;
}

class FakeBilling extends BillingController {
  @override
  BillingState build() => const BillingState(available: true);
}

class FakePush extends PushService {
  FakePush(super.ref);

  @override
  Future<void> initialize(BuildContext context) async {}
}

class FakeAnnouncements extends AnnouncementService {
  FakeAnnouncements(super.ref);

  @override
  Future<List<Announcement>> fetch() async => const [];

  @override
  Future<void> showPending(BuildContext context) async {}
}

/// Sessão com a usuária "Ana" (assinante opcional) ou visitante.
SessionState sessionFor({bool loggedIn = true, bool premium = false, bool guest = true}) => SessionState(
  initialized: true,
  guest: !loggedIn && guest,
  user: loggedIn ? AppUser.fromJson(userJson(premium: premium)) : null,
);

/// Provedores falsos com os dados de [fixtures.dart].
Future<List<Override>> overridesFor({
  bool loggedIn = true,
  bool premium = false,
  bool guest = true,
  Map<String, dynamic>? home,
  List<Override> extra = const [],
}) async {
  SharedPreferences.setMockInitialValues({});
  final prefs = await SharedPreferences.getInstance();
  return [
    sharedPreferencesProvider.overrideWithValue(prefs),
    clockProvider.overrideWithValue(() => fixedNow),
    sessionProvider.overrideWith(() => FakeSession(sessionFor(loggedIn: loggedIn, premium: premium, guest: guest))),
    configProvider.overrideWith(() => FakeConfig(AppConfig.fromJson(configJson))),
    pushServiceProvider.overrideWith(FakePush.new),
    announcementServiceProvider.overrideWith(FakeAnnouncements.new),
    bannersProvider.overrideWith((ref) async => const []),
    billingProvider.overrideWith(FakeBilling.new),
    homeProvider.overrideWith((ref) async => home ?? homeJson()),
    categoriesProvider.overrideWith((ref) async => categoriesJson.map(Category.fromJson).toList()),
    prayersProvider.overrideWith((ref, category) async => prayersJson.map(PrayerItem.fromJson).toList()),
    journeysProvider.overrideWith((ref) async => journeysJson.map(Journey.fromJson).toList()),
    bibleBooksProvider.overrideWith(
      (ref) async => (version: 'Bíblia Ave-Maria', license: 'Texto bíblico usado com autorização.', books: booksJson.map(BibleBook.fromJson).toList()),
    ),
    readingProgressProvider.overrideWith((ref) async => loggedIn ? {'book': 'João', 'book_slug': 'joao', 'chapter': 3} : null),
    churchesProvider.overrideWith((ref, args) async => churchesJson.map(Church.fromJson).toList()),
    liveProvider.overrideWith((ref) async => liveJson.map(LiveChannel.fromJson).toList()),
    plansProvider.overrideWith((ref) async => plansJson.map(PlanItem.fromJson).toList()),
    statsProvider.overrideWith((ref) async => loggedIn ? statsJson : null),
    newsProvider.overrideWith((ref) async => newsJson.map(NewsItem.fromJson).toList()),
    prayerProvider.overrideWith((ref, slug) async => PrayerItem.fromJson(prayerDetailJson(slug))),
    journeyProvider.overrideWith((ref, slug) async => Journey.fromJson(journeyDetailJson)),
    chapterProvider.overrideWith((ref, args) async => BibleChapter.fromJson(chapterJson)),
    liturgyProvider.overrideWith((ref, date) async => liturgyJson),
    churchProvider.overrideWith((ref, id) async => Church.fromJson(churchDetailJson)),
    faqsProvider.overrideWith(
      (ref) async => const [
        {'question': 'O Sancta é gratuito?', 'answer': 'Sim. Bíblia, liturgia, orações essenciais e o mapa de igrejas são gratuitos.'},
      ],
    ),
    newsDetailProvider.overrideWith((ref, id) async => NewsItem.fromJson(newsDetailJson)),
    pageProvider.overrideWith((ref, slug) async => pageJson),
    annotationsProvider.overrideWith((ref, type) async => annotationsJson),
    intentionsProvider.overrideWith((ref) async => intentionsJson.map(Intention.fromJson).toList()),
    favoritePrayersProvider.overrideWith((ref) async => const []),
    favoriteChurchesProvider.overrideWith((ref) async => const []),
    ...extra,
  ];
}

/// App de teste: mesmo roteador e temas do app real (sem o bloqueio de manutenção).
class TestApp extends ConsumerWidget {
  const TestApp({super.key, required this.dark});

  final bool dark;

  @override
  Widget build(BuildContext context, WidgetRef ref) => MaterialApp.router(
    debugShowCheckedModeBanner: false,
    routerConfig: ref.watch(routerProvider),
    theme: AppTheme.build(SanctaPalette.claro),
    darkTheme: AppTheme.build(SanctaPalette.escuro),
    themeMode: dark ? ThemeMode.dark : ThemeMode.light,
    locale: const Locale('pt', 'BR'),
    supportedLocales: const [Locale('pt', 'BR')],
    localizationsDelegates: const [GlobalMaterialLocalizations.delegate, GlobalWidgetsLocalizations.delegate, GlobalCupertinoLocalizations.delegate],
  );
}

/// Abre o app em [location] numa tela de [width] × [height] (pixels lógicos).
Future<ProviderContainer> pumpApp(
  WidgetTester tester, {
  String location = '/hoje',
  bool dark = false,
  bool loggedIn = true,
  bool premium = false,
  bool guest = true,
  double height = 844,
  double width = 390,
  double textScale = 1,
  Map<String, dynamic>? home,
  List<Override> extra = const [],
}) async {
  await loadAppFonts();
  // Leitura em voz alta (flutter_tts) não tem canal nativo nos testes.
  TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(const MethodChannel('flutter_tts'), (_) async => 1);
  tester.view.physicalSize = Size(width * 2, height * 2);
  tester.view.devicePixelRatio = 2;
  tester.platformDispatcher.textScaleFactorTestValue = textScale;
  addTearDown(tester.view.reset);
  addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);

  final container = ProviderContainer(
    overrides: await overridesFor(loggedIn: loggedIn, premium: premium, guest: guest, home: home, extra: extra),
  );
  addTearDown(container.dispose);
  await tester.pumpWidget(
    UncontrolledProviderScope(
      container: container,
      child: TestApp(dark: dark),
    ),
  );
  await settle(tester);
  if (location != '/hoje') {
    container.read(routerProvider).go(location);
    await settle(tester);
  }
  return container;
}

/// Avança o tempo sem depender de animações terminarem (selo AO VIVO pulsa sem parar).
Future<void> settle(WidgetTester tester) async {
  for (var i = 0; i < 12; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

/// Router atual (para conferir redirecionamentos).
GoRouter routerOf(ProviderContainer container) => container.read(routerProvider);
