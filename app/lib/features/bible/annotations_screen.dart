import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/state/content_providers.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/palette.dart';
import '../../core/widgets/common.dart';

/// Meus destaques, notas e marcadores.
class AnnotationsScreen extends StatelessWidget {
  const AnnotationsScreen({super.key});

  @override
  Widget build(BuildContext context) => const DefaultTabController(
    length: 3,
    child: Scaffold(
      appBar: _AnnotationsAppBar(),
      body: TabBarView(
        children: [
          _List(type: 'highlights'),
          _List(type: 'notes'),
          _List(type: 'bookmarks'),
        ],
      ),
    ),
  );
}

class _AnnotationsAppBar extends StatelessWidget implements PreferredSizeWidget {
  const _AnnotationsAppBar();

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight + 62);

  @override
  Widget build(BuildContext context) => AppBar(
    title: const Text('Minhas anotações'),
    bottom: const PillTabBar(
      tabs: [
        Tab(height: 48, text: 'Destaques'),
        Tab(height: 48, text: 'Notas'),
        Tab(height: 48, text: 'Marcadores'),
      ],
    ),
  );
}

class _List extends ConsumerWidget {
  const _List({required this.type});

  final String type;

  static const _empty = {
    'highlights': (Icons.format_paint_outlined, 'Nenhum destaque ainda'),
    'notes': (Icons.sticky_note_2_outlined, 'Nenhuma nota ainda'),
    'bookmarks': (Icons.bookmark_border_rounded, 'Nenhum marcador ainda'),
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) => ref
      .watch(annotationsProvider(type))
      .when(
        loading: () => const SkeletonTiles(count: 5),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(annotationsProvider(type))),
        data: (items) => items.isEmpty
            ? EmptyView(icon: _empty[type]!.$1, title: _empty[type]!.$2, message: 'Toque em um versículo durante a leitura para destacar, anotar ou marcar.')
            : ListView.separated(
                padding: EdgeInsets.fromLTRB(16, 6, 16, 24 + MediaQuery.paddingOf(context).bottom),
                itemCount: items.length,
                separatorBuilder: (_, _) => const SizedBox(height: 10),
                itemBuilder: (_, i) => _AnnotationCard(item: items[i]),
              ),
      );
}

class _AnnotationCard extends StatelessWidget {
  const _AnnotationCard({required this.item});

  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final highlight = SanctaPalette.highlights['${item['highlight']}'];
    return Material(
      color: p.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(SanctaRadius.tile),
        side: p.isDark ? BorderSide.none : BorderSide(color: p.outline),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/biblia/${item['book_slug']}/${item['chapter']}?v=${item['verse']}'),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  if (highlight != null) ...[
                    Container(
                      width: 12,
                      height: 12,
                      decoration: BoxDecoration(color: highlight.withAlpha(255), shape: BoxShape.circle),
                    ),
                    const SizedBox(width: 8),
                  ],
                  Expanded(
                    child: Text('${item['reference']}', style: context.text.titleSmall?.copyWith(color: p.primary)),
                  ),
                  Icon(Icons.chevron_right_rounded, color: p.muted),
                ],
              ),
              const SizedBox(height: 6),
              Text('${item['text']}', style: AppTheme.reading(context, size: 18), maxLines: 3, overflow: TextOverflow.ellipsis),
              if (item['note'] != null) ...[
                const SizedBox(height: 10),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(color: p.surfaceAlt, borderRadius: BorderRadius.circular(SanctaRadius.icon)),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(Icons.sticky_note_2_outlined, size: 18, color: p.primary),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text('${item['note']}', style: context.text.bodyMedium?.copyWith(color: p.text)),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
