'use strict';
/** Sancta — utilitários do gerador de marca: tokens, wordmark (Figtree Bold em curvas), SVG e PNG. */
const fs = require('fs');
const path = require('path');
const opentype = require('opentype.js');
const { Resvg } = require('@resvg/resvg-js');
const { PORTAL, portal, r3 } = require('./portal');

const ROOT = process.env.SANCTA_ROOT || path.resolve(__dirname, '..', '..');
const P = (...a) => path.join(ROOT, ...a);

/** Tokens oficiais + derivações documentadas em docs/design/MARCA.md. */
const T = {
  azul: '#1F3D9C', // Azul Manto
  noite: '#132760', // Noite
  tinta: '#0E1A3A', // Tinta
  ouro: '#E3B65B', // Ouro (só a cruz e destaques)
  pergaminho: '#FBF6EC', // Pergaminho
  branco: '#FFFFFF',
  nevoa: '#C8D5FF', // derivação: texto secundário sobre Azul Manto (slogan)
  tintaSuave: '#5A6487', // derivação: texto secundário sobre fundos claros
};

const fonte = (nome) => opentype.loadSync(P('app/assets/fonts', nome));
const bold = fonte('Figtree-Bold.ttf');
const regular = fonte('Figtree-Regular.ttf');
const semibold = fonte('Figtree-SemiBold.ttf');

const SLOGAN = 'Sua jornada de fé, todos os dias.';

/**
 * Wordmark "Sa[portal]cta". Espaçamento: métricas e kerning da Figtree Bold + ajuste óptico medido na
 * referência aprovada (S→a −18 u, c→t −18 u, t→a −15 u). O Portal ocupa o lugar do n, centrado no olho do n.
 */
function wordmarkGeom() {
  const seq = 'Sancta';
  const extra = [0, -18, 0, 0, -18, -15];
  let x = 0;
  const items = [];
  for (let i = 0; i < seq.length; i++) {
    x += extra[i];
    const g = bold.charToGlyph(seq[i]);
    items.push({ ch: seq[i], g, x });
    x += g.advanceWidth;
    if (i < seq.length - 1) x += bold.getKerningValue(g, bold.charToGlyph(seq[i + 1]));
  }
  let minX = Infinity;
  let maxX = -Infinity;
  let minY = Infinity;
  let maxY = -Infinity;
  for (const it of items) {
    if (it.ch === 'n') {
      const nb = it.g.getBoundingBox();
      it.cx = it.x + (nb.x1 + nb.x2) / 2; // centro do olho do n (57 + 463/2)
      minX = Math.min(minX, it.cx - PORTAL.raioExterno);
      maxX = Math.max(maxX, it.cx + PORTAL.raioExterno);
      maxY = Math.max(maxY, PORTAL.letra.altura);
      minY = Math.min(minY, 0);
      continue;
    }
    const bb = it.g.getBoundingBox();
    minX = Math.min(minX, it.x + bb.x1);
    maxX = Math.max(maxX, it.x + bb.x2);
    minY = Math.min(minY, bb.y1);
    maxY = Math.max(maxY, bb.y2);
  }
  return { items, minX, maxX, minY, maxY, largura: maxX - minX, altura: maxY - minY };
}
const WM = wordmarkGeom();

/** Paths do wordmark com o canto superior esquerdo da tinta em (ox, oy) e escala s (px por unidade). */
function wordmarkPaths(ox, oy, s) {
  const base = oy + WM.maxY * s;
  const letras = WM.items
    .filter((i) => i.ch !== 'n')
    .map((i) => i.g.getPath(ox + (i.x - WM.minX) * s, base, 1000 * s).toPathData(2))
    .join('');
  const n = WM.items.find((i) => i.ch === 'n');
  const pt = portal('letra', { cx: ox + (n.cx - WM.minX) * s, base, k: s });
  return { letras, arco: pt.arco, cruz: pt.cruz, base, largura: WM.largura * s, altura: WM.altura * s };
}

