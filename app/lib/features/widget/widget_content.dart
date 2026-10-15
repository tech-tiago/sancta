import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:intl/intl.dart';

import '../../core/utils/links.dart';

/// Período do dia usado pelo widget "Oração do dia" (o mesmo cálculo existe no Kotlin,
/// `OracaoDoDiaWidgetProvider`): manhã 05h–11h59 · tarde 12h–17h59 · noite 18h–04h59.
enum WidgetPeriod {
  manha('Oração da manhã'),
  tarde('Oração da tarde'),
  noite('Oração da noite');

  const WidgetPeriod(this.defaultTitle);

  /// Título padrão (sem dados de /home).
  final String defaultTitle;

  static WidgetPeriod of(DateTime time) {
    final h = time.hour;
    if (h >= 5 && h < 12) return manha;
    if (h >= 12 && h < 18) return tarde;
    return noite;
  }
}

/// O que o widget mostra num período: título, linha de apoio (duração) e o deep link aberto
/// pelo toque. Só conteúdo público (nada de nome, sequência ou dados da conta).
@immutable
class WidgetEntry {
  const WidgetEntry({required this.title, required this.subtitle, required this.link, this.daily = false});

  /// Sem dados: título do período, "Rezar agora" e abre a tela Hoje.
  WidgetEntry.fallback(WidgetPeriod period) : this(title: period.defaultTitle, subtitle: defaultSubtitle, link: 'sancta://hoje');

  static const defaultSubtitle = 'Rezar agora';

  final String title;
  final String subtitle;

  /// Deep link `sancta://` já validado por [deepLinkToRoute].
  final String link;

  /// A linha de apoio vale só no dia da sincronização (ex.: referência do Evangelho).
  final bool daily;

  Map<String, Object> toJson() => {'title': title, 'subtitle': subtitle, 'link': link, 'daily': daily};

  factory WidgetEntry.fromJson(Map<String, dynamic> j, WidgetPeriod period) {
    final link = '${j['link'] ?? ''}';
    if (deepLinkToRoute(link) == null) return WidgetEntry.fallback(period);
    return WidgetEntry(title: _clean(j['title'], period.defaultTitle), subtitle: _clean(j['subtitle'], defaultSubtitle), link: link, daily: j['daily'] == true);
  }

  @override
  bool operator ==(Object other) => other is WidgetEntry && other.title == title && other.subtitle == subtitle && other.link == link && other.daily == daily;

  @override
  int get hashCode => Object.hash(title, subtitle, link, daily);

  @override
  String toString() => 'WidgetEntry($title · $subtitle · $link)';
}

String _clean(Object? value, String fallback) {
  final text = '${value ?? ''}'.replaceAll(RegExp(r'\s+'), ' ').trim();
  if (text.isEmpty) return fallback;
  return text.length > 80 ? '${text.substring(0, 79)}…' : text;
}

/// Conteúdo gravado para o widget (JSON único em `HomeWidgetPreferences`, chave `sancta_widget`).
/// Guarda os três períodos de uma vez: o widget escolhe o do horário atual mesmo sem o app abrir.
@immutable
class WidgetContent {
  const WidgetContent({required this.date, required this.dateLabel, required this.periods});

  static const version = 1;
  static final _slug = RegExp(r'^[a-z0-9][a-z0-9-]{0,119}$');

  /// Dia da sincronização (AAAA-MM-DD, horário local).
  final String date;

  /// Ex.: "Domingo, 4 de outubro" (o widget recalcula para o dia atual).
  final String dateLabel;
  final Map<WidgetPeriod, WidgetEntry> periods;

  WidgetEntry entryFor(WidgetPeriod period) => periods[period] ?? WidgetEntry.fallback(period);

  /// "Domingo, 4 de outubro" (pt-BR, inicial maiúscula).
  static String formatDate(DateTime day) {
    final text = DateFormat("EEEE, d 'de' MMMM", 'pt_BR').format(day);
    return text.isEmpty ? text : text[0].toUpperCase() + text.substring(1);
  }

