import 'dart:ui' show FlutterView;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/brand/brand.dart';
import '../../core/motion/motion.dart';
import '../../core/state/session_controller.dart';
import 'intro_painter.dart';

/// Liga a abertura ao carregamento real do app: fica pronta quando a sessão termina de iniciar
/// (o mesmo sinal que o roteador usa para sair de `/carregando`).
///
/// Uso (em `MaterialApp.builder`): `SanctaIntroGate(child: child)`.
class SanctaIntroGate extends ConsumerWidget {
  const SanctaIntroGate({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) => SanctaIntro(ready: ref.watch(sessionProvider.select((s) => s.initialized)), child: child);
}

/// Abertura animada do Sancta, desenhada **por cima** do app enquanto ele carrega.
///
/// - Primeiro quadro idêntico ao splash nativo (Portal de 110 dp no centro da tela, Azul Manto).
/// - Toca a sequência da [IntroTimeline] (1,5 s) **uma única vez por processo** (abertura a frio).
/// - Nunca atrasa o app: o [child] (roteador) é montado por baixo desde o início e já navega para a
///   primeira tela; se o carregamento terminar antes, a sequência completa uma vez e sai; se demorar,
///   a abertura do "n" respira luz (sem spinner) até [ready].
/// - Saída: fade + leve aproximação (380 ms) revelando a primeira tela, que cresce de 98,5% a 100%.
/// - "Reduzir movimento": sem deslocamentos — fade curto do Portal para o wordmark e saída assim que
///   [ready] (sem tempo mínimo).
/// - Acessível: um único nó "Sancta" (o app por baixo fica fora da árvore semântica até a saída).
class SanctaIntro extends StatefulWidget {
  const SanctaIntro({super.key, required this.ready, required this.child, this.onFinished});

  /// O app terminou de carregar (sessão/configuração) e a primeira tela já pode aparecer.
  final bool ready;

  /// O app (normalmente o `Router` do `MaterialApp`).
  final Widget child;

  /// Chamado quando a abertura sai de cena (a primeira tela fica interativa).
  final VoidCallback? onFinished;

  static bool _played = false;

  /// Se a abertura já tocou neste processo.
  static bool get played => _played;

  /// Permite tocar de novo (só para testes).
  @visibleForTesting
  static void debugReset() => _played = false;

  @override
  State<SanctaIntro> createState() => _SanctaIntroState();
}

class _SanctaIntroState extends State<SanctaIntro> with TickerProviderStateMixin {
  late bool _visible;
  bool _started = false;
  bool _reduced = false;
  bool _exiting = false;

