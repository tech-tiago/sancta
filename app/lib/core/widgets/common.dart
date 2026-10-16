import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../brand/brand.dart';
import '../motion/pressable.dart';
import '../motion/skeleton.dart';
import '../network/api_exception.dart';
import '../theme/app_theme.dart';

// ───────────────────────── Marca ─────────────────────────

/// Céu padrão para capas sem imagem, estável para a mesma chave (slug, categoria…).
SkyScene skyFor(String? key) {
  const scenes = [SkyScene.aurora, SkyScene.entardecer, SkyScene.noite];
  if (key == null || key.isEmpty) return SkyScene.aurora;
  final hash = key.codeUnits.fold<int>(7, (h, c) => (h * 31 + c) & 0x7fffffff);
  return scenes[hash % scenes.length];
}

// ───────────────────────── Títulos e seções ─────────────────────────

/// Título grande das abas (Orar, Bíblia, Igrejas, Você), com ações à direita.
class ScreenTitle extends StatelessWidget {
  const ScreenTitle(this.title, {super.key, this.subtitle, this.actions = const [], this.padding = const EdgeInsets.fromLTRB(20, 12, 8, 4)});

  final String title;
  final String? subtitle;
  final List<Widget> actions;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) => SafeArea(
    bottom: false,
    child: Padding(
      padding: padding,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Semantics(header: true, child: Text(title, style: context.text.headlineLarge)),
                if (subtitle != null) ...[const SizedBox(height: 2), Text(subtitle!, style: context.text.bodyMedium)],
              ],
            ),
          ),
          ...actions,
        ],
      ),
    ),
  );
}

/// Cabeçalho de seção ("Para hoje", "Jornadas"…), com ação opcional ("Ver tudo").
class SectionHeader extends StatelessWidget {
  const SectionHeader(this.title, {super.key, this.action, this.onAction, this.padding = const EdgeInsets.fromLTRB(20, 24, 10, 6)});

  final String title;
  final String? action;
  final VoidCallback? onAction;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) => Padding(
    padding: padding,
    child: Row(
      children: [
        Expanded(
          child: Semantics(header: true, child: Text(title, style: context.text.titleLarge?.copyWith(fontSize: 18))),
        ),
        if (action != null)
          TextButton(
            onPressed: onAction,
            style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 10), textStyle: context.text.labelLarge?.copyWith(fontSize: 14)),
            child: Text(action!),
          )
        else
          const SizedBox(height: 48),
      ],
    ),
  );
}

// ───────────────────────── Ícones e selos ─────────────────────────

/// Ícone em quadrado arredondado azul-claro (estilo "Para hoje").
class IconBadge extends StatelessWidget {
  const IconBadge(this.icon, {super.key, this.size = 40, this.color, this.background, this.child});

  final IconData? icon;
  final double size;
  final Color? color;
  final Color? background;

  /// Conteúdo alternativo ao [icon] (ex.: sigla de um livro, data).
  final Widget? child;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(color: background ?? p.iconBg, borderRadius: BorderRadius.circular(size * .3)),
      child: child ?? Icon(icon, size: size * .52, color: color ?? p.primary),
    );
  }
}

/// Selo **Sancta+** (Ouro com texto Tinta: 9,05:1).
class PremiumBadge extends StatelessWidget {
  const PremiumBadge({super.key, this.compact = false, this.label = 'Sancta+'});

  final bool compact;
  final String label;

  @override
  Widget build(BuildContext context) => Semantics(
    label: 'Conteúdo $label',
    excludeSemantics: true,
    child: Container(
      padding: EdgeInsets.symmetric(horizontal: compact ? 7 : 10, vertical: compact ? 2.5 : 4),
      decoration: BoxDecoration(color: BrandColors.ouro, borderRadius: BorderRadius.circular(SanctaRadius.pill)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (!compact) ...[const Icon(Icons.auto_awesome_rounded, size: 13, color: BrandColors.tinta), const SizedBox(width: 4)],
          Text(
            label,
            style: TextStyle(fontFamily: BrandFonts.figtree, fontSize: compact ? 10.5 : 12, height: 1.2, fontWeight: FontWeight.w700, color: BrandColors.tinta),
          ),
        ],
      ),
    ),
  );
}

/// Sequência de dias em oração: pílula azul com a chama Ouro (Ouro só sobre azul).
class StreakPill extends StatelessWidget {
  const StreakPill({super.key, required this.days, this.onTap});

