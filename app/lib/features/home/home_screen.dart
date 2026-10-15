import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/brand/brand.dart';
import '../../core/models/models.dart';
import '../../core/motion/live_badge.dart';
import '../../core/motion/skeleton.dart';
import '../../core/state/config_controller.dart';
import '../../core/state/content_providers.dart';
import '../../core/state/session_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/clock.dart';
import '../../core/utils/links.dart';
import '../../core/widgets/common.dart';
import '../announcements/announcement_service.dart';
import '../journeys/journey_card.dart';
import '../news/news_tile.dart';
import '../prayers/prayer_card.dart';
import '../widget/home_widget_sync.dart';

/// Aba **Hoje**: saudação, oração do período em arco, "Para hoje" e descobertas.
class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  @override
  void initState() {
    super.initState();
    // Widget "Oração do dia": atualizado sempre que os dados de /home chegam (sem travar a tela).
    ref.listenManual<AsyncValue<Map<String, dynamic>>>(homeProvider, (_, next) {
      final data = next.valueOrNull;
      if (data != null) unawaited(HomeWidgetSync.update(data).catchError((Object _) {}));
    }, fireImmediately: true);
  }

  Future<void> _refresh() async {
    ref.invalidate(homeProvider);
    ref.invalidate(bannersProvider);
    await ref.read(sessionProvider.notifier).refreshUser();
    await ref.read(homeProvider.future).catchError((Object _) => <String, dynamic>{});
  }

  @override
  Widget build(BuildContext context) {
    final home = ref.watch(homeProvider);

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: home.when(
          skipLoadingOnRefresh: true,
          loading: () => const _HomeSkeleton(),
          error: (e, _) => ListView(
            children: [
              const _HomeHeader(),
              const SizedBox(height: 40),
              ErrorView(error: e, onRetry: () => ref.invalidate(homeProvider)),
            ],
          ),
          data: (data) => _HomeContent(data: data),
        ),
      ),
    );
  }
}

// ───────────────────────── Cabeçalho ─────────────────────────

class _HomeHeader extends ConsumerWidget {
  const _HomeHeader();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.sancta;
    final session = ref.watch(sessionProvider);
    final now = ref.watch(clockProvider)();
    final user = session.user;
    final firstName = user?.firstName.trim().split(RegExp(r'\s+')).first ?? '';
    final greeting = firstName.isEmpty ? PrayerPeriod.of(now).greeting : '${PrayerPeriod.of(now).greeting}, $firstName';
    final date = DateFormat("EEEE, d 'de' MMMM", 'pt_BR').format(now);

    return SafeArea(
      bottom: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 10, 12, 0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                SanctaWordmark(variant: p.isDark ? WordmarkVariant.pergaminho : WordmarkVariant.azul, height: 24),
                const Spacer(),
                if (user != null && user.currentStreak > 0) ...[
                  StreakPill(days: user.currentStreak, onTap: () => context.go('/voce')),
                  const SizedBox(width: 6),
                ],
                _AvatarButton(initial: firstName.isEmpty ? null : firstName.characters.first.toUpperCase()),
              ],
            ),
            const SizedBox(height: 18),
            Semantics(header: true, child: Text(greeting, style: context.text.headlineMedium)),
            const SizedBox(height: 2),
            Text('${date[0].toUpperCase()}${date.substring(1)}', style: context.text.bodyMedium),
          ],
        ),
      ),
    );
  }
}

class _AvatarButton extends StatelessWidget {
  const _AvatarButton({this.initial});

  final String? initial;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return Semantics(
      button: true,
      label: 'Você: perfil e ajustes',
      excludeSemantics: true,
      child: InkResponse(
        onTap: () => context.go('/voce'),
        radius: 26,
        child: SizedBox(
          width: 48,
          height: 48,
          child: Center(
            child: CircleAvatar(
              radius: 18,
              backgroundColor: p.iconBg,
              child: initial == null
                  ? Icon(Icons.person_rounded, size: 20, color: p.primary)
                  : Text(
                      initial!,
                      style: context.text.titleSmall?.copyWith(color: p.primary, fontWeight: FontWeight.w700),
                    ),
            ),
          ),
        ),
      ),
    );
  }
}

// ───────────────────────── Conteúdo ─────────────────────────

class _HomeContent extends ConsumerWidget {
  const _HomeContent({required this.data});

  final Map<String, dynamic> data;

