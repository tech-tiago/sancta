import 'package:flutter/widgets.dart';

import 'brand_colors.dart';
import 'portal_geometry.dart';

/// O símbolo **Portal** desenhado em código (nítido em qualquer tamanho, cores configuráveis).
///
/// - Padrão: arco Azul Manto + cruz Ouro (fundos claros).
/// - [PortalMark.pergaminho]: arco Pergaminho + cruz Ouro (fundos azuis).
/// - [PortalMark.mono]: arco e cruz numa só cor.
///
/// Tamanho mínimo recomendado: 16 px de altura (abaixo disso use só o ícone do app).
class PortalMark extends StatelessWidget {
  const PortalMark({
    super.key,
    this.height = 48,
    this.shape = PortalShape.letter,
    this.arcColor = BrandColors.azulManto,
    this.crossColor = BrandColors.ouro,
    this.openingColor,
    this.semanticLabel = 'Sancta',
  });

  /// Arco Pergaminho + cruz Ouro, para fundos Azul Manto/Noite/Tinta.
  const PortalMark.pergaminho({super.key, this.height = 48, this.shape = PortalShape.letter, this.openingColor, this.semanticLabel = 'Sancta'})
    : arcColor = BrandColors.pergaminho,
      crossColor = BrandColors.ouro;

  /// Versão monocromática (arco e cruz na mesma cor).
  const PortalMark.mono({super.key, required Color color, this.height = 48, this.shape = PortalShape.letter, this.semanticLabel = 'Sancta'})
    : arcColor = color,
      crossColor = color,
      openingColor = null;

  /// Altura em px (a largura segue a proporção do Portal).
  final double height;

  /// Proporção da letra (padrão) ou da porta (ícone).
  final PortalShape shape;

  final Color arcColor;
  final Color crossColor;

  /// Cor opcional para preencher a abertura (ex.: brilho ao concluir uma oração).
  final Color? openingColor;

  /// Rótulo de acessibilidade; `null` exclui o símbolo da árvore semântica (uso decorativo).
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) {
    final geometry = PortalGeometry.of(shape);
    final mark = SizedBox(
      width: height * geometry.aspectRatio,
      height: height,
      child: CustomPaint(
        painter: PortalPainter(geometry: geometry, arcColor: arcColor, crossColor: crossColor, openingColor: openingColor),
      ),
    );
    if (semanticLabel == null) return ExcludeSemantics(child: mark);
    return Semantics(label: semanticLabel, image: true, child: mark);
  }
}

/// Pinta o Portal com [PortalGeometry]. Pode ser reutilizado por animações (ex.: [crossOpacity]).
class PortalPainter extends CustomPainter {
  const PortalPainter({required this.geometry, required this.arcColor, required this.crossColor, this.openingColor, this.crossOpacity = 1});

  final PortalGeometry geometry;
  final Color arcColor;
  final Color crossColor;
  final Color? openingColor;

  /// Opacidade da cruz (0–1), multiplicada pela opacidade de [crossColor].
  final double crossOpacity;

  @override
  void paint(Canvas canvas, Size size) {
    if (size.isEmpty) return;
    final fill = Paint()
      ..isAntiAlias = true
      ..style = PaintingStyle.fill;
    if (openingColor != null) {
      canvas.drawPath(geometry.opening(size), fill..color = openingColor!);
    }
    canvas.drawPath(geometry.arch(size), fill..color = arcColor);
    if (crossOpacity > 0) {
      canvas.drawPath(geometry.crossPath(size), fill..color = crossColor.withValues(alpha: crossColor.a * crossOpacity.clamp(0.0, 1.0)));
    }
  }

  @override
  bool shouldRepaint(PortalPainter oldDelegate) =>
      oldDelegate.geometry != geometry ||
      oldDelegate.arcColor != arcColor ||
      oldDelegate.crossColor != crossColor ||
      oldDelegate.openingColor != openingColor ||
      oldDelegate.crossOpacity != crossOpacity;
}
