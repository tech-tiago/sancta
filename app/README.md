# Sancta — App Flutter

App Android/iOS do Sancta. Todo o conteúdo vem da API do backend (gerenciado pelo painel); o app só guarda em cache o necessário para funcionar offline.

## Arquitetura

```
lib/
  main.dart                 inicialização (Firebase opcional, preferências, locale pt-BR)
  app/                      MaterialApp, rotas (go_router), barra inferior, splash, bloqueio por versão/manutenção
  core/
    config/env.dart         URL da API e IDs públicos via --dart-define (nenhum segredo no APK)
    network/                cliente HTTP (Dio) com token seguro, erros amigáveis e cache offline
    storage/                token no Keystore/Keychain (flutter_secure_storage) e cache em arquivos
    state/                  Riverpod: sessão, configuração remota, aparência (4 temas), provedores de conteúdo
    theme/                  paleta (Vigília, Aurora, Pergaminho) e tema Material 3
    models/                 modelos tipados e tolerantes a dados incompletos
    widgets/                componentes comuns e renderizador de blocos (orações litúrgicas sem WebView)
  features/
    auth/                   boas-vindas, login (e-mail/Google), cadastro, recuperação de senha, modo visitante
    home/                   "Hoje": versículo, liturgia, jornadas, orações, santo, lives, eventos, notícias
    prayers/ journeys/      biblioteca, leitor/áudio de orações, jornadas/novenas com progresso
    bible/                  índice, leitor com destaques/notas/marcadores/IA/voz, busca, anotações
    liturgy/                liturgia diária por data
    churches/               mapa OpenStreetMap, perto de mim, detalhes, favoritos, sugestões/correções
    news/ live/ community/  notícias, ao vivo (YouTube), intenções de oração
    profile/                perfil, estatísticas, configurações, favoritos, ajuda, páginas legais, exclusão de conta
    premium/                paywall e Google Play Billing (validação no servidor)
    announcements/          pop-ups e banners do painel (frequência controlada)
    notifications/          push (FCM), tópicos por público e deep links sancta://
```

## Executar

```bash
flutter pub get
```

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

## Publicar

Veja [`../docs/IMPLANTACAO.md`](../docs/IMPLANTACAO.md) (assinatura com a keystore atual, ofuscação, Play Billing, Login com Google).
