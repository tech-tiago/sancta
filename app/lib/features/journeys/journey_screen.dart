import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/router.dart';
import '../../core/models/models.dart';
import '../../core/motion/amen_celebration.dart';
import '../../core/network/api_exception.dart';
import '../../core/providers.dart';
import '../../core/state/content_providers.dart';
import '../../core/state/session_controller.dart';
import '../../core/state/theme_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/content_blocks.dart';
import '../../core/widgets/reading_settings.dart';
import '../prayers/audio_player_bar.dart';
import 'journey_card.dart';

/// Jornada (novena, plano de leitura, desafio) com progresso por dia.
class JourneyScreen extends ConsumerWidget {
  const JourneyScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final journey = ref.watch(journeyProvider(slug));

    return journey.when(
      loading: () => Scaffold(appBar: AppBar(), body: const SkeletonTiles(count: 6)),
      error: (e, _) => Scaffold(
        appBar: AppBar(),
        body: ErrorView(error: e, onRetry: () => ref.invalidate(journeyProvider(slug))),
      ),
      data: (j) {
        final p = context.sancta;
        final next = j.steps.where((s) => !s.completed).firstOrNull;
        return Scaffold(
          body: CustomScrollView(
            slivers: [
              SliverAppBar(
                pinned: true,
                actions: [
                  IconButton(tooltip: 'Ajustes de leitura', icon: const Icon(Icons.text_fields_rounded), onPressed: () => showReadingSettings(context)),
                ],
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(24, 0, 24, 0),
                  child: Column(
                    children: [
                      ArchCover(url: j.coverUrl, scene: skyFor(j.slug), width: 180, aspectRatio: .86),
                      const SizedBox(height: 18),
                      Wrap(
                        spacing: 8,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          if (j.typeLabel.isNotEmpty) Text(j.typeLabel, style: context.text.labelLarge?.copyWith(color: p.primary, fontSize: 14)),
                          if (j.isPremium) const PremiumBadge(compact: true),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Semantics(
                        header: true,
                        child: Text(j.title, textAlign: TextAlign.center, style: context.text.headlineMedium),
                      ),
                      if (j.subtitle != null) ...[const SizedBox(height: 4), Text(j.subtitle!, textAlign: TextAlign.center, style: context.text.bodyMedium)],
                    ],
                  ),
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
                  child: Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: p.surface,
                      borderRadius: BorderRadius.circular(SanctaRadius.card),
                      border: p.isDark ? null : Border.all(color: p.outline),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Row(
                          children: [
                            Expanded(child: Text(j.completedSteps == 0 ? 'Comece hoje' : journeyDayLabel(j), style: context.text.titleMedium)),
                            Text('${j.completedSteps} de ${j.stepsCount}', style: context.text.labelMedium),
                          ],
                        ),
                        const SizedBox(height: 10),
                        SanctaProgress(value: j.progress, height: 7),
                        if (next != null) ...[
                          const SizedBox(height: 14),
                          FilledButton.icon(
                            onPressed: () => next.locked ? context.push('/planos') : _openStep(context, j, next),
                            icon: Icon(next.locked ? Icons.lock_outline_rounded : Icons.play_arrow_rounded),
                            label: Text(
                              next.locked ? 'Desbloquear com o Sancta+' : (j.completedSteps == 0 ? 'Começar o dia 1' : 'Continuar: dia ${next.position}'),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
              if (j.description != null)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(22, 20, 22, 0),
                    child: Text(j.description!, style: context.text.bodyLarge),
                  ),
                ),
              const SliverToBoxAdapter(child: SectionHeader('Dias')),
              SliverPadding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                sliver: SliverToBoxAdapter(
                  child: TileGroup(
                    children: [
                      for (final step in j.steps)
                        SanctaTile(
                          card: false,
                          leading: _StepDot(step: step, current: step.id == next?.id),
                          title: step.title,
                          titleColor: step.completed ? p.textSoft : null,
                          subtitle: [?step.bibleReference, if (step.durationMinutes != null) '${step.durationMinutes} min'].join(' · '),
                          trailing: step.locked ? const PremiumBadge(compact: true) : null,
                          onTap: () => step.locked ? context.push('/planos') : _openStep(context, j, step),
                        ),
                    ],
                  ),
                ),
              ),
              SliverToBoxAdapter(child: SizedBox(height: 32 + MediaQuery.paddingOf(context).bottom)),
            ],
          ),
        );
      },
    );
  }

  void _openStep(BuildContext context, Journey journey, JourneyStep step) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (_) => _StepSheet(journey: journey, step: step),
  );
}

