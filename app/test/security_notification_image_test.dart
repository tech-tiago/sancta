import 'dart:async';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sancta/features/notifications/notification_center.dart';

/// Pentest fase 3 — SEC-17: imagem grande dos pushes (baixada pelo app com ele aberto).
/// Antes: o corpo inteiro ia para a memória e só depois o limite de 5 MB era conferido; redirecionamentos
/// eram seguidos sem revalidar o destino; IPs internos/loopback eram aceitos.
class _FakeAdapter implements HttpClientAdapter {
  _FakeAdapter(this.handler);

  final ResponseBody Function(RequestOptions options) handler;
  final requests = <Uri>[];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    requests.add(options.uri);
    return handler(options);
  }

  @override
  void close({bool force = false}) {}
}

Dio _dio(_FakeAdapter adapter) => Dio()..httpClientAdapter = adapter;

ResponseBody _image(List<int> bytes, {String type = 'image/png', int? declaredLength}) => ResponseBody(
  Stream.value(Uint8List.fromList(bytes)),
  200,
  headers: {
    'content-type': [type],
    if (declaredLength != null) 'content-length': ['$declaredLength'],
  },
);

void main() {
  group('Imagem grande do push', () {
    test('só HTTPS para hosts públicos (sem localhost, rede interna, link-local ou metadados da nuvem)', () {
      bool allowed(String url) => NotificationCenter.isAllowedImageUri(Uri.parse(url));

      expect(allowed('https://cdn.sancta.app.br/campanha.png'), isTrue);
      expect(allowed('https://8.8.8.8/a.png'), isTrue);
      expect(allowed('https://[2001:4860:4860::8888]/a.png'), isTrue);

      for (final url in [
        'http://cdn.sancta.app.br/a.png',
        'https://localhost/a.png',
        'https://admin.localhost/a.png',
        'https://impressora.local/a.png',
        'https://127.0.0.1/a.png',
        'https://10.0.0.5/a.png',
        'https://172.20.1.1/a.png',
        'https://192.168.0.1/a.png',
        'https://169.254.169.254/latest/meta-data',
        'https://100.64.0.1/a.png',
        'https://0.0.0.0/a.png',
        'https://[::1]/a.png',
        'https://[fd00::1]/a.png',
        'https://[fe80::1]/a.png',
        'https://[::ffff:192.168.0.1]/a.png',
        'https://usuario:senha@cdn.sancta.app.br/a.png',
      ]) {
        expect(allowed(url), isFalse, reason: url);
      }
    });

    test('baixa uma imagem pequena válida', () async {
      final adapter = _FakeAdapter((_) => _image([1, 2, 3, 4]));
      final bytes = await NotificationCenter.downloadImage('https://cdn.sancta.app.br/a.png', dio: _dio(adapter));
      expect(bytes, Uint8List.fromList([1, 2, 3, 4]));
    });

    test('para de baixar ao passar de 5 MB (não acumula o corpo inteiro na memória)', () async {
      var chunksRead = 0;
      Stream<Uint8List> endless() async* {
        while (true) {
          await Future<void>.delayed(Duration.zero); // pedaços chegando pela rede
          chunksRead++;
          yield Uint8List(1024 * 1024); // 1 MB por pedaço, sem fim
        }
      }

      final adapter = _FakeAdapter(
        (_) => ResponseBody(
          endless(),
          200,
          headers: {
            'content-type': ['image/jpeg'],
          },
        ),
      );
      final bytes = await NotificationCenter.downloadImage('https://cdn.sancta.app.br/enorme.jpg', dio: _dio(adapter)).timeout(const Duration(seconds: 10));

      expect(bytes, isNull);
      expect(chunksRead, lessThanOrEqualTo(7), reason: 'Leitura interrompida logo após o limite');
    });

    test('Content-Length acima do limite é recusado de cara (download interrompido)', () async {
      var chunksRead = 0;
      var cancelled = false;
      final controller = StreamController<Uint8List>(onCancel: () => cancelled = true);
      Timer.periodic(Duration.zero, (timer) {
        if (cancelled || chunksRead > 50) return timer.cancel();
        chunksRead++;
        controller.add(Uint8List(1024 * 1024));
      });

      final adapter = _FakeAdapter(
        (_) => ResponseBody(
          controller.stream,
          200,
          headers: {
            'content-type': ['image/png'],
            'content-length': ['${NotificationCenter.maxImageBytes + 1}'],
          },
        ),
      );

      expect(await NotificationCenter.downloadImage('https://cdn.sancta.app.br/a.png', dio: _dio(adapter)), isNull);
      await Future<void>.delayed(const Duration(milliseconds: 50));
      expect(cancelled, isTrue, reason: 'Conexão encerrada');
      expect(chunksRead, lessThan(5), reason: 'Interrompido antes de chegar aos 5 MB');
    });

    test('só aceita image/*', () async {
      final adapter = _FakeAdapter((_) => _image([60, 104, 116, 109, 108, 62], type: 'text/html'));
      expect(await NotificationCenter.downloadImage('https://cdn.sancta.app.br/a.png', dio: _dio(adapter)), isNull);
    });

    test('redirecionamento para endereço interno não é seguido; para público, é revalidado e seguido', () async {
      final toInternal = _FakeAdapter(
        (_) => ResponseBody(
          const Stream.empty(),
          302,
          headers: {
            'location': ['https://169.254.169.254/latest/meta-data/'],
          },
        ),
      );
      expect(await NotificationCenter.downloadImage('https://cdn.sancta.app.br/a.png', dio: _dio(toInternal)), isNull);
      expect(toInternal.requests, hasLength(1));

      final toPublic = _FakeAdapter(
        (options) => options.uri.path == '/a.png'
            ? ResponseBody(
                const Stream.empty(),
                301,
                headers: {
                  'location': ['https://img.sancta.app.br/b.png'],
                },
              )
            : _image([9, 9]),
      );
      expect(await NotificationCenter.downloadImage('https://cdn.sancta.app.br/a.png', dio: _dio(toPublic)), Uint8List.fromList([9, 9]));
      expect(toPublic.requests.map((u) => u.host), ['cdn.sancta.app.br', 'img.sancta.app.br']);

      final loop = _FakeAdapter(
        (_) => ResponseBody(
          const Stream.empty(),
          302,
          headers: {
            'location': ['https://cdn.sancta.app.br/a.png'],
          },
        ),
      );
      expect(await NotificationCenter.downloadImage('https://cdn.sancta.app.br/a.png', dio: _dio(loop)), isNull);
      expect(loop.requests.length, lessThanOrEqualTo(4));
    });
  });
}
