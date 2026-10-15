'use strict';
/**
 * Sancta — ilustrações (céus em arco) e ícones de postura litúrgica.
 * Só formas, degradês lineares/radiais e opacidade (compatível com flutter_svg e resvg; sem filtros).
 * As cores dos degradês estão também em app/lib/core/brand/sky_art.dart (manter em sincronia).
 */
const { r3 } = require('./portal');

/** Paletas dos céus (de cima para baixo). */
const CEUS = {
  aurora: {
    nome: 'Aurora',
    stops: [[0, '#2D4DB4'], [0.2, '#5266C1'], [0.42, '#7A82CE'], [0.53, '#9890C1'], [0.66, '#C9A3A3'], [0.78, '#EEB38D'], [1, '#F8D69C']],
  },
  entardecer: {
    nome: 'Entardecer',
    stops: [[0, '#1A3388'], [0.24, '#3B4BAA'], [0.44, '#7559AC'], [0.6, '#B8628F'], [0.76, '#E57F63'], [1, '#F4AE5A']],
  },
  noite: {
    nome: 'Noite',
    stops: [[0, '#0B1533'], [0.42, '#132760'], [0.8, '#1F3D9C'], [1, '#2E4DB4']],
  },
  luz: {
    nome: 'Luz',
    stops: [[0, '#DCE3F7'], [0.55, '#EEEDF1'], [1, '#FBF6EC']],
  },
};

const grad = (id, stops) =>
  `<linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1">${stops.map(([o, c]) => `<stop offset="${o}" stop-color="${c}"/>`).join('')}</linearGradient>`;

/** Lua crescente: disco menos um círculo deslocado (path analítico, sem máscara). */
function crescente(cx, cy, R, dx, dy, R2) {
  const d = Math.hypot(dx, dy);
  const a = (R * R - R2 * R2 + d * d) / (2 * d);
  const h = Math.sqrt(R * R - a * a);
  const ux = dx / d;
  const uy = dy / d;
  const mx = cx + a * ux;
  const my = cy + a * uy;
  const p1 = [mx + h * -uy, my + h * ux];
  const p2 = [mx - h * -uy, my - h * ux];
  // arco grande do disco de p1 a p2 (lado oposto ao cortador) e volta pelo cortador
  return `M${r3(p1[0])} ${r3(p1[1])}A${r3(R)} ${r3(R)} 0 1 1 ${r3(p2[0])} ${r3(p2[1])}A${r3(R2)} ${r3(R2)} 0 0 0 ${r3(p1[0])} ${r3(p1[1])}Z`;
}

/** Brilho de 4 pontas (estrela) centrado em (x,y) com raio r. */
function brilho(x, y, r) {
  const q = r * 0.18;
  return `M${r3(x)} ${r3(y - r)}Q${r3(x + q)} ${r3(y - q)} ${r3(x + r)} ${r3(y)}Q${r3(x + q)} ${r3(y + q)} ${r3(x)} ${r3(y + r)}Q${r3(x - q)} ${r3(y + q)} ${r3(x - r)} ${r3(y)}Q${r3(x - q)} ${r3(y - q)} ${r3(x)} ${r3(y - r)}Z`;
}

/*
 * Cada céu é desenhado para qualquer proporção (W×H): tamanhos relativos à largura (u = W/1200),
 * sol/horizonte ancorados na base e elementos dentro da área útil do arco (raio = W/2).
 * Arquivos gerados: paisagem 1200×800 (cartões, site) e retrato 600×800 (widget, molduras altas).
 */
const SOL = {
  aurora: { cy: -24, r: 235 }, // cy relativo à base (em u); r em u
  entardecer: { cy: 50, r: 290 },
  luz: { cy: -10, r: 170 },
};

