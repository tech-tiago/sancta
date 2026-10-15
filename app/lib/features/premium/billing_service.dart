import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:in_app_purchase/in_app_purchase.dart';
import 'package:in_app_purchase_android/in_app_purchase_android.dart';

import '../../core/config/env.dart';
import '../../core/network/api_exception.dart';
import '../../core/providers.dart';
import '../../core/state/config_controller.dart';
import '../../core/state/session_controller.dart';

/// Oferta de assinatura do Google Play (plano-base ou oferta com teste grátis).
class StoreOffer {
  StoreOffer({required this.details, required this.basePlanId, required this.price, required this.hasFreeTrial});

  final ProductDetails details;
  final String basePlanId;
  final String price;
  final bool hasFreeTrial;
}

enum BillingStatus { idle, loading, purchasing, verifying, success, error }

class BillingState {
  const BillingState({this.status = BillingStatus.idle, this.offers = const {}, this.message, this.available = false});

  final BillingStatus status;

  /// basePlanId → melhor oferta (prioriza teste grátis).
  final Map<String, StoreOffer> offers;
  final String? message;
  final bool available;

  BillingState copyWith({BillingStatus? status, Map<String, StoreOffer>? offers, String? message, bool? available}) =>
      BillingState(status: status ?? this.status, offers: offers ?? this.offers, message: message, available: available ?? this.available);
}

/// Compras no app via Google Play Billing.
/// A confirmação de acesso é SEMPRE feita pelo servidor (Google Play Developer API).
class BillingController extends AutoDisposeNotifier<BillingState> {
  StreamSubscription<List<PurchaseDetails>>? _sub;

  InAppPurchase get _iap => InAppPurchase.instance;

  @override
  BillingState build() {
    ref.onDispose(() => _sub?.cancel());
    Future.microtask(_init);
    return const BillingState(status: BillingStatus.loading);
  }

  Future<void> _init() async {
    if (defaultTargetPlatform != TargetPlatform.android) {
      state = state.copyWith(status: BillingStatus.idle, available: false);
      return;
    }

    final available = await _iap.isAvailable();
    if (!available) {
      state = state.copyWith(status: BillingStatus.idle, available: false, message: 'Google Play indisponível neste aparelho.');
      return;
    }

    _sub = _iap.purchaseStream.listen(
      _onPurchases,
      onError: (Object e) {
        state = state.copyWith(status: BillingStatus.error, message: 'Não foi possível concluir a compra.');
      },
    );

    // IDs cadastrados nos planos do painel; sem configuração remota, o padrão do build.
    final productIds = {...ref.read(configValueProvider).productIds};
    if (productIds.isEmpty) productIds.add(Env.playSubscriptionId);
    final response = await _iap.queryProductDetails(productIds);
    final offers = <String, StoreOffer>{};

    for (final product in response.productDetails.whereType<GooglePlayProductDetails>()) {
      final index = product.subscriptionIndex;
      final subs = product.productDetails.subscriptionOfferDetails;
      if (index == null || subs == null) continue;

      final offer = subs[index];
      final trial = offer.pricingPhases.any((p) => p.priceAmountMicros == 0);
      final recurring = offer.pricingPhases.lastWhere((p) => p.priceAmountMicros > 0, orElse: () => offer.pricingPhases.last);
      final current = offers[offer.basePlanId];

      // Prefere a oferta com teste grátis; senão, o plano-base.
      if (current == null || (trial && !current.hasFreeTrial)) {
        offers[offer.basePlanId] = StoreOffer(details: product, basePlanId: offer.basePlanId, price: recurring.formattedPrice, hasFreeTrial: trial);
      }
    }

    state = state.copyWith(status: BillingStatus.idle, offers: offers, available: true);
  }

  Future<void> buy(String basePlanId) async {
    final offer = state.offers[basePlanId];
    if (offer == null) {
      state = state.copyWith(status: BillingStatus.error, message: 'Plano indisponível na loja no momento.');
      return;
    }

    // Vincula a compra à conta (o servidor confere esse identificador ofuscado).
    final accountId = ref.read(configValueProvider).obfuscatedAccountId;
    if (accountId == null) {
      await ref.read(configProvider.notifier).refresh();
    }

    state = state.copyWith(status: BillingStatus.purchasing);
    final param = GooglePlayPurchaseParam(productDetails: offer.details, applicationUserName: ref.read(configValueProvider).obfuscatedAccountId);
    try {
      await _iap.buyNonConsumable(purchaseParam: param);
    } catch (_) {
      state = state.copyWith(status: BillingStatus.error, message: 'Não foi possível iniciar a compra.');
    }
  }

  Future<void> restore() async {
    state = state.copyWith(status: BillingStatus.verifying);
    await _iap.restorePurchases();
    Future.delayed(const Duration(seconds: 8), () {
      if (state.status == BillingStatus.verifying) {
        state = state.copyWith(status: BillingStatus.idle, message: 'Nenhuma assinatura ativa encontrada nesta conta Google.');
      }
    });
  }

  Future<void> _onPurchases(List<PurchaseDetails> purchases) async {
    for (final purchase in purchases) {
      switch (purchase.status) {
        case PurchaseStatus.pending:
          state = state.copyWith(status: BillingStatus.purchasing, message: 'Pagamento pendente. Assim que for aprovado, seu acesso será liberado.');
        case PurchaseStatus.canceled:
          state = state.copyWith(status: BillingStatus.idle);
        case PurchaseStatus.error:
          state = state.copyWith(status: BillingStatus.error, message: purchase.error?.message ?? 'A compra não foi concluída.');
        case PurchaseStatus.purchased:
        case PurchaseStatus.restored:
          await _verify(purchase);
      }
      if (purchase.pendingCompletePurchase && purchase.status != PurchaseStatus.pending) {
        await _iap.completePurchase(purchase);
      }
    }
  }

  Future<void> _verify(PurchaseDetails purchase) async {
    state = state.copyWith(status: BillingStatus.verifying);
    try {
      await ref
          .read(apiClientProvider)
          .post('/billing/google-play/verify', data: {'product_id': purchase.productID, 'purchase_token': purchase.verificationData.serverVerificationData});
      await ref.read(sessionProvider.notifier).refreshUser();
      state = state.copyWith(status: BillingStatus.success, message: 'Bem-vindo ao Sancta+! ✨');
    } on ApiException catch (e) {
      state = state.copyWith(status: BillingStatus.error, message: e.message);
    }
  }
}

final billingProvider = AutoDisposeNotifierProvider<BillingController, BillingState>(BillingController.new);
