'use strict';
/**
 * Sancta — gerador de TODOS os assets da marca "Portal" (SVG masters, PNG, Android, web, app).
 *
 * Uso (Node 18+; dependências instaladas FORA do projeto, numa pasta temporária):
 *   npm i --prefix <pasta-temp> opentype.js@1 @resvg/resvg-js@2
 *   NODE_PATH=<pasta-temp>/node_modules node brand/source/build-brand.js
 * A raiz do projeto é detectada a partir deste arquivo (ou SANCTA_ROOT).
 * Fontes usadas: app/assets/fonts/Figtree-*.ttf (OFL). Saídas: ver lista impressa ao final e docs/design/MARCA.md.
 */
const fs = require('fs');
const L = require('./lib');
const A = require('./art');

const { T, P, WM, wordmarkPaths, textoPath, svgDoc, write, png, ico, vectorDrawable, portal, r3 } = L;

const saidas = [];
const out = (rel, content) => {
  write(rel, content);
  saidas.push(rel);
};
const outPng = (svg, rel, w) => {
  const r = png(svg, rel, w);
  saidas.push(`${rel} (${r.w}×${r.h})`);
  return r;
};
const rm = (rel) => {
  const f = P(rel);
  if (fs.existsSync(f)) {
    fs.rmSync(f, { recursive: true, force: true });
    saidas.push(`(removido) ${rel}`);
  }
};

// ---------------------------------------------------------------------------------------------
// 1. Símbolo (Portal na proporção da letra: 486 × 512 u)
// ---------------------------------------------------------------------------------------------
const SIMBOLO = {
  pergaminho: { arco: T.pergaminho, cruz: T.ouro, uso: 'para fundos azuis' },
  azul: { arco: T.azul, cruz: T.ouro, uso: 'para fundos claros' },
  'mono-branco': { mono: T.branco, uso: 'monocromático branco (cruz na mesma cor)' },
  'mono-tinta': { mono: T.tinta, uso: 'monocromático escuro (cruz na mesma cor)' },
};

function simbolo(v, { cor } = {}) {
  const pt = portal('letra', { cx: 243, base: 512, k: 1 });
  const s = SIMBOLO[v];
  const body = s.mono || cor
    ? `<path fill="${cor || s.mono}" d="${pt.arco}${pt.cruz}"/>`
    : `<path fill="${s.arco}" d="${pt.arco}"/>\n<path fill="${s.cruz}" d="${pt.cruz}"/>`;
  return svgDoc(486, 512, body, { title: 'Sancta — Portal', dw: 97.2, dh: 102.4 });
}

// ---------------------------------------------------------------------------------------------
// 2. Wordmark, lockup com slogan e "Sancta Gestão"
// ---------------------------------------------------------------------------------------------
const WMV = {
  pergaminho: { letras: T.pergaminho, cruz: T.ouro, slogan: T.nevoa },
  azul: { letras: T.azul, cruz: T.ouro, slogan: T.tintaSuave },
  'mono-branco': { letras: T.branco, cruz: T.branco, slogan: T.branco },
  'mono-tinta': { letras: T.tinta, cruz: T.tinta, slogan: T.tinta },
};

function wmBody(wp, v) {
  const c = WMV[v];
  return c.letras === c.cruz
    ? `<path fill="${c.letras}" d="${wp.letras}${wp.arco}${wp.cruz}"/>`
    : `<path fill="${c.letras}" d="${wp.letras}${wp.arco}"/>\n<path fill="${c.cruz}" d="${wp.cruz}"/>`;
}

function wordmark(v) {
  const wp = wordmarkPaths(0, 0, 1);
  return svgDoc(WM.largura, WM.altura, wmBody(wp, v), { title: 'Sancta', dw: WM.largura / 10, dh: WM.altura / 10 });
}

// Slogan: Figtree Regular, corpo 148 u (0,148 do em do wordmark), linha de base 394 u abaixo da do wordmark.
const SLOGAN_CORPO = 148;
const SLOGAN_BASE = 394;

function lockup(v) {
  const wp = wordmarkPaths(0, 0, 1);
  const sl = textoPath(L.regular, L.SLOGAN, SLOGAN_CORPO, { cx: WM.largura / 2, y: wp.base + SLOGAN_BASE });
  const h = sl.fundo;
  return svgDoc(WM.largura, h, `${wmBody(wp, v)}\n<path fill="${WMV[v].slogan}" d="${sl.d}"/>`, {
    title: 'Sancta — Sua jornada de fé, todos os dias.',
    dw: WM.largura / 10,
    dh: h / 10,
  });
}

