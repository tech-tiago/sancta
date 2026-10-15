import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/brand/brand.dart';
import '../../core/models/models.dart';
import '../../core/state/config_controller.dart';
import '../../core/state/content_providers.dart';
import '../../core/state/session_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/clock.dart';
import '../../core/utils/links.dart';
import '../../core/widgets/common.dart';

/// Aba **Você**: perfil, sequência de oração, Sancta+, favoritos, ajustes e ajuda.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);
    final config = ref.watch(configValueProvider);
    final user = session.user;
    final p = context.sancta;

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () async {
          await ref.read(sessionProvider.notifier).refreshUser();
          ref.invalidate(statsProvider);
        },
        child: ListView(
          padding: const EdgeInsets.only(bottom: 32),
          children: [
            ScreenTitle(
              'Você',
              actions: [IconButton(tooltip: 'Ajustes', icon: const Icon(Icons.settings_outlined), onPressed: () => context.push('/ajustes'))],
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (user == null) const _GuestCard() else ...[_UserHeader(user: user), const SizedBox(height: 16), const _StatsCard()],
                  const SizedBox(height: 12),
                  if (user?.isPremium == true) _SubscriberCard(user: user!, premiumName: config.premiumName) else _PremiumCard(content: config.paywall),

                  const GroupLabel('Sua vida de fé'),
                  TileGroup(
                    children: [
                      if (user != null) ...[
                        SanctaTile(
                          card: false,
                          icon: Icons.favorite_border_rounded,
                          title: 'Favoritos',
                          subtitle: 'Orações e igrejas',
                          onTap: () => context.push('/favoritos'),
                        ),
                        SanctaTile(
                          card: false,
                          icon: Icons.bookmarks_outlined,
                          title: 'Minhas anotações',
                          subtitle: 'Destaques, notas e marcadores',
                          onTap: () => context.push('/anotacoes'),
                        ),
                      ],
                      if (config.feature('intentions'))
                        SanctaTile(
                          card: false,
                          icon: Icons.volunteer_activism_outlined,
                          title: 'Intenções',
                          subtitle: 'Peça e ofereça orações',
                          onTap: () => context.push('/intencoes'),
                        ),
                      if (config.feature('live'))
                        SanctaTile(
                          card: false,
                          icon: Icons.live_tv_rounded,
                          title: 'Ao vivo',
                          subtitle: 'Missas e terços transmitidos',
                          onTap: () => context.push('/ao-vivo'),
                        ),
                      if (config.feature('news'))
                        SanctaTile(card: false, icon: Icons.newspaper_rounded, title: 'Notícias', onTap: () => context.push('/noticias')),
                    ],
                  ),

                  const GroupLabel('Sancta'),
                  TileGroup(
                    children: [
                      SanctaTile(
                        card: false,
                        icon: Icons.tune_rounded,
                        title: 'Ajustes',
                        subtitle: 'Tema, leitura, lembretes e conta',
                        onTap: () => context.push('/ajustes'),
                      ),
                      SanctaTile(
                        card: false,
                        icon: Icons.help_outline_rounded,
                        title: 'Ajuda',
                        subtitle: 'Perguntas frequentes e contato',
                        onTap: () => context.push('/ajuda'),
                      ),
                      SanctaTile(card: false, icon: Icons.info_outline_rounded, title: 'Sobre o Sancta', onTap: () => context.push('/pagina/sobre')),
                      if (config.playStoreUrl.isNotEmpty)
                        SanctaTile(
                          card: false,
                          icon: Icons.star_outline_rounded,
                          title: 'Avaliar na Play Store',
                          onTap: () => openLink(context, config.playStoreUrl),
                        ),
                      SanctaTile(
                        card: false,
                        icon: Icons.share_outlined,
                        title: 'Indicar para um amigo',
                        onTap: () => shareText('Estou rezando com o Sancta: Bíblia, liturgia, orações e igrejas perto de você. ${config.playStoreUrl}'.trim()),
                      ),
                    ],
                  ),
                  if (user != null) ...[
                    const SizedBox(height: 20),
                    OutlinedButton.icon(onPressed: () => _logout(context, ref), icon: const Icon(Icons.logout_rounded), label: const Text('Sair da conta')),
                  ],
                  const SizedBox(height: 32),
                  Center(child: SanctaWordmark(variant: p.isDark ? WordmarkVariant.pergaminho : WordmarkVariant.azul, height: 18)),
                  const SizedBox(height: 6),
                  Text(config.slogan, textAlign: TextAlign.center, style: context.text.bodySmall),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _logout(BuildContext context, WidgetRef ref) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (d) => AlertDialog(
        title: const Text('Sair da conta?'),
        content: const Text('Você pode entrar novamente quando quiser.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(d, true), child: const Text('Sair')),
        ],
      ),
    );
    if (ok == true) await ref.read(sessionProvider.notifier).logout();
  }
}

class _UserHeader extends StatelessWidget {
  const _UserHeader({required this.user});

  final AppUser user;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final initial = user.firstName.trim().isEmpty ? '?' : user.firstName.trim().characters.first.toUpperCase();
    return Row(
      children: [
        CircleAvatar(
          radius: 32,
          backgroundColor: p.iconBg,
          backgroundImage: user.avatarUrl != null ? CachedNetworkImageProvider(user.avatarUrl!) : null,
          child: user.avatarUrl == null ? Text(initial, style: context.text.headlineSmall?.copyWith(color: p.primary)) : null,
        ),
        const SizedBox(width: 16),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(user.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: context.text.titleLarge),
              Text(user.email, maxLines: 1, overflow: TextOverflow.ellipsis, style: context.text.bodySmall),
              const SizedBox(height: 6),
              if (user.isPremium) const PremiumBadge() else Text('Plano gratuito', style: context.text.labelMedium),
            ],
          ),
        ),
      ],
    );
  }
}

