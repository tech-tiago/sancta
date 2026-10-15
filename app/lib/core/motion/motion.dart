import 'package:flutter/material.dart';

import 'page_transitions.dart';

export 'page_transitions.dart' show SanctaPageTransitionsBuilder;

/// Tokens de movimento do Sancta "Portal" — durações, curvas e transições de página.
///
/// Princípios (ver `docs/design/MOTION.md`): **calmo** (nada pisca nem quica), **reverente** (a luz
/// vem de dentro do Portal, o Ouro é só acento) e **rápido** (a interface responde em ≤ 150 ms e
/// nunca espera uma animação terminar para funcionar).
///
/// Use sempre estes tokens em vez de valores soltos. Toda animação deve consultar [reduced]
/// ("Remover animações" do sistema): sem deslocamento, escala ou repetição — no máximo um fade curto.
abstract final class SanctaMotion {
  // ---------------------------------------------------------------------------
  // Durações
  // ---------------------------------------------------------------------------

  /// 90 ms — resposta imediata ao toque (afundar um cartão).
  static const press = Duration(milliseconds: 90);

  /// 150 ms — microinterações: troca de ícone, cor, opacidade.
  static const fast = Duration(milliseconds: 150);

  /// 250 ms — padrão para elementos que mudam de estado (abas, chips, expandir).
  static const base = Duration(milliseconds: 250);

  /// 300 ms — transição entre telas (ida); a volta usa [pageReverse].
  static const page = Duration(milliseconds: 300);

  /// 250 ms — transição entre telas (volta).
  static const pageReverse = Duration(milliseconds: 250);

  /// 400 ms — deslocamentos maiores (carrossel, folhas, destaques).
  static const slow = Duration(milliseconds: 400);

  /// 1500 ms — abertura do app (do Portal ao wordmark com o slogan).
  static const intro = Duration(milliseconds: 1500);

  /// 1750 ms — celebração "Amém" completa (limite: 1,8 s).
  static const celebration = Duration(milliseconds: 1750);

  /// 1600 ms — um ciclo do ponto pulsante do selo AO VIVO.
  static const pulse = Duration(milliseconds: 1600);

  /// 1600 ms — um ciclo do brilho dos esqueletos de carregamento.
  static const shimmer = Duration(milliseconds: 1600);

  /// 2400 ms — um ciclo da "luz respirando" (espera calma, sem spinner).
  static const breath = Duration(milliseconds: 2400);

  /// 120 ms — fade curto que substitui movimento quando [reduced] está ligado.
  static const reducedFade = Duration(milliseconds: 120);

  // ---------------------------------------------------------------------------
  // Curvas (nomes semânticos)
  // ---------------------------------------------------------------------------

  /// Mudanças de estado dentro da tela (M3 "standard"): sai rápido e assenta suave.
  static const Curve standard = Cubic(0.2, 0.0, 0.0, 1.0);

  /// Movimentos de destaque que atravessam a tela (o Portal encaixando no wordmark).
  static const Curve emphasized = Curves.easeInOutCubicEmphasized;

  /// Algo que **entra** ou aparece (desacelera até parar, sem rebote).
  static const Curve enter = Cubic(0.05, 0.7, 0.1, 1.0);

  /// Algo que **sai** ou desaparece (acelera ao partir).
  static const Curve exit = Cubic(0.3, 0.0, 0.8, 0.15);

  /// Ciclos contínuos (luz respirando, pulso): senoide, sem trancos nas pontas.
  static const Curve breathing = Curves.easeInOutSine;

  // ---------------------------------------------------------------------------
  // Acessibilidade
  // ---------------------------------------------------------------------------

  /// "Remover animações" ligado no sistema (Android: Acessibilidade → Remover animações, ou escala de
  /// animação 0). Quando `true`: nada de deslocamento, escala, rotação ou repetição.
  static bool reduced(BuildContext context) => MediaQuery.maybeDisableAnimationsOf(context) ?? false;

  /// Duração respeitando "reduzir movimento" (zero = muda de estado na hora).
  static Duration of(BuildContext context, Duration duration) => reduced(context) ? Duration.zero : duration;

  /// Para fades que devem continuar existindo com "reduzir movimento" (só encurtam para [reducedFade]).
  static Duration fadeOf(BuildContext context, Duration duration) => reduced(context) && duration > reducedFade ? reducedFade : duration;

  // ---------------------------------------------------------------------------
  // Transições de página
  // ---------------------------------------------------------------------------

  /// Transições de página do app: "passagem" própria do Sancta (fade-through com leve subida, 300 ms)
  /// no Android/desktop; no iOS mantém a transição nativa (gesto de voltar deslizando).
  static const PageTransitionsTheme pageTransitions = PageTransitionsTheme(
    builders: {
      TargetPlatform.android: SanctaPageTransitionsBuilder(),
      TargetPlatform.fuchsia: SanctaPageTransitionsBuilder(),
      TargetPlatform.windows: SanctaPageTransitionsBuilder(),
      TargetPlatform.linux: SanctaPageTransitionsBuilder(),
      TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
      TargetPlatform.macOS: CupertinoPageTransitionsBuilder(),
    },
  );
}
