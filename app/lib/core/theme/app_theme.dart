import 'package:flutter/material.dart';

import '../brand/brand.dart';
import '../motion/motion.dart';
import 'palette.dart';

/// Temas disponíveis (mesmos valores aceitos pela API e pelo site: system/light/dark/sepia).
enum SanctaTheme {
  system('Automático', 'Segue o tema do aparelho', Icons.brightness_auto_rounded),
  light('Claro', 'Pergaminho e Azul Manto', Icons.light_mode_rounded),
  dark('Escuro', 'Tinta e Noite, para rezar à noite', Icons.dark_mode_rounded),
  sepia('Sépia', 'Papel envelhecido, para leituras longas', Icons.menu_book_rounded);

  const SanctaTheme(this.label, this.description, this.icon);

  final String label;
  final String description;
  final IconData icon;

  static SanctaTheme fromValue(String? value) => SanctaTheme.values.firstWhere((t) => t.name == value, orElse: () => SanctaTheme.system);
}

/// Fonte de leitura da Bíblia, das orações e da liturgia (Ajustes → Leitura).
enum ReadingFont {
  padrao('Padrão', 'Figtree, clara e moderna', BrandFonts.figtree),
  classica('Clássica', 'EB Garamond, como nos livros', BrandFonts.ebGaramond);

  const ReadingFont(this.label, this.description, this.family);

  final String label;
  final String description;
  final String family;

  static ReadingFont fromValue(String? value) => ReadingFont.values.firstWhere((f) => f.name == value, orElse: () => ReadingFont.padrao);
}

/// Extensão com os tokens do Sancta acessíveis via `context.sancta`.
@immutable
class SanctaColors extends ThemeExtension<SanctaColors> {
  const SanctaColors(this.palette, {this.readingFont = ReadingFont.padrao});

  final SanctaPalette palette;
  final ReadingFont readingFont;

  @override
  SanctaColors copyWith({SanctaPalette? palette, ReadingFont? readingFont}) =>
      SanctaColors(palette ?? this.palette, readingFont: readingFont ?? this.readingFont);

  @override
  SanctaColors lerp(ThemeExtension<SanctaColors>? other, double t) => other is SanctaColors && t > .5 ? other : this;
}

extension SanctaThemeX on BuildContext {
  SanctaPalette get sancta => Theme.of(this).extension<SanctaColors>()?.palette ?? SanctaPalette.claro;
  TextTheme get text => Theme.of(this).textTheme;
}

/// Raios e espaçamentos usados em todo o app.
abstract final class SanctaRadius {
  static const double card = 20;
  static const double tile = 16;
  static const double icon = 12;
  static const double sheet = 28;
  static const double pill = 999;
}

class AppTheme {
  AppTheme._();

  /// Tipografia: Figtree Bold (700) para títulos, Medium/SemiBold (500/600) para interface e Regular (400) para texto.
  static TextTheme textTheme(SanctaPalette p) {
    TextStyle s(double size, FontWeight weight, Color color, {double? height, double spacing = 0}) =>
        TextStyle(fontFamily: BrandFonts.figtree, fontSize: size, fontWeight: weight, color: color, height: height, letterSpacing: spacing);

    return TextTheme(
      displayLarge: s(48, FontWeight.w700, p.text, height: 1.08, spacing: -1),
      displayMedium: s(40, FontWeight.w700, p.text, height: 1.1, spacing: -.8),
      displaySmall: s(34, FontWeight.w700, p.text, height: 1.12, spacing: -.6),
      headlineLarge: s(30, FontWeight.w700, p.text, height: 1.15, spacing: -.5),
      headlineMedium: s(27, FontWeight.w700, p.text, height: 1.18, spacing: -.4),
      headlineSmall: s(22, FontWeight.w700, p.text, height: 1.22, spacing: -.2),
      titleLarge: s(19, FontWeight.w700, p.text, height: 1.25, spacing: -.1),
      titleMedium: s(16.5, FontWeight.w600, p.text, height: 1.3),
      titleSmall: s(15, FontWeight.w600, p.text, height: 1.3),
      bodyLarge: s(16, FontWeight.w400, p.text, height: 1.5),
      bodyMedium: s(14.5, FontWeight.w400, p.textSoft, height: 1.45),
      bodySmall: s(13, FontWeight.w400, p.textSoft, height: 1.4),
      labelLarge: s(15, FontWeight.w600, p.text, height: 1.2),
      labelMedium: s(13, FontWeight.w500, p.textSoft, height: 1.25),
      labelSmall: s(12, FontWeight.w600, p.textSoft, height: 1.25, spacing: .3),
    );
  }

