import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_tts/flutter_tts.dart';
import 'package:go_router/go_router.dart';

import '../../app/router.dart';
import '../../core/brand/brand.dart';
import '../../core/models/models.dart';
import '../../core/motion/skeleton.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import '../../core/providers.dart';
import '../../core/state/config_controller.dart';
import '../../core/state/content_providers.dart';
import '../../core/state/session_controller.dart';
import '../../core/state/theme_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/palette.dart';
import '../../core/utils/links.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/reading_settings.dart';

/// Leitor de capítulo: toque nos versículos para destacar, anotar, marcar,
/// copiar, compartilhar ou pedir explicação (IA, Sancta+). Também lê em voz alta.
class ChapterScreen extends ConsumerStatefulWidget {
  const ChapterScreen({super.key, required this.book, required this.chapter, this.highlightVerse});

  final String book;
  final int chapter;
  final int? highlightVerse;

  @override
  ConsumerState<ChapterScreen> createState() => _ChapterScreenState();
}

class _ChapterScreenState extends ConsumerState<ChapterScreen> {
  final Set<int> _selected = {};
  final _tts = FlutterTts();
  final _opened = DateTime.now();
  bool _speaking = false;
  bool _progressSaved = false;
  final _keys = <int, GlobalKey>{};

  late final ApiClient _api;
  late final bool _authenticated;

  static const _highlightNames = {'gold': 'Dourado', 'blue': 'Azul', 'green': 'Verde', 'rose': 'Rosa', 'lilac': 'Lilás'};

  @override
  void initState() {
    super.initState();
    _api = ref.read(apiClientProvider);
    _authenticated = ref.read(sessionProvider).isAuthenticated;
    _tts.setLanguage('pt-BR');
    _tts.setSpeechRate(0.45);
    _tts.setCompletionHandler(() => mounted ? setState(() => _speaking = false) : null);
  }

  @override
  void dispose() {
    _tts.stop();
    // Leitura de 1 min ou mais conta como tempo de oração (sequência de dias).
    final seconds = DateTime.now().difference(_opened).inSeconds;
    if (_authenticated && seconds >= 60) {
      unawaited(_api.post('/me/prayer-sessions', data: {'content_type': 'bible', 'seconds': seconds.clamp(60, 14400)}).catchError((_) => null));
    }
    super.dispose();
  }

  void _saveProgress() {
    if (_progressSaved || !ref.read(sessionProvider).isAuthenticated) return;
    _progressSaved = true;
    ref
        .read(apiClientProvider)
        .put('/me/bible/progress', data: {'book': widget.book, 'chapter': widget.chapter})
        .then((_) => ref.invalidate(readingProgressProvider))
        .catchError((_) => null);
  }

  void _scrollToHighlight() {
    final key = _keys[widget.highlightVerse];
    if (key?.currentContext != null) Scrollable.ensureVisible(key!.currentContext!, duration: const Duration(milliseconds: 400), alignment: .2);
  }

  Future<void> _toggleSpeak(BibleChapter chapter) async {
    if (_speaking) {
      await _tts.stop();
      setState(() => _speaking = false);
      return;
    }
    setState(() => _speaking = true);
    await _tts.speak('${chapter.bookName}, capítulo ${chapter.chapter}. ${chapter.verses.map((v) => v.text).join(' ')}');
  }

  String _reference(BibleChapter c) {
    final list = _selected.toList()..sort();
    if (list.isEmpty) return '${c.abbreviation} ${c.chapter}';
    final contiguous = list.last - list.first + 1 == list.length;
    final verses = list.length == 1 ? '${list.first}' : (contiguous ? '${list.first}-${list.last}' : list.join('.'));
    return '${c.abbreviation} ${c.chapter},$verses';
  }

  String _selectedText(BibleChapter c) => c.verses.where((v) => _selected.contains(v.number)).map((v) => v.text).join(' ');

  List<int> _selectedIds(BibleChapter c) => c.verses.where((v) => _selected.contains(v.number)).map((v) => v.id).toList();

