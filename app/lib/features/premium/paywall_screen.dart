import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/router.dart';
import '../../core/brand/brand.dart';
import '../../core/models/models.dart';
import '../../core/state/config_controller.dart';
import '../../core/state/content_providers.dart';
import '../../core/state/session_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/system_bars.dart';
import '../../core/utils/links.dart';
import '../../core/widgets/common.dart';
import 'billing_service.dart';

/// Ícone de cada chave de benefício da API (`benefit_items[].icon`).
IconData? benefitIcon(String key) => switch (key) {
  'headphones' => Icons.headphones_rounded,
  'flame' => Icons.local_fire_department_rounded,
  'sun' => Icons.wb_sunny_rounded,
  'sparkles' => Icons.auto_awesome_rounded,
  'book' => Icons.menu_book_rounded,
  'calendar' => Icons.calendar_month_rounded,
  'heart' => Icons.favorite_rounded,
  'cross' => null, // desenhado como cruz latina
  'chalice' => Icons.wine_bar_rounded,
  'play' => Icons.play_circle_rounded,
  'moon' => Icons.bedtime_rounded,
  'users' => Icons.groups_rounded,
  'shield' => Icons.verified_user_rounded,
  _ => Icons.star_rounded,
};

/// Etapas da linha do tempo do teste grátis: hoje → lembrete (2 dias antes) → cobrança.
List<({String when, String title, String text})> trialTimeline(int trialDays) => [
  (when: 'Hoje', title: 'Acesso completo', text: 'Todo o Sancta+ liberado, sem pagar nada.'),
  if (trialDays > 2) (when: 'Dia ${trialDays - 2}', title: 'Lembrete', text: 'Avisamos que o teste está acabando.'),
  (when: 'Dia $trialDays', title: 'Início da cobrança', text: 'Cancele antes no Google Play e não pague nada.'),
];

/// Tela de assinatura (estilo Hallow/Calm): herói em arco, benefícios, planos com economia,
/// linha do tempo do teste grátis, depoimentos e perguntas frequentes. Compra só pelo Google Play.
class PaywallScreen extends ConsumerStatefulWidget {
  const PaywallScreen({super.key});

  @override
  ConsumerState<PaywallScreen> createState() => _PaywallScreenState();
}

class _PaywallScreenState extends ConsumerState<PaywallScreen> {
  String? _selected;

  void _close() => context.canPop() ? context.pop() : context.go('/hoje');

