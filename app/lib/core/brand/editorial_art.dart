/// Catálogo de ilustrações editoriais do Sancta. Capas remotas do painel continuam prioritárias;
/// estes caminhos servem como fallback local e como linguagem visual padrão do produto.
abstract final class EditorialArt {
  static const oracaoManha = 'assets/images/editorial/oracao-manha.webp';
  static const oracaoNoite = 'assets/images/editorial/oracao-noite.webp';
  static const leituraBiblica = 'assets/images/editorial/leitura-biblica.webp';
  static const jornadaRosario = 'assets/images/editorial/jornada-rosario.webp';
  static const acesso = 'assets/images/editorial/fundo-acesso.webp';
  static const carloAcutis = 'assets/images/editorial/sao-carlo-acutis.webp';
  static const nossaSenhoraAparecida = 'assets/images/editorial/nossa-senhora-aparecida.webp';
  static const saoJose = 'assets/images/editorial/sao-jose.webp';
  static const saoFrancisco = 'assets/images/editorial/sao-francisco.webp';

  static String? forPrayer(String title, String slug) {
    final key = _normalize('$title $slug');
    if (_hasAny(key, ['manha', 'matinal', 'despertar', 'oferecimento'])) return oracaoManha;
    if (_hasAny(key, ['noite', 'completas', 'exame de consciencia', 'dormir'])) return oracaoNoite;
    if (_hasAny(key, ['rosario', 'terco'])) return jornadaRosario;
    if (_hasAny(key, ['biblia', 'evangelho', 'leitura', 'lectio', 'liturgia'])) return leituraBiblica;
    return null;
  }

  static String? forJourney(String title, String slug) {
    final key = _normalize('$title $slug');
    if (_hasAny(key, ['rosario', 'terco', 'aparecida', 'mariana', 'nossa senhora'])) return jornadaRosario;
    return null;
  }

  static String? forSaint(String name) {
    final key = _normalize(name);
    if (key.contains('carlo acutis')) return carloAcutis;
    if (key.contains('aparecida')) return nossaSenhoraAparecida;
    if (key.contains('sao jose')) return saoJose;
    if (key.contains('francisco de assis')) return saoFrancisco;
    return null;
  }

  static bool _hasAny(String value, List<String> choices) => choices.any(value.contains);

  static String _normalize(String value) => value
      .toLowerCase()
      .replaceAll(RegExp('[áàâã]'), 'a')
      .replaceAll(RegExp('[éê]'), 'e')
      .replaceAll(RegExp('[íì]'), 'i')
      .replaceAll(RegExp('[óôõ]'), 'o')
      .replaceAll(RegExp('[úù]'), 'u')
      .replaceAll('ç', 'c')
      .replaceAll(RegExp(r'\s+'), ' ')
      .trim();
}
