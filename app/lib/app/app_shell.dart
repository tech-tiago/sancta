import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/motion/portal_tab_icon.dart';
import '../core/theme/app_theme.dart';
import '../features/announcements/announcement_service.dart';
import '../features/notifications/push_service.dart';

/// Estrutura principal com a barra inferior: Hoje · Orar · Bíblia · Igrejas · Você.
class AppShell extends ConsumerStatefulWidget {
  const AppShell({super.key, required this.shell});

  final StatefulNavigationShell shell;

  @override
  ConsumerState<AppShell> createState() => _AppShellState();
}

class _AppShellState extends ConsumerState<AppShell> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      ref.read(pushServiceProvider).initialize(context);
      ref.read(announcementServiceProvider).showPending(context);
    });
  }

  @override
  Widget build(BuildContext context) {
    final shell = widget.shell;
    final p = context.sancta;

    return Scaffold(
      body: shell,
      bottomNavigationBar: DecoratedBox(
        decoration: BoxDecoration(
          border: Border(top: BorderSide(color: p.outline)),
        ),
        child: NavigationBar(
          selectedIndex: shell.currentIndex,
          onDestinationSelected: (index) => shell.goBranch(index, initialLocation: index == shell.currentIndex),
          destinations: [
            NavigationDestination(
              icon: PortalTabIcon(selected: false, color: p.muted),
              selectedIcon: PortalTabIcon(selected: true, color: p.primary),
              label: 'Hoje',
            ),
            const NavigationDestination(icon: Icon(Icons.adjust_rounded), selectedIcon: Icon(Icons.radio_button_checked_rounded), label: 'Orar'),
            const NavigationDestination(icon: Icon(Icons.menu_book_outlined), selectedIcon: Icon(Icons.menu_book_rounded), label: 'Bíblia'),
            const NavigationDestination(icon: Icon(Icons.church_outlined), selectedIcon: Icon(Icons.church_rounded), label: 'Igrejas'),
            const NavigationDestination(icon: Icon(Icons.person_outline_rounded), selectedIcon: Icon(Icons.person_rounded), label: 'Você'),
          ],
        ),
      ),
    );
  }
}
