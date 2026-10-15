// Gera lib/features/splash/wordmark_glyphs.dart a partir de assets/brand/wordmark_pergaminho.svg (só Node, sem dependências).
// Uso (na pasta app/): node test/motion/tool/gen_wordmark_glyphs.js .   e depois: dart format lib/features/splash
const fs = require('fs');
const path = require('path');
const root = process.argv[2];
const svg = fs.readFileSync(path.join(root, 'assets/brand/wordmark_pergaminho.svg'), 'utf8');
const m = [...svg.matchAll(/<path fill="([^"]+)" d="([^"]+)"/g)];
const subs = m[0][2].split(/(?=M)/);
const vb = svg.match(/viewBox="0 0 ([\d.]+) ([\d.]+)"/);
const glyphs = [
  ['S', subs[0], -1, 1],
  ['a', subs[1] + subs[2], -1, 0],
  ['c', subs[3], 1, 0],
  ['t', subs[4], 1, 1],
  ['a', subs[5] + subs[6], 1, 2],
];
const portal = subs[7];
const pm = portal.match(/^M([\d.]+) ([\d.]+)V([\d.]+)A([\d.]+)/);
const left = Number(pm[1]);
const base = Number(pm[2]);
const top = Number(pm[3]) - Number(pm[4]);

const lines = [];
lines.push('// GERADO a partir de assets/brand/wordmark_pergaminho.svg (contornos da Figtree Bold do wordmark');
lines.push('// oficial) por test/motion/tool/gen_wordmark_glyphs.js. Não edite à mão: test/motion/intro_test.dart');
lines.push('// confere que continuam iguais aos do SVG (se a marca mudar o wordmark, gere de novo — ver MOTION.md).');
lines.push('');
lines.push('/// Contornos do wordmark oficial "Sa[Portal]cta" separados por letra, para animar a abertura.');
lines.push('///');
lines.push('/// Unidades da fonte (Figtree Bold, 1000 por em), eixo y para baixo, como no viewBox do SVG.');
lines.push('abstract final class WordmarkGlyphs {');
lines.push('  /// Largura e altura da tinta do wordmark (viewBox do SVG).');
lines.push(`  static const double width = ${Number(vb[1])};`);
lines.push(`  static const double height = ${Number(vb[2])};`);
lines.push('');
lines.push('  /// Linha de base (y): as letras descem até 724 (overshoot); o Portal pousa em 712.');
lines.push(`  static const double baseline = ${base};`);
lines.push('');
lines.push('  /// Canto superior esquerdo do Portal no lugar do "n" (486 × 512, como `PortalGeometry.letter`).');
lines.push(`  static const double portalLeft = ${left};`);
lines.push(`  static const double portalTop = ${top};`);
lines.push('');
lines.push('  /// Letras: (letra, contorno SVG, lado do Portal: -1 = "Sa" / 1 = "cta", ordem de entrada a partir do Portal).');
lines.push('  static const List<(String, String, int, int)> letters = [');
for (const g of glyphs) lines.push(`    ('${g[0]}', '${g[1]}', ${g[2]}, ${g[3]}),`);
lines.push('  ];');
lines.push('}');
lines.push('');
fs.writeFileSync(path.join(root, 'lib/features/splash/wordmark_glyphs.dart'), lines.join('\n'));
console.log('ok', left, base, top);
