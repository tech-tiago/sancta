import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models/models.dart';
import '../../core/providers.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/links.dart';
import '../../core/widgets/common.dart';

/// Avisos e pop-ups cadastrados no painel (eventos, promoções, novidades).
/// Respeita a frequência configurada: uma vez, uma vez por dia ou a cada abertura.
class AnnouncementService {
  AnnouncementService(this.ref);

  final Ref ref;
  bool _shownThisSession = false;

  Future<List<Announcement>> fetch() async {
    final data = await ref.read(apiClientProvider).get('/announcements');
    return (data as List).whereType<Map>().map((e) => Announcement.fromJson(Map<String, dynamic>.from(e))).toList();
  }

  bool _eligible(Announcement a) {
    final prefs = ref.read(sharedPreferencesProvider);
    final key = 'sancta.ann.${a.id}';
    final last = prefs.getString(key);
    if (last == null) return true;

    final parts = last.split('|'); // data|updated_at
    if (parts.length == 2 && parts[1] != a.updatedAt) return true; // aviso editado no painel: mostra de novo
    return switch (a.frequency) {
      'every_open' => true,
      'daily' => parts.first != _today(),
      _ => false,
    };
  }

  void _markShown(Announcement a) => ref.read(sharedPreferencesProvider).setString('sancta.ann.${a.id}', '${_today()}|${a.updatedAt}');

  String _today() => DateTime.now().toIso8601String().substring(0, 10);

  void _track(Announcement a, String action) {
    ref.read(apiClientProvider).post('/announcements/${a.id}/interactions', data: {'action': action}).catchError((_) => null);
  }

  /// Exibe o primeiro pop-up elegível (uma vez por sessão do app).
  Future<void> showPending(BuildContext context) async {
    if (_shownThisSession) return;
    try {
      final list = await fetch();
      final next = list.where((a) => a.kind != 'banner').where(_eligible).firstOrNull;
      if (next == null || !context.mounted) return;

      _shownThisSession = true;
      _markShown(next);
      _track(next, 'view');

      final clicked = await showDialog<bool>(
        context: context,
        barrierDismissible: next.dismissible,
        builder: (_) => _AnnouncementDialog(announcement: next),
      );

      if (clicked == true) {
        _track(next, 'click');
        if (context.mounted) await openLink(context, next.ctaUrl);
      } else {
        _track(next, 'dismiss');
      }
    } catch (_) {
      /* avisos nunca devem travar o app */
    }
  }
}

final announcementServiceProvider = Provider<AnnouncementService>((ref) => AnnouncementService(ref));

/// Banners para o topo da tela inicial.
final bannersProvider = FutureProvider.autoDispose<List<Announcement>>((ref) async {
  final list = await ref.read(announcementServiceProvider).fetch();
  return list.where((a) => a.kind == 'banner').toList();
});

class _AnnouncementDialog extends StatelessWidget {
  const _AnnouncementDialog({required this.announcement});

  final Announcement announcement;

  @override
  Widget build(BuildContext context) {
    final a = announcement;
    return Dialog(
      clipBehavior: Clip.antiAlias,
      insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 40),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(28)),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (a.imageUrl != null) AspectRatio(aspectRatio: 16 / 9, child: NetImage(a.imageUrl)),
          Flexible(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(24, 22, 24, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(a.title, style: context.text.headlineSmall),
                  if (a.body != null) ...[const SizedBox(height: 10), Text(a.body!, style: context.text.bodyLarge)],
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(24, 12, 24, 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (a.ctaLabel != null && a.ctaUrl != null) FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(a.ctaLabel!)),
                if (a.dismissible) TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Agora não')),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
