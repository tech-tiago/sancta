import 'dart:convert';
import 'dart:io';

import 'package:path_provider/path_provider.dart';

/// Cache simples em arquivos para respostas da API (uso offline e app mais leve):
/// tela inicial, livros e capítulos da Bíblia, orações abertas recentemente.
class CacheStore {
  Directory? _dir;

  Future<Directory> _directory() async {
    if (_dir != null) return _dir!;
    final base = await getApplicationCacheDirectory();
    _dir = Directory('${base.path}/sancta_api');
    if (!await _dir!.exists()) await _dir!.create(recursive: true);
    return _dir!;
  }

  String _fileName(String key) => base64Url.encode(utf8.encode(key)).replaceAll('=', '');

  Future<void> put(String key, Object? data) async {
    try {
      final dir = await _directory();
      final payload = jsonEncode({'t': DateTime.now().millisecondsSinceEpoch, 'd': data});
      await File('${dir.path}/${_fileName(key)}.json').writeAsString(payload, flush: true);
    } catch (_) {
      /* cache é opcional */
    }
  }

  /// Retorna o dado se existir e (opcionalmente) não estiver vencido.
  Future<Object?> get(String key, {Duration? maxAge}) async {
    try {
      final dir = await _directory();
      final file = File('${dir.path}/${_fileName(key)}.json');
      if (!await file.exists()) return null;
      final map = jsonDecode(await file.readAsString()) as Map<String, dynamic>;
      if (maxAge != null) {
        final age = DateTime.now().difference(DateTime.fromMillisecondsSinceEpoch(map['t'] as int));
        if (age > maxAge) return null;
      }
      return map['d'];
    } catch (_) {
      return null;
    }
  }

  Future<void> clear() async {
    try {
      final dir = await _directory();
      if (await dir.exists()) await dir.delete(recursive: true);
      _dir = null;
    } catch (_) {}
  }
}
