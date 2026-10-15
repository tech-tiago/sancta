import 'dart:ui';

import 'wordmark_glyphs.dart';

export 'wordmark_glyphs.dart';

/// Uma letra do wordmark, pronta para pintar (contorno em unidades da fonte, y para baixo).
class WordmarkLetter {
  const WordmarkLetter({required this.char, required this.path, required this.side, required this.order});

  final String char;
  final Path path;

  /// -1 = à esquerda do Portal ("Sa"), 1 = à direita ("cta").
  final int side;

  /// Ordem de entrada a partir do Portal (0 = vizinhas do Portal).
  final int order;
}

/// As letras do wordmark oficial, convertidas uma única vez (os `Path` são reaproveitados a cada quadro
/// e escalados com `Canvas.scale`).
abstract final class WordmarkLayers {
  static final List<WordmarkLetter> letters = [
    for (final (char, d, side, order) in WordmarkGlyphs.letters) WordmarkLetter(char: char, path: parseSvgPath(d), side: side, order: order),
  ];
}

final _token = RegExp(r'[A-Za-z]|-?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?');

/// Converte um atributo `d` de SVG em [Path]. Suporta os comandos **absolutos** gerados pelo
/// opentype.js para contornos de fonte (M, L, H, V, Q, C, Z) — o suficiente para o wordmark.
Path parseSvgPath(String d) {
  final tokens = _token.allMatches(d).map((m) => m.group(0)!).toList();
  final path = Path();
  var i = 0;
  var command = '';
  var x = 0.0, y = 0.0;
  double next() {
    final value = i < tokens.length ? double.tryParse(tokens[i++]) : null;
    if (value == null) throw FormatException('Contorno SVG inválido ou incompleto', d);
    return value;
  }

  while (i < tokens.length) {
    final token = tokens[i];
    if (RegExp(r'^[A-Za-z]$').hasMatch(token)) {
      command = token;
      i++;
      if (command == 'Z' || command == 'z') {
        path.close();
        continue;
      }
    }
    switch (command) {
      case 'M':
        x = next();
        y = next();
        path.moveTo(x, y);
        command = 'L'; // pares seguintes a um M são linhas
      case 'L':
        x = next();
        y = next();
        path.lineTo(x, y);
      case 'H':
        x = next();
        path.lineTo(x, y);
      case 'V':
        y = next();
        path.lineTo(x, y);
      case 'Q':
        final x1 = next(), y1 = next();
        x = next();
        y = next();
        path.quadraticBezierTo(x1, y1, x, y);
      case 'C':
        final x1 = next(), y1 = next(), x2 = next(), y2 = next();
        x = next();
        y = next();
        path.cubicTo(x1, y1, x2, y2, x, y);
      default:
        throw FormatException('Comando SVG não suportado: $command', d);
    }
  }
  return path;
}