function ceu(id, W, H, { px = id, inner = false } = {}) {
  const u = W / 1200;
  const cx = W / 2;
  const defs = [grad(`${px}-ceu`, CEUS[id].stops)];
  const body = [`<rect width="${r3(W)}" height="${r3(H)}" fill="url(#${px}-ceu)"/>`];
  const rad = (gid, x, y, r, stops) =>
    `<radialGradient id="${gid}" cx="${r3(x)}" cy="${r3(y)}" r="${r3(r)}" gradientUnits="userSpaceOnUse">${stops
      .map(([o, c, a]) => `<stop offset="${o}" stop-color="${c}" stop-opacity="${a}"/>`)
      .join('')}</radialGradient>`;

  if (id === 'aurora') {
    const sy = H + SOL.aurora.cy * u;
    defs.push(rad(`${px}-halo`, cx, sy, 560 * u, [[0.42, '#FFF3DC', 0.42], [1, '#FFF3DC', 0]]));
    body.push(`<circle cx="${r3(cx)}" cy="${r3(sy)}" r="${r3(560 * u)}" fill="url(#${px}-halo)"/>`);
    body.push(`<circle cx="${r3(cx)}" cy="${r3(sy)}" r="${r3(SOL.aurora.r * u)}" fill="#FBF6EC"/>`);
  } else if (id === 'entardecer') {
    const sy = H + SOL.entardecer.cy * u;
    defs.push(rad(`${px}-halo`, cx, sy - 10 * u, 640 * u, [[0.38, '#FFD9A0', 0.5], [1, '#FFD9A0', 0]]));
    defs.push(`<linearGradient id="${px}-sol" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FDEFD2"/><stop offset="1" stop-color="#F9CF8C"/></linearGradient>`);
    body.push(`<circle cx="${r3(cx)}" cy="${r3(sy - 10 * u)}" r="${r3(640 * u)}" fill="url(#${px}-halo)"/>`);
    body.push(`<circle cx="${r3(cx)}" cy="${r3(sy)}" r="${r3(SOL.entardecer.r * u)}" fill="url(#${px}-sol)"/>`);
    // faixas de nuvem (x relativo ao centro, y relativo à base, em u)
    const nuvens = [[-350, -348, 330, 26, 0.28], [40, -300, 300, 22, 0.24], [-220, -174, 470, 30, 0.34], [90, -110, 260, 22, 0.3]];
    for (const [nx, ny, nw, nh, o] of nuvens) {
      body.push(`<rect x="${r3(cx + nx * u)}" y="${r3(H + ny * u)}" width="${r3(nw * u)}" height="${r3(nh * u)}" rx="${r3((nh * u) / 2)}" fill="#FCE1CF" fill-opacity="${o}"/>`);
    }
  } else if (id === 'noite') {
    const mx = cx + 156 * u;
    const my = Math.min(300 * u + (H - 800 * u) * 0.25, H * 0.42);
    defs.push(rad(`${px}-horizonte`, cx, H + 100 * u, 620 * u, [[0, '#6F86D6', 0.45], [1, '#6F86D6', 0]]));
    defs.push(rad(`${px}-lua`, mx, my, 250 * u, [[0.25, '#FBF6EC', 0.22], [1, '#FBF6EC', 0]]));
    body.push(`<circle cx="${r3(cx)}" cy="${r3(H + 100 * u)}" r="${r3(620 * u)}" fill="url(#${px}-horizonte)"/>`);
    body.push(`<circle cx="${r3(mx)}" cy="${r3(my)}" r="${r3(250 * u)}" fill="url(#${px}-lua)"/>`);
    body.push(`<path fill="#FBF6EC" d="${crescente(mx, my, 84 * u, 38 * u, -26 * u, 74 * u)}"/>`);
    // estrelas: posições normalizadas (x/1200, y/800) e raio em u
    const pontos = [
      [300, 330, 3.2, 0.85], [372, 214, 2.4, 0.7], [468, 132, 3.0, 0.9], [560, 236, 2.2, 0.6], [640, 110, 2.6, 0.8],
      [266, 470, 2.0, 0.55], [430, 400, 2.6, 0.75], [520, 330, 1.8, 0.5], [930, 420, 2.8, 0.8], [980, 540, 2.0, 0.6],
      [872, 196, 2.2, 0.65], [690, 420, 2.0, 0.55], [180, 600, 2.2, 0.6], [1030, 640, 2.4, 0.55], [350, 560, 1.8, 0.5],
      [820, 560, 2.2, 0.6], [600, 520, 1.6, 0.45], [150, 720, 1.8, 0.45], [1060, 360, 1.6, 0.45], [710, 250, 1.6, 0.5],
    ];
    const X = (x) => (x / 1200) * W;
    const Y = (y) => (y / 800) * H;
    const rs = Math.max(u, 0.35); // piso: estrelas não somem em molduras pequenas
    body.push(pontos.map(([x, y, r, o]) => `<circle cx="${r3(X(x))}" cy="${r3(Y(y))}" r="${r3(r * rs)}" fill="#FBF6EC" fill-opacity="${o}"/>`).join(''));
    body.push(
      [
        `<path fill="#FBF6EC" fill-opacity="0.95" d="${brilho(X(404), Y(268), 16 * rs)}"/>`,
        `<path fill="#FBF6EC" fill-opacity="0.8" d="${brilho(X(1010), Y(470), 11 * rs)}"/>`,
        `<path fill="#E3B65B" d="${brilho(X(520), Y(186), 9 * rs)}"/>`,
        `<path fill="#FBF6EC" fill-opacity="0.75" d="${brilho(X(240), Y(540), 9 * rs)}"/>`,
      ].join(''),
    );
  } else if (id === 'luz') {
    const oy = H + 20 * u;
    defs.push(rad(`${px}-brilho`, cx, H, 560 * u, [[0, '#FFFFFF', 0.95], [0.45, '#FFFFFF', 0.55], [1, '#FFFFFF', 0]]));
    defs.push(`<linearGradient id="${px}-raios" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#FFFFFF" stop-opacity="0.6"/><stop offset="1" stop-color="#FFFFFF" stop-opacity="0"/></linearGradient>`);
    const raios = [];
    const L = Math.hypot(W, H) * 1.1;
    for (let i = -4; i <= 4; i++) {
      const ang = (i * 15 * Math.PI) / 180;
      const half = (3.2 * Math.PI) / 180;
      const p = (a) => [cx + Math.sin(a) * L, oy - Math.cos(a) * L];
      const a1 = p(ang - half);
      const a2 = p(ang + half);
      raios.push(`M${r3(cx)} ${r3(oy)}L${r3(a1[0])} ${r3(a1[1])}L${r3(a2[0])} ${r3(a2[1])}Z`);
    }
    body.push(`<path fill="url(#${px}-raios)" d="${raios.join('')}"/>`);
    body.push(`<circle cx="${r3(cx)}" cy="${r3(H)}" r="${r3(560 * u)}" fill="url(#${px}-brilho)"/>`);
    body.push(`<circle cx="${r3(cx)}" cy="${r3(H + SOL.luz.cy * u)}" r="${r3(SOL.luz.r * u)}" fill="#FFFFFF" fill-opacity="0.9"/>`);
  }

  const conteudo = `<defs>${defs.join('')}</defs>\n${body.join('\n')}`;
  if (inner) return conteudo;
  return (
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${r3(W)} ${r3(H)}" width="${r3(W)}" height="${r3(H)}"><title>Sancta — céu ${CEUS[id].nome}</title>\n` +
    `${conteudo}\n</svg>\n`
  );
}