  List<Map<String, dynamic>> _list(String key) => (data[key] as List? ?? const []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();

  Map<String, dynamic>? _map(String key) => data[key] is Map ? Map<String, dynamic>.from(data[key] as Map) : null;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);
    final config = ref.watch(configValueProvider);
    final now = ref.watch(clockProvider)();
    final verse = _map('daily_verse');
    final liturgy = _map('liturgy');
    final saint = _map('saint');
    final continueJourney = _map('continue_journey') == null ? null : Journey.fromJson(_map('continue_journey')!);
    final journeys = _list('featured_journeys').map(Journey.fromJson).toList();
    final prayers = _list('featured_prayers').map(PrayerItem.fromJson).toList();
    final news = _list('news').map(NewsItem.fromJson).toList();
    final events = _list('events');
    final liveNow = config.feature('live') ? LiveNow.listFrom(data['live_now']) : const <LiveNow>[];
    final gospel = liturgy?['gospel_reference'] as String?;

    final today = <Widget>[
      if (liveNow.isNotEmpty) _LiveNowTile(items: liveNow),
      if (liturgy != null)
        SanctaTile(
          icon: Icons.menu_book_outlined,
          title: 'Evangelho do dia',
          subtitle: gospel ?? '${liturgy['title'] ?? 'Liturgia diária'}',
          onTap: () => context.push('/liturgia'),
        ),
      if (saint != null) SanctaTile(icon: Icons.today_rounded, title: 'Santo do dia', subtitle: '${saint['name']}', onTap: () => _showSaint(context, saint)),
      if (continueJourney != null)
        SanctaTile(
          icon: Icons.route_rounded,
          title: 'Continue sua jornada',
          subtitle: '${journeyDayLabel(continueJourney)} · ${continueJourney.title}',
          onTap: () => context.push('/jornada/${continueJourney.slug}'),
        ),
      if (verse != null)
        SanctaTile(
          icon: Icons.format_quote_rounded,
          title: 'Versículo do dia',
          subtitle: '${verse['reference']} · ${verse['text']}',
          subtitleMaxLines: 1,
          onTap: () => _showVerse(context, verse),
        ),
    ];

