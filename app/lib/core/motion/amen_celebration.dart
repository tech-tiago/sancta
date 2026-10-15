import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../brand/brand.dart';
import 'lit_portal.dart';
import 'motion.dart';

/// Celebração ao concluir uma oração ("Amém"): o Portal acende por dentro, a cruz brilha, raios
/// dourados sutis se abrem, aparece "Amém" e, se houver, a sequência de dias.
///
/// - Curta: 1,75 s ([SanctaMotion.celebration]); um toque em qualquer lugar dispensa na hora.
/// - Não bloqueia a navegação: é uma camada no `Overlay` raiz (não empilha rota); o `Future` termina
///   quando a celebração sai de cena, e o botão voltar continua funcionando normalmente.
/// - Háptico: um toque médio quando a porta acende (+ um clique leve quando a sequência aparece).
/// - "Reduzir movimento": composição estática com fade curto (sem raios, escala ou subida).
/// - Leitores de tela: anuncia "Amém" e a sequência (região dinâmica).
///
/// Interface estável: `await showAmenCelebration(context, streakDays: n)`.
Future<void> showAmenCelebration(BuildContext context, {int? streakDays}) {
  final overlay = Overlay.maybeOf(context, rootOverlay: true);
  if (overlay == null) return Future<void>.value();
  _active?.call(false); // nunca duas celebrações ao mesmo tempo

  final done = Completer<void>();
  late final OverlayEntry entry;
  var closed = false;
  void close(bool disposing) {
    if (closed) return;
    closed = true;
    if (identical(_active, close)) _active = null;
    if (!disposing) {
      entry.remove();
      entry.dispose();
    }
    if (!done.isCompleted) done.complete();
  }

  entry = OverlayEntry(
    builder: (_) => AmenCelebration(streakDays: streakDays, onDone: () => close(false), onDisposed: () => close(true)),
  );
  _active = close;
  overlay.insert(entry);
  return done.future;
}

void Function(bool disposing)? _active;

/// Texto da sequência de dias ("" quando não há sequência).
String amenStreakLabel(int? days) => switch (days) {
  null || <= 0 => '',
  1 => 'Primeiro dia da sequência',
  _ => '$days dias seguidos',
};

/// A camada da celebração (exposta para testes; use [showAmenCelebration]).
class AmenCelebration extends StatefulWidget {
  const AmenCelebration({super.key, this.streakDays, required this.onDone, this.onDisposed});

  final int? streakDays;

  /// Chamado ao terminar (sozinha ou por toque).
  final VoidCallback onDone;

  /// Chamado se a camada for desmontada antes de terminar (ex.: o app fechou).
  final VoidCallback? onDisposed;

  @override
  State<AmenCelebration> createState() => _AmenCelebrationState();
}

class _AmenCelebrationState extends State<AmenCelebration> with TickerProviderStateMixin {
  // `preserve`: com "Remover animações" o Flutter encurtaria a celebração 20× (70 ms, ilegível); a
  // versão reduzida já é estática, então o tempo de leitura é mantido.
  late final AnimationController _main = AnimationController(vsync: this, duration: SanctaMotion.celebration, animationBehavior: AnimationBehavior.preserve);
  late final AnimationController _dismiss = AnimationController(vsync: this, duration: SanctaMotion.fast, animationBehavior: AnimationBehavior.preserve);
  bool _reduced = false;
  bool _started = false;
  bool _buzzed = false;
  bool _clicked = false;
  bool _done = false;

  static const _total = 1750.0;
  static const _reducedTotal = 1400.0;

  String get _streak => amenStreakLabel(widget.streakDays);

