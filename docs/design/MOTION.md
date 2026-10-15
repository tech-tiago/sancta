# Movimento do Sancta "Portal"

> Dono: Motion. Código em `app/lib/core/motion/` (sistema e componentes) e `app/lib/features/splash/`
> (abertura); splash nativo em `app/android/app/src/main/res/` (estilos, `launch_background.xml`).
> Testes em `app/test/motion/`. Marca e geometria: [MARCA.md](MARCA.md).

## 1. Princípios

1. **Calmo.** Nada pisca, quica ou chama atenção sem motivo. Curvas que desaceleram até parar, sem rebote;
   ciclos contínuos são senoidais e lentos (≥ 1,6 s).
2. **Reverente.** O gesto central da marca é **a luz que acende dentro da porta**: o céu Aurora e o brilho
   quente ficam sempre *recortados pela abertura do Portal* (nada de sombra ou brilho na marca por fora).
   O Ouro é acento (cruz, sequência, raios sutis), nunca área grande.
3. **Rápido.** O toque responde em ≤ 90 ms; mudanças de estado em 150–250 ms; telas em 300 ms. Nenhuma
   animação segura o app: tudo funciona antes de a animação terminar.
4. **Acessível.** "Remover animações" desliga deslocamento, escala, rotação e repetição (fica, no máximo,
   um fade curto). Animações contínuas param fora da tela. Nada depende só do movimento para informar.

## 2. Tokens (`SanctaMotion`, `core/motion/motion.dart`)

### Durações

| Token | ms | Uso |
|---|---|---|
| `press` | 90 | afundar ao toque (`Pressable`) |
| `fast` | 150 | troca de cor/ícone/opacidade, dispensar a celebração |
| `base` | 250 | mudança de estado (aba, chip, expandir), soltar o toque |
| `page` / `pageReverse` | 300 / 250 | transição entre telas (ida / volta) |
| `slow` | 400 | deslocamentos maiores (carrossel, destaques) |
| `intro` | 1500 | abertura do app |
| `celebration` | 1750 | "Amém" (limite 1,8 s) |
| `pulse` | 1600 | ciclo do ponto AO VIVO |
| `shimmer` | 1600 | ciclo do brilho dos esqueletos |
| `breath` | 2400 | ciclo da "luz respirando" (espera sem spinner) |
| `reducedFade` | 120 | fade que substitui movimento com "reduzir movimento" |

### Curvas (nomes semânticos)

| Token | Curva | Uso |
|---|---|---|
| `standard` | `Cubic(0.2, 0, 0, 1)` | mudanças de estado na tela |
| `emphasized` | `Curves.easeInOutCubicEmphasized` | movimentos que atravessam a tela (o Portal encaixando) |
| `enter` | `Cubic(0.05, 0.7, 0.1, 1)` | algo que entra/aparece (desacelera até parar) |
| `exit` | `Cubic(0.3, 0, 0.8, 0.15)` | algo que sai (acelera ao partir) |
| `breathing` | `Curves.easeInOutSine` | ciclos contínuos |

### Acessibilidade

```dart
SanctaMotion.reduced(context)                 // "Remover animações" ligado?
SanctaMotion.of(context, SanctaMotion.base)   // Duration.zero quando reduzido (muda na hora)
SanctaMotion.fadeOf(context, SanctaMotion.slow) // fades continuam, encurtados para 120 ms
```

> Atenção: com "Remover animações", o Flutter encurta **20×** todo `AnimationController` comum
> (`AnimationBehavior.normal`). Se a duração for tempo de *leitura* (ex.: a celebração reduzida), use
> `animationBehavior: AnimationBehavior.preserve` e faça a versão estática você mesmo.

## 3. Abertura do app

### Arquitetura (por que nunca atrasa)

```
Toque no ícone ─► splash nativo (Azul Manto + Portal 110 dp no centro da tela)
                 ─► 1º quadro do Flutter = o MESMO quadro (SanctaIntro por cima do app)
                    ├─ por baixo: o roteador já monta /carregando e, quando a sessão inicia, a 1ª tela
                    └─ por cima: a sequência de 1,5 s; ao fim, se pronto → saída; senão, luz respirando
```

- `SanctaIntroGate` (em `MaterialApp.builder`, `app/lib/app/app.dart`) liga a abertura ao sinal que o
  roteador já usa: `sessionProvider.initialized`.
- `SanctaIntro` desenha **por cima** do app (camada em `Stack`), então a primeira tela é construída e
  carrega seus dados *durante* a abertura. Enquanto a abertura está na tela, o app por baixo não recebe
  toques e fica fora da árvore semântica (`BlockSemantics`); a abertura é um único nó "Sancta".
