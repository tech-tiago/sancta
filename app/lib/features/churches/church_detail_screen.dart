import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/router.dart';
import '../../core/brand/brand.dart';
import '../../core/models/models.dart';
import '../../core/providers.dart';
import '../../core/state/content_providers.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/links.dart';
import '../../core/widgets/common.dart';
import 'churches_screen.dart';

class ChurchDetailScreen extends ConsumerStatefulWidget {
  const ChurchDetailScreen({super.key, required this.id});

  final String id;

  @override
  ConsumerState<ChurchDetailScreen> createState() => _ChurchDetailScreenState();
}

class _ChurchDetailScreenState extends ConsumerState<ChurchDetailScreen> {
  bool? _favorite;

  static const _scheduleLabels = {
    'missa': ('Missas', Icons.auto_stories_outlined),
    'confissao': ('Confissões', Icons.forum_outlined),
    'adoracao': ('Adoração', Icons.wb_sunny_outlined),
    'terco': ('Terço', Icons.radio_button_checked_rounded),
    'secretaria': ('Secretaria', Icons.schedule_rounded),
    'outro': ('Outros horários', Icons.event_note_outlined),
  };

  Future<void> _toggleFavorite(Church c) async {
    if (!requireAccount(context, ref, reason: 'Entre para favoritar igrejas e receber avisos.')) return;
    try {
      final result = await ref.read(apiClientProvider).post('/churches/${c.id}/favorite');
      setState(() => _favorite = (result as Map)['is_favorite'] == true);
      ref.invalidate(favoriteChurchesProvider);
    } catch (e) {
      if (mounted) showMessage(context, errorText(e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final church = ref.watch(churchProvider(widget.id));

    return church.when(
      loading: () => Scaffold(appBar: AppBar(), body: const SkeletonTiles(count: 5)),
      error: (e, _) => Scaffold(
        appBar: AppBar(),
        body: ErrorView(error: e, onRetry: () => ref.invalidate(churchProvider(widget.id))),
      ),
      data: (c) {
        final fav = _favorite ?? c.isFavorite;
        final contacts = c.contacts;
        final p = context.sancta;
        final actions = <Widget>[
          if (c.mapsUrl != null) _Action(icon: Icons.directions_rounded, label: 'Como chegar', primary: true, onTap: () => openLink(context, c.mapsUrl)),
          if (contacts['phone'] != null)
            _Action(
              icon: Icons.call_rounded,
              label: 'Ligar',
              onTap: () => openLink(context, 'tel:${'${contacts['phone']}'.replaceAll(RegExp(r'[^0-9+]'), '')}'),
            ),
          if (contacts['whatsapp'] != null)
            _Action(
              icon: Icons.chat_rounded,
              label: 'WhatsApp',
              onTap: () => openLink(context, 'https://wa.me/${'${contacts['whatsapp']}'.replaceAll(RegExp(r'[^0-9]'), '')}'),
            ),
          if (contacts['email'] != null) _Action(icon: Icons.mail_rounded, label: 'E-mail', onTap: () => openLink(context, 'mailto:${contacts['email']}')),
          if (contacts['instagram'] != null)
            _Action(
              icon: Icons.camera_alt_rounded,
              label: 'Instagram',
              onTap: () => openLink(context, 'https://instagram.com/${'${contacts['instagram']}'.replaceAll('@', '')}'),
            ),
          if (contacts['website'] != null && '${contacts['website']}'.startsWith('http'))
            _Action(icon: Icons.language_rounded, label: 'Site', onTap: () => openLink(context, '${contacts['website']}', inApp: true)),
        ];

        return Scaffold(
          body: CustomScrollView(
            slivers: [
              SliverAppBar(
                pinned: true,
                expandedHeight: 220,
                leading: const _CircleBack(),
                actions: [
                  _CircleAction(
                    tooltip: fav ? 'Remover dos favoritos' : 'Favoritar',
                    icon: fav ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                    onPressed: () => _toggleFavorite(c),
                  ),
                  _CircleAction(
                    tooltip: 'Compartilhar',
                    icon: Icons.ios_share_rounded,
                    onPressed: () => shareText('${c.name}\n${c.address}\n\nHorários no app Sancta'),
                  ),
                  const SizedBox(width: 8),
                ],
                flexibleSpace: FlexibleSpaceBar(
                  background: NetImage(c.coverUrl, scene: SkyScene.luz, icon: Icons.church_rounded, semanticLabel: 'Foto de ${c.name}'),
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 18, 20, 0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Text(c.typeLabel, style: context.text.labelLarge?.copyWith(color: p.primary, fontSize: 14)),
                          if (c.isVerified) ...[
                            const SizedBox(width: 6),
                            Icon(Icons.verified_rounded, size: 16, color: p.primary),
                            const SizedBox(width: 3),
                            Text('Verificada', style: context.text.labelMedium?.copyWith(color: p.primary)),
                          ],
                        ],
                      ),
                      const SizedBox(height: 4),
                      Semantics(header: true, child: Text(c.name, style: context.text.headlineSmall)),
                      const SizedBox(height: 4),
                      Text([c.address, if (c.distanceKm != null) formatDistance(c.distanceKm!)].join(' · '), style: context.text.bodyMedium),
                      if (c.nextMass != null) ...[
                        const SizedBox(height: 12),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          decoration: BoxDecoration(color: p.iconBg, borderRadius: BorderRadius.circular(SanctaRadius.pill)),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.schedule_rounded, size: 18, color: p.primary),
                              const SizedBox(width: 6),
                              Flexible(
                                child: Text('Próxima missa: ${c.nextMass}', style: context.text.labelLarge?.copyWith(color: p.primary, fontSize: 14)),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              if (actions.isNotEmpty)
                SliverToBoxAdapter(
                  child: SizedBox(
                    height: 68,
                    child: ListView.separated(
                      padding: const EdgeInsets.fromLTRB(20, 14, 20, 2),
                      scrollDirection: Axis.horizontal,
                      itemCount: actions.length,
                      separatorBuilder: (_, _) => const SizedBox(width: 8),
                      itemBuilder: (_, i) => actions[i],
                    ),
                  ),
                ),
              if (c.schedules.isEmpty)
                const SliverToBoxAdapter(
                  child: EmptyView(
                    icon: Icons.schedule_rounded,
                    title: 'Horários ainda não cadastrados',
                    message: 'Ajude a comunidade enviando os horários desta igreja.',
                  ),
                )
              else
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                  sliver: SliverList.list(
                    children: [
                      for (final entry in c.schedules.entries)
                        Padding(
                          padding: const EdgeInsets.only(top: 10),
                          child: _ScheduleCard(
                            title: _scheduleLabels[entry.key]?.$1 ?? entry.key,
                            icon: _scheduleLabels[entry.key]?.$2 ?? Icons.event_note_outlined,
                            items: entry.value,
                          ),
                        ),
                    ],
                  ),
                ),
              if (c.description != null)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(20, 24, 20, 0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Sobre', style: context.text.titleMedium),
                        const SizedBox(height: 6),
                        Text(c.description!, style: context.text.bodyLarge),
                      ],
                    ),
                  ),
                ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.fromLTRB(20, 24, 20, 32 + MediaQuery.paddingOf(context).bottom),
                  child: Column(
                    children: [
                      Text('Horários podem mudar em datas especiais. Confirme com a paróquia.', textAlign: TextAlign.center, style: context.text.bodySmall),
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: () {
                          if (requireAccount(context, ref, reason: 'Entre para enviar correções.')) {
                            context.push('/sugerir-igreja?igreja=${c.id}&nome=${Uri.encodeComponent(c.name)}');
                          }
                        },
                        icon: const Icon(Icons.edit_outlined),
                        label: const Text('Corrigir informações'),
                      ),
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

class _ScheduleCard extends StatelessWidget {
  const _ScheduleCard({required this.title, required this.icon, required this.items});

  final String title;
  final IconData icon;
  final List<ScheduleItem> items;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
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
              IconBadge(icon, size: 36),
              const SizedBox(width: 12),
              Text(title, style: context.text.titleMedium),
            ],
          ),
          const SizedBox(height: 8),
          for (final s in items)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              // Letra grande: dia em cima e horário embaixo (o dia não quebra no meio da palavra).
              child: Builder(
                builder: (context) {
                  final day = Text(s.weekdayLabel, style: context.text.bodyMedium);
                  final time = Text(
                    [if (s.startsAt != null) s.endsAt != null ? '${s.startsAt} às ${s.endsAt}' : s.startsAt!, if (s.notes != null) s.notes!].join(' · '),
                    style: context.text.bodyLarge?.copyWith(fontWeight: FontWeight.w600),
                  );
                  if (largeText(context)) return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [day, time]);
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(width: 118, child: day),
                      Expanded(child: time),
                    ],
                  );
                },
              ),
            ),
        ],
      ),
    );
  }
}

