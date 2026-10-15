import 'package:flutter/widgets.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Variantes oficiais do wordmark "Sa[portal]cta".
enum WordmarkVariant {
  /// Letras Pergaminho + cruz Ouro — sobre Azul Manto, Noite ou Tinta.
  pergaminho,

  /// Letras Azul Manto + cruz Ouro — sobre Pergaminho ou branco.
  azul,

  /// Tudo branco (cruz na mesma cor) — sobre fotos e fundos escuros.
  monoBranco,

  /// Tudo Tinta (cruz na mesma cor) — impressão e fundos claros neutros.
  monoTinta,
}

/// Wordmark **Sancta** (Figtree Bold em curvas, com o Portal no lugar do "n").
///
/// [height] é a altura da tinta (do topo do "S" ao pé das letras); a largura segue [aspectRatio].
/// Tamanho mínimo: 16 px de altura. Área de proteção: a altura do Portal ao redor (ver MARCA.md).
/// Para uma cor fora das variantes (ex.: tema), passe [color]: usa a versão mono tingida.
class SanctaWordmark extends StatelessWidget {
  const SanctaWordmark({super.key, this.variant = WordmarkVariant.azul, this.height = 28, this.color, this.semanticLabel = 'Sancta'});

  /// Largura ÷ altura da tinta do wordmark (3041,04 × 724 unidades da fonte).
  static const double aspectRatio = 3041.04 / 724;

  /// Fração da altura ocupada pelo Portal (512 de 724): útil para alinhar o símbolo ao wordmark.
  static const double portalHeightFactor = 512 / 724;

  /// Caminho do SVG de cada variante.
  static String assetFor(WordmarkVariant variant) => switch (variant) {
    WordmarkVariant.pergaminho => 'assets/brand/wordmark_pergaminho.svg',
    WordmarkVariant.azul => 'assets/brand/wordmark_azul.svg',
    WordmarkVariant.monoBranco => 'assets/brand/wordmark_mono_branco.svg',
    WordmarkVariant.monoTinta => 'assets/brand/wordmark_mono_tinta.svg',
  };

  final WordmarkVariant variant;
  final double height;

  /// Cor única opcional (ignora [variant] e tinge a versão monocromática).
  final Color? color;

  final String? semanticLabel;

  @override
  Widget build(BuildContext context) => SvgPicture.asset(
    color == null ? assetFor(variant) : assetFor(WordmarkVariant.monoBranco),
    height: height,
    width: height * aspectRatio,
    fit: BoxFit.contain,
    colorFilter: color == null ? null : ColorFilter.mode(color!, BlendMode.srcIn),
    semanticsLabel: semanticLabel,
    excludeFromSemantics: semanticLabel == null,
  );
}
