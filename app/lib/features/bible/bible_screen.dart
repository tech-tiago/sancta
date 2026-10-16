import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/brand/brand.dart';
import '../../core/brand/editorial_art.dart';
import '../../core/models/models.dart';
import '../../core/state/content_providers.dart';
import '../../core/state/session_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/reading_settings.dart';

/// Aba **Bíblia**: busca, continuar leitura, Antigo e Novo Testamento.
class BibleScreen extends ConsumerWidget {
  const BibleScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final books = ref.watch(bibleBooksProvider);
    final progress = ref.watch(readingProgressProvider).valueOrNull;
    final authenticated = ref.watch(sessionProvider).isAuthenticated;
    final p = context.sancta;

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        body: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ScreenTitle(
              'Bíblia',
              subtitle: books.valueOrNull?.version,
              actions: [
                IconButton(tooltip: 'Ajustes de leitura', icon: const Icon(Icons.text_fields_rounded), onPressed: () => showReadingSettings(context)),
                if (authenticated)
                  IconButton(tooltip: 'Minhas anotações', icon: const Icon(Icons.bookmarks_outlined), onPressed: () => context.push('/anotacoes')),
              ],
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
              child: _SearchButton(onTap: () => context.push('/biblia-busca')),
            ),
            if (progress != null && progress['book_slug'] != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                child: HeroCard(
                  padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
                  radius: SanctaRadius.card,
                  onTap: () => context.push('/biblia/${progress['book_slug']}/${progress['chapter']}'),
                  child: Row(
                    children: [
                      SizedBox(
                        width: 44,
                        child: ArchFrame(
                          aspectRatio: .82,
                          bottomRadius: 6,
                          child: Image.asset(EditorialArt.leituraBiblica, fit: BoxFit.cover, cacheWidth: 192),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Continuar leitura', style: context.text.labelMedium?.copyWith(color: p.onHeroSoft)),
                            Text('${progress['book']} ${progress['chapter']}', style: context.text.titleLarge?.copyWith(color: p.onHero)),
                          ],
                        ),
                      ),
                      const Icon(Icons.arrow_forward_rounded, color: BrandColors.pergaminho),
                    ],
                  ),
                ),
              ),
            if (progress == null || progress['book_slug'] == null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                child: HeroCard(
                  padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                  radius: SanctaRadius.card,
                  onTap: () => context.push('/biblia-busca'),
                  child: Row(
                    children: [
                      SizedBox(
                        width: 54,
                        child: ArchFrame(
                          aspectRatio: .82,
                          bottomRadius: 6,
                          child: Image.asset(EditorialArt.leituraBiblica, fit: BoxFit.cover, cacheWidth: 192),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Uma pausa com a Palavra', style: context.text.titleSmall?.copyWith(color: p.onHero)),
                            Text('Encontre um trecho para ler e meditar.', style: context.text.bodySmall?.copyWith(color: p.onHeroSoft)),
                          ],
                        ),
                      ),
                      const Icon(Icons.arrow_forward_rounded, color: BrandColors.pergaminho),
                    ],
                  ),
                ),
              ),
            const PillTabBar(
              margin: EdgeInsets.fromLTRB(16, 16, 16, 4),
              tabs: [
                Tab(height: 48, text: 'Antigo Testamento'),
                Tab(height: 48, text: 'Novo Testamento'),
              ],
            ),
            Expanded(
              child: books.when(
                loading: () => const SkeletonTiles(count: 8),
                error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(bibleBooksProvider)),
                data: (data) => TabBarView(
                  children: [
                    _BookList(books: data.books.where((b) => b.testament == 'AT').toList()),
                    _BookList(books: data.books.where((b) => b.testament == 'NT').toList(), footer: data.license),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SearchButton extends StatelessWidget {
  const _SearchButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return Semantics(
      button: true,
      label: 'Buscar na Bíblia',
      excludeSemantics: true,
      child: Material(
        color: p.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SanctaRadius.tile),
          side: BorderSide(color: p.isDark ? p.outline : const Color(0xFFE3DBCB)),
        ),
        child: InkWell(
          onTap: onTap,
          customBorder: RoundedRectangleBorder(borderRadius: BorderRadius.circular(SanctaRadius.tile)),
          child: SizedBox(
            height: 54,
            child: Row(
              children: [
                const SizedBox(width: 14),
                Icon(Icons.search_rounded, color: p.textSoft),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Palavra ou referência (Jo 3,16)',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: context.text.bodyLarge?.copyWith(color: p.textSoft),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _BookList extends StatelessWidget {
  const _BookList({required this.books, this.footer});

  final List<BibleBook> books;
  final String? footer;

  @override
  Widget build(BuildContext context) => ListView.builder(
    padding: const EdgeInsets.fromLTRB(8, 6, 8, 16),
    itemCount: books.length + (footer != null ? 1 : 0),
    itemBuilder: (context, i) {
      if (i == books.length) {
        return Padding(
          padding: const EdgeInsets.all(20),
          child: Text(footer!, style: context.text.bodySmall, textAlign: TextAlign.center),
        );
      }
      final b = books[i];
      final p = context.sancta;
      return SanctaTile(
        card: false,
        padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
        leading: IconBadge(
          null,
          child: Text(
            b.abbreviation,
            maxLines: 1,
            style: context.text.labelMedium?.copyWith(color: p.primary, fontWeight: FontWeight.w700, fontSize: 12.5),
          ),
        ),
        title: b.name,
        subtitle: '${b.chapters} ${b.chapters == 1 ? 'capítulo' : 'capítulos'}',
        onTap: () => b.chapters == 1 ? context.push('/biblia/${b.slug}/1') : _pickChapter(context, b),
      );
    },
  );

  void _pickChapter(BuildContext context, BibleBook book) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    builder: (sheet) => SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .72),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 0, 24, 4),
              child: Text(book.name, style: sheet.text.headlineSmall),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 0, 24, 14),
              child: Text('Escolha o capítulo', style: sheet.text.bodyMedium),
            ),
            Flexible(
              child: GridView.builder(
                shrinkWrap: true,
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
                gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(maxCrossAxisExtent: 64, mainAxisSpacing: 10, crossAxisSpacing: 10),
                itemCount: book.chapters,
                itemBuilder: (_, i) => Material(
                  color: sheet.sancta.surfaceAlt,
                  borderRadius: BorderRadius.circular(SanctaRadius.icon),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(SanctaRadius.icon),
                    onTap: () {
                      Navigator.pop(sheet);
                      context.push('/biblia/${book.slug}/${i + 1}');
                    },
                    child: Center(
                      child: Text('${i + 1}', semanticsLabel: 'Capítulo ${i + 1}', style: sheet.text.titleMedium),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

/// Converte "Jo 3,16", "1Cor 13" ou "Salmos 22" em livro/capítulo/versículo.
({String slug, int chapter, int? verse})? resolveReference(List<BibleBook> books, String query) {
  final match = RegExp(r'^\s*(\d?\s?[A-Za-zÀ-ú]+(?:\s+[A-Za-zÀ-ú]+)*)\.?\s+(\d{1,3})(?:\s*[,:]\s*(\d{1,3}))?').firstMatch(query);
  if (match == null) return null;

  String norm(String s) => s.toLowerCase().replaceAll(' ', '').replaceAll('.', '');
  final name = norm(match.group(1)!);
  final book = books.where((b) => norm(b.abbreviation) == name || norm(b.name) == name).firstOrNull;
  if (book == null) return null;

  final chapter = int.parse(match.group(2)!);
  if (chapter < 1 || chapter > book.chapters) return null;

  return (slug: book.slug, chapter: chapter, verse: int.tryParse(match.group(3) ?? ''));
}
