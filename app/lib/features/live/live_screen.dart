import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/brand/brand.dart';
import '../../core/models/models.dart';
import '../../core/motion/live_badge.dart';
import '../../core/state/content_providers.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/clock.dart';
import '../../core/utils/links.dart';
import '../../core/widgets/common.dart';
import '../notifications/live_alerts.dart';

/// "hoje às 18:00", "amanhã às 07:00", "sexta às 07:00" ou "09/10 às 07:00".
String formatNextAt(DateTime at, DateTime now) {
  final local = at.toLocal();
  final time = DateFormat('HH:mm').format(local);
  final days = DateUtils.dateOnly(local).difference(DateUtils.dateOnly(now)).inDays;
  if (days == 0) return 'hoje às $time';
  if (days == 1) return 'amanhã às $time';
  if (days > 1 && days < 7) return '${DateFormat('EEEE', 'pt_BR').format(local).split('-').first} às $time';
  return '${DateFormat('dd/MM').format(local)} às $time';
}

/// Fuso da agenda do painel: Brasília (UTC−3; sem horário de verão desde 2019).
const brasiliaOffset = Duration(hours: -3);

/// Relógio de Brasília a partir do relógio do aparelho. A agenda (`schedule[].time`) é cadastrada em horário
/// de Brasília e o servidor calcula `on_schedule_now` nesse fuso: comparar com a hora local de um aparelho
/// em outro fuso (Manaus, Acre, exterior) marcaria "AGORA"/"encerrada" no horário errado.
DateTime brasiliaWallClock(DateTime now) {
  if (now.timeZoneOffset == brasiliaOffset) return now;
  final b = now.toUtc().add(brasiliaOffset);
  return DateTime(b.year, b.month, b.day, b.hour, b.minute, b.second);
}

/// Situação de um item da agenda em relação a [now] (relógio de Brasília; minutos desde a meia-noite).
enum ScheduleStatus { upcoming, now, ended }

ScheduleStatus scheduleStatus(LiveScheduleItem item, DateTime now) {
  final minutes = now.hour * 60 + now.minute;
  final end = item.minutes + (item.durationMinutes ?? 60);
  if (minutes >= item.minutes && minutes < end) return ScheduleStatus.now;
  return minutes >= end ? ScheduleStatus.ended : ScheduleStatus.upcoming;
}

/// **Ao vivo**: transmissões agora, agenda de hoje e canais (abre no YouTube — mais leve que um player embutido).
class LiveScreen extends ConsumerStatefulWidget {
  const LiveScreen({super.key});

  @override
  ConsumerState<LiveScreen> createState() => _LiveScreenState();
}

class _LiveScreenState extends ConsumerState<LiveScreen> {
  String? _category;

  @override
  Widget build(BuildContext context) {
    final live = ref.watch(liveProvider);
    final local = ref.watch(clockProvider)();
    // Agenda em horário de Brasília (ver brasiliaWallClock).
    final now = brasiliaWallClock(local);
    final otherZone = local.timeZoneOffset != brasiliaOffset;

    return Scaffold(
      appBar: AppBar(title: const Text('Ao vivo')),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(liveProvider);
          await ref.read(liveProvider.future).catchError((Object _) => <LiveChannel>[]);
        },
        child: live.when(
          skipLoadingOnRefresh: true,
          loading: () => const SkeletonTiles(count: 5),
          error: (e, _) => ListView(
            children: [ErrorView(error: e, onRetry: () => ref.invalidate(liveProvider))],
          ),
          data: (channels) {
            if (channels.isEmpty) {
              return ListView(
                children: const [
                  SizedBox(height: 60),
                  EmptyView(icon: Icons.live_tv_rounded, title: 'Nenhuma transmissão cadastrada', message: 'Em breve, missas e terços ao vivo aqui.'),
                ],
              );
            }
            final onAir = channels.where((c) => c.isOnAir).toList();
            final agenda = [
              for (final c in channels)
                for (final item in c.schedule.where((s) => s.occursOn(now))) (channel: c, item: item),
            ]..sort((a, b) => a.item.minutes.compareTo(b.item.minutes));
            final categories = {for (final c in channels) c.category: c.categoryLabel};
            final filtered = _category == null ? channels : channels.where((c) => c.category == _category).toList();

            return ListView(
              padding: EdgeInsets.fromLTRB(16, 4, 16, 32 + MediaQuery.paddingOf(context).bottom),
              children: [
                if (onAir.isNotEmpty) ...[
                  const SectionHeader('Ao vivo agora', padding: EdgeInsets.fromLTRB(4, 8, 4, 10)),
                  for (final c in onAir)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _OnAirCard(channel: c),
                    ),
                ],
                if (agenda.isNotEmpty) ...[
                  SectionHeader(otherZone ? 'Hoje na agenda (horário de Brasília)' : 'Hoje na agenda', padding: const EdgeInsets.fromLTRB(4, 14, 4, 10)),
                  TileGroup(
                    dividerIndent: 84,
                    children: [for (final a in agenda) _AgendaRow(channel: a.channel, item: a.item, now: now)],
                  ),
                ],
                const SectionHeader('Canais', padding: EdgeInsets.fromLTRB(4, 22, 4, 6)),
                if (categories.length > 1)
                  SizedBox(
                    height: 52,
                    child: ListView(
                      scrollDirection: Axis.horizontal,
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: SanctaChoiceChip(label: 'Todos', selected: _category == null, onSelected: () => setState(() => _category = null)),
                        ),
                        for (final entry in categories.entries)
                          Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: SanctaChoiceChip(
                              label: entry.value,
                              selected: _category == entry.key,
                              onSelected: () => setState(() => _category = entry.key),
                            ),
                          ),
                      ],
                    ),
                  ),
                const SizedBox(height: 6),
                for (final c in filtered)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: _ChannelTile(channel: c, now: local), // próximo horário: instante absoluto, mostrado no fuso do aparelho
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}

/// Destaque de um canal no ar: miniatura, selo AO VIVO, título e botão para o YouTube.
class _OnAirCard extends StatelessWidget {
  const _OnAirCard({required this.channel});

