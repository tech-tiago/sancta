import 'dart:async';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timezone/timezone.dart' as tz;

import '../../core/utils/links.dart';
import 'notification_channels.dart';

/// Permissão de notificação negada (Android 13+) ou notificações desligadas nas
/// configurações do aparelho. A mensagem já é adequada para mostrar ao usuário.
class NotificationPermissionDenied implements Exception {
  const NotificationPermissionDenied([this.message = 'Para receber avisos, permita as notificações do Sancta nas configurações do aparelho.']);

  final String message;

  @override
  String toString() => message;
}

/// Notificações locais (flutter_local_notifications) do Sancta:
///  - cria os três canais (Avisos e novidades · Ao vivo · Lembretes de oração) e remove o antigo;
///  - exibe o push recebido com o app aberto (o FCM não mostra nada em primeiro plano);
///  - agenda os lembretes (sempre inexatos: nenhum pedido de "alarme exato");
///  - entrega os toques como deep links `sancta://` — a validação acontece em quem navega.
class NotificationCenter {
  NotificationCenter({FlutterLocalNotificationsPlugin? plugin}) : _plugin = plugin ?? FlutterLocalNotificationsPlugin();

  final FlutterLocalNotificationsPlugin _plugin;
  final _taps = StreamController<String>.broadcast();
  Future<bool>? _ready;

  /// Só o Android usa estas notificações locais (o app é publicado na Play Store).
  bool get supported => !kIsWeb && defaultTargetPlatform == TargetPlatform.android;

  /// Deep links (payload) das notificações locais tocadas com o app aberto/em segundo plano.
  Stream<String> get taps => _taps.stream;

  AndroidFlutterLocalNotificationsPlugin? get _android => _plugin.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();

  Future<bool> ensureInitialized() => _ready ??= _init();

  Future<bool> _init() async {
    if (!supported) return false;
    try {
      await _plugin.initialize(
        settings: const InitializationSettings(android: AndroidInitializationSettings(SanctaChannels.smallIcon)),
        onDidReceiveNotificationResponse: (response) {
          final payload = response.payload;
          if (payload != null && payload.isNotEmpty) _taps.add(payload);
        },
      );

      final android = _android;
      if (android != null) {
        // Criar de novo só atualiza nome/descrição (o Android preserva as escolhas do usuário).
        for (final channel in SanctaChannels.all) {
          await android.createNotificationChannel(channel);
        }
        final existing = await android.getNotificationChannels() ?? const <AndroidNotificationChannel>[];
        for (final legacy in SanctaChannels.legacy) {
          if (existing.any((c) => c.id == legacy)) await android.deleteNotificationChannel(channelId: legacy);
        }
      }
      return true;
    } catch (e) {
      debugPrint('Notificações locais indisponíveis: $e');
      _ready = null; // permite tentar de novo na próxima chamada
      return false;
    }
  }

  /// Payload da notificação local que abriu o app (app estava fechado), se houver.
  Future<String?> launchPayload() async {
    if (!await ensureInitialized()) return null;
    try {
      final details = await _plugin.getNotificationAppLaunchDetails();
      if (details?.didNotificationLaunchApp != true) return null;
      return details?.notificationResponse?.payload;
    } catch (_) {
      return null;
    }
  }

  /// As notificações do app estão liberadas (permissão do Android 13+ e chave geral do app)?
  Future<bool> areEnabled() async {
    if (!supported) return true;
    if (!await ensureInitialized()) return false;
    try {
      return await _android?.areNotificationsEnabled() ?? false;
    } catch (_) {
      return false;
    }
  }

  /// Pede a permissão de notificação (Android 13+). Chame só a partir de uma ação do usuário
  /// (ligar um lembrete, "Me avise"). Retorna se as notificações ficaram liberadas.
  Future<bool> requestPermission() async {
    if (!supported) return true;
    if (await areEnabled()) return true;
    try {
      final granted = await _android?.requestNotificationsPermission();
      return granted == true || await areEnabled();
    } catch (_) {
      // Ex.: outro pedido de permissão em andamento.
      return areEnabled();
    }
  }

  /// Abre as configurações de notificação do Sancta no Android.
  Future<void> openSettings() async {
    if (!supported || !await ensureInitialized()) return;
    try {
      await _android?.openAppNotificationSettings();
    } catch (_) {}
  }

