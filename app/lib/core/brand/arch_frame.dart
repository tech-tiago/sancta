import 'dart:math' as math;

import 'package:flutter/widgets.dart';

/// Recorte em arco: topo em semicírculo (raio = largura ÷ 2) — "o arco vira moldura".
///
/// Se a altura for menor que meia largura, o topo vira meia elipse (nunca quebra o layout).
/// [bottomRadius] arredonda os cantos de baixo (0 = retos, como no mockup).
class ArchClipper extends CustomClipper<Path> {
  const ArchClipper({this.bottomRadius = 0});

  final double bottomRadius;

  /// Path da moldura em arco para [size] (também usado para bordas e sombras).
  static Path archPath(Size size, {double bottomRadius = 0}) {
    final w = size.width;
    final h = size.height;
    if (w <= 0 || h <= 0) return Path();
    final top = math.min(w / 2, h);
    final br = bottomRadius.clamp(0.0, math.min(w / 2, h - top)).toDouble();
    final path = Path()
      ..moveTo(0, h - br)
      ..lineTo(0, top)
      ..arcToPoint(Offset(w, top), radius: Radius.elliptical(w / 2, top))
      ..lineTo(w, h - br);
    if (br > 0) {
      path
        ..arcToPoint(Offset(w - br, h), radius: Radius.circular(br))
        ..lineTo(br, h)
        ..arcToPoint(Offset(0, h - br), radius: Radius.circular(br));
    } else {
      path.lineTo(0, h);
    }
    return path..close();
  }

  @override
  Path getClip(Size size) => archPath(size, bottomRadius: bottomRadius);

  @override
  bool shouldReclip(ArchClipper oldClipper) => oldClipper.bottomRadius != bottomRadius;
}

/// Moldura em arco para imagens de orações, jornadas e destaques.
///
/// ```dart
/// ArchFrame(aspectRatio: 3 / 2, child: SkyArt(scene: SkyScene.aurora))
/// ```
class ArchFrame extends StatelessWidget {
  const ArchFrame({super.key, required this.child, this.aspectRatio, this.bottomRadius = 0, this.backgroundColor, this.borderColor, this.borderWidth = 1});

  final Widget child;

  /// Largura ÷ altura; se nulo, usa o tamanho dado pelo pai.
  final double? aspectRatio;

  /// Raio dos cantos inferiores.
  final double bottomRadius;

  /// Fundo atrás do conteúdo (ex.: enquanto a imagem carrega).
  final Color? backgroundColor;

  /// Contorno opcional seguindo o arco.
  final Color? borderColor;
  final double borderWidth;

  @override
  Widget build(BuildContext context) {
    Widget framed = ClipPath(
      clipper: ArchClipper(bottomRadius: bottomRadius),
      child: backgroundColor == null ? child : ColoredBox(color: backgroundColor!, child: child),
    );
    if (borderColor != null && borderWidth > 0) {
      framed = CustomPaint(
        foregroundPainter: _ArchBorderPainter(color: borderColor!, width: borderWidth, bottomRadius: bottomRadius),
        child: framed,
      );
    }
    if (aspectRatio != null) framed = AspectRatio(aspectRatio: aspectRatio!, child: framed);
    return framed;
  }
}

class _ArchBorderPainter extends CustomPainter {
  const _ArchBorderPainter({required this.color, required this.width, required this.bottomRadius});

  final Color color;
  final double width;
  final double bottomRadius;

  @override
  void paint(Canvas canvas, Size size) {
    // traço por dentro do recorte (metade da espessura para dentro)
    final inset = width / 2;
    final inner = Size(size.width - width, size.height - width);
    if (inner.isEmpty) return;
    canvas.save();
    canvas.translate(inset, inset);
    canvas.drawPath(
      ArchClipper.archPath(inner, bottomRadius: math.max(0, bottomRadius - inset)),
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = width
        ..color = color
        ..isAntiAlias = true,
    );
    canvas.restore();
  }

  @override
  bool shouldRepaint(_ArchBorderPainter oldDelegate) => oldDelegate.color != color || oldDelegate.width != width || oldDelegate.bottomRadius != bottomRadius;
}
