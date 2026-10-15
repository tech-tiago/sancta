import 'package:flutter/foundation.dart';
import 'package:intl/intl.dart';
import 'package:timezone/timezone.dart' as tz;

import '../../core/models/models.dart';
import 'local_timezone.dart';
import 'notification_center.dart';

/// Aviso "Dia N-2" do teste grátis do Sancta+ (prometido na linha do tempo do paywall):
/// uma notificação local no canal "Lembretes de oração", 2 dias antes do fim do teste.
///
/// Fonte segura do fim do teste: a assinatura da conta (`/me` → `subscription`) com
/// `status = trialing` e `ends_at` — o servidor grava o `expiryTime` do Google Play, que
/// durante o teste é exatamente o fim do período gratuito. Fora de `trialing` (ativa, cancelada,
/// expirada, sem conta) o aviso é cancelado.
abstract final class TrialReminder {
  static const id = 7201;
  static const notice = Duration(days: 2);

  /// Instante do aviso, ou `null` quando não há teste em andamento ou já passou do ponto.
  static DateTime? fireAtFor(AppUser? user, DateTime now) {
    final sub = user?.subscription;
    final endsAt = sub?.endsAt;
    if (sub == null || sub.status != 'trialing' || endsAt == null) return null;
    final at = endsAt.subtract(notice);
    return at.isAfter(now.add(const Duration(minutes: 5))) ? at : null;
  }

  static String title() => 'Seu teste grátis termina em 2 dias';

  /// Ex.: "No dia 15 de outubro começa a cobrança do Sancta+. Se preferir não continuar, cancele antes, sem custo."
  static String body(DateTime endsAt) {
    final day = DateFormat("d 'de' MMMM", 'pt_BR').format(endsAt.toLocal());
    return 'No dia $day começa a cobrança do Sancta+. Se preferir não continuar, cancele antes, sem custo.';
  }

  /// Agenda, reagenda ou cancela conforme a assinatura atual do usuário.
  static Future<void> sync(NotificationCenter center, AppUser? user) async {
    if (!center.supported) return;
    try {
      final at = fireAtFor(user, DateTime.now());
      if (at == null) {
        await center.cancel(id);
        return;
      }
      await LocalTimezone.refresh();
      await center.schedule(id: id, at: tz.TZDateTime.from(at, tz.local), title: title(), body: body(user!.subscription!.endsAt!), payload: 'sancta://voce');
    } catch (e) {
      debugPrint('Falha ao agendar o aviso do teste grátis: $e');
    }
  }
}