  @override
  Widget build(BuildContext context) {
    final config = ref.watch(configValueProvider);
    final content = config.paywall;
    final plans = ref.watch(plansProvider);
    final billing = ref.watch(billingProvider);
    final session = ref.watch(sessionProvider);
    final p = context.sancta;

    ref.listen(billingProvider, (prev, next) {
      if (next.message != null && next.message != prev?.message) showMessage(context, next.message!);
      if (next.status == BillingStatus.success && context.mounted) _close();
    });

    final busy = billing.status == BillingStatus.purchasing || billing.status == BillingStatus.verifying;
    final playPlans = (plans.valueOrNull ?? const <PlanItem>[]).where((pl) => pl.playBasePlanId != null).toList();
    _selected ??= playPlans.where((pl) => pl.isFeatured).firstOrNull?.playBasePlanId ?? playPlans.firstOrNull?.playBasePlanId;
    final selected = playPlans.where((pl) => pl.playBasePlanId == _selected).firstOrNull;
    final offer = billing.offers[_selected];
    final trialDays = selected == null ? 0 : (selected.hasTrial && (offer?.hasFreeTrial ?? true) ? selected.trialDays : 0);

    return Scaffold(
      body: CustomScrollView(
        slivers: [
          SliverToBoxAdapter(
            child: _Hero(
              content: content,
              premiumName: config.premiumName,
              onClose: _close,
              onRestore: busy ? null : () => ref.read(billingProvider.notifier).restore(),
            ),
          ),
          if (content.benefits.isNotEmpty) ...[
            const SliverToBoxAdapter(child: SectionHeader('O que você recebe', padding: EdgeInsets.fromLTRB(20, 26, 20, 10))),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: TileGroup(children: [for (final b in content.benefits) _BenefitRow(benefit: b)]),
              ),
            ),
          ],
          if (!session.isPremium) ...[
            const SliverToBoxAdapter(child: SectionHeader('Escolha seu plano', padding: EdgeInsets.fromLTRB(20, 26, 20, 10))),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: plans.when(
                  loading: () => const SkeletonTiles(count: 2, shrinkWrap: true, padding: EdgeInsets.zero),
                  error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(plansProvider)),
                  data: (_) => playPlans.isEmpty
                      ? const EmptyView(icon: Icons.storefront_outlined, title: 'Planos indisponíveis', message: 'Tente novamente em instantes.')
                      : Column(
                          children: [
                            for (final plan in playPlans)
                              _PlanOption(
                                plan: plan,
                                offer: billing.offers[plan.playBasePlanId],
                                selected: _selected == plan.playBasePlanId,
                                onTap: () => setState(() => _selected = plan.playBasePlanId),
                              ),
                          ],
                        ),
                ),
              ),
            ),
            if (trialDays > 0)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                  child: _TrialTimeline(days: trialDays),
                ),
              ),
          ],
          if (content.testimonials.isNotEmpty) ...[
            const SliverToBoxAdapter(child: SectionHeader('Quem reza com o Sancta+', padding: EdgeInsets.fromLTRB(20, 26, 20, 10))),
            SliverToBoxAdapter(
              child: SizedBox(
                height: carouselHeight(context, 176, textBlock: 150),
                child: ListView.separated(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  scrollDirection: Axis.horizontal,
                  itemCount: content.testimonials.length,
                  separatorBuilder: (_, _) => const SizedBox(width: 12),
                  itemBuilder: (_, i) => _TestimonialCard(item: content.testimonials[i]),
                ),
              ),
            ),
          ],
          if (content.faq.isNotEmpty) ...[
            const SliverToBoxAdapter(child: SectionHeader('Perguntas frequentes', padding: EdgeInsets.fromLTRB(20, 26, 20, 10))),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: TileGroup(
                  dividerIndent: 18,
                  children: [
                    for (final f in content.faq)
                      Theme(
                        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
                        child: ExpansionTile(
                          title: Text(f.question, style: context.text.titleSmall),
                          childrenPadding: const EdgeInsets.fromLTRB(18, 0, 18, 16),
                          expandedAlignment: Alignment.centerLeft,
                          children: [Text(f.answer, style: context.text.bodyMedium)],
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ],
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 24, 20, 24),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.verified_user_outlined, size: 20, color: p.primary),
                      const SizedBox(width: 8),
                      Flexible(child: Text(content.guarantee, style: context.text.titleSmall)),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Text(
                    'Cobrança pelo Google Play. A assinatura renova automaticamente e pode ser cancelada a qualquer momento em '
                    'Play Store › Pagamentos e assinaturas. No teste grátis, cancele antes do fim para não ser cobrado.',
                    textAlign: TextAlign.center,
                    style: context.text.bodySmall,
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    alignment: WrapAlignment.center,
                    children: [
                      TextButton(onPressed: busy ? null : () => ref.read(billingProvider.notifier).restore(), child: const Text('Restaurar compras')),
                      TextButton(onPressed: () => context.push('/pagina/termos-de-uso'), child: const Text('Termos')),
                      TextButton(onPressed: () => context.push('/pagina/politica-de-privacidade'), child: const Text('Privacidade')),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: _CtaBar(
        premium: session.isPremium,
        busy: busy,
        enabled: selected != null,
        label: selected == null
            ? 'Assinar'
            : trialDays > 0
            ? 'Começar $trialDays dias grátis'
            : 'Assinar por ${offer?.price ?? selected.priceFormatted}',
        note: selected == null
            ? content.guarantee
            : trialDays > 0
            ? 'Depois, ${offer?.price ?? selected.priceFormatted} ${selected.periodLabel} · ${content.guarantee}'
            : '${selected.periodLabel.isEmpty ? '' : '${selected.periodLabel[0].toUpperCase()}${selected.periodLabel.substring(1)} · '}${content.guarantee}',
        manageUrl: session.user?.subscription?.manageUrl,
        onSubscribe: () {
          if (!requireAccount(context, ref, reason: 'Crie sua conta para assinar e acessar o Sancta+ em qualquer aparelho.')) return;
          ref.read(billingProvider.notifier).buy(_selected!);
        },
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.content, required this.premiumName, required this.onClose, required this.onRestore});

  final PaywallContent content;
  final String premiumName;
  final VoidCallback onClose;
  final VoidCallback? onRestore;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return DarkSystemBars(
      child: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(colors: [p.heroStart, p.heroEnd], begin: Alignment.topCenter, end: Alignment.bottomCenter),
          borderRadius: const BorderRadius.vertical(bottom: Radius.circular(32)),
        ),
        child: SafeArea(
          bottom: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(8, 4, 8, 28),
            child: Column(
              children: [
                Row(
                  children: [
                    IconButton(
                      tooltip: 'Fechar',
                      onPressed: onClose,
                      icon: const Icon(Icons.close_rounded, color: BrandColors.pergaminho),
                    ),
                    const Spacer(),
                    TextButton(
                      onPressed: onRestore,
                      style: TextButton.styleFrom(foregroundColor: BrandColors.nevoa),
                      child: const Text('Restaurar'),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                SizedBox(
                  width: 148,
                  child: ArchFrame(
                    aspectRatio: .8,
                    bottomRadius: 16,
                    borderColor: const Color(0x33FBF6EC),
                    borderWidth: 1.5,
                    child: SkyArt(
                      scene: SkyScene.noite,
                      child: const Align(
                        alignment: Alignment(0, .62),
                        child: PortalMark.pergaminho(height: 54, shape: PortalShape.door),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 20),
                PremiumBadge(label: premiumName),
                const SizedBox(height: 12),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Column(
                    children: [
                      Semantics(
                        header: true,
                        child: Text(
                          content.headline,
                          textAlign: TextAlign.center,
                          style: context.text.headlineMedium?.copyWith(color: BrandColors.pergaminho),
                        ),
                      ),
                      if (content.subheadline != null) ...[
                        const SizedBox(height: 8),
                        Text(
                          content.subheadline!,
                          textAlign: TextAlign.center,
                          style: context.text.bodyLarge?.copyWith(color: BrandColors.nevoa),
                        ),
                      ],
                      if (content.socialProof != null) ...[
                        const SizedBox(height: 16),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            ExcludeSemantics(
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [for (var i = 0; i < 5; i++) const Icon(Icons.star_rounded, size: 16, color: BrandColors.ouro)],
                              ),
                            ),
                            const SizedBox(width: 8),
                            Flexible(
                              child: Text(content.socialProof!, style: context.text.labelMedium?.copyWith(color: BrandColors.nevoa)),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _BenefitRow extends StatelessWidget {
  const _BenefitRow({required this.benefit});

  final PremiumBenefit benefit;

  @override
  Widget build(BuildContext context) {
    final icon = benefitIcon(benefit.icon);
    return SanctaTile(
      card: false,
      leading: IconBadge(icon, child: icon == null ? LatinCross(color: context.sancta.primary, size: 20) : null),
      title: benefit.title,
      subtitle: benefit.description,
      subtitleMaxLines: 3,
    );
  }
}

/// Cruz latina simples (benefício "cross"), nas proporções da cruz do Portal.
class LatinCross extends StatelessWidget {
  const LatinCross({super.key, required this.color, this.size = 20});

  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: size,
    height: size,
    child: CustomPaint(painter: _CrossPainter(color)),
  );
}

class _CrossPainter extends CustomPainter {
  const _CrossPainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final h = size.height;
    final stem = w * .2;
    final paint = Paint()..color = color;
    canvas.drawRRect(RRect.fromRectAndRadius(Rect.fromLTWH((w - stem) / 2, 0, stem, h), Radius.circular(stem * .2)), paint);
    canvas.drawRRect(RRect.fromRectAndRadius(Rect.fromLTWH(w * .14, h * .26, w * .72, stem), Radius.circular(stem * .2)), paint);
  }

  @override
  bool shouldRepaint(_CrossPainter oldDelegate) => oldDelegate.color != color;
}

class _PlanOption extends StatelessWidget {
  const _PlanOption({required this.plan, required this.offer, required this.selected, required this.onTap});

  final PlanItem plan;
  final StoreOffer? offer;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    // Preço local da Play Store quando disponível; equivalente mensal e "de/por" só valem para o preço do servidor.
    final price = offer?.price ?? plan.priceFormatted;
    final serverPrice = offer == null || offer!.price == plan.priceFormatted;
    final trial = plan.hasTrial && (offer?.hasFreeTrial ?? true);
    final ribbon = plan.isFeatured ? (plan.badge ?? 'Recomendado') : null;
    final details = [
      if (trial) '${plan.trialDays} dias grátis',
      if (plan.showMonthlyEquivalent && serverPrice) '${plan.monthlyEquivalent}/mês',
      if (!trial && plan.description != null && !plan.showMonthlyEquivalent) plan.description!,
    ].join(' · ');

    final card = AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      margin: EdgeInsets.only(top: ribbon != null ? 12 : 0, bottom: 12),
      decoration: BoxDecoration(
        color: p.surface,
        borderRadius: BorderRadius.circular(SanctaRadius.card),
        border: Border.all(color: selected ? p.primary : (p.isDark ? p.surfaceAlt : p.outline), width: selected ? 2 : 1),
      ),
      child: Material(
        type: MaterialType.transparency,
        child: InkWell(
          borderRadius: BorderRadius.circular(SanctaRadius.card),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 16),
            child: Row(
              children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  width: 24,
                  height: 24,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: selected ? p.primary : Colors.transparent,
                    border: Border.all(color: selected ? p.primary : p.muted, width: 2),
                  ),
                  child: selected ? Icon(Icons.check_rounded, size: 16, color: p.onPrimary) : null,
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Wrap(
                        spacing: 8,
                        runSpacing: 4,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          Text(plan.name, style: context.text.titleMedium),
                          if (plan.savingsPercent != null) _SavingsPill(percent: plan.savingsPercent!),
                        ],
                      ),
                      if (details.isNotEmpty) ...[const SizedBox(height: 3), Text(details, style: context.text.bodySmall)],
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    if (plan.compareAtFormatted != null && serverPrice)
                      Text(
                        plan.compareAtFormatted!,
                        style: context.text.bodySmall?.copyWith(decoration: TextDecoration.lineThrough, decorationColor: p.textSoft),
                      ),
                    Text(price, style: context.text.titleLarge),
                    Text(plan.periodLabel, style: context.text.bodySmall),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );

    return Semantics(
      inMutuallyExclusiveGroup: true,
      checked: selected,
      button: true,
      label: [
        'Plano ${plan.name}',
        '$price ${plan.periodLabel}',
        if (plan.savingsPercent != null) 'economize ${plan.savingsPercent}%',
        if (trial) '${plan.trialDays} dias grátis',
        ?ribbon,
      ].join(', '),
      excludeSemantics: true,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          card,
          if (ribbon != null)
            Positioned(
              top: 0,
              left: 18,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(color: p.primary, borderRadius: BorderRadius.circular(SanctaRadius.pill)),
                child: Text(ribbon, style: context.text.labelSmall?.copyWith(color: p.onPrimary, fontSize: 11.5)),
              ),
            ),
        ],
      ),
    );
  }
}

class _SavingsPill extends StatelessWidget {
  const _SavingsPill({required this.percent});

  final int percent;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2.5),
    decoration: BoxDecoration(color: BrandColors.ouro, borderRadius: BorderRadius.circular(SanctaRadius.pill)),
    child: Text(
      'Economize $percent%',
      style: const TextStyle(fontFamily: BrandFonts.figtree, fontSize: 11.5, fontWeight: FontWeight.w700, color: BrandColors.tinta, height: 1.25),
    ),
  );
}

/// "Hoje: acesso completo → Dia N-2: lembrete → Dia N: cobrança".
class _TrialTimeline extends StatelessWidget {
  const _TrialTimeline({required this.days});

  final int days;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final steps = trialTimeline(days);
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
      decoration: BoxDecoration(color: p.surfaceAlt, borderRadius: BorderRadius.circular(SanctaRadius.card)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Como funciona o teste grátis', style: context.text.titleSmall),
          const SizedBox(height: 12),
          for (var i = 0; i < steps.length; i++)
            IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  SizedBox(
                    width: 28,
                    child: Column(
                      children: [
                        Container(
                          width: 22,
                          height: 22,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: i == 0 ? p.primary : p.surface,
                            border: Border.all(color: p.primary, width: 2),
                          ),
                          child: Icon(
                            i == 0 ? Icons.lock_open_rounded : (i == steps.length - 1 ? Icons.credit_card_rounded : Icons.notifications_none_rounded),
                            size: 12,
                            color: i == 0 ? p.onPrimary : p.primary,
                          ),
                        ),
                        if (i < steps.length - 1) Expanded(child: Container(width: 2, color: p.primary.withValues(alpha: .35))),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 14),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('${steps[i].when} · ${steps[i].title}', style: context.text.titleSmall),
                          Text(steps[i].text, style: context.text.bodySmall),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _TestimonialCard extends StatelessWidget {
  const _TestimonialCard({required this.item});

  final Testimonial item;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 270,
    child: HeroCard(
      padding: const EdgeInsets.all(18),
      radius: SanctaRadius.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Semantics(
            label: 'Nota ${item.rating} de 5',
            excludeSemantics: true,
            child: Row(
              children: [for (var i = 0; i < 5; i++) Icon(Icons.star_rounded, size: 16, color: i < item.rating ? BrandColors.ouro : const Color(0x40FBF6EC))],
            ),
          ),
          const SizedBox(height: 10),
          Expanded(
            child: Text(
              '“${item.content}”',
              maxLines: largeText(context) ? 6 : 4,
              overflow: TextOverflow.ellipsis,
              style: context.text.bodyMedium?.copyWith(color: BrandColors.pergaminho),
            ),
          ),
          Text(item.author, style: context.text.labelMedium?.copyWith(color: BrandColors.nevoa)),
        ],
      ),
    ),
  );
}

/// Barra fixa com o botão de assinatura (ou o estado de assinante).
class _CtaBar extends StatelessWidget {
  const _CtaBar({
    required this.premium,
    required this.busy,
    required this.enabled,
    required this.label,
    required this.note,
    required this.onSubscribe,
    this.manageUrl,
  });

  final bool premium;
  final bool busy;
  final bool enabled;
  final String label;
  final String note;
  final VoidCallback onSubscribe;
  final String? manageUrl;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    return Container(
      decoration: BoxDecoration(
        color: p.surface,
        border: Border(top: BorderSide(color: p.outline)),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 10),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: premium
                ? [
                    Text('Você já é Sancta+. Obrigado por rezar conosco.', textAlign: TextAlign.center, style: context.text.titleSmall),
                    const SizedBox(height: 10),
                    OutlinedButton(
                      onPressed: () => openLink(context, manageUrl ?? 'https://play.google.com/store/account/subscriptions'),
                      child: const Text('Gerenciar assinatura'),
                    ),
                  ]
                : [
                    FilledButton(
                      onPressed: busy || !enabled ? null : onSubscribe,
                      style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(56)),
                      child: busy ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2.4)) : Text(label),
                    ),
                    const SizedBox(height: 8),
                    Text(note, textAlign: TextAlign.center, style: context.text.bodySmall),
                  ],
          ),
        ),
      ),
    );
  }
}
