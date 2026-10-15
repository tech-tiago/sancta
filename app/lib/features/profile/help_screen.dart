import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/state/config_controller.dart';
import '../../core/state/content_providers.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/links.dart';
import '../../core/widgets/common.dart';

/// **Ajuda**: perguntas frequentes (acordeão) e contato com o suporte.
class HelpScreen extends ConsumerWidget {
  const HelpScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(configValueProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Ajuda')),
      body: ref
          .watch(faqsProvider)
          .when(
            loading: () => const SkeletonTiles(count: 6),
            error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(faqsProvider)),
            data: (faqs) => ListView(
              padding: EdgeInsets.fromLTRB(16, 0, 16, 32 + MediaQuery.paddingOf(context).bottom),
              children: [
                if (faqs.isNotEmpty) ...[
                  const GroupLabel('Perguntas frequentes', padding: EdgeInsets.fromLTRB(6, 8, 6, 10)),
                  TileGroup(
                    dividerIndent: 18,
                    children: [
                      for (final f in faqs)
                        Theme(
                          data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
                          child: ExpansionTile(
                            title: Text('${f['question']}', style: context.text.titleSmall),
                            childrenPadding: const EdgeInsets.fromLTRB(18, 0, 18, 16),
                            expandedAlignment: Alignment.centerLeft,
                            children: [Text('${f['answer']}', style: context.text.bodyMedium)],
                          ),
                        ),
                    ],
                  ),
                ],
                const GroupLabel('Ainda precisa de ajuda?'),
                TileGroup(
                  children: [
                    if (config.supportEmail.isNotEmpty)
                      SanctaTile(
                        card: false,
                        icon: Icons.mail_outline_rounded,
                        title: 'Escrever para o suporte',
                        subtitle: config.supportEmail,
                        onTap: () => openLink(context, 'mailto:${config.supportEmail}'),
                      ),
                    if (config.supportWhatsapp != null)
                      SanctaTile(
                        card: false,
                        icon: Icons.chat_outlined,
                        title: 'WhatsApp',
                        subtitle: 'Resposta em horário comercial',
                        onTap: () => openLink(context, 'https://wa.me/${config.supportWhatsapp!.replaceAll(RegExp(r'[^0-9]'), '')}'),
                      ),
                    SanctaTile(card: false, icon: Icons.info_outline_rounded, title: 'Sobre o Sancta', onTap: () => context.push('/pagina/sobre')),
                  ],
                ),
              ],
            ),
          ),
    );
  }
}
