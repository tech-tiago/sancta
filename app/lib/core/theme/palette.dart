import 'package:flutter/material.dart';

import '../brand/brand.dart';

/// Tokens de cor de cada tema do Sancta "Portal" (claro, escuro e sépia).
///
/// Todas as cores derivam dos tokens oficiais de [BrandColors] (ver docs/design/MARCA.md):
/// Azul Manto, Noite, Tinta, Ouro e Pergaminho, mais as derivações documentadas. Contraste WCAG
/// medido entre parênteses (texto normal precisa de 4,5:1; ícones e texto grande, 3:1).
class SanctaPalette {
  const SanctaPalette({
    required this.brightness,
    required this.background,
    required this.surface,
    required this.surfaceAlt,
    required this.primary,
    required this.onPrimary,
    required this.text,
    required this.textSoft,
    required this.muted,
    required this.outline,
    required this.iconBg,
    required this.heroStart,
    required this.heroEnd,
    required this.danger,
    required this.success,
  });

  final Brightness brightness;

  /// Fundo das telas.
  final Color background;

  /// Cartões, barras e folhas.
  final Color surface;

  /// Superfície de apoio (campos, chips, grupos, trilhas de progresso).
  final Color surfaceAlt;

  /// Cor de ação (botões, links, aba ativa).
  final Color primary;
  final Color onPrimary;

  /// Texto principal e secundário (ambos AA para texto normal).
  final Color text;
  final Color textSoft;

  /// Ícones inativos e detalhes (mínimo 3:1 — não use para texto pequeno no tema claro).
  final Color muted;

  /// Linhas finas e bordas.
  final Color outline;

  /// Fundo do quadrado arredondado dos ícones (estilo "Para hoje").
  final Color iconBg;

  /// Degradê dos cartões de destaque (Azul Manto → Noite), com texto [onHero].
  final Color heroStart;
  final Color heroEnd;

  final Color danger;
  final Color success;

  bool get isDark => brightness == Brightness.dark;

  /// Texto e marca sobre os cartões azuis (Pergaminho sobre Azul Manto: 8,84:1).
  Color get onHero => BrandColors.pergaminho;

  /// Texto secundário sobre azul (Névoa sobre Azul Manto: 6,54:1).
  Color get onHeroSoft => BrandColors.nevoa;

  /// Claro (padrão): Pergaminho, cartões brancos, Azul Manto e Tinta.
  static const claro = SanctaPalette(
    brightness: Brightness.light,
    background: BrandColors.pergaminho,
    surface: BrandColors.branco,
    surfaceAlt: Color(0xFFF3EEE3),
    primary: BrandColors.azulManto, // 9,52:1 sobre branco
    onPrimary: BrandColors.pergaminho, // 8,84:1
    text: BrandColors.tinta, // 15,88:1 sobre Pergaminho
    textSoft: BrandColors.tintaSuave, // 5,82:1 branco · 5,40:1 Pergaminho
    muted: BrandColors.tintaApagada, // 3,73:1 (ícones)
    outline: Color(0xFFECE5D6),
    iconBg: BrandColors.veuAzul, // Azul Manto sobre ele: 8,06:1
    heroStart: BrandColors.azulManto,
    heroEnd: BrandColors.noite,
    danger: BrandColors.erro,
    success: BrandColors.sucesso,
  );

  /// Escuro: fundo Tinta, superfícies Noite, Azul Luz como cor de ação.
  static const escuro = SanctaPalette(
    brightness: Brightness.dark,
    background: BrandColors.tinta,
    surface: BrandColors.noite,
    surfaceAlt: Color(0xFF1A3170), // Pergaminho sobre ele: 11,35:1
    primary: BrandColors.azulLuz, // 6,89:1 sobre Noite · 8,35:1 sobre Tinta
    onPrimary: BrandColors.tinta, // 8,35:1
    text: BrandColors.pergaminho, // 13,11:1 sobre Noite
    textSoft: Color(0xFFB8C3E3), // 8,04:1 sobre Noite
    muted: Color(0xFF8D99C2), // 5,02:1 sobre Noite
    outline: Color(0x24FBF6EC),
    iconBg: Color(0xFF293D79), // Azul Luz sobre ele: 5,03:1
    heroStart: Color(0xFF243F95),
    heroEnd: BrandColors.noite,
    danger: BrandColors.erroEscuro,
    success: BrandColors.sucessoEscuro,
  );

  /// Sépia (leitura): papel envelhecido, tinta quente e Azul Manto para ações.
  static const sepia = SanctaPalette(
    brightness: Brightness.light,
    background: Color(0xFFF3EBD9),
    surface: Color(0xFFFAF5E9),
    surfaceAlt: Color(0xFFEDE3CC),
    primary: BrandColors.azulManto, // 8,75:1 sobre a superfície
    onPrimary: BrandColors.pergaminho,
    text: Color(0xFF2B2418), // 12,93:1
    textSoft: Color(0xFF5E513D), // 6,51:1
    muted: Color(0xFF857660), // 4,06:1 (ícones)
    outline: Color(0xFFE2D6BC),
    iconBg: Color(0xFFE9E2D2), // Azul Manto sobre ele: 7,5:1
    heroStart: BrandColors.azulManto,
    heroEnd: BrandColors.noite,
    danger: Color(0xFF9E2B25),
    success: Color(0xFF3E6B3A),
  );

  /// Cores de destaque de versículos (mesmas chaves da API).
  static const highlights = <String, Color>{
    'gold': Color(0x66E3B65B),
    'blue': Color(0x559DB2FF),
    'green': Color(0x5584C79A),
    'rose': Color(0x55E89AB8),
    'lilac': Color(0x55B39AE8),
  };
}