class _StepDot extends StatelessWidget {
  const _StepDot({required this.step, required this.current});

  final JourneyStep step;
  final bool current;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return Semantics(
      label: step.completed ? 'Dia ${step.position}, concluído' : 'Dia ${step.position}',
      excludeSemantics: true,
      child: Container(
        width: 40,
        height: 40,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: step.completed ? p.primary : (current ? p.iconBg : p.surfaceAlt),
          border: current ? Border.all(color: p.primary, width: 2) : null,
        ),
        child: step.completed
            ? Icon(Icons.check_rounded, size: 20, color: p.onPrimary)
            : Text('${step.position}', style: context.text.titleSmall?.copyWith(color: current ? p.primary : p.textSoft)),
      ),
    );
  }
}

class _StepSheet extends ConsumerStatefulWidget {
  const _StepSheet({required this.journey, required this.step});

  final Journey journey;
  final JourneyStep step;

  @override
  ConsumerState<_StepSheet> createState() => _StepSheetState();
}

class _StepSheetState extends ConsumerState<_StepSheet> {
  bool _saving = false;

  Future<void> _complete() async {
    if (!requireAccount(context, ref, reason: 'Entre para salvar seu progresso na jornada.')) return;
    setState(() => _saving = true);
    try {
      final result = await ref.read(apiClientProvider).post('/journeys/${widget.journey.slug}/steps/${widget.step.position}/complete');
      ref.invalidate(journeyProvider(widget.journey.slug));
      ref.invalidate(homeProvider);
      await ref.read(sessionProvider.notifier).refreshUser();
      if (!mounted) return;
      final finished = (result as Map)['finished'] == true;
      final navigator = Navigator.of(context);
      final messenger = ScaffoldMessenger.of(context);
      await showAmenCelebration(context, streakDays: ref.read(sessionProvider).user?.currentStreak);
      navigator.pop();
      messenger.showSnackBar(SnackBar(content: Text(finished ? 'Jornada concluída. Deus seja louvado.' : 'Dia concluído. Até amanhã.')));
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.paywall) {
        Navigator.pop(context);
        context.push('/planos');
      } else {
        showMessage(context, e.message);
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _openReference(String reference) {
    // "Jo 3,1-21" → abre a busca pela referência no leitor bíblico.
    Navigator.pop(context);
    context.push('/biblia-busca?q=${Uri.encodeComponent(reference)}');
  }

  @override
  Widget build(BuildContext context) {
    final step = widget.step;
    final scale = ref.watch(appearanceProvider).readingScale;

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: .88,
      maxChildSize: .96,
      builder: (_, controller) => ListView(
        controller: controller,
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 28),
        children: [
          Text('Dia ${step.position} · ${widget.journey.title}', style: context.text.labelMedium),
          const SizedBox(height: 4),
          Text(step.title.replaceFirst(RegExp(r'^Dia \d+ — '), ''), style: context.text.headlineSmall),
          if (step.bibleReference != null) ...[
            const SizedBox(height: 14),
            Align(
              alignment: Alignment.centerLeft,
              child: OutlinedButton.icon(
                onPressed: () => _openReference(step.bibleReference!),
                icon: const Icon(Icons.menu_book_rounded),
                label: Text('Ler ${step.bibleReference}'),
              ),
            ),
          ],
          if (step.audioUrl != null) ...[const SizedBox(height: 14), AudioPlayerBar(url: step.audioUrl!, title: step.title)],
          const SizedBox(height: 20),
          ContentBlocksView(blocks: step.blocks, scale: scale),
          if (step.prayerSlug != null) ...[
            const SizedBox(height: 8),
            SanctaTile(
              icon: Icons.volunteer_activism_outlined,
              title: 'Rezar a oração do dia',
              onTap: () {
                Navigator.pop(context);
                context.push('/oracao/${step.prayerSlug}');
              },
            ),
          ],
          const SizedBox(height: 20),
          FilledButton.icon(
            onPressed: step.completed || _saving ? null : _complete,
            icon: Icon(step.completed ? Icons.check_circle_rounded : Icons.check_rounded),
            label: Text(step.completed ? 'Dia concluído' : 'Amém · Concluir este dia'),
          ),
        ],
      ),
    );
  }
}
