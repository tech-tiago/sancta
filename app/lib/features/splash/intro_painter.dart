import 'dart:math' as math;

import 'package:flutter/widgets.dart';

import '../../core/brand/brand.dart';
import '../../core/motion/lit_portal.dart';
import '../../core/motion/motion.dart';
import 'wordmark_layers.dart';

/// Linha do tempo da abertura, em milissegundos (ver a coreografia em docs/design/MOTION.md).
abstract final class IntroTimeline {
  /// 0–120: quadro idêntico ao splash nativo (cobre o fade de saída do splash do Android 12+).
  static const int hold = 120;

  /// 120–620: a abertura do Portal acende (o céu Aurora sobe da soleira).
  static const int dawnStart = 120;
  static const int dawnEnd = 620;

  /// 250–750: a cruz Ouro brilha (luz quente atrás dela).
  static const int glowStart = 250;
  static const int glowEnd = 750;

  /// 650–1150: o Portal desliza e encaixa no lugar do "n" (curva enfática).
  static const int slideStart = 650;
  static const int slideEnd = 1150;

  /// 820–1150: a luz assenta (a abertura volta ao Azul Manto: o wordmark termina oficial).
  static const int lightOutStart = 820;
  static const int lightOutEnd = 1150;

  /// 850–1290: "Sa" e "cta" saem de trás do Portal, letra a letra (a, c → S, t → a).
  static const int lettersStart = 850;
  static const int letterStagger = 70;
  static const int letterDuration = 300;

  /// 1150–1500: o slogan aparece.
  static const int sloganStart = 1150;
  static const int sloganEnd = 1500;

  /// Duração total da sequência (= [SanctaMotion.intro]).
  static const int total = 1500;

  /// Saída para a primeira tela (fade + leve aproximação).
  static const int exit = 380;

  /// "Reduzir movimento": Portal → wordmark só com fade, e saída curta.
  static const int reducedCrossfade = 240;
  static const int reducedExit = 200;
}

/// Posições da abertura para uma tela: Portal inicial (igual ao splash nativo) e final (no wordmark).
class IntroLayout {
  IntroLayout._({
    required this.size,
    required this.portalStart,
    required this.portalEnd,
    required this.wordOrigin,
    required this.scale,
    required this.slogan,
    required this.sloganOffset,
  });

  /// Altura do Portal no splash nativo (dp): `sancta_portal_splash.xml` e `launch_background.xml`.
  static const double nativePortalHeight = 110;

  /// Texto do slogan (lockup oficial).
  static const String sloganText = 'Sua jornada de fé, todos os dias.';

  /// Calcula a composição para [size]. [startCenter] = centro do Portal no splash nativo (centro da
  /// TELA; quando a janela do Flutter não cobre a barra de navegação, ele fica abaixo do centro da janela).
  factory IntroLayout.compute(Size size, {Offset? startCenter, TextScaler textScaler = TextScaler.noScaling}) {
    // Wordmark com ~58% da largura (como no mockup "Abertura do app"), entre 180 e 340 px.
    final wordWidth = (size.width * .58).clamp(180.0, 340.0);
    final s = wordWidth / WordmarkGlyphs.width;
    // Slogan: corpo do lockup (0,148 do em) com piso de 14 px para leitura no celular.
    final fontSize = math.max(148 * s, 14.0);
    final slogan = TextPainter(
      text: TextSpan(
        text: sloganText,
        style: TextStyle(fontFamily: BrandFonts.figtree, fontWeight: FontWeight.w400, fontSize: fontSize, height: 1.25, color: BrandColors.nevoa),
      ),
      textAlign: TextAlign.center,
      textDirection: TextDirection.ltr,
      textScaler: textScaler,
      maxLines: 2,
    )..layout(maxWidth: math.max(0, size.width - 48));
    // Linha de base do slogan 394 u abaixo da do wordmark (proporção do lockup, escalada com o texto).
    final gap = textScaler.scale(fontSize) * 394 / 148;
    final ascent = slogan.computeDistanceToActualBaseline(TextBaseline.alphabetic);
    final blockHeight = WordmarkGlyphs.baseline * s + gap + (slogan.height - ascent);
    final origin = Offset((size.width - wordWidth) / 2, (size.height - blockHeight) / 2);
    final portalEnd = Rect.fromLTWH(
      origin.dx + WordmarkGlyphs.portalLeft * s,
      origin.dy + WordmarkGlyphs.portalTop * s,
      PortalGeometry.width * s,
      PortalGeometry.letter.height * s,
    );
    final portalStart = Rect.fromCenter(
      center: startCenter ?? size.center(Offset.zero),
      width: nativePortalHeight * PortalGeometry.letter.aspectRatio,
      height: nativePortalHeight,
    );
    return IntroLayout._(
      size: size,
      portalStart: portalStart,
      portalEnd: portalEnd,
      wordOrigin: origin,
      scale: s,
      slogan: slogan,
      sloganOffset: Offset((size.width - slogan.width) / 2, origin.dy + WordmarkGlyphs.baseline * s + gap - ascent),
    );
  }

