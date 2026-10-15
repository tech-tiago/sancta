// Dados falsos (mesmo formato da API, ver docs/API.md) para os testes de tela.

Map<String, dynamic> userJson({bool premium = false}) => {
  'id': 'u-1',
  'name': 'Ana Clara Souza',
  'first_name': 'Ana',
  'email': 'ana@exemplo.com',
  'email_verified': true,
  'has_password': true,
  'theme': 'system',
  'is_premium': premium,
  'subscription': premium ? {'plan': 'Anual', 'status': 'active', 'provider': 'google_play', 'auto_renew': true, 'ends_at': '2027-10-04T00:00:00-03:00'} : null,
  'stats': {'current_streak': 5, 'longest_streak': 12, 'total_prayer_minutes': 248},
  'notification_preferences': {'daily_reminder': true, 'reminder_time': '07:00', 'liturgy': true, 'news': false, 'churches': true},
};

const configJson = <String, dynamic>{
  'app': {'name': 'Sancta', 'support_email': 'contato@sancta.app.br', 'play_store_url': 'https://play.google.com/store/apps/details?id=br.app.sancta'},
  'features': {'live': true, 'intentions': true, 'news': true, 'guest_mode': true, 'ai_explanations': true},
  'premium': {
    'name': 'Sancta+',
    'headline': 'Aprofunde sua vida de oração',
    'subheadline': 'Orações guiadas, jornadas e reflexões para rezar todos os dias — no app e no site, com a mesma conta.',
    'benefits': ['Todas as orações guiadas, jornadas e novenas'],
    'benefit_items': [
      {'icon': 'headphones', 'title': 'Orações guiadas em áudio', 'description': 'Reze com calma, passo a passo, onde estiver.'},
      {'icon': 'sun', 'title': 'Reflexão diária do Evangelho', 'description': 'Uma meditação curta para cada dia da liturgia.'},
      {'icon': 'calendar', 'title': 'Jornadas e novenas completas', 'description': 'Planos de 7 a 54 dias para criar o hábito.'},
      {'icon': 'sparkles', 'title': 'Explicação dos versículos', 'description': 'Entenda o contexto de cada passagem da Bíblia.'},
    ],
    'social_proof': 'Mais de 20 mil pessoas rezam com o Sancta',
    'guarantee': 'Cancele quando quiser',
    'faq': [
      {'question': 'Posso cancelar quando quiser?', 'answer': 'Sim. No app, a assinatura é gerenciada pelo Google Play, em Pagamentos e assinaturas.'},
      {'question': 'Como funciona o teste grátis?', 'answer': 'Você tem acesso completo durante o teste. Se cancelar antes do fim, não há cobrança.'},
    ],
    'testimonials': [
      {'author': 'Maria', 'content': 'Rezo o terço todos os dias com o app. Mudou minha rotina.', 'rating': 5},
      {'author': 'João', 'content': 'As novenas guiadas me ajudam a não desistir no meio.', 'rating': 5},
    ],
  },
};

Map<String, dynamic> homeJson() => {
  'greeting': {'title': 'Bom dia, Ana!', 'subtitle': 'Que hoje seja um dia de bênçãos.'},
  'streak': 5,
  'daily_verse': {
    'reference': 'Sl 23,1',
    'text': 'O Senhor é o meu pastor, nada me faltará.',
    'image_url': null,
    'reflection_title': 'Confiar no cuidado de Deus',
    'reflection': null,
    'reflection_locked': true,
  },
  'liturgy': {'date': '2026-10-04', 'title': '27º Domingo do Tempo Comum', 'color': 'Verde', 'color_hex': '#2E7D32', 'gospel_reference': 'Mt 21,33-43'},
  'saint': {'name': 'São Francisco de Assis', 'title': 'Fundador dos Frades Menores', 'biography': 'Nasceu em Assis, em 1181.', 'image_url': null},
  'continue_journey': journeysJson.first,
  'featured_journeys': journeysJson,
  // Escolha do servidor para o cartão principal (mesma do mockup: Oração da manhã às 8h30).
  'period_prayers': {'morning': prayersJson[0], 'afternoon': prayersJson[2], 'night': prayersJson[4]},
  'featured_prayers': prayersJson,
  'news': newsJson,
  'live_channels': [],
  'live_now': [
    {
      'id': 3,
      'name': 'TV Ao Vivo',
      'category': 'missa',
      'is_live': true,
      'on_schedule_now': false,
      'title': 'Santa Missa',
      'started_at': '2026-10-04T08:00:00-03:00',
      'watch_url': 'https://www.youtube.com/watch?v=LIVEvideo01',
      'thumbnail_url': null,
      'logo_url': null,
      'topic': 'sancta_live_3',
    },
  ],
  'events': [],
  'is_premium': false,
};

