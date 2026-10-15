import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/state/session_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart' show IconBadge, showMessage;
import 'notification_center.dart';
import 'reminders.dart';

/// Seção "Lembretes de oração" da tela Ajustes: manhã e noite, cada um com liga/desliga e
/// horário. Interface estável: a tela de Ajustes inclui `const ReminderSettingsSection()` dentro
/// de um `TileGroup`. A permissão de notificação só é pedida quando o usuário liga um lembrete.
class ReminderSettingsSection extends ConsumerStatefulWidget {
  const ReminderSettingsSection({super.key});

  @override
  ConsumerState<ReminderSettingsSection> createState() => _ReminderSettingsSectionState();
}

class _ReminderSettingsSectionState extends ConsumerState<ReminderSettingsSection> {
  // Ao voltar das configurações do Android, confere de novo se as notificações foram liberadas.
  late final AppLifecycleListener _lifecycle = AppLifecycleListener(onResume: () => ref.invalidate(notificationsEnabledProvider));
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _lifecycle;
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    super.dispose();
  }

  static String _label(ReminderSlot slot) => slot == ReminderSlot.manha ? 'Lembrete da manhã' : 'Lembrete da noite';

  Future<void> _change(ReminderSlot slot, {bool? on, ReminderTime? time}) async {
    if (_busy) return;
    setState(() => _busy = true);
    final result = await ref.read(remindersProvider.notifier).change(slot, on: on, time: time);
    ref.invalidate(notificationsEnabledProvider);
    if (!mounted) return;
    setState(() => _busy = false);

    if (result == ReminderChange.permissionDenied) {
      _askToEnable();
      return;
    }
    final settings = ref.read(remindersProvider);
    if (settings.isOn(slot) && (on == true || time != null)) {
      showMessage(context, '${_label(slot)}: todos os dias às ${settings.timeOf(slot).format()}.');
    }
  }

  Future<void> _pickTime(ReminderSlot slot) async {
    final settings = ref.read(remindersProvider);
    final current = settings.timeOf(slot);
    final picked = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(hour: current.hour, minute: current.minute),
      helpText: _label(slot),
      cancelText: 'Cancelar',
      confirmText: 'Salvar',
      hourLabelText: 'Hora',
      minuteLabelText: 'Minuto',
    );
    if (picked == null || !mounted) return;
    // Escolher um horário liga o lembrete (é o que a pessoa quer ao ajustar a hora).
    await _change(slot, time: ReminderTime(picked.hour, picked.minute), on: settings.isOn(slot) ? null : true);
  }

  void _askToEnable() {
    final messenger = ScaffoldMessenger.maybeOf(context);
    messenger?.hideCurrentSnackBar();
    messenger?.showSnackBar(
      SnackBar(
        content: const Text('Para receber os lembretes, permita as notificações do Sancta.'),
        action: SnackBarAction(label: 'Abrir ajustes', onPressed: () => ref.read(notificationCenterProvider).openSettings()),
      ),
    );
  }

  Future<void> _allow() async {
    final center = ref.read(notificationCenterProvider);
    if (!await center.requestPermission()) await center.openSettings();
    ref.invalidate(notificationsEnabledProvider);
  }

  @override
  Widget build(BuildContext context) {
    final settings = ref.watch(remindersProvider);
    final signedIn = ref.watch(sessionProvider.select((s) => s.isAuthenticated));
    final enabled = ref.watch(notificationsEnabledProvider).valueOrNull ?? true;
    final p = context.sancta;

    final rows = <Widget>[
      for (final slot in ReminderSlot.values)
        _ReminderRow(
          slot: slot,
          settings: settings,
          busy: _busy,
          onToggle: (v) => _change(slot, on: v),
          onPickTime: () => _pickTime(slot),
        ),
      if (settings.anyOn && !enabled)
        Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
          child: Row(
            children: [
              IconBadge(Icons.notifications_off_outlined, color: p.danger),
              const SizedBox(width: 14),
              Expanded(
                child: Text('As notificações do Sancta estão desligadas neste aparelho. Sem elas, os lembretes não aparecem.', style: context.text.bodySmall),
              ),
              const SizedBox(width: 8),
              TextButton(onPressed: _allow, child: const Text('Permitir')),
            ],
          ),
        ),
      Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
        child: Text(
          signedIn
              ? 'Um convite diário para rezar, no horário que você escolher. Chega mesmo sem internet e fica salvo na sua conta.'
              : 'Um convite diário para rezar, no horário que você escolher. Chega mesmo sem internet.',
          style: context.text.bodySmall?.copyWith(color: p.textSoft),
        ),
      ),
    ];

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 0; i < rows.length; i++) ...[rows[i], if (i < rows.length - 2) Divider(height: 1, indent: 68, color: p.outline)],
      ],
    );
  }
}

class _ReminderRow extends StatelessWidget {
  const _ReminderRow({required this.slot, required this.settings, required this.busy, required this.onToggle, required this.onPickTime});

  final ReminderSlot slot;
  final ReminderSettings settings;
  final bool busy;
  final ValueChanged<bool> onToggle;
  final VoidCallback onPickTime;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final on = settings.isOn(slot);
    final time = settings.timeOf(slot).format();
    final morning = slot == ReminderSlot.manha;
    final title = morning ? 'Lembrete da manhã' : 'Lembrete da noite';

    return Material(
      type: MaterialType.transparency,
      child: InkWell(
        onTap: busy ? null : onPickTime,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 64),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 10, 8, 10),
            child: Row(
              children: [
                IconBadge(morning ? Icons.wb_twilight_rounded : Icons.nightlight_round),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(title, style: context.text.titleSmall),
                      const SizedBox(height: 2),
                      Text(on ? 'Todos os dias às $time' : 'Desligado', style: context.text.bodySmall),
                    ],
                  ),
                ),
                Semantics(
                  button: true,
                  label: 'Alterar horário do ${title.toLowerCase()}, agora $time',
                  excludeSemantics: true,
                  child: Material(
                    color: on ? p.iconBg : Colors.transparent,
                    shape: StadiumBorder(side: BorderSide(color: on ? Colors.transparent : p.outline)),
                    child: InkWell(
                      customBorder: const StadiumBorder(),
                      onTap: busy ? null : onPickTime,
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(minHeight: 40, minWidth: 48),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.schedule_rounded, size: 16, color: on ? p.primary : p.muted),
                              const SizedBox(width: 6),
                              Text(
                                time,
                                style: context.text.labelLarge?.copyWith(
                                  color: on ? p.primary : p.textSoft,
                                  fontFeatures: const [FontFeature.tabularFigures()],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 4),
                Semantics(
                  label: title,
                  child: Switch(value: on, onChanged: busy ? null : onToggle),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
