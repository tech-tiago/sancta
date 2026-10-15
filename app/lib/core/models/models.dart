/// Modelos tipados da API do Sancta (conversão defensiva: a API pode evoluir
/// sem quebrar versões antigas do app).
library;

String _s(dynamic v, [String fallback = '']) => v == null ? fallback : '$v';
String? _ns(dynamic v) => v == null || '$v'.isEmpty ? null : '$v';
int _i(dynamic v, [int fallback = 0]) => v is int ? v : (v is num ? v.toInt() : int.tryParse('$v') ?? fallback);
int? _ni(dynamic v) => v == null ? null : (v is num ? v.toInt() : int.tryParse('$v'));
DateTime? _date(dynamic v) => v == null ? null : DateTime.tryParse('$v');
double? _d(dynamic v) => v is num ? v.toDouble() : double.tryParse('$v');
bool _b(dynamic v) => v == true || v == 1 || v == '1';
List<Map<String, dynamic>> _list(dynamic v) => v is List ? v.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : const [];

/// Objeto JSON como mapa (cópia). O PHP serializa um array associativo VAZIO como `[]`: tratar como `{}`
/// em vez de quebrar (`as Map?` lançaria erro e derrubaria a tela).
Map<String, dynamic> _m(dynamic v) => v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};

/// Lista JSON (qualquer outra coisa vira lista vazia).
List<dynamic> _l(dynamic v) => v is List ? v : const [];

// ───────────────────────── Conta ─────────────────────────

class SubscriptionInfo {
  SubscriptionInfo.fromJson(Map<String, dynamic> j)
    : plan = _ns(j['plan']),
      status = _s(j['status']),
      statusLabel = _s(j['status_label']),
      provider = _s(j['provider']),
      autoRenew = _b(j['auto_renew']),
      endsAt = DateTime.tryParse(_s(j['ends_at'])),
      manageUrl = _ns(j['manage_url']);

  final String? plan;
  final String status;
  final String statusLabel;
  final String provider;
  final bool autoRenew;
  final DateTime? endsAt;
  final String? manageUrl;
}

class AppUser {
  AppUser.fromJson(Map<String, dynamic> j)
    : id = _s(j['id']),
      name = _s(j['name']),
      firstName = _s(j['first_name'], _s(j['name'])),
      email = _s(j['email']),
      emailVerified = _b(j['email_verified']),
      avatarUrl = _ns(j['avatar_url']),
      hasPassword = _b(j['has_password']),
      theme = _s(j['theme'], 'system'),
      isPremium = _b(j['is_premium']),
      subscription = j['subscription'] is Map ? SubscriptionInfo.fromJson(Map<String, dynamic>.from(j['subscription'] as Map)) : null,
      currentStreak = _i(_m(j['stats'])['current_streak']),
      longestStreak = _i(_m(j['stats'])['longest_streak']),
      totalMinutes = _i(_m(j['stats'])['total_prayer_minutes']),
      notificationPreferences = _m(j['notification_preferences']);

  final String id;
  final String name;
  final String firstName;
  final String email;
  final bool emailVerified;
  final String? avatarUrl;
  final bool hasPassword;
  final String theme;
  final bool isPremium;
  final SubscriptionInfo? subscription;
  final int currentStreak;
  final int longestStreak;
  final int totalMinutes;
  final Map<String, dynamic> notificationPreferences;
}

// ───────────────────────── Configuração remota ─────────────────────────