  final int days;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final label = '$days ${days == 1 ? 'dia' : 'dias'}';
    return Semantics(
      button: onTap != null,
      label: 'Sequência de $label em oração',
      excludeSemantics: true,
      child: Material(
        color: BrandColors.azulManto,
        shape: const StadiumBorder(),
        child: InkWell(
          customBorder: const StadiumBorder(),
          onTap: onTap,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 30),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(8, 4, 11, 4),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.local_fire_department_rounded, size: 16, color: BrandColors.ouro),
                  const SizedBox(width: 3),
                  Text(
                    label,
                    style: const TextStyle(fontFamily: BrandFonts.figtree, fontWeight: FontWeight.w600, fontSize: 12.5, color: BrandColors.pergaminho),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Botão de play redondo (Azul Manto), como no cartão "Oração da manhã".
class PlayButton extends StatelessWidget {
  const PlayButton({super.key, required this.onPressed, this.size = 48, this.semanticLabel = 'Rezar agora', this.icon = Icons.play_arrow_rounded});

  final VoidCallback? onPressed;
  final double size;
  final String semanticLabel;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final box = size < 48 ? 48.0 : size;
    return Semantics(
      button: true,
      label: semanticLabel,
      excludeSemantics: true,
      child: SizedBox(
        width: box,
        height: box,
        child: Center(
          child: Material(
            color: p.primary,
            shape: const CircleBorder(),
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: onPressed,
              child: SizedBox(
                width: size,
                height: size,
                child: Icon(icon, color: p.onPrimary, size: size * .58),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

// ───────────────────────── Listas e cartões ─────────────────────────

/// Linha no estilo "Para hoje": ícone em quadrado azul-claro, título, subtítulo e chevron.
/// Com [card] = true vira um cartão isolado; dentro de [TileGroup], use `card: false`.
class SanctaTile extends StatelessWidget {
  const SanctaTile({
    super.key,
    required this.title,
    this.subtitle,
    this.icon,
    this.leading,
    this.trailing,
    this.onTap,
    this.card = true,
    this.titleColor,
    this.semanticLabel,
    this.padding = const EdgeInsets.fromLTRB(14, 12, 10, 12),
    this.subtitleMaxLines = 2,
  });

  final String title;
  final String? subtitle;
  final IconData? icon;

  /// Substitui o ícone padrão (ex.: miniatura em arco).
  final Widget? leading;

  /// Padrão: chevron quando há [onTap].
  final Widget? trailing;
  final VoidCallback? onTap;
  final bool card;
  final Color? titleColor;
  final String? semanticLabel;
  final EdgeInsets padding;
  final int subtitleMaxLines;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final lead = leading ?? (icon != null ? IconBadge(icon) : null);
    final trail = trailing ?? (onTap != null ? Icon(Icons.chevron_right_rounded, color: p.muted, size: 22) : null);

    final content = ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 60),
      child: Padding(
        padding: padding,
        child: Row(
          children: [
            if (lead != null) ...[lead, const SizedBox(width: 14)],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: context.text.titleSmall?.copyWith(color: titleColor),
                  ),
                  if (subtitle != null && subtitle!.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(subtitle!, maxLines: subtitleMaxLines, overflow: TextOverflow.ellipsis, style: context.text.bodySmall),
                  ],
                ],
              ),
            ),
            if (trail != null) ...[const SizedBox(width: 8), trail],
          ],
        ),
      ),
    );

    final tappable = onTap == null ? content : InkWell(onTap: onTap, child: content);
    final semantic = Semantics(button: onTap != null, label: semanticLabel, child: tappable);
    if (!card) return Material(type: MaterialType.transparency, child: semantic);

    return Material(
      color: p.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(SanctaRadius.tile),
        side: p.isDark ? BorderSide.none : BorderSide(color: p.outline),
      ),
      clipBehavior: Clip.antiAlias,
      child: semantic,
    );
  }
}

/// Grupo de linhas num único cartão, com divisórias finas (menus de Você e Ajustes).
class TileGroup extends StatelessWidget {
  const TileGroup({super.key, required this.children, this.dividerIndent = 68});