- **Só na abertura a frio**: toca uma vez por processo (`SanctaIntro.played`); reconstruções do app
  (tema, idioma, hot reload) não repetem.
- A rota `/carregando` (`SplashScreen`) mostra o mesmo primeiro quadro, parado (`SanctaIntroStill`), sem
  spinner.

### Coreografia (ms)

| ms | O que acontece | Curva |
|---|---|---|
| 0–120 | Quadro idêntico ao splash nativo: Portal Pergaminho + cruz Ouro, 110 dp, centro da tela, fundo Azul Manto. Cobre o fade de saída do splash do Android 12+. | — |
| 120–620 | **A porta acende**: o céu Aurora surge dentro da abertura e o sol Pergaminho nasce da soleira (o degradê sobe 32% da altura até o lugar). | `enter` |
| 250–750 | **A cruz acende em Ouro**: brilho quente atrás da cruz, recortado pela abertura. | `easeOut` |
| 650–1150 | **O Portal encaixa no lugar do "n"**: desliza e reduz de 110 dp até o tamanho do wordmark (≈ 58% da largura da tela). | `emphasized` |
| 820–1150 | A luz assenta: a abertura volta ao Azul Manto — o wordmark termina **exatamente** o oficial. | `easeIn` |
| 850–1150 | "a" e "c" saem de trás do Portal (fade + 120 u em direção ao lugar). | `enter`, 300 ms cada |
| 920–1220 | "S" e "t". | idem |
| 990–1290 | o último "a". | idem |
| 1150–1500 | O slogan "Sua jornada de fé, todos os dias." aparece (fade + sobe 6 px), em Névoa. | `enter` |
| 1500 → | **Pronto?** Saída (380 ms): a abertura some com fade (`easeOut`) e aproxima 1→1,03 (`exit`); a primeira tela cresce 0,985→1 (`enter`). **Ainda carregando?** A abertura do "n" respira luz (Aurora até 55%, ciclo de 2,4 s, `breathing`) até ficar pronto; então sai na hora. | |

Quadros de referência: `app/test/motion/png/abertura_{0000,0300,0600,0900,1200,1500,espera}.png`.

### Regras

- **Nunca atrasar**: se o app ficar pronto antes, a sequência completa **uma vez** (1,5 s) e sai; se
  demorar, nada de spinner — a luz respira no "n" até `ready`, e a saída começa no quadro seguinte.
- **Reduzir movimento**: sem deslocamento/escala. O Portal se funde ao wordmark com um fade de 240 ms e a
  saída (fade de 200 ms) acontece **assim que pronto**, sem tempo mínimo.
- **Continuidade**: o Portal inicial fica no **centro da tela** (não da janela). No Android < 15 sem
  edge-to-edge a janela do Flutter não cobre a barra de navegação; a abertura compensa usando o tamanho do
  display (`IntroLayout`/`_startCenter`). Desde a revisão do QA o app liga **edge-to-edge em todas as
  versões** (`main.dart`, antes do 1º quadro; barras transparentes em `core/theme/system_bars.dart`), então a
  janela passa a cobrir o display inteiro e a compensação fica neutra — mantida como proteção.
- **Barra de status**: clara sobre o Azul Manto durante a abertura (`AnnotatedRegion`). A barra de
  navegação não é alterada (fica com o tema do app).
- **Fidelidade**: letras desenhadas com os **contornos do SVG oficial** (`wordmark_glyphs.dart`, gerado de
  `assets/brand/wordmark_pergaminho.svg`); o Portal com `PortalGeometry`. Um teste garante que os contornos
  são idênticos ao SVG. Se a marca mudar o wordmark: `node test/motion/tool/gen_wordmark_glyphs.js .` (na
  pasta `app/`) e `dart format lib/features/splash`.
- Slogan: corpo do lockup (0,148 do em) com **piso de 14 px** para leitura no celular; linha de base a
  394 u (escalados com o texto) abaixo da do wordmark; respeita a escala de texto do app (até 2,0× desde a revisão de acessibilidade do QA; `maxTextScale` em `app/app.dart`).

### Splash nativo (Android)