const prayersJson = <Map<String, dynamic>>[
  {
    'id': 1,
    'slug': 'oracao-da-manha',
    'title': 'Oração da manhã',
    'duration_minutes': 8,
    'has_audio': true,
    'category': {'name': 'Manhã', 'color': '#1F3D9C'},
  },
  {
    'id': 2,
    'slug': 'santo-terco',
    'title': 'Santo Terço',
    'subtitle': 'Mistérios gloriosos',
    'duration_minutes': 20,
    'has_audio': true,
    'category': {'name': 'Terço'},
  },
  {
    'id': 3,
    'slug': 'angelus',
    'title': 'Ângelus',
    'duration_minutes': 4,
    'category': {'name': 'Marianas'},
  },
  {
    'id': 4,
    'slug': 'lectio-divina',
    'title': 'Lectio Divina guiada',
    'duration_minutes': 15,
    'has_audio': true,
    'is_premium': true,
    'locked': true,
    'category': {'name': 'Meditação'},
  },
  {
    'id': 5,
    'slug': 'exame-de-consciencia',
    'title': 'Exame de consciência',
    'duration_minutes': 10,
    'category': {'name': 'Noite'},
  },
];

const categoriesJson = <Map<String, dynamic>>[
  {'slug': 'manha', 'name': 'Manhã', 'prayers_count': 8},
  {'slug': 'terco', 'name': 'Terço', 'prayers_count': 6},
  {'slug': 'marianas', 'name': 'Marianas', 'prayers_count': 12},
  {'slug': 'noite', 'name': 'Noite', 'prayers_count': 5},
];

const journeysJson = <Map<String, dynamic>>[
  {
    'id': 1,
    'slug': 'novena-aparecida',
    'title': 'Novena de Nossa Senhora Aparecida',
    'type': 'novena',
    'type_label': 'Novena',
    'steps_count': 9,
    'completed_steps': 2,
  },
  {
    'id': 2,
    'slug': 'evangelho-de-marcos',
    'title': 'O Evangelho de Marcos em 30 dias',
    'type': 'reading_plan',
    'type_label': 'Plano de leitura',
    'steps_count': 30,
    'completed_steps': 0,
    'is_premium': true,
  },
];

const newsJson = <Map<String, dynamic>>[
  {'id': 1, 'title': 'Papa convoca jornada de oração pela paz', 'source': 'Vatican News', 'published_at': '2026-10-03T10:00:00-03:00'},
  {'id': 2, 'title': 'Santuário Nacional prepara a festa da Padroeira', 'source': 'A12', 'published_at': '2026-10-02T09:00:00-03:00'},
];

const booksJson = <Map<String, dynamic>>[
  {'testament': 'AT', 'position': 1, 'name': 'Gênesis', 'abbreviation': 'Gn', 'slug': 'genesis', 'chapters_count': 50},
  {'testament': 'AT', 'position': 2, 'name': 'Êxodo', 'abbreviation': 'Ex', 'slug': 'exodo', 'chapters_count': 40},
  {'testament': 'AT', 'position': 3, 'name': 'Levítico', 'abbreviation': 'Lv', 'slug': 'levitico', 'chapters_count': 27},
  {'testament': 'AT', 'position': 4, 'name': 'Números', 'abbreviation': 'Nm', 'slug': 'numeros', 'chapters_count': 36},
  {'testament': 'AT', 'position': 5, 'name': 'Deuteronômio', 'abbreviation': 'Dt', 'slug': 'deuteronomio', 'chapters_count': 34},
  {'testament': 'AT', 'position': 6, 'name': 'Josué', 'abbreviation': 'Js', 'slug': 'josue', 'chapters_count': 24},
  {'testament': 'AT', 'position': 7, 'name': 'Juízes', 'abbreviation': 'Jz', 'slug': 'juizes', 'chapters_count': 21},
  {'testament': 'AT', 'position': 8, 'name': 'Rute', 'abbreviation': 'Rt', 'slug': 'rute', 'chapters_count': 4},
  {'testament': 'NT', 'position': 47, 'name': 'Mateus', 'abbreviation': 'Mt', 'slug': 'mateus', 'chapters_count': 28},
  {'testament': 'NT', 'position': 48, 'name': 'Marcos', 'abbreviation': 'Mc', 'slug': 'marcos', 'chapters_count': 16},
  {'testament': 'NT', 'position': 50, 'name': 'João', 'abbreviation': 'Jo', 'slug': 'joao', 'chapters_count': 21},
];

