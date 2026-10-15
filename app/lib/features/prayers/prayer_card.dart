import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/brand/brand.dart';
import '../../core/models/models.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';

/// Meta de uma oração: "8 min · Áudio · Terço".
String prayerMeta(PrayerItem prayer, {bool category = true}) => [
  if (prayer.durationMinutes != null) '${prayer.durationMinutes} min',
  if (prayer.hasAudio) 'Áudio',
  if (category && prayer.categoryName != null) prayer.categoryName!,
].join(' · ');

/// Cartão vertical com capa em arco (carrosséis de Hoje e Orar).
class PrayerCard extends StatelessWidget {
  const PrayerCard({super.key, required this.prayer, this.width = 136});

  final PrayerItem prayer;
  final double width;

  @override
  Widget build(BuildContext context) {
    final meta = prayerMeta(prayer, category: false);
    return SizedBox(
      width: width,
      child: PressableCard(
        onTap: () => context.push('/oracao/${prayer.slug}'),
        semanticLabel: [prayer.title, meta, if (prayer.isPremium) 'Sancta+'].where((s) => s.isNotEmpty).join(', '),
        child: ExcludeSemantics(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ArchCover(
                url: prayer.coverUrl,
                scene: skyFor(prayer.categoryName ?? prayer.slug),
                aspectRatio: .8,
                child: Stack(
                  children: [
                    if (prayer.hasAudio) const Positioned(right: 8, bottom: 8, child: _CoverChip(icon: Icons.headphones_rounded)),
                    if (prayer.isPremium) const Positioned(left: 8, bottom: 8, child: PremiumBadge(compact: true)),
                  ],
                ),
              ),
              const SizedBox(height: 10),
              Text(prayer.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: context.text.titleSmall),
              if (meta.isNotEmpty) ...[const SizedBox(height: 2), Text(meta, maxLines: 1, overflow: TextOverflow.ellipsis, style: context.text.bodySmall)],
            ],
          ),
        ),
      ),
    );
  }
}

class _CoverChip extends StatelessWidget {
  const _CoverChip({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    width: 26,
    height: 26,
    decoration: BoxDecoration(color: BrandColors.tinta.withValues(alpha: .55), shape: BoxShape.circle),
    child: Icon(icon, size: 15, color: BrandColors.pergaminho),
  );
}

/// Miniatura em arco para listas.
class ArchThumb extends StatelessWidget {
  const ArchThumb({super.key, this.url, this.scene, this.width = 44});

  final String? url;
  final SkyScene? scene;
  final double width;

  @override
  Widget build(BuildContext context) => ArchCover(url: url, scene: scene, width: width, aspectRatio: .82, bottomRadius: 8);
}

/// Linha de lista (biblioteca, favoritos).
class PrayerTile extends StatelessWidget {
  const PrayerTile({super.key, required this.prayer, this.card = true});

  final PrayerItem prayer;
  final bool card;

  @override
  Widget build(BuildContext context) {
    final subtitle = [
      if (prayer.subtitle != null) prayer.subtitle!,
      prayerMeta(prayer, category: prayer.subtitle == null),
    ].where((s) => s.isNotEmpty).join(' · ');
    return SanctaTile(
      card: card,
      onTap: () => context.push('/oracao/${prayer.slug}'),
      leading: ArchThumb(url: prayer.coverUrl, scene: skyFor(prayer.categoryName ?? prayer.slug)),
      title: prayer.title,
      subtitle: subtitle,
      subtitleMaxLines: 1,
      semanticLabel: prayer.isPremium ? 'Sancta+' : null,
      trailing: prayer.isPremium && prayer.locked
          ? const PremiumBadge(compact: true)
          : Icon(Icons.chevron_right_rounded, color: context.sancta.muted, size: 22),
    );
  }
}