function gestao(tema) {
  const v = tema === 'claro' ? 'azul' : 'pergaminho';
  const cores = tema === 'claro' ? { txt: T.tintaSuave, fio: T.azul, op: 0.25 } : { txt: T.nevoa, fio: T.nevoa, op: 0.4 };
  const wp = wordmarkPaths(0, 0, 1);
  const gap = 150;
  const fioX = WM.largura + gap;
  const fioW = 18;
  const tx = fioX + fioW + gap;
  const t = textoPath(L.semibold, 'Gestão', 430, { x: tx, y: wp.base });
  const w = tx + t.largura;
  const body = `${wmBody(wp, v)}\n<rect x="${r3(fioX)}" y="${r3(wp.base - 560)}" width="${fioW}" height="${r3(600)}" rx="${fioW / 2}" fill="${cores.fio}" fill-opacity="${cores.op}"/>\n<path fill="${cores.txt}" d="${t.d}"/>`;
  return svgDoc(w, WM.altura, body, { title: 'Sancta Gestão', dw: w / 10, dh: WM.altura / 10 });
}

// ---------------------------------------------------------------------------------------------
// 3. Ícone do app (porta em proporção vertical)
// ---------------------------------------------------------------------------------------------
const ICONE = {
  padrao: { fundo: T.azul, arco: T.pergaminho, cruz: T.ouro, nome: 'Padrão' },
  escuro: { fundo: T.tinta, arco: T.pergaminho, cruz: T.ouro, nome: 'Escuro' },
  tingido: { fundo: '#111111', arco: '#DADADA', cruz: '#9B9B9B', nome: 'Tingido' },
};
const ICONE_ALTURA = 0.595; // altura do Portal / lado do ícone (medido no mockup aprovado)

function iconeSvg({ size = 1024, v = 'padrao', raio = 0, h = ICONE_ALTURA, pesoCruz = 1, transparente = false, forma = 'quadrado' }) {
  const c = ICONE[v];
  const k = (h * size) / 666;
  const pt = portal('porta', { cx: size / 2, base: size / 2 + 333 * k, k, pesoCruz });
  let fundo = '';
  if (!transparente) {
    fundo = forma === 'circulo'
      ? `<circle cx="${size / 2}" cy="${size / 2}" r="${size / 2}" fill="${c.fundo}"/>`
      : `<rect width="${size}" height="${size}"${raio ? ` rx="${r3(raio * size)}"` : ''} fill="${c.fundo}"/>`;
  }
  return svgDoc(size, size, `${fundo}\n<path fill="${c.arco}" d="${pt.arco}"/>\n<path fill="${c.cruz}" d="${pt.cruz}"/>`, { title: `Sancta — ícone ${c.nome}` });
}

/**
 * Versões "hintadas" (alinhadas à grade de pixels) para 16/32/48 px: hastes e cruz em pixels inteiros,
 * Portal um pouco maior no quadro e cruz mais encorpada — o mesmo desenho, ajustado opticamente.
 */
const HINT = {
  16: { x0: 4, x1: 13, haste: 2, topo: 3, base: 14, raio: 3.5, cruz: [8, 9, 7, 12, 7, 10, 8, 9] },
  32: { x0: 8, x1: 24, haste: 4, topo: 5, base: 27, raio: 7, cruz: [15, 17, 14, 24, 13, 19, 16, 18] },
  48: { x0: 12, x1: 37, haste: 6, topo: 7, base: 41, raio: 10.5, cruz: [23, 26, 20, 35, 20, 29, 23, 26] },
};

function iconeHint(N, { v = 'padrao', raio = true } = {}) {
  const s = HINT[N];
  const c = ICONE[v];
  const R = (s.x1 - s.x0) / 2;
  const r = R - s.haste;
  const cy = s.topo + R;
  const arco = `M${s.x0} ${s.base}V${cy}A${R} ${R} 0 0 1 ${s.x1} ${cy}V${s.base}H${s.x1 - s.haste}V${cy}A${r} ${r} 0 0 0 ${s.x0 + s.haste} ${cy}V${s.base}Z`;
  const [x0, x1, y0, y1, bx0, bx1, by0, by1] = s.cruz;
  const cruz = `M${x0} ${y0}H${x1}V${by0}H${bx1}V${by1}H${x1}V${y1}H${x0}V${by1}H${bx0}V${by0}H${x0}Z`;
  const fundo = `<rect width="${N}" height="${N}"${raio ? ` rx="${s.raio}"` : ''} fill="${c.fundo}"/>`;
  return svgDoc(N, N, `${fundo}\n<path fill="${c.arco}" d="${arco}"/>\n<path fill="${c.cruz}" d="${cruz}"/>`, { title: 'Sancta' });
}