  Future<void> _annotate(BibleChapter c, Map<String, dynamic> changes) async {
    if (!requireAccount(context, ref, reason: 'Entre para salvar destaques, notas e marcadores.')) return;
    try {
      await ref.read(apiClientProvider).put('/me/bible/annotations', data: {'verse_ids': _selectedIds(c), ...changes});
      setState(_selected.clear);
      ref.invalidate(chapterProvider((widget.book, widget.chapter)));
    } catch (e) {
      if (mounted) showMessage(context, errorText(e));
    }
  }

  Future<void> _note(BibleChapter c) async {
    if (!requireAccount(context, ref, reason: 'Entre para escrever notas nos versículos.')) return;
    final first = c.verses.firstWhere((v) => _selected.contains(v.number));
    final controller = TextEditingController(text: first.note);
    final saved = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      builder: (sheet) => Padding(
        padding: EdgeInsets.fromLTRB(24, 0, 24, MediaQuery.viewInsetsOf(sheet).bottom + 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Nota', style: sheet.text.titleLarge),
            Text(_reference(c), style: sheet.text.bodyMedium),
            const SizedBox(height: 14),
            TextField(
              controller: controller,
              maxLines: 6,
              maxLength: 5000,
              autofocus: true,
              decoration: const InputDecoration(hintText: 'O que esta Palavra diz ao seu coração?'),
            ),
            const SizedBox(height: 8),
            FilledButton(onPressed: () => Navigator.pop(sheet, controller.text), child: const Text('Salvar nota')),
          ],
        ),
      ),
    );
    if (saved != null) await _annotate(c, {'note': saved.trim().isEmpty ? null : saved.trim()});
  }

  Future<void> _explain(BibleChapter c) async {
    if (!requireAccount(context, ref, reason: 'Entre para usar a explicação de versículos.')) return;
    if (!ref.read(isPremiumProvider)) {
      context.push('/planos');
      return;
    }
    final ids = _selectedIds(c).take(10).toList();
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _ExplanationSheet(verseIds: ids, reference: _reference(c)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final chapter = ref.watch(chapterProvider((widget.book, widget.chapter)));
    final scale = ref.watch(appearanceProvider).readingScale;
    final config = ref.watch(configValueProvider);

    return chapter.when(
      loading: () => Scaffold(appBar: AppBar(), body: const _ChapterSkeleton()),
      error: (e, _) => Scaffold(
        appBar: AppBar(),
        body: ErrorView(error: e, onRetry: () => ref.invalidate(chapterProvider((widget.book, widget.chapter)))),
      ),
      data: (c) {
        _saveProgress();
        if (widget.highlightVerse != null) WidgetsBinding.instance.addPostFrameCallback((_) => _scrollToHighlight());

        return Scaffold(
          appBar: AppBar(
            title: Text('${c.bookName} ${c.chapter}'),
            actions: [
              IconButton(
                tooltip: _speaking ? 'Parar leitura em voz alta' : 'Ouvir capítulo',
                icon: Icon(_speaking ? Icons.stop_circle_outlined : Icons.headphones_rounded),
                onPressed: () => _toggleSpeak(c),
              ),
              IconButton(tooltip: 'Ajustes de leitura', icon: const Icon(Icons.text_fields_rounded), onPressed: () => showReadingSettings(context)),
              const SizedBox(width: 4),
            ],
          ),
          body: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 680),
              child: ListView(
                padding: EdgeInsets.fromLTRB(22, 4, 22, (_selected.isEmpty ? 40 : 200) + MediaQuery.paddingOf(context).bottom),
                children: [
                  Text(c.bookName, style: context.text.labelLarge?.copyWith(color: context.sancta.primary)),
                  Semantics(header: true, child: Text('Capítulo ${c.chapter}', style: context.text.headlineMedium)),
                  const SizedBox(height: 18),
                  for (final v in c.verses) _verse(context, v, scale),
                  const SizedBox(height: 28),
                  Row(
                    children: [
                      if (c.previous != null)
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: () => context.pushReplacement('/biblia/${c.previous!.book}/${c.previous!.chapter}'),
                            icon: const Icon(Icons.chevron_left_rounded),
                            label: const Text('Anterior'),
                          ),
                        ),
                      if (c.previous != null && c.next != null) const SizedBox(width: 12),
                      if (c.next != null)
                        Expanded(
                          child: FilledButton.icon(
                            onPressed: () => context.pushReplacement('/biblia/${c.next!.book}/${c.next!.chapter}'),
                            icon: const Icon(Icons.chevron_right_rounded),
                            label: const Text('Próximo'),
                            iconAlignment: IconAlignment.end,
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          bottomSheet: _selected.isEmpty ? null : _actionBar(context, c, config.feature('ai_explanations')),
        );
      },
    );
  }

  Widget _verse(BuildContext context, BibleVerse v, double scale) {
    final p = context.sancta;
    final selected = _selected.contains(v.number);
    final highlight = v.highlight != null ? SanctaPalette.highlights[v.highlight] : null;
    final key = _keys.putIfAbsent(v.number, GlobalKey.new);

    return Semantics(
      key: key,
      selected: selected,
      label: 'Versículo ${v.number}',
      child: InkWell(
        onTap: () => setState(() => selected ? _selected.remove(v.number) : _selected.add(v.number)),
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 6),
          decoration: BoxDecoration(
            color: selected ? p.primary.withValues(alpha: .12) : (widget.highlightVerse == v.number ? p.primary.withValues(alpha: .07) : null),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Text.rich(
            TextSpan(
              children: [
                WidgetSpan(
                  alignment: PlaceholderAlignment.top,
                  child: Padding(
                    padding: const EdgeInsets.only(right: 5, top: 3),
                    child: Text(
                      '${v.number}',
                      style: TextStyle(fontFamily: BrandFonts.figtree, fontSize: 11.5 * scale, fontWeight: FontWeight.w700, color: p.primary),
                    ),
                  ),
                ),
                TextSpan(
                  text: v.text,
                  style: AppTheme.reading(context, size: 21 * scale).copyWith(
                    backgroundColor: highlight,
                    decoration: selected ? TextDecoration.underline : null,
                    decorationColor: p.primary,
                    decorationStyle: TextDecorationStyle.dotted,
                  ),
                ),
                if (v.bookmarked)
                  WidgetSpan(
                    child: Padding(
                      padding: const EdgeInsets.only(left: 4),
                      child: Icon(Icons.bookmark_rounded, size: 16, color: p.primary, semanticLabel: 'Marcado'),
                    ),
                  ),
                if (v.note != null)
                  WidgetSpan(
                    child: Padding(
                      padding: const EdgeInsets.only(left: 4),
                      child: Icon(Icons.sticky_note_2_rounded, size: 16, color: p.primary, semanticLabel: 'Com nota'),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _actionBar(BuildContext context, BibleChapter c, bool aiEnabled) {
    final p = context.sancta;
    return Material(
      color: p.surface,
      elevation: 12,
      shadowColor: BrandColors.tinta.withValues(alpha: .25),
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(SanctaRadius.sheet))),
      // Edge-to-edge: o Scaffold tira o recuo inferior do `bottomSheet` (um SafeArea aqui dentro não enxerga a
      // barra de navegação) — o recuo vem do contexto da tela, acima do Scaffold.
      child: SafeArea(
        top: false,
        bottom: false,
        child: Padding(
          padding: EdgeInsets.fromLTRB(16, 14, 8, 6 + MediaQuery.paddingOf(context).bottom),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Expanded(child: Text(_reference(c), style: context.text.titleMedium)),
                  for (final entry in SanctaPalette.highlights.entries)
                    Semantics(
                      button: true,
                      label: 'Destacar em ${_highlightNames[entry.key] ?? entry.key}',
                      excludeSemantics: true,
                      child: InkResponse(
                        onTap: () => _annotate(c, {'highlight': entry.key}),
                        radius: 22,
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 3, vertical: 10),
                          child: Container(
                            width: 28,
                            height: 28,
                            decoration: BoxDecoration(
                              color: entry.value.withAlpha(255),
                              shape: BoxShape.circle,
                              border: Border.all(color: p.outline, width: 2),
                            ),
                          ),
                        ),
                      ),
                    ),
                  IconButton(
                    tooltip: 'Remover destaque',
                    icon: const Icon(Icons.format_color_reset_rounded, size: 22),
                    onPressed: () => _annotate(c, {'highlight': null}),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    _action(Icons.sticky_note_2_outlined, 'Nota', () => _note(c)),
                    _action(Icons.bookmark_add_outlined, 'Marcar', () => _annotate(c, {'bookmarked': true})),
                    _action(Icons.copy_rounded, 'Copiar', () async {
                      await Clipboard.setData(ClipboardData(text: '“${_selectedText(c)}” (${_reference(c)})'));
                      if (context.mounted) showMessage(context, 'Versículo copiado');
                      setState(_selected.clear);
                    }),
                    _action(Icons.ios_share_rounded, 'Compartilhar', () => shareText('“${_selectedText(c)}”\n— ${_reference(c)}\n\nLido no app Sancta')),
                    if (aiEnabled) _action(Icons.auto_awesome_rounded, 'Explicar', () => _explain(c), premium: true),
                    _action(Icons.close_rounded, 'Cancelar', () => setState(_selected.clear)),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _action(IconData icon, String label, VoidCallback onTap, {bool premium = false}) => Padding(
    padding: const EdgeInsets.only(right: 2),
    child: TextButton(
      onPressed: onTap,
      style: TextButton.styleFrom(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(SanctaRadius.tile)),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              IconBadge(icon, size: 40),
              if (premium) const Positioned(right: -14, top: -6, child: PremiumBadge(compact: true, label: '+')),
            ],
          ),
          const SizedBox(height: 4),
          Text(label, style: context.text.labelMedium?.copyWith(color: context.sancta.text)),
        ],
      ),
    ),
  );
}

class _ExplanationSheet extends ConsumerStatefulWidget {
  const _ExplanationSheet({required this.verseIds, required this.reference});

  final List<int> verseIds;
  final String reference;

  @override
  ConsumerState<_ExplanationSheet> createState() => _ExplanationSheetState();
}

class _ExplanationSheetState extends ConsumerState<_ExplanationSheet> {
  late final Future<Map<String, dynamic>> _future = ref
      .read(apiClientProvider)
      .post('/bible/explain', data: {'verse_ids': widget.verseIds})
      .then((d) => Map<String, dynamic>.from(d as Map));

  @override
  Widget build(BuildContext context) => SafeArea(
    child: ConstrainedBox(
      constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .75),
      child: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            final e = snapshot.error;
            return Padding(padding: const EdgeInsets.all(24), child: Text(e is ApiException ? e.message : 'Não foi possível gerar a explicação.'));
          }
          if (!snapshot.hasData) return const SizedBox(height: 220, child: LoadingView(message: 'Meditando a Palavra…'));
          final data = snapshot.data!;
          return ListView(
            shrinkWrap: true,
            padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
            children: [
              Row(
                children: [
                  const IconBadge(Icons.auto_awesome_rounded, size: 36),
                  const SizedBox(width: 12),
                  Expanded(child: Text(widget.reference, style: context.text.titleLarge)),
                ],
              ),
              const SizedBox(height: 16),
              Text('${data['explanation']}', style: AppTheme.reading(context, size: 19)),
              const SizedBox(height: 16),
              Text('${data['disclaimer'] ?? ''}', style: context.text.bodySmall),
            ],
          );
        },
      ),
    ),
  );
}

class _ChapterSkeleton extends StatelessWidget {
  const _ChapterSkeleton();

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.fromLTRB(22, 4, 22, 24),
    children: [
      const SanctaSkeleton(width: 80, height: 14),
      const SizedBox(height: 8),
      const SanctaSkeleton(width: 180, height: 26),
      const SizedBox(height: 24),
      for (var i = 0; i < 8; i++) ...[SanctaSkeleton(width: i.isEven ? null : 260, height: 16), const SizedBox(height: 12)],
    ],
  );
}
