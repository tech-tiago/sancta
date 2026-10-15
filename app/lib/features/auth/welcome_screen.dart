import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/brand/brand.dart';
import '../../core/motion/motion.dart';
import '../../core/state/config_controller.dart';
import '../../core/state/session_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/system_bars.dart';
import '../../core/widgets/common.dart';
import 'auth_scaffold.dart';

/// Boas-vindas: apresentação em arcos (Azul Manto + céus da marca) e escolha de entrada.
class WelcomeScreen extends ConsumerStatefulWidget {
  const WelcomeScreen({super.key});

  @override
  ConsumerState<WelcomeScreen> createState() => _WelcomeScreenState();
}

class _WelcomeScreenState extends ConsumerState<WelcomeScreen> {
  final _pages = PageController();
  Timer? _timer;
  int _page = 0;
  bool _googleLoading = false;

  static const _slides = [
    (SkyScene.aurora, 'Reze todos os dias', 'Orações guiadas, terço, novenas e jornadas para criar o hábito da oração.'),
    (SkyScene.entardecer, 'A Palavra com você', 'Bíblia completa, liturgia diária e reflexões para viver o Evangelho.'),
    (SkyScene.noite, 'Igrejas perto de você', 'Horários de missa, confissão e adoração, sempre à mão.'),
  ];

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 6), (_) {
      if (!_pages.hasClients || !mounted || SanctaMotion.reduced(context)) return;
      _pages.animateToPage((_page + 1) % _slides.length, duration: SanctaMotion.slow, curve: SanctaMotion.standard);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _pages.dispose();
    super.dispose();
  }

  Future<void> _google() async {
    setState(() => _googleLoading = true);
    try {
      await ref.read(sessionProvider.notifier).loginWithGoogle();
    } catch (e) {
      if (mounted) showMessage(context, errorText(e));
    } finally {
      if (mounted) setState(() => _googleLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = ref.watch(configValueProvider);

    return DarkSystemBars(
      child: Scaffold(
        backgroundColor: BrandColors.azulManto,
        body: DecoratedBox(
          decoration: const BoxDecoration(
            gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [BrandColors.azulManto, BrandColors.noite]),
          ),
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Column(
                children: [
                  const SizedBox(height: 28),
                  const SanctaWordmark(variant: WordmarkVariant.pergaminho, height: 40),
                  const SizedBox(height: 10),
                  Text(
                    config.slogan,
                    textAlign: TextAlign.center,
                    style: context.text.bodyLarge?.copyWith(color: BrandColors.nevoa),
                  ),
                  Expanded(
                    child: PageView.builder(
                      controller: _pages,
                      itemCount: _slides.length,
                      onPageChanged: (i) => setState(() => _page = i),
                      itemBuilder: (_, i) {
                        final (scene, title, text) = _slides[i];
                        return LayoutBuilder(
                          builder: (context, box) {
                            // O espaço do texto cresce com a letra do sistema (até 2×): o arco encolhe ou some e,
                            // se ainda faltar altura, o slide rola — o texto nunca é cortado.
                            final textBlock = 130 * MediaQuery.textScalerOf(context).scale(1);
                            final archHeight = (box.maxHeight - textBlock).clamp(0.0, 230.0);
                            return SingleChildScrollView(
                              child: ConstrainedBox(
                                constraints: BoxConstraints(minHeight: box.maxHeight),
                                child: Column(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    if (archHeight > 60)
                                      SizedBox(
                                        height: archHeight,
                                        width: archHeight * .78,
                                        child: ArchFrame(
                                          bottomRadius: 18,
                                          borderColor: const Color(0x33FBF6EC),
                                          borderWidth: 1.5,
                                          child: SkyArt(scene: scene),
                                        ),
                                      ),
                                    const SizedBox(height: 20),
                                    Text(
                                      title,
                                      textAlign: TextAlign.center,
                                      style: context.text.headlineSmall?.copyWith(color: BrandColors.pergaminho),
                                    ),
                                    const SizedBox(height: 6),
                                    Text(
                                      text,
                                      textAlign: TextAlign.center,
                                      style: context.text.bodyMedium?.copyWith(color: BrandColors.nevoa),
                                    ),
                                  ],
                                ),
                              ),
                            );
                          },
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 12),
                  Semantics(
                    label: 'Página ${_page + 1} de ${_slides.length}',
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        for (var i = 0; i < _slides.length; i++)
                          AnimatedContainer(
                            duration: SanctaMotion.base,
                            margin: const EdgeInsets.symmetric(horizontal: 4),
                            width: i == _page ? 22 : 7,
                            height: 7,
                            decoration: BoxDecoration(
                              color: i == _page ? BrandColors.pergaminho : const Color(0x55FBF6EC),
                              borderRadius: BorderRadius.circular(9),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),
                  GoogleButton(onPressed: _google, loading: _googleLoading),
                  const SizedBox(height: 10),
                  FilledButton(
                    onPressed: () => context.push('/cadastrar'),
                    style: FilledButton.styleFrom(
                      backgroundColor: BrandColors.pergaminho,
                      foregroundColor: BrandColors.azulManto,
                      minimumSize: const Size.fromHeight(54),
                    ),
                    child: const Text('Criar conta com e-mail'),
                  ),
                  const SizedBox(height: 10),
                  OutlinedButton(
                    onPressed: () => context.push('/entrar'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: BrandColors.pergaminho,
                      side: const BorderSide(color: Color(0x66FBF6EC), width: 1.2),
                      minimumSize: const Size.fromHeight(54),
                    ),
                    child: const Text('Já tenho conta'),
                  ),
                  if (config.feature('guest_mode'))
                    TextButton(
                      // O roteador não tira o visitante das telas públicas (ele pode querer entrar depois):
                      // a navegação para Hoje é explícita. Achado no QA — antes o botão não saía desta tela.
                      onPressed: () async {
                        await ref.read(sessionProvider.notifier).continueAsGuest();
                        if (context.mounted) context.go('/hoje');
                      },
                      style: TextButton.styleFrom(foregroundColor: BrandColors.nevoa),
                      child: const Text('Explorar sem conta'),
                    ),
                  const SizedBox(height: 8),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
