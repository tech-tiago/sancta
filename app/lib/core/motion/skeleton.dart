import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/scheduler.dart';

import '../brand/brand_colors.dart';
import 'motion.dart';

/// Bloco de carregamento ("esqueleto") nas cores da marca, com um brilho suave que atravessa a tela.
///
/// - Base: Azul Manto a 7% (claro) ou Pergaminho a 8% (escuro); brilho: branco (claro) ou Azul Luz (escuro).
/// - O brilho usa coordenadas **da tela** e um relógio comum: todos os esqueletos visíveis formam uma
///   única faixa de luz que passa da esquerda para a direita a cada [SanctaMotion.shimmer].
/// - Cada esqueleto é uma camada própria (`isRepaintBoundary`): o brilho não repinta o resto da tela.
/// - O ticker para sozinho fora da tela (rotas cobertas, `TickerMode`) e com "reduzir movimento"
///   (fica só a base, estática).
///
/// Sem largura, ocupa toda a largura disponível (como um `Container` vazio).
class SanctaSkeleton extends StatefulWidget {
  const SanctaSkeleton({super.key, this.width, this.height = 16, this.radius = const BorderRadius.all(Radius.circular(12))});

  final double? width;
  final double height;
  final BorderRadius radius;

  @override
  State<SanctaSkeleton> createState() => _SanctaSkeletonState();
}

class _SanctaSkeletonState extends State<SanctaSkeleton> with SingleTickerProviderStateMixin {
  final _phase = ValueNotifier<double>(0);
  Ticker? _ticker;
  bool _reduced = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _reduced = SanctaMotion.reduced(context);
    if (_reduced) {
      _ticker?.stop();
    } else {
      _ticker ??= createTicker(_tick);
      if (!_ticker!.isActive) _ticker!.start();
    }
  }

  void _tick(Duration _) {
    // Relógio comum a todos os esqueletos (mesmo quadro = mesma fase).
    final period = SanctaMotion.shimmer.inMicroseconds;
    _phase.value = (SchedulerBinding.instance.currentFrameTimeStamp.inMicroseconds % period) / period;
  }

  @override
  void dispose() {
    _ticker?.dispose();
    _phase.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return SizedBox(
      width: widget.width,
      height: widget.height,
      child: _SkeletonBox(
        phase: _reduced ? null : _phase,
        base: dark ? BrandColors.pergaminho.withValues(alpha: .08) : BrandColors.azulManto.withValues(alpha: .07),
        highlight: dark ? BrandColors.azulLuz.withValues(alpha: .16) : BrandColors.branco.withValues(alpha: .62),
        radius: widget.radius,
        sweepWidth: MediaQuery.sizeOf(context).width,
      ),
    );
  }
}

class _SkeletonBox extends LeafRenderObjectWidget {
  const _SkeletonBox({required this.phase, required this.base, required this.highlight, required this.radius, required this.sweepWidth});

  final ValueListenable<double>? phase;
  final Color base;
  final Color highlight;
  final BorderRadius radius;
  final double sweepWidth;

  @override
  RenderObject createRenderObject(BuildContext context) =>
      _RenderSkeleton(phase: phase, base: base, highlight: highlight, radius: radius, sweepWidth: sweepWidth);

  @override
  void updateRenderObject(BuildContext context, _RenderSkeleton renderObject) => renderObject
    ..phase = phase
    ..base = base
    ..highlight = highlight
    ..radius = radius
    ..sweepWidth = sweepWidth;
}

class _RenderSkeleton extends RenderBox {
  _RenderSkeleton({
    required ValueListenable<double>? phase,
    required Color base,
    required Color highlight,
    required BorderRadius radius,
    required double sweepWidth,
  }) : _phase = phase,
       _base = base,
       _highlight = highlight,
       _radius = radius,
       _sweepWidth = sweepWidth;

  /// Fração do ciclo em que a faixa atravessa a tela (o resto é pausa).
  static const _sweepFraction = .72;

  ValueListenable<double>? _phase;
  set phase(ValueListenable<double>? value) {
    if (value == _phase) return;
    if (attached) _phase?.removeListener(markNeedsPaint);
    _phase = value;
    if (attached) _phase?.addListener(markNeedsPaint);
    markNeedsPaint();
  }

  Color _base;
  set base(Color value) {
    if (value == _base) return;
    _base = value;
    markNeedsPaint();
  }

  Color _highlight;
  set highlight(Color value) {
    if (value == _highlight) return;
    _highlight = value;
    markNeedsPaint();
  }

  BorderRadius _radius;
  set radius(BorderRadius value) {
    if (value == _radius) return;
    _radius = value;
    markNeedsPaint();
  }

  double _sweepWidth;
  set sweepWidth(double value) {
    if (value == _sweepWidth) return;
    _sweepWidth = value;
    markNeedsPaint();
  }

  @override
  bool get isRepaintBoundary => true;

  @override
  bool get sizedByParent => true;

  @override
  Size computeDryLayout(BoxConstraints constraints) => Size(
    constraints.hasBoundedWidth ? constraints.maxWidth : constraints.minWidth,
    constraints.hasBoundedHeight ? constraints.maxHeight : constraints.minHeight,
  );

  @override
  void attach(PipelineOwner owner) {
    super.attach(owner);
    _phase?.addListener(markNeedsPaint);
  }

  @override
  void detach() {
    _phase?.removeListener(markNeedsPaint);
    super.detach();
  }

  @override
  void paint(PaintingContext context, Offset offset) {
    if (size.isEmpty) return;
    final canvas = context.canvas;
    final rrect = _radius.toRRect(offset & size);
    canvas.drawRRect(rrect, Paint()..color = _base);

    final phase = _phase?.value;
    if (phase == null || phase >= _sweepFraction) return;
    final t = Curves.easeInOut.transform(phase / _sweepFraction);
    final sweep = math.max(_sweepWidth, size.width);
    final band = math.max(sweep * .45, 96.0);
    // Centro da faixa em coordenadas da tela → coordenadas locais deste bloco.
    final globalLeft = localToGlobal(Offset.zero).dx;
    final center = -band + t * (sweep + 2 * band) - globalLeft + offset.dx;
    if (center + band / 2 < offset.dx || center - band / 2 > offset.dx + size.width) return;
    final rect = Rect.fromLTWH(center - band / 2, offset.dy, band, size.height);
    canvas.drawRRect(
      rrect,
      Paint()
        ..shader = LinearGradient(
          colors: [_highlight.withValues(alpha: 0), _highlight, _highlight.withValues(alpha: 0)],
          stops: const [0, .5, 1],
        ).createShader(rect),
    );
  }
}
