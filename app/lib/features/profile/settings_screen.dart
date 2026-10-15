import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/models/models.dart';
import '../../core/network/api_exception.dart';
import '../../core/providers.dart';
import '../../core/state/config_controller.dart';
import '../../core/state/session_controller.dart';
import '../../core/state/theme_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/palette.dart';
import '../../core/utils/links.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/reading_settings.dart';
import '../notifications/push_service.dart';
import '../notifications/reminder_settings.dart';
import '../notifications/reminders.dart' show ReminderSettings;

/// **Ajustes**: aparência (4 temas), leitura, lembretes, notificações, conta e privacidade.
class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final appearance = ref.watch(appearanceProvider);
    final session = ref.watch(sessionProvider);
    final config = ref.watch(configValueProvider);
    final user = session.user;
    final p = context.sancta;

    return Scaffold(
      appBar: AppBar(title: const Text('Ajustes')),
      body: ListView(
        padding: EdgeInsets.fromLTRB(16, 0, 16, 32 + MediaQuery.paddingOf(context).bottom),
        children: [
          const GroupLabel('Aparência', padding: EdgeInsets.fromLTRB(6, 8, 6, 10)),
          // Letra grande: uma coluna (o nome do tema não quebra no meio da palavra).
          GridView.count(
            crossAxisCount: largeText(context) ? 1 : 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: largeText(context) ? 2.6 / textScaleOf(context).clamp(1.0, 2.0) * 1.4 : 1.25 / textScaleOf(context).clamp(1.0, 1.3),
            children: [
              for (final theme in [SanctaTheme.light, SanctaTheme.dark, SanctaTheme.sepia, SanctaTheme.system])
                _ThemeOption(theme: theme, selected: appearance.theme == theme, onTap: () => ref.read(appearanceProvider.notifier).setTheme(theme)),
            ],
          ),

          const GroupLabel('Leitura'),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: p.surface,
              borderRadius: BorderRadius.circular(SanctaRadius.card),
              border: p.isDark ? null : Border.all(color: p.outline),
            ),
            child: const ReadingSettingsPanel(),
          ),

          const GroupLabel('Lembretes de oração'),
          const TileGroup(children: [ReminderSettingsSection()]),

          if (user != null) ...[
            const GroupLabel('Notificações'),
            _NotificationPrefs(user: user),
            const GroupLabel('Conta'),
            TileGroup(
              children: [
                SanctaTile(card: false, icon: Icons.badge_outlined, title: 'Nome', subtitle: user.name, onTap: () => _editName(context, ref, user)),
                SanctaTile(
                  card: false,
                  icon: Icons.lock_reset_rounded,
                  title: user.hasPassword ? 'Alterar senha' : 'Criar senha',
                  subtitle: user.hasPassword ? null : 'Para entrar também pelo site',
                  onTap: () => _changePassword(context, ref, user),
                ),
                SanctaTile(
                  card: false,
                  icon: Icons.devices_rounded,
                  title: 'Sair de todos os aparelhos',
                  subtitle: 'Encerra a sessão em todos os celulares e no site',
                  onTap: () => _logoutAll(context, ref),
                ),
              ],
            ),
          ],

          const GroupLabel('Privacidade e termos'),
          TileGroup(
            children: [
              SanctaTile(
                card: false,
                icon: Icons.privacy_tip_outlined,
                title: 'Política de privacidade',
                onTap: () => context.push('/pagina/politica-de-privacidade'),
              ),
              SanctaTile(card: false, icon: Icons.gavel_rounded, title: 'Termos de uso', onTap: () => context.push('/pagina/termos-de-uso')),
              SanctaTile(
                card: false,
                icon: Icons.description_outlined,
                title: 'Licenças de código aberto',
                onTap: () =>
                    showLicensePage(context: context, applicationName: config.name, applicationLegalese: 'Fontes Figtree e EB Garamond (SIL OFL 1.1).'),
              ),
              if (user != null)
                SanctaTile(
                  card: false,
                  leading: IconBadge(Icons.delete_forever_outlined, color: p.danger),
                  title: 'Excluir minha conta',
                  titleColor: p.danger,
                  subtitle: 'Remove seus dados pessoais (LGPD)',
                  onTap: () => _deleteAccount(context, ref, user),
                )
              else
                SanctaTile(
                  card: false,
                  icon: Icons.delete_outline_rounded,
                  title: 'Como excluir uma conta',
                  onTap: () => openLink(context, config.deleteAccountUrl, inApp: true),
                ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _logoutAll(BuildContext context, WidgetRef ref) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (d) => AlertDialog(
        title: const Text('Sair de todos os aparelhos?'),
        content: const Text('Você precisará entrar de novo em cada aparelho.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(d, true), child: const Text('Sair de todos')),
        ],
      ),
    );
    if (ok != true) return;
    await ref.read(apiClientProvider).post('/auth/logout-all').catchError((_) => null);
    await ref.read(sessionProvider.notifier).logout();
  }

  Future<void> _editName(BuildContext context, WidgetRef ref, AppUser user) async {
    final controller = TextEditingController(text: user.name);
    final name = await showDialog<String>(
      context: context,
      builder: (d) => AlertDialog(
        title: const Text('Seu nome'),
        content: TextField(controller: controller, maxLength: 120, textCapitalization: TextCapitalization.words, autofocus: true),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(d, controller.text.trim()), child: const Text('Salvar')),
        ],
      ),
    );
    if (name == null || name.length < 2) return;
    try {
      final data = await ref.read(apiClientProvider).patch('/me', data: {'name': name});
      ref.read(sessionProvider.notifier).updateUser(AppUser.fromJson(Map<String, dynamic>.from(data as Map)));
    } catch (e) {
      if (context.mounted) showMessage(context, errorText(e));
    }
  }

  Future<void> _changePassword(BuildContext context, WidgetRef ref, AppUser user) async {
    final current = TextEditingController();
    final next = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (d) => AlertDialog(
        title: Text(user.hasPassword ? 'Alterar senha' : 'Criar senha'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (user.hasPassword)
              TextField(
                controller: current,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'Senha atual'),
              ),
            const SizedBox(height: 12),
            TextField(
              controller: next,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Nova senha', helperText: '8 ou mais caracteres, com letras e números'),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(d, true), child: const Text('Salvar')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref
          .read(apiClientProvider)
          .put('/me/password', data: {if (user.hasPassword) 'current_password': current.text, 'password': next.text, 'password_confirmation': next.text});
      await ref.read(sessionProvider.notifier).refreshUser();
      if (context.mounted) showMessage(context, 'Senha salva. As outras sessões foram encerradas.');
    } on ApiException catch (e) {
      if (context.mounted) showMessage(context, e.message);
    }
  }

  Future<void> _deleteAccount(BuildContext context, WidgetRef ref, AppUser user) async {
    final password = TextEditingController();
    final confirm = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (d) => AlertDialog(
        title: const Text('Excluir conta'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Seus dados pessoais, anotações, favoritos e progresso serão apagados. Esta ação não pode ser desfeita.\n\n'
                'Assinaturas do Google Play devem ser canceladas também na Play Store.',
              ),
              const SizedBox(height: 12),
              if (user.hasPassword)
                TextField(
                  controller: password,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: 'Sua senha'),
                ),
              const SizedBox(height: 12),
              TextField(
                controller: confirm,
                decoration: const InputDecoration(labelText: 'Digite EXCLUIR para confirmar'),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d, false), child: const Text('Cancelar')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Theme.of(d).colorScheme.error, foregroundColor: Theme.of(d).colorScheme.onError),
            onPressed: () => Navigator.pop(d, confirm.text.trim().toUpperCase() == 'EXCLUIR'),
            child: const Text('Excluir'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(pushServiceProvider).unregister();
      await ref.read(sessionProvider.notifier).deleteAccount(password: user.hasPassword ? password.text : null);
    } on ApiException catch (e) {
      if (context.mounted) showMessage(context, e.message);
    }
  }
}

