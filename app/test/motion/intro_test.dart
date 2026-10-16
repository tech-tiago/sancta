// Abertura animada: sequência, espera calma, saída quando o app está pronto, "reduzir movimento",
// abertura só a frio, navegação real com o roteador e fidelidade ao wordmark oficial.
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sancta/core/brand/brand.dart';
import 'package:sancta/core/state/session_controller.dart';
import 'package:sancta/features/splash/intro_painter.dart';
import 'package:sancta/features/splash/splash_screen.dart';
import 'package:sancta/features/splash/wordmark_layers.dart';

/// App de teste com estado próprio (para conferir que a abertura não recria o app por baixo).
class _Counter extends StatefulWidget {
  const _Counter();

  @override
  State<_Counter> createState() => _CounterState();
}

class _CounterState extends State<_Counter> {
  int taps = 0;

  @override
  Widget build(BuildContext context) => Center(
    child: TextButton(onPressed: () => setState(() => taps++), child: Text('APP $taps')),
  );
}

Widget _host({required bool ready, VoidCallback? onFinished, bool reduced = false}) => MaterialApp(
  home: MediaQuery(
    data: MediaQueryData(size: const Size(390, 844), disableAnimations: reduced),
    child: SanctaIntro(
      ready: ready,
      onFinished: onFinished,
      child: const Scaffold(body: _Counter()),
    ),
  ),
);

/// Quadros da abertura na tela (a camada por cima do app e o quadro parado de /carregando).
Finder get _introFrames => find.byWidgetPredicate((w) => w is CustomPaint && w.painter is IntroPainter);

Finder get _overlay => find.descendant(of: find.byType(SanctaIntro), matching: _introFrames).first;

/// Avança o relógio em passos de 50 ms (como quadros reais), para encadear animações.
Future<void> _wait(WidgetTester tester, int ms) async {
  for (var t = 0; t < ms; t += 50) {
    await tester.pump(const Duration(milliseconds: 50));
  }
}