    return CustomScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      slivers: [
        const SliverToBoxAdapter(child: _HomeHeader()),
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 0),
            child: _PeriodPrayerCard(prayers: prayers, server: PeriodPrayers.fromJson(data['period_prayers']), gospel: gospel, now: now),
          ),
        ),

        if (today.isNotEmpty) ...[
          const SliverToBoxAdapter(child: SectionHeader('Para hoje')),
          SliverPadding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            sliver: SliverList.separated(itemCount: today.length, separatorBuilder: (_, _) => const SizedBox(height: 8), itemBuilder: (_, i) => today[i]),
          ),
        ],

        // Banner do painel (avisos do tipo "banner")
        SliverToBoxAdapter(
          child: Consumer(
            builder: (context, ref, _) {
              final banner = (ref.watch(bannersProvider).valueOrNull ?? const <Announcement>[]).firstOrNull;
              if (banner == null) return const SizedBox.shrink();
              return Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                child: SanctaTile(
                  leading: banner.imageUrl != null
                      ? ClipRRect(borderRadius: BorderRadius.circular(SanctaRadius.icon), child: NetImage(banner.imageUrl, width: 56, height: 56))
                      : const IconBadge(Icons.campaign_outlined),
                  title: banner.title,
                  subtitle: banner.ctaLabel ?? banner.body,
                  onTap: banner.ctaUrl == null ? null : () => openLink(context, banner.ctaUrl),
                ),
              );
            },
          ),
        ),

        if (prayers.isNotEmpty) ...[
          SliverToBoxAdapter(
            child: SectionHeader('Para rezar', action: 'Ver tudo', onAction: () => context.go('/orar')),
          ),
          SliverToBoxAdapter(
            child: _Carousel(
              height: 236,
              itemCount: prayers.length,
              itemBuilder: (_, i) => PrayerCard(prayer: prayers[i]),
            ),
          ),
        ],

        if (journeys.isNotEmpty) ...[
          SliverToBoxAdapter(
            child: SectionHeader('Jornadas e novenas', action: 'Ver todas', onAction: () => context.go('/orar')),
          ),
          SliverToBoxAdapter(
            child: _Carousel(
              height: 262,
              itemCount: journeys.length,
              itemBuilder: (_, i) => JourneyCard(journey: journeys[i]),
            ),
          ),
        ],

        const SliverToBoxAdapter(child: SectionHeader('Explorar')),
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _Shortcut(icon: Icons.auto_stories_outlined, label: 'Liturgia', onTap: () => context.push('/liturgia')),
                if (config.feature('live')) _Shortcut(icon: Icons.live_tv_rounded, label: 'Ao vivo', onTap: () => context.push('/ao-vivo')),
                if (config.feature('intentions'))
                  _Shortcut(icon: Icons.volunteer_activism_outlined, label: 'Intenções', onTap: () => context.push('/intencoes')),
                if (config.feature('news')) _Shortcut(icon: Icons.newspaper_rounded, label: 'Notícias', onTap: () => context.push('/noticias')),
              ],
            ),
          ),
        ),

        if (events.isNotEmpty) ...[
          const SliverToBoxAdapter(child: SectionHeader('Próximos eventos')),
          SliverPadding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            sliver: SliverList.separated(
              itemCount: events.length,
              separatorBuilder: (_, _) => const SizedBox(height: 8),
              itemBuilder: (_, i) => _EventTile(event: events[i]),
            ),
          ),
        ],

        if (news.isNotEmpty) ...[
          SliverToBoxAdapter(
            child: SectionHeader('Notícias', action: 'Ver todas', onAction: () => context.push('/noticias')),
          ),
          SliverPadding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            sliver: SliverList.separated(
              itemCount: news.length.clamp(0, 3),
              separatorBuilder: (_, _) => const SizedBox(height: 8),
              itemBuilder: (_, i) => NewsTile(item: news[i]),
            ),
          ),
        ],

        if (!session.isPremium)
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 28, 16, 0),
              child: _PremiumBanner(content: config.paywall),
            ),
          ),

        const SliverToBoxAdapter(child: SizedBox(height: 32)),
      ],
    );
  }

  void _showSaint(BuildContext context, Map<String, dynamic> saint) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (sheet) => DraggableScrollableSheet(
      expand: false,
      initialChildSize: .6,
      maxChildSize: .92,
      builder: (_, controller) => ListView(
        controller: controller,
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
        children: [
          if (saint['image_url'] != null) ...[
            Center(child: ArchCover(url: saint['image_url'] as String?, width: 150, aspectRatio: .78)),
            const SizedBox(height: 18),
          ],
          Text('Santo do dia', style: sheet.text.labelMedium),
          const SizedBox(height: 4),
          Text('${saint['name']}', style: sheet.text.headlineSmall),
          if (saint['title'] != null) ...[
            const SizedBox(height: 2),
            Text('${saint['title']}', style: sheet.text.titleSmall?.copyWith(color: sheet.sancta.primary)),
          ],
          if (saint['biography'] != null) ...[const SizedBox(height: 14), Text('${saint['biography']}', style: AppTheme.reading(sheet, size: 19))],
        ],
      ),
    ),
  );

  void _showVerse(BuildContext context, Map<String, dynamic> verse) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (sheet) => _VerseSheet(verse: verse),
  );
}

/// Cartão herói: a oração do período dentro do arco, com o céu da hora (como no mockup).
class _PeriodPrayerCard extends StatelessWidget {
  const _PeriodPrayerCard({required this.prayers, required this.server, required this.gospel, required this.now});

  final List<PrayerItem> prayers;

  /// Escolha do servidor (`period_prayers`); sem ela, a heurística local sobre os destaques.
  final PeriodPrayers server;
  final String? gospel;
  final DateTime now;

  static const _keywords = {
    PrayerPeriod.manha: ['manha', 'manhã', 'laudes', 'matinal', 'despertar'],
    PrayerPeriod.tarde: ['tarde', 'angelus', 'ângelus', 'misericordia', 'misericórdia', 'meio-dia'],
    PrayerPeriod.noite: ['noite', 'completas', 'exame', 'dormir', 'vésperas', 'vesperas'],
  };

  /// Escolhe, entre as orações em destaque, a que combina com o período; senão a primeira liberada.
  static PrayerItem? pick(List<PrayerItem> prayers, PrayerPeriod period) {
    String norm(PrayerItem p) => '${p.title} ${p.slug} ${p.categoryName ?? ''}'.toLowerCase();
    return prayers.where((p) => _keywords[period]!.any(norm(p).contains)).firstOrNull ?? prayers.where((p) => !p.locked).firstOrNull ?? prayers.firstOrNull;
  }

