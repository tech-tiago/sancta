import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Relógio do app (saudação, céu do período, agenda ao vivo). Substituível nos testes
/// para que as telas e os goldens não dependam da hora em que rodam.
final clockProvider = Provider<DateTime Function()>((ref) => DateTime.now);

/// Período do dia usado nos textos (mesmos cortes de `SkyArt.sceneFor`).
enum PrayerPeriod {
  manha('Bom dia', 'Oração da manhã'),
  tarde('Boa tarde', 'Oração da tarde'),
  noite('Boa noite', 'Oração da noite');

  const PrayerPeriod(this.greeting, this.prayerTitle);

  final String greeting;
  final String prayerTitle;

  /// 05h–11h59 manhã · 12h–17h59 tarde · 18h–04h59 noite.
  static PrayerPeriod of(DateTime time) {
    final h = time.hour;
    if (h >= 5 && h < 12) return PrayerPeriod.manha;
    if (h >= 12 && h < 18) return PrayerPeriod.tarde;
    return PrayerPeriod.noite;
  }
}