// ---------------------------------------------------------------------------------------------
// 4. Composições: imagem de compartilhamento (1200×630) e feature graphic (1024×500)
// ---------------------------------------------------------------------------------------------
function ogImage() {
  // Medidas do mockup aprovado: wordmark com em de 200 px, centrado; slogan 40 px Névoa.
  const s = 0.2;
  const wp = wordmarkPaths((1200 - WM.largura * s) / 2, 187, s);
  const sl = textoPath(L.regular, L.SLOGAN, 40, { cx: 600, y: 425.5 });
  const body = `<rect width="1200" height="630" fill="${T.azul}"/>\n<path fill="${T.pergaminho}" d="${wp.letras}${wp.arco}"/>\n<path fill="${T.ouro}" d="${wp.cruz}"/>\n<path fill="${T.nevoa}" d="${sl.d}"/>`;
  return svgDoc(1200, 630, body, { title: 'Sancta — Sua jornada de fé, todos os dias.' });
}

/** Moldura em arco (raio = largura/2) com um céu dentro (SVG aninhado, recorte por clipPath). */
function arcoComCeu(id, x, y, w, h, px) {
  const r = w / 2;
  const clip = `<clipPath id="${px}-arco"><path d="M${r3(x)} ${r3(y + h)}V${r3(y + r)}A${r3(r)} ${r3(r)} 0 0 1 ${r3(x + w)} ${r3(y + r)}V${r3(y + h)}Z"/></clipPath>`;
  const vw = 600;
  const vh = 800;
  const inner = A.ceu(id, vw, vh, { px, inner: true });
  return `${clip}<g clip-path="url(#${px}-arco)"><svg x="${r3(x)}" y="${r3(y)}" width="${r3(w)}" height="${r3(h)}" viewBox="0 0 ${vw} ${vh}" preserveAspectRatio="xMidYMax slice">${inner}</svg></g>`;
}

function featureGraphic() {
  const s = 0.145;
  const wp = wordmarkPaths(88, 168, s);
  const sl = textoPath(L.regular, L.SLOGAN, 26, { x: 88 + 40 * 0, y: wp.base + 66 });
  const body = [
    `<rect width="1024" height="500" fill="${T.azul}"/>`,
    arcoComCeu('aurora', 664, 92, 262, 408, 'fg'),
    `<path fill="${T.pergaminho}" d="${wp.letras}${wp.arco}"/>`,
    `<path fill="${T.ouro}" d="${wp.cruz}"/>`,
    `<path fill="${T.nevoa}" d="${sl.d}"/>`,
  ].join('\n');
  return svgDoc(1024, 500, body, { title: 'Sancta: Orações e Bíblia' });
}

// ---------------------------------------------------------------------------------------------
// 5. Android
// ---------------------------------------------------------------------------------------------
const DENS = { mdpi: 1, hdpi: 1.5, xhdpi: 2, xxhdpi: 3, xxxhdpi: 4 };
const RES = 'app/android/app/src/main/res';

/** Ícone legado (API < 26): quadrado arredondado de 44 dp num quadro de 48 dp. */
function launcherLegado(forma) {
  const k = (ICONE_ALTURA * 44) / 666;
  const pt = portal('porta', { cx: 24, base: 24 + 333 * k, k });
  const fundo = forma === 'round' ? `<circle cx="24" cy="24" r="22" fill="${T.azul}"/>` : `<rect x="2" y="2" width="44" height="44" rx="9.6" fill="${T.azul}"/>`;
  return svgDoc(48, 48, `${fundo}<path fill="${T.pergaminho}" d="${pt.arco}"/><path fill="${T.ouro}" d="${pt.cruz}"/>`);
}

/** Primeiro plano do ícone adaptativo (108 dp; Portal com 42,84 dp de altura, dentro da zona segura de 66 dp). */
function foregroundPaths() {
  const k = (ICONE_ALTURA * 72) / 666;
  return portal('porta', { cx: 54, base: 54 + 333 * k, k });
}

