import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../state/theme_controller.dart';
import '../theme/app_theme.dart';

/// Ajustes de leitura (fonte e tamanho) — usados pela Bíblia, orações, liturgia e Ajustes.
class ReadingSettingsPanel extends ConsumerWidget {
  const ReadingSettingsPanel({super.key, this.sample = 'No princípio era o Verbo, e o Verbo estava junto de Deus.'});

  final String sample;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final appearance = ref.watch(appearanceProvider);
    final controller = ref.read(appearanceProvider.notifier);
    final p = context.sancta;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text('Fonte de leitura', style: context.text.titleSmall),
        const SizedBox(height: 10),
        SegmentedButton<ReadingFont>(
          showSelectedIcon: false,
          segments: [
            for (final f in ReadingFont.values)
              ButtonSegment(
                value: f,
                label: Text(
                  f.label,
                  style: TextStyle(fontFamily: f.family, fontSize: f == ReadingFont.classica ? 16 : 14.5),
                ),
                tooltip: f.description,
              ),
          ],
          selected: {appearance.readingFont},
          onSelectionChanged: (s) => controller.setReadingFont(s.first),
        ),
        const SizedBox(height: 20),
        Row(
          children: [
            Expanded(child: Text('Tamanho do texto', style: context.text.titleSmall)),
            Text('${(appearance.readingScale * 100).round()}%', style: context.text.labelMedium),
          ],
        ),
        Row(
          children: [
            Icon(Icons.text_decrease_rounded, color: p.textSoft, semanticLabel: 'Menor'),
            Expanded(
              child: Slider(
                value: appearance.readingScale,
                min: .8,
                max: 1.6,
                divisions: 8,
                label: '${(appearance.readingScale * 100).round()}%',
                semanticFormatterCallback: (v) => 'Tamanho do texto ${(v * 100).round()}%',
                onChanged: controller.setReadingScale,
              ),
            ),
            Icon(Icons.text_increase_rounded, color: p.textSoft, semanticLabel: 'Maior'),
          ],
        ),
        const SizedBox(height: 6),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: p.surfaceAlt, borderRadius: BorderRadius.circular(SanctaRadius.tile)),
          child: Text(sample, style: AppTheme.reading(context, size: 20 * appearance.readingScale)),
        ),
      ],
    );
  }
}

/// Abre os ajustes de leitura numa folha inferior.
Future<void> showReadingSettings(BuildContext context) => showModalBottomSheet<void>(
  context: context,
  isScrollControlled: true,
  builder: (sheet) => SafeArea(
    child: SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Leitura', style: sheet.text.titleLarge),
          const SizedBox(height: 16),
          const ReadingSettingsPanel(),
        ],
      ),
    ),
  ),
);
