import 'package:flutter/painting.dart';

/// Cores da marca Sancta "Portal".
///
/// Os 5 primeiros são os **tokens oficiais** (não crie outras cores de marca). Os demais são derivações
/// documentadas em `docs/design/MARCA.md`, com o contraste WCAG medido entre parênteses.
abstract final class BrandColors {
  // ---------------------------------------------------------------------------
  // Tokens oficiais
  // ---------------------------------------------------------------------------

  /// Azul Manto — cor principal: marca, botões, links, navegação ativa.
  /// Pergaminho sobre Azul Manto: 8,84:1 · Azul Manto sobre branco: 9,52:1.
  static const Color azulManto = Color(0xFF1F3D9C);

  /// Noite — fundos escuros e superfícies do tema escuro.
  static const Color noite = Color(0xFF132760);

  /// Tinta — texto; fundo mais profundo do tema escuro. Sobre Pergaminho: 15,88:1.
  static const Color tinta = Color(0xFF0E1A3A);

  /// Ouro — **só a cruz e destaques pequenos** (selo Sancta+, sequência). Nunca em grandes áreas
  /// nem como texto sobre fundo claro (sobre Pergaminho: 1,76:1). Sobre Azul Manto: 5,04:1.
  static const Color ouro = Color(0xFFE3B65B);

  /// Pergaminho — fundos claros; texto e marca sobre azul.
  static const Color pergaminho = Color(0xFFFBF6EC);

  // ---------------------------------------------------------------------------
  // Derivações permitidas
  // ---------------------------------------------------------------------------

  /// Branco — cartões sobre Pergaminho.
  static const Color branco = Color(0xFFFFFFFF);

  /// Névoa — texto secundário sobre Azul Manto (slogan, datas no widget). Sobre Azul Manto: 6,54:1.
  static const Color nevoa = Color(0xFFC8D5FF);

  /// Tinta suave — texto secundário em fundos claros. Sobre Pergaminho: 5,40:1 · sobre branco: 5,82:1.
  static const Color tintaSuave = Color(0xFF5A6487);

  /// Tinta apagada — ícones inativos/desabilitados (mínimo 3:1). Sobre branco: 3,73:1 · Pergaminho: 3,46:1.
  static const Color tintaApagada = Color(0xFF7C849C);

  /// Véu azul — fundo de ícone (~10% de Azul Manto sobre branco). Azul Manto sobre ele: 8,06:1.
  static const Color veuAzul = Color(0xFFE8ECF8);

  /// Azul Luz — Azul Manto clareado para o tema escuro (links, aba ativa). Sobre Tinta: 8,35:1 · Noite: 6,89:1.
  static const Color azulLuz = Color(0xFF9DB2FF);

  /// Vermelho funcional (erros) — tema claro (sobre branco 6,54:1) e escuro (sobre Tinta 10,02:1).
  static const Color erro = Color(0xFFB3261E);
  static const Color erroEscuro = Color(0xFFF2B8B5);

  /// Verde funcional (sucesso) — tema claro (sobre branco 5,33:1) e escuro (sobre Tinta 9,53:1).
  static const Color sucesso = Color(0xFF1E7A4C);
  static const Color sucessoEscuro = Color(0xFF7DD3A0);

  // ---------------------------------------------------------------------------
  // Ícone "Tingido" (monocromático, como o mockup aprovado)
  // ---------------------------------------------------------------------------
  static const Color tingidoFundo = Color(0xFF111111);
  static const Color tingidoArco = Color(0xFFDADADA);
  static const Color tingidoCruz = Color(0xFF9B9B9B);
}