function android() {
  // Remove PNGs antigos que conflitariam ou não são mais usados
  for (const d of Object.keys(DENS)) {
    rm(`${RES}/drawable-${d}/ic_notification.png`);
    rm(`${RES}/mipmap-${d}/ic_launcher_background.png`);
  }
  for (const [d, f] of Object.entries(DENS)) {
    outPng(launcherLegado('square'), `${RES}/mipmap-${d}/ic_launcher.png`, 48 * f);
    outPng(launcherLegado('round'), `${RES}/mipmap-${d}/ic_launcher_round.png`, 48 * f);
    // Mantido (com a arte nova) porque os estilos de splash v31 ainda apontam para @mipmap/ic_launcher_foreground.
    const fg = foregroundPaths();
    outPng(svgDoc(108, 108, `<path fill="${T.pergaminho}" d="${fg.arco}"/><path fill="${T.ouro}" d="${fg.cruz}"/>`), `${RES}/mipmap-${d}/ic_launcher_foreground.png`, 108 * f);
  }

  const fg = foregroundPaths();
  out(
    `${RES}/drawable/sancta_portal_launcher_foreground.xml`,
    vectorDrawable({
      dp: 108,
      vw: 108,
      vh: 108,
      comentario: 'Ícone adaptativo (primeiro plano): Portal Pergaminho + cruz Ouro, 42,84 dp de altura, dentro da zona segura de 66 dp.',
      paths: [
        { d: fg.arco, fill: T.pergaminho },
        { d: fg.cruz, fill: T.ouro },
      ],
    }),
  );
  out(
    `${RES}/drawable/sancta_portal_launcher_monochrome.xml`,
    vectorDrawable({
      dp: 108,
      vw: 108,
      vh: 108,
      comentario: 'Ícone temático (Android 13+): só o alfa conta; arco 100% e cruz 55% (como a versão "Tingido").',
      paths: [
        { d: fg.arco, fill: '#FFFFFF' },
        { d: fg.cruz, fill: '#FFFFFF', alpha: 0.55 },
      ],
    }),
  );
  const adaptive = (nome) =>
    `<?xml version="1.0" encoding="utf-8"?>\n<!-- ${nome}: ícone adaptativo Sancta "Portal" (fundo Azul Manto, Portal vetorial e versão temática). Gerado por brand/source/build-brand.js. -->\n` +
    `<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">\n    <background android:drawable="@color/ic_launcher_background" />\n    <foreground android:drawable="@drawable/sancta_portal_launcher_foreground" />\n    <monochrome android:drawable="@drawable/sancta_portal_launcher_monochrome" />\n</adaptive-icon>\n`;
  out(`${RES}/mipmap-anydpi-v26/ic_launcher.xml`, adaptive('ic_launcher'));
  out(`${RES}/mipmap-anydpi-v26/ic_launcher_round.xml`, adaptive('ic_launcher_round'));
  out(
    `${RES}/values/ic_launcher_colors.xml`,
    `<?xml version="1.0" encoding="utf-8"?>\n<!-- Cor de fundo do ícone adaptativo (Azul Manto). Dono: marca (brand/source/build-brand.js). -->\n<resources>\n    <color name="ic_launcher_background">${T.azul}</color>\n</resources>\n`,
  );

  // Notificação: Portal aberto (arco + cruz na mesma cor) alinhado à grade de 24 dp (área útil 20 dp).
  // A silhueta cheia com cruz recortada lembra uma lápide — por isso a porta fica aberta, como no símbolo.
  const nArco = 'M4 22V10A8 8 0 0 1 20 10V22H16V9.5A4 4 0 0 0 8 9.5V22Z';
  const nCruz = 'M11 9H13V11H15V13H13V19H11V13H9V11H11Z';
  out(
    `${RES}/drawable/ic_notification.xml`,
    vectorDrawable({
      dp: 24,
      vw: 24,
      vh: 24,
      comentario: 'Ícone pequeno de notificação: Portal (arco + cruz) em branco; o sistema aplica a cor de destaque.',
      paths: [{ d: nArco + nCruz, fill: '#FFFFFF' }],
    }),
  );

  // Prévias em SVG dos vetores Android (para a vitrine brand/index.html e revisão visual).
  out('brand/android/ic_notification.svg', svgDoc(24, 24, `<path fill="${T.branco}" d="${nArco}${nCruz}"/>`, { title: 'ic_notification (24 dp)', dw: 96, dh: 96 }));
  out(
    'brand/android/ic_launcher_foreground.svg',
    svgDoc(108, 108, `<rect width="108" height="108" fill="${T.azul}"/><path fill="${T.pergaminho}" d="${fg.arco}"/><path fill="${T.ouro}" d="${fg.cruz}"/>`, { title: 'Ícone adaptativo 108 dp (fundo + primeiro plano)', dw: 216, dh: 216 }),
  );
  out(
    'brand/android/ic_launcher_monochrome.svg',
    svgDoc(108, 108, `<path fill="#000000" d="${fg.arco}"/><path fill="#000000" fill-opacity="0.55" d="${fg.cruz}"/>`, { title: 'Camada monocromática (Android 13+, só o alfa conta)', dw: 216, dh: 216 }),
  );

  // Portal para splash/widget (proporção da letra, 486 × 512 u).
  const k = 96 / 512;
  const pl = portal('letra', { cx: 243 * k, base: 96, k });
  out(
    `${RES}/drawable/sancta_portal.xml`,
    vectorDrawable({
      dp: 486 * k,
      dpH: 96,
      vw: 486 * k,
      vh: 96,
      comentario: 'Portal (proporção da letra) Pergaminho + cruz Ouro, para fundos Azul Manto (splash, widget).',
      paths: [
        { d: pl.arco, fill: T.pergaminho },
        { d: pl.cruz, fill: T.ouro },
      ],
    }),
  );
  out(
    `${RES}/drawable/sancta_portal_mono.xml`,
    vectorDrawable({
      dp: 486 * k,
      dpH: 96,
      vw: 486 * k,
      vh: 96,
      comentario: 'Portal monocromático (arco + cruz na mesma cor), branco — tingível com android:tint.',
      paths: [{ d: pl.arco + pl.cruz, fill: '#FFFFFF' }],
    }),
  );
  // Splash do Android 12+ (sem fundo de ícone): quadro de 288 dp, conteúdo dentro do círculo de 192 dp.
  const ks = 110 / 512;
  const ps = portal('letra', { cx: 144, base: 144 + 256 * ks, k: ks });
  out(
    `${RES}/drawable/sancta_portal_splash.xml`,
    vectorDrawable({
      dp: 288,
      vw: 288,
      vh: 288,
      comentario: 'Portal para windowSplashScreenAnimatedIcon (Android 12+): 288 dp, Portal com 110 dp de altura no círculo de 192 dp.',
      paths: [
        { d: ps.arco, fill: T.pergaminho },
        { d: ps.cruz, fill: T.ouro },
      ],
    }),
  );
  // Logo da splash legada (mesmo nome referenciado por drawable/launch_background.xml): arte nova.
  const kk = (0.66 * 512) / 512;
  const pk = portal('letra', { cx: 256, base: 256 + 256 * kk, k: kk });
  outPng(svgDoc(512, 512, `<path fill="${T.pergaminho}" d="${pk.arco}"/><path fill="${T.ouro}" d="${pk.cruz}"/>`), `${RES}/drawable/splash_logo.png`, 512);
}

