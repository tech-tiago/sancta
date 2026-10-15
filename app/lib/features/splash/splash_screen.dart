import 'package:flutter/widgets.dart';

import 'sancta_intro.dart';

export 'sancta_intro.dart' show SanctaIntro, SanctaIntroGate, SanctaIntroStill;

/// Rota `/carregando` (enquanto a sessão inicia).
///
/// Na abertura a frio quem aparece é a [SanctaIntro] (por cima do app, ver `app.dart`); esta tela
/// fica por baixo e mostra o mesmo primeiro quadro do splash nativo — Portal Pergaminho com a cruz
/// Ouro no centro, sobre Azul Manto, sem spinner — para que nada "pisque" se ela chegar a aparecer.
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) => const SanctaIntroStill();
}
