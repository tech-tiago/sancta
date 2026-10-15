import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/state/content_providers.dart';
import '../../core/widgets/common.dart';
import '../churches/churches_screen.dart';
import '../prayers/prayer_card.dart';

/// Orações e igrejas favoritas.
class FavoritesScreen extends ConsumerWidget {
  const FavoritesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) => DefaultTabController(
    length: 2,
    child: Scaffold(
      appBar: AppBar(
        title: const Text('Favoritos'),
        bottom: const PillTabBar(
          tabs: [
            Tab(height: 48, text: 'Orações'),
            Tab(height: 48, text: 'Igrejas'),
          ],
        ),
      ),
      body: TabBarView(
        children: [
          ref
              .watch(favoritePrayersProvider)
              .when(
                loading: () => const SkeletonTiles(count: 5),
                error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(favoritePrayersProvider)),
                data: (items) => items.isEmpty
                    ? EmptyView(
                        icon: Icons.favorite_border_rounded,
                        title: 'Nenhuma oração favorita',
                        message: 'Toque no coração dentro de uma oração para guardá-la aqui.',
                        action: OutlinedButton(onPressed: () => context.go('/orar'), child: const Text('Ir para Orar')),
                      )
                    : ListView.separated(
                        padding: EdgeInsets.fromLTRB(16, 8, 16, 24 + MediaQuery.paddingOf(context).bottom),
                        itemCount: items.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 8),
                        itemBuilder: (_, i) => PrayerTile(prayer: items[i]),
                      ),
              ),
          ref
              .watch(favoriteChurchesProvider)
              .when(
                loading: () => const SkeletonTiles(count: 5),
                error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(favoriteChurchesProvider)),
                data: (items) => items.isEmpty
                    ? EmptyView(
                        icon: Icons.church_outlined,
                        title: 'Nenhuma igreja favorita',
                        message: 'Favorite uma igreja para ver seus horários rapidamente.',
                        action: OutlinedButton(onPressed: () => context.go('/igrejas'), child: const Text('Ver igrejas')),
                      )
                    : ListView.separated(
                        padding: EdgeInsets.fromLTRB(16, 8, 16, 24 + MediaQuery.paddingOf(context).bottom),
                        itemCount: items.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 8),
                        itemBuilder: (_, i) => ChurchTile(church: items[i]),
                      ),
              ),
        ],
      ),
    ),
  );
}