  /// Oração do período: a do servidor (painel) e, sem ela (servidor antigo), a heurística local.
  static PrayerItem? choose(PeriodPrayers server, List<PrayerItem> prayers, PrayerPeriod period) =>
      switch (period) {
        PrayerPeriod.manha => server.morning,
        PrayerPeriod.tarde => server.afternoon,
        PrayerPeriod.noite => server.night,
      } ??
      pick(prayers, period);

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final period = PrayerPeriod.of(now);
    final prayer = choose(server, prayers, period);
    final title = period.prayerTitle;
    final sameTitle = prayer != null && prayer.title.toLowerCase().trim() == title.toLowerCase();
    final subtitle = prayer == null
        ? 'Leituras e Evangelho do dia'
        : sameTitle
        ? [if (prayer.durationMinutes != null) '${prayer.durationMinutes} min', if (gospel != null) 'com o Evangelho do dia'].join(', ')
        : [prayer.title, if (prayer.durationMinutes != null) '${prayer.durationMinutes} min'].join(' · ');
    void open() => prayer == null ? context.push('/liturgia') : context.push('/oracao/${prayer.slug}');

    return Material(
      color: p.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(26),
        side: p.isDark ? BorderSide.none : BorderSide(color: p.outline),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: open,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 12, 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.only(right: 4),
                child: ArchFrame(aspectRatio: 1.5, child: prayer?.coverUrl != null ? NetImage(prayer!.coverUrl) : SkyArt.forTime(now)),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  const SizedBox(width: 2),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(title, style: context.text.titleLarge),
                        if (subtitle.isNotEmpty) ...[
                          const SizedBox(height: 2),
                          Text(subtitle, maxLines: 2, overflow: TextOverflow.ellipsis, style: context.text.bodySmall),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  PlayButton(onPressed: open, size: 50, semanticLabel: 'Rezar agora: $title'),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// "Ao vivo agora" (de `/home` → `live_now`).
class _LiveNowTile extends StatelessWidget {
  const _LiveNowTile({required this.items});

  final List<LiveNow> items;

  @override
  Widget build(BuildContext context) {
    final first = items.first;
    final others = items.length - 1;
    final subtitle = [first.name, if (first.title != null && first.title != first.name) first.title!].join(' · ');
    return SanctaTile(
      icon: Icons.live_tv_rounded,
      title: first.isLive ? 'Ao vivo agora' : 'No horário da transmissão',
      subtitle: others > 0 ? '$subtitle e mais $others' : subtitle,
      trailing: first.isLive ? const LiveBadge(compact: true) : const _ScheduleChip(),
      onTap: () => items.length == 1 && first.watchUrl != null ? openLink(context, first.watchUrl) : context.push('/ao-vivo'),
    );
  }
}

class _ScheduleChip extends StatelessWidget {
  const _ScheduleChip();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
    decoration: BoxDecoration(color: context.sancta.iconBg, borderRadius: BorderRadius.circular(6)),
    child: Text('AGORA', style: context.text.labelSmall?.copyWith(color: context.sancta.primary, fontSize: 10.5, letterSpacing: .6)),
  );
}

class _Carousel extends StatelessWidget {
  const _Carousel({required this.height, required this.itemCount, required this.itemBuilder});

  final double height;
  final int itemCount;
  final IndexedWidgetBuilder itemBuilder;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: carouselHeight(context, height),
    child: ListView.separated(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      scrollDirection: Axis.horizontal,
      itemCount: itemCount,
      separatorBuilder: (_, _) => const SizedBox(width: 14),
      itemBuilder: itemBuilder,
    ),
  );
}

class _Shortcut extends StatelessWidget {
  const _Shortcut({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Semantics(
      button: true,
      label: label,
      excludeSemantics: true,
      child: InkWell(
        borderRadius: BorderRadius.circular(SanctaRadius.tile),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 2),
          child: Column(
            children: [
              IconBadge(icon, size: 54),
              const SizedBox(height: 8),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: context.text.labelMedium?.copyWith(color: context.sancta.text),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _EventTile extends StatelessWidget {
  const _EventTile({required this.event});

  final Map<String, dynamic> event;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final date = DateTime.tryParse('${event['starts_at']}')?.toLocal();
    return SanctaTile(
      leading: IconBadge(
        null,
        size: 48,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              date != null ? DateFormat('dd').format(date) : '--',
              style: context.text.titleMedium?.copyWith(color: p.primary, height: 1.05, fontWeight: FontWeight.w700),
            ),
            Text(
              date != null ? DateFormat('MMM', 'pt_BR').format(date).replaceAll('.', '').toUpperCase() : '',
              style: context.text.labelSmall?.copyWith(color: p.primary, fontSize: 10),
            ),
          ],
        ),
      ),
      title: '${event['title']}',
      subtitle: [if (date != null) DateFormat("HH'h'mm").format(date), event['location']].whereType<String>().join(' · '),
      trailing: event['is_premium'] == true ? const PremiumBadge(compact: true) : null,
    );
  }
}

/// Entrada discreta do Sancta+ para quem não assina.
class _PremiumBanner extends StatelessWidget {
  const _PremiumBanner({required this.content});

  final PaywallContent content;

  @override
  Widget build(BuildContext context) => SanctaTile(
    onTap: () => context.push('/planos'),
    padding: const EdgeInsets.fromLTRB(14, 14, 10, 14),
    leading: const ArchCover(scene: SkyScene.noite, width: 48, aspectRatio: .8, bottomRadius: 8),
    title: content.headline,
    subtitle: content.subheadline ?? 'Orações guiadas, jornadas e reflexões com o Sancta+.',
    trailing: const PremiumBadge(compact: true),
  );
}

// ───────────────────────── Folhas ─────────────────────────

class _VerseSheet extends StatelessWidget {
  const _VerseSheet({required this.verse});

  final Map<String, dynamic> verse;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final locked = verse['reflection_locked'] == true;
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: .62,
      maxChildSize: .94,
      builder: (_, controller) => ListView(
        controller: controller,
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
        children: [
          Text('Versículo do dia', style: context.text.labelMedium),
          const SizedBox(height: 10),
          Text('“${verse['text']}”', style: AppTheme.reading(context, size: 24).copyWith(height: 1.4)),
          const SizedBox(height: 10),
          Text('${verse['reference']}', style: context.text.titleSmall?.copyWith(color: p.primary)),
          const SizedBox(height: 18),
          Row(
            children: [
              OutlinedButton.icon(
                onPressed: () => shareText('“${verse['text']}” (${verse['reference']})\n\nRezado com o Sancta'),
                icon: const Icon(Icons.ios_share_rounded, size: 18),
                label: const Text('Compartilhar'),
              ),
            ],
          ),
          if (verse['reflection_title'] != null) ...[
            const SizedBox(height: 26),
            Row(
              children: [
                Expanded(child: Text('Reflexão: ${verse['reflection_title']}', style: context.text.titleMedium)),
                if (locked) const PremiumBadge(compact: true),
              ],
            ),
            const SizedBox(height: 10),
            if (locked)
              _LockedNote(
                text: 'A reflexão diária do Evangelho é parte do Sancta+.',
                onTap: () {
                  Navigator.pop(context);
                  context.push('/planos');
                },
              )
            else
              Text('${verse['reflection'] ?? ''}', style: AppTheme.reading(context, size: 19)),
          ],
        ],
      ),
    );
  }
}

class _LockedNote extends StatelessWidget {
  const _LockedNote({required this.text, required this.onTap});