  final List<Widget> children;
  final double dividerIndent;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return Material(
      color: p.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(SanctaRadius.card),
        side: p.isDark ? BorderSide.none : BorderSide(color: p.outline),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          for (var i = 0; i < children.length; i++) ...[children[i], if (i < children.length - 1) Divider(height: 1, indent: dividerIndent, color: p.outline)],
        ],
      ),
    );
  }
}

/// Rótulo pequeno acima de grupos (Ajustes, Você).
class GroupLabel extends StatelessWidget {
  const GroupLabel(this.text, {super.key, this.padding = const EdgeInsets.fromLTRB(6, 26, 6, 10)});

  final String text;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) => Padding(
    padding: padding,
    child: Semantics(
      header: true,
      child: Text(text, style: context.text.titleSmall?.copyWith(color: context.sancta.textSoft)),
    ),
  );
}

/// Cartão de destaque em Azul Manto → Noite (texto Pergaminho), com imagem opcional ao fundo.
class HeroCard extends StatelessWidget {
  const HeroCard({super.key, required this.child, this.onTap, this.imageUrl, this.padding = const EdgeInsets.all(20), this.colors, this.radius = 24});

  final Widget child;
  final VoidCallback? onTap;
  final String? imageUrl;
  final EdgeInsets padding;
  final List<Color>? colors;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final body = Ink(
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: colors ?? [p.heroStart, p.heroEnd], begin: Alignment.topLeft, end: Alignment.bottomRight),
      ),
      child: Stack(
        children: [
          if (imageUrl != null) Positioned.fill(child: Opacity(opacity: .28, child: NetImage(imageUrl))),
          Padding(
            padding: padding,
            child: DefaultTextStyle.merge(
              style: TextStyle(color: p.onHero),
              child: IconTheme.merge(
                data: IconThemeData(color: p.onHero),
                child: child,
              ),
            ),
          ),
        ],
      ),
    );
    return Material(
      color: Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(radius)),
      clipBehavior: Clip.antiAlias,
      child: onTap == null ? body : InkWell(onTap: onTap, child: body),
    );
  }
}

/// Chip de filtro em pílula (selecionado: Azul Manto).
class SanctaChoiceChip extends StatelessWidget {
  const SanctaChoiceChip({super.key, required this.label, required this.selected, required this.onSelected, this.icon});

  final String label;
  final bool selected;
  final VoidCallback onSelected;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final fg = selected ? p.onPrimary : p.text;
    return Semantics(
      selected: selected,
      button: true,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Material(
          color: selected ? p.primary : p.surface,
          shape: StadiumBorder(side: BorderSide(color: selected ? p.primary : p.outline)),
          child: InkWell(
            customBorder: const StadiumBorder(),
            onTap: onSelected,
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 40, minWidth: 48),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (icon != null) ...[Icon(icon, size: 17, color: selected ? p.onPrimary : p.primary), const SizedBox(width: 6)],
                    Text(label, style: context.text.labelLarge?.copyWith(fontSize: 14, color: fg)),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Abas em pílula (segmentado), para AppBar.bottom ou corpo da tela. Use `Tab(height: 48, …)`:
/// a área de toque tem 48 dp e o indicador fica recuado 4 dp dentro dela.
class PillTabBar extends StatelessWidget implements PreferredSizeWidget {
  const PillTabBar({super.key, required this.tabs, this.isScrollable = false, this.margin = const EdgeInsets.fromLTRB(16, 4, 16, 10)});

  final List<Widget> tabs;
  final bool isScrollable;
  final EdgeInsets margin;

  @override
  Size get preferredSize => Size.fromHeight(48 + margin.vertical);

  @override
  Widget build(BuildContext context) => Container(
    margin: margin,
    decoration: BoxDecoration(color: context.sancta.surfaceAlt, borderRadius: BorderRadius.circular(SanctaRadius.pill)),
    child: TabBar(
      tabs: tabs,
      isScrollable: isScrollable,
      indicatorPadding: const EdgeInsets.all(4),
      padding: isScrollable ? const EdgeInsets.symmetric(horizontal: 4) : EdgeInsets.zero,
      tabAlignment: isScrollable ? TabAlignment.start : TabAlignment.fill,
    ),
  );
}

/// Barra de progresso fina em pílula.
class SanctaProgress extends StatelessWidget {
  const SanctaProgress({super.key, required this.value, this.height = 6, this.color, this.track});

  final double value;
  final double height;
  final Color? color;
  final Color? track;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(height),
    child: LinearProgressIndicator(
      value: value.clamp(0.0, 1.0),
      minHeight: height,
      color: color ?? context.sancta.primary,
      backgroundColor: track ?? context.sancta.surfaceAlt,
    ),
  );
}

// ───────────────────────── Imagens ─────────────────────────

/// Imagem de rede com cache em disco; sem imagem (ou com erro), mostra o céu da marca.
class NetImage extends StatelessWidget {
  const NetImage(this.url, {super.key, this.fit = BoxFit.cover, this.width, this.height, this.icon, this.scene, this.semanticLabel});

  final String? url;
  final BoxFit fit;
  final double? width;
  final double? height;

  /// Ícone opcional sobre o céu do espaço reservado.
  final IconData? icon;

  /// Céu do espaço reservado (padrão: aurora).
  final SkyScene? scene;

  /// Descrição da imagem para leitores de tela (`null` = decorativa).
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) {
    final placeholder = SizedBox(
      width: width,
      height: height,
      child: SkyArt(
        scene: scene ?? SkyScene.aurora,
        child: icon == null ? null : Center(child: Icon(icon, color: BrandColors.pergaminho.withValues(alpha: .92), size: 30)),
      ),
    );
    if (url == null || !url!.startsWith('http')) return ExcludeSemantics(child: placeholder);

    final image = CachedNetworkImage(
      imageUrl: url!,
      fit: fit,
      width: width,
      height: height,
      fadeInDuration: const Duration(milliseconds: 200),
      placeholder: (_, _) => placeholder,
      errorWidget: (_, _, _) => placeholder,
      memCacheWidth: width != null ? (width! * 3).round() : 900,
    );
    if (semanticLabel == null) return ExcludeSemantics(child: image);
    return Semantics(image: true, label: semanticLabel, child: image);
  }
}

/// Capa em arco: imagem da API, asset editorial local ou céu da marca, nessa ordem.
class ArchCover extends StatelessWidget {
  const ArchCover({super.key, this.url, this.assetPath, this.scene, this.aspectRatio = 3 / 4, this.width, this.bottomRadius = 14, this.child});

