import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'network/api_client.dart';
import 'storage/cache_store.dart';
import 'storage/secure_store.dart';

/// Inicializado em main() antes do runApp.
final sharedPreferencesProvider = Provider<SharedPreferences>((ref) => throw UnimplementedError());

final secureStoreProvider = Provider<SecureStore>((ref) => SecureStore());

final cacheStoreProvider = Provider<CacheStore>((ref) => CacheStore());

final apiClientProvider = Provider<ApiClient>((ref) => ApiClient(secureStore: ref.watch(secureStoreProvider), cache: ref.watch(cacheStoreProvider)));