  // ─────────────── Push em primeiro plano ───────────────

  /// Exibe como notificação local um push recebido com o app aberto, no canal enviado
  /// pelo servidor (desconhecido → "Avisos e novidades"), com imagem grande quando houver.
  Future<void> showRemote(RemoteMessage message, {Future<Uint8List?> Function(String url)? loadImage}) async {
    final notification = message.notification;
    if (notification == null || !await ensureInitialized()) return;

    final channel = SanctaChannels.resolve(notification.android?.channelId);
    final link = message.data['deep_link'];
    final payload = link is String && deepLinkToRoute(link) != null ? link : null;

    final imageUrl = notification.android?.imageUrl;
    final image = imageUrl == null || imageUrl.isEmpty ? null : await (loadImage ?? downloadImage)(imageUrl);

    await _plugin.show(
      id: notificationIdFor(message.messageId),
      title: notification.title,
      body: notification.body,
      payload: payload,
      notificationDetails: NotificationDetails(
        android: androidDetails(channel, title: notification.title, body: notification.body, picture: image == null ? null : ByteArrayAndroidBitmap(image)),
      ),
    );
  }

  /// ID estável e positivo para o push (repetições do mesmo push substituem a anterior).
  static int notificationIdFor(String? messageId) => messageId == null ? DateTime.now().millisecondsSinceEpoch & 0x3fffffff : messageId.hashCode & 0x3fffffff;

  /// Detalhes Android padronizados: ícone do Portal, cor Azul Manto e texto expansível.
  static AndroidNotificationDetails androidDetails(AndroidNotificationChannel channel, {String? title, String? body, AndroidBitmap<Object>? picture}) =>
      AndroidNotificationDetails(
        channel.id,
        channel.name,
        channelDescription: channel.description,
        importance: channel.importance,
        priority: channel.importance == Importance.high ? Priority.high : Priority.defaultPriority,
        icon: SanctaChannels.smallIcon,
        color: SanctaChannels.accent,
        largeIcon: picture,
        styleInformation: picture != null
            ? BigPictureStyleInformation(picture, contentTitle: title, summaryText: body, hideExpandedLargeIcon: true)
            : (body != null && body.isNotEmpty ? BigTextStyleInformation(body, contentTitle: title) : null),
      );

  /// Tamanho máximo da imagem grande de uma campanha.
  static const maxImageBytes = 5 * 1024 * 1024;

  static const _maxImageRedirects = 3;

  /// Baixa a imagem de uma campanha (pentest fase 3 — SEC-17):
  ///  - só HTTPS e só para endereços públicos (nada de localhost/IP interno, também após redirecionamento —
  ///    cada salto é revalidado, no máximo 3);
  ///  - para de baixar assim que passa de 5 MB (o tamanho não é conferido só no fim) e recusa de cara um
  ///    `Content-Length` maior; só `image/*`; tempo total limitado.
  /// [dio] existe para os testes.
  static Future<Uint8List?> downloadImage(String url, {Dio? dio}) async {
    var uri = Uri.tryParse(url);
    if (uri == null || !isAllowedImageUri(uri)) return null;

    final client =
        dio ??
        Dio(
          BaseOptions(
            connectTimeout: const Duration(seconds: 8),
            receiveTimeout: const Duration(seconds: 10),
            followRedirects: false,
            responseType: ResponseType.stream,
            validateStatus: (status) => status != null && status < 400,
          ),
        );
    final cancel = CancelToken();
    final deadline = Timer(const Duration(seconds: 20), () => cancel.cancel('tempo esgotado'));
    try {
      for (var hop = 0; hop <= _maxImageRedirects; hop++) {
        if (uri == null || !isAllowedImageUri(uri)) return null;
        final response = await client.getUri<ResponseBody>(
          uri,
          cancelToken: cancel,
          options: Options(responseType: ResponseType.stream, followRedirects: false, validateStatus: (status) => status != null && status < 400),
        );
        final status = response.statusCode ?? 0;
        if (status >= 300) {
          final location = response.headers.value('location');
          uri = location == null ? null : uri.resolve(location);
          continue;
        }

        final type = response.headers.value(Headers.contentTypeHeader) ?? '';
        final declared = int.tryParse(response.headers.value(Headers.contentLengthHeader) ?? '');
        final body = response.data;
        if (status != 200 || body == null || !type.startsWith('image/') || (declared != null && declared > maxImageBytes)) return null;

        final bytes = BytesBuilder(copy: false);
        await for (final chunk in body.stream) {
          bytes.add(chunk);
          if (bytes.length > maxImageBytes) return null; // sair do laço cancela o download
        }
        return bytes.isEmpty ? null : bytes.takeBytes();
      }
      return null;
    } catch (_) {
      return null;
    } finally {
      deadline.cancel();
      if (!cancel.isCancelled) cancel.cancel('fim');
    }
  }

