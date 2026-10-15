import 'dart:math' as math;
import 'dart:ui';

/// Proporções do Portal.
enum PortalShape {
  /// Proporção da letra (486 × 512 u): o Portal que ocupa o lugar do "n" no wordmark.
  letter,

  /// Proporção vertical (486 × 666 u): a "porta" do ícone do app.
  door,
}

/// Medidas da cruz (unidades da fonte, eixo y para cima, x centrado no Portal).
class PortalCrossSpec {
  const PortalCrossSpec({
    required this.halfWidth,
    required this.bottom,
    required this.top,
    required this.armHalfWidth,
    required this.barBottom,
    required this.barTop,
  });

  /// Meia espessura da haste vertical (haste = 58 u).
  final double halfWidth;

  /// Pé da haste vertical (a cruz flutua acima da linha de base, como nas imagens aprovadas).
  final double bottom;

  /// Topo da haste vertical.
  final double top;

  /// Meia largura do travessão (travessão = 150 u).
  final double armHalfWidth;

  /// Base e topo do travessão (no terço superior da cruz).
  final double barBottom;
  final double barTop;
}

/// Geometria do símbolo **Portal** — as MESMAS coordenadas do master vetorial
/// (`brand/source/portal.js` → `brand/symbol/*.svg`, `app/assets/brand/portal_*.svg`).
///
/// Unidades = unidades da fonte Figtree Bold (1000 por em): hastes de 132 u (iguais às do "n"),
/// arco externo semicircular de raio 243 u com topo no overshoot do n (512 u), abertura em arco de
/// raio 111 u (topo em 395 u: espessura de 117 u no alto, a compensação óptica dos arcos da Figtree)
/// e cruz Ouro de 58 u de espessura com o travessão no terço superior.
///
/// Todos os métodos devolvem `Path`s já escalados para caber (contain, centralizado) em um [Size],
/// prontos para `Canvas.drawPath`, `ClipPath` ou animação com `PathMetrics`.
class PortalGeometry {
  const PortalGeometry._({required this.height, required this.outerCenterY, required this.innerCenterY, required this.cross});

  /// Espessura das hastes (= haste do "n" da Figtree Bold).
  static const double stem = 132;

  /// Raio do arco externo.
  static const double outerRadius = 243;

  /// Raio da abertura.
  static const double innerRadius = 111;

  /// Largura total do Portal (2 × raio externo).
  static const double width = 2 * outerRadius;

  /// Portal na proporção da letra (wordmark, símbolo, splash).
  static const PortalGeometry letter = PortalGeometry._(
    height: 512,
    outerCenterY: 269,
    innerCenterY: 284,
    cross: PortalCrossSpec(halfWidth: 29, bottom: 55, top: 317, armHalfWidth: 75, barBottom: 207, barTop: 265),
  );

  /// Portal em proporção vertical (ícone do app): hastes alongadas em 154 u.
  static const PortalGeometry door = PortalGeometry._(
    height: 666,
    outerCenterY: 423,
    innerCenterY: 438,
    cross: PortalCrossSpec(halfWidth: 29, bottom: 111, top: 405, armHalfWidth: 75, barBottom: 285, barTop: 343),
  );

  /// Geometria de uma proporção.
  static PortalGeometry of(PortalShape shape) => shape == PortalShape.door ? door : letter;

  /// Altura total (topo do arco; a base é y = 0).
  final double height;

  /// Altura do centro do arco externo (onde as hastes viram arco).
  final double outerCenterY;

  /// Altura do centro do arco da abertura.
  final double innerCenterY;

  /// Medidas da cruz.
  final PortalCrossSpec cross;

  /// Largura ÷ altura.
  double get aspectRatio => width / height;

  /// Topo da abertura (em unidades).
  double get openingTop => innerCenterY + innerRadius;

  /// Escala (px por unidade) usada para caber em [size].
  double scaleFor(Size size) => math.min(size.width / width, size.height / height);

  /// Espessura da haste em px para [size] (útil para o traço de [centerline]).
  double stemWidth(Size size) => stem * scaleFor(size);

  /// Retângulo ocupado pelo Portal dentro de [size].
  Rect bounds(Size size) {
    final f = _Fit.of(this, size);
    return Rect.fromLTWH(f.dx, f.dy, width * f.s, height * f.s);
  }

  /// O arco (forma "∩": contorno externo menos a abertura) — o que se pinta com a cor do arco.
  Path arch(Size size) {
    final f = _Fit.of(this, size);
    const r = outerRadius;
    const i = innerRadius;
    return Path()
      ..moveTo(f.x(-r), f.y(0))
      ..lineTo(f.x(-r), f.y(outerCenterY))
      ..arcToPoint(Offset(f.x(r), f.y(outerCenterY)), radius: Radius.circular(r * f.s))
      ..lineTo(f.x(r), f.y(0))
      ..lineTo(f.x(i), f.y(0))
      ..lineTo(f.x(i), f.y(innerCenterY))
      ..arcToPoint(Offset(f.x(-i), f.y(innerCenterY)), radius: Radius.circular(i * f.s), clockwise: false)
      ..lineTo(f.x(-i), f.y(0))
      ..close();
  }

  /// Contorno externo (silhueta da porta, cheia). Útil para recortes, brilho e o ícone de aba "Hoje".
  Path outline(Size size) {
    final f = _Fit.of(this, size);
    const r = outerRadius;
    return Path()
      ..moveTo(f.x(-r), f.y(0))
      ..lineTo(f.x(-r), f.y(outerCenterY))
      ..arcToPoint(Offset(f.x(r), f.y(outerCenterY)), radius: Radius.circular(r * f.s))
      ..lineTo(f.x(r), f.y(0))
      ..close();
  }

  /// A abertura (vão interno em arco) — onde "acende" a luz.
  Path opening(Size size) {
    final f = _Fit.of(this, size);
    const i = innerRadius;
    return Path()
      ..moveTo(f.x(-i), f.y(0))
      ..lineTo(f.x(-i), f.y(innerCenterY))
      ..arcToPoint(Offset(f.x(i), f.y(innerCenterY)), radius: Radius.circular(i * f.s))
      ..lineTo(f.x(i), f.y(0))
      ..close();
  }

  /// A cruz (haste vertical + travessão), um único polígono.
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

  /// Linha central do arco (da base da haste esquerda, pelo alto, até a base da direita), para animações
  /// de "desenho" com `PathMetric.extractPath` + traço de espessura [stemWidth] e pontas retas.
  Path centerline(Size size) {
    final f = _Fit.of(this, size);
    const m = (outerRadius + innerRadius) / 2;
    final cy = (outerCenterY + innerCenterY) / 2;
    return Path()
      ..moveTo(f.x(-m), f.y(0))
      ..lineTo(f.x(-m), f.y(cy))
      ..arcToPoint(Offset(f.x(m), f.y(cy)), radius: Radius.circular(m * f.s))
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
