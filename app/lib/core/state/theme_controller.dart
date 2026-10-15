import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../theme/app_theme.dart';
import '../providers.dart';
import 'session_controller.dart';

/// Preferências de aparência: tema (4 opções), fonte e tamanho do texto de leitura.
class AppearanceState {
  const AppearanceState({this.theme = SanctaTheme.system, this.readingScale = 1.0, this.readingFont = ReadingFont.padrao});

  final SanctaTheme theme;
  final double readingScale;
  final ReadingFont readingFont;

  AppearanceState copyWith({SanctaTheme? theme, double? readingScale, ReadingFont? readingFont}) =>
      AppearanceState(theme: theme ?? this.theme, readingScale: readingScale ?? this.readingScale, readingFont: readingFont ?? this.readingFont);
}

class AppearanceController extends Notifier<AppearanceState> {
  static const _themeKey = 'sancta.theme';
  static const _scaleKey = 'sancta.reading_scale';
  static const _fontKey = 'sancta.reading_font';

  @override
  AppearanceState build() {
    final prefs = ref.read(sharedPreferencesProvider);
    return AppearanceState(
      theme: SanctaTheme.fromValue(prefs.getString(_themeKey)),
      readingScale: prefs.getDouble(_scaleKey) ?? 1.0,
      readingFont: ReadingFont.fromValue(prefs.getString(_fontKey)),
    );
  }

  Future<void> setTheme(SanctaTheme theme, {bool sync = true}) async {
    state = state.copyWith(theme: theme);
    await ref.read(sharedPreferencesProvider).setString(_themeKey, theme.name);

    // Sincroniza com a conta (o tema também vale no site).
    if (sync && ref.read(sessionProvider).isAuthenticated) {
      try {
        await ref.read(apiClientProvider).patch('/me', data: {'theme': theme.name});
      } catch (_) {}
    }
  }

  /// Aplica o tema salvo na conta ao entrar (sem reenviar ao servidor).
  void adoptFromAccount(String value) {
    final prefs = ref.read(sharedPreferencesProvider);
    if (prefs.getString(_themeKey) == null) setTheme(SanctaTheme.fromValue(value), sync: false);
  }

  Future<void> setReadingScale(double scale) async {
    final value = scale.clamp(0.8, 1.6);
    state = state.copyWith(readingScale: value);
    await ref.read(sharedPreferencesProvider).setDouble(_scaleKey, value);
  }

  Future<void> setReadingFont(ReadingFont font) async {
    state = state.copyWith(readingFont: font);
    await ref.read(sharedPreferencesProvider).setString(_fontKey, font.name);
  }
}

final appearanceProvider = NotifierProvider<AppearanceController, AppearanceState>(AppearanceController.new);