class _Action extends StatelessWidget {
  const _Action({required this.icon, required this.label, required this.onTap, this.primary = false});

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool primary;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final style = primary
        ? FilledButton.styleFrom(minimumSize: const Size(48, 48), padding: const EdgeInsets.symmetric(horizontal: 18))
        : FilledButton.styleFrom(
            minimumSize: const Size(48, 48),
            padding: const EdgeInsets.symmetric(horizontal: 18),
            backgroundColor: p.iconBg,
            foregroundColor: p.primary,
          );
    return FilledButton.icon(onPressed: onTap, style: style, icon: Icon(icon, size: 19), label: Text(label));
  }
}

/// Botões redondos legíveis sobre a foto e sobre o fundo (quando a barra recolhe).
class _CircleAction extends StatelessWidget {
  const _CircleAction({required this.tooltip, required this.icon, required this.onPressed});

  final String tooltip;
  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 2),
    child: IconButton.filled(
      tooltip: tooltip,
      style: IconButton.styleFrom(backgroundColor: BrandColors.tinta.withValues(alpha: .55), foregroundColor: BrandColors.pergaminho),
      icon: Icon(icon, size: 21),
      onPressed: onPressed,
    ),
  );
}

class _CircleBack extends StatelessWidget {
  const _CircleBack();

  @override
  Widget build(BuildContext context) => Center(
    child: _CircleAction(tooltip: 'Voltar', icon: Icons.arrow_back_rounded, onPressed: () => Navigator.maybePop(context)),
  );
}