// ---------------------------------------------------------------------------------------------
// 6. Execução
// ---------------------------------------------------------------------------------------------
function brand() {
  // limpa a marca antiga em brand/
  for (const f of ['png', 'web', 'android-adaptive-foreground.svg', 'og-image.svg', 'sancta-app-icon.svg', 'sancta-logo-horizontal-dark.svg', 'sancta-logo-horizontal-light.svg', 'sancta-mark-mono.svg', 'sancta-mark.svg']) {
    rm(`brand/${f}`);
  }
  // símbolo
  for (const v of Object.keys(SIMBOLO)) {
    const svg = simbolo(v);
    out(`brand/symbol/portal-${v}.svg`, svg);
    outPng(svg, `brand/symbol/png/portal-${v}-512.png`, 512);
  }
  // wordmark + lockups
  for (const v of Object.keys(WMV)) {
    const svg = wordmark(v);
    out(`brand/logo/sancta-wordmark-${v}.svg`, svg);
    outPng(svg, `brand/logo/png/sancta-wordmark-${v}-1600.png`, 1600);
    const lk = lockup(v);
    out(`brand/logo/sancta-lockup-slogan-${v}.svg`, lk);
    outPng(lk, `brand/logo/png/sancta-lockup-slogan-${v}-1600.png`, 1600);
  }
  for (const tema of ['claro', 'escuro']) {
    const g = gestao(tema);
    out(`brand/logo/sancta-gestao-${tema}.svg`, g);
    outPng(g, `brand/logo/png/sancta-gestao-${tema}-1200.png`, 1200);
  }
  // ícones do app (masters 1024, quadrado cheio — as plataformas aplicam a máscara)
  for (const v of Object.keys(ICONE)) {
    const svg = iconeSvg({ v });
    out(`brand/app-icon/icone-${v}.svg`, svg);
    outPng(svg, `brand/app-icon/png/icone-${v}-1024.png`, 1024);
  }
  out('brand/app-icon/icone-padrao-arredondado.svg', iconeSvg({ v: 'padrao', raio: 0.2237 }));
  // ilustrações
  for (const id of Object.keys(A.CEUS)) {
    const p = A.ceus[id](1200, 800);
    const rt = A.ceus[id](600, 800);
    out(`brand/illustrations/${id}.svg`, p);
    out(`brand/illustrations/${id}-retrato.svg`, rt);
    outPng(p, `brand/illustrations/png/${id}-1200x800.png`, 1200);
    outPng(rt, `brand/illustrations/png/${id}-retrato-600x800.png`, 600);
  }
  // posturas
  for (const id of Object.keys(A.POSTURAS)) {
    out(`brand/icons/posturas/${id}.svg`, A.postura(id, T.azul));
  }
  // Play Store
  outPng(iconeSvg({ v: 'padrao', size: 512 }), 'brand/play-store/icone-512.png', 512);
  const fgSvg = featureGraphic();
  out('brand/play-store/feature-graphic-1024x500.svg', fgSvg);
  outPng(fgSvg, 'brand/play-store/feature-graphic-1024x500.png', 1024);
  // compartilhamento
  const og = ogImage();
  out('brand/social/og-image-1200x630.svg', og);
  outPng(og, 'brand/social/og-image-1200x630.png', 1200);
}

