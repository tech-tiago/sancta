# Kit editorial visual do Sancta

Este kit amplia a identidade **Portal** com uma biblioteca de ilustrações para site e aplicativo. O Portal, o wordmark, as cores funcionais e a tipografia continuam sendo a marca principal; as artes acrescentam humanidade, textura e reconhecimento às experiências de oração.

## Direção visual

- **Referência:** ilustração editorial de formas vetoriais claras combinada a recortes de papel e fragmentos de gravura devocional, conforme a imagem fornecida para o projeto.
- **Tratamento:** colagem em camadas, textura mate e grão de impressão sutil; silhuetas legíveis em miniatura e rostos humanos simples e serenos.
- **Tom:** reverente, acolhedor, contemporâneo e brasileiro; evitar aparência de banco de imagens, efeitos mágicos ou acabamento 3D.
- **Paleta das artes:** Azul Manto/índigo e Pergaminho como âncoras; vinho, terracota e dourado envelhecido como variações ilustrativas. Esta paleta é para as imagens — **não** altera os tokens de interface descritos em `MARCA.md`.
- **Composição:** imagens sem palavras; a interface aplica títulos, chamadas e informações como texto real. Usar o arco do Portal como moldura quando o recorte funcionar para o conteúdo.
- **Marca:** não inserir wordmark, símbolo Portal ou cruz como se fossem parte de uma ilustração. Usar os arquivos oficiais da marca por cima, quando necessário.

## Biblioteca criada

Os PNGs são os masters de alta resolução em `brand/editorial/`. Para uso em produção, os WebP otimizados estão duplicados nas pastas pública do backend e de assets do app.

| Asset | Uso recomendado | Master | WebP de produção |
|---|---|---|---|
| Hero mariano | Destaque principal do site, campanha editorial | `sancta-hero-marian.png` | `sancta-hero-marian.webp` |
| São Carlo Acutis | Santo do dia e jornadas dedicadas | `santa-carlo-acutis.png` | `sao-carlo-acutis.webp` |
| Nossa Senhora Aparecida | Santo do dia, novenas e devoção mariana | `nossa-senhora-aparecida.png` | `nossa-senhora-aparecida.webp` |
| São José | Santo do dia e oração pelas famílias | `sao-jose.png` | `sao-jose.webp` |
| São Francisco | Santo do dia e oração pela criação | `sao-francisco.png` | `sao-francisco.webp` |
| Oração da manhã | Home, oração matinal e card do período | `oracao-manha.png` | `oracao-manha.webp` |
| Oração da noite | Home, exame de consciência e oração noturna | `oracao-noite.png` | `oracao-noite.webp` |
| Leitura bíblica | Bíblia, Evangelho, liturgia e leitura guiada | `leitura-biblica.png` | `leitura-biblica.webp` |
| Jornada do Rosário | Novena, terço e jornada mariana | `jornada-rosario.png` | `jornada-rosario.webp` |
| Fundo de acesso | Login/cadastro web e cabeçalho de autenticação no app | `fundo-acesso.png` | `fundo-acesso.webp` |

Caminhos dos masters: `brand/editorial/<nome>.png`. Caminhos WebP:

- Site: `backend/public/images/editorial/<nome>.webp`
- App: `app/assets/images/editorial/<nome>.webp`

## Onde as artes já são usadas

- **Site:** composição do hero principal, lista visual dos momentos do dia, cards de Bíblia/igrejas e fundo das telas de acesso em desktop e celular.
- **App:** fundo de login/cadastro; capas locais de oração por horário/categoria; fallback de oração e jornada quando não há capa remota; retrato do santo do dia para Carlo Acutis, Nossa Senhora Aparecida, São José e São Francisco; cartão inicial e continuidade de leitura bíblica.
- **Prioridade de conteúdo:** quando a API ou o painel fornecer uma capa remota, ela continua tendo prioridade. A biblioteca local é fallback; capas não mapeadas mantêm o céu ilustrado já existente.

## Regras para futuras imagens

1. Definir primeiro a função: santo, oração, leitura, jornada, campanha, login ou seção do site; escolher enquadramento e recorte para essa função.
2. Manter uma família visual: papel recortado, vetor editorial, gravura discreta e textura impressa controlada; variar a paleta sem reinventar o estilo.
3. Em santos, representar atributos devocionais reconhecíveis e respeitosos, sem misturar iconografias nem inventar inscrições. Preferir retratos que continuem legíveis em cards pequenos.
4. Em oração e leitura, mostrar uma cena cotidiana verossímil, com poucos objetos e foco calmo. Evitar halos, raios e símbolos em excesso.
5. Gerar sem texto, sem logotipo e sem mockup de tela. Tratar palavras, títulos, botões e dados nos componentes reais.
6. Criar composições específicas para retrato e paisagem quando o corte puder esconder o elemento principal; nunca depender de um recorte que elimine o rosto, o livro ou o símbolo central.
7. Exportar master PNG e WebP otimizado; manter nomes sem acentos e registrar o novo asset nesta página e em `app/lib/core/brand/editorial_art.dart` quando ele for usado pelo app.
8. Respeitar contraste, acessibilidade, direitos de terceiros e as regras de uso de marca em `MARCA.md`.

### Brief-base para novas artes

> Create a Sancta editorial illustration for **[use case]**. Follow the approved visual family: simplified vector forms, layered matte paper collage, discreet devotional engraving fragments and subtle print grain. Use the Sancta palette as the anchor (manto indigo and parchment), with controlled wine, terracotta and aged-gold accents. Keep a clear focal point and a silhouette that remains legible at mobile-card size. Adapt the composition to **[aspect ratio and crop]**. No text, no lettering, no logo, no UI, no photorealism, no glossy 3D, no excessive glow, no malformed anatomy or decorative clutter.

## Manutenção

- Galeria de referência: `brand/index.html`.
- Manual da marca e Portal: [`MARCA.md`](MARCA.md).
- Catálogo usado pelo Flutter: `app/lib/core/brand/editorial_art.dart`.
- Todas as cópias WebP devem manter o mesmo nome-base nas pastas `backend/public/images/editorial/` e `app/assets/images/editorial/`.