  /// HTTPS para um host público: recusa localhost, `.local` e IPs literais de redes internas,
  /// loopback, link-local (inclui 169.254.169.254), CGNAT, multicast e reservados (IPv4 e IPv6).
  static bool isAllowedImageUri(Uri uri) {
    if (uri.scheme != 'https' || uri.host.isEmpty || uri.userInfo.isNotEmpty) return false;
    final host = uri.host.toLowerCase();
    if (host == 'localhost' || host.endsWith('.localhost') || host.endsWith('.local') || host.endsWith('.internal')) return false;

    List<int>? ip;
    try {
      ip = host.contains(':') ? Uri.parseIPv6Address(host) : (RegExp(r'^[0-9.]+$').hasMatch(host) ? Uri.parseIPv4Address(host) : null);
    } on FormatException {
      return false; // parece IP, mas é inválido
    }
    if (ip == null) return true; // nome de domínio

    if (ip.length == 16) {
      final mappedV4 = ip.sublist(0, 10).every((b) => b == 0) && ip[10] == 0xff && ip[11] == 0xff;
      if (mappedV4) return _isPublicIPv4(ip.sublist(12));
      if (ip.every((b) => b == 0) || (ip.sublist(0, 15).every((b) => b == 0) && ip[15] == 1)) return false; // :: e ::1
      if ((ip[0] & 0xfe) == 0xfc) return false; // fc00::/7 (rede interna)
      if (ip[0] == 0xfe && (ip[1] & 0xc0) == 0x80) return false; // fe80::/10 (link-local)
      if (ip[0] == 0xff) return false; // multicast
      return true;
    }
    return _isPublicIPv4(ip);
  }

  static bool _isPublicIPv4(List<int> ip) {
    final a = ip[0], b = ip[1];
    return !(a == 0 ||
        a == 10 ||
        a == 127 ||
        (a == 100 && b >= 64 && b <= 127) ||
        (a == 169 && b == 254) ||
        (a == 172 && b >= 16 && b <= 31) ||
        (a == 192 && b == 168) ||
        (a == 192 && b == 0 && ip[2] == 0) ||
        (a == 198 && (b == 18 || b == 19)) ||
        a >= 224);
  }

  // ─────────────── Agendamento (lembretes) ───────────────

  /// Agenda (ou substitui) uma notificação no canal "Lembretes de oração".
  /// [daily] = repete todos os dias no mesmo horário local. Sempre inexata.
  Future<void> schedule({
    required int id,
    required tz.TZDateTime at,
    required String title,
    required String body,
    required String payload,
    bool daily = false,
  }) async {
    if (!await ensureInitialized()) return;
    await _plugin.zonedSchedule(
      id: id,
      scheduledDate: at,
      title: title,
      body: body,
      payload: payload,
      notificationDetails: NotificationDetails(
        android: androidDetails(SanctaChannels.lembretes, title: title, body: body),
      ),
      androidScheduleMode: AndroidScheduleMode.inexactAllowWhileIdle,
      matchDateTimeComponents: daily ? DateTimeComponents.time : null,
    );
  }

  Future<void> cancel(int id) async {
    if (!await ensureInitialized()) return;
    await _plugin.cancel(id: id);
  }

  Future<List<PendingNotificationRequest>> pending() async {
    if (!await ensureInitialized()) return const [];
    return _plugin.pendingNotificationRequests();
  }
}

final notificationCenterProvider = Provider<NotificationCenter>((ref) => NotificationCenter());

/// As notificações do app estão liberadas? Invalide ao voltar das configurações.
final notificationsEnabledProvider = FutureProvider.autoDispose<bool>((ref) => ref.watch(notificationCenterProvider).areEnabled());
