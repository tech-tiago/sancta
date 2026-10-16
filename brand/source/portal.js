'use strict';
/**
 * Sancta — geometria do símbolo Portal/N (fonte única de verdade).
 *
 * Unidades da fonte Figtree Bold (1000/em), eixo y para cima e linha de base em y=0.
 * O N mantém a porta aberta e a cruz como luz interior, com coroa plana curta, ombros suaves e
 * hastes reforçadas. Coordenadas espelhadas em
 * app/lib/core/brand/portal_geometry.dart.
 */
const PORTAL = {
  raioExterno: 256,
  raioInterno: 106,
  haste: 150,
  alcaExterna: 144,
  meiaCoroaExterna: 48,
  alcaInternaLateral: 60,
  meiaCoroaInterna: 24,
  alcaInternaCoroa: 42,
  letra: {
    altura: 512,
    centroExterno: 256,
    centroInterno: 256,
    cruz: { meia: 26, base: 64, topo: 314, braco: 72, bracoBase: 214, bracoTopo: 266 },
  },
  porta: {
    altura: 666,
    centroExterno: 410,
    centroInterno: 410,
    cruz: { meia: 26, base: 141, topo: 391, braco: 72, bracoBase: 291, bracoTopo: 343 },
  },
};

const r3 = (v) => {
  const s = (Math.round(v * 1000) / 1000).toFixed(3);
  return s.replace(/\.?0+$/, '') || '0';
};

/** Gera os paths SVG (y para baixo) do Portal/N. */
function portal(tipo, t) {
  const g = PORTAL[tipo];
  const k = t.k;
  const X = (x) => r3(t.cx + x * k);
  const Y = (y) => r3(t.base - y * k);
  const R = PORTAL.raioExterno;
  const r = PORTAL.raioInterno;
  const oc = g.centroExterno;
  const ic = g.centroInterno;
  const outerHandle = PORTAL.alcaExterna;
  const outerCrown = PORTAL.meiaCoroaExterna;
  const innerSideHandle = PORTAL.alcaInternaLateral;
  const innerTopHalf = PORTAL.meiaCoroaInterna;
  const innerCrownHandle = PORTAL.alcaInternaCoroa;
  const innerTop = ic + r;

  // Abóbada externa: laterais longas e coroa suavemente mais plana que uma meia-circunferência.
  const outerCurve =
    `C${X(-R)} ${Y(oc + outerHandle)} ${X(-outerHandle)} ${Y(g.altura)} ${X(-outerCrown)} ${Y(g.altura)}` +
    `H${X(outerCrown)}C${X(outerHandle)} ${Y(g.altura)} ${X(R)} ${Y(oc + outerHandle)} ${X(R)} ${Y(oc)}`;
  // Vão interno: repete a linguagem da coroa com espessura ampliada e espaço claro para a cruz.
  const innerCurveForward =
    `C${X(-r)} ${Y(ic + innerSideHandle)} ${X(-innerCrownHandle)} ${Y(innerTop)} ${X(-innerTopHalf)} ${Y(innerTop)}` +
    `H${X(innerTopHalf)}C${X(innerCrownHandle)} ${Y(innerTop)} ${X(r)} ${Y(ic + innerSideHandle)} ${X(r)} ${Y(ic)}`;
  const innerCurveReverse =
    `C${X(r)} ${Y(ic + innerSideHandle)} ${X(innerCrownHandle)} ${Y(innerTop)} ${X(innerTopHalf)} ${Y(innerTop)}` +
    `H${X(-innerTopHalf)}C${X(-innerCrownHandle)} ${Y(innerTop)} ${X(-r)} ${Y(ic + innerSideHandle)} ${X(-r)} ${Y(ic)}`;

  const arco =
    `M${X(-R)} ${Y(0)}V${Y(oc)}${outerCurve}V${Y(0)}H${X(r)}V${Y(ic)}` +
    `${innerCurveReverse}V${Y(0)}Z`;
  const silhueta = `M${X(-R)} ${Y(0)}V${Y(oc)}${outerCurve}V${Y(0)}Z`;
  const abertura =
    `M${X(-r)} ${Y(0)}V${Y(ic)}` +
    `${innerCurveForward}V${Y(0)}Z`;

  // Cruz geométrica, com cantos retos para preservar a leitura em ícones pequenos.
  const c = g.cruz;
  const p = t.pesoCruz || 1;
  const m = c.meia * p;
  const meioBraco = (c.bracoBase + c.bracoTopo) / 2;
  const bb = meioBraco - c.meia * p;
  const bt = meioBraco + c.meia * p;
  const bm = c.braco + (p - 1) * c.meia * 0.6;
  const pts = [
    [-m, c.topo], [m, c.topo], [m, bt], [bm, bt], [bm, bb], [m, bb],
    [m, c.base], [-m, c.base], [-m, bb], [-bm, bb], [-bm, bt], [-m, bt],
  ];
  const poly = (list) => 'M' + list.map(([x, y]) => `${X(x)} ${Y(y)}`).join('L') + 'Z';
  const cruz = poly(pts);
  const cruzInversa = poly(pts.slice().reverse());

  return { arco, silhueta, abertura, cruz, cruzInversa, largura: 2 * R * k, altura: g.altura * k };
}

module.exports = { PORTAL, portal, r3 };