class _GuestCard extends StatelessWidget {
  const _GuestCard();

  @override
  Widget build(BuildContext context) => HeroCard(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const PortalMark.pergaminho(height: 40, semanticLabel: null),
        const SizedBox(height: 14),
        Text('Crie sua conta grátis', style: context.text.titleLarge?.copyWith(color: BrandColors.pergaminho)),
        const SizedBox(height: 4),
        Text(
          'Guarde sua sequência de oração, favoritos, notas na Bíblia e o progresso nas jornadas.',
          style: context.text.bodyMedium?.copyWith(color: BrandColors.nevoa),
        ),
        const SizedBox(height: 16),
        Row(
          children: [
            Expanded(
              child: FilledButton(
                onPressed: () => context.push('/cadastrar'),
                style: FilledButton.styleFrom(backgroundColor: BrandColors.pergaminho, foregroundColor: BrandColors.azulManto),
                child: const Text('Criar conta'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: OutlinedButton(
                onPressed: () => context.push('/entrar'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: BrandColors.pergaminho,
                  side: const BorderSide(color: Color(0x80FBF6EC)),
                ),
                child: const Text('Entrar'),
              ),
            ),
          ],
        ),
      ],
    ),
  );
}

class _SubscriberCard extends StatelessWidget {
  const _SubscriberCard({required this.user, required this.premiumName});

  final AppUser user;
  final String premiumName;

  @override
  Widget build(BuildContext context) {
    final sub = user.subscription;
    final ends = sub?.endsAt;
    final subtitle = ends == null ? 'Acesso vitalício' : '${sub!.autoRenew ? 'Renova em' : 'Válido até'} ${DateFormat('dd/MM/yyyy').format(ends.toLocal())}';
    return SanctaTile(
      leading: const IconBadge(Icons.auto_awesome_rounded),
      title: [premiumName, if (sub?.plan != null) sub!.plan!].join(' · '),
      subtitle: subtitle,
      trailing: sub?.manageUrl != null
          ? TextButton(onPressed: () => openLink(context, sub!.manageUrl), child: const Text('Gerenciar'))
          : const PremiumBadge(compact: true),
    );
  }
}

class _PremiumCard extends StatelessWidget {
  const _PremiumCard({required this.content});

  final PaywallContent content;

  @override
  Widget build(BuildContext context) => HeroCard(
    onTap: () => context.push('/planos'),
    padding: const EdgeInsets.fromLTRB(16, 16, 14, 16),
    child: Row(
      children: [
        const ArchCover(scene: SkyScene.noite, width: 56, aspectRatio: .8, bottomRadius: 8),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const PremiumBadge(),
              const SizedBox(height: 8),
              Text(content.headline, style: context.text.titleMedium?.copyWith(color: BrandColors.pergaminho)),
              const SizedBox(height: 2),
              Text('Conheça os planos', style: context.text.bodySmall?.copyWith(color: BrandColors.nevoa)),
            ],
          ),
        ),
        const Icon(Icons.chevron_right_rounded, color: BrandColors.pergaminho),
      ],
    ),
  );
}

class _StatsCard extends ConsumerWidget {
  const _StatsCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final stats = ref.watch(statsProvider).valueOrNull;
    final user = ref.watch(sessionProvider).user;
    final today = ref.watch(clockProvider)();
    final p = context.sancta;
    final week = (stats?['last_seven_days'] as List? ?? const []).whereType<Map>().toList();
    final streak = stats?['current_streak'] ?? user?.currentStreak ?? 0;

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
      decoration: BoxDecoration(
        color: p.surface,
        borderRadius: BorderRadius.circular(SanctaRadius.card),
        border: p.isDark ? null : Border.all(color: p.outline),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text('Sua caminhada', style: context.text.titleMedium)),
              StreakPill(days: streak is int ? streak : int.tryParse('$streak') ?? 0),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              _stat(context, '$streak', 'dias seguidos'),
              _stat(context, '${stats?['longest_streak'] ?? user?.longestStreak ?? 0}', 'seu recorde'),
              _stat(context, '${stats?['total_prayer_minutes'] ?? user?.totalMinutes ?? 0}', 'minutos de oração'),
            ],
          ),
          if (week.isNotEmpty) ...[
            const SizedBox(height: 16),
            Divider(color: p.outline),
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                for (final day in week)
                  Builder(
                    builder: (context) {
                      final date = DateTime.tryParse('${day['date']}');
                      final done = ((day['seconds'] as num?) ?? 0) > 0;
                      final isToday = date != null && DateUtils.isSameDay(date, today);
                      final label = date == null ? '' : DateFormat('EEEEE', 'pt_BR').format(date).toUpperCase();
                      return Semantics(
                        label: date == null ? null : '${DateFormat('EEEE', 'pt_BR').format(date)}: ${done ? 'rezou' : 'sem oração'}',
                        excludeSemantics: true,
                        child: Column(
                          children: [
                            Container(
                              width: 34,
                              height: 34,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: done ? p.primary : p.surfaceAlt,
                                border: isToday ? Border.all(color: p.primary, width: 2) : null,
                              ),
                              child: done ? Icon(Icons.check_rounded, size: 18, color: p.onPrimary) : null,
                            ),
                            const SizedBox(height: 6),
                            Text(label, style: context.text.labelSmall?.copyWith(color: isToday ? p.primary : p.textSoft)),
                          ],
                        ),
                      );
                    },
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _stat(BuildContext context, String value, String label) => Expanded(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(value, style: context.text.headlineSmall),
        Text(label, style: context.text.bodySmall),
      ],
    ),
  );
}
