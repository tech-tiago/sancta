import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/providers.dart';
import '../../core/state/content_providers.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import 'bible_screen.dart';

/// Busca por palavras ou por referência ("Jo 3,16" abre direto o capítulo).
class BibleSearchScreen extends ConsumerStatefulWidget {
  const BibleSearchScreen({super.key});

  @override
  ConsumerState<BibleSearchScreen> createState() => _BibleSearchScreenState();
}

class _BibleSearchScreenState extends ConsumerState<BibleSearchScreen> {
  final _controller = TextEditingController();
  Future<List<Map<String, dynamic>>>? _results;
  bool _initialized = false;

  static const _suggestions = ['Jo 3,16', 'Sl 23', 'amor', 'paz', 'Mt 5'];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_initialized) return;
    _initialized = true;
    final q = GoRouterState.of(context).uri.queryParameters['q'];
    if (q != null && q.isNotEmpty) {
      _controller.text = q;
      WidgetsBinding.instance.addPostFrameCallback((_) => _search(q));
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _search(String query) async {
    final q = query.trim();
    if (q.length < 3 && !RegExp(r'\d').hasMatch(q)) return;

    final books = await ref.read(bibleBooksProvider.future);
    final ref0 = resolveReference(books.books, q);
    if (ref0 != null && mounted) {
      context.pushReplacement('/biblia/${ref0.slug}/${ref0.chapter}${ref0.verse != null ? '?v=${ref0.verse}' : ''}');
      return;
    }
    if (q.length < 3) return;

    setState(() {
      _results = ref
          .read(apiClientProvider)
          .get('/bible/search', query: {'q': q})
          .then((d) => ((d as Map)['results'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList());
    });
  }

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Padding(
          padding: const EdgeInsets.only(right: 16),
          child: TextField(
            controller: _controller,
            autofocus: true,
            textInputAction: TextInputAction.search,
            onSubmitted: _search,
            decoration: InputDecoration(
              hintText: 'Palavra ou referência',
              isDense: true,
              prefixIcon: const Icon(Icons.search_rounded),
              suffixIcon: IconButton(tooltip: 'Buscar', icon: const Icon(Icons.arrow_forward_rounded), onPressed: () => _search(_controller.text)),
            ),
          ),
        ),
      ),
      body: _results == null
          ? ListView(
              children: [
                const SizedBox(height: 24),
                const EmptyView(
                  icon: Icons.manage_search_rounded,
                  title: 'Busque na Bíblia',
                  message: 'Digite uma palavra (mínimo 3 letras) ou uma referência, como "Sl 22" ou "Jo 3,16".',
                ),
                Wrap(
                  alignment: WrapAlignment.center,
                  spacing: 8,
                  children: [
                    for (final s in _suggestions)
                      SanctaChoiceChip(
                        label: s,
                        selected: false,
                        onSelected: () {
                          _controller.text = s;
                          _search(s);
                        },
                      ),
                  ],
                ),
              ],
            )
          : FutureBuilder<List<Map<String, dynamic>>>(
              future: _results,
              builder: (context, snap) {
                if (snap.hasError) return ErrorView(error: snap.error!, onRetry: () => _search(_controller.text));
                if (!snap.hasData) return const SkeletonTiles(count: 6);
                final list = snap.data!;
                if (list.isEmpty) return const EmptyView(icon: Icons.search_off_rounded, title: 'Nenhum versículo encontrado', message: 'Tente outra palavra.');
                return ListView.separated(
                  padding: EdgeInsets.fromLTRB(16, 8, 16, 24 + MediaQuery.paddingOf(context).bottom),
                  itemCount: list.length + 1,
                  separatorBuilder: (_, _) => const SizedBox(height: 10),
                  itemBuilder: (_, i) {
                    if (i == 0) {
                      return Padding(
                        padding: const EdgeInsets.fromLTRB(4, 0, 4, 0),
                        child: Text('${list.length} ${list.length == 1 ? 'versículo' : 'versículos'}', style: context.text.labelMedium),
                      );
                    }
                    final r = list[i - 1];
                    return Material(
                      color: p.surface,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(SanctaRadius.tile),
                        side: p.isDark ? BorderSide.none : BorderSide(color: p.outline),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: InkWell(
                        onTap: () => context.push('/biblia/${r['book_slug']}/${r['chapter']}?v=${r['verse']}'),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('${r['reference']}', style: context.text.titleSmall?.copyWith(color: p.primary)),
                              const SizedBox(height: 6),
                              Text('${r['text']}', style: AppTheme.reading(context, size: 18)),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                );
              },
            ),
    );
  }
}