const churchesJson = <Map<String, dynamic>>[
  {
    'id': 'c-1',
    'slug': 'catedral-da-se',
    'name': 'Catedral Metropolitana da Sé',
    'type': 'cathedral',
    'type_label': 'Catedral',
    'city': 'São Paulo',
    'state': 'SP',
    'address': 'Praça da Sé, s/n — Sé, São Paulo',
    'latitude': -23.5503,
    'longitude': -46.6339,
    'is_verified': true,
    'distance_km': 0.35,
    'next_mass': {'label': 'Hoje às 11h'},
  },
  {
    'id': 'c-2',
    'slug': 'mosteiro-de-sao-bento',
    'name': 'Basílica Abacial de São Bento',
    'type': 'basilica',
    'type_label': 'Basílica',
    'city': 'São Paulo',
    'state': 'SP',
    'address': 'Largo de São Bento, s/n — Centro',
    'latitude': -23.5440,
    'longitude': -46.6340,
    'is_verified': true,
    'distance_km': 1.24,
    'next_mass': {'label': 'Hoje às 18h'},
  },
  {
    'id': 'c-3',
    'slug': 'paroquia-santa-cecilia',
    'name': 'Paróquia Santa Cecília',
    'type': 'parish',
    'type_label': 'Paróquia',
    'city': 'São Paulo',
    'state': 'SP',
    'address': 'Largo de Santa Cecília, s/n — Santa Cecília',
    'latitude': -23.5380,
    'longitude': -46.6500,
    'is_verified': false,
    'distance_km': 2.8,
  },
];

const liveJson = <Map<String, dynamic>>[
  {
    'id': 3,
    'name': 'TV Ao Vivo',
    'description': 'Missas e orações',
    'category': 'missa',
    'category_label': 'Missa',
    'watch_url': 'https://www.youtube.com/watch?v=LIVEvideo01',
    'youtube_url': 'https://www.youtube.com/channel/UCabc',
    'is_live': true,
    'live': {'video_id': 'LIVEvideo01', 'title': 'Santa Missa — 27º Domingo do Tempo Comum', 'started_at': '2026-10-04T08:00:00-03:00', 'thumbnail_url': null},
    'upcoming': null,
    'on_schedule_now': true,
    'current_schedule_title': 'Santa Missa',
    'schedule': [
      {'weekday': null, 'time': '08:00', 'duration_minutes': 60, 'title': 'Santa Missa'},
      {'weekday': 0, 'time': '18:00', 'duration_minutes': 90, 'title': 'Missa de domingo'},
    ],
    'next_at': '2026-10-04T18:00:00-03:00',
    'topic': 'sancta_live_3',
    'type': 'live',
    'url': 'https://www.youtube.com/watch?v=LIVEvideo01',
  },
  {
    'id': 5,
    'name': 'Terço Diário',
    'description': 'O terço rezado em comunidade',
    'category': 'terco',
    'category_label': 'Terço',
    'watch_url': 'https://www.youtube.com/@terco',
    'is_live': false,
    'live': null,
    'on_schedule_now': false,
    'schedule': [
      {'weekday': null, 'time': '06:00', 'duration_minutes': 30, 'title': 'Terço da manhã'},
      {'weekday': null, 'time': '21:00', 'duration_minutes': 30, 'title': 'Terço da noite'},
    ],
    'next_at': '2026-10-04T21:00:00-03:00',
    'topic': 'sancta_live_5',
  },
  {
    'id': 7,
    'name': 'Adoração Perpétua',
    'description': 'Capela com o Santíssimo exposto 24 horas',
    'category': 'adoracao',
    'category_label': 'Adoração',
    'watch_url': 'https://www.youtube.com/@adoracao',
    'is_live': false,
    'on_schedule_now': false,
    'schedule': [],
    'next_at': null,
    'topic': 'sancta_live_7',
  },
];

