import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/models/models.dart';
import '../../core/state/config_controller.dart';
import '../../core/state/content_providers.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../journeys/journey_card.dart';
import 'prayer_card.dart';

/// Aba **Orar**: busca, atalhos, jornadas e a biblioteca de orações por categoria.
class LibraryScreen extends ConsumerStatefulWidget {
  const LibraryScreen({super.key});

  @override
  ConsumerState<LibraryScreen> createState() => _LibraryScreenState();
}

class _LibraryScreenState extends ConsumerState<LibraryScreen> {
  final _search = TextEditingController();
  String? _category;
  String _query = '';

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  static String _norm(String s) => s
      .toLowerCase()
      .replaceAll(RegExp('[áàâã]'), 'a')
      .replaceAll(RegExp('[éê]'), 'e')
      .replaceAll('í', 'i')
      .replaceAll(RegExp('[óôõ]'), 'o')
      .replaceAll('ú', 'u')
      .replaceAll('ç', 'c');

  @override
  Widget build(BuildContext context) {
    final categories = ref.watch(categoriesProvider).valueOrNull ?? const <Category>[];
    final journeys = ref.watch(journeysProvider).valueOrNull ?? const <Journey>[];
    final prayers = ref.watch(prayersProvider(_category));
    final config = ref.watch(configValueProvider);
    final searching = _query.isNotEmpty;

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(journeysProvider);
          ref.invalidate(categoriesProvider);
          ref.invalidate(prayersProvider(_category));
        },
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(child: ScreenTitle('Orar', subtitle: 'Orações, jornadas e novenas')),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
                child: TextField(
                  controller: _search,
                  textInputAction: TextInputAction.search,
                  decoration: InputDecoration(
                    hintText: 'Buscar oração',
                    prefixIcon: const Icon(Icons.search_rounded),
                    suffixIcon: searching
                        ? IconButton(
                            tooltip: 'Limpar busca',
                            icon: const Icon(Icons.close_rounded),
                            onPressed: () {
                              _search.clear();
                              setState(() => _query = '');
                            },
                          )
                        : null,
                  ),
                  onChanged: (v) => setState(() => _query = _norm(v.trim())),
                ),
              ),
            ),

            if (!searching) ...[
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                  child: Builder(
                    builder: (context) {
                      final liturgy = _QuickCard(
                        icon: Icons.auto_stories_outlined,
                        title: 'Liturgia diária',
                        subtitle: 'Leituras de hoje',
                        onTap: () => context.push('/liturgia'),
                      );
                      final second = config.feature('intentions')
                          ? _QuickCard(
                              icon: Icons.volunteer_activism_outlined,
                              title: 'Intenções',
                              subtitle: 'Reze por alguém',
                              onTap: () => context.push('/intencoes'),
                            )
                          : _QuickCard(icon: Icons.menu_book_outlined, title: 'Bíblia', subtitle: 'Continue a leitura', onTap: () => context.go('/biblia'));
                      // Letra grande: um cartão por linha (títulos inteiros, sem reticências).
                      if (largeText(context)) {
                        return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [liturgy, const SizedBox(height: 10), second]);
                      }
                      return Row(
                        children: [
                          Expanded(child: liturgy),
                          const SizedBox(width: 10),
                          Expanded(child: second),
                        ],
                      );
                    },
                  ),
                ),
              ),
              if (journeys.isNotEmpty) ...[
                const SliverToBoxAdapter(child: SectionHeader('Jornadas e novenas')),
                SliverToBoxAdapter(
                  child: SizedBox(
                    height: carouselHeight(context, 262),
                    child: ListView.separated(
                      padding: const EdgeInsets.symmetric(horizontal: 20),
                      scrollDirection: Axis.horizontal,
                      itemCount: journeys.length,
                      separatorBuilder: (_, _) => const SizedBox(width: 14),
                      itemBuilder: (_, i) => JourneyCard(journey: journeys[i]),
                    ),
                  ),
                ),
              ],
              const SliverToBoxAdapter(child: SectionHeader('Biblioteca')),
              SliverToBoxAdapter(
                child: SizedBox(
                  height: 52,
                  child: ListView(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    scrollDirection: Axis.horizontal,
                    children: [_chip('Todas', null), for (final c in categories) _chip(c.name, c.slug)],
                  ),
                ),
              ),
            ] else
              const SliverToBoxAdapter(child: SectionHeader('Resultados')),

            const SliverToBoxAdapter(child: SizedBox(height: 6)),
            prayers.when(
              loading: () => const SliverToBoxAdapter(child: SkeletonTiles(count: 5, shrinkWrap: true, padding: EdgeInsets.symmetric(horizontal: 16))),
              error: (e, _) => SliverToBoxAdapter(
                child: ErrorView(error: e, onRetry: () => ref.invalidate(prayersProvider(_category))),
              ),
              data: (list) {
                final filtered = searching
                    ? list.where((p) => _norm('${p.title} ${p.subtitle ?? ''} ${p.categoryName ?? ''}').contains(_query)).toList()
                    : list;
                if (filtered.isEmpty) {
                  return SliverToBoxAdapter(
                    child: EmptyView(
                      icon: Icons.search_off_rounded,
                      title: searching ? 'Nenhuma oração encontrada' : 'Nada nesta categoria ainda',
                      message: searching ? 'Tente outra palavra, como "terço" ou "manhã".' : null,
                    ),
                  );
                }
                return SliverPadding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  sliver: SliverList.separated(
                    itemCount: filtered.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 8),
                    itemBuilder: (_, i) => PrayerTile(prayer: filtered[i]),
                  ),
                );
              },
            ),
            const SliverToBoxAdapter(child: SizedBox(height: 28)),
          ],
        ),
      ),
    );
  }

  Widget _chip(String label, String? slug) => Padding(
    padding: const EdgeInsets.only(right: 8),
    child: SanctaChoiceChip(label: label, selected: _category == slug, onSelected: () => setState(() => _category = slug)),
  );
}

class _QuickCard extends StatelessWidget {
  const _QuickCard({required this.icon, required this.title, required this.subtitle, required this.onTap});

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return Material(
      color: p.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(SanctaRadius.tile),
        side: p.isDark ? BorderSide.none : BorderSide(color: p.outline),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(icon, size: 38),
              const SizedBox(height: 12),
              Text(title, maxLines: largeText(context) ? 2 : 1, overflow: TextOverflow.ellipsis, style: context.text.titleSmall),
              Text(subtitle, maxLines: largeText(context) ? 2 : 1, overflow: TextOverflow.ellipsis, style: context.text.bodySmall),
            ],
          ),
        ),
      ),
    );
  }
}
