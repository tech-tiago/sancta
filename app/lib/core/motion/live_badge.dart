import 'package:flutter/material.dart';

import 'motion.dart';

/// Selo "AO VIVO" com um ponto pulsante (respira e solta um anel suave a cada [SanctaMotion.pulse]).
///
/// - Só o ponto repinta (camada própria); o texto e o selo ficam parados.
/// - O pulso para fora da tela (`TickerMode`) e com "reduzir movimento" (ponto estático).
class LiveBadge extends StatefulWidget {
  const LiveBadge({super.key, this.label = 'AO VIVO', this.compact = false});

  final String label;
  final bool compact;

  /// Vermelho do selo (texto branco: 4,55:1, AA).
  static const Color red = Color(0xFFD93A3A);

  @override
  State<LiveBadge> createState() => _LiveBadgeState();
}

class _LiveBadgeState extends State<LiveBadge> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(vsync: this, duration: SanctaMotion.pulse);
  bool _reduced = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _reduced = SanctaMotion.reduced(context);
    if (_reduced) {
      _pulse.stop();
      _pulse.value = 0;
    } else if (!_pulse.isAnimating) {
      _pulse.repeat();
    }
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final compact = widget.compact;
    final slot = compact ? 9.0 : 11.0;
    return Container(
      padding: EdgeInsets.fromLTRB(compact ? 4 : 6, compact ? 2 : 4, compact ? 6 : 8, compact ? 2 : 4),
      decoration: BoxDecoration(color: LiveBadge.red, borderRadius: BorderRadius.circular(6)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          RepaintBoundary(
            child: CustomPaint(
              size: Size.square(slot),
              painter: _PulsePainter(_pulse, reduced: _reduced, dotRadius: compact ? 2.4 : 2.9),
            ),
          ),
          SizedBox(width: compact ? 3 : 4),
          Text(
            widget.label,
            style: TextStyle(color: Colors.white, fontSize: compact ? 10 : 11, fontWeight: FontWeight.w700, letterSpacing: .6),
          ),
        ],
      ),
    );
  }
}

class _PulsePainter extends CustomPainter {
  _PulsePainter(this.pulse, {required this.reduced, required this.dotRadius}) : super(repaint: pulse);

  final Animation<double> pulse;
  final bool reduced;
  final double dotRadius;

  @override
  void paint(Canvas canvas, Size size) {
    final center = size.center(Offset.zero);
    final paint = Paint()..isAntiAlias = true;
    if (reduced) {
      canvas.drawCircle(center, dotRadius, paint..color = Colors.white);
      return;
    }
    final t = pulse.value;
    // Anel: nasce no ponto e se dissolve até a borda do espaço reservado (sem invadir o texto).
    final ring = Curves.easeOut.transform(t);
    final maxRadius = size.shortestSide / 2 + .5;
    canvas.drawCircle(center, dotRadius + (maxRadius - dotRadius) * ring, paint..color = Colors.white.withValues(alpha: .5 * (1 - ring)));
    // Ponto: respira entre 100% e 72% de opacidade.
    final breath = SanctaMotion.breathing.transform(t < .5 ? t * 2 : 2 - t * 2);
    canvas.drawCircle(center, dotRadius, paint..color = Colors.white.withValues(alpha: 1 - .28 * breath));
  }

  @override
  bool shouldRepaint(_PulsePainter oldDelegate) => oldDelegate.reduced != reduced || oldDelegate.dotRadius != dotRadius || oldDelegate.pulse != pulse;
}