  @override
  void initState() {
    super.initState();
    _main.addListener(_haptics);
    _main.addStatusListener((s) {
      if (s == AnimationStatus.completed) _finish();
    });
    _dismiss.addStatusListener((s) {
      if (s == AnimationStatus.completed) _finish();
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    _reduced = SanctaMotion.reduced(context);
    if (_reduced) _main.duration = Duration(milliseconds: _reducedTotal.toInt());
    _main.forward();
  }

  void _haptics() {
    final ms = _main.value * (_reduced ? _reducedTotal : _total);
    if (!_buzzed && (_reduced || ms >= 200)) {
      _buzzed = true;
      HapticFeedback.mediumImpact();
    }
    if (!_clicked && !_reduced && _streak.isNotEmpty && ms >= 560) {
      _clicked = true;
      HapticFeedback.selectionClick();
    }
  }

  void _finish() {
    if (_done) return;
    _done = true;
    widget.onDone();
  }

  void _onTap() {
    if (_done || _dismiss.isAnimating) return;
    _main.stop();
    _dismiss.forward();
  }

  @override
  void dispose() {
    if (!_done) widget.onDisposed?.call();
    _main.dispose();
    _dismiss.dispose();
    super.dispose();
  }

  static double _iv(double t, double start, double end) => ((t - start) / (end - start)).clamp(0.0, 1.0);

  @override
  Widget build(BuildContext context) {
    final streak = _streak;
    return Semantics(
      liveRegion: true,
      container: true,
      label: streak.isEmpty ? 'Amém' : 'Amém. $streak de oração.',
      onTapHint: 'fechar',
      onTap: _onTap,
      child: ExcludeSemantics(
        child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: _onTap,
          child: Material(
            type: MaterialType.transparency,
            child: AnimatedBuilder(animation: Listenable.merge([_main, _dismiss]), builder: (context, _) => _frame(context, streak)),
          ),
        ),
      ),
    );
  }

  Widget _frame(BuildContext context, String streak) {
    final out = 1 - Curves.easeOut.transform(_dismiss.value);
    final double scrim, portalIn, portalScale, light, rise, glow, rays, amen, streakIn, exit;
    if (_reduced) {
      final ms = _main.value * _reducedTotal;
      exit = Curves.easeIn.transform(_iv(ms, _reducedTotal - 120, _reducedTotal));
      final enter = Curves.easeOut.transform(_iv(ms, 0, 120));
      scrim = enter;
      portalIn = enter;
      portalScale = 1;
      light = 1;
      rise = 1;
      glow = .6;
      rays = 0;
      amen = enter;
      streakIn = enter;
    } else {
      final ms = _main.value * _total;
      exit = Curves.easeIn.transform(_iv(ms, 1450, _total));
      scrim = SanctaMotion.enter.transform(_iv(ms, 0, 300));
      portalIn = SanctaMotion.enter.transform(_iv(ms, 0, 380));
      portalScale = .9 + .1 * portalIn + .04 * exit;
      rise = SanctaMotion.enter.transform(_iv(ms, 150, 650));
      light = rise;
      glow = Curves.easeOut.transform(_iv(ms, 300, 800));
      rays = math.sin(math.pi * _iv(ms, 250, 1300));
      amen = SanctaMotion.enter.transform(_iv(ms, 380, 700));
      streakIn = SanctaMotion.enter.transform(_iv(ms, 560, 860));
    }
    final fade = (1 - exit) * out;
    final rise10 = _reduced ? 0.0 : 10.0;

    return Stack(
      fit: StackFit.expand,
      children: [
        ColoredBox(color: BrandColors.tinta.withValues(alpha: .74 * scrim * fade)),
        Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              RepaintBoundary(
                child: SizedBox(
                  width: 240,
                  height: 168,
                  child: CustomPaint(
                    painter: _AmenPainter(
                      portalOpacity: portalIn * fade,
                      portalScale: portalScale,
                      light: light * fade,
                      rise: rise,
                      glow: glow * fade,
                      rays: rays * fade,
                      turn: _main.value,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Opacity(
                opacity: amen * fade,
                child: Transform.translate(
                  offset: Offset(0, (1 - amen) * rise10),
                  child: Text(
                    'Amém',
                    style: TextStyle(
                      fontFamily: BrandFonts.figtree,
                      fontWeight: FontWeight.w700,
                      fontSize: 34,
                      height: 1.1,
                      letterSpacing: .2,
                      color: BrandColors.pergaminho,
                    ),
                  ),
                ),
              ),
              if (streak.isNotEmpty) ...[
                const SizedBox(height: 14),
                Opacity(
                  opacity: streakIn * fade,
                  child: Transform.translate(
                    offset: Offset(0, (1 - streakIn) * rise10 * .6),
                    child: _StreakPill(label: streak),
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _StreakPill extends StatelessWidget {
  const _StreakPill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(12, 7, 14, 7),
    decoration: BoxDecoration(
      color: BrandColors.pergaminho.withValues(alpha: .1),
      borderRadius: BorderRadius.circular(999),
      border: Border.all(color: BrandColors.ouro.withValues(alpha: .45)),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        const CustomPaint(size: Size(10, 14), painter: _SmallCross()),
        const SizedBox(width: 8),
        Text(
          label,
          style: TextStyle(fontFamily: BrandFonts.figtree, fontWeight: FontWeight.w600, fontSize: 15, color: BrandColors.pergaminho),
        ),
      ],
    ),
  );
}

/// Cruz Ouro pequena (proporções da cruz do Portal) — acento da sequência.
class _SmallCross extends CustomPainter {
  const _SmallCross();

  @override
  void paint(Canvas canvas, Size size) {
    final s = size.height / 262; // altura da cruz do Portal: 317 − 55 u
    final w = 58 * s;
    final arm = 150 * s;
    final paint = Paint()..color = BrandColors.ouro;
    final cx = size.width / 2;
    canvas.drawRect(Rect.fromLTWH(cx - w / 2, 0, w, size.height), paint);
    canvas.drawRect(Rect.fromLTWH(cx - arm / 2, (317 - 265) * s, arm, 58 * s), paint);
  }

  @override
  bool shouldRepaint(_SmallCross oldDelegate) => false;
}

/// Raios + Portal aceso.
class _AmenPainter extends CustomPainter {
  const _AmenPainter({
    required this.portalOpacity,
    required this.portalScale,
    required this.light,
    required this.rise,
    required this.glow,
    required this.rays,
    required this.turn,
  });

  final double portalOpacity;
  final double portalScale;
  final double light;
  final double rise;
  final double glow;
  final double rays;

  /// Progresso geral (gira os raios ~6° ao longo da celebração).
  final double turn;

  static const double _portalHeight = 104;

  @override
  void paint(Canvas canvas, Size size) {
    const g = PortalGeometry.letter;
    final h = _portalHeight * portalScale;
    final portal = Rect.fromCenter(center: size.center(Offset.zero), width: h * g.aspectRatio, height: h);
    final s = g.scaleFor(portal.size);
    final crossCenter = Offset(portal.center.dx, portal.top + (g.height - (g.cross.barBottom + g.cross.barTop) / 2) * s);

    if (rays > 0) {
      // 12 raios finos (longos e curtos alternados) que nascem da cruz, por trás da porta.
      final inner = h * .5;
      final outer = h * 1.25;
      final path = Path();
      const half = 2.2 * math.pi / 180;
      final base = turn * 6 * math.pi / 180;
      for (var i = 0; i < 12; i++) {
        final a = base + i * math.pi / 6;
        final len = i.isEven ? outer : outer * .78;
        Offset p(double angle, double r) => crossCenter + Offset(math.sin(angle) * r, -math.cos(angle) * r);
        path
          ..moveTo(p(a - half * .5, inner).dx, p(a - half * .5, inner).dy)
          ..lineTo(p(a - half, len).dx, p(a - half, len).dy)
          ..lineTo(p(a + half, len).dx, p(a + half, len).dy)
          ..lineTo(p(a + half * .5, inner).dx, p(a + half * .5, inner).dy)
          ..close();
      }
      canvas.drawPath(
        path,
        Paint()
          ..shader = RadialGradient(
            colors: [
              BrandColors.ouro.withValues(alpha: .34 * rays),
              BrandColors.ouro.withValues(alpha: 0),
            ],
            stops: [inner / outer, 1],
          ).createShader(Rect.fromCircle(center: crossCenter, radius: outer)),
      );
    }

    paintLitPortal(canvas, portal, light: light, rise: rise, glow: glow, opacity: portalOpacity);
  }

  @override
  bool shouldRepaint(_AmenPainter old) =>
      old.portalOpacity != portalOpacity ||
      old.portalScale != portalScale ||
      old.light != light ||
      old.rise != rise ||
      old.glow != glow ||
      old.rays != rays ||
      old.turn != turn;
}
