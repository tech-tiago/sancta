import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import '../providers.dart';
import 'session_controller.dart';

/// Provedores de leitura da API. Recarregam quando a sessão muda
/// (ex.: ao assinar o Sancta+, o conteúdo bloqueado é liberado).

/// Itens de uma resposta de lista: array puro (`/plans`, `/journeys`…) ou paginado/envelopado (`{data: […]}`).
List<Map<String, dynamic>> apiItems(dynamic data) {
  final list = data is Map ? data['data'] : data;
  return list is List ? list.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : const [];
}

/// Chave que muda quando o usuário/assinatura muda.
final _sessionKeyProvider = Provider<String>((ref) {
  final s = ref.watch(sessionProvider);
  return '${s.user?.id ?? 'guest'}:${s.isPremium}';
});

final homeProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  ref.watch(_sessionKeyProvider);
  final data = await ref.read(apiClientProvider).get('/home', cacheKey: 'home');
  return Map<String, dynamic>.from(data as Map);
});

final categoriesProvider = FutureProvider<List<Category>>((ref) async {
  final data = await ref.read(apiClientProvider).get('/categories', cacheKey: 'categories');
  return apiItems(data).map(Category.fromJson).toList();
});

final prayersProvider = FutureProvider.autoDispose.family<List<PrayerItem>, String?>((ref, category) async {
  ref.watch(_sessionKeyProvider);
  final data = await ref.read(apiClientProvider).get('/prayers', query: {'category': ?category}, cacheKey: 'prayers:$category');
  return apiItems(data).map(PrayerItem.fromJson).toList();
});

final prayerProvider = FutureProvider.autoDispose.family<PrayerItem, String>((ref, slug) async {
  ref.watch(_sessionKeyProvider);
  final data = await ref.read(apiClientProvider).get('/prayers/$slug', cacheKey: 'prayer:$slug');
  return PrayerItem.fromJson(Map<String, dynamic>.from(data as Map));
});

final journeysProvider = FutureProvider.autoDispose<List<Journey>>((ref) async {
  ref.watch(_sessionKeyProvider);
  final data = await ref.read(apiClientProvider).get('/journeys', cacheKey: 'journeys');
  return apiItems(data).map(Journey.fromJson).toList();
});

final journeyProvider = FutureProvider.autoDispose.family<Journey, String>((ref, slug) async {
  ref.watch(_sessionKeyProvider);
  final data = await ref.read(apiClientProvider).get('/journeys/$slug', cacheKey: 'journey:$slug');
  return Journey.fromJson(Map<String, dynamic>.from(data as Map));
});

final bibleBooksProvider = FutureProvider<({String version, String? license, List<BibleBook> books})>((ref) async {
  final data = Map<String, dynamic>.from(await ref.read(apiClientProvider).get('/bible/books', cacheKey: 'bible:books') as Map);
  final version = data['version'] is Map ? Map<String, dynamic>.from(data['version'] as Map) : const <String, dynamic>{};
  return (
    version: '${version['name'] ?? 'Bíblia'}',
    license: version['license_notice']?.toString(),
    books: apiItems(data['books']).map(BibleBook.fromJson).toList(),
  );
});

final chapterProvider = FutureProvider.autoDispose.family<BibleChapter, (String, int)>((ref, args) async {
  ref.watch(_sessionKeyProvider);
  final (book, chapter) = args;
  final data = await ref.read(apiClientProvider).get('/bible/books/$book/chapters/$chapter', cacheKey: 'bible:$book:$chapter');
  return BibleChapter.fromJson(Map<String, dynamic>.from(data as Map));
});

final readingProgressProvider = FutureProvider.autoDispose<Map<String, dynamic>?>((ref) async {
  if (!ref.watch(sessionProvider).isAuthenticated) return null;
  final data = await ref.read(apiClientProvider).get('/me/bible/progress');
  return data is Map ? Map<String, dynamic>.from(data) : null;
});

final liturgyProvider = FutureProvider.autoDispose.family<Map<String, dynamic>, String>((ref, date) async {
  ref.watch(_sessionKeyProvider);
  final data = await ref.read(apiClientProvider).get('/liturgy', query: {'date': date}, cacheKey: 'liturgy:$date');
  return Map<String, dynamic>.from(data as Map);
});

