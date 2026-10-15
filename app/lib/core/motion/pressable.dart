import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'motion.dart';

/// Envolve um elemento tocável com o efeito de toque da marca: o elemento "afunda" levemente
/// (escala [pressedScale]) em [SanctaMotion.press] e volta em [SanctaMotion.base].
///
/// - Toque rápido também mostra o afundar completo antes de voltar (o efeito nunca "some").
/// - [haptic]: um clique háptico leve ao confirmar o toque (use em ações principais, não em listas).
/// - Com "reduzir movimento" não há escala (o toque continua funcionando normalmente).
/// - Sem [onTap], é só o [child] (desabilitado, sem efeito).
class Pressable extends StatefulWidget {
  const Pressable({super.key, required this.child, this.onTap, this.haptic = false, this.pressedScale = .97});

  final Widget child;
  final VoidCallback? onTap;

  /// Clique háptico leve (`HapticFeedback.selectionClick`) ao tocar.
  final bool haptic;

  /// Escala com o dedo pressionado (0,97 = 3% menor: perceptível sem parecer elástico).
  final double pressedScale;

  @override
  State<Pressable> createState() => _PressableState();
}

class _PressableState extends State<Pressable> with SingleTickerProviderStateMixin {
  late final AnimationController _press = AnimationController(vsync: this, duration: SanctaMotion.press, reverseDuration: SanctaMotion.base);
  late final CurvedAnimation _curve = CurvedAnimation(parent: _press, curve: Curves.easeOut, reverseCurve: SanctaMotion.standard.flipped);
  bool _held = false;

  bool get _enabled => widget.onTap != null && !SanctaMotion.reduced(context);

  void _down(TapDownDetails _) {
    if (!_enabled) return;
    _held = true;
    _press.forward();
  }

  void _release() {
    _held = false;
    if (_press.isDismissed) return;
    // Termina de afundar (toques rápidos) e então volta.
    _press.forward().whenCompleteOrCancel(() {
      if (mounted && !_held) _press.reverse();
    });
  }

  void _tap() {
    if (widget.haptic) HapticFeedback.selectionClick();
    widget.onTap?.call();
  }

  @override
  void dispose() {
    _curve.dispose();
    _press.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final onTap = widget.onTap;
    return GestureDetector(
      onTap: onTap == null ? null : _tap,
      onTapDown: onTap == null ? null : _down,
      onTapUp: onTap == null ? null : (_) => _release(),
      onTapCancel: onTap == null ? null : _release,
      child: ScaleTransition(
        scale: _curve.drive(Tween<double>(begin: 1, end: widget.pressedScale)),
        child: widget.child,
      ),
    );
  }
}
