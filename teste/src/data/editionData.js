export const navItems = [
  { label: 'Visão', href: '#visao' },
  { label: 'Sites', href: '#sites' },
  { label: 'Sistemas', href: '#sistemas' },
  { label: 'Automação', href: '#automacao' },
  { label: 'Projetos', href: '#projetos' },
  { label: 'Contato', href: '#contato' }
];

export const galleryImages = [
  {
    title: 'Experiência digital',
    tag: 'Interface premium',
    url: 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1300&q=90'
  },
  {
    title: 'Operação inteligente',
    tag: 'Painéis e dados',
    url: 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1300&q=90'
  },
  {
    title: 'Tecnologia aplicada',
    tag: 'Código e produto',
    url: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1300&q=90'
  },
  {
    title: 'Marca com presença',
    tag: 'Visual e estratégia',
    url: 'https://images.unsplash.com/photo-1518005020951-eccb494ad742?auto=format&fit=crop&w=1300&q=90'
  },
  {
    title: 'Equipe e entrega',
    tag: 'Processo claro',
    url: 'https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=1300&q=90'
  },
  {
    title: 'Produto digital',
    tag: 'Web app',
    url: 'https://images.unsplash.com/photo-1551434678-e076c223a692?auto=format&fit=crop&w=1300&q=90'
  },
  {
    title: 'Conversão',
    tag: 'Landing page',
    url: 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1300&q=90'
  },
  {
    title: 'Dashboard',
    tag: 'Decisão rápida',
    url: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1300&q=90'
  },
  {
    title: 'Arquitetura',
    tag: 'Digital sólido',
    url: 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1300&q=90'
  },
  {
    title: 'Design system',
    tag: 'Componentes',
    url: 'https://images.unsplash.com/photo-1545235617-9465d2a55698?auto=format&fit=crop&w=1300&q=90'
  },
  {
    title: 'Lançamento',
    tag: 'Campanha',
    url: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1300&q=90'
  },
  {
    title: 'Performance',
    tag: 'Front-end',
    url: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1300&q=90'
  },
  {
    title: 'Atendimento moderno',
    tag: 'Relacionamento',
    url: 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1300&q=90'
  },
  {
    title: 'Estratégia de marca',
    tag: 'Posicionamento',
    url: 'https://images.unsplash.com/photo-1559136555-9303baea8ebd?auto=format&fit=crop&w=1300&q=90'
  },
  {
    title: 'Crescimento digital',
    tag: 'Escala',
    url: 'https://images.unsplash.com/photo-1533750349088-cd871a92f312?auto=format&fit=crop&w=1300&q=90'
  }
];

export const chapters = [
  {
    id: 'sites',
    number: '01',
    eyebrow: 'Presença digital',
    title: 'Sites institucionais com estética de lançamento global.',
    text: 'Uma página precisa parecer segura, moderna e bem pensada. Criamos experiências com hero forte, storytelling, seções editoriais, imagens grandes, prova visual e contato direto.',
    points: ['Hero cinematográfico', 'Parallax com profundidade', 'WhatsApp estratégico'],
    image: galleryImages[0].url,
    color: 'lime'
  },
  {
    id: 'sistemas',
    number: '02',
    eyebrow: 'Produto e gestão',
    title: 'Sistemas web com visual de produto premium.',
    text: 'Não basta funcionar. O sistema precisa ser claro, rápido, bonito e organizado. Construímos dashboards, cadastros, permissões, relatórios e fluxos internos com experiência profissional.',
    points: ['PHP e MySQL quando precisar', 'Painéis por perfil', 'UI limpa e responsiva'],
    image: galleryImages[7].url,
    color: 'cyan'
  },
  {
    id: 'automacao',
    number: '03',
    eyebrow: 'Rotinas inteligentes',
    title: 'Automações que reduzem trabalho manual e aceleram atendimento.',
    text: 'Fluxos de WhatsApp, lembretes, relatórios, integrações, notificações e rotinas internas para sua empresa ganhar velocidade sem perder controle.',
    points: ['WhatsApp e alertas', 'Rotinas automáticas', 'Relatórios inteligentes'],
    image: galleryImages[5].url,
    color: 'purple'
  }
];

export const projectCards = [
  {
    label: 'Institucional',
    title: 'Site para empresa',
    text: 'Página de apresentação premium com narrativa, imagens, prova visual e contato direto.',
    image: galleryImages[3].url
  },
  {
    label: 'Sistema',
    title: 'Dashboard interno',
    text: 'Painel administrativo com dados, gráficos, usuários, permissões e relatórios.',
    image: galleryImages[1].url
  },
  {
    label: 'Conversão',
    title: 'Landing page',
    text: 'Página para campanha, lançamento, captação e venda com foco em resultado.',
    image: galleryImages[6].url
  },
  {
    label: 'Automação',
    title: 'Fluxos inteligentes',
    text: 'Integrações, notificações, WhatsApp, rotinas e economia de trabalho manual.',
    image: galleryImages[12].url
  }
];