  static ThemeData build(SanctaPalette p, {ReadingFont readingFont = ReadingFont.padrao}) {
    final text = textTheme(p);
    final base = ThemeData(brightness: p.brightness, useMaterial3: true, fontFamily: BrandFonts.figtree);

    final scheme = ColorScheme(
      brightness: p.brightness,
      primary: p.primary,
      onPrimary: p.onPrimary,
      primaryContainer: p.iconBg,
      onPrimaryContainer: p.primary,
      secondary: p.heroStart,
      onSecondary: BrandColors.pergaminho,
      tertiary: BrandColors.ouro,
      onTertiary: BrandColors.tinta,
      error: p.danger,
      onError: p.isDark ? BrandColors.tinta : Colors.white,
      surface: p.surface,
      onSurface: p.text,
      onSurfaceVariant: p.textSoft,
      surfaceContainerLowest: p.surface,
      surfaceContainerLow: p.surface,
      surfaceContainer: p.surface,
      surfaceContainerHigh: p.surfaceAlt,
      surfaceContainerHighest: p.surfaceAlt,
      outline: p.outline,
      outlineVariant: p.outline,
      inverseSurface: p.text,
      onInverseSurface: p.background,
      shadow: BrandColors.tinta,
      surfaceTint: Colors.transparent,
    );

    final pill = RoundedRectangleBorder(borderRadius: BorderRadius.circular(SanctaRadius.pill));
    final buttonText = TextStyle(fontFamily: BrandFonts.figtree, fontWeight: FontWeight.w600, fontSize: 15.5, letterSpacing: .1);
    OutlineInputBorder border(Color color, [double width = 1]) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(SanctaRadius.tile),
      borderSide: BorderSide(color: color, width: width),
    );

