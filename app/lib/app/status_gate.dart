import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/brand/brand.dart';
import '../core/models/models.dart';
import '../core/state/config_controller.dart';
import '../core/theme/app_theme.dart';
import '../core/theme/system_bars.dart';

final _appVersionProvider = FutureProvider<String>((ref) async => (await PackageInfo.fromPlatform()).version);

/// Bloqueia o app quando o painel ativa manutenção ou exige versão mínima.
class StatusGate extends ConsumerWidget {
  const StatusGate({super.key, required this.child});

  final Widget child;

  static bool isOlder(String current, String required) {
    List<int> parse(String v) => v.split('.').map((p) => int.tryParse(p.replaceAll(RegExp(r'[^0-9]'), '')) ?? 0).toList();
    final a = parse(current), b = parse(required);
    for (var i = 0; i < 3; i++) {
      final x = i < a.length ? a[i] : 0, y = i < b.length ? b[i] : 0;
      if (x != y) return x < y;
    }
    return false;
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(configProvider).valueOrNull;
    final version = ref.watch(_appVersionProvider).valueOrNull;
    if (config == null || version == null) return child;

    if (config.maintenance) {
      return _Blocker(
        icon: Icons.construction_rounded,
        title: 'Em manutenção',
        message: config.maintenanceMessage,
        onRetry: () => ref.read(configProvider.notifier).refresh(),
      );
    }
    if (isOlder(version, config.minVersion)) {
      return _Blocker(
        icon: Icons.system_update_rounded,
        title: 'Atualize o Sancta',
        message: config.updateMessage.isEmpty ? 'Esta versão não é mais suportada. Atualize para continuar.' : config.updateMessage,
        actionLabel: 'Atualizar agora',
        onAction: () => _openStore(config),
      );
    }
    return child;
  }

  static Future<void> _openStore(AppConfig config) async {
    final uri = Uri.tryParse(config.playStoreUrl);
    if (uri != null) await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}

class _Blocker extends StatelessWidget {
  const _Blocker({required this.icon, required this.title, required this.message, this.actionLabel, this.onAction, this.onRetry});

  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => DarkSystemBars(
    child: Scaffold(
      backgroundColor: BrandColors.azulManto,
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [BrandColors.azulManto, BrandColors.noite]),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Center(child: PortalMark.pergaminho(height: 72, shape: PortalShape.door)),
                const SizedBox(height: 28),
                Icon(icon, color: BrandColors.nevoa, size: 32),
                const SizedBox(height: 12),
                Text(
                  title,
                  textAlign: TextAlign.center,
                  style: context.text.headlineMedium?.copyWith(color: BrandColors.pergaminho),
                ),
                const SizedBox(height: 10),
                Text(
                  message,
                  textAlign: TextAlign.center,
                  style: context.text.bodyLarge?.copyWith(color: BrandColors.nevoa),
                ),
                const SizedBox(height: 28),
                if (actionLabel != null)
                  FilledButton(
                    onPressed: onAction,
                    style: FilledButton.styleFrom(backgroundColor: BrandColors.pergaminho, foregroundColor: BrandColors.azulManto),
                    child: Text(actionLabel!),
                  ),
                if (onRetry != null)
                  OutlinedButton(
                    onPressed: onRetry,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: BrandColors.pergaminho,
                      side: const BorderSide(color: Color(0x66FBF6EC)),
                    ),
                    child: const Text('Tentar novamente'),
                  ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}
