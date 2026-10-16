import 'dart:math' as math;
import 'dart:ui';

/// Proporções do símbolo Sancta Portal/N.
enum PortalShape {
  /// Proporção da letra (512 × 512 u): o símbolo que ocupa o lugar do "n" no wordmark.
  letter,

  /// Proporção vertical (512 × 666 u): a porta do ícone do app.
  door,
}

/// Medidas da cruz.
class PortalCrossSpec {
  const PortalCrossSpec({
    required this.halfWidth,
    required this.bottom,
    required this.top,
    required this.armHalfWidth,
    required this.barBottom,
    required this.barTop,
  });

  /// Meia espessura da haste vertical.
  final double halfWidth;

  /// Pé da haste vertical (a cruz flutua acima da linha de base).
  final double bottom;

  /// Topo da haste vertical.
  final double top;

  /// Meia largura do travessão.
  final double armHalfWidth;

  /// Base e topo do travessão.
  final double barBottom;
  final double barTop;
}

/// Geometria do Portal/N — arco vetorial robusto e proprietário, com a cruz como luz no vão.
///
/// As coordenadas correspondem a `brand/source/portal.js` e são compartilhadas pelos SVGs, ícones,
/// wordmark e animações. Todos os métodos devolvem `Path`s escalados para caber (contain, centralizado)
/// em um [Size], prontos para `Canvas.drawPath`, `ClipPath` ou animação com `PathMetrics`.
class PortalGeometry {
  const PortalGeometry._({required this.height, required this.outerCenterY, required this.innerCenterY, required this.cross});

  /// Raio horizontal externo (metade da largura total).
  static const double outerRadius = 256;

  /// Raio horizontal do vão interno.
  static const double innerRadius = 106;

  /// Espessura nominal das hastes laterais.
  static const double stem = outerRadius - innerRadius;

  /// Alças das curvas cúbicas da abóbada externa e do vão interno.
  static const double outerHandle = 144;
  static const double outerCrownInset = 48;
  static const double innerSideHandle = 60;
  static const double innerCrownHandle = 42;
  static const double innerTopHalf = 24;

  /// Largura total do símbolo.
  static const double width = 2 * outerRadius;

  /// Proporção da letra (wordmark, símbolo, splash).
  static const PortalGeometry letter = PortalGeometry._(
    height: 512,
    outerCenterY: 256,
    innerCenterY: 256,
    cross: PortalCrossSpec(halfWidth: 26, bottom: 64, top: 314, armHalfWidth: 72, barBottom: 214, barTop: 266),
  );

  /// Proporção vertical (ícone do app): laterais alongadas em 154 u.
  static const PortalGeometry door = PortalGeometry._(
    height: 666,
    outerCenterY: 410,
    innerCenterY: 410,
    cross: PortalCrossSpec(halfWidth: 26, bottom: 141, top: 391, armHalfWidth: 72, barBottom: 291, barTop: 343),
  );

  /// Geometria de uma proporção.
  static PortalGeometry of(PortalShape shape) => shape == PortalShape.door ? door : letter;

  /// Altura total (topo da abóbada; a base é y = 0).
  final double height;

  /// Altura onde a curva externa começa.
  final double outerCenterY;

  /// Altura onde o vão interno começa.
  final double innerCenterY;

  /// Medidas da cruz.
  final PortalCrossSpec cross;

  /// Largura ÷ altura.
  double get aspectRatio => width / height;

  /// Topo do vão interior (em unidades).
  double get openingTop => innerCenterY + innerRadius;

  /// Escala (px por unidade) usada para caber em [size].
  double scaleFor(Size size) => math.min(size.width / width, size.height / height);

  /// Espessura das hastes em px para [size].
  double stemWidth(Size size) => stem * scaleFor(size);

  /// Retângulo ocupado pelo símbolo dentro de [size].
  Rect bounds(Size size) {
    final f = _Fit.of(this, size);
    return Rect.fromLTWH(f.dx, f.dy, width * f.s, height * f.s);
  }

  /// O arco preenchido (contorno externo menos o vão interno).
  Path arch(Size size) {
    final f = _Fit.of(this, size);
    const r = outerRadius;
    const i = innerRadius;
    final outerTop = height;
    final innerTop = openingTop;
    return Path()
      ..moveTo(f.x(-r), f.y(0))
      ..lineTo(f.x(-r), f.y(outerCenterY))
      ..cubicTo(f.x(-r), f.y(outerCenterY + outerHandle), f.x(-outerHandle), f.y(outerTop), f.x(-outerCrownInset), f.y(outerTop))
      ..lineTo(f.x(outerCrownInset), f.y(outerTop))
      ..cubicTo(f.x(outerHandle), f.y(outerTop), f.x(r), f.y(outerCenterY + outerHandle), f.x(r), f.y(outerCenterY))
      ..lineTo(f.x(r), f.y(0))
      ..lineTo(f.x(i), f.y(0))
      ..lineTo(f.x(i), f.y(innerCenterY))
      ..cubicTo(f.x(i), f.y(innerCenterY + innerSideHandle), f.x(innerCrownHandle), f.y(innerTop), f.x(innerTopHalf), f.y(innerTop))
      ..lineTo(f.x(-innerTopHalf), f.y(innerTop))
      ..cubicTo(f.x(-innerCrownHandle), f.y(innerTop), f.x(-i), f.y(innerCenterY + innerSideHandle), f.x(-i), f.y(innerCenterY))
      ..lineTo(f.x(-i), f.y(0))
      ..close();
  }