    return base.copyWith(
      colorScheme: scheme,
      scaffoldBackgroundColor: p.background,
      canvasColor: p.background,
      textTheme: text,
      primaryTextTheme: text,
      extensions: [SanctaColors(p, readingFont: readingFont)],
      splashFactory: InkRipple.splashFactory,
      pageTransitionsTheme: SanctaMotion.pageTransitions,
      visualDensity: VisualDensity.standard,
      materialTapTargetSize: MaterialTapTargetSize.padded,
      iconTheme: IconThemeData(color: p.text, size: 24),
      dividerColor: p.outline,
      appBarTheme: AppBarTheme(
        backgroundColor: p.background,
        surfaceTintColor: Colors.transparent,
        foregroundColor: p.text,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleSpacing: 20,
        titleTextStyle: text.titleLarge,
        iconTheme: IconThemeData(color: p.text),
        actionsIconTheme: IconThemeData(color: p.text),
      ),
      cardTheme: CardThemeData(
        color: p.surface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SanctaRadius.card),
          side: BorderSide(color: p.outline),
        ),
      ),
      dividerTheme: DividerThemeData(color: p.outline, space: 1, thickness: 1),
      listTileTheme: ListTileThemeData(
        iconColor: p.primary,
        textColor: p.text,
        titleTextStyle: text.titleSmall,
        subtitleTextStyle: text.bodySmall,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16),
        minVerticalPadding: 10,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(SanctaRadius.tile)),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: p.surface,
        surfaceTintColor: Colors.transparent,
        indicatorColor: Colors.transparent,
        elevation: 0,
        height: 66,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (s) => TextStyle(
            fontFamily: BrandFonts.figtree,
            fontSize: 12,
            height: 1.2,
            fontWeight: s.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500,
            color: s.contains(WidgetState.selected) ? p.primary : p.textSoft,
          ),
        ),
        iconTheme: WidgetStateProperty.resolveWith((s) => IconThemeData(color: s.contains(WidgetState.selected) ? p.primary : p.muted, size: 24)),
      ),
      tabBarTheme: TabBarThemeData(
        indicator: BoxDecoration(color: p.primary, borderRadius: BorderRadius.circular(SanctaRadius.pill)),
        indicatorSize: TabBarIndicatorSize.tab,
        dividerColor: Colors.transparent,
        dividerHeight: 0,
        labelColor: p.onPrimary,
        unselectedLabelColor: p.textSoft,
        labelStyle: TextStyle(fontFamily: BrandFonts.figtree, fontWeight: FontWeight.w600, fontSize: 14),
        unselectedLabelStyle: TextStyle(fontFamily: BrandFonts.figtree, fontWeight: FontWeight.w500, fontSize: 14),
        labelPadding: const EdgeInsets.symmetric(horizontal: 16),
        overlayColor: WidgetStatePropertyAll(p.primary.withValues(alpha: .06)),
        splashBorderRadius: BorderRadius.circular(SanctaRadius.pill),
        tabAlignment: TabAlignment.fill,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: p.primary,
          foregroundColor: p.onPrimary,
          disabledBackgroundColor: p.primary.withValues(alpha: .35),
          disabledForegroundColor: p.onPrimary.withValues(alpha: .8),
          minimumSize: const Size(64, 52),
          padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
          shape: pill,
          textStyle: buttonText,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: p.surface,
          foregroundColor: p.primary,
          elevation: 0,
          minimumSize: const Size(64, 52),
          shape: pill,
          textStyle: buttonText,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: p.primary,
          minimumSize: const Size(64, 52),
          padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
          side: BorderSide(color: p.primary.withValues(alpha: p.isDark ? .55 : .35), width: 1.2),
          shape: pill,
          textStyle: buttonText,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: p.primary, minimumSize: const Size(48, 48), shape: pill, textStyle: buttonText.copyWith(fontSize: 15)),
      ),
      iconButtonTheme: IconButtonThemeData(style: IconButton.styleFrom(minimumSize: const Size(48, 48))),
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: p.primary,
        foregroundColor: p.onPrimary,
        elevation: 2,
        highlightElevation: 4,
        shape: pill,
        extendedTextStyle: buttonText,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: p.surface,
        contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        border: border(p.outline),
        enabledBorder: border(p.isDark ? p.outline : const Color(0xFFE3DBCB)),
        focusedBorder: border(p.primary, 1.6),
        errorBorder: border(p.danger),
        focusedErrorBorder: border(p.danger, 1.6),
        labelStyle: TextStyle(color: p.textSoft, fontFamily: BrandFonts.figtree),
        floatingLabelStyle: TextStyle(color: p.primary, fontFamily: BrandFonts.figtree, fontWeight: FontWeight.w600),
        hintStyle: TextStyle(color: p.textSoft, fontFamily: BrandFonts.figtree),
        helperStyle: TextStyle(color: p.textSoft, fontFamily: BrandFonts.figtree),
        prefixIconColor: p.textSoft,
        suffixIconColor: p.textSoft,
      ),
      chipTheme: ChipThemeData(
        backgroundColor: p.surface,
        selectedColor: p.primary,
        secondarySelectedColor: p.primary,
        side: BorderSide(color: p.outline),
        labelStyle: TextStyle(color: p.text, fontWeight: FontWeight.w600, fontFamily: BrandFonts.figtree, fontSize: 14),
        secondaryLabelStyle: TextStyle(color: p.onPrimary, fontWeight: FontWeight.w600, fontFamily: BrandFonts.figtree, fontSize: 14),
        checkmarkColor: p.onPrimary,
        iconTheme: IconThemeData(color: p.primary, size: 18),
        shape: pill,
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 6),
      ),
      segmentedButtonTheme: SegmentedButtonThemeData(
        style: ButtonStyle(
          backgroundColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? p.primary : p.surface),
          foregroundColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? p.onPrimary : p.text),
          iconColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? p.onPrimary : p.textSoft),
          side: WidgetStatePropertyAll(BorderSide(color: p.outline)),
          shape: WidgetStatePropertyAll(pill),
          minimumSize: const WidgetStatePropertyAll(Size(48, 44)),
          textStyle: WidgetStatePropertyAll(buttonText.copyWith(fontSize: 14)),
        ),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: p.surface,
        surfaceTintColor: Colors.transparent,
        modalBackgroundColor: p.surface,
        showDragHandle: true,
        dragHandleColor: p.muted.withValues(alpha: .5),
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(SanctaRadius.sheet))),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: p.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(SanctaRadius.sheet)),
        titleTextStyle: text.headlineSmall,
        contentTextStyle: text.bodyLarge,
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: p.isDark ? p.surfaceAlt : BrandColors.tinta,
        contentTextStyle: TextStyle(color: BrandColors.pergaminho, fontFamily: BrandFonts.figtree, fontSize: 14.5, fontWeight: FontWeight.w500),
        actionTextColor: p.isDark ? p.primary : BrandColors.nevoa,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(SanctaRadius.tile)),
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: p.primary, linearTrackColor: p.surfaceAlt, circularTrackColor: Colors.transparent),
      sliderTheme: SliderThemeData(
        activeTrackColor: p.primary,
        inactiveTrackColor: p.surfaceAlt,
        thumbColor: p.primary,
        overlayColor: p.primary.withValues(alpha: .12),
        valueIndicatorColor: p.primary,
        valueIndicatorTextStyle: TextStyle(color: p.onPrimary, fontFamily: BrandFonts.figtree, fontWeight: FontWeight.w600),
      ),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? p.onPrimary : p.muted),
        trackColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? p.primary : p.surfaceAlt),
        trackOutlineColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? p.primary : p.muted.withValues(alpha: .6)),
      ),
      checkboxTheme: CheckboxThemeData(
        fillColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? p.primary : Colors.transparent),
        checkColor: WidgetStatePropertyAll(p.onPrimary),
        side: BorderSide(color: p.muted, width: 1.6),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
      ),
      radioTheme: RadioThemeData(fillColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? p.primary : p.muted)),
      expansionTileTheme: ExpansionTileThemeData(
        iconColor: p.primary,
        collapsedIconColor: p.textSoft,
        textColor: p.text,
        collapsedTextColor: p.text,
        shape: const Border(),
        collapsedShape: const Border(),
        tilePadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 2),
      ),
      tooltipTheme: TooltipThemeData(
        decoration: BoxDecoration(color: p.isDark ? p.surfaceAlt : BrandColors.tinta, borderRadius: BorderRadius.circular(10)),
        textStyle: TextStyle(color: BrandColors.pergaminho, fontFamily: BrandFonts.figtree, fontSize: 13),
      ),
      timePickerTheme: TimePickerThemeData(backgroundColor: p.surface),
      datePickerTheme: DatePickerThemeData(backgroundColor: p.surface, surfaceTintColor: Colors.transparent),
    );
  }

  /// Estilo de leitura longa (Bíblia, orações, liturgia): Figtree por padrão ou EB Garamond ("Clássica").
  ///
  /// [size] é o corpo de referência da EB Garamond; a Figtree, de olho maior, usa 88% dele para a mesma
  /// sensação de tamanho (o ajuste de Ajustes → Leitura multiplica os dois).
  static TextStyle reading(BuildContext context, {double size = 20}) {
    final ext = Theme.of(context).extension<SanctaColors>();
    final color = ext?.palette.text ?? Theme.of(context).colorScheme.onSurface;
    if (ext?.readingFont == ReadingFont.classica) {
      return TextStyle(fontFamily: BrandFonts.ebGaramond, fontSize: size, height: 1.55, color: color, fontWeight: FontWeight.w500);
    }
    return TextStyle(fontFamily: BrandFonts.figtree, fontSize: size * .88, height: 1.62, color: color, fontWeight: FontWeight.w400);
  }
}