| Versão | Arquivo | Conteúdo |
|---|---|---|
| Android 12+ | `res/values-v31/styles.xml` | `windowSplashScreenBackground` Azul Manto, `windowSplashScreenAnimatedIcon` = `@drawable/sancta_portal_splash` (288 dp, Portal de 110 dp no centro, dentro do círculo de 192 dp), `windowSplashScreenBehavior=icon_preferred` (13+: ícone também ao abrir por notificação) |
| Android < 12 | `res/drawable/launch_background.xml` | Azul Manto + `@drawable/sancta_portal` (vetor) com 104,41 × 110 dp no centro |
| Ambos | `res/values/splash.xml` | `sancta_splash_background` = `#1F3D9C` |
| Janela do Flutter | `NormalTheme` (`res/values/styles.xml`, `values-v31`) | `windowBackground` Azul Manto: **nenhum quadro branco** entre o splash e o Flutter |

- **Iguais no claro e no escuro**: não há `values-night*` (removidos); tema-base escuro (`Theme.Black`) para a
  janela não ser "clara" para o modo escuro forçado do sistema.
- **Sem AnimatedVectorDrawable, de propósito**: o tempo do splash do sistema é imprevisível (depende do
  aparelho) e o Android 12 parte do ícone do lançador (que tem a cruz). Uma animação nativa cortada no meio
  ou que esconde a cruz geraria um "pulo" na troca para o Flutter. O splash nativo é o quadro 0 da
  abertura; toda a luz e o movimento acontecem no Flutter, sem emendas.

## 4. Componentes (`app/lib/core/motion/`)

### Transição de página — `SanctaPageTransitionsBuilder` (via `SanctaMotion.pageTransitions`)

Uma "passagem": o fundo da nova tela cobre a anterior nos primeiros ~90 ms (nunca aparece quadro preto
nem duas telas sobrepostas), o conteúdo surge com fade e sobe 2% (`enter`), 300 ms; a volta faz o caminho
inverso em 250 ms. Reduzido: só fades, comprimidos nos primeiros ~40%. iOS/macOS mantêm a transição
Cupertino (gesto de voltar). Já está no tema (`app_theme.dart`); não é preciso fazer nada nas rotas.

### `SanctaSkeleton({width, height = 16, radius})`

Bloco de carregamento: base Azul Manto 7% (claro) / Pergaminho 8% (escuro) e uma faixa de luz (branco /
Azul Luz) que **atravessa a tela inteira** — todos os esqueletos visíveis formam uma única varredura,
porque o brilho usa coordenadas da tela e um relógio comum. Cada bloco é uma camada própria (não repinta a
tela); para fora da tela (`TickerMode`) e com "reduzir movimento" (fica só a base). Sem `width`, ocupa a
largura disponível; `height: double.infinity` preenche o pai.

### `LiveBadge({label = 'AO VIVO', compact = false})`

Selo vermelho (texto branco 4,55:1) com um **ponto pulsante**: o ponto respira (100% ↔ 72%) e solta um
anel suave a cada 1,6 s, sem invadir o texto. Só o ponto repinta. Para fora da tela e com "reduzir
movimento" (ponto estático).

### `Pressable({child, onTap, haptic = false, pressedScale = .97})`

Toque com "afundar": 0,97 em 90 ms (`easeOut`), volta em 250 ms (`standard`). Toques rápidos mostram o
afundar completo antes de voltar. `haptic: true` dá um clique leve (`selectionClick`) — use em ações
principais, não em listas. Reduzido: sem escala. Sem `onTap`: só o filho, sem semântica de toque.
(`PressableCard`, em `core/widgets/common.dart`, já usa.)

### `PortalTabIcon({selected, color, size = 24})`

Ícone da aba "Hoje". Inativo: **contorno do Portal** (traço de ~2 px, mesma silhueta). Ativo: **Portal
cheio com a cruz Ouro**. Transição de 250 ms: o arco engrossa para dentro até as hastes do "n" (0–70%,
`standard`) e a cruz acende a partir de 35% (fade + cresce de 70% a 100%, `enter`); a cor acompanha em
150 ms. Decorativo para leitores de tela (o rótulo da aba diz "Hoje"). `portalArchAt(size, fill)` expõe o
arco em qualquer espessura (fill 1 = `PortalGeometry.letter.arch`).

### `showAmenCelebration(context, {streakDays})`

| ms | |
|---|---|
| 0–300 | véu Tinta (74%) sobre a tela |
| 0–380 | o Portal aparece (fade + 0,9 → 1) |
| 150–650 | a porta acende (Aurora + sol) — **háptico médio aos 200 ms** |
| 300–800 | a cruz brilha |
| 250–1300 | 12 raios dourados finos nascem da cruz, por trás da porta (opacidade máx. 34%, giram 6°) |
| 380–700 | "Amém" (Figtree Bold 34, Pergaminho) sobe 10 px |
| 560–860 | sequência ("Primeiro dia da sequência" / "N dias seguidos", com cruz Ouro) — clique leve |
| 1450–1750 | tudo sai (fade; o Portal aproxima 4%) |

