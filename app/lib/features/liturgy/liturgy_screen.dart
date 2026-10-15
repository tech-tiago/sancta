import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/state/content_providers.dart';
import '../../core/state/theme_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/clock.dart';
import '../../core/utils/links.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/reading_settings.dart';

/// **Liturgia diária**: leituras, orações do dia e reflexão (Sancta+), por data.
class LiturgyScreen extends ConsumerStatefulWidget {
  const LiturgyScreen({super.key});

  @override
  ConsumerState<LiturgyScreen> createState() => _LiturgyScreenState();
}

class _LiturgyScreenState extends ConsumerState<LiturgyScreen> {
  late DateTime _date = ref.read(clockProvider)();
  final _opened = DateTime.now();

  String get _key => DateFormat('yyyy-MM-dd').format(_date);

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 60)),
    );
    if (picked != null) setState(() => _date = picked);
  }

  void _shift(int days) => setState(() => _date = _date.add(Duration(days: days)));

  @override
  Widget build(BuildContext context) {
    final liturgy = ref.watch(liturgyProvider(_key));
    final scale = ref.watch(appearanceProvider).readingScale;
    final dateLabel = DateFormat("EEEE, d 'de' MMMM", 'pt_BR').format(_date);

    PreferredSizeWidget appBar({List<Widget> actions = const [], PreferredSizeWidget? bottom}) => AppBar(
      title: const Text('Liturgia diária'),
      actions: [
        IconButton(tooltip: 'Ajustes de leitura', icon: const Icon(Icons.text_fields_rounded), onPressed: () => showReadingSettings(context)),
        IconButton(tooltip: 'Escolher data', icon: const Icon(Icons.calendar_month_rounded), onPressed: _pickDate),
        ...actions,
      ],
      bottom: bottom,
    );

    return liturgy.when(
      loading: () => Scaffold(appBar: appBar(), body: const SkeletonTiles(count: 5)),
      error: (e, _) => Scaffold(
        appBar: appBar(),
        body: ErrorView(error: e, onRetry: () => ref.invalidate(liturgyProvider(_key))),
      ),
      data: (data) {
        final p = context.sancta;
        final readings = (data['readings'] is List ? data['readings'] as List : const []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
        // `prayers` é um mapa; servidores antigos mandavam `[]` quando vazio.
        final prayers = data['prayers'] is Map ? Map<String, dynamic>.from(data['prayers'] as Map) : const <String, dynamic>{};
        final color = parseHexColor(data['color_hex'] is String ? data['color_hex'] as String : null) ?? p.primary;
        final hasHomily = data['homily'] != null || data['homily_locked'] == true;
        final tabs = [
          for (final r in readings) Tab(height: 48, text: _shortLabel('${r['label']}')),
          if (prayers.isNotEmpty) const Tab(height: 48, text: 'Orações'),
          if (hasHomily) const Tab(height: 48, text: 'Reflexão'),
        ];
        final gospel = readings.where((r) => r['label'] == 'Evangelho').firstOrNull?['reference'];

        return DefaultTabController(
          length: tabs.length,
          child: Scaffold(
            appBar: appBar(
              actions: [
                IconButton(
                  tooltip: 'Compartilhar',
                  icon: const Icon(Icons.ios_share_rounded),
                  onPressed: () =>
                      shareText('Liturgia de ${DateFormat('dd/MM').format(_date)} — ${data['title']}\nEvangelho: ${gospel ?? ''}\n\nNo app Sancta'),
                ),
              ],
              bottom: PreferredSize(
                // Data, título (até 2 linhas) e cor crescem com a letra do sistema (até 2×).
                preferredSize: Size.fromHeight((tabs.isEmpty ? 88 : 150) + 80 * (MediaQuery.textScalerOf(context).scale(1) - 1).clamp(0.0, 1.0)),
                child: Column(
                  children: [
                    Padding(
                      padding: const EdgeInsets.fromLTRB(8, 0, 8, 8),
                      child: Row(
                        children: [
                          IconButton(tooltip: 'Dia anterior', icon: const Icon(Icons.chevron_left_rounded), onPressed: () => _shift(-1)),
                          Expanded(
                            child: Column(
                              children: [
                                Text('${dateLabel[0].toUpperCase()}${dateLabel.substring(1)}', style: context.text.labelMedium),
                                const SizedBox(height: 2),
                                Text(
                                  '${data['title'] ?? ''}',
                                  maxLines: 2,
                                  textAlign: TextAlign.center,
                                  overflow: TextOverflow.ellipsis,
                                  style: context.text.titleSmall,
                                ),
                                const SizedBox(height: 4),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    Container(
                                      width: 10,
                                      height: 10,
                                      decoration: BoxDecoration(
                                        color: color,
                                        shape: BoxShape.circle,
                                        border: Border.all(color: p.outline),
                                      ),
                                    ),
                                    const SizedBox(width: 6),
                                    Text('Cor litúrgica: ${data['color'] ?? '—'}', style: context.text.bodySmall),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          IconButton(tooltip: 'Próximo dia', icon: const Icon(Icons.chevron_right_rounded), onPressed: () => _shift(1)),
                        ],
                      ),
                    ),
                    if (tabs.isNotEmpty) PillTabBar(tabs: tabs, isScrollable: true, margin: const EdgeInsets.fromLTRB(16, 0, 16, 8)),
                  ],
                ),
              ),
            ),
            body: tabs.isEmpty
                ? const EmptyView(icon: Icons.event_busy_rounded, title: 'Liturgia indisponível para esta data', message: 'Escolha outro dia no calendário.')
                : TabBarView(
                    children: [
                      for (final r in readings) _ReadingView(reading: r, scale: scale, opened: _opened),
                      if (prayers.isNotEmpty)
                        ListView(
                          padding: EdgeInsets.fromLTRB(24, 20, 24, 40 + MediaQuery.paddingOf(context).bottom),
                          children: [
                            for (final entry in {'collect': 'Oração do dia', 'offerings': 'Sobre as oferendas', 'communion': 'Depois da comunhão'}.entries)
                              if (prayers[entry.key] != null) ...[
                                Text(entry.value, style: context.text.titleLarge),
                                const SizedBox(height: 8),
                                Text('${prayers[entry.key]}', style: AppTheme.reading(context, size: 20 * scale)),
                                const SizedBox(height: 26),
                              ],
                          ],
                        ),
                      if (hasHomily)
                        data['homily_locked'] == true
                            ? EmptyView(
                                icon: Icons.lock_outline_rounded,
                                title: 'Reflexão do dia no Sancta+',
                                message: 'Aprofunde o Evangelho com uma meditação curta para cada dia.',
                                action: FilledButton(onPressed: () => context.push('/planos'), child: const Text('Conhecer o Sancta+')),
                              )
                            : ListView(
                                padding: EdgeInsets.fromLTRB(24, 20, 24, 40 + MediaQuery.paddingOf(context).bottom),
                                children: [
                                  Row(
                                    children: [
                                      Expanded(child: Text('Reflexão', style: context.text.headlineSmall)),
                                      const PremiumBadge(compact: true),
                                    ],
                                  ),
                                  if (data['homily_author'] != null) Text('por ${data['homily_author']}', style: context.text.bodySmall),
                                  const SizedBox(height: 14),
                                  Text('${data['homily']}', style: AppTheme.reading(context, size: 20 * scale)),
                                ],
                              ),
                    ],
                  ),
          ),
        );
      },
    );
  }

  String _shortLabel(String label) => switch (label) {
    'Primeira leitura' => '1ª leitura',
    'Segunda leitura' => '2ª leitura',
    'Salmo responsorial' => 'Salmo',
    _ => label,
  };
}

class _ReadingView extends ConsumerWidget {
  const _ReadingView({required this.reading, required this.scale, required this.opened});

  final Map<String, dynamic> reading;
  final double scale;
  final DateTime opened;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.sancta;
    // Remove numeração de versículos colada ao texto (ex.: "1Jó respondeu" → "Jó respondeu").
    final text = '${reading['text'] ?? ''}'.replaceAllMapped(RegExp(r'(^|\s)(\d{1,3})(?=[A-ZÀ-Úa-zà-ú“"])'), (m) => '${m[1]}');

    return ListView(
      padding: EdgeInsets.fromLTRB(24, 20, 24, 40 + MediaQuery.paddingOf(context).bottom),
      children: [
        Text('${reading['label']}', style: context.text.labelLarge?.copyWith(color: p.primary, fontSize: 14)),
        const SizedBox(height: 4),
        if (reading['reference'] != null) Semantics(header: true, child: Text('${reading['reference']}', style: context.text.headlineSmall)),
        if (reading['title'] != null) ...[
          const SizedBox(height: 4),
          Text('${reading['title']}', style: context.text.bodyMedium?.copyWith(fontStyle: FontStyle.italic)),
        ],
        if (reading['refrain'] != null) ...[
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(color: p.iconBg, borderRadius: BorderRadius.circular(SanctaRadius.tile)),
            child: Text(
              '℟. ${reading['refrain']}',
              style: AppTheme.reading(context, size: 20 * scale).copyWith(fontWeight: FontWeight.w700, color: p.text),
            ),
          ),
        ],
        const SizedBox(height: 18),
        Text(text, style: AppTheme.reading(context, size: 21 * scale)),
        const SizedBox(height: 28),
        if (reading['label'] == 'Evangelho')
          FilledButton.icon(
            onPressed: () async {
              final seconds = DateTime.now().difference(opened).inSeconds;
              await recordPrayerSession(ref, type: 'liturgy', seconds: seconds < 60 ? 300 : seconds);
              if (context.mounted) showMessage(context, 'Palavra da salvação. Glória a vós, Senhor.');
            },
            icon: const Icon(Icons.check_rounded),
            label: const Text('Concluir leitura do dia'),
          ),
      ],
    );
  }
}
