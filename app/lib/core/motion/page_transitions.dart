import 'package:flutter/material.dart';

import 'motion.dart';

/// Transição de página do Sancta: uma "passagem" discreta (fade-through com leve subida).
///
/// Ida (300 ms): o fundo da nova tela cobre a anterior nos primeiros ~90 ms (nunca aparece um
/// quadro preto nem duas telas sobrepostas) e o conteúdo surge com fade, subindo 2% da altura com
/// a curva [SanctaMotion.enter]. Volta (250 ms): o mesmo caminho ao contrário.
/// Com "reduzir movimento": só os fades, comprimidos nos primeiros ~40% (sem deslocamento).
class SanctaPageTransitionsBuilder extends PageTransitionsBuilder {
  const SanctaPageTransitionsBuilder();

  @override
  Duration get transitionDuration => SanctaMotion.page;

  @override
  Duration get reverseTransitionDuration => SanctaMotion.pageReverse;

  @override
  Widget buildTransitions<T>(PageRoute<T> route, BuildContext context, Animation<double> animation, Animation<double> secondaryAnimation, Widget child) =>
      SanctaPassage(animation: animation, reduced: SanctaMotion.reduced(context), child: child);
}

/// A transição em si (separada para poder ser reutilizada e testada fora de rotas).
class SanctaPassage extends StatelessWidget {
  const SanctaPassage({super.key, required this.animation, required this.child, this.reduced = false, this.fillColor});

  /// Progresso da rota: 0 = fora da tela, 1 = na tela.
  final Animation<double> animation;
  final Widget child;
  final bool reduced;

  /// Cor que cobre a tela anterior durante a passagem (padrão: fundo do tema).
  final Color? fillColor;

  static final Animatable<double> _fill = _FillTween(const Interval(0, .3, curve: Curves.easeOut));
  static final Animatable<double> _fillReduced = _FillTween(const Interval(0, .15, curve: Curves.easeOut));
  static final Animatable<double> _content = CurveTween(curve: const Interval(.18, .9, curve: Curves.easeOut));
  static final Animatable<double> _contentReduced = CurveTween(curve: const Interval(.1, .45, curve: Curves.easeOut));
  static final Animatable<Offset> _rise = Tween<Offset>(begin: const Offset(0, .02), end: Offset.zero).chain(CurveTween(curve: SanctaMotion.enter));

  @override
  Widget build(BuildContext context) {
    final fill = fillColor ?? Theme.of(context).scaffoldBackgroundColor;
    return Stack(
      fit: StackFit.passthrough,
      children: [
        Positioned.fill(
          child: IgnorePointer(
            child: FadeTransition(
              opacity: animation.drive(reduced ? _fillReduced : _fill),
              child: ColoredBox(color: fill),
            ),
          ),
        ),
        // Estrutura fixa (o SlideTransition fica mesmo com "reduzir movimento", parado em zero) para
        // não recriar o estado da página se a preferência mudar no meio do uso.
        SlideTransition(
          position: reduced ? const AlwaysStoppedAnimation(Offset.zero) : animation.drive(_rise),
          child: FadeTransition(opacity: animation.drive(reduced ? _contentReduced : _content), child: child),
        ),
      ],
    );
  }
}

/// Opacidade do "véu" de fundo: segue a curva durante a transição e some (0) com a tela parada,
/// para não pintar uma camada a mais atrás de toda página.
class _FillTween extends Animatable<double> {
  const _FillTween(this.curve);

  final Curve curve;

  @override
  double transform(double t) => t >= 1 ? 0 : curve.transform(t);
}
