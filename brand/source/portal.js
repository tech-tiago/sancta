'use strict';
/**
 * Sancta — geometria da marca "Portal" (fonte única de verdade).
 *
 * Unidades = unidades da fonte Figtree Bold (1000 por em). Eixo y para CIMA, linha de base em y = 0,
 * Portal centrado em x = 0. As MESMAS medidas estão em app/lib/core/brand/portal_geometry.dart.
 *
 * Construção (a partir do "n" da Figtree Bold: hastes de 132 u, altura-x 500 u, overshoot 512 u):
 *  - Haste = 132 u (igual às hastes do n).
 *  - Contorno externo: semicírculo de raio 243 u (largura total 486 u) cujo topo toca o overshoot do n (512 u).
 *  - Abertura: semicírculo de raio 111 u (vão de 222 u); topo em 395 u. A espessura no topo (117 u, 89% da haste)
 *    repete a compensação óptica dos arcos da Figtree (no n: 124 u no topo contra 132 u nas hastes).
 *  - Cruz Ouro: hastes de 58 u; travessão de 150 u no terço superior; flutua 55 u acima da linha de base
 *    (como nas imagens aprovadas).
 *  - Variante "porta" (ícone do app): mesmo arco com as hastes alongadas em 154 u (altura 666 u, proporção vertical).
 */

const PORTAL = {
  haste: 132,
  raioExterno: 243,
  raioInterno: 111,
  letra: {
    altura: 512,
    centroExterno: 269, // 512 − 243
    centroInterno: 284, // 395 − 111
    cruz: { meia: 29, base: 55, topo: 317, braco: 75, bracoBase: 207, bracoTopo: 265 },
  },
  porta: {
    altura: 666,
    centroExterno: 423, // 269 + 154
    centroInterno: 438, // 284 + 154
    cruz: { meia: 29, base: 111, topo: 405, braco: 75, bracoBase: 285, bracoTopo: 343 },
  },
};

const r3 = (v) => {
  const s = (Math.round(v * 1000) / 1000).toFixed(3);
  return s.replace(/\.?0+$/, '') || '0';
};

/**
 * Gera os paths SVG (y para baixo) do Portal.
 * @param {'letra'|'porta'} tipo
 * @param {{cx:number, base:number, k:number, pesoCruz?:number}} t  centro x, y da linha de base e escala (px por unidade)
 */
function portal(tipo, t) {
  const g = PORTAL[tipo];
  const k = t.k;
  const X = (x) => r3(t.cx + x * k);
  const Y = (y) => r3(t.base - y * k);
  const R = PORTAL.raioExterno;
  const r = PORTAL.raioInterno;
  const Rk = r3(R * k);
  const rk = r3(r * k);
  const oc = g.centroExterno;
  const ic = g.centroInterno;

  // Arco (forma "∩"): contorno externo no sentido horário e abertura percorrida de volta.
  const arco = `M${X(-R)} ${Y(0)}V${Y(oc)}A${Rk} ${Rk} 0 0 1 ${X(R)} ${Y(oc)}V${Y(0)}H${X(r)}V${Y(ic)}A${rk} ${rk} 0 0 0 ${X(-r)} ${Y(ic)}V${Y(0)}Z`;
  // Silhueta da porta (contorno externo cheio).
  const silhueta = `M${X(-R)} ${Y(0)}V${Y(oc)}A${Rk} ${Rk} 0 0 1 ${X(R)} ${Y(oc)}V${Y(0)}Z`;
  // Abertura (vão interno).
  const abertura = `M${X(-r)} ${Y(0)}V${Y(ic)}A${rk} ${rk} 0 0 1 ${X(r)} ${Y(ic)}V${Y(0)}Z`;

  // Cruz; pesoCruz > 1 engrossa as hastes (versões de tamanho mínimo: favicon, notificação).
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
  const cruz = poly(pts); // horário (na tela)
  const cruzInversa = poly(pts.slice().reverse()); // anti-horário: vira furo com regra nonzero

  return { arco, silhueta, abertura, cruz, cruzInversa, largura: 2 * R * k, altura: g.altura * k };
}

module.exports = { PORTAL, portal, r3 };
