import 'package:flutter/material.dart';

import '../models/models.dart';
import '../theme/app_theme.dart';
import 'common.dart';

/// Renderiza blocos vindos da API sem WebView/HTML (mais leve e sem risco de XSS):
/// parágrafos, títulos, citações, listas e os blocos litúrgicos
/// (rubrica, sacerdote, assembleia, diálogo, posturas e seções recolhíveis).
class ContentBlocksView extends StatelessWidget {
  const ContentBlocksView({super.key, required this.blocks, this.scale = 1.0});

  final List<ContentBlock> blocks;
  final double scale;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [for (final b in blocks) _BlockView(block: b, scale: scale)],
  );
}

class _BlockView extends StatelessWidget {
  const _BlockView({required this.block, required this.scale});

  final ContentBlock block;
  final double scale;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final reading = AppTheme.reading(context, size: 20 * scale);
    const gap = EdgeInsets.only(bottom: 14);

    switch (block.type) {
      case 'heading':
        return Padding(
          padding: const EdgeInsets.only(top: 14, bottom: 8),
          child: Semantics(
            header: true,
            child: Text(block.text, style: context.text.titleLarge?.copyWith(fontSize: (block.level <= 2 ? 22 : 19) * scale)),
          ),
        );
      case 'quote':
        return Container(
          margin: gap,
          padding: const EdgeInsets.only(left: 16, top: 4, bottom: 4),
          decoration: BoxDecoration(
            border: Border(left: BorderSide(color: p.primary, width: 3)),
          ),
          child: Text(block.text, style: reading.copyWith(fontStyle: FontStyle.italic)),
        );
      case 'list':
      case 'ordered_list':
        return Padding(
          padding: gap,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (var i = 0; i < block.items.length; i++)
                Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        width: 26,
                        child: Text(block.type == 'ordered_list' ? '${i + 1}.' : '•', style: reading.copyWith(color: p.primary)),
                      ),
                      Expanded(child: Text(block.items[i], style: reading)),
                    ],
                  ),
                ),
            ],
          ),
        );
      case 'image':
        return Padding(
          padding: gap,
          child: ClipRRect(borderRadius: BorderRadius.circular(SanctaRadius.card), child: NetImage(block.src, height: 200)),
        );
      case 'divider':
        return Padding(
          padding: gap,
          child: Divider(color: p.outline),
        );
      case 'rubric':
        return Padding(
          padding: gap,
          // Rubricas em vermelho, como no Missal (tom mais claro no tema escuro).
          child: Text(
            block.text,
            style: reading.copyWith(fontSize: 16.5 * scale, fontStyle: FontStyle.italic, color: p.danger),
          ),
        );
      case 'priest':
        return _Speech(label: block.speaker ?? 'PR', text: block.text, emphasize: block.highlight, scale: scale);
      case 'assembly':
        return _Speech(label: 'AS', text: block.text, bold: true, scale: scale);
      case 'dialogue':
        return _Speech(label: block.speaker ?? '℣.', text: block.text, bold: (block.speaker ?? '').contains('℟'), scale: scale);
      case 'postures':
        return _Postures(icons: block.icons);
      case 'expandable':
        return Container(
          margin: gap,
          decoration: BoxDecoration(color: p.surfaceAlt, borderRadius: BorderRadius.circular(SanctaRadius.tile)),
          child: Theme(
            data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
            child: ExpansionTile(
              title: Text(block.title ?? 'Mais', style: context.text.titleMedium),
              childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              iconColor: p.primary,
              children: [ContentBlocksView(blocks: block.children, scale: scale)],
            ),
          ),
        );
      default:
        return Padding(
          padding: gap,
          child: Text(block.text, style: reading),
        );
    }
  }
}

class _Speech extends StatelessWidget {
  const _Speech({required this.label, required this.text, this.bold = false, this.emphasize = false, required this.scale});

  final String label;
  final String text;
  final bool bold;
  final bool emphasize;
  final double scale;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final style = AppTheme.reading(context, size: 20 * scale).copyWith(
      fontWeight: bold || emphasize ? FontWeight.w600 : null,
      fontSize: (emphasize ? 22 : 20) * scale * (Theme.of(context).extension<SanctaColors>()?.readingFont == ReadingFont.classica ? 1 : .88),
    );

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: emphasize ? const EdgeInsets.all(14) : EdgeInsets.zero,
      decoration: emphasize ? BoxDecoration(color: p.iconBg, borderRadius: BorderRadius.circular(SanctaRadius.tile)) : null,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            margin: const EdgeInsets.only(top: 4, right: 10),
            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
            decoration: BoxDecoration(color: emphasize ? p.surface : p.iconBg, borderRadius: BorderRadius.circular(6)),
            child: Text(
              label,
              style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: p.primary),
            ),
          ),
          Expanded(child: Text(text, style: style)),
        ],
      ),
    );
  }
}

class _Postures extends StatelessWidget {
  const _Postures({required this.icons});

  final List<String> icons;

  static const _labels = {'de_pe': 'De pé', 'maos_postas': 'Mãos postas', 'de_joelhos': 'De joelhos'};

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: Wrap(
      spacing: 10,
      runSpacing: 8,
      children: [
        for (final icon in icons.where(_labels.containsKey))
          Chip(
            avatar: Image.asset('assets/images/$icon.png', width: 20, height: 20, color: context.sancta.primary),
            label: Text(_labels[icon]!),
          ),
      ],
    ),
  );
}