- Um toque em qualquer lugar dispensa (fade de 150 ms). Não empilha rota: vive no `Overlay` raiz, o `Future`
  completa quando sai de cena e o botão voltar continua funcionando. Nunca duas ao mesmo tempo.
- Reduzido: composição estática (porta acesa, "Amém", sequência) com fade de 120 ms na entrada e na saída,
  1,4 s no total, mesmo com o encurtamento do sistema (`AnimationBehavior.preserve`).
- Leitores de tela: região dinâmica "Amém. N dias seguidos de oração." com ação "fechar".

### `paintLitPortal(canvas, rect, {light, rise, glow, opacity})`

O Portal com a luz por dentro (usado pela abertura e pelo "Amém"). Com `light = glow = 0` é o símbolo
oficial. Use para qualquer novo momento "a porta acende".

## 5. Como usar (para as outras equipes)

- Use os tokens: `SanctaMotion.base`, `SanctaMotion.standard`… nunca `Duration(milliseconds: …)` solto.
- Sempre consulte `SanctaMotion.reduced(context)` antes de animar posição/escala/repetição.
- Animações contínuas: só com `SingleTickerProviderStateMixin`/`TickerMode` (param fora da tela), numa
  camada própria (`RepaintBoundary` ou `isRepaintBoundary`), e nunca em listas longas sem necessidade.
- Testes de tela com `LiveBadge`/`SanctaSkeleton` visíveis **não podem usar `pumpAndSettle`** (são
  contínuos por definição): avance o tempo com `pump(Duration)` em passos (ex.: `settle()` do harness).
- Toques: prefira `Pressable`/`PressableCard`; háptico só em ações principais.
- Conclusão de oração/jornada: `await showAmenCelebration(context, streakDays: n)`.

## 6. Testes (`app/test/motion/`)

| Arquivo | Cobre |
|---|---|
| `intro_test.dart` | sequência completa uma vez e sai; luz respirando na espera e saída imediata quando pronto; app por baixo montado e com estado preservado; toques bloqueados só durante a abertura; só a frio; reduzir movimento (sem tempo mínimo, nada contínuo); integração com GoRouter + `SanctaIntroGate` (navega por baixo e revela a primeira tela); contornos = SVG oficial; Portal = `PortalGeometry`; splash nativo com o mesmo Portal |
| `components_test.dart` | tokens e reduzido; transição de página; esqueleto (anima, tamanho, para reduzido e fora da tela); AO VIVO (pulsa, para reduzido/fora da tela); Pressable (afunda/volta, toque rápido, háptico, reduzido, desabilitado); aba Hoje (transição, reduzido, arco = oficial); Amém (sequência, háptico, ≤ 1,8 s, toque dispensa sem empilhar rota, reduzido, sem duplicar) |
| `intro_golden_test.dart` | quadros 0/300/600/900/1200/1500 ms e a espera (`png/`) — `flutter test test/motion/intro_golden_test.dart --update-goldens` |

## 7. Para conferir no aparelho (QA)

1. Abertura a frio no Android 12+ e em um < 12, tema claro e escuro: nenhum quadro branco; o Portal não
   "pula" na troca splash → Flutter (mesmo tamanho e posição).
2. Android 12–14 com navegação por **3 botões** (janela sem edge-to-edge): o Portal continua no mesmo
   lugar na troca (compensação pelo tamanho do display). Se houver um deslocamento de ~24 dp, anotar o
   aparelho.
3. Abrir pelo toque numa notificação (Android 13+): o splash mostra o Portal (`icon_preferred`).
4. Rede lenta / sem rede: depois de 1,5 s a luz respira no "n" sem spinner; ao carregar, a primeira tela
   aparece com a saída suave.
5. Acessibilidade → Remover animações: abertura vira fade curto e não espera 1,5 s; "Amém" estático,
   legível por ~1,4 s; AO VIVO e esqueletos parados; abas trocam na hora.
6. TalkBack: a abertura anuncia "Sancta"; o "Amém" é anunciado; a aba "Hoje" é lida só como "Hoje".
7. Fluidez (perfil): abertura e "Amém" a 60 fps em aparelho de entrada; tela "Ao vivo" e listas com
   esqueletos sem queda de quadros.
