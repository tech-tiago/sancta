import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

/// Converte links internos (sancta://...) usados em avisos, notificações e no widget em rotas do app.
/// Só destinos conhecidos são aceitos (lista fechada) e parâmetros são codificados; qualquer outro
/// esquema é ignorado (proteção contra links maliciosos). Nomes antigos continuam valendo
/// (`rezar` → Orar, `perfil` → Você, `configuracoes` → Ajustes).
String? deepLinkToRoute(String? link) {
  if (link == null || !link.startsWith('sancta://')) return null;
  final uri = Uri.tryParse(link);
  if (uri == null) return null;

  final segments = [uri.host, ...uri.pathSegments].where((s) => s.isNotEmpty).toList();
  if (segments.isEmpty) return '/hoje';
  final slug = segments.length > 1 ? Uri.encodeComponent(segments[1]) : null;
  final id = segments.length > 1 ? int.tryParse(segments[1]) : null;

  return switch (segments.first) {
    'hoje' || 'inicio' => '/hoje',
    'liturgia' => '/liturgia',
    'biblia' => '/biblia',
    'orar' || 'rezar' || 'oracoes' => '/orar',
    'oracao' when slug != null => '/oracao/$slug',
    'jornada' when slug != null => '/jornada/$slug',
    'igrejas' => '/igrejas',
    'igreja' when slug != null => '/igreja/$slug',
    'noticias' => '/noticias',
    'noticia' when id != null && id > 0 => '/noticia/$id',
    'ao-vivo' || 'live' => '/ao-vivo',
    'intencoes' => '/intencoes',
    'planos' || 'sancta-plus' => '/planos',
    'voce' || 'perfil' => '/voce',
    'ajustes' || 'configuracoes' => '/ajustes',
    _ => null,
  };
}

/// Abre um link: interno → navega; https → navegador; demais esquemas permitidos → app externo.
Future<void> openLink(BuildContext context, String? link, {bool inApp = false}) async {
  if (link == null || link.isEmpty) return;

  final route = deepLinkToRoute(link);
  if (route != null) {
    context.push(route);
    return;
  }

  final uri = Uri.tryParse(link);
  if (uri == null || !['https', 'http', 'tel', 'mailto'].contains(uri.scheme)) return;

  await launchUrl(uri, mode: inApp && uri.scheme.startsWith('http') ? LaunchMode.inAppBrowserView : LaunchMode.externalApplication);
}

Future<void> shareText(String text, {String? subject}) => SharePlus.instance.share(ShareParams(text: text, subject: subject));