  /// Monta o conteúdo a partir do mapa bruto de `GET /home`:
  ///  - primeiro a escolha do servidor, `period_prayers` (morning/afternoon/night — painel → Configurações →
  ///    Aplicativo, ou a heurística do servidor);
  ///  - servidor antigo ou sem escolha: manhã/noite = a oração do período entre as orações em destaque
  ///    (slug/título com "manhã"/"noite"); tarde = Angelus/terço/oração da tarde, senão o Evangelho do dia;
  ///  - sem correspondência: a "oração do dia" (destaque gratuito que muda a cada dia);
  ///  - sem nada: o padrão do período (abre a tela Hoje).
  /// Orações bloqueadas (Sancta+) não entram: o toque abriria o paywall.
  factory WidgetContent.fromHome(Map<String, dynamic> home, DateTime now) {
    bool usable(Object? item) => item is Map && item['locked'] != true && _slug.hasMatch('${item['slug'] ?? ''}') && '${item['title'] ?? ''}'.trim().isNotEmpty;

    final prayers = <Map<String, dynamic>>[
      for (final item in (home['featured_prayers'] is List ? home['featured_prayers'] as List : const []))
        if (usable(item)) Map<String, dynamic>.from(item as Map),
    ];

    final server = home['period_prayers'] is Map ? home['period_prayers'] as Map : const {};
    Map<String, dynamic>? chosen(String key) => usable(server[key]) ? Map<String, dynamic>.from(server[key] as Map) : null;

    Map<String, dynamic>? find(List<String> words, Set<String> taken) {
      for (final p in prayers) {
        if (taken.contains(p['slug'])) continue;
        final haystack = _plain('${p['slug']} ${p['title']}');
        if (words.any(haystack.contains)) return p;
      }
      return null;
    }

    final serverMorning = chosen('morning');
    final serverAfternoon = chosen('afternoon');
    final serverNight = chosen('night');
    final taken = <String>{
      for (final p in [serverMorning, serverAfternoon, serverNight])
        if (p != null) '${p['slug']}',
    };
    final morning = serverMorning ?? find(const ['manha'], taken);
    if (morning != null) taken.add('${morning['slug']}');
    final night = serverNight ?? find(const ['noite'], taken);
    if (night != null) taken.add('${night['slug']}');
    final afternoon = serverAfternoon ?? find(const ['angelus', 'tarde', 'terco'], taken);
    if (afternoon != null) taken.add('${afternoon['slug']}');

    // "Oração do dia": um destaque gratuito que gira a cada dia (estável dentro do mesmo dia).
    final pool = prayers.where((p) => !taken.contains(p['slug'])).toList();
    final dayIndex = DateTime.utc(now.year, now.month, now.day).difference(DateTime.utc(now.year)).inDays;
    final ofTheDay = pool.isEmpty ? null : pool[dayIndex % pool.length];

    final liturgy = home['liturgy'] is Map ? Map<String, dynamic>.from(home['liturgy'] as Map) : null;
    final gospel = '${liturgy?['gospel_reference'] ?? ''}'.trim();
    final gospelEntry = gospel.isEmpty ? null : WidgetEntry(title: 'Evangelho do dia', subtitle: _clean(gospel, ''), link: 'sancta://liturgia', daily: true);

    WidgetEntry pick(WidgetPeriod period, Map<String, dynamic>? specific, {WidgetEntry? alternative}) {
      if (specific != null) return _fromPrayer(specific, period);
      if (alternative != null) return alternative;
      if (ofTheDay != null) return _fromPrayer(ofTheDay, period);
      return WidgetEntry.fallback(period);
    }

    final day = DateTime(now.year, now.month, now.day);
    return WidgetContent(
      date: DateFormat('yyyy-MM-dd').format(day),
      dateLabel: formatDate(day),
      periods: {
        WidgetPeriod.manha: pick(WidgetPeriod.manha, morning),
        WidgetPeriod.tarde: pick(WidgetPeriod.tarde, afternoon, alternative: gospelEntry),
        WidgetPeriod.noite: pick(WidgetPeriod.noite, night),
      },
    );
  }

  static WidgetEntry _fromPrayer(Map<String, dynamic> prayer, WidgetPeriod period) {
    final minutes = prayer['duration_minutes'] is num ? (prayer['duration_minutes'] as num).round() : int.tryParse('${prayer['duration_minutes']}');
    return WidgetEntry(
      title: _clean(prayer['title'], period.defaultTitle),
      subtitle: minutes != null && minutes > 0 ? '$minutes min' : WidgetEntry.defaultSubtitle,
      link: 'sancta://oracao/${prayer['slug']}',
    );
  }

  /// Minúsculas e sem acentos (para "manhã", "Ângelus", "terço").
  static String _plain(String text) {
    const from = 'áàâãäéèêëíìîïóòôõöúùûüç';
    const to = 'aaaaaeeeeiiiiooooouuuuc';
    final lower = text.toLowerCase();
    final buffer = StringBuffer();
    for (final rune in lower.runes) {
      final char = String.fromCharCode(rune);
      final index = from.indexOf(char);
      buffer.write(index >= 0 ? to[index] : char);
    }
    return buffer.toString();
  }

  String encode() => jsonEncode({
    'v': version,
    'date': date,
    'date_label': dateLabel,
    'periods': {for (final e in periods.entries) e.key.name: e.value.toJson()},
  });

  static WidgetContent? decode(String? raw) {
    if (raw == null || raw.isEmpty) return null;
    try {
      final map = Map<String, dynamic>.from(jsonDecode(raw) as Map);
      final periods = Map<String, dynamic>.from((map['periods'] as Map?) ?? const {});
      return WidgetContent(
        date: '${map['date'] ?? ''}',
        dateLabel: '${map['date_label'] ?? ''}',
        periods: {
          for (final period in WidgetPeriod.values)
            if (periods[period.name] is Map) period: WidgetEntry.fromJson(Map<String, dynamic>.from(periods[period.name] as Map), period),
        },
      );
    } catch (_) {
      return null;
    }
  }
}