class AppConfig {
  AppConfig.fromJson(Map<String, dynamic> j)
    : name = _s(_m(j['app'])['name'], 'Sancta'),
      tagline = _s(_m(j['app'])['tagline'], 'Ora · Lê · Vive'),
      slogan = _s(_m(j['app'])['slogan'], 'Sua jornada de fé, todos os dias.'),
      minVersion = _s(_m(j['app'])['min_version'], '0.0.0'),
      latestVersion = _s(_m(j['app'])['latest_version'], '0.0.0'),
      updateMessage = _s(_m(j['app'])['update_message']),
      maintenance = _b(_m(j['app'])['maintenance']),
      maintenanceMessage = _s(_m(j['app'])['maintenance_message']),
      playStoreUrl = _s(_m(j['app'])['play_store_url']),
      supportEmail = _s(_m(j['app'])['support_email']),
      supportWhatsapp = _ns(_m(j['app'])['support_whatsapp']),
      websiteUrl = _s(_m(j['app'])['website_url']),
      privacyUrl = _s(_m(j['app'])['privacy_url']),
      termsUrl = _s(_m(j['app'])['terms_url']),
      deleteAccountUrl = _s(_m(j['app'])['delete_account_url']),
      features = _m(j['features']),
      defaultTheme = _s(_m(j['theme'])['default'], 'system'),
      premiumName = _s(_m(j['premium'])['name'], 'Sancta+'),
      premiumHeadline = _s(_m(j['premium'])['headline']),
      premiumBenefits = _l(_m(j['premium'])['benefits']).map((e) => '$e').toList(),
      paywall = PaywallContent.fromJson(_m(j['premium'])),
      obfuscatedAccountId = _ns(_m(j['billing'])['obfuscated_account_id']),
      productIds = _l(_m(j['billing'])['product_ids']).map((e) => '$e').where((e) => e.isNotEmpty).toList(),
      googleServerClientId = _ns(_m(j['auth'])['google_server_client_id']),
      tilesLight = _s(_m(j['maps'])['tiles_light'], 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
      tilesDark = _s(_m(j['maps'])['tiles_dark'], 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
      mapDarkFilter = _m(j['maps'])['dark_filter'] != false,
      mapAttribution = _s(_m(j['maps'])['attribution'], '© OpenStreetMap');

  AppConfig.fallback() : this.fromJson(const {});

  final String name;
  final String tagline;
  final String slogan;
  final String minVersion;
  final String latestVersion;
  final String updateMessage;
  final bool maintenance;
  final String maintenanceMessage;
  final String playStoreUrl;
  final String supportEmail;
  final String? supportWhatsapp;
  final String websiteUrl;
  final String privacyUrl;
  final String termsUrl;
  final String deleteAccountUrl;
  final Map<String, dynamic> features;
  final String defaultTheme;
  final String premiumName;
  final String premiumHeadline;
  final List<String> premiumBenefits;

  /// Textos do paywall editáveis no painel (bloco `premium` de /config).
  final PaywallContent paywall;
  final String? obfuscatedAccountId;

  /// IDs das assinaturas do Google Play (cadastrados nos planos do painel).
  final List<String> productIds;

  /// Client ID Web do Google para o login (painel → Integrações e segurança).
  final String? googleServerClientId;
  final String tilesLight;
  final String tilesDark;
  final bool mapDarkFilter;
  final String mapAttribution;

  bool feature(String key) => features[key] != false;
}

/// Benefício do Sancta+ com ícone (chave da API: headphones, flame, sun, sparkles, book, calendar,
/// heart, cross, chalice, play, moon, users, shield, star — desconhecida vira `star`).
class PremiumBenefit {
  PremiumBenefit.fromJson(Map<String, dynamic> j)
    : icon = _knownIcons.contains(j['icon']) ? '${j['icon']}' : 'star',
      title = _s(j['title']),
      description = _ns(j['description']);

  const PremiumBenefit(this.title, {this.icon = 'star', this.description});

  static const _knownIcons = {
    'headphones',
    'flame',
    'sun',
    'sparkles',
    'book',
    'calendar',
    'heart',
    'cross',
    'chalice',
    'play',
    'moon',
    'users',
    'shield',
    'star',
  };

  final String icon;
  final String title;
  final String? description;
}

class PremiumFaq {
  PremiumFaq.fromJson(Map<String, dynamic> j) : question = _s(j['question']), answer = _s(j['answer']);

  final String question;
  final String answer;
}

class Testimonial {
  Testimonial.fromJson(Map<String, dynamic> j) : author = _s(j['author'], 'Assinante'), content = _s(j['content']), rating = _i(j['rating'], 5).clamp(1, 5);

  final String author;
  final String content;
  final int rating;
}

/// Conteúdo do paywall (Painel → Configurações → Sancta+). Tolerante a servidores antigos:
/// sem `benefit_items`, usa a lista simples `benefits`.
class PaywallContent {
  PaywallContent.fromJson(Map<String, dynamic> j)
    : headline = _s(j['headline'], 'Aprofunde sua vida de oração'),
      subheadline = _ns(j['subheadline']),
      benefits = _benefits(j),
      socialProof = _ns(j['social_proof']),
      guarantee = _ns(j['guarantee']) ?? 'Cancele quando quiser',
      faq = _list(j['faq']).map(PremiumFaq.fromJson).where((f) => f.question.isNotEmpty && f.answer.isNotEmpty).toList(),
      testimonials = _list(j['testimonials']).map(Testimonial.fromJson).where((t) => t.content.isNotEmpty).toList();

  static List<PremiumBenefit> _benefits(Map<String, dynamic> j) {
    final items = _list(j['benefit_items']).map(PremiumBenefit.fromJson).where((b) => b.title.isNotEmpty).toList();
    if (items.isNotEmpty) return items;
    return _l(j['benefits']).map((e) => '$e').where((e) => e.isNotEmpty).map(PremiumBenefit.new).toList();
  }

  final String headline;
  final String? subheadline;
  final List<PremiumBenefit> benefits;
  final String? socialProof;
  final String guarantee;
  final List<PremiumFaq> faq;
  final List<Testimonial> testimonials;
}

// ───────────────────────── Conteúdo em blocos ─────────────────────────

/// Bloco de conteúdo renderizável (orações litúrgicas, páginas, notícias).
class ContentBlock {
  ContentBlock.fromJson(Map<String, dynamic> j)
    : type = _s(j['type'], 'paragraph'),
      text = _s(j['text']),
      title = _ns(j['title']),
      speaker = _ns(j['speaker']),
      highlight = _b(j['highlight']),
      level = _i(j['level'], 2),
      items = _l(j['items']).map((e) => '$e').toList(),
      icons = _l(j['icons']).map((e) => '$e').toList(),
      src = _ns(j['src']),
      children = _list(j['children']).map(ContentBlock.fromJson).toList();

  final String type;
  final String text;
  final String? title;
  final String? speaker;
  final bool highlight;
  final int level;
  final List<String> items;
  final List<String> icons;
  final String? src;
  final List<ContentBlock> children;

  static List<ContentBlock> listFrom(dynamic v) => _list(v).map(ContentBlock.fromJson).toList();
}

// ───────────────────────── Orações e jornadas ─────────────────────────

class PrayerItem {
  PrayerItem.fromJson(Map<String, dynamic> j)
    : id = _i(j['id']),
      slug = _s(j['slug']),
      title = _s(j['title']),
      subtitle = _ns(j['subtitle']),
      categoryName = _ns(_m(j['category'])['name']),
      categoryColor = _ns(_m(j['category'])['color']),
      coverUrl = _ns(j['cover_url']),
      durationMinutes = j['duration_minutes'] == null ? null : _i(j['duration_minutes']),
      hasAudio = _b(j['has_audio']),
      isPremium = _b(j['is_premium']),
      locked = _b(j['locked']),
      description = _ns(j['description']),
      author = _ns(j['author']),
      audioUrl = _ns(j['audio_url']),
      blocks = ContentBlock.listFrom(j['blocks']),
      isFavorite = _b(j['is_favorite']);

  final int id;
  final String slug;
  final String title;
  final String? subtitle;
  final String? categoryName;
  final String? categoryColor;
  final String? coverUrl;
  final int? durationMinutes;
  final bool hasAudio;
  final bool isPremium;
  final bool locked;
  final String? description;
  final String? author;
  final String? audioUrl;
  final List<ContentBlock> blocks;
  final bool isFavorite;
}

/// Oração de cada período do dia (`/home` → `period_prayers`): escolhida no painel ou pela heurística do
/// servidor. Campo ausente (servidor antigo) ou `null` = o app usa a própria heurística.
class PeriodPrayers {
  PeriodPrayers.fromJson(dynamic v) : morning = _prayer(v, 'morning'), afternoon = _prayer(v, 'afternoon'), night = _prayer(v, 'night');

  static PrayerItem? _prayer(dynamic v, String key) {
    final item = v is Map ? v[key] : null;
    if (item is! Map) return null;
    final prayer = PrayerItem.fromJson(Map<String, dynamic>.from(item));
    return prayer.slug.isEmpty || prayer.title.isEmpty ? null : prayer;
  }

  /// 05h–11h59.
  final PrayerItem? morning;

  /// 12h–17h59.
  final PrayerItem? afternoon;

  /// 18h–04h59.
  final PrayerItem? night;
}

class Category {
  Category.fromJson(Map<String, dynamic> j)
    : slug = _s(j['slug']),
      name = _s(j['name']),
      description = _ns(j['description']),
      icon = _ns(j['icon']),
      color = _ns(j['color']),
      count = _i(j['prayers_count']);

  final String slug;
  final String name;
  final String? description;
  final String? icon;
  final String? color;
  final int count;
}

class JourneyStep {
  JourneyStep.fromJson(Map<String, dynamic> j)
    : id = _i(j['id']),
      position = _i(j['position']),
      title = _s(j['title']),
      bibleReference = _ns(j['bible_reference']),
      durationMinutes = j['duration_minutes'] == null ? null : _i(j['duration_minutes']),
      completed = _b(j['completed']),
      blocks = ContentBlock.listFrom(j['blocks']),
      prayerSlug = _ns(j['prayer_slug']),
      audioUrl = _ns(j['audio_url']),
      locked = _b(j['locked']);

  final int id;
  final int position;
  final String title;
  final String? bibleReference;
  final int? durationMinutes;
  final bool completed;
  final List<ContentBlock> blocks;
  final String? prayerSlug;
  final String? audioUrl;
  final bool locked;
}

class Journey {
  Journey.fromJson(Map<String, dynamic> j)
    : id = _i(j['id']),
      slug = _s(j['slug']),
      title = _s(j['title']),
      type = _s(j['type']),
      typeLabel = _s(j['type_label']),
      subtitle = _ns(j['subtitle']),
      coverUrl = _ns(j['cover_url']),
      color = _ns(j['color']),
      isPremium = _b(j['is_premium']),
      locked = _b(j['locked']),
      stepsCount = _i(j['steps_count']),
      completedSteps = _i(j['completed_steps']),
      description = _ns(j['description']),
      steps = _list(j['steps']).map(JourneyStep.fromJson).toList();

  final int id;
  final String slug;
  final String title;
  final String type;
  final String typeLabel;
  final String? subtitle;
  final String? coverUrl;
  final String? color;
  final bool isPremium;
  final bool locked;
  final int stepsCount;
  final int completedSteps;
  final String? description;
  final List<JourneyStep> steps;

  double get progress => stepsCount == 0 ? 0 : completedSteps / stepsCount;
}

// ───────────────────────── Bíblia ─────────────────────────

class BibleBook {
  BibleBook.fromJson(Map<String, dynamic> j)
    : testament = _s(j['testament']),
      position = _i(j['position']),
      name = _s(j['name']),
      abbreviation = _s(j['abbreviation']),
      slug = _s(j['slug']),
      chapters = _i(j['chapters_count'], 1);

  final String testament;
  final int position;
  final String name;
  final String abbreviation;
  final String slug;
  final int chapters;
}

class BibleVerse {
  BibleVerse.fromJson(Map<String, dynamic> j)
    : id = _i(j['id']),
      number = _i(j['number']),
      text = _s(j['text']),
      highlight = _ns(j['highlight']),
      note = _ns(j['note']),
      bookmarked = _b(j['bookmarked']);

  final int id;
  final int number;
  final String text;
  final String? highlight;
  final String? note;
  final bool bookmarked;
}

class BibleChapter {
  BibleChapter.fromJson(Map<String, dynamic> j)
    : bookName = _s(_m(j['book'])['name']),
      bookSlug = _s(_m(j['book'])['slug']),
      abbreviation = _s(_m(j['book'])['abbreviation']),
      chapter = _i(j['chapter'], 1),
      chaptersCount = _i(_m(j['book'])['chapters_count'], 1),
      previous = j['previous'] is Map ? (book: _s((j['previous'] as Map)['book']), chapter: _i((j['previous'] as Map)['chapter'])) : null,
      next = j['next'] is Map ? (book: _s((j['next'] as Map)['book']), chapter: _i((j['next'] as Map)['chapter'])) : null,
      verses = _list(j['verses']).map(BibleVerse.fromJson).toList();

  final String bookName;
  final String bookSlug;
  final String abbreviation;
  final int chapter;
  final int chaptersCount;
  final ({String book, int chapter})? previous;
  final ({String book, int chapter})? next;
  final List<BibleVerse> verses;
}

// ───────────────────────── Igrejas ─────────────────────────

class ScheduleItem {
  ScheduleItem.fromJson(Map<String, dynamic> j)
    : weekday = j['weekday'] == null ? null : _i(j['weekday']),
      weekdayLabel = _s(j['weekday_label']),
      startsAt = _ns(j['starts_at']),
      endsAt = _ns(j['ends_at']),
      notes = _ns(j['notes']),
      label = _s(j['label']);

  final int? weekday;
  final String weekdayLabel;
  final String? startsAt;
  final String? endsAt;
  final String? notes;
  final String label;
}

class Church {
  Church.fromJson(Map<String, dynamic> j)
    : id = _s(j['id']),
      slug = _s(j['slug']),
      name = _s(j['name']),
      type = _s(j['type']),
      typeLabel = _s(j['type_label']),
      city = _ns(j['city']),
      state = _ns(j['state']),
      address = _s(j['address']),
      latitude = _d(j['latitude']),
      longitude = _d(j['longitude']),
      isVerified = _b(j['is_verified']),
      coverUrl = _ns(j['cover_url']),
      distanceKm = _d(j['distance_km']),
      nextMass = _ns(_m(j['next_mass'])['label']),
      description = _ns(j['description']),
      diocese = _ns(j['diocese']),
      contacts = _m(j['contacts']),
      mapsUrl = _ns(j['maps_url']),
      schedules = _m(j['schedules']).map((key, value) => MapEntry(key, _list(value).map(ScheduleItem.fromJson).toList())),
      isFavorite = _b(j['is_favorite']);

  final String id;
  final String slug;
  final String name;
  final String type;
  final String typeLabel;
  final String? city;
  final String? state;
  final String address;
  final double? latitude;
  final double? longitude;
  final bool isVerified;
  final String? coverUrl;
  final double? distanceKm;
  final String? nextMass;
  final String? description;
  final String? diocese;
  final Map<String, dynamic> contacts;
  final String? mapsUrl;
  final Map<String, List<ScheduleItem>> schedules;
  final bool isFavorite;

  bool get hasLocation => latitude != null && longitude != null;
}

// ───────────────────────── Assinatura ─────────────────────────

/// Plano do Sancta+ (`GET /plans`). Equivalente mensal e % de economia vêm calculados do servidor.
class PlanItem {
  PlanItem.fromJson(Map<String, dynamic> j)
    : slug = _s(j['slug']),
      name = _s(j['name']),
      description = _ns(j['description']),
      interval = _s(j['interval']),
      intervalCount = _i(j['interval_count'], 1),
      periodLabel = _s(j['period_label']),
      priceCents = _ni(j['price_cents']),
      priceFormatted = _s(j['price_formatted']),
      compareAtCents = _ni(j['compare_at_price_cents']),
      compareAtFormatted = _ns(j['compare_at_price_formatted']),
      monthlyEquivalentCents = _ni(j['monthly_equivalent_cents']),
      monthlyEquivalent = _ns(j['monthly_equivalent_formatted']),
      savingsPercent = (_ni(j['savings_percent']) ?? 0) > 0 ? _ni(j['savings_percent']) : null,
      trialDays = _i(j['trial_days']).clamp(0, 365),
      badge = _ns(j['badge']),
      isFeatured = _b(j['is_featured']),
      features = _l(j['features']).map((e) => '$e').toList(),
      playProductId = _ns(_m(j['google_play'])['product_id']),
      playBasePlanId = _ns(_m(j['google_play'])['base_plan_id']);

  final String slug;
  final String name;
  final String? description;

  /// `month`, `year`… (vazio em servidores antigos).
  final String interval;
  final int intervalCount;
  final String periodLabel;
  final int? priceCents;
  final String priceFormatted;
  final int? compareAtCents;
  final String? compareAtFormatted;
  final int? monthlyEquivalentCents;
  final String? monthlyEquivalent;

  /// Economia em relação ao plano mensal (`null` quando não há economia).
  final int? savingsPercent;
  final int trialDays;
  final String? badge;
  final bool isFeatured;
  final List<String> features;
  final String? playProductId;
  final String? playBasePlanId;

  bool get isMonthly => (interval == 'month' && intervalCount == 1) || periodLabel == 'por mês';

  bool get hasTrial => trialDays > 0;

  /// Mostra "R$ X/mês" só quando ajuda a comparar (planos maiores que um mês).
  bool get showMonthlyEquivalent => monthlyEquivalent != null && !isMonthly;
}

// ───────────────────────── Avisos ─────────────────────────

class Announcement {
  Announcement.fromJson(Map<String, dynamic> j)
    : id = _i(j['id']),
      title = _s(j['title']),
      body = _ns(j['body']),
      imageUrl = _ns(j['image_url']),
      kind = _s(j['kind'], 'popup'),
      category = _s(j['category'], 'notice'),
      ctaLabel = _ns(j['cta_label']),
      ctaUrl = _ns(j['cta_url']),
      frequency = _s(j['frequency'], 'once'),
      dismissible = j['dismissible'] != false,
      updatedAt = _s(j['updated_at']);

  final int id;
  final String title;
  final String? body;
  final String? imageUrl;
  final String kind;
  final String category;
  final String? ctaLabel;
  final String? ctaUrl;
  final String frequency;
  final bool dismissible;
  final String updatedAt;
}

// ───────────────────────── Notícias / mídia ─────────────────────────

class NewsItem {
  NewsItem.fromJson(Map<String, dynamic> j)
    : id = _i(j['id']),
      title = _s(j['title']),
      summary = _ns(j['summary']),
      imageUrl = _ns(j['image_url']),
      source = _s(j['source'], 'Sancta'),
      url = _ns(j['url']),
      publishedAt = DateTime.tryParse(_s(j['published_at'])),
      blocks = ContentBlock.listFrom(j['blocks']),
      hasFullContent = _b(j['has_full_content']);

  final int id;
  final String title;
  final String? summary;
  final String? imageUrl;
  final String source;
  final String? url;
  final DateTime? publishedAt;
  final List<ContentBlock> blocks;
  final bool hasFullContent;
}

// ───────────────────────── Ao vivo ─────────────────────────

/// Rótulos das categorias de canal (usados quando o servidor não envia `category_label`).
const liveCategoryLabels = {'missa': 'Missa', 'terco': 'Terço', 'adoracao': 'Adoração', 'oracao': 'Oração', 'canal': 'Canal'};

/// Transmissão ao vivo detectada pelo servidor (`live`).
class LiveVideo {
  LiveVideo.fromJson(Map<String, dynamic> j)
    : videoId = _ns(j['video_id']),
      title = _ns(j['title']),
      startedAt = _date(j['started_at']),
      thumbnailUrl = _ns(j['thumbnail_url']);

  final String? videoId;
  final String? title;
  final DateTime? startedAt;
  final String? thumbnailUrl;
}

/// Transmissão agendada no YouTube (`upcoming`).
class LiveUpcoming {
  LiveUpcoming.fromJson(Map<String, dynamic> j) : videoId = _ns(j['video_id']), title = _ns(j['title']), scheduledAt = _date(j['scheduled_at']);

  final String? videoId;
  final String? title;
  final DateTime? scheduledAt;
}

/// Item da agenda do canal: `weekday` 0 = domingo … 6 = sábado, `null` = todos os dias; `time` "HH:MM" (Brasília).
class LiveScheduleItem {
  LiveScheduleItem.fromJson(Map<String, dynamic> j)
    : weekday = j['weekday'] == null ? null : _i(j['weekday']).clamp(0, 6),
      time = _s(j['time']),
      durationMinutes = _ni(j['duration_minutes']),
      title = _ns(j['title']);

  final int? weekday;
  final String time;
  final int? durationMinutes;
  final String? title;

  /// Minutos desde a meia-noite (para ordenar); horário inválido vai para o fim.
  int get minutes {
    final parts = time.split(':');
    final h = parts.isNotEmpty ? int.tryParse(parts[0]) : null;
    final m = parts.length > 1 ? int.tryParse(parts[1]) : 0;
    return h == null ? 24 * 60 : h * 60 + (m ?? 0);
  }

  /// Acontece em [day]? (`DateTime.weekday` usa 1 = segunda … 7 = domingo).
  bool occursOn(DateTime day) => weekday == null || weekday == day.weekday % 7;
}

/// Canal ao vivo (`GET /live-channels`), já ordenado pelo servidor: ao vivo → no horário → ordem do painel.
class LiveChannel {
  LiveChannel.fromJson(Map<String, dynamic> j)
    : id = _i(j['id']),
      name = _s(j['name']),
      description = _ns(j['description']),
      category = _s(j['category'], 'canal'),
      categoryLabel = _ns(j['category_label']) ?? liveCategoryLabels[_s(j['category'], 'canal')] ?? 'Canal',
      coverUrl = _ns(j['cover_url']),
      logoUrl = _ns(j['logo_url']),
      youtubeUrl = _ns(j['youtube_url']),
      watchUrl = _ns(j['watch_url']),
      isLive = _b(j['is_live']),
      live = j['live'] is Map ? LiveVideo.fromJson(Map<String, dynamic>.from(j['live'] as Map)) : null,
      upcoming = j['upcoming'] is Map ? LiveUpcoming.fromJson(Map<String, dynamic>.from(j['upcoming'] as Map)) : null,
      onScheduleNow = _b(j['on_schedule_now']),
      currentScheduleTitle = _ns(j['current_schedule_title']),
      schedule = _list(j['schedule']).map(LiveScheduleItem.fromJson).toList(),
      nextAt = _date(j['next_at']),
      topic = _ns(j['topic']),
      type = _s(j['type']),
      url = _s(j['url']),
      fallbackUrl = _ns(j['fallback_url']),
      thumbnailUrl = _ns(j['thumbnail_url']);

  final int id;
  final String name;
  final String? description;

  /// `missa` · `terco` · `adoracao` · `oracao` · `canal`.
  final String category;
  final String categoryLabel;
  final String? coverUrl;
  final String? logoUrl;
  final String? youtubeUrl;

  /// Link a abrir: vídeo ao vivo quando houver, senão o canal.
  final String? watchUrl;
  final bool isLive;
  final LiveVideo? live;
  final LiveUpcoming? upcoming;

  /// Dentro de um horário da agenda (independe da detecção pelo YouTube).
  final bool onScheduleNow;
  final String? currentScheduleTitle;
  final List<LiveScheduleItem> schedule;

  /// Próximo início da agenda ainda não começado.
  final DateTime? nextAt;

  /// Tópico FCM do "Me avise" (`sancta_live_<id>`).
  final String? topic;

  // Compatibilidade com servidores antigos (app 3.0).
  final String type;
  final String url;
  final String? fallbackUrl;
  final String? thumbnailUrl;

  /// Ao vivo detectado ou no horário da agenda.
  bool get isOnAir => isLive || onScheduleNow;

  /// Link para abrir no YouTube.
  String? get openUrl => watchUrl ?? (url.isNotEmpty ? url : youtubeUrl);

  /// Melhor imagem: miniatura da live → capa → miniatura → logo.
  String? get imageUrl => live?.thumbnailUrl ?? coverUrl ?? thumbnailUrl ?? logoUrl;

  /// Título do que está passando agora (live detectada ou item da agenda).
  String? get nowTitle => live?.title ?? currentScheduleTitle;
}

/// Destaque "Ao vivo agora" da tela Hoje (`/home` → `live_now`).
class LiveNow {
  LiveNow.fromJson(Map<String, dynamic> j)
    : id = _i(j['id']),
      name = _s(j['name']),
      category = _s(j['category'], 'canal'),
      isLive = _b(j['is_live']),
      onScheduleNow = _b(j['on_schedule_now']),
      title = _ns(j['title']),
      startedAt = _date(j['started_at']),
      watchUrl = _ns(j['watch_url']),
      thumbnailUrl = _ns(j['thumbnail_url']),
      logoUrl = _ns(j['logo_url']),
      topic = _ns(j['topic']);

  final int id;
  final String name;
  final String category;
  final bool isLive;
  final bool onScheduleNow;
  final String? title;
  final DateTime? startedAt;
  final String? watchUrl;
  final String? thumbnailUrl;
  final String? logoUrl;
  final String? topic;

  static List<LiveNow> listFrom(dynamic v) => _list(v).map(LiveNow.fromJson).where((l) => l.name.isNotEmpty).toList();
}

class Intention {
  Intention.fromJson(Map<String, dynamic> j)
    : id = _s(j['id']),
      text = _s(j['text']),
      author = _s(j['author'], 'Anônimo'),
      prayersCount = _i(j['prayers_count']),
      prayed = _b(j['prayed']);

  final String id;
  final String text;
  final String author;
  final int prayersCount;
  final bool prayed;
}
