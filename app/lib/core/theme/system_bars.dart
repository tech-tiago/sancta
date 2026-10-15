import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';

/// Barras do sistema no modo edge-to-edge (ligado em `main.dart` para todas as versões do Android; o
/// Android 15+ já impõe): transparentes, com ícones legíveis sobre o fundo. O conteúdo desenha por trás
/// delas e respeita `SafeArea`/`MediaQuery.paddingOf`. Na navegação por 3 botões o Android mantém o véu
/// de contraste (`systemNavigationBarContrastEnforced` padrão).
///
/// [background] = brilho do que está POR TRÁS das barras (escuro → ícones claros).
SystemUiOverlayStyle edgeToEdgeStyle(Brightness background) {
  final dark = background == Brightness.dark;
  return SystemUiOverlayStyle(
    statusBarColor: const Color(0x00000000),
    statusBarIconBrightness: dark ? Brightness.light : Brightness.dark,
    statusBarBrightness: dark ? Brightness.dark : Brightness.light,
    systemNavigationBarColor: const Color(0x00000000),
    systemNavigationBarDividerColor: const Color(0x00000000),
    systemNavigationBarIconBrightness: dark ? Brightness.light : Brightness.dark,
  );
}

/// Para áreas de fundo escuro (heróis em Azul Manto, boas-vindas): ícones claros nas barras do sistema
/// que ficam sobre esta área — só enquanto ela está atrás da barra (ao rolar, volta o padrão do tema).
class DarkSystemBars extends StatelessWidget {
  const DarkSystemBars({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) => AnnotatedRegion<SystemUiOverlayStyle>(value: edgeToEdgeStyle(Brightness.dark), child: child);
}