  // `preserve`: com "Remover animações" o Flutter encurta controladores 20×; aqui as durações já são as
  // da versão reduzida (fade curto), então são mantidas como estão.
  late final AnimationController _intro = AnimationController(vsync: this, duration: SanctaMotion.intro, animationBehavior: AnimationBehavior.preserve);
  late final AnimationController _breath = AnimationController(vsync: this, duration: SanctaMotion.breath ~/ 2);
  late final AnimationController _exit = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: IntroTimeline.exit),
    animationBehavior: AnimationBehavior.preserve,
  );

  IntroLayout? _layout;
  (Size, TextScaler, Offset)? _layoutKey;

  @override
  void initState() {
    super.initState();
    _visible = !SanctaIntro._played;
    SanctaIntro._played = true;
    _intro.addStatusListener((status) {
      if (status == AnimationStatus.completed) _advance();
    });
    _exit.addStatusListener((status) {
      if (status == AnimationStatus.completed) _finish();
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_visible || _started) return;
    _started = true;
    _reduced = SanctaMotion.reduced(context);
    if (_reduced) {
      _intro.duration = const Duration(milliseconds: IntroTimeline.reducedCrossfade);
      _exit.duration = const Duration(milliseconds: IntroTimeline.reducedExit);
    }
    _intro.forward();
    _advance(rebuild: false);
  }

  @override
  void didUpdateWidget(SanctaIntro oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.ready != oldWidget.ready) _advance();
  }

  /// Decide o próximo passo: esperar, respirar ou sair.
  void _advance({bool rebuild = true}) {
    if (!_visible || _exiting || !_started) return;
    final sequenceDone = _reduced || _intro.isCompleted;
    if (!widget.ready) {
      if (_intro.isCompleted && !_reduced && !_breath.isAnimating) _breath.repeat(reverse: true);
      return;
    }
    if (!sequenceDone) return; // completa a sequência (uma vez) e então sai
    _exiting = true;
    _exit.forward();
    if (rebuild) setState(() {});
  }

  void _finish() {
    _breath.stop();
    _intro.stop();
    _layout?.dispose();
    _layout = null;
    setState(() => _visible = false);
    widget.onFinished?.call();
  }

  @override
  void dispose() {
    _intro.dispose();
    _breath.dispose();
    _exit.dispose();
    _layout?.dispose();
    super.dispose();
  }

  /// Centro do Portal do splash nativo: o centro da TELA. Quando a janela do Flutter começa no topo
  /// mas não cobre a barra de navegação (Android < 15 sem edge-to-edge), o centro da tela fica até
  /// ~32 dp abaixo do centro da janela.
  static Offset _startCenter(Size size, FlutterView? view) {
    final center = size.center(Offset.zero);
    if (view == null) return center;
    final display = view.display.size / view.devicePixelRatio;
    final missing = display.height - size.height;
    if ((display.width - size.width).abs() < 1 && missing > 0 && missing <= 64) return Offset(center.dx, display.height / 2);
    return center;
  }

  IntroLayout _layoutFor(Size size, TextScaler scaler, FlutterView? view) {
    final key = (size, scaler, _startCenter(size, view));
    if (_layout == null || _layoutKey != key) {
      _layout?.dispose();
      _layout = IntroLayout.compute(size, startCenter: key.$3, textScaler: scaler);
      _layoutKey = key;
    }
    return _layout!;
  }

  @override
  Widget build(BuildContext context) {
    final blocking = _visible && !_exiting;
    final scaler = MediaQuery.textScalerOf(context);
    final view = View.maybeOf(context);
    final app = IgnorePointer(
      ignoring: blocking,
      child: ScaleTransition(
        scale: _visible && !_reduced
            ? _exit.drive(Tween<double>(begin: .985, end: 1).chain(CurveTween(curve: SanctaMotion.enter)))
            : const AlwaysStoppedAnimation(1),
        child: widget.child,
      ),
    );
    return Stack(
      fit: StackFit.expand,
      alignment: Alignment.topLeft,
      children: [
        app,
        if (_visible)
          Positioned.fill(
            child: IgnorePointer(
              ignoring: _exiting,
              child: BlockSemantics(
                blocking: blocking,
                child: FadeTransition(
                  opacity: _exit.drive(Tween<double>(begin: 1, end: 0).chain(CurveTween(curve: Curves.easeOut))),
                  child: ScaleTransition(
                    scale: _reduced
                        ? const AlwaysStoppedAnimation(1)
                        : _exit.drive(Tween<double>(begin: 1, end: 1.03).chain(CurveTween(curve: SanctaMotion.exit))),
                    child: _IntroFrame(layoutFor: (size) => _layoutFor(size, scaler, view), progress: _intro, breath: _breath, reduced: _reduced),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}

/// Um quadro da abertura em tela cheia (Azul Manto, barra de status clara, rótulo "Sancta").
class _IntroFrame extends StatelessWidget {
  const _IntroFrame({required this.layoutFor, required this.progress, required this.breath, required this.reduced});

  final IntroLayout Function(Size size) layoutFor;
  final Animation<double> progress;
  final Animation<double> breath;
  final bool reduced;

  @override
  Widget build(BuildContext context) => AnnotatedRegion<SystemUiOverlayStyle>(
    value: SanctaIntroStill.systemStyle,
    child: Semantics(
      label: 'Sancta',
      image: true,
      container: true,
      child: RepaintBoundary(
        child: LayoutBuilder(
          builder: (context, constraints) => CustomPaint(
            size: constraints.biggest,
            painter: IntroPainter(layout: layoutFor(constraints.biggest), progress: progress, breath: breath, reduced: reduced),
          ),
        ),
      ),
    ),
  );
}

/// Primeiro quadro da abertura, parado (o mesmo do splash nativo). Usado na rota `/carregando`.
class SanctaIntroStill extends StatefulWidget {
  const SanctaIntroStill({super.key});

  /// Barra de status clara sobre o Azul Manto (a barra de navegação fica com o tema do app).
  static const SystemUiOverlayStyle systemStyle = SystemUiOverlayStyle(
    statusBarColor: Color(0x00000000),
    statusBarIconBrightness: Brightness.light,
    statusBarBrightness: Brightness.dark,
  );

  @override
  State<SanctaIntroStill> createState() => _SanctaIntroStillState();
}

class _SanctaIntroStillState extends State<SanctaIntroStill> {
  IntroLayout? _layout;

  @override
  void dispose() {
    _layout?.dispose();
    super.dispose();
  }

  IntroLayout _layoutFor(Size size) {
    if (_layout?.size != size) {
      _layout?.dispose();
      _layout = IntroLayout.compute(size);
    }
    return _layout!;
  }

  @override
  Widget build(BuildContext context) => ColoredBox(
    color: BrandColors.azulManto,
    child: _IntroFrame(layoutFor: _layoutFor, progress: const AlwaysStoppedAnimation(0), breath: const AlwaysStoppedAnimation(0), reduced: false),
  );
}