final churchesProvider = FutureProvider.autoDispose.family<List<Church>, ({double? lat, double? lng, String? q})>((ref, args) async {
  final data = await ref
      .read(apiClientProvider)
      .get(
        '/churches',
        query: {
          if (args.lat != null) 'lat': args.lat,
          if (args.lng != null) 'lng': args.lng,
          if (args.lat != null) 'radius': 80,
          if (args.q != null && args.q!.isNotEmpty) 'q': args.q,
          'limit': 300,
        },
        cacheKey: args.lat == null && (args.q ?? '').isEmpty ? 'churches' : null,
      );
  return apiItems(data).map(Church.fromJson).toList();
});

final churchProvider = FutureProvider.autoDispose.family<Church, String>((ref, id) async {
  ref.watch(_sessionKeyProvider);
  final data = await ref.read(apiClientProvider).get('/churches/$id', cacheKey: 'church:$id');
  return Church.fromJson(Map<String, dynamic>.from(data as Map));
});

final newsProvider = FutureProvider.autoDispose<List<NewsItem>>((ref) async {
  final data = await ref.read(apiClientProvider).get('/news', cacheKey: 'news');
  return apiItems(data).map(NewsItem.fromJson).toList();
});

final newsDetailProvider = FutureProvider.autoDispose.family<NewsItem, int>((ref, id) async {
  final data = await ref.read(apiClientProvider).get('/news/$id');
  return NewsItem.fromJson(Map<String, dynamic>.from(data as Map));
});

final liveProvider = FutureProvider.autoDispose<List<LiveChannel>>((ref) async {
  final data = await ref.read(apiClientProvider).get('/live-channels', cacheKey: 'live');
  return apiItems(data).map(LiveChannel.fromJson).toList();
});

final intentionsProvider = FutureProvider.autoDispose<List<Intention>>((ref) async {
  ref.watch(_sessionKeyProvider);
  final data = await ref.read(apiClientProvider).get('/intentions');
  return apiItems(data).map(Intention.fromJson).toList();
});

final plansProvider = FutureProvider.autoDispose<List<PlanItem>>((ref) async {
  final data = await ref.read(apiClientProvider).get('/plans', cacheKey: 'plans');
  return apiItems(data).map(PlanItem.fromJson).toList();
});

final pageProvider = FutureProvider.autoDispose.family<Map<String, dynamic>, String>((ref, slug) async {
  final data = await ref.read(apiClientProvider).get('/pages/$slug', cacheKey: 'page:$slug');
  return Map<String, dynamic>.from(data as Map);
});

final faqsProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final data = await ref.read(apiClientProvider).get('/faqs', cacheKey: 'faqs');
  return apiItems(data);
});

final statsProvider = FutureProvider.autoDispose<Map<String, dynamic>?>((ref) async {
  if (!ref.watch(sessionProvider).isAuthenticated) return null;
  final data = await ref.read(apiClientProvider).get('/me/stats');
  return Map<String, dynamic>.from(data as Map);
});

final favoritePrayersProvider = FutureProvider.autoDispose<List<PrayerItem>>((ref) async {
  if (!ref.watch(sessionProvider).isAuthenticated) return const [];
  return apiItems(await ref.read(apiClientProvider).get('/me/favorite-prayers')).map(PrayerItem.fromJson).toList();
});

final favoriteChurchesProvider = FutureProvider.autoDispose<List<Church>>((ref) async {
  if (!ref.watch(sessionProvider).isAuthenticated) return const [];
  return apiItems(await ref.read(apiClientProvider).get('/me/favorite-churches')).map(Church.fromJson).toList();
});

final annotationsProvider = FutureProvider.autoDispose.family<List<Map<String, dynamic>>, String>((ref, type) async {
  if (!ref.watch(sessionProvider).isAuthenticated) return const [];
  return apiItems(await ref.read(apiClientProvider).get('/me/bible/annotations', query: {'type': type}));
});

/// Registra tempo de oração (sequência de dias) — usado por orações, Bíblia e liturgia.
Future<void> recordPrayerSession(WidgetRef ref, {required String type, int? contentId, required int seconds}) async {
  if (seconds < 10 || !ref.read(sessionProvider).isAuthenticated) return;
  try {
    await ref.read(apiClientProvider).post('/me/prayer-sessions', data: {'content_type': type, 'content_id': contentId, 'seconds': seconds.clamp(10, 14400)});
    await ref.read(sessionProvider.notifier).refreshUser();
  } catch (_) {}
}
