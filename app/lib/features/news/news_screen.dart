import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/state/content_providers.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/links.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/content_blocks.dart';
import 'news_tile.dart';

/// **Notícias** da Igreja (fontes cadastradas no painel).
class NewsScreen extends ConsumerWidget {
  const NewsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    appBar: AppBar(title: const Text('Notícias')),
    body: RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(newsProvider);
        await ref.read(newsProvider.future).catchError((Object _) => <Never>[]);
      },
      child: ref
          .watch(newsProvider)
          .when(
            skipLoadingOnRefresh: true,
            loading: () => const SkeletonTiles(count: 6),
            error: (e, _) => ListView(
              children: [ErrorView(error: e, onRetry: () => ref.invalidate(newsProvider))],
            ),
            data: (items) => items.isEmpty
                ? ListView(
                    children: const [
                      SizedBox(height: 60),
                      EmptyView(icon: Icons.newspaper_rounded, title: 'Sem notícias no momento'),
                    ],
                  )
                : ListView.separated(
                    padding: EdgeInsets.fromLTRB(16, 8, 16, 24 + MediaQuery.paddingOf(context).bottom),
                    itemCount: items.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 8),
                    itemBuilder: (_, i) => NewsTile(item: items[i]),
                  ),
          ),
    ),
  );
}

class NewsDetailScreen extends ConsumerWidget {
  const NewsDetailScreen({super.key, required this.id});

  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    appBar: AppBar(),
    body: ref
        .watch(newsDetailProvider(id))
        .when(
          loading: () => const SkeletonTiles(count: 4),
          error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(newsDetailProvider(id))),
          data: (n) => ListView(
            padding: EdgeInsets.fromLTRB(20, 0, 20, 32 + MediaQuery.paddingOf(context).bottom),
            children: [
              if (n.imageUrl != null) ...[
                ClipRRect(
                  borderRadius: BorderRadius.circular(SanctaRadius.card),
                  child: AspectRatio(
                    aspectRatio: 16 / 9,
                    child: NetImage(n.imageUrl, semanticLabel: n.title),
                  ),
                ),
                const SizedBox(height: 18),
              ],
              Text(
                [n.source, if (n.publishedAt != null) DateFormat("d 'de' MMMM 'de' y", 'pt_BR').format(n.publishedAt!.toLocal())].join(' · '),
                style: context.text.labelMedium?.copyWith(color: context.sancta.primary),
              ),
              const SizedBox(height: 8),
              Semantics(header: true, child: Text(n.title, style: context.text.headlineMedium)),
              const SizedBox(height: 18),
              ContentBlocksView(blocks: n.blocks),
              if (n.url != null && n.url!.startsWith('http')) ...[
                const SizedBox(height: 14),
                FilledButton.icon(
                  onPressed: () => openLink(context, n.url, inApp: true),
                  icon: const Icon(Icons.open_in_new_rounded),
                  label: Text(n.hasFullContent ? 'Ver no site original' : 'Ler a matéria completa'),
                ),
              ],
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => shareText('${n.title}\n${n.url ?? ''}'.trim()),
                icon: const Icon(Icons.ios_share_rounded),
                label: const Text('Compartilhar'),
              ),
            ],
          ),
        ),
  );
}
