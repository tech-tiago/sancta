import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/state/config_controller.dart';
import '../core/state/session_controller.dart';
import '../core/state/theme_controller.dart';
import '../core/theme/app_theme.dart';
import '../core/theme/palette.dart';
import '../core/theme/system_bars.dart';
import '../features/splash/sancta_intro.dart';
import 'router.dart';
import 'status_gate.dart';

/// Maior escala de texto aceita do sistema (Configurações → Tamanho da fonte do Android).
const maxTextScale = 2.0;

class SanctaApp extends ConsumerStatefulWidget {
  const SanctaApp({super.key});

  @override
  ConsumerState<SanctaApp> createState() => _SanctaAppState();
}

class _SanctaAppState extends ConsumerState<SanctaApp> {
  @override
  void initState() {
    super.initState();
    ref.read(configProvider);
    ref.read(sessionProvider.notifier).bootstrap();
  }

  @override
  Widget build(BuildContext context) {
    final appearance = ref.watch(appearanceProvider);
    final router = ref.watch(routerProvider);

    // Ao entrar, adota o tema salvo na conta (se o aparelho ainda não tiver escolha própria).
    ref.listen(sessionProvider, (previous, next) {
      final theme = next.user?.theme;
      if (previous?.user == null && theme != null) ref.read(appearanceProvider.notifier).adoptFromAccount(theme);
    });

    // Claro é o padrão; Sépia substitui o claro (leitura); Escuro vale para "escuro" e para o automático à noite.
    final light = appearance.theme == SanctaTheme.sepia ? SanctaPalette.sepia : SanctaPalette.claro;
    final mode = switch (appearance.theme) {
      SanctaTheme.system => ThemeMode.system,
      SanctaTheme.dark => ThemeMode.dark,
      SanctaTheme.light || SanctaTheme.sepia => ThemeMode.light,
    };

    return MaterialApp.router(
      title: 'Sancta',
      debugShowCheckedModeBanner: false,
      routerConfig: router,
      theme: AppTheme.build(light, readingFont: appearance.readingFont),
      darkTheme: AppTheme.build(SanctaPalette.escuro, readingFont: appearance.readingFont),
      themeMode: mode,
      locale: const Locale('pt', 'BR'),
      supportedLocales: const [Locale('pt', 'BR')],
      localizationsDelegates: const [GlobalMaterialLocalizations.delegate, GlobalWidgetsLocalizations.delegate, GlobalCupertinoLocalizations.delegate],
      // Texto escalável até 2,0× (acessibilidade: letra grande do sistema), sem cortar textos —
      // coberto por test/goldens/a11y_test.dart a 1,3×, 1,6× e 2,0×.
      builder: (context, child) {
        final media = MediaQuery.of(context);
        return AnnotatedRegion<SystemUiOverlayStyle>(
          // Edge-to-edge (main.dart): barras transparentes; ícones claros/escuros conforme o tema.
          value: edgeToEdgeStyle(Theme.of(context).brightness),
          child: MediaQuery(
            data: media.copyWith(textScaler: media.textScaler.clamp(minScaleFactor: .85, maxScaleFactor: maxTextScale)),
            // Abertura animada por cima do app enquanto a sessão carrega (só na abertura a frio; ver MOTION.md).
            child: SanctaIntroGate(child: StatusGate(child: child ?? const SizedBox.shrink())),
          ),
        );
      },
    );
  }
}