  final Size size;

  /// Portal no primeiro quadro (mesma posição e tamanho do splash nativo).
  final Rect portalStart;

  /// Portal no lugar do "n" do wordmark.
  final Rect portalEnd;

  /// Canto superior esquerdo da tinta do wordmark e escala (px por unidade da fonte).
  final Offset wordOrigin;
  final double scale;

  final TextPainter slogan;
  final Offset sloganOffset;

  void dispose() => slogan.dispose();
}

/// Pinta um quadro da abertura.
///
/// [progress] 0–1 percorre a [IntroTimeline] (ou, com [reduced], o fade Portal → wordmark);
/// [breath] 0–1 é a "luz respirando" na abertura do wordmark enquanto o app termina de carregar.
class IntroPainter extends CustomPainter {
  IntroPainter({required this.layout, required this.progress, required this.breath, this.reduced = false})
    : super(repaint: Listenable.merge([progress, breath]));

  final IntroLayout layout;
  final Animation<double> progress;
  final Animation<double> breath;
  final bool reduced;

  static double _iv(double t, int start, int end) => ((t - start) / (end - start)).clamp(0.0, 1.0);

  @override
  void paint(Canvas canvas, Size size) {
    canvas.drawRect(Offset.zero & size, Paint()..color = BrandColors.azulManto);
    final l = layout;
    if (reduced) {
      final p = Curves.easeInOut.transform(progress.value);
      if (p < 1) paintLitPortal(canvas, l.portalStart, opacity: 1 - p);
      if (p > 0) _paintLockup(canvas, letters: p, portal: l.portalEnd, portalOpacity: p, slogan: p, sloganRise: 0);
      return;
    }

    final t = progress.value * IntroTimeline.total;
    final slide = SanctaMotion.emphasized.transform(_iv(t, IntroTimeline.slideStart, IntroTimeline.slideEnd));
    final portal = Rect.lerp(l.portalStart, l.portalEnd, slide)!;

    // Luz: aurora + brilho da cruz durante a sequência; depois, a respiração calma da espera.
    final double light, glow, rise;
    if (progress.value < 1) {
      final settle = 1 - Curves.easeIn.transform(_iv(t, IntroTimeline.lightOutStart, IntroTimeline.lightOutEnd));
      rise = SanctaMotion.enter.transform(_iv(t, IntroTimeline.dawnStart, IntroTimeline.dawnEnd));
      light = rise * settle;
      glow = Curves.easeOut.transform(_iv(t, IntroTimeline.glowStart, IntroTimeline.glowEnd)) * settle;
    } else {
      rise = 1;
      light = .55 * SanctaMotion.breathing.transform(breath.value);
      glow = 0;
    }

    _paintLockup(
      canvas,
      letters: t,
      portal: portal,
      portalOpacity: 1,
      light: light,
      rise: rise,
      glow: glow,
      slogan: SanctaMotion.enter.transform(_iv(t, IntroTimeline.sloganStart, IntroTimeline.sloganEnd)),
      sloganRise: 6,
      timed: true,
    );
  }

  /// Letras + Portal + slogan. Com [timed], [letters] é o tempo em ms (entrada letra a letra);
  /// senão é a opacidade comum de todas as letras.
  void _paintLockup(
    Canvas canvas, {
    required double letters,
    required Rect portal,
    required double portalOpacity,
    required double slogan,
    required double sloganRise,
    double light = 0,
    double rise = 1,
    double glow = 0,
    bool timed = false,
  }) {
    final l = layout;
    final paint = Paint()..isAntiAlias = true;
    // Letras primeiro: parecem sair de trás do Portal.
    for (final letter in WordmarkLayers.letters) {
      double p;
      if (timed) {
        final start = IntroTimeline.lettersStart + letter.order * IntroTimeline.letterStagger;
        p = SanctaMotion.enter.transform(_iv(letters, start, start + IntroTimeline.letterDuration));
      } else {
        p = letters;
      }
      if (p <= 0) continue;
      final dx = timed ? -letter.side * (1 - p) * 120 * l.scale : 0.0;
      canvas.save();
      canvas.translate(l.wordOrigin.dx + dx, l.wordOrigin.dy);
      canvas.scale(l.scale);
      canvas.drawPath(letter.path, paint..color = BrandColors.pergaminho.withValues(alpha: p));
      canvas.restore();
    }

    paintLitPortal(canvas, portal, light: light, rise: rise, glow: glow, opacity: portalOpacity);

    if (slogan > 0) {
      final offset = l.sloganOffset.translate(0, (1 - slogan) * sloganRise);
      final bounds = offset & l.slogan.size;
      canvas.saveLayer(bounds.inflate(4), Paint()..color = Color.fromRGBO(0, 0, 0, slogan));
      l.slogan.paint(canvas, offset);
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(IntroPainter oldDelegate) =>
      oldDelegate.layout != layout || oldDelegate.reduced != reduced || oldDelegate.progress != progress || oldDelegate.breath != breath;
}
