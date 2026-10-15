import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Token de acesso guardado no armazenamento seguro do sistema
/// (Android Keystore / iOS Keychain) — nunca em SharedPreferences.
class SecureStore {
  SecureStore()
    : _storage = const FlutterSecureStorage(
        aOptions: AndroidOptions(),
        iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock_this_device),
      );

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'sancta.token';

  Future<String?> readToken() async {
    try {
      return await _storage.read(key: _tokenKey);
    } catch (_) {
      // Chave corrompida (ex.: restauração de backup em outro aparelho): descarta.
      await _storage.deleteAll();
      return null;
    }
  }

  Future<void> writeToken(String token) => _storage.write(key: _tokenKey, value: token);

  Future<void> clear() => _storage.delete(key: _tokenKey);
}