  final LiveChannel channel;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final c = channel;
    final title = c.nowTitle ?? c.name;
    final started = c.live?.startedAt;
    final meta = [if (title != c.name) c.name, c.categoryLabel, if (started != null) 'desde ${DateFormat('HH:mm').format(started.toLocal())}'].join(' · ');

    return Material(
      color: p.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(SanctaRadius.card),
        side: p.isDark ? BorderSide.none : BorderSide(color: p.outline),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => openLink(context, c.openUrl),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AspectRatio(
              aspectRatio: 16 / 9,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  NetImage(c.imageUrl, scene: SkyScene.noite, semanticLabel: 'Miniatura de $title'),
                  const DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [Color(0x330E1A3A), Color(0x000E1A3A), Color(0x660E1A3A)],
                      ),
                    ),
                  ),
                  Positioned(left: 12, top: 12, child: c.isLive ? const LiveBadge() : const _Pill(label: 'NO HORÁRIO DA TRANSMISSÃO')),
                  Center(
                    child: Container(
                      width: 60,
                      height: 60,
                      decoration: BoxDecoration(color: BrandColors.tinta.withValues(alpha: .55), shape: BoxShape.circle),
                      child: const Icon(Icons.play_arrow_rounded, size: 38, color: BrandColors.pergaminho),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, maxLines: 2, overflow: TextOverflow.ellipsis, style: context.text.titleMedium),
                  const SizedBox(height: 2),
                  Text(meta, maxLines: 1, overflow: TextOverflow.ellipsis, style: context.text.bodySmall),
                  const SizedBox(height: 12),
                  Builder(
                    builder: (context) {
                      final watch = FilledButton.icon(
                        onPressed: () => openLink(context, c.openUrl),
                        icon: const Icon(Icons.smart_display_rounded, size: 20),
                        label: Text(c.isLive ? 'Assistir no YouTube' : 'Abrir no YouTube'),
                      );
                      // Letra grande: um botão por linha (o rótulo não quebra no meio da palavra).
                      if (largeText(context)) {
                        return Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            watch,
                            if (c.topic != null) ...[
                              const SizedBox(height: 8),
                              Align(
                                alignment: Alignment.centerLeft,
                                child: LiveAlertButton(topic: c.topic!, channelName: c.name, compact: true),
                              ),
                            ],
                          ],
                        );
                      }
                      return Row(
                        children: [
                          Expanded(child: watch),
                          if (c.topic != null) ...[const SizedBox(width: 8), LiveAlertButton(topic: c.topic!, channelName: c.name, compact: true)],
                        ],
                      );
                    },
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(color: BrandColors.azulManto, borderRadius: BorderRadius.circular(6)),
    child: Text(
      label,
      style: const TextStyle(fontFamily: BrandFonts.figtree, color: BrandColors.pergaminho, fontSize: 11, fontWeight: FontWeight.w700, letterSpacing: .6),
    ),
  );
}

/// Linha da agenda de hoje: horário, título, canal e situação (agora / encerrada).
class _AgendaRow extends StatelessWidget {
  const _AgendaRow({required this.channel, required this.item, required this.now});

