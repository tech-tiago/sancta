import 'package:flutter/painting.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../../core/brand/brand_colors.dart';

/// Canais de notificação do Android (nomes e descrições aparecem em
/// Configurações › Apps › Sancta › Notificações). Os IDs são o contrato com o
/// servidor (`PushService.php`) e com o `AndroidManifest.xml`.
abstract final class SanctaChannels {
  /// Campanhas, novidades e avisos da conta (canal padrão do FCM).
  static const avisos = AndroidNotificationChannel(
    'sancta_avisos',
    'Avisos e novidades',
    description: 'Novidades do Sancta e avisos importantes da sua conta.',
  );

  /// "Me avise" dos canais ao vivo (missas, terços, adoração).
  static const aoVivo = AndroidNotificationChannel(
    'sancta_ao_vivo',
    'Ao vivo',
    description: 'Quando um canal que você escolheu começa uma transmissão.',
    importance: Importance.high,
  );

  /// Lembretes locais de oração (manhã/noite) e avisos do teste grátis.
  static const lembretes = AndroidNotificationChannel(
    'sancta_lembretes',
    'Lembretes de oração',
    description: 'Os lembretes para rezar nos horários que você escolheu.',
  );

  static const all = [avisos, aoVivo, lembretes];

  /// Canal das versões anteriores: é removido na inicialização. Pushes antigos que ainda
  /// citem esse ID caem no canal padrão do Manifest (`sancta_avisos`).
  static const legacy = ['sancta_default'];

  /// Ícone pequeno (silhueta do Portal) e cor de destaque (Azul Manto).
  static const smallIcon = 'ic_notification';
  static const Color accent = BrandColors.azulManto;

  /// Canal a usar para um ID recebido do servidor: só os três canais conhecidos;
  /// qualquer outro (inclusive o antigo `sancta_default`) vira "Avisos e novidades".
  static AndroidNotificationChannel resolve(String? id) => all.firstWhere((c) => c.id == id, orElse: () => avisos);
}
