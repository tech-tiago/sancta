import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models/models.dart';
import '../../core/state/content_providers.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/content_blocks.dart';

/// Páginas institucionais editadas no painel (termos, privacidade, sobre…).
class PageScreen extends ConsumerWidget {
  const PageScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    appBar: AppBar(),
    body: ref
        .watch(pageProvider(slug))
        .when(
          loading: () => const SkeletonTiles(count: 4),
          error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(pageProvider(slug))),
          data: (page) => Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 680),
              child: ListView(
                padding: EdgeInsets.fromLTRB(22, 0, 22, 32 + MediaQuery.paddingOf(context).bottom),
                children: [
                  Semantics(header: true, child: Text('${page['title']}', style: context.text.headlineMedium)),
                  const SizedBox(height: 18),
                  ContentBlocksView(blocks: ContentBlock.listFrom(page['blocks']), scale: .9),
                ],
              ),
            ),
          ),
        ),
  );
}