void main() {
  setUp(SanctaIntro.debugReset);

  group('sequência', () {
    testWidgets('pronto antes do fim: completa a sequência uma vez (1,5 s) e então sai (380 ms)', (tester) async {
      final semantics = tester.ensureSemantics();
      var finished = 0;
      await tester.pumpWidget(_host(ready: true, onFinished: () => finished++));
      expect(_overlay, findsOneWidget);
      expect(find.bySemanticsLabel('Sancta'), findsOneWidget);
      expect(find.bySemanticsLabel('APP 0'), findsNothing, reason: 'o app por baixo fica fora da árvore semântica');

      await tester.pump(const Duration(milliseconds: 1400));
      expect(finished, 0, reason: 'não corta a sequência no meio');
      await tester.pump(const Duration(milliseconds: 100)); // fim da sequência → começa a saída
      await _wait(tester, 300);
      expect(finished, 0, reason: 'saída em andamento');
      await _wait(tester, 150);
      expect(finished, 1);
      expect(_introFrames, findsNothing);
      expect(tester.hasRunningAnimations, isFalse);
      expect(tester.takeException(), isNull);
      semantics.dispose();
    });

    testWidgets('carregamento demorado: luz respirando (sem spinner) até ficar pronto, depois sai', (tester) async {
      var finished = 0;
      await tester.pumpWidget(_host(ready: false, onFinished: () => finished++));
      for (var i = 0; i < 40; i++) {
        await tester.pump(const Duration(milliseconds: 100)); // 4 s
      }
      expect(finished, 0);
      expect(find.byType(CircularProgressIndicator), findsNothing);
      final painter = tester.widget<CustomPaint>(_overlay).painter! as IntroPainter;
      expect(painter.progress.value, 1);
      expect(painter.breath.isAnimating, isTrue, reason: 'a abertura do "n" respira enquanto espera');

      await tester.pumpWidget(_host(ready: true, onFinished: () => finished++));
      await _wait(tester, 400);
      expect(finished, 1, reason: 'sai logo que fica pronto (sem esperar o ciclo de luz)');
      expect(_introFrames, findsNothing);
      expect(tester.hasRunningAnimations, isFalse, reason: 'nenhum ticker sobra depois da abertura');
    });

    testWidgets('o app por baixo é montado desde o início e mantém o estado; toques só depois da abertura', (tester) async {
      await tester.pumpWidget(_host(ready: true));
      expect(find.text('APP 0', skipOffstage: false), findsOneWidget);
      await tester.tap(find.text('APP 0'), warnIfMissed: false);
      await tester.pump();
      expect(find.text('APP 0'), findsOneWidget, reason: 'a abertura bloqueia toques enquanto está na tela');

      await _wait(tester, 1500 + 400);
      await tester.tap(find.text('APP 0'));
      await tester.pump();
      expect(find.text('APP 1'), findsOneWidget);
    });

    testWidgets('toca só uma vez por processo (abertura a frio)', (tester) async {
      await tester.pumpWidget(_host(ready: true));
      await _wait(tester, 1500 + 400);
      expect(SanctaIntro.played, isTrue);

      // Recriar o app (ex.: troca de tema, reconstrução do MaterialApp) não toca de novo.
      await tester.pumpWidget(const SizedBox());
      await tester.pumpWidget(_host(ready: false));
      expect(_introFrames, findsNothing);
      expect(find.text('APP 0'), findsOneWidget);
    });
  });

  group('reduzir movimento', () {
    testWidgets('sem tempo mínimo: só um fade curto e sai assim que pronto', (tester) async {
      var finished = 0;
      await tester.pumpWidget(_host(ready: true, reduced: true, onFinished: () => finished++));
      await tester.pump(const Duration(milliseconds: 100));
      await tester.pump(const Duration(milliseconds: 150));
      expect(finished, 1, reason: '≤ 250 ms em vez de 1,9 s');
      expect(tester.hasRunningAnimations, isFalse);
    });

    testWidgets('esperando: estático (nenhuma animação contínua)', (tester) async {
      await tester.pumpWidget(_host(ready: false, reduced: true));
      await tester.pump(const Duration(milliseconds: 300));
      expect(tester.hasRunningAnimations, isFalse);
      final painter = tester.widget<CustomPaint>(_overlay).painter! as IntroPainter;
      expect(painter.reduced, isTrue);
      expect(painter.breath.isAnimating, isFalse);
    });
  });

  group('integração com o roteador', () {
    testWidgets('a navegação acontece por baixo e a primeira tela aparece quando a sessão fica pronta', (tester) async {
      final semantics = tester.ensureSemantics();
      final container = ProviderContainer(overrides: [sessionProvider.overrideWith(_TestSession.new)]);
      addTearDown(container.dispose);
      final router = GoRouter(
        initialLocation: '/carregando',
        refreshListenable: _SessionListenable(container),
        redirect: (context, state) {
          final ready = container.read(sessionProvider).initialized;
          if (!ready) return state.matchedLocation == '/carregando' ? null : '/carregando';
          return state.matchedLocation == '/carregando' ? '/hoje' : null;
        },
        routes: [
          GoRoute(path: '/carregando', builder: (_, _) => const SplashScreen()),
          GoRoute(
            path: '/hoje',
            builder: (_, _) => const Scaffold(body: Center(child: Text('Hoje'))),
          ),
        ],
      );
      addTearDown(router.dispose);
      await tester.pumpWidget(
        UncontrolledProviderScope(
          container: container,
          child: MaterialApp.router(
            routerConfig: router,
            builder: (context, child) => SanctaIntroGate(child: child!),
          ),
        ),
      );
      expect(router.routeInformationProvider.value.uri.path, '/carregando');
      expect(find.byType(SanctaIntroStill), findsOneWidget, reason: 'rota /carregando = primeiro quadro, parado');

      await tester.pump(const Duration(milliseconds: 500));
      (container.read(sessionProvider.notifier) as _TestSession).finishBootstrap();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      expect(router.routeInformationProvider.value.uri.path, '/hoje', reason: 'o roteador já navegou por baixo da abertura');

      await _wait(tester, 900 + 450); // fim da sequência + saída
      expect(find.text('Hoje'), findsOneWidget);
      expect(_introFrames, findsNothing);
      expect(find.bySemanticsLabel('Sancta'), findsNothing);
      expect(tester.takeException(), isNull);
      semantics.dispose();
    });
  });

  group('fidelidade à marca', () {
    test('as letras animadas são exatamente os contornos do wordmark oficial', () {
      final svg = File('assets/brand/wordmark_pergaminho.svg').readAsStringSync();
      final d = RegExp(r'<path fill="#FBF6EC" d="([^"]+)"').firstMatch(svg)!.group(1)!;
      final subpaths = d.split(RegExp(r'(?=M)'));
      final letters = WordmarkGlyphs.letters.map((l) => l.$2).join();
      expect(subpaths.take(7).join(), letters);
      expect(subpaths.last, startsWith('M${WordmarkGlyphs.portalLeft} ${WordmarkGlyphs.baseline.toInt()}V'));
      expect(svg, contains('viewBox="0 0 ${WordmarkGlyphs.width} ${WordmarkGlyphs.height.toInt()}"'));
    });

    test('o Portal no wordmark tem as medidas de PortalGeometry.letter', () {
      expect(WordmarkGlyphs.baseline - WordmarkGlyphs.portalTop, PortalGeometry.letter.height);
      final layout = IntroLayout.compute(const Size(390, 844));
      expect(layout.portalEnd.width / layout.portalEnd.height, closeTo(PortalGeometry.letter.aspectRatio, 1e-9));
      expect(layout.portalStart.height, 110, reason: 'mesmo tamanho do splash nativo');
      expect(layout.portalStart.center, const Offset(195, 422), reason: 'centro da tela, como o splash nativo');
      layout.dispose();
    });

    test('o splash nativo usa o mesmo Portal (110 dp, centrado) — launch_background e v31', () {
      final launch = File('android/app/src/main/res/drawable/launch_background.xml').readAsStringSync();
      expect(launch, contains('@drawable/sancta_portal'));
      expect(launch, contains('android:height="110dp"'));
      expect(launch, contains('android:gravity="center"'));
      final v31 = File('android/app/src/main/res/values-v31/styles.xml').readAsStringSync();
      expect(v31, contains('@drawable/sancta_portal_splash'));
      final splash = File('android/app/src/main/res/drawable/sancta_portal_splash.xml').readAsStringSync();
      // Portal de 110 dp de altura (de y = 89 a 199) centrado no ícone de 288 dp.
      expect(splash, contains('M89 199V144C89 113.063 113.063 89 133.688 89H154.313'));
    });

    test('parser de contornos: letras com as caixas esperadas', () {
      final s = WordmarkLayers.letters.first;
      expect(s.char, 'S');
      final b = s.path.getBounds();
      expect(b.left, closeTo(0, .5));
      expect(b.right, closeTo(526, .5));
      expect(b.top, closeTo(0, .5));
      expect(b.bottom, closeTo(724, .5));
      expect(WordmarkLayers.letters.map((l) => l.char).join(), 'Sacta');
      expect(() => parseSvgPath('M0 0A1 1 0 0 1 2 2'), throwsFormatException);
    });
  });
}

class _TestSession extends SessionController {
  @override
  SessionState build() => const SessionState();

  @override
  Future<void> bootstrap() async {}

  void finishBootstrap() => state = const SessionState(guest: true, initialized: true);
}

class _SessionListenable extends ChangeNotifier {
  _SessionListenable(ProviderContainer container) {
    container.listen(sessionProvider, (_, _) => notifyListeners());
  }
}