/** Texto em curvas (centrado em cx ou alinhado à esquerda em x) com linha de base em y. */
function textoPath(font, texto, size, { x, cx, y }) {
  const pth = font.getPath(texto, 0, 0, size);
  const bb = pth.getBoundingBox();
  const left = cx !== undefined ? cx - (bb.x1 + bb.x2) / 2 : x - bb.x1;
  return { d: font.getPath(texto, left, y, size).toPathData(2), largura: bb.x2 - bb.x1, topo: y + bb.y1, fundo: y + bb.y2 };
}

function svgDoc(w, h, body, { title, dw, dh, extra = '' } = {}) {
  return (
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${r3(w)} ${r3(h)}" width="${r3(dw ?? w)}" height="${r3(dh ?? h)}"${extra}>` +
    (title ? `<title>${title}</title>` : '') +
    `\n${body}\n</svg>\n`
  );
}

function write(rel, content) {
  const f = P(rel);
  fs.mkdirSync(path.dirname(f), { recursive: true });
  fs.writeFileSync(f, content);
  return f;
}

/** SVG → PNG (resvg). width em px (altura proporcional) ou {width,height} exatos pelo viewBox. */
function png(svg, rel, width) {
  const r = new Resvg(svg, { fitTo: { mode: 'width', value: width }, font: { loadSystemFonts: false }, shapeRendering: 2 });
  const out = r.render();
  const buf = out.asPng();
  if (rel) write(rel, buf);
  return { buf, w: out.width, h: out.height, pixels: out.pixels };
}

/** Arquivo .ico com várias imagens PNG embutidas (formato aceito pelos navegadores e pelo Windows Vista+). */
function ico(pngs) {
  const n = pngs.length;
  const head = Buffer.alloc(6 + 16 * n);
  head.writeUInt16LE(0, 0);
  head.writeUInt16LE(1, 2);
  head.writeUInt16LE(n, 4);
  let offset = head.length;
  pngs.forEach(({ buf, size }, i) => {
    const o = 6 + 16 * i;
    head.writeUInt8(size >= 256 ? 0 : size, o);
    head.writeUInt8(size >= 256 ? 0 : size, o + 1);
    head.writeUInt8(0, o + 2);
    head.writeUInt8(0, o + 3);
    head.writeUInt16LE(1, o + 4);
    head.writeUInt16LE(32, o + 6);
    head.writeUInt32LE(buf.length, o + 8);
    head.writeUInt32LE(offset, o + 12);
    offset += buf.length;
  });
  return Buffer.concat([head, ...pngs.map((p) => p.buf)]);
}

/** VectorDrawable Android. paths: [{d, fill, alpha}] já nas coordenadas do viewport. */
function vectorDrawable({ dp, dpH, vw, vh, paths, comentario }) {
  const body = paths
    .map(
      (p) =>
        `    <path\n        android:fillColor="${p.fill}"` +
        (p.alpha !== undefined && p.alpha !== 1 ? `\n        android:fillAlpha="${p.alpha}"` : '') +
        `\n        android:pathData="${p.d}" />`,
    )
    .join('\n');
  return (
    `<?xml version="1.0" encoding="utf-8"?>\n<!-- ${comentario} Gerado por brand/source/build-brand.js — não edite à mão. -->\n` +
    `<vector xmlns:android="http://schemas.android.com/apk/res/android"\n    android:width="${r3(dp)}dp"\n    android:height="${r3(dpH ?? dp)}dp"\n` +
    `    android:viewportWidth="${r3(vw)}"\n    android:viewportHeight="${r3(vh)}">\n${body}\n</vector>\n`
  );
}

module.exports = { ROOT, P, T, bold, regular, semibold, SLOGAN, WM, wordmarkPaths, textoPath, svgDoc, write, png, ico, vectorDrawable, portal, PORTAL, r3 };