  /// Silhueta externa cheia (útil para recortes e brilho).
  Path outline(Size size) {
    final f = _Fit.of(this, size);
    const r = outerRadius;
    return Path()
      ..moveTo(f.x(-r), f.y(0))
      ..lineTo(f.x(-r), f.y(outerCenterY))
      ..cubicTo(f.x(-r), f.y(outerCenterY + outerHandle), f.x(-outerHandle), f.y(height), f.x(-outerCrownInset), f.y(height))
      ..lineTo(f.x(outerCrownInset), f.y(height))
      ..cubicTo(f.x(outerHandle), f.y(height), f.x(r), f.y(outerCenterY + outerHandle), f.x(r), f.y(outerCenterY))
      ..lineTo(f.x(r), f.y(0))
      ..close();
  }

  /// Vão interno recortado (onde a luz pode aparecer).
  Path opening(Size size) {
    final f = _Fit.of(this, size);
    const i = innerRadius;
    final top = openingTop;
    return Path()
      ..moveTo(f.x(-i), f.y(0))
      ..lineTo(f.x(-i), f.y(innerCenterY))
      ..cubicTo(f.x(-i), f.y(innerCenterY + innerSideHandle), f.x(-innerCrownHandle), f.y(top), f.x(-innerTopHalf), f.y(top))
      ..lineTo(f.x(innerTopHalf), f.y(top))
      ..cubicTo(f.x(innerCrownHandle), f.y(top), f.x(i), f.y(innerCenterY + innerSideHandle), f.x(i), f.y(innerCenterY))
      ..lineTo(f.x(i), f.y(0))
      ..close();
  }

  /// Cruz (haste + travessão), um único polígono.
  Path crossPath(Size size) {
    final f = _Fit.of(this, size);
    final c = cross;
    return Path()..addPolygon([
      Offset(f.x(-c.halfWidth), f.y(c.top)),
      Offset(f.x(c.halfWidth), f.y(c.top)),
      Offset(f.x(c.halfWidth), f.y(c.barTop)),
      Offset(f.x(c.armHalfWidth), f.y(c.barTop)),
      Offset(f.x(c.armHalfWidth), f.y(c.barBottom)),
      Offset(f.x(c.halfWidth), f.y(c.barBottom)),
      Offset(f.x(c.halfWidth), f.y(c.bottom)),
      Offset(f.x(-c.halfWidth), f.y(c.bottom)),
      Offset(f.x(-c.halfWidth), f.y(c.barBottom)),
      Offset(f.x(-c.armHalfWidth), f.y(c.barBottom)),
      Offset(f.x(-c.armHalfWidth), f.y(c.barTop)),
      Offset(f.x(-c.halfWidth), f.y(c.barTop)),
    ], true);
  }

  /// Linha central da abóbada, equidistante dos contornos externo e interno.
  Path centerline(Size size) {
    final f = _Fit.of(this, size);
    const m = (outerRadius + innerRadius) / 2;
    final cy = (outerCenterY + innerCenterY) / 2;
    final top = (height + openingTop) / 2;
    const side = (outerHandle + innerSideHandle) / 2;
    const crown = (outerHandle + innerCrownHandle) / 2;
    const topHalf = (outerCrownInset + innerTopHalf) / 2;
    return Path()
      ..moveTo(f.x(-m), f.y(0))
      ..lineTo(f.x(-m), f.y(cy))
      ..cubicTo(f.x(-m), f.y(cy + side), f.x(-crown), f.y(top), f.x(-topHalf), f.y(top))
      ..lineTo(f.x(topHalf), f.y(top))
      ..cubicTo(f.x(crown), f.y(top), f.x(m), f.y(cy + side), f.x(m), f.y(cy))
      ..lineTo(f.x(m), f.y(0));
  }
}

/// Encaixe (contain, centralizado) das unidades da fonte em pixels.
class _Fit {
  const _Fit(this.g, this.s, this.dx, this.dy);

  factory _Fit.of(PortalGeometry g, Size size) {
    final s = g.scaleFor(size);
    return _Fit(g, s, (size.width - PortalGeometry.width * s) / 2, (size.height - g.height * s) / 2);
  }

  final PortalGeometry g;
  final double s;
  final double dx;
  final double dy;

  /// x em unidades (centrado no Portal) → px.
  double x(double u) => dx + (u + PortalGeometry.outerRadius) * s;

  /// y em unidades (para cima, base = 0) → px (para baixo).
  double y(double u) => dy + (g.height - u) * s;
}