const plansJson = <Map<String, dynamic>>[
  {
    'slug': 'anual',
    'name': 'Anual',
    'description': 'O melhor valor — 7 dias grátis para experimentar.',
    'interval': 'year',
    'interval_count': 1,
    'period_label': 'por ano',
    'price_cents': 14990,
    'price_formatted': 'R\$ 149,90',
    'compare_at_price_cents': 23880,
    'compare_at_price_formatted': 'R\$ 238,80',
    'monthly_equivalent_cents': 1249,
    'monthly_equivalent_formatted': 'R\$ 12,49',
    'savings_percent': 37,
    'trial_days': 7,
    'badge': 'Mais popular',
    'is_featured': true,
    'google_play': {'product_id': 'sancta_plus', 'base_plan_id': 'anual'},
  },
  {
    'slug': 'mensal',
    'name': 'Mensal',
    'interval': 'month',
    'interval_count': 1,
    'period_label': 'por mês',
    'price_cents': 1990,
    'price_formatted': 'R\$ 19,90',
    'monthly_equivalent_cents': 1990,
    'monthly_equivalent_formatted': 'R\$ 19,90',
    'savings_percent': null,
    'trial_days': 0,
    'is_featured': false,
    'google_play': {'product_id': 'sancta_plus', 'base_plan_id': 'mensal'},
  },
];

const statsJson = <String, dynamic>{
  'current_streak': 5,
  'longest_streak': 12,
  'total_prayer_minutes': 248,
  'last_seven_days': [
    {'date': '2026-09-28', 'seconds': 600},
    {'date': '2026-09-29', 'seconds': 0},
    {'date': '2026-09-30', 'seconds': 300},
    {'date': '2026-10-01', 'seconds': 900},
    {'date': '2026-10-02', 'seconds': 420},
    {'date': '2026-10-03', 'seconds': 600},
    {'date': '2026-10-04', 'seconds': 480},
  ],
};

Map<String, dynamic> prayerDetailJson(String slug) {
  final base = prayersJson.firstWhere((p) => p['slug'] == slug, orElse: () => prayersJson.first);
  final locked = base['locked'] == true;
  return {
    ...base,
    'description': 'Comece o dia entregando a Deus seus planos, alegrias e preocupações.',
    'author': 'Tradição da Igreja',
    'blocks': [
      {'type': 'rubric', 'text': 'Em silêncio, faça o sinal da cruz.'},
      {'type': 'paragraph', 'text': 'Senhor, no silêncio deste dia que amanhece, venho pedir-te a paz, a sabedoria e a força.'},
      {'type': 'priest', 'speaker': 'PR', 'text': 'O Senhor esteja convosco.'},
      {'type': 'assembly', 'text': 'Ele está no meio de nós.'},
      if (!locked) {'type': 'paragraph', 'text': 'Quero olhar hoje o mundo com olhos cheios de amor, ser paciente, compreensivo, manso e prudente.'},
    ],
  };
}

const journeyDetailJson = <String, dynamic>{
  ...{
    'id': 1,
    'slug': 'novena-aparecida',
    'title': 'Novena de Nossa Senhora Aparecida',
    'type': 'novena',
    'type_label': 'Novena',
    'subtitle': 'Nove dias com a Mãe Aparecida',
    'steps_count': 9,
    'completed_steps': 2,
    'description': 'Uma oração por dia, com uma leitura curta e uma intenção para a família.',
  },
  'steps': [
    {'id': 11, 'position': 1, 'title': 'Dia 1 — A pescaria milagrosa', 'bible_reference': 'Lc 5,1-11', 'duration_minutes': 8, 'completed': true},
    {'id': 12, 'position': 2, 'title': 'Dia 2 — Maria, mãe atenta', 'bible_reference': 'Jo 2,1-11', 'duration_minutes': 8, 'completed': true},
    {'id': 13, 'position': 3, 'title': 'Dia 3 — A fé dos simples', 'bible_reference': 'Mt 11,25-30', 'duration_minutes': 9},
    {'id': 14, 'position': 4, 'title': 'Dia 4 — Mãe da esperança', 'bible_reference': 'Lc 1,39-56', 'duration_minutes': 8, 'locked': true},
  ],
};

const chapterJson = <String, dynamic>{
  'book': {'name': 'João', 'slug': 'joao', 'abbreviation': 'Jo', 'chapters_count': 21},
  'chapter': 3,
  'previous': {'book': 'joao', 'chapter': 2},
  'next': {'book': 'joao', 'chapter': 4},
  'verses': [
    {'id': 1, 'number': 14, 'text': 'Como Moisés levantou a serpente no deserto, assim deve ser levantado o Filho do Homem,'},
    {'id': 2, 'number': 15, 'text': 'para que todo homem que nele crer tenha a vida eterna.'},
    {
      'id': 3,
      'number': 16,
      'text': 'Com efeito, de tal modo Deus amou o mundo, que lhe deu seu Filho único, para que todo o que nele crer não pereça, mas tenha a vida eterna.',
      'highlight': 'gold',
      'bookmarked': true,
    },
    {
      'id': 4,
      'number': 17,
      'text': 'Pois Deus não enviou o Filho ao mundo para condená-lo, mas para que o mundo seja salvo por ele.',
      'note': 'Salvação, não condenação.',
    },
  ],
};

