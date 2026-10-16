import 'dart:ui' as ui;

import 'package:flutter/material.dart';

import '../brand/brand.dart';
import 'motion.dart';

/// Ícone da aba "Hoje": **contorno do Portal** (inativo) que se preenche até o **Portal cheio com a
/// cruz acesa em Ouro** (ativo).
///
/// Transição (250 ms): o arco engrossa para dentro — de um traço fino de ~2 px até as hastes do "n"
/// (mesma curva do símbolo) — e, a partir de ~35%, a cruz acende (fade + cresce de 70% a 100%).
/// A cor acompanha em 150 ms. Com "reduzir movimento", muda na hora.
///
/// Decorativo para leitores de tela (o rótulo da aba já diz "Hoje").
class PortalTabIcon extends StatefulWidget {
  const PortalTabIcon({super.key, required this.selected, required this.color, this.size = 24});

  final bool selected;
  final Color color;
  final double size;

  @override
  State<PortalTabIcon> createState() => _PortalTabIconState();
}

class _PortalTabIconState extends State<PortalTabIcon> with TickerProviderStateMixin {
  late final AnimationController _select = AnimationController(vsync: this, duration: SanctaMotion.base, value: widget.selected ? 1 : 0);
  late final AnimationController _tint = AnimationController(vsync: this, duration: SanctaMotion.fast, value: 1);
  late Color _from = widget.color;

  @override
  void didUpdateWidget(PortalTabIcon oldWidget) {
    super.didUpdateWidget(oldWidget);
    final reduced = SanctaMotion.reduced(context);
    if (widget.color != oldWidget.color) {
      _from = Color.lerp(_from, oldWidget.color, Curves.easeOut.transform(_tint.value))!;
      if (reduced) {
        _tint.value = 1;
      } else {
        _tint.forward(from: 0);
      }
    }
    if (widget.selected != oldWidget.selected) {
      final target = widget.selected ? 1.0 : 0.0;
      if (reduced) {
        _select.value = target;
      } else {
        _select.animateTo(target, curve: Curves.linear);
      }
    }
  }

  @override
  void dispose() {
    _select.dispose();
    _tint.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SizedBox.square(
    dimension: widget.size,
    child: CustomPaint(
      painter: PortalTabPainter(selection: _select, tint: _tint, from: _from, to: widget.color),
    ),
  );
}

/// Pinta o ícone da aba em qualquer ponto da transição (exposto para testes e outras abas).
class PortalTabPainter extends CustomPainter {
  PortalTabPainter({required this.selection, required this.tint, required this.from, required this.to}) : super(repaint: Listenable.merge([selection, tint]));

  /// 0 = contorno, 1 = Portal cheio com a cruz Ouro.
  final Animation<double> selection;

  /// Progresso da troca de cor ([from] → [to]).
  final Animation<double> tint;
  final Color from;
  final Color to;

  static const _fill = Interval(0, .7, curve: SanctaMotion.standard);
  static const _cross = Interval(.35, 1, curve: SanctaMotion.enter);

  @override
  void paint(Canvas canvas, Size size) {
    if (size.isEmpty) return;
    final t = selection.value;
    final color = Color.lerp(from, to, Curves.easeOut.transform(tint.value))!;
    // O Portal ocupa 90% da altura do quadro (mesma área do PortalMark que ele substitui).
    final h = size.height * .9;
    final box = Size(h * PortalGeometry.letter.aspectRatio, h);
    canvas.save();
    canvas.translate((size.width - box.width) / 2, (size.height - box.height) / 2);
    final stroke = size.height / 12;
    canvas.drawPath(portalArchAt(box, _fill.transform(t), strokeWidth: stroke), Paint()..color = color);
    final c = _cross.transform(t);
    if (c > 0) {
      const g = PortalGeometry.letter;
      final bounds = g.bounds(box);
      // Cresce a partir do cruzamento dos braços.
      final pivot = Offset(bounds.center.dx, bounds.top + (g.height - (g.cross.barBottom + g.cross.barTop) / 2) * g.scaleFor(box));
      final k = ui.lerpDouble(.7, 1, c)!;
      canvas.translate(pivot.dx, pivot.dy);
      canvas.scale(k);
      canvas.translate(-pivot.dx, -pivot.dy);
      canvas.drawPath(g.crossPath(box), Paint()..color = BrandColors.ouro.withValues(alpha: c));
    }
    canvas.restore();
  }

  @override
  bool shouldRepaint(PortalTabPainter oldDelegate) =>
      oldDelegate.from != from || oldDelegate.to != to || oldDelegate.selection != selection || oldDelegate.tint != tint;
}

/// O arco do Portal (proporção da letra) em qualquer espessura: [fill] 0 = contorno fino de
/// [strokeWidth] px (a mesma silhueta externa), 1 = o arco oficial (`PortalGeometry.letter.arch`).
/// A abertura encolhe do contorno externo até a coroa Bézier interna de 106 u, sem mudar a silhueta.
Path portalArchAt(Size size, double fill, {double strokeWidth = 2}) {
  const g = PortalGeometry.letter;
  const r = PortalGeometry.outerRadius;
  final s = g.scaleFor(size);
  final dx = (size.width - PortalGeometry.width * s) / 2;
  final dy = (size.height - g.height * s) / 2;
  double x(double u) => dx + (u + r) * s;
  double y(double u) => dy + (g.height - u) * s;

  final progress = fill.clamp(0.0, 1.0);
  final thin = (r - strokeWidth / s).clamp(PortalGeometry.innerRadius, r);
  final i = ui.lerpDouble(thin, PortalGeometry.innerRadius, progress)!;
  final iy = ui.lerpDouble(g.outerCenterY, g.innerCenterY, progress)!;
  final side = ui.lerpDouble(PortalGeometry.outerHandle * i / r, PortalGeometry.innerSideHandle, progress)!;
  final crown = ui.lerpDouble(PortalGeometry.outerHandle * i / r, PortalGeometry.innerCrownHandle, progress)!;
  final topHalf = ui.lerpDouble(PortalGeometry.outerCrownInset * i / r, PortalGeometry.innerTopHalf, progress)!;
  final innerTop = iy + i;
  return Path()
    ..moveTo(x(-r), y(0))
    ..lineTo(x(-r), y(g.outerCenterY))
    ..cubicTo(
      x(-r),
      y(g.outerCenterY + PortalGeometry.outerHandle),
      x(-PortalGeometry.outerHandle),
      y(g.height),
      x(-PortalGeometry.outerCrownInset),
      y(g.height),
    )
    ..lineTo(x(PortalGeometry.outerCrownInset), y(g.height))
    ..cubicTo(x(PortalGeometry.outerHandle), y(g.height), x(r), y(g.outerCenterY + PortalGeometry.outerHandle), x(r), y(g.outerCenterY))
    ..lineTo(x(r), y(0))
    ..lineTo(x(i), y(0))
    ..lineTo(x(i), y(iy))
    ..cubicTo(x(i), y(iy + side), x(crown), y(innerTop), x(topHalf), y(innerTop))
    ..lineTo(x(-topHalf), y(innerTop))
    ..cubicTo(x(-crown), y(innerTop), x(-i), y(iy + side), x(-i), y(iy))
    ..lineTo(x(-i), y(0))
    ..close();
}