  final String text;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(color: context.sancta.surfaceAlt, borderRadius: BorderRadius.circular(SanctaRadius.tile)),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(text, style: context.text.bodyMedium),
        const SizedBox(height: 12),
        FilledButton(onPressed: onTap, child: const Text('Conhecer o Sancta+')),
      ],
    ),
  );
}

// ───────────────────────── Esqueleto ─────────────────────────

class _HomeSkeleton extends StatelessWidget {
  const _HomeSkeleton();

  @override
  Widget build(BuildContext context) => ListView(
    physics: const AlwaysScrollableScrollPhysics(),
    children: [
      const _HomeHeader(),
      Padding(
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 0),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: context.sancta.surface, borderRadius: BorderRadius.circular(26)),
          child: const Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AspectRatio(
                aspectRatio: 1.5,
                child: ClipPath(
                  clipper: ArchClipper(),
                  child: SanctaSkeleton(height: double.infinity, radius: BorderRadius.zero),
                ),
              ),
              SizedBox(height: 16),
              SanctaSkeleton(width: 180, height: 18),
              SizedBox(height: 8),
              SanctaSkeleton(width: 120, height: 13),
            ],
          ),
        ),
      ),
      const SizedBox(height: 18),
      const SkeletonTiles(count: 3, shrinkWrap: true, padding: EdgeInsets.symmetric(horizontal: 16)),
    ],
  );
}