  final String? url;
  final String? assetPath;
  final SkyScene? scene;
  final double aspectRatio;
  final double? width;
  final double bottomRadius;

  /// Conteúdo opcional sobre a imagem (selos, duração).
  final Widget? child;

  @override
  Widget build(BuildContext context) {
    final frame = ArchFrame(
      aspectRatio: aspectRatio,
      bottomRadius: bottomRadius,
      backgroundColor: context.sancta.surfaceAlt,
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (url != null && url!.startsWith('http'))
            NetImage(url, scene: scene)
          else if (assetPath != null)
            Image.asset(assetPath!, fit: BoxFit.cover, cacheWidth: width != null ? (width! * 3).round() : 1200)
          else
            NetImage(url, scene: scene),
          ?child,
        ],
      ),
    );
    return width == null ? frame : SizedBox(width: width, child: frame);
  }
}

// ───────────────────────── Estados ─────────────────────────

/// Indicador de carregamento discreto (para listas e telas de conteúdo, prefira os esqueletos).
class LoadingView extends StatelessWidget {
  const LoadingView({super.key, this.message});

  final String? message;

  @override
  Widget build(BuildContext context) => Center(
    child: Semantics(
      label: message ?? 'Carregando',
      liveRegion: true,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SizedBox(width: 28, height: 28, child: CircularProgressIndicator(strokeWidth: 2.4, strokeCap: StrokeCap.round)),
          if (message != null) ...[const SizedBox(height: 14), Text(message!, style: context.text.bodyMedium)],
        ],
      ),
    ),
  );
}

/// Esqueleto de uma lista de linhas no estilo "Para hoje".
class SkeletonTiles extends StatelessWidget {
  const SkeletonTiles({super.key, this.count = 6, this.padding = const EdgeInsets.fromLTRB(20, 12, 20, 12), this.shrinkWrap = false});

  final int count;
  final EdgeInsets padding;
  final bool shrinkWrap;

  @override
  Widget build(BuildContext context) => Semantics(
    label: 'Carregando',
    child: ListView.separated(
      padding: padding,
      shrinkWrap: shrinkWrap,
      physics: shrinkWrap ? const NeverScrollableScrollPhysics() : null,
      itemCount: count,
      separatorBuilder: (_, _) => const SizedBox(height: 10),
      itemBuilder: (_, _) => const SkeletonTile(),
    ),
  );
}

