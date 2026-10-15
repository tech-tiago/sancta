// Componentes de movimento: montam, animam sem erro, param fora da tela e respeitam "reduzir movimento".
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sancta/core/brand/brand.dart';
import 'package:sancta/core/motion/amen_celebration.dart';
import 'package:sancta/core/motion/live_badge.dart';
import 'package:sancta/core/motion/motion.dart';
import 'package:sancta/core/motion/portal_tab_icon.dart';
import 'package:sancta/core/motion/pressable.dart';
import 'package:sancta/core/motion/skeleton.dart';

Widget _wrap(Widget child, {bool reduced = false, ThemeData? theme}) => MaterialApp(
  theme: theme ?? ThemeData(pageTransitionsTheme: SanctaMotion.pageTransitions),
  home: Builder(
    builder: (context) => MediaQuery(
      data: MediaQuery.of(context).copyWith(disableAnimations: reduced),
      child: Scaffold(body: Center(child: child)),
    ),
  ),
);

Future<void> _wait(WidgetTester tester, int ms) async {
  for (var t = 0; t < ms; t += 50) {
    await tester.pump(const Duration(milliseconds: 50));
  }
}

void main() {
  group('SanctaMotion', () {
    testWidgets('tokens e "reduzir movimento"', (tester) async {
      await tester.pumpWidget(_wrap(const SizedBox(key: Key('n'))));
      final normal = tester.element(find.byKey(const Key('n')));
      expect(SanctaMotion.reduced(normal), isFalse);
      expect(SanctaMotion.of(normal, SanctaMotion.base), SanctaMotion.base);
      expect(SanctaMotion.fadeOf(normal, SanctaMotion.slow), SanctaMotion.slow);

      await tester.pumpWidget(_wrap(const SizedBox(key: Key('r')), reduced: true));
      final reduced = tester.element(find.byKey(const Key('r')));
      expect(SanctaMotion.reduced(reduced), isTrue);
      expect(SanctaMotion.of(reduced, SanctaMotion.base), Duration.zero);
      expect(SanctaMotion.fadeOf(reduced, SanctaMotion.slow), SanctaMotion.reducedFade);

      expect(SanctaMotion.intro, const Duration(milliseconds: 1500));
      expect(SanctaMotion.celebration.inMilliseconds, lessThanOrEqualTo(1800));
      expect(SanctaMotion.standard.transform(1), 1);
      expect(SanctaMotion.enter.transform(0), 0);
    });
  });

  group('transição de página', () {
    testWidgets('passagem: fade + leve subida em 300 ms; volta em 250 ms', (tester) async {
      final nav = GlobalKey<NavigatorState>();
      await tester.pumpWidget(
        MaterialApp(
          navigatorKey: nav,
          theme: ThemeData(platform: TargetPlatform.android, pageTransitionsTheme: SanctaMotion.pageTransitions),
          home: const Scaffold(body: Text('A')),
        ),
      );
      nav.currentState!.push(MaterialPageRoute<void>(builder: (_) => const Scaffold(body: Text('B'))));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 120));
      final mid = tester.widget<FadeTransition>(find.ancestor(of: find.text('B'), matching: find.byType(FadeTransition)).first);
      expect(mid.opacity.value, inExclusiveRange(0, 1), reason: 'no meio da passagem o conteúdo está surgindo');
      await tester.pump(const Duration(milliseconds: 200));
      expect(find.text('B'), findsOneWidget);
      expect(find.text('A'), findsNothing, reason: 'rota opaca terminou: a anterior sai da tela');
      nav.currentState!.pop();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 260));
      expect(find.text('B'), findsNothing);
      expect(tester.takeException(), isNull);
    });
  });

  group('SanctaSkeleton', () {
    testWidgets('brilho anima, ocupa a largura disponível e para com "reduzir movimento"', (tester) async {
      await tester.pumpWidget(_wrap(const SizedBox(width: 300, child: Column(children: [SanctaSkeleton(height: 16), SanctaSkeleton(width: 120)]))));
      expect(tester.getSize(find.byType(SanctaSkeleton).first), const Size(300, 16));
      expect(tester.getSize(find.byType(SanctaSkeleton).last), const Size(120, 16));
      for (var i = 0; i < 10; i++) {
        await tester.pump(const Duration(milliseconds: 160));
      }
      expect(tester.hasRunningAnimations, isTrue);
      expect(tester.takeException(), isNull);

      await tester.pumpWidget(_wrap(const SanctaSkeleton(width: 120), reduced: true));
      await tester.pump(const Duration(milliseconds: 100));
      expect(tester.hasRunningAnimations, isFalse);
    });

    testWidgets('fora da tela (TickerMode desligado) não gasta quadros', (tester) async {
      await tester.pumpWidget(_wrap(const TickerMode(enabled: false, child: SanctaSkeleton(width: 120))));
      await tester.pump(const Duration(milliseconds: 100));
      expect(tester.hasRunningAnimations, isFalse);
    });

    testWidgets('tema escuro e altura infinita (preenche o pai)', (tester) async {
      await tester.pumpWidget(
        _wrap(
          const SizedBox(
            width: 200,
            height: 120,
            child: SanctaSkeleton(height: double.infinity, radius: BorderRadius.zero),
          ),
          theme: ThemeData(brightness: Brightness.dark),
        ),
      );
      expect(tester.getSize(find.byType(SanctaSkeleton)), const Size(200, 120));
      await tester.pump(const Duration(milliseconds: 500));
      expect(tester.takeException(), isNull);
    });
  });

  group('LiveBadge', () {
    testWidgets('ponto pulsa sem parar enquanto visível; texto inalterado', (tester) async {
      await tester.pumpWidget(_wrap(const Column(mainAxisSize: MainAxisSize.min, children: [LiveBadge(), LiveBadge(compact: true)])));
      expect(find.text('AO VIVO'), findsNWidgets(2));
      await tester.pump(const Duration(milliseconds: 800));
      expect(tester.hasRunningAnimations, isTrue);
      expect(tester.takeException(), isNull);
    });

    testWidgets('"reduzir movimento": ponto estático', (tester) async {
      await tester.pumpWidget(_wrap(const LiveBadge(), reduced: true));
      await tester.pump(const Duration(milliseconds: 100));
      expect(tester.hasRunningAnimations, isFalse);
    });

    testWidgets('fora da tela não anima', (tester) async {
      await tester.pumpWidget(_wrap(const TickerMode(enabled: false, child: LiveBadge())));
      await tester.pump(const Duration(milliseconds: 100));
      expect(tester.hasRunningAnimations, isFalse);
    });
  });

  group('Pressable', () {
    double scaleOf(WidgetTester tester) =>
        tester.widget<ScaleTransition>(find.descendant(of: find.byType(Pressable), matching: find.byType(ScaleTransition))).scale.value;

    testWidgets('afunda ao pressionar, volta ao soltar e chama onTap uma vez', (tester) async {
      var taps = 0;
      await tester.pumpWidget(
        _wrap(
          Pressable(
            onTap: () => taps++,
            child: const SizedBox(width: 120, height: 80, child: Text('Cartão')),
          ),
        ),
      );
      final gesture = await tester.startGesture(tester.getCenter(find.text('Cartão')));
      await tester.pump(const Duration(milliseconds: 150)); // passa do kPressTimeout
      await tester.pump(const Duration(milliseconds: 100));
      expect(scaleOf(tester), closeTo(.97, .001));
      await gesture.up();
      await _wait(tester, 300);
      expect(scaleOf(tester), 1);
      expect(taps, 1);
    });

    testWidgets('toque rápido mostra o afundar completo antes de voltar', (tester) async {
      await tester.pumpWidget(
        _wrap(
          Pressable(
            onTap: () {},
            child: const SizedBox(width: 120, height: 80, child: Text('Cartão')),
          ),
        ),
      );
      await tester.tap(find.text('Cartão'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 90));
      expect(scaleOf(tester), lessThan(.975));
      await _wait(tester, 300);
      expect(scaleOf(tester), 1);
    });

    testWidgets('háptico opcional e "reduzir movimento" sem escala', (tester) async {
      final calls = <MethodCall>[];
      tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
        calls.add(call);
        return null;
      });
      addTearDown(() => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, null));
      await tester.pumpWidget(
        _wrap(
          Pressable(
            haptic: true,
            onTap: () {},
            child: const SizedBox(width: 120, height: 80, child: Text('X')),
          ),
          reduced: true,
        ),
      );
      await tester.tap(find.text('X'));
      await tester.pump(const Duration(milliseconds: 100));
      expect(scaleOf(tester), 1);
      expect(calls.where((c) => c.method == 'HapticFeedback.vibrate'), isNotEmpty);
    });

    testWidgets('sem onTap: desabilitado, sem semântica de toque', (tester) async {
      final handle = tester.ensureSemantics();
      await tester.pumpWidget(_wrap(const Pressable(child: Text('Desabilitado'))));
      expect(tester.getSemantics(find.text('Desabilitado')), isNot(matchesSemantics(hasTapAction: true)));
      handle.dispose();
    });
  });

  group('PortalTabIcon', () {
    testWidgets('contorno → Portal cheio com a cruz acesa em 250 ms (e volta)', (tester) async {
      Widget icon(bool selected) => _wrap(PortalTabIcon(selected: selected, color: selected ? BrandColors.azulManto : BrandColors.tintaApagada, size: 48));
      await tester.pumpWidget(icon(false));
      PortalTabPainter painter() =>
          tester.widget<CustomPaint>(find.descendant(of: find.byType(PortalTabIcon), matching: find.byType(CustomPaint))).painter! as PortalTabPainter;
      expect(painter().selection.value, 0);
      await tester.pumpWidget(icon(true));
      await tester.pump(const Duration(milliseconds: 100));
      expect(painter().selection.value, inExclusiveRange(0, 1));
      await _wait(tester, 250);
      expect(painter().selection.value, 1);
      expect(tester.hasRunningAnimations, isFalse);
      await tester.pumpWidget(icon(false));
      await _wait(tester, 300);
      expect(painter().selection.value, 0);
    });

    testWidgets('"reduzir movimento": troca na hora', (tester) async {
      await tester.pumpWidget(_wrap(const PortalTabIcon(selected: false, color: Colors.grey), reduced: true));
      await tester.pumpWidget(_wrap(const PortalTabIcon(selected: true, color: Colors.blue), reduced: true));
      await tester.pump();
      expect(tester.hasRunningAnimations, isFalse);
    });

    test('preenchido = o arco oficial; contorno = mesma silhueta com traço fino', () {
      const size = Size(48, 50);
      final full = portalArchAt(size, 1);
      final official = PortalGeometry.letter.arch(size);
      expect(full.getBounds(), official.getBounds());
      final bounds = official.getBounds();
      for (var x = bounds.left + .5; x < bounds.right; x += 1.7) {
        for (var y = bounds.top + .5; y < bounds.bottom; y += 1.9) {
          expect(full.contains(Offset(x, y)), official.contains(Offset(x, y)), reason: '($x, $y)');
        }
      }
      final outline = portalArchAt(size, 0, strokeWidth: 2);
      expect(outline.getBounds(), official.getBounds(), reason: 'a silhueta externa não muda');
      // No contorno, o meio da haste esquerda está vazio (só o traço de 2 px na borda).
      final stemMiddle = Offset(bounds.left + PortalGeometry.stem * PortalGeometry.letter.scaleFor(size) / 2, bounds.bottom - 4);
      expect(official.contains(stemMiddle), isTrue);
      expect(outline.contains(stemMiddle), isFalse);
    });
  });

  group('Amém', () {
    testWidgets('celebra (Portal aceso, "Amém", sequência), com háptico, e termina em ≤ 1,8 s', (tester) async {
      final calls = <MethodCall>[];
      tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
        calls.add(call);
        return null;
      });
      addTearDown(() => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, null));
      var done = false;
      await tester.pumpWidget(
        _wrap(
          Builder(
            builder: (context) =>
                TextButton(onPressed: () => showAmenCelebration(context, streakDays: 7).then((_) => done = true), child: const Text('Concluir')),
          ),
        ),
      );
      await tester.tap(find.text('Concluir'));
      await _wait(tester, 900);
      expect(find.text('Amém'), findsOneWidget);
      expect(find.text('7 dias seguidos'), findsOneWidget);
      expect(calls.where((c) => c.method == 'HapticFeedback.vibrate'), isNotEmpty);
      expect(done, isFalse);
      await _wait(tester, 950);
      expect(done, isTrue);
      expect(find.text('Amém'), findsNothing);
      expect(tester.hasRunningAnimations, isFalse);
      expect(tester.takeException(), isNull);
    });

    testWidgets('um toque dispensa na hora; a tela por baixo continua navegável', (tester) async {
      var done = false;
      final nav = GlobalKey<NavigatorState>();
      await tester.pumpWidget(
        MaterialApp(
          navigatorKey: nav,
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(onPressed: () => showAmenCelebration(context).then((_) => done = true), child: const Text('Concluir')),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Concluir'));
      await _wait(tester, 300);
      await tester.tapAt(const Offset(20, 20));
      await _wait(tester, 250);
      expect(done, isTrue);
      expect(find.text('Amém'), findsNothing);
      // Nenhuma rota foi empilhada pela celebração.
      expect(nav.currentState!.canPop(), isFalse);
    });

    testWidgets('"reduzir movimento": estática, fade curto, termina sozinha (1,4 s)', (tester) async {
      // A celebração mora no Overlay raiz: a preferência vem do sistema (MediaQuery do MaterialApp).
      tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
      addTearDown(tester.platformDispatcher.clearAccessibilityFeaturesTestValue);
      var done = false;
      await tester.pumpWidget(
        _wrap(
          Builder(
            builder: (context) => TextButton(onPressed: () => showAmenCelebration(context, streakDays: 1).then((_) => done = true), child: const Text('Ok')),
          ),
        ),
      );
      await tester.tap(find.text('Ok'));
      await _wait(tester, 200);
      final pill = tester.widget<Opacity>(find.ancestor(of: find.text('Primeiro dia da sequência'), matching: find.byType(Opacity)).first);
      expect(pill.opacity, 1, reason: 'sem subida nem entrada escalonada: tudo aparece junto');
      await _wait(tester, 1300);
      expect(done, isTrue);
      expect(tester.hasRunningAnimations, isFalse);
    });

    testWidgets('duas conclusões seguidas não empilham celebrações', (tester) async {
      late BuildContext ctx;
      await tester.pumpWidget(
        _wrap(
          Builder(
            builder: (c) {
              ctx = c;
              return const Text('x');
            },
          ),
        ),
      );
      var first = false;
      showAmenCelebration(ctx).then((_) => first = true);
      await tester.pump(const Duration(milliseconds: 100));
      showAmenCelebration(ctx);
      await tester.pump(const Duration(milliseconds: 100));
      expect(first, isTrue, reason: 'a anterior é encerrada');
      expect(find.text('Amém'), findsOneWidget);
      await _wait(tester, 1900);
    });

    test('texto da sequência', () {
      expect(amenStreakLabel(null), '');
      expect(amenStreakLabel(0), '');
      expect(amenStreakLabel(1), 'Primeiro dia da sequência');
      expect(amenStreakLabel(12), '12 dias seguidos');
    });
  });
}