const ceus = {
  aurora: (W = 1200, H = 800, o) => ceu('aurora', W, H, o),
  entardecer: (W = 1200, H = 800, o) => ceu('entardecer', W, H, o),
  noite: (W = 1200, H = 800, o) => ceu('noite', W, H, o),
  luz: (W = 1200, H = 800, o) => ceu('luz', W, H, o),
};

/**
 * Ícones de postura (grade 24×24, formas cheias e cantos arredondados — mesmo vocabulário do Portal).
 * Uma só cor (o app tinge o PNG com a cor primária: Image.asset(color: ...)).
 */
const POSTURAS = {
  de_pe: {
    nome: 'De pé',
    corpo: [
      '<circle cx="12" cy="4.1" r="2.35"/>',
      // tronco com ombros arredondados
      '<path d="M8.6 10.1a2.6 2.6 0 0 1 2.6-2.6h1.6a2.6 2.6 0 0 1 2.6 2.6v4.4H8.6Z"/>',
      // braços
      '<rect x="5.3" y="8.1" width="2.1" height="6.9" rx="1.05"/>',
      '<rect x="16.6" y="8.1" width="2.1" height="6.9" rx="1.05"/>',
      // pernas
      '<path d="M8.6 15.4h2.7v5.4a1.35 1.35 0 0 1-2.7 0Z"/>',
      '<path d="M12.7 15.4h2.7v5.4a1.35 1.35 0 0 1-2.7 0Z"/>',
    ],
  },
  de_joelhos: {
    nome: 'De joelhos',
    corpo: [
      '<circle cx="9.6" cy="4.1" r="2.35"/>',
      // tronco
      '<path d="M7 10a2.5 2.5 0 0 1 5 0v5.6H7Z"/>',
      // braço: do ombro ao cotovelo e mãos postas à frente
      '<path d="M11.2 9.5l2.2 2.7.9-3.9" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
      // coxa (do quadril ao joelho)
      '<path d="M7 15.2h5.6a1.4 1.4 0 0 1 1.4 1.4v2.3H7Z"/>',
      // canela e pé apoiados no chão
      '<path d="M5.4 18.6h8.6v1.8a1.3 1.3 0 0 1-1.3 1.3H5.4a1.55 1.55 0 0 1 0-3.1Z"/>',
    ],
  },
  maos_postas: {
    nome: 'Mãos postas',
    corpo: [
      // mão esquerda (dedos para cima, encostada no eixo)
      '<path d="M11.45 2.9c-.75 0-1.3.5-1.5 1.25L8.1 11.3l-1.55 2.8 3.4 3.1 1.5-1.45Z"/>',
      // mão direita (espelho)
      '<path d="M12.55 2.9c.75 0 1.3.5 1.5 1.25l1.85 7.15 1.55 2.8-3.4 3.1-1.5-1.45Z"/>',
      // punhos
      '<path d="M5.7 14.9l3.6 3.3-2.5 2.7a1.2 1.2 0 0 1-1.7.05L3.3 19.3a1.2 1.2 0 0 1-.05-1.7Z"/>',
      '<path d="M18.3 14.9l-3.6 3.3 2.5 2.7a1.2 1.2 0 0 0 1.7.05l1.8-1.65a1.2 1.2 0 0 0 .05-1.7Z"/>',
    ],
  },
};

function postura(id, cor, size = 24) {
  const p = POSTURAS[id];
  return (
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="${size}" height="${size}"><title>${p.nome}</title>\n` +
    `<g fill="${cor}" stroke="${cor}" stroke-width="0">${p.corpo.join('')}</g>\n</svg>\n`
  );
}

module.exports = { CEUS, SOL, ceu, ceus, POSTURAS, postura, crescente };
