import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/router.dart';
import '../../core/brand/editorial_art.dart';
import '../../core/models/models.dart';
import '../../core/motion/amen_celebration.dart';
import '../../core/motion/skeleton.dart';
import '../../core/providers.dart';
import '../../core/state/content_providers.dart';
import '../../core/state/session_controller.dart';
import '../../core/state/theme_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/links.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/content_blocks.dart';
import '../../core/widgets/reading_settings.dart';
import 'audio_player_bar.dart';
import 'prayer_card.dart';

/// Leitura/escuta de uma oração. Conteúdo Sancta+ chega bloqueado da API
/// (apenas um trecho) para quem não é assinante.
class PrayerScreen extends ConsumerStatefulWidget {
  const PrayerScreen({super.key, required this.slug});

  final String slug;

  @override
  ConsumerState<PrayerScreen> createState() => _PrayerScreenState();
}

class _PrayerScreenState extends ConsumerState<PrayerScreen> {
  final _opened = DateTime.now();
  bool? _favorite;
  bool _finished = false;

  Future<void> _finish(PrayerItem prayer) async {
    final seconds = DateTime.now().difference(_opened).inSeconds;
    setState(() => _finished = true);
    // O registro segue em segundo plano; a celebração espera no máximo 0,7 s por ele.
    // Se o servidor responder a tempo, mostra a sequência atualizada; senão, celebra sem
    // a sequência (nunca um número desatualizado).
    var recorded = false;
    final record = recordPrayerSession(ref, type: 'prayer', contentId: prayer.id, seconds: seconds < 30 ? (prayer.durationMinutes ?? 1) * 60 : seconds).then((
      _,
    ) {
      recorded = true;
      if (mounted) ref.invalidate(statsProvider);
    });
    try {
      await record.timeout(const Duration(milliseconds: 700));
    } catch (_) {
      // lento ou sem rede: a celebração acontece mesmo assim
    }
    if (!mounted) return;
    final session = ref.read(sessionProvider);
    await showAmenCelebration(context, streakDays: recorded ? session.user?.currentStreak : null);
    if (mounted && !session.isAuthenticated) showMessage(context, 'Amém. Entre na sua conta para guardar sua sequência de oração.');
  }

