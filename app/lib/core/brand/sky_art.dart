import 'dart:math' as math;

import 'package:flutter/widgets.dart';

import 'brand_colors.dart';

/// Céus da marca (ilustrações em arco), um por período do dia.
enum SkyScene {
  /// Azul Manto → lilás → pêssego/âmbar com sol Pergaminho (manhã).
  aurora,

  /// Céu claro e neutro com raios suaves (meio do dia, capas neutras).
  luz,

  /// Violeta → rosa → laranja com sol se pondo (fim da tarde).
  entardecer,

  /// Tinta → Noite → Azul Manto com lua crescente e estrelas.
  noite,
}

/// Ilustração de céu desenhada em código — mesma arte dos SVGs `brand/illustrations/*.svg`
/// (`assets/brand/ceu_*.svg`), mas adaptada a QUALQUER proporção: tamanhos relativos à largura,
/// sol/horizonte ancorados na base e elementos dentro da área útil do arco. Use dentro de uma
/// área com tamanho definido (ex.: [ArchFrame] com `aspectRatio`).
///
/// ```dart
/// ArchFrame(aspectRatio: 3 / 2, child: SkyArt.forTime(DateTime.now()))
/// ```
class SkyArt extends StatelessWidget {
  const SkyArt({super.key, this.scene = SkyScene.aurora, this.child});

  /// Céu adequado ao horário (ver [sceneFor]).
  SkyArt.forTime(DateTime time, {super.key, this.child}) : scene = sceneFor(time);

  final SkyScene scene;

  /// Conteúdo opcional por cima do céu.
  final Widget? child;

  /// 05h–11h59 aurora · 12h–15h59 luz · 16h–18h59 entardecer · 19h–04h59 noite.
  static SkyScene sceneFor(DateTime time) {
    final h = time.hour;
    if (h >= 5 && h < 12) return SkyScene.aurora;
    if (h >= 12 && h < 16) return SkyScene.luz;
    if (h >= 16 && h < 19) return SkyScene.entardecer;
    return SkyScene.noite;
  }

  /// SVG equivalente (paisagem 3:2 ou retrato 3:4), para quem preferir `SvgPicture`.
  static String assetFor(SkyScene scene, {bool portrait = false}) => 'assets/brand/ceu_${scene.name}${portrait ? '_retrato' : ''}.svg';

  @override
  Widget build(BuildContext context) => CustomPaint(painter: SkyPainter(scene), child: child ?? const SizedBox.expand());
}

/// Pinta um [SkyScene]. Medidas em "u" = largura ÷ 1200 (a arte foi desenhada numa grade de 1200 px).
class SkyPainter extends CustomPainter {
  const SkyPainter(this.scene);

  final SkyScene scene;

  /// Degradês de cima para baixo (iguais a `brand/source/art.js`).
  static const Map<SkyScene, List<(double, Color)>> gradients = {
    SkyScene.aurora: [
      (0, Color(0xFF2D4DB4)),
      (0.2, Color(0xFF5266C1)),
      (0.42, Color(0xFF7A82CE)),
      (0.53, Color(0xFF9890C1)),
      (0.66, Color(0xFFC9A3A3)),
      (0.78, Color(0xFFEEB38D)),
      (1, Color(0xFFF8D69C)),
    ],
    SkyScene.entardecer: [
      (0, Color(0xFF1A3388)),
      (0.24, Color(0xFF3B4BAA)),
      (0.44, Color(0xFF7559AC)),
      (0.6, Color(0xFFB8628F)),
      (0.76, Color(0xFFE57F63)),
      (1, Color(0xFFF4AE5A)),
    ],
    SkyScene.noite: [(0, Color(0xFF0B1533)), (0.42, BrandColors.noite), (0.8, BrandColors.azulManto), (1, Color(0xFF2E4DB4))],
    SkyScene.luz: [(0, Color(0xFFDCE3F7)), (0.55, Color(0xFFEEEDF1)), (1, BrandColors.pergaminho)],
  };