const liturgyJson = <String, dynamic>{
  'date': '2026-10-04',
  'title': '27º Domingo do Tempo Comum',
  'color': 'Verde',
  'color_hex': '#2E7D32',
  'readings': [
    {
      'label': 'Primeira leitura',
      'reference': 'Is 5,1-7',
      'title': 'A vinha do Senhor',
      'text': 'Vou cantar para o meu amado o cântico do amigo para a sua vinha.',
    },
    {
      'label': 'Salmo responsorial',
      'reference': 'Sl 79',
      'refrain': 'A vinha do Senhor é a casa de Israel.',
      'text': 'Arrancastes do Egito esta videira e expulsastes as nações para plantá-la.',
    },
    {
      'label': 'Evangelho',
      'reference': 'Mt 21,33-43',
      'text': 'Naquele tempo, Jesus disse aos sumos sacerdotes e aos anciãos do povo: Escutai esta outra parábola.',
    },
  ],
  'prayers': {'collect': 'Ó Deus eterno e todo-poderoso, que nos concedeis no vosso imenso amor de Pai mais do que merecemos e pedimos.'},
  'homily_locked': true,
};

const churchDetailJson = <String, dynamic>{
  ...{
    'id': 'c-1',
    'slug': 'catedral-da-se',
    'name': 'Catedral Metropolitana da Sé',
    'type_label': 'Catedral',
    'address': 'Praça da Sé, s/n — Sé, São Paulo',
    'is_verified': true,
    'distance_km': 0.35,
    'next_mass': {'label': 'Hoje às 11h'},
    'maps_url': 'https://maps.google.com/?q=-23.55,-46.63',
    'contacts': {'phone': '(11) 3107-6832', 'instagram': '@catedraldase'},
    'description': 'Sede da Arquidiocese de São Paulo, em estilo neogótico.',
  },
  'schedules': {
    'missa': [
      {'weekday': 0, 'weekday_label': 'Domingo', 'starts_at': '09:00', 'notes': null},
      {'weekday': 0, 'weekday_label': 'Domingo', 'starts_at': '11:00'},
      {'weekday': 1, 'weekday_label': 'Segunda a sexta', 'starts_at': '12:00'},
    ],
    'confissao': [
      {'weekday': 1, 'weekday_label': 'Segunda a sexta', 'starts_at': '10:00', 'ends_at': '12:00'},
    ],
  },
};

const newsDetailJson = <String, dynamic>{
  ...{'id': 2, 'title': 'Santuário Nacional prepara a festa da Padroeira', 'source': 'A12', 'published_at': '2026-10-02T09:00:00-03:00'},
  'summary': 'Programação vai de 3 a 12 de outubro, com missas, novena e procissão.',
  'has_full_content': true,
  'blocks': [
    {'type': 'heading', 'text': 'Programação'},
    {'type': 'paragraph', 'text': 'Missas a cada duas horas no dia 12, novena às 19h e procissão luminosa no encerramento.'},
    {
      'type': 'list',
      'items': ['Novena: 3 a 11 de outubro', 'Missa solene: 12 de outubro, 9h'],
    },
  ],
};

const pageJson = <String, dynamic>{
  'slug': 'sobre',
  'title': 'Sobre o Sancta',
  'blocks': [
    {'type': 'paragraph', 'text': 'O Sancta nasceu para ajudar católicos a rezar todos os dias, com a Bíblia, a liturgia e orações guiadas.'},
    {'type': 'heading', 'text': 'Fale conosco'},
    {'type': 'paragraph', 'text': 'Escreva para contato@sancta.app.br.'},
  ],
};

const annotationsJson = <Map<String, dynamic>>[
  {
    'verse_id': 3,
    'reference': 'Jo 3,16',
    'book_slug': 'joao',
    'chapter': 3,
    'verse': 16,
    'text': 'Com efeito, de tal modo Deus amou o mundo, que lhe deu seu Filho único, para que todo o que nele crer não pereça, mas tenha a vida eterna.',
    'highlight': 'gold',
    'note': 'Salvação, não condenação.',
    'bookmarked': true,
  },
];

const intentionsJson = <Map<String, dynamic>>[
  {'id': 'i-1', 'text': 'Pela saúde da minha mãe, que fará uma cirurgia nesta semana.', 'author': 'Maria', 'prayers_count': 12, 'prayed': false},
  {'id': 'i-2', 'text': 'Por um emprego para o meu filho.', 'author': 'Anônimo', 'prayers_count': 3, 'prayed': true},
];