function web() {
  const B = 'backend/public/images/brand';
  // nomes já referenciados pelo site/painel (mantidos)
  out(`${B}/sancta-mark.svg`, iconeSvg({ v: 'padrao', size: 256, raio: 0.2237 }));
  out(`${B}/sancta-mark-mono.svg`, simbolo('mono-branco', { cor: 'currentColor' }));
  out(`${B}/sancta-logo-horizontal-light.svg`, wordmark('azul'));
  out(`${B}/sancta-logo-horizontal-dark.svg`, wordmark('pergaminho'));
  out(`${B}/sancta-app-icon.svg`, iconeSvg({ v: 'padrao', size: 256, raio: 0.2237 }));
  outPng(ogImage(), `${B}/og-image.png`, 1200);
  out(`${B}/og-image.svg`, ogImage());
  // favicons hintados
  const f16 = outPng(iconeHint(16), `${B}/favicon-16.png`, 16);
  const f32 = outPng(iconeHint(32), `${B}/favicon-32.png`, 32);
  const f48 = outPng(iconeHint(48), `${B}/favicon-48.png`, 48);
  out(`${B}/favicon.svg`, iconeHint(32));
  out('backend/public/favicon.ico', ico([{ buf: f16.buf, size: 16 }, { buf: f32.buf, size: 32 }, { buf: f48.buf, size: 48 }]));
  outPng(iconeHint(48), 'backend/public/favicon.png', 48);
  outPng(iconeSvg({ v: 'padrao', size: 180 }), `${B}/apple-touch-icon.png`, 180);
  outPng(iconeSvg({ v: 'padrao', size: 192, raio: 0.2237 }), `${B}/icon-192.png`, 192);
  outPng(iconeSvg({ v: 'padrao', size: 512, raio: 0.2237 }), `${B}/icon-512.png`, 512);
  outPng(iconeSvg({ v: 'padrao', size: 512 }), `${B}/icon-maskable-512.png`, 512);
  // logos novos
  for (const v of Object.keys(WMV)) out(`${B}/sancta-wordmark-${v}.svg`, wordmark(v));
  for (const v of ['pergaminho', 'azul']) out(`${B}/sancta-lockup-slogan-${v}.svg`, lockup(v));
  for (const tema of ['claro', 'escuro']) out(`${B}/sancta-gestao-${tema}.svg`, gestao(tema));
  for (const v of Object.keys(SIMBOLO)) out(`${B}/portal-${v}.svg`, simbolo(v));
  // ilustrações do site
  for (const id of Object.keys(A.CEUS)) {
    out(`backend/public/images/illustrations/${id}.svg`, A.ceus[id](1200, 800));
    out(`backend/public/images/illustrations/${id}-retrato.svg`, A.ceus[id](600, 800));
  }
  // manifesto PWA
  out(
    'backend/public/site.webmanifest',
    JSON.stringify(
      {
        name: 'Sancta',
        short_name: 'Sancta',
        description: 'Sua jornada de fé, todos os dias.',
        lang: 'pt-BR',
        start_url: '/',
        scope: '/',
        display: 'standalone',
        background_color: T.pergaminho,
        theme_color: T.azul,
        icons: [
          { src: '/images/brand/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
          { src: '/images/brand/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
          { src: '/images/brand/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
          { src: '/images/brand/favicon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' },
        ],
      },
      null,
      2,
    ) + '\n',
  );
}

function app() {
  const AB = 'app/assets/brand';
  for (const v of Object.keys(WMV)) out(`${AB}/wordmark_${v.replace('-', '_')}.svg`, wordmark(v));
  for (const v of Object.keys(SIMBOLO)) out(`${AB}/portal_${v.replace('-', '_')}.svg`, simbolo(v));
  for (const id of Object.keys(A.CEUS)) {
    out(`${AB}/ceu_${id}.svg`, A.ceus[id](1200, 800));
    out(`${AB}/ceu_${id}_retrato.svg`, A.ceus[id](600, 800));
  }
  for (const id of Object.keys(A.POSTURAS)) {
    out(`${AB}/postura_${id}.svg`, A.postura(id, T.azul));
    // mesmos nomes de antes (o app tinge com a cor primária): 256 px, transparente, uma cor
    outPng(A.postura(id, T.azul), `app/assets/images/${id}.png`, 256);
  }
  // SanctaMark atual (Image.asset) — ícone arredondado, legível em fundo claro e escuro
  outPng(iconeSvg({ v: 'padrao', size: 512, raio: 0.2237 }), 'app/assets/images/sancta_mark.png', 512);
}

// ---------------------------------------------------------------------------------------------
// 7. Diagramas do manual (construção e área de proteção), com textos em curvas
// ---------------------------------------------------------------------------------------------
const rotulo = (texto, size, pos, cor = T.tintaSuave, font = L.semibold) => `<path fill="${cor}" d="${textoPath(font, texto, size, pos).d}"/>`;

function construcao() {
  const k = 0.8;
  const base = 690;
  const Y = (u) => base - u * k;
  const W = 1880;
  const H = 820;
  const nx = 120 - 57 * k; // origem do n (tinta começa em x = 120)
  const cxL = 680 + 243 * k;
  const cxD = 1180 + 243 * k;
  const guia = (u, dash, op) => `<line x1="40" y1="${r3(Y(u))}" x2="${W - 40}" y2="${r3(Y(u))}" stroke="${T.azul}" stroke-opacity="${op}" stroke-width="2"${dash ? ' stroke-dasharray="8 8"' : ''}/>`;
  const colchete = (x0, x1, y, cor = T.ouro) => `<path d="M${r3(x0)} ${y - 10}V${y}H${r3(x1)}V${y - 10}" fill="none" stroke="${cor}" stroke-width="3"/>`;
  const nPath = L.bold.charToGlyph('n').getPath(nx, base, 1000 * k).toPathData(2);
  const pl = portal('letra', { cx: cxL, base, k });
  const pd = portal('porta', { cx: cxD, base, k });
  const body = [
    `<rect width="${W}" height="${H}" fill="${T.pergaminho}"/>`,
    guia(0, false, 0.45),
    guia(500, true, 0.35),
    guia(512, true, 0.18),
    rotulo('linha de base', 20, { x: W - 200, y: Y(0) - 12 }),
    rotulo('altura-x 500 u', 20, { x: W - 200, y: Y(500) - 12 }),
    // n da Figtree Bold em contorno
    `<path d="${nPath}" fill="none" stroke="${T.azul}" stroke-opacity="0.75" stroke-width="2.5"/>`,
    colchete(nx + 57 * k, nx + 189 * k, base + 34),
    colchete(nx + 388 * k, nx + 520 * k, base + 34),
    rotulo('132', 20, { cx: nx + 123 * k, y: base + 66 }, T.ouro),
    rotulo('132', 20, { cx: nx + 454 * k, y: base + 66 }, T.ouro),
    rotulo('n da Figtree Bold', 24, { cx: nx + 288 * k, y: base + 112 }, T.tinta),
    // seta
    `<path d="M560 ${Y(250)}H636M622 ${Y(250) - 12}L638 ${Y(250)}L622 ${Y(250) + 12}" fill="none" stroke="${T.tintaSuave}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>`,
    // Portal (letra)
    `<path fill="${T.azul}" d="${pl.arco}"/>`,
    `<circle cx="${r3(cxL)}" cy="${r3(Y(269))}" r="${r3(243 * k)}" fill="none" stroke="${T.azul}" stroke-opacity="0.45" stroke-width="2" stroke-dasharray="6 7"/>`,
    `<circle cx="${r3(cxL)}" cy="${r3(Y(284))}" r="${r3(111 * k)}" fill="none" stroke="${T.pergaminho}" stroke-opacity="0.8" stroke-width="2" stroke-dasharray="5 6"/>`,
    `<path fill="${T.ouro}" d="${pl.cruz}"/>`,
    `<line x1="${r3(cxL)}" y1="${r3(Y(560))}" x2="${r3(cxL)}" y2="${base + 20}" stroke="${T.azul}" stroke-opacity="0.35" stroke-width="2" stroke-dasharray="4 6"/>`,
    `<path d="M${r3(cxL - 243 * k)} ${r3(Y(512) - 34)}H${r3(cxL + 243 * k)}M${r3(cxL - 243 * k)} ${r3(Y(512) - 44)}V${r3(Y(512) - 24)}M${r3(cxL + 243 * k)} ${r3(Y(512) - 44)}V${r3(Y(512) - 24)}" stroke="${T.tintaSuave}" stroke-width="2"/>`,
    rotulo('486', 20, { cx: cxL, y: Y(512) - 46 }),
    colchete(cxL - 243 * k, cxL - 111 * k, base + 34),
    colchete(cxL + 111 * k, cxL + 243 * k, base + 34),
    colchete(cxL - 111 * k, cxL + 111 * k, base + 34, T.tintaSuave),
    rotulo('132', 20, { cx: cxL - 177 * k, y: base + 66 }, T.ouro),
    rotulo('222', 20, { cx: cxL, y: base + 66 }),
    rotulo('132', 20, { cx: cxL + 177 * k, y: base + 66 }, T.ouro),
    rotulo('R 243', 18, { x: cxL + 243 * k + 10, y: Y(269) + 6 }),
    rotulo('Portal (letra) 486 × 512', 24, { cx: cxL, y: base + 112 }, T.tinta),
    // Porta (ícone)
    `<path fill="${T.azul}" d="${pd.arco}"/>`,
    `<path fill="${T.ouro}" d="${pd.cruz}"/>`,
    `<path d="M${r3(cxD + 243 * k + 22)} ${r3(Y(666))}V${base}M${r3(cxD + 243 * k + 12)} ${r3(Y(666))}H${r3(cxD + 243 * k + 32)}M${r3(cxD + 243 * k + 12)} ${base}H${r3(cxD + 243 * k + 32)}" stroke="${T.tintaSuave}" stroke-width="2"/>`,
    rotulo('666', 20, { x: cxD + 243 * k + 30, y: Y(333) + 7 }),
    rotulo('Porta (ícone) 486 × 666', 24, { cx: cxD, y: base + 112 }, T.tinta),
    rotulo('Cruz: hastes de 58 · travessão 150 no terço superior · flutua 55 acima da base (letra) / 111 (porta)', 20, { x: 120, y: 60 }),
    rotulo('Abertura com topo em 395 (letra): espessura de 117 no alto, a mesma compensação óptica dos arcos da Figtree', 20, { x: 120, y: 92 }),
  ].join('\n');
  return svgDoc(W, H, body, { title: 'Sancta — construção do Portal (unidades da fonte Figtree Bold)' });
}

function areaProtecao() {
  const s = 0.28;
  const x = 512 * s; // área de proteção = altura do Portal
  const ox = 60;
  const oy = 60;
  const w = WM.largura * s + 2 * x;
  const h = WM.altura * s + 2 * x;
  const W = w + 120;
  const H = h + 150;
  const wp = wordmarkPaths(ox + x, oy + x, s);
  const mini = (cx, base) => {
    const p = portal('letra', { cx, base, k: s });
    return `<path fill="${T.azul}" fill-opacity="0.14" d="${p.arco}"/>`;
  };
  const body = [
    `<rect width="${r3(W)}" height="${r3(H)}" fill="${T.pergaminho}"/>`,
    `<rect x="${ox}" y="${oy}" width="${r3(w)}" height="${r3(h)}" fill="${T.azul}" fill-opacity="0.05" stroke="${T.azul}" stroke-opacity="0.5" stroke-width="2" stroke-dasharray="10 8"/>`,
    `<rect x="${r3(ox + x)}" y="${r3(oy + x)}" width="${r3(WM.largura * s)}" height="${r3(WM.altura * s)}" fill="none" stroke="${T.azul}" stroke-opacity="0.25" stroke-width="1.5"/>`,
    mini(ox + x / 2 * (486 / 512), oy + x + WM.maxY * s),
    mini(ox + x + WM.largura * s + x / 2 * (486 / 512) + (x - x * (486 / 512)), oy + x + WM.maxY * s),
    mini(ox + x + (WM.largura * s) / 2, oy + x),
    mini(ox + x + (WM.largura * s) / 2, oy + h),
    `<path fill="${T.azul}" d="${wp.letras}${wp.arco}"/>`,
    `<path fill="${T.ouro}" d="${wp.cruz}"/>`,
    `<path d="M${ox + 26} ${oy}V${r3(oy + x)}M${ox + 16} ${r3(oy + x)}H${ox + 36}" stroke="${T.azul}" stroke-width="2"/>`,
    rotulo('x', 26, { x: ox + 38, y: oy + x / 2 + 9 }, T.azul),
    rotulo('x = altura do Portal (512 u, cerca de 71% da altura do wordmark)', 22, { x: ox, y: oy + h + 56 }),
  ].join('\n');
  return svgDoc(W, H, body, { title: 'Sancta — área de proteção' });
}

function diagramas() {
  const c = construcao();
  out('brand/docs/construcao-portal.svg', c);
  outPng(c, 'brand/docs/construcao-portal.png', 1880);
  const a = areaProtecao();
  out('brand/docs/area-protecao.svg', a);
  outPng(a, 'brand/docs/area-protecao.png', 1400);
}

if (require.main === module) {
  const etapas = process.argv.slice(2);
  const todas = etapas.length === 0;
  if (todas || etapas.includes('diagramas')) diagramas();
  if (todas || etapas.includes('brand')) brand();
  if (todas || etapas.includes('web')) web();
  if (todas || etapas.includes('android')) android();
  if (todas || etapas.includes('app')) app();
  console.log(saidas.join('\n'));
  console.log(`\n${saidas.length} itens.`);
}

module.exports = { simbolo, wordmark, lockup, gestao, iconeSvg, iconeHint, ogImage, featureGraphic, arcoComCeu };