/// Cartão de escolha de tema com uma prévia das cores.
class _ThemeOption extends StatelessWidget {
  const _ThemeOption({required this.theme, required this.selected, required this.onTap});

  final SanctaTheme theme;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final previews = switch (theme) {
      SanctaTheme.light => [SanctaPalette.claro],
      SanctaTheme.dark => [SanctaPalette.escuro],
      SanctaTheme.sepia => [SanctaPalette.sepia],
      SanctaTheme.system => [SanctaPalette.claro, SanctaPalette.escuro],
    };

    return Semantics(
      inMutuallyExclusiveGroup: true,
      checked: selected,
      button: true,
      label: '${theme.label}. ${theme.description}',
      excludeSemantics: true,
      child: Material(
        color: p.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SanctaRadius.tile),
          side: BorderSide(color: selected ? p.primary : (p.isDark ? Colors.transparent : p.outline), width: selected ? 2 : 1),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: Row(
                      children: [for (final palette in previews) Expanded(child: _Preview(palette: palette))],
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(child: Text(theme.label, style: context.text.titleSmall)),
                    Icon(selected ? Icons.check_circle_rounded : Icons.circle_outlined, size: 20, color: selected ? p.primary : p.muted),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Preview extends StatelessWidget {
  const _Preview({required this.palette});

  final SanctaPalette palette;

  @override
  Widget build(BuildContext context) => Container(
    color: palette.background,
    padding: const EdgeInsets.all(8),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 34,
          height: 6,
          decoration: BoxDecoration(color: palette.text, borderRadius: BorderRadius.circular(3)),
        ),
        const SizedBox(height: 6),
        Expanded(
          child: Container(
            decoration: BoxDecoration(color: palette.surface, borderRadius: BorderRadius.circular(6)),
            padding: const EdgeInsets.all(5),
            child: Row(
              children: [
                Container(
                  width: 12,
                  height: 12,
                  decoration: BoxDecoration(color: palette.iconBg, borderRadius: BorderRadius.circular(4)),
                ),
                const SizedBox(width: 5),
                Expanded(
                  child: Container(
                    height: 4,
                    decoration: BoxDecoration(color: palette.textSoft.withValues(alpha: .6), borderRadius: BorderRadius.circular(2)),
                  ),
                ),
                const SizedBox(width: 5),
                Container(
                  width: 12,
                  height: 12,
                  decoration: BoxDecoration(color: palette.primary, shape: BoxShape.circle),
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}

/// Preferências de notificação da conta (os lembretes diários ficam em "Lembretes de oração").
class _NotificationPrefs extends ConsumerStatefulWidget {
  const _NotificationPrefs({required this.user});

  final AppUser user;

  @override
  ConsumerState<_NotificationPrefs> createState() => _NotificationPrefsState();
}

class _NotificationPrefsState extends ConsumerState<_NotificationPrefs> {
  // Inclui as chaves dos lembretes (manhã/noite) para não apagá-las ao salvar (ver reminders.dart).
  static const _allowed = ReminderSettings.allAccountKeys;

  late final Map<String, bool> _prefs = {
    'liturgy': widget.user.notificationPreferences['liturgy'] != false,
    'churches': widget.user.notificationPreferences['churches'] != false,
    'news': widget.user.notificationPreferences['news'] == true,
  };

  Future<void> _save() async {
    // O servidor substitui o objeto inteiro: mescla com o que já está salvo (inclusive os lembretes).
    final latest = ref.read(sessionProvider).user?.notificationPreferences ?? widget.user.notificationPreferences;
    final payload = {...latest, ..._prefs}..removeWhere((key, _) => !_allowed.contains(key));
    try {
      final data = await ref.read(apiClientProvider).patch('/me', data: {'notification_preferences': payload});
      if (data is Map && data['id'] != null) ref.read(sessionProvider.notifier).updateUser(AppUser.fromJson(Map<String, dynamic>.from(data)));
    } catch (e) {
      if (mounted) showMessage(context, errorText(e));
    }
  }

  void _toggle(String key, bool value) {
    setState(() => _prefs[key] = value);
    _save();
  }

  Widget _switch(String key, IconData icon, String title, String subtitle) => SwitchListTile(
    value: _prefs[key] ?? false,
    onChanged: (v) => _toggle(key, v),
    contentPadding: const EdgeInsets.fromLTRB(14, 4, 12, 4),
    secondary: IconBadge(icon),
    title: Text(title, style: context.text.titleSmall),
    subtitle: Text(subtitle, style: context.text.bodySmall),
  );

  @override
  Widget build(BuildContext context) => TileGroup(
    children: [
      _switch('liturgy', Icons.auto_stories_outlined, 'Liturgia e santo do dia', 'Um aviso pela manhã com o Evangelho'),
      _switch('churches', Icons.church_outlined, 'Igrejas favoritas', 'Mudanças de horário e eventos'),
      _switch('news', Icons.newspaper_rounded, 'Notícias importantes', 'Só o que for relevante'),
    ],
  );
}
