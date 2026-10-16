import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/brand/editorial_art.dart';
import '../../core/models/models.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../prayers/prayer_card.dart';

/// Dia atual de uma jornada ("Dia 3 de 9").
String journeyDayLabel(Journey j) {
  final day = j.completedSteps + 1 > j.stepsCount ? j.stepsCount : j.completedSteps + 1;
  return j.stepsCount == 0 ? j.typeLabel : 'Dia $day de ${j.stepsCount}';
}

/// Cartão de jornada com capa em arco (carrosséis).
class JourneyCard extends StatelessWidget {
  const JourneyCard({super.key, required this.journey, this.width = 216});

  final Journey journey;
  final double width;

  @override
  Widget build(BuildContext context) {
    final j = journey;
    final meta = [if (j.typeLabel.isNotEmpty) j.typeLabel, if (j.stepsCount > 0) '${j.stepsCount} dias'].join(' · ');
    return SizedBox(
      width: width,
      child: PressableCard(
        onTap: () => context.push('/jornada/${j.slug}'),
        semanticLabel: [j.title, meta, if (j.completedSteps > 0) journeyDayLabel(j), if (j.isPremium) 'Sancta+'].join(', '),
        child: ExcludeSemantics(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ArchCover(
                url: j.coverUrl,
                assetPath: EditorialArt.forJourney(j.title, j.slug),
                scene: skyFor(j.slug),
                aspectRatio: 1.3,
                child: j.isPremium ? const Stack(children: [Positioned(left: 10, bottom: 10, child: PremiumBadge(compact: true))]) : null,
              ),
              const SizedBox(height: 10),
              Text(meta, maxLines: 1, overflow: TextOverflow.ellipsis, style: context.text.labelMedium),
              const SizedBox(height: 2),
              Text(j.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: context.text.titleSmall),
              if (j.completedSteps > 0) ...[const SizedBox(height: 8), SanctaProgress(value: j.progress, height: 5)],
            ],
          ),
        ),
      ),
    );
  }
}

/// Jornada em andamento ("Continue de onde parou").
class JourneyWideCard extends StatelessWidget {
  const JourneyWideCard({super.key, required this.journey});

  final Journey journey;

  @override
  Widget build(BuildContext context) {
    final j = journey;
    final p = context.sancta;
    return Material(
      color: p.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(SanctaRadius.card),
        side: p.isDark ? BorderSide.none : BorderSide(color: p.outline),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/jornada/${j.slug}'),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              ArchThumb(url: j.coverUrl, assetPath: EditorialArt.forJourney(j.title, j.slug), scene: skyFor(j.slug), width: 56),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(journeyDayLabel(j), style: context.text.labelMedium),
                    const SizedBox(height: 2),
                    Text(j.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: context.text.titleSmall),
                    const SizedBox(height: 8),
                    SanctaProgress(value: j.progress, height: 5),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              PlayButton(onPressed: () => context.push('/jornada/${j.slug}'), size: 44, semanticLabel: 'Continuar ${j.title}'),
            ],
          ),
        ),
      ),
    );
  }
}
