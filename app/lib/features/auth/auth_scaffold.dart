import 'package:flutter/material.dart';

import '../../core/brand/brand.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/system_bars.dart';

/// Layout das telas de conta: cabeçalho Azul Manto com o wordmark e o céu nascendo no arco.
class AuthScaffold extends StatelessWidget {
  const AuthScaffold({super.key, required this.title, required this.subtitle, required this.children, this.scene = SkyScene.aurora});

  final String title;
  final String subtitle;
  final List<Widget> children;
  final SkyScene scene;

  @override
  Widget build(BuildContext context) {
    final canPop = Navigator.of(context).canPop();
    return Scaffold(
      body: ListView(
        padding: EdgeInsets.zero,
        children: [
          _AuthHeader(scene: scene, canPop: canPop),
          Padding(
            padding: EdgeInsets.fromLTRB(24, 26, 24, 32 + MediaQuery.paddingOf(context).bottom),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Semantics(header: true, child: Text(title, style: context.text.headlineMedium)),
                const SizedBox(height: 6),
                Text(subtitle, style: context.text.bodyLarge?.copyWith(color: context.sancta.textSoft)),
                const SizedBox(height: 26),
                ...children,
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _AuthHeader extends StatelessWidget {
  const _AuthHeader({required this.scene, required this.canPop});

  final SkyScene scene;
  final bool canPop;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return DarkSystemBars(
      child: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(colors: [p.heroStart, p.heroEnd], begin: Alignment.topCenter, end: Alignment.bottomCenter),
          borderRadius: const BorderRadius.vertical(bottom: Radius.circular(32)),
        ),
        clipBehavior: Clip.antiAlias,
        child: SafeArea(
          bottom: false,
          child: SizedBox(
            height: 206,
            child: Stack(
              children: [
                if (canPop)
                  Positioned(
                    left: 4,
                    top: 4,
                    child: IconButton(
                      tooltip: 'Voltar',
                      icon: const Icon(Icons.arrow_back_rounded, color: BrandColors.pergaminho),
                      onPressed: () => Navigator.maybePop(context),
                    ),
                  ),
                const Align(
                  alignment: Alignment(0, -.62),
                  child: SanctaWordmark(variant: WordmarkVariant.pergaminho, height: 34),
                ),
                Align(
                  alignment: Alignment.bottomCenter,
                  child: Padding(
                    padding: const EdgeInsets.only(bottom: 22),
                    child: SizedBox(
                      width: 104,
                      height: 82,
                      child: ArchFrame(
                        bottomRadius: 12,
                        borderColor: const Color(0x40FBF6EC),
                        borderWidth: 1.5,
                        child: SkyArt(scene: scene),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

String? validateEmail(String? value) {
  final v = value?.trim() ?? '';
  if (v.isEmpty) return 'Informe seu e-mail.';
  if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(v)) return 'E-mail inválido.';
  return null;
}

String? validatePassword(String? value) {
  final v = value ?? '';
  if (v.length < 8) return 'Use pelo menos 8 caracteres.';
  if (!RegExp(r'[A-Za-z]').hasMatch(v) || !RegExp(r'[0-9]').hasMatch(v)) return 'Use letras e números.';
  return null;
}

/// Botão "Continuar com o Google" (fundo branco, como pedem as diretrizes do Google).
class GoogleButton extends StatelessWidget {
  const GoogleButton({super.key, required this.onPressed, this.loading = false, this.label = 'Continuar com o Google'});

  final VoidCallback? onPressed;
  final bool loading;
  final String label;

  @override
  Widget build(BuildContext context) => FilledButton.icon(
    onPressed: loading ? null : onPressed,
    style: FilledButton.styleFrom(
      backgroundColor: Colors.white,
      foregroundColor: const Color(0xFF1F1F1F),
      disabledBackgroundColor: Colors.white70,
      minimumSize: const Size.fromHeight(54),
      side: context.sancta.isDark ? null : const BorderSide(color: Color(0xFFDADCE0)),
    ),
    icon: loading
        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: BrandColors.azulManto))
        : const Text(
            'G',
            style: TextStyle(fontFamily: BrandFonts.figtree, fontWeight: FontWeight.w800, fontSize: 18, color: Color(0xFF4285F4)),
          ),
    label: Text(label),
  );
}

/// Mensagem de erro de formulário (vermelho funcional, com ícone).
class FormError extends StatelessWidget {
  const FormError(this.message, {super.key});

  final String message;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return Semantics(
      liveRegion: true,
      child: Container(
        margin: const EdgeInsets.only(bottom: 14),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: p.danger.withValues(alpha: .08), borderRadius: BorderRadius.circular(SanctaRadius.icon)),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.error_outline_rounded, color: p.danger, size: 20),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                message,
                style: context.text.bodyMedium?.copyWith(color: p.danger, fontWeight: FontWeight.w500),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Divisor "ou" entre o login por e-mail e o Google.
class OrDivider extends StatelessWidget {
  const OrDivider({super.key});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 18),
    child: Row(
      children: [
        Expanded(child: Divider(color: context.sancta.outline)),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Text('ou', style: context.text.bodySmall),
        ),
        Expanded(child: Divider(color: context.sancta.outline)),
      ],
    ),
  );
}
