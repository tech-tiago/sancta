import 'package:flutter/painting.dart';

import '../brand/brand.dart';

/// Pinta o Portal (proporção da letra) em [rect] com a **luz por dentro da porta** — o gesto central
/// do movimento da marca (abertura do app e "Amém").
///
/// - [light] 0–1: o céu Aurora (mesmos degradês de `SkyPainter`) acende dentro da abertura.
/// - [rise] 0–1: o sol sobe da soleira até o lugar (0 = escondido abaixo da linha de base).
/// - [glow] 0–1: brilho quente atrás da cruz ("a cruz acende").
/// - [opacity]: opacidade do arco e da cruz.
///
/// Com `light = glow = 0` o resultado é exatamente o símbolo oficial (`PortalGeometry.letter`).
/// A luz fica **sempre recortada pela abertura**: nada de brilho ou sombra fora da marca.
void paintLitPortal(
  Canvas canvas,
  Rect rect, {
  double light = 0,
  double rise = 1,
  double glow = 0,
  double opacity = 1,
  Color archColor = BrandColors.pergaminho,
  Color crossColor = BrandColors.ouro,
}) {
  if (rect.isEmpty) return;
  const g = PortalGeometry.letter;
  final size = rect.size;
  canvas.save();
  canvas.translate(rect.left, rect.top);

  if (light > 0 || glow > 0) {
    final opening = g.opening(size);
    final ob = opening.getBounds();
    canvas.save();
    canvas.clipPath(opening);
    if (light > 0) {
      final a = light.clamp(0.0, 1.0);
      // O céu "sobe": o degradê começa deslocado para baixo e assenta no lugar.
      final shift = (1 - rise.clamp(0.0, 1.0)) * ob.height * .32;
      final skyRect = Rect.fromLTWH(ob.left, ob.top + shift, ob.width, ob.height);
      final stops = SkyPainter.gradients[SkyScene.aurora]!;
      canvas.drawRect(
        ob.inflate(1),
        Paint()
          ..shader = LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [for (final s in stops) s.$2.withValues(alpha: s.$2.a * a)],
            stops: [for (final s in stops) s.$1],
          ).createShader(skyRect),
      );
      // Sol Pergaminho nascendo na soleira, com halo quente.
      final sun = Offset(ob.center.dx, ob.bottom + shift - ob.width * .02);
      final halo = ob.width * .95;
      canvas.drawCircle(
        sun,
        halo,
        Paint()
          ..shader = RadialGradient(
            colors: [
              const Color(0xFFFFF3DC).withValues(alpha: .5 * a),
              const Color(0xFFFFF3DC).withValues(alpha: 0),
            ],
          ).createShader(Rect.fromCircle(center: sun, radius: halo)),
      );
      canvas.drawCircle(sun, ob.width * .34, Paint()..color = BrandColors.pergaminho.withValues(alpha: a));
    }
    if (glow > 0) {
      final s = g.scaleFor(size);
      final bounds = g.bounds(size);
      final center = Offset(bounds.center.dx, bounds.top + (g.height - (g.cross.barBottom + g.cross.barTop) / 2) * s);
      final radius = ob.width * .85;
      canvas.drawCircle(
        center,
        radius,
        Paint()
          ..shader = RadialGradient(
            colors: [
              const Color(0xFFFFE7B0).withValues(alpha: .7 * glow.clamp(0.0, 1.0)),
              const Color(0xFFFFE7B0).withValues(alpha: 0),
            ],
          ).createShader(Rect.fromCircle(center: center, radius: radius)),
      );
    }
    canvas.restore();
  }

  final o = opacity.clamp(0.0, 1.0);
  final paint = Paint()..isAntiAlias = true;
  canvas.drawPath(g.arch(size), paint..color = archColor.withValues(alpha: archColor.a * o));
  canvas.drawPath(g.crossPath(size), paint..color = crossColor.withValues(alpha: crossColor.a * o));
  canvas.restore();
}
