import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/state/session_controller.dart';
import '../core/theme/app_theme.dart';
import '../core/widgets/common.dart';
import '../features/auth/forgot_password_screen.dart';
import '../features/auth/login_screen.dart';
import '../features/auth/register_screen.dart';
import '../features/auth/welcome_screen.dart';
import '../features/bible/annotations_screen.dart';
import '../features/bible/bible_screen.dart';
import '../features/bible/chapter_screen.dart';
import '../features/bible/search_screen.dart';
import '../features/churches/church_detail_screen.dart';
import '../features/churches/churches_screen.dart';
import '../features/churches/suggest_church_screen.dart';
import '../features/community/intentions_screen.dart';
import '../features/home/home_screen.dart';
import '../features/journeys/journey_screen.dart';
import '../features/live/live_screen.dart';
import '../features/liturgy/liturgy_screen.dart';
import '../features/news/news_screen.dart';
import '../features/prayers/library_screen.dart';
import '../features/prayers/prayer_screen.dart';
import '../features/premium/paywall_screen.dart';
import '../features/profile/favorites_screen.dart';
import '../features/profile/help_screen.dart';
import '../features/profile/page_screen.dart';
import '../features/profile/profile_screen.dart';
import '../features/profile/settings_screen.dart';
import 'app_shell.dart';
import 'splash_screen.dart';

final rootNavigatorKey = GlobalKey<NavigatorState>();

/// Notifica o roteador quando a sessão muda (login/logout/expiração).
class _SessionListenable extends ChangeNotifier {
  _SessionListenable(Ref ref) {
    ref.listen(sessionProvider, (_, _) => notifyListeners());
  }
}

const _publicRoutes = {'/boas-vindas', '/entrar', '/cadastrar', '/esqueci-senha'};

/// Rotas antigas (antes do rebrand "Portal") → rotas novas. Links, avisos e atalhos antigos continuam abrindo.
const legacyRoutes = {'/rezar': '/orar', '/perfil': '/voce', '/configuracoes': '/ajustes'};

final routerProvider = Provider<GoRouter>((ref) {
  final listenable = _SessionListenable(ref);

  return GoRouter(
    navigatorKey: rootNavigatorKey,
    initialLocation: '/carregando',
    refreshListenable: listenable,
    redirect: (context, state) {
      final session = ref.read(sessionProvider);
      final path = state.matchedLocation;

      if (!session.initialized) return path == '/carregando' ? null : '/carregando';
      if (!session.canEnterApp) return _publicRoutes.contains(path) ? null : '/boas-vindas';
      if (path == '/carregando' || (session.isAuthenticated && _publicRoutes.contains(path))) return '/hoje';
      return null;
    },
    routes: [
      GoRoute(path: '/carregando', builder: (_, _) => const SplashScreen()),
      GoRoute(path: '/boas-vindas', builder: (_, _) => const WelcomeScreen()),
      GoRoute(path: '/entrar', builder: (_, _) => const LoginScreen()),
      GoRoute(path: '/cadastrar', builder: (_, _) => const RegisterScreen()),
      GoRoute(path: '/esqueci-senha', builder: (_, _) => const ForgotPasswordScreen()),

      // Abas: Hoje · Orar · Bíblia · Igrejas · Você
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => AppShell(shell: shell),
        branches: [
          StatefulShellBranch(
            routes: [GoRoute(path: '/hoje', builder: (_, _) => const HomeScreen())],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: '/orar', builder: (_, _) => const LibraryScreen())],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: '/biblia', builder: (_, _) => const BibleScreen())],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: '/igrejas', builder: (_, _) => const ChurchesScreen())],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: '/voce', builder: (_, _) => const ProfileScreen())],
          ),
        ],
      ),

      for (final entry in legacyRoutes.entries) GoRoute(path: entry.key, redirect: (_, _) => entry.value),

      GoRoute(
        path: '/oracao/:slug',
        builder: (_, s) => PrayerScreen(slug: s.pathParameters['slug']!),
      ),
      GoRoute(
        path: '/jornada/:slug',
        builder: (_, s) => JourneyScreen(slug: s.pathParameters['slug']!),
      ),
      GoRoute(
        path: '/biblia/:book/:chapter',
        builder: (_, s) => ChapterScreen(
          book: s.pathParameters['book']!,
          chapter: int.tryParse(s.pathParameters['chapter']!) ?? 1,
          highlightVerse: int.tryParse(s.uri.queryParameters['v'] ?? ''),
        ),
      ),
      GoRoute(path: '/biblia-busca', builder: (_, _) => const BibleSearchScreen()),
      GoRoute(path: '/anotacoes', builder: (_, _) => const AnnotationsScreen()),
      GoRoute(path: '/liturgia', builder: (_, _) => const LiturgyScreen()),
      GoRoute(
        path: '/igreja/:id',
        builder: (_, s) => ChurchDetailScreen(id: s.pathParameters['id']!),
      ),
      GoRoute(
        path: '/sugerir-igreja',
        builder: (_, s) => SuggestChurchScreen(churchId: s.uri.queryParameters['igreja'], churchName: s.uri.queryParameters['nome']),
      ),
      GoRoute(path: '/noticias', builder: (_, _) => const NewsScreen()),
      GoRoute(
        path: '/noticia/:id',
        builder: (_, s) => NewsDetailScreen(id: int.tryParse(s.pathParameters['id']!) ?? 0),
      ),
      GoRoute(path: '/ao-vivo', builder: (_, _) => const LiveScreen()),
      GoRoute(path: '/intencoes', builder: (_, _) => const IntentionsScreen()),
      GoRoute(path: '/planos', builder: (_, _) => const PaywallScreen()),
      GoRoute(path: '/ajustes', builder: (_, _) => const SettingsScreen()),
      GoRoute(path: '/favoritos', builder: (_, _) => const FavoritesScreen()),
      GoRoute(path: '/ajuda', builder: (_, _) => const HelpScreen()),
      GoRoute(
        path: '/pagina/:slug',
        builder: (_, s) => PageScreen(slug: s.pathParameters['slug']!),
      ),
    ],
  );
});

/// Ações que exigem conta: leva visitantes para o login com uma explicação.
bool requireAccount(BuildContext context, WidgetRef ref, {String reason = 'Entre na sua conta para usar este recurso.'}) {
  if (ref.read(sessionProvider).isAuthenticated) return true;

  showModalBottomSheet<void>(
    context: context,
    builder: (sheetContext) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Center(child: ArchIcon(Icons.person_rounded, size: 64)),
            const SizedBox(height: 16),
            Text('Entre para continuar', textAlign: TextAlign.center, style: sheetContext.text.titleLarge),
            const SizedBox(height: 6),
            Text(reason, textAlign: TextAlign.center, style: sheetContext.text.bodyMedium),
            const SizedBox(height: 22),
            FilledButton(
              onPressed: () {
                Navigator.pop(sheetContext);
                context.push('/entrar');
              },
              child: const Text('Entrar'),
            ),
            const SizedBox(height: 10),
            OutlinedButton(
              onPressed: () {
                Navigator.pop(sheetContext);
                context.push('/cadastrar');
              },
              child: const Text('Criar conta grátis'),
            ),
          ],
        ),
      ),
    ),
  );

  return false;
}