/// Uma linha-esqueleto (ícone + duas linhas de texto).
class SkeletonTile extends StatelessWidget {
  const SkeletonTile({super.key});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: context.sancta.surface, borderRadius: BorderRadius.circular(SanctaRadius.tile)),
    child: const Row(
      children: [
        SanctaSkeleton(width: 40, height: 40),
        SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [SanctaSkeleton(width: 160, height: 14), SizedBox(height: 8), SanctaSkeleton(width: 110, height: 12)],
          ),
        ),
      ],
    ),
  );
}

/// Ícone dentro de um pequeno arco (estados vazio e de erro): a marca em cada tela.
class ArchIcon extends StatelessWidget {
  const ArchIcon(this.icon, {super.key, this.size = 76});

  final IconData icon;
  final double size;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return ExcludeSemantics(
      child: SizedBox(
        width: size,
        height: size * 1.18,
        child: ArchFrame(
          bottomRadius: 12,
          backgroundColor: p.iconBg,
          child: Align(
            alignment: const Alignment(0, .35),
            child: Icon(icon, size: size * .42, color: p.primary),
          ),
        ),
      ),
    );
  }
}

class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.error, this.onRetry});

  final Object error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final offline = error is ApiException && (error as ApiException).isOffline;
    final message = error is ApiException ? (error as ApiException).message : 'Não foi possível carregar agora.';
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ArchIcon(offline ? Icons.wifi_off_rounded : Icons.cloud_off_rounded),
            const SizedBox(height: 18),
            Text(offline ? 'Você está sem conexão' : 'Algo não saiu como esperado', textAlign: TextAlign.center, style: context.text.titleMedium),
            const SizedBox(height: 6),
            Text(message, textAlign: TextAlign.center, style: context.text.bodyMedium),
            if (onRetry != null) ...[
              const SizedBox(height: 20),
              OutlinedButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh_rounded), label: const Text('Tentar de novo')),
            ],
          ],
        ),
      ),
    );
  }
}

class EmptyView extends StatelessWidget {
  const EmptyView({super.key, required this.icon, required this.title, this.message, this.action});

  final IconData icon;
  final String title;
  final String? message;
  final Widget? action;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          ArchIcon(icon),
          const SizedBox(height: 18),
          Text(title, textAlign: TextAlign.center, style: context.text.titleMedium),
          if (message != null) ...[const SizedBox(height: 6), Text(message!, textAlign: TextAlign.center, style: context.text.bodyMedium)],
          if (action != null) ...[const SizedBox(height: 20), action!],
        ],
      ),
    ),
  );
}

/// Cartão tocável com o efeito de toque da marca (escala leve, ver `Pressable`).
class PressableCard extends StatelessWidget {
  const PressableCard({super.key, required this.child, this.onTap, this.semanticLabel});

  final Widget child;
  final VoidCallback? onTap;
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) => Semantics(
    button: onTap != null,
    label: semanticLabel,
    child: Pressable(onTap: onTap, child: child),
  );
}

// ───────────────────────── Utilidades ─────────────────────────

Color? parseHexColor(String? hex) {
  if (hex == null) return null;
  final clean = hex.replaceAll('#', '');
  if (clean.length != 6 && clean.length != 8) return null;
  final value = int.tryParse(clean.length == 6 ? 'FF$clean' : clean, radix: 16);
  return value == null ? null : Color(value);
}

void showMessage(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}

String errorText(Object e) => e is ApiException ? e.message : 'Algo deu errado. Tente novamente.';

// ───────────────────────── Letra grande (acessibilidade) ─────────────────────────

/// Escala de texto em uso (1 = padrão; o app aceita até 2× — ver `maxTextScale` em app/app.dart).
double textScaleOf(BuildContext context) => MediaQuery.textScalerOf(context).scale(1);

/// Letra grande o bastante para trocar grades de 2 colunas por uma só (e evitar quebrar palavras).
bool largeText(BuildContext context) => textScaleOf(context) > 1.45;

/// Altura de um carrossel horizontal que acompanha a letra do sistema: a capa tem tamanho fixo e só o
/// bloco de texto cresce ([textBlock] = altura do texto a 1×), até 2×.
double carouselHeight(BuildContext context, double base, {double textBlock = 70}) => base + textBlock * (textScaleOf(context) - 1).clamp(0.0, 1.0);
