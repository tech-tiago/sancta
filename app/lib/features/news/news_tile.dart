import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/models/models.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';

/// Linha de notícia: miniatura arredondada, título e fonte · data.
class NewsTile extends StatelessWidget {
  const NewsTile({super.key, required this.item});

  final NewsItem item;

  @override
  Widget build(BuildContext context) {
    final meta = [item.source, if (item.publishedAt != null) DateFormat("d 'de' MMM", 'pt_BR').format(item.publishedAt!.toLocal())].join(' · ');
    return SanctaTile(
      onTap: () => context.push('/noticia/${item.id}'),
      leading: ClipRRect(
        borderRadius: BorderRadius.circular(SanctaRadius.icon),
        child: NetImage(item.imageUrl, width: 64, height: 64, scene: skyFor(item.source), icon: Icons.article_outlined),
      ),
      title: item.title,
      subtitle: meta,
      subtitleMaxLines: 1,
    );
  }
}
