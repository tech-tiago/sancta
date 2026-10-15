import 'package:flutter/foundation.dart';
import 'package:flutter_timezone/flutter_timezone.dart';
import 'package:timezone/data/latest_10y.dart' as tzdata;
import 'package:timezone/timezone.dart' as tz;

/// Fuso horário do aparelho para os agendamentos (`tz.local`).
/// Usa a base compacta (10 anos): basta para lembretes futuros.
abstract final class LocalTimezone {
  static const fallback = 'America/Sao_Paulo';
  static bool _loaded = false;

  /// Nome IANA do fuso atual (ex.: "America/Manaus").
  static String get current => tz.local.name;

  /// Carrega a base (uma vez) e ajusta `tz.local` ao fuso atual do aparelho.
  static Future<String> refresh() async {
    if (!_loaded) {
      tzdata.initializeTimeZones();
      _loaded = true;
    }

    var name = fallback;
    try {
      name = (await FlutterTimezone.getLocalTimezone()).identifier;
    } catch (e) {
      debugPrint('Fuso do aparelho indisponível: $e');
    }
    return use(name);
  }

  /// Nomes que o Android pode informar e que a base compacta não traz (existem só como apelido).
  static const _utcAliases = {'GMT', 'UTC', 'UCT', 'Universal', 'Zulu', 'Greenwich', 'GMT0', 'Etc/Universal', 'Etc/Zulu'};

  /// Ajusta `tz.local`. Fuso desconhecido → fuso fixo com o deslocamento atual do aparelho (`Etc/GMT±N`)
  /// e, só se nem isso servir, [fallback]. Útil também nos testes ([offset] simula o aparelho).
  ///
  /// Achado no QA (emulador em "GMT"): antes, "GMT"/"UTC" caíam em Brasília e o lembrete das 07:00
  /// tocava às 10:00 no relógio do aparelho.
  static String use(String name, {Duration? offset}) {
    if (!_loaded) {
      tzdata.initializeTimeZones();
      _loaded = true;
    }
    final alias = _utcAliases.contains(name) ? 'Etc/UTC' : null;
    final current = offset ?? DateTime.now().timeZoneOffset;
    // POSIX: "Etc/GMT+3" = UTC−3.
    final fixed = current.inMinutes % 60 != 0
        ? null
        : current.inHours == 0
        ? 'Etc/UTC'
        : 'Etc/GMT${current.isNegative ? '+' : '-'}${current.inHours.abs()}';
    for (final candidate in [name, ?alias, ?fixed, fallback]) {
      try {
        tz.setLocalLocation(tz.getLocation(candidate));
        return candidate;
      } catch (_) {}
    }
    return fallback;
  }
}