  final LiveChannel channel;
  final LiveScheduleItem item;
  final DateTime now;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final status = scheduleStatus(item, now);
    final ended = status == ScheduleStatus.ended;
    final color = ended ? p.textSoft : p.text;
    return SanctaTile(
      card: false,
      onTap: () => openLink(context, channel.openUrl),
      leading: SizedBox(
        width: 56 * textScaleOf(context).clamp(1.0, 2.0),
        child: Text(item.time, style: context.text.titleMedium?.copyWith(color: ended ? p.textSoft : p.primary)),
      ),
      title: item.title ?? channel.name,
      titleColor: color,
      subtitle: [channel.name, if (item.durationMinutes != null) '${item.durationMinutes} min', if (ended) 'encerrada'].join(' · '),
      subtitleMaxLines: 1,
      trailing: status == ScheduleStatus.now ? (channel.isLive ? const LiveBadge(compact: true) : const _NowChip()) : const SizedBox.shrink(),
    );
  }
}

class _NowChip extends StatelessWidget {
  const _NowChip();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
    decoration: BoxDecoration(color: context.sancta.iconBg, borderRadius: BorderRadius.circular(6)),
    child: Text('AGORA', style: context.text.labelSmall?.copyWith(color: context.sancta.primary, fontSize: 10.5, letterSpacing: .6)),
  );
}

/// Canal na lista: imagem, status/próximo horário e "Me avise".
class _ChannelTile extends StatelessWidget {
  const _ChannelTile({required this.channel, required this.now});

  final LiveChannel channel;
  final DateTime now;

  @override
  Widget build(BuildContext context) {
    final c = channel;
    final status = c.isLive
        ? 'Ao vivo agora'
        : c.onScheduleNow
        ? 'No horário: ${c.currentScheduleTitle ?? 'transmissão'}'
        : c.upcoming?.scheduledAt != null
        ? 'Agendada: ${formatNextAt(c.upcoming!.scheduledAt!, now)}'
        : c.nextAt != null
        ? 'Próxima: ${formatNextAt(c.nextAt!, now)}'
        : c.description;
    final image = c.logoUrl ?? c.coverUrl ?? c.thumbnailUrl;
    return SanctaTile(
      onTap: () => openLink(context, c.openUrl),
      leading: image != null
          ? ClipRRect(
              borderRadius: BorderRadius.circular(SanctaRadius.icon),
              child: NetImage(image, width: 48, height: 48, scene: SkyScene.noite),
            )
          : IconBadge(_categoryIcon(c.category), size: 48),
      title: c.name,
      subtitle: [c.categoryLabel, ?status].join(' · '),
      trailing: c.topic != null ? LiveAlertButton(topic: c.topic!, channelName: c.name, compact: true) : null,
    );
  }

  static IconData _categoryIcon(String category) => switch (category) {
    'missa' => Icons.church_rounded,
    'terco' => Icons.radio_button_checked_rounded,
    'adoracao' => Icons.wb_sunny_outlined,
    'oracao' => Icons.volunteer_activism_outlined,
    _ => Icons.live_tv_rounded,
  };
}

/// Botão "Me avise": assina o tópico do canal para receber o push quando ele entrar ao vivo.
class LiveAlertButton extends ConsumerWidget {
  const LiveAlertButton({super.key, required this.topic, required this.channelName, this.compact = false});

  final String topic;
  final String channelName;
  final bool compact;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final on = ref.watch(liveAlertsProvider).contains(topic);
    final p = context.sancta;

    Future<void> toggle() async {
      try {
        final enabled = await ref.read(liveAlertsProvider.notifier).toggle(topic);
        if (context.mounted) showMessage(context, enabled ? 'Pronto. Avisaremos quando $channelName entrar ao vivo.' : 'Aviso de $channelName desligado.');
      } on NotificationPermissionDenied catch (e) {
        if (context.mounted) showMessage(context, e.message);
      } catch (_) {
        if (context.mounted) showMessage(context, 'Não foi possível mudar o aviso agora. Tente de novo.');
      }
    }

    final label = on ? 'Avisando' : 'Me avise';
    return Semantics(
      toggled: on,
      button: true,
      label: on ? 'Desligar aviso de $channelName' : 'Me avise quando $channelName entrar ao vivo',
      excludeSemantics: true,
      child: on
          ? FilledButton.icon(
              onPressed: toggle,
              style: FilledButton.styleFrom(
                backgroundColor: p.iconBg,
                foregroundColor: p.primary,
                minimumSize: const Size(48, 48),
                padding: EdgeInsets.symmetric(horizontal: compact ? 12 : 18),
                textStyle: context.text.labelLarge?.copyWith(fontSize: 13.5),
              ),
              icon: const Icon(Icons.notifications_active_rounded, size: 18),
              label: Text(label),
            )
          : OutlinedButton.icon(
              onPressed: toggle,
              style: OutlinedButton.styleFrom(
                minimumSize: const Size(48, 48),
                padding: EdgeInsets.symmetric(horizontal: compact ? 12 : 18),
                textStyle: context.text.labelLarge?.copyWith(fontSize: 13.5),
              ),
              icon: const Icon(Icons.notifications_none_rounded, size: 18),
              label: Text(label),
            ),
    );
  }
}