  // estrelas da noite: (x/1200, y/800, raio em u, opacidade)
  static const List<(double, double, double, double)> _stars = [
    (300, 330, 3.2, 0.85),
    (372, 214, 2.4, 0.7),
    (468, 132, 3.0, 0.9),
    (560, 236, 2.2, 0.6),
    (640, 110, 2.6, 0.8),
    (266, 470, 2.0, 0.55),
    (430, 400, 2.6, 0.75),
    (520, 330, 1.8, 0.5),
    (930, 420, 2.8, 0.8),
    (980, 540, 2.0, 0.6),
    (872, 196, 2.2, 0.65),
    (690, 420, 2.0, 0.55),
    (180, 600, 2.2, 0.6),
    (1030, 640, 2.4, 0.55),
    (350, 560, 1.8, 0.5),
    (820, 560, 2.2, 0.6),
    (600, 520, 1.6, 0.45),
    (150, 720, 1.8, 0.45),
    (1060, 360, 1.6, 0.45),
    (710, 250, 1.6, 0.5),
  ];

  // brilhos de 4 pontas: (x/1200, y/800, raio em u, cor)
  static const List<(double, double, double, Color)> _sparkles = [
    (404, 268, 16, Color(0xF2FBF6EC)),
    (1010, 470, 11, Color(0xCCFBF6EC)),
    (520, 186, 9, BrandColors.ouro),
    (240, 540, 9, Color(0xBFFBF6EC)),
  ];

  // nuvens do entardecer: (x a partir do centro, y a partir da base, largura, altura, opacidade) em u
  static const List<(double, double, double, double, double)> _clouds = [
    (-350, -348, 330, 26, 0.28),
    (40, -300, 300, 22, 0.24),
    (-220, -174, 470, 30, 0.34),
    (90, -110, 260, 22, 0.3),
  ];

  @override
  void paint(Canvas canvas, Size size) {
    if (size.isEmpty) return;
    final w = size.width;
    final h = size.height;
    final u = w / 1200;
    final cx = w / 2;
    final rect = Offset.zero & size;
    final stops = gradients[scene]!;

    canvas.save();
    canvas.clipRect(rect);
    canvas.drawRect(
      rect,
      Paint()
        ..shader = LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [for (final s in stops) s.$2],
          stops: [for (final s in stops) s.$1],
        ).createShader(rect),
    );

