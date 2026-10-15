import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/router.dart';
import '../../core/models/models.dart';
import '../../core/providers.dart';
import '../../core/state/content_providers.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';

/// **Intenções**: pedidos de oração da comunidade (publicados após moderação).
class IntentionsScreen extends ConsumerWidget {
  const IntentionsScreen({super.key});

  Future<void> _new(BuildContext context, WidgetRef ref) async {
    if (!requireAccount(context, ref, reason: 'Entre para pedir orações à comunidade.')) return;
    final text = TextEditingController();
    var anonymous = true;
    String? error;

    final sent = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (sheet) => StatefulBuilder(
        builder: (context, setState) => Padding(
          padding: EdgeInsets.fromLTRB(24, 0, 24, MediaQuery.viewInsetsOf(sheet).bottom + 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Pedir oração', style: context.text.headlineSmall),
              const SizedBox(height: 6),
              Text('Sua intenção passa por revisão antes de ser publicada. Não inclua dados pessoais de outras pessoas.', style: context.text.bodyMedium),
              const SizedBox(height: 14),
              TextField(
                controller: text,
                maxLines: 5,
                maxLength: 500,
                autofocus: true,
                decoration: InputDecoration(hintText: 'Ex.: Pela saúde da minha mãe…', errorText: error),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: anonymous,
                onChanged: (v) => setState(() => anonymous = v),
                title: const Text('Publicar como anônimo'),
              ),
              const SizedBox(height: 8),
              FilledButton(
                onPressed: () async {
                  if (text.text.trim().length < 10) {
                    setState(() => error = 'Escreva pelo menos 10 caracteres.');
                    return;
                  }
                  try {
                    await ref.read(apiClientProvider).post('/intentions', data: {'text': text.text.trim(), 'is_anonymous': anonymous});
                    if (sheet.mounted) Navigator.pop(sheet, true);
                  } catch (e) {
                    setState(() => error = errorText(e));
                  }
                },
                child: const Text('Enviar intenção'),
              ),
            ],
          ),
        ),
      ),
    );

    if (sent == true && context.mounted) showMessage(context, 'Intenção enviada. Estamos rezando com você.');
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    appBar: AppBar(title: const Text('Intenções')),
    floatingActionButton: FloatingActionButton.extended(
      onPressed: () => _new(context, ref),
      icon: const Icon(Icons.add_rounded),
      label: const Text('Pedir oração'),
    ),
    body: RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(intentionsProvider);
        await ref.read(intentionsProvider.future).catchError((Object _) => <Intention>[]);
      },
      child: ref
          .watch(intentionsProvider)
          .when(
            skipLoadingOnRefresh: true,
            loading: () => const SkeletonTiles(count: 5),
            error: (e, _) => ListView(
              children: [ErrorView(error: e, onRetry: () => ref.invalidate(intentionsProvider))],
            ),
            data: (items) => ListView.separated(
              padding: EdgeInsets.fromLTRB(16, 4, 16, 104 + MediaQuery.paddingOf(context).bottom),
              itemCount: items.length + 1,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (_, i) {
                if (i == 0) {
                  return Padding(
                    padding: const EdgeInsets.fromLTRB(4, 0, 4, 6),
                    child: Text(
                      items.isEmpty ? 'Seja o primeiro a pedir uma oração.' : 'Reze pelos pedidos da comunidade e peça orações por quem você ama.',
                      style: context.text.bodyMedium,
                    ),
                  );
                }
                return _IntentionCard(intention: items[i - 1]);
              },
            ),
          ),
    ),
  );
}

class _IntentionCard extends ConsumerStatefulWidget {
  const _IntentionCard({required this.intention});

  final Intention intention;

  @override
  ConsumerState<_IntentionCard> createState() => _IntentionCardState();
}

class _IntentionCardState extends ConsumerState<_IntentionCard> {
  late bool _prayed = widget.intention.prayed;
  late int _count = widget.intention.prayersCount;

  Future<void> _pray() async {
    if (_prayed || !requireAccount(context, ref, reason: 'Entre para rezar pelas intenções da comunidade.')) return;
    setState(() {
      _prayed = true;
      _count++;
    });
    try {
      final result = await ref.read(apiClientProvider).post('/intentions/${widget.intention.id}/pray');
      setState(() => _count = (result as Map)['prayers_count'] as int? ?? _count);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return Container(
      padding: const EdgeInsets.fromLTRB(18, 16, 12, 10),
      decoration: BoxDecoration(
        color: p.surface,
        borderRadius: BorderRadius.circular(SanctaRadius.card),
        border: p.isDark ? null : Border.all(color: p.outline),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(widget.intention.text, style: context.text.bodyLarge),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(child: Text(widget.intention.author, style: context.text.bodySmall)),
              Semantics(
                button: true,
                toggled: _prayed,
                label: _prayed ? 'Você rezou por esta intenção. $_count orações' : 'Rezar por esta intenção. $_count orações',
                excludeSemantics: true,
                child: _prayed
                    ? FilledButton.icon(
                        onPressed: null,
                        style: FilledButton.styleFrom(
                          disabledBackgroundColor: p.iconBg,
                          disabledForegroundColor: p.primary,
                          minimumSize: const Size(48, 44),
                          padding: const EdgeInsets.symmetric(horizontal: 14),
                        ),
                        icon: const Icon(Icons.volunteer_activism_rounded, size: 18),
                        label: Text('Rezei · $_count'),
                      )
                    : OutlinedButton.icon(
                        onPressed: _pray,
                        style: OutlinedButton.styleFrom(minimumSize: const Size(48, 44), padding: const EdgeInsets.symmetric(horizontal: 14)),
                        icon: const Icon(Icons.volunteer_activism_outlined, size: 18),
                        label: Text('Rezar · $_count'),
                      ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