  Future<void> _toggleFavorite(PrayerItem prayer) async {
    if (!requireAccount(context, ref, reason: 'Entre para salvar suas orações favoritas.')) return;
    try {
      final result = await ref.read(apiClientProvider).post('/prayers/${prayer.slug}/favorite');
      setState(() => _favorite = (result as Map)['is_favorite'] == true);
      ref.invalidate(favoritePrayersProvider);
    } catch (e) {
      if (mounted) showMessage(context, errorText(e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final prayer = ref.watch(prayerProvider(widget.slug));
    final scale = ref.watch(appearanceProvider).readingScale;

    return prayer.when(
      loading: () => Scaffold(appBar: AppBar(), body: const _PrayerSkeleton()),
      error: (e, _) => Scaffold(
        appBar: AppBar(),
        body: ErrorView(error: e, onRetry: () => ref.invalidate(prayerProvider(widget.slug))),
      ),
      data: (p) {
        final fav = _favorite ?? p.isFavorite;
        final palette = context.sancta;
        final meta = prayerMeta(p, category: false);
        return Scaffold(
          body: CustomScrollView(
            slivers: [
              SliverAppBar(
                pinned: true,
                actions: [
                  IconButton(tooltip: 'Ajustes de leitura', icon: const Icon(Icons.text_fields_rounded), onPressed: () => showReadingSettings(context)),
                  IconButton(
                    tooltip: fav ? 'Remover dos favoritos' : 'Favoritar',
                    icon: Icon(fav ? Icons.favorite_rounded : Icons.favorite_border_rounded, color: fav ? palette.primary : null),
                    onPressed: () => _toggleFavorite(p),
                  ),
                  IconButton(tooltip: 'Compartilhar', icon: const Icon(Icons.ios_share_rounded), onPressed: () => shareText('${p.title} — rezei com o Sancta')),
                  const SizedBox(width: 4),
                ],
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(24, 0, 24, 0),
                  child: Column(
                    children: [
                      ArchCover(
                        url: p.coverUrl,
                        assetPath: EditorialArt.forPrayer(p.title, p.slug),
                        scene: skyFor(p.categoryName ?? p.slug),
                        width: 200,
                        aspectRatio: .86,
                      ),
                      const SizedBox(height: 20),
                      if (p.categoryName != null) Text(p.categoryName!, style: context.text.labelLarge?.copyWith(color: palette.primary, fontSize: 14)),
                      const SizedBox(height: 4),
                      Semantics(
                        header: true,
                        child: Text(p.title, textAlign: TextAlign.center, style: context.text.headlineMedium),
                      ),
                      if (p.subtitle != null) ...[const SizedBox(height: 4), Text(p.subtitle!, textAlign: TextAlign.center, style: context.text.bodyMedium)],
                      const SizedBox(height: 10),
                      Wrap(
                        alignment: WrapAlignment.center,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        spacing: 10,
                        runSpacing: 6,
                        children: [
                          if (meta.isNotEmpty) Text(meta, style: context.text.bodySmall),
                          if (p.isPremium) const PremiumBadge(),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              if (p.audioUrl != null)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 22, 16, 0),
                    child: AudioPlayerBar(url: p.audioUrl!, title: p.title),
                  ),
                ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(24, 26, 24, 0),
                sliver: SliverToBoxAdapter(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      if (p.description != null) ...[
                        Text(
                          p.description!,
                          style: context.text.bodyLarge?.copyWith(color: palette.textSoft, fontStyle: FontStyle.italic),
                        ),
                        const SizedBox(height: 18),
                      ],
                      if (p.locked)
                        _LockedPreview(blocks: p.blocks, scale: scale, onUnlock: () => context.push('/planos'))
                      else ...[
                        ContentBlocksView(blocks: p.blocks, scale: scale),
                        const SizedBox(height: 20),
                        FilledButton.icon(
                          onPressed: _finished ? null : () => _finish(p),
                          icon: Icon(_finished ? Icons.check_circle_rounded : Icons.check_rounded),
                          label: Text(_finished ? 'Oração concluída' : 'Amém · Concluir oração'),
                        ),
                        if (p.author != null) ...[
                          const SizedBox(height: 14),
                          Text('Fonte: ${p.author}', textAlign: TextAlign.center, style: context.text.bodySmall),
                        ],
                      ],
                      SizedBox(height: 40 + MediaQuery.paddingOf(context).bottom),
                    ],
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Prévia do conteúdo bloqueado (o servidor envia só um trecho) com o convite ao Sancta+.
class _LockedPreview extends StatelessWidget {
  const _LockedPreview({required this.blocks, required this.scale, required this.onUnlock});

  final List<ContentBlock> blocks;
  final double scale;
  final VoidCallback onUnlock;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (blocks.isNotEmpty)
          ShaderMask(
            blendMode: BlendMode.dstIn,
            shaderCallback: (rect) => const LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Colors.black, Colors.transparent],
              stops: [.35, 1],
            ).createShader(rect),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 260),
              child: ClipRect(
                child: SingleChildScrollView(
                  physics: const NeverScrollableScrollPhysics(),
                  child: ContentBlocksView(blocks: blocks, scale: scale),
                ),
              ),
            ),
          ),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.fromLTRB(20, 22, 20, 20),
          decoration: BoxDecoration(
            color: p.surface,
            borderRadius: BorderRadius.circular(SanctaRadius.card),
            border: p.isDark ? null : Border.all(color: p.outline),
          ),
          child: Column(
            children: [
              const PremiumBadge(),
              const SizedBox(height: 12),
              Text('Continue com o Sancta+', style: context.text.titleLarge, textAlign: TextAlign.center),
              const SizedBox(height: 6),
              Text('Esta oração completa, em texto e áudio, faz parte do Sancta+.', textAlign: TextAlign.center, style: context.text.bodyMedium),
              const SizedBox(height: 18),
              SizedBox(
                width: double.infinity,
                child: FilledButton(onPressed: onUnlock, child: const Text('Conhecer o Sancta+')),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _PrayerSkeleton extends StatelessWidget {
  const _PrayerSkeleton();

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
    children: const [
      Center(
        child: SizedBox(
          width: 200,
          height: 232,
          child: SanctaSkeleton(
            radius: BorderRadius.vertical(top: Radius.circular(100), bottom: Radius.circular(14)),
          ),
        ),
      ),
      SizedBox(height: 24),
      Center(child: SanctaSkeleton(width: 220, height: 24)),
      SizedBox(height: 10),
      Center(child: SanctaSkeleton(width: 120, height: 14)),
      SizedBox(height: 36),
      SanctaSkeleton(height: 16),
      SizedBox(height: 10),
      SanctaSkeleton(height: 16),
      SizedBox(height: 10),
      SanctaSkeleton(width: 240, height: 16),
    ],
  );
}