    switch (scene) {
      case SkyScene.aurora:
        final sun = Offset(cx, h - 24 * u);
        _radial(canvas, sun, 560 * u, const [(0.42, Color(0x6BFFF3DC)), (1, Color(0x00FFF3DC))]);
        canvas.drawCircle(sun, 235 * u, Paint()..color = BrandColors.pergaminho);
      case SkyScene.entardecer:
        final sun = Offset(cx, h + 50 * u);
        _radial(canvas, sun.translate(0, -10 * u), 640 * u, const [(0.38, Color(0x80FFD9A0)), (1, Color(0x00FFD9A0))]);
        final sunRect = Rect.fromCircle(center: sun, radius: 290 * u);
        canvas.drawCircle(
          sun,
          290 * u,
          Paint()
            ..shader = const LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Color(0xFFFDEFD2), Color(0xFFF9CF8C)],
            ).createShader(sunRect),
        );
        for (final (nx, ny, nw, nh, o) in _clouds) {
          canvas.drawRRect(
            RRect.fromRectAndRadius(Rect.fromLTWH(cx + nx * u, h + ny * u, nw * u, nh * u), Radius.circular(nh * u / 2)),
            Paint()..color = const Color(0xFFFCE1CF).withValues(alpha: o),
          );
        }
      case SkyScene.noite:
        final moon = Offset(cx + 156 * u, math.min(300 * u + (h - 800 * u) * 0.25, h * 0.42));
        _radial(canvas, Offset(cx, h + 100 * u), 620 * u, const [(0, Color(0x736F86D6)), (1, Color(0x006F86D6))]);
        _radial(canvas, moon, 250 * u, const [(0.25, Color(0x38FBF6EC)), (1, Color(0x00FBF6EC))]);
        final crescent = Path.combine(
          PathOperation.difference,
          Path()..addOval(Rect.fromCircle(center: moon, radius: 84 * u)),
          Path()..addOval(Rect.fromCircle(center: moon.translate(38 * u, -26 * u), radius: 74 * u)),
        );
        canvas.drawPath(crescent, Paint()..color = BrandColors.pergaminho);
        final rs = math.max(u, 0.35); // piso: estrelas não somem em molduras pequenas
        for (final (x, y, r, o) in _stars) {
          canvas.drawCircle(Offset(x / 1200 * w, y / 800 * h), r * rs, Paint()..color = BrandColors.pergaminho.withValues(alpha: o));
        }
        for (final (x, y, r, color) in _sparkles) {
          canvas.drawPath(_sparkle(Offset(x / 1200 * w, y / 800 * h), r * rs), Paint()..color = color);
        }
      case SkyScene.luz:
        final origin = Offset(cx, h + 20 * u);
        final length = math.sqrt(w * w + h * h) * 1.1;
        final rays = Path();
        const half = 3.2 * math.pi / 180;
        for (var i = -4; i <= 4; i++) {
          final a = i * 15 * math.pi / 180;
          Offset p(double ang) => Offset(origin.dx + math.sin(ang) * length, origin.dy - math.cos(ang) * length);
          rays.addPolygon([origin, p(a - half), p(a + half)], true);
        }
        canvas.drawPath(
          rays,
          Paint()
            ..shader = const LinearGradient(
              begin: Alignment.bottomCenter,
              end: Alignment.topCenter,
              colors: [Color(0x99FFFFFF), Color(0x00FFFFFF)],
            ).createShader(rays.getBounds()),
        );
        _radial(canvas, Offset(cx, h), 560 * u, const [(0, Color(0xF2FFFFFF)), (0.45, Color(0x8CFFFFFF)), (1, Color(0x00FFFFFF))]);
        canvas.drawCircle(Offset(cx, h - 10 * u), 170 * u, Paint()..color = const Color(0xE6FFFFFF));
    }
    canvas.restore();
  }

  /// Degradê radial; como no SVG, antes do primeiro stop vale a cor do primeiro stop.
  static void _radial(Canvas canvas, Offset center, double radius, List<(double, Color)> stops) {
    if (radius <= 0) return;
    final colors = <Color>[];
    final offsets = <double>[];
    if (stops.first.$1 > 0) {
      colors.add(stops.first.$2);
      offsets.add(0);
    }
    for (final s in stops) {
      colors.add(s.$2);
      offsets.add(s.$1);
    }
    canvas.drawCircle(
      center,
      radius,
      Paint()..shader = RadialGradient(colors: colors, stops: offsets).createShader(Rect.fromCircle(center: center, radius: radius)),
    );
  }

  /// Estrela de 4 pontas (curvas quadráticas), como `brilho()` em art.js.
  static Path _sparkle(Offset c, double r) {
    final q = r * 0.18;
    return Path()
      ..moveTo(c.dx, c.dy - r)
      ..quadraticBezierTo(c.dx + q, c.dy - q, c.dx + r, c.dy)
      ..quadraticBezierTo(c.dx + q, c.dy + q, c.dx, c.dy + r)
      ..quadraticBezierTo(c.dx - q, c.dy + q, c.dx - r, c.dy)
      ..quadraticBezierTo(c.dx - q, c.dy - q, c.dx, c.dy - r)
      ..close();
  }

  @override
  bool shouldRepaint(SkyPainter oldDelegate) => oldDelegate.scene != scene;
}
