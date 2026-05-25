import {
  ArrowRight,
  BadgeCheck,
  Camera,
  CheckCircle2,
  Database,
  Gauge,
  Layers3,
  LockKeyhole,
  MonitorCog,
  Palette,
  Rocket,
  ShieldCheck,
  Sparkles,
  Workflow,
} from "lucide-react";

import { Button } from "@/components/ui/button";

const siteUrl = "https://ljsolucoestech.com.br";
const contactUrl = `${siteUrl}/contato.php`;

const navLinks = [
  { label: "Soluções", href: "#solucoes" },
  { label: "Portfólio", href: "#portfolio" },
  { label: "Método", href: "#metodo" },
  { label: "Segurança", href: "#seguranca" },
  { label: "Contato", href: "#contato" },
];

const heroStats = [
  { value: "6", label: "frentes de atuação" },
  { value: "PHP", label: "base leve para produção" },
  { value: "UX", label: "foco em conversão" },
  { value: "CSRF", label: "segurança desde o formulário" },
];

const solutions = [
  {
    icon: MonitorCog,
    eyebrow: "Sistema",
    title: "Sistemas Web Sob Medida",
    description:
      "Plataformas administrativas, painéis internos, sistemas de gestão, portais e ferramentas digitais adaptadas ao fluxo real da empresa.",
    items: ["Login e permissões", "Banco estruturado", "Relatórios", "Responsividade"],
  },
  {
    icon: Rocket,
    eyebrow: "Presença digital",
    title: "Sites Institucionais e Landing Pages",
    description:
      "Páginas profissionais para transmitir confiança, apresentar serviços com clareza e gerar contato comercial com CTA estratégico.",
    items: ["SEO técnico", "Performance", "Design responsivo", "WhatsApp"],
  },
  {
    icon: Layers3,
    eyebrow: "Produto",
    title: "SaaS e Produtos Digitais",
    description:
      "Plataformas recorrentes com módulos, planos, usuários, área administrativa e gestão centralizada para vender tecnologia como serviço.",
    items: ["Assinaturas", "Usuários", "Escalabilidade", "Integrações"],
  },
  {
    icon: Workflow,
    eyebrow: "Operação",
    title: "Automação e Sistemas Internos",
    description:
      "Transformação de processos manuais em fluxos digitais para reduzir retrabalho, erros operacionais e perda de informação.",
    items: ["Formulários", "Notificações", "Fluxos", "Dashboards"],
  },
  {
    icon: Palette,
    eyebrow: "Marca",
    title: "Identidade Visual e Design Digital",
    description:
      "Identidades visuais, materiais digitais e interfaces que fortalecem marca, percepção profissional e consistência comercial.",
    items: ["Logo", "Paleta", "Tipografia", "UI para sistemas"],
  },
  {
    icon: Camera,
    eyebrow: "Imagem",
    title: "Drone e Captação Visual",
    description:
      "Imagens aéreas e registros institucionais para empresas, obras, eventos, propriedades, marketing e relatórios visuais.",
    items: ["Fotos aéreas", "Vídeos", "Redes sociais", "Media Vault"],
  },
];

const products = [
  {
    type: "SaaS de cobranças",
    name: "Fluxo Pay",
    description:
      "Produto por assinatura para organizar recebimentos, clientes, status de pagamento, relatórios e previsibilidade de caixa.",
    tags: ["Clientes", "Cobranças", "Relatórios", "Painel financeiro"],
  },
  {
    type: "SaaS contábil",
    name: "SaaS para Contadores",
    description:
      "Portal para escritórios contábeis organizarem clientes, documentos, demandas, prazos, equipe e comunicação interna.",
    tags: ["Clientes", "Documentos", "Prazos", "Equipe"],
  },
  {
    type: "Nuvem privada de mídia",
    name: "LJ Media Vault",
    description:
      "Sistema para armazenar e organizar fotos e vídeos em alta qualidade, com áreas por cliente, evento, data ou categoria.",
    tags: ["Upload", "Busca", "Backup", "Compartilhamento seguro"],
  },
];

const portfolio = [
  {
    category: "Sistema",
    title: "Gestão operacional",
    description: "Painel com cadastros, relatórios, status e controle de processos internos.",
  },
  {
    category: "SaaS",
    title: "Fluxo Pay",
    description: "Produto por assinatura para cobranças, clientes e acompanhamento financeiro.",
  },
  {
    category: "Web",
    title: "Site institucional premium",
    description: "Página de alta conversão com identidade visual, SEO base e atendimento via WhatsApp.",
  },
  {
    category: "Drone",
    title: "Captação aérea institucional",
    description: "Fotos e vídeos em alta qualidade para obras, empresas, eventos e campanhas.",
  },
  {
    category: "Branding",
    title: "Identidade corporativa",
    description: "Sistema visual, cores, materiais digitais e linguagem comercial.",
  },
  {
    category: "SaaS",
    title: "SaaS para contadores",
    description: "Portal digital para organizar clientes, documentos, prazos e solicitações.",
  },
];

const process = [
  ["01", "Diagnóstico", "Entendimento do problema, público, fluxo atual e objetivo comercial."],
  ["02", "Planejamento", "Definição de páginas, funcionalidades, banco de dados, integrações e prioridades."],
  ["03", "Design e protótipo", "Interface moderna, clara e responsiva antes da implementação final."],
  ["04", "Desenvolvimento", "Código organizado, validação de dados, segurança e performance."],
  ["05", "Testes", "Validação de responsividade, navegação, formulários, erros, links e compatibilidade."],
  ["06", "Publicação e evolução", "Entrega publicada e preparada para melhorias futuras."],
];

const engineering = [
  {
    icon: ShieldCheck,
    title: "Segurança desde o primeiro formulário",
    description: "CSRF, honeypot, sanitização de saída, validação de dados e estrutura preparada para áreas restritas.",
  },
  {
    icon: Gauge,
    title: "Performance compatível com hospedagem real",
    description: "CSS e JavaScript leves, carregamento progressivo, imagens otimizadas e cache por versão de arquivo.",
  },
  {
    icon: Database,
    title: "Banco de dados com visão de crescimento",
    description: "Modelagem para leads, serviços, projetos, produtos, usuários administrativos e evolução futura sem bagunça.",
  },
  {
    icon: LockKeyhole,
    title: "UX pensada para conversão",
    description: "Cada dobra explica, prova capacidade, reduz dúvidas e leva o cliente para uma ação clara.",
  },
];

export default function Home() {
  return (
    <main className="min-h-screen overflow-hidden bg-background text-foreground noise-overlay">
      <header className="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-background/80 backdrop-blur-xl">
        <nav className="mx-auto flex h-20 max-w-[1400px] items-center justify-between px-6 lg:px-12">
          <a href="#topo" className="flex items-center gap-3" aria-label="LJ Soluções Tecnológicas">
            <span className="grid size-11 place-items-center rounded-2xl border border-teal-300/30 bg-teal-300/10 font-mono text-sm font-bold text-teal-200">
              LJ
            </span>
            <span className="leading-tight">
              <span className="block font-display text-2xl tracking-tight">L&J</span>
              <span className="block text-xs text-muted-foreground">Soluções Tecnológicas</span>
            </span>
          </a>

          <div className="hidden items-center gap-9 md:flex">
            {navLinks.map((link) => (
              <a key={link.href} href={link.href} className="text-sm text-muted-foreground transition hover:text-foreground">
                {link.label}
              </a>
            ))}
          </div>

          <Button asChild className="hidden rounded-full bg-teal-300 px-6 text-slate-950 hover:bg-teal-200 md:inline-flex">
            <a href={contactUrl}>Solicitar diagnóstico</a>
          </Button>
        </nav>
      </header>

      <section id="topo" className="relative flex min-h-screen items-center pt-24">
        <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_75%_20%,rgba(45,212,191,0.22),transparent_32%),radial-gradient(circle_at_15%_70%,rgba(14,165,233,0.16),transparent_30%)]" />
        <div className="absolute inset-0 -z-10 opacity-30 [background-image:linear-gradient(rgba(255,255,255,.07)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.07)_1px,transparent_1px)] [background-size:64px_64px]" />

        <div className="mx-auto grid max-w-[1400px] items-center gap-16 px-6 py-20 lg:grid-cols-[1.08fr_.92fr] lg:px-12">
          <div>
            <div className="mb-8 inline-flex items-center gap-3 rounded-full border border-teal-300/20 bg-teal-300/10 px-4 py-2 text-sm font-mono text-teal-100">
              <Sparkles className="size-4" />
              Software • SaaS • Automação • Drone
            </div>

            <h1 className="max-w-5xl font-display text-[clamp(3.4rem,9vw,8.8rem)] leading-[0.86] tracking-tight">
              Tecnologia sob medida para empresas que querem crescer.
            </h1>

            <p className="mt-8 max-w-2xl text-xl leading-relaxed text-muted-foreground lg:text-2xl">
              Sites, sistemas web, SaaS, automações, identidade visual e soluções com drone para transformar processos em experiências digitais profissionais.
            </p>

            <div className="mt-10 flex flex-col gap-4 sm:flex-row">
              <Button asChild size="lg" className="h-14 rounded-full bg-teal-300 px-8 text-base text-slate-950 hover:bg-teal-200">
                <a href={contactUrl}>
                  Solicitar diagnóstico
                  <ArrowRight className="ml-2 size-4" />
                </a>
              </Button>
              <Button asChild size="lg" variant="outline" className="h-14 rounded-full border-white/15 bg-white/5 px-8 text-base hover:bg-white/10">
                <a href="#portfolio">Ver portfólio</a>
              </Button>
            </div>
          </div>

          <div className="relative">
            <div className="absolute -inset-8 rounded-[3rem] bg-teal-300/10 blur-3xl" />
            <div className="relative overflow-hidden rounded-[2rem] border border-white/10 bg-white/[0.04] p-6 shadow-2xl shadow-black/30 backdrop-blur">
              <div className="mb-6 flex items-center justify-between border-b border-white/10 pb-5">
                <div>
                  <p className="font-mono text-xs uppercase tracking-[0.32em] text-teal-200">LJ Digital OS</p>
                  <h2 className="mt-2 font-display text-4xl">Projeto em produção</h2>
                </div>
                <span className="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-200">Online</span>
              </div>

              <div className="grid gap-4">
                {["Fluxo Pay", "Media Vault", "SaaS Contábil"].map((item, index) => (
                  <div key={item} className="rounded-2xl border border-white/10 bg-slate-950/40 p-5">
                    <div className="flex items-center justify-between gap-4">
                      <span className="font-medium">{item}</span>
                      <span className="text-xs text-muted-foreground">Módulo {String(index + 1).padStart(2, "0")}</span>
                    </div>
                    <div className="mt-4 h-2 overflow-hidden rounded-full bg-white/10">
                      <div className="h-full rounded-full bg-teal-300" style={{ width: `${76 + index * 8}%` }} />
                    </div>
                  </div>
                ))}
              </div>

              <div className="mt-6 rounded-2xl border border-teal-300/20 bg-teal-300/10 p-5 font-mono text-sm text-teal-50">
                backend_seguro: csrf_token() + sanitize(input) + prepared_queries()
              </div>
            </div>
          </div>
        </div>

        <div className="absolute bottom-8 left-0 right-0 hidden overflow-hidden md:block">
          <div className="marquee flex gap-12 whitespace-nowrap text-sm font-mono text-muted-foreground">
            {[...Array(2)].map((_, loop) => (
              <div key={loop} className="flex gap-12">
                {heroStats.map((stat) => (
                  <span key={`${stat.label}-${loop}`} className="flex items-center gap-3">
                    <span className="text-2xl font-display text-foreground">{stat.value}</span>
                    {stat.label}
                  </span>
                ))}
              </div>
            ))}
          </div>
        </div>
      </section>

      <section id="solucoes" className="border-y border-white/10 py-24 lg:py-32">
        <div className="mx-auto max-w-[1400px] px-6 lg:px-12">
          <div className="mb-16 max-w-3xl">
            <span className="mb-5 inline-flex items-center gap-3 text-sm font-mono text-muted-foreground">
              <span className="h-px w-8 bg-teal-300" />
              Soluções por objetivo
            </span>
            <h2 className="font-display text-5xl tracking-tight lg:text-7xl">Seis frentes para tirar a empresa do improviso digital.</h2>
            <p className="mt-6 text-lg leading-relaxed text-muted-foreground">
              O site precisa vender, o sistema precisa organizar, o SaaS precisa escalar, a identidade precisa gerar confiança e o drone precisa valorizar a imagem.
            </p>
          </div>

          <div className="grid gap-px overflow-hidden rounded-[2rem] border border-white/10 bg-white/10 md:grid-cols-2 xl:grid-cols-3">
            {solutions.map((solution) => {
              const Icon = solution.icon;
              return (
                <article key={solution.title} className="group bg-background p-7 transition hover:bg-white/[0.035] lg:p-9">
                  <div className="mb-8 flex items-start justify-between gap-4">
                    <div className="grid size-13 place-items-center rounded-2xl border border-teal-300/20 bg-teal-300/10 text-teal-200">
                      <Icon className="size-6" />
                    </div>
                    <span className="rounded-full border border-white/10 px-3 py-1 text-xs text-muted-foreground">{solution.eyebrow}</span>
                  </div>
                  <h3 className="font-display text-3xl tracking-tight">{solution.title}</h3>
                  <p className="mt-4 leading-relaxed text-muted-foreground">{solution.description}</p>
                  <div className="mt-7 flex flex-wrap gap-2">
                    {solution.items.map((item) => (
                      <span key={item} className="rounded-full bg-white/5 px-3 py-1 text-xs text-muted-foreground">
                        {item}
                      </span>
                    ))}
                  </div>
                </article>
              );
            })}
          </div>
        </div>
      </section>

      <section className="py-24 lg:py-32">
        <div className="mx-auto max-w-[1400px] px-6 lg:px-12">
          <div className="grid gap-12 lg:grid-cols-[.85fr_1.15fr] lg:items-end">
            <div>
              <span className="mb-5 inline-flex items-center gap-3 text-sm font-mono text-muted-foreground">
                <span className="h-px w-8 bg-teal-300" />
                Ecossistema LJ
              </span>
              <h2 className="font-display text-5xl tracking-tight lg:text-7xl">Produtos próprios provam visão de negócio digital.</h2>
            </div>
            <p className="text-lg leading-relaxed text-muted-foreground">
              A LJ aplica nos projetos dos clientes a mesma lógica usada em SaaS: módulos, recorrência, usuários, permissões, métricas, evolução e manutenção organizada.
            </p>
          </div>

          <div className="mt-16 grid gap-6 lg:grid-cols-3">
            {products.map((product) => (
              <article key={product.name} className="rounded-[2rem] border border-white/10 bg-white/[0.04] p-8 hover-lift">
                <p className="font-mono text-xs uppercase tracking-[0.28em] text-teal-200">{product.type}</p>
                <h3 className="mt-5 font-display text-4xl">{product.name}</h3>
                <p className="mt-5 leading-relaxed text-muted-foreground">{product.description}</p>
                <ul className="mt-8 space-y-3">
                  {product.tags.map((tag) => (
                    <li key={tag} className="flex items-center gap-3 text-sm text-muted-foreground">
                      <CheckCircle2 className="size-4 text-teal-300" />
                      {tag}
                    </li>
                  ))}
                </ul>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section id="portfolio" className="bg-slate-950 py-24 text-white lg:py-32">
        <div className="mx-auto max-w-[1400px] px-6 lg:px-12">
          <div className="mb-16 grid gap-10 lg:grid-cols-[1fr_.8fr] lg:items-end">
            <div>
              <span className="mb-5 inline-flex items-center gap-3 text-sm font-mono text-white/50">
                <span className="h-px w-8 bg-teal-300" />
                Projetos e frentes de atuação
              </span>
              <h2 className="font-display text-5xl tracking-tight lg:text-7xl">Uma vitrine forte para provar capacidade antes do orçamento.</h2>
            </div>
            <p className="text-lg leading-relaxed text-white/60">
              Cards organizados como linha de portfólio para mostrar software, marca, imagem e produto digital trabalhando no mesmo ecossistema.
            </p>
          </div>

          <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            {portfolio.map((item, index) => (
              <article key={item.title} className="group overflow-hidden rounded-[2rem] border border-white/10 bg-white/[0.035]">
                <div className="relative h-48 border-b border-white/10 bg-[radial-gradient(circle_at_30%_25%,rgba(45,212,191,.32),transparent_34%),linear-gradient(135deg,rgba(255,255,255,.11),rgba(255,255,255,.02))] p-5">
                  <span className="rounded-full bg-slate-950/60 px-3 py-1 text-xs text-teal-100">{item.category}</span>
                  <span className="absolute bottom-5 right-5 font-display text-7xl text-white/10">0{index + 1}</span>
                </div>
                <div className="p-7">
                  <h3 className="font-display text-3xl tracking-tight">{item.title}</h3>
                  <p className="mt-4 leading-relaxed text-white/60">{item.description}</p>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section id="metodo" className="py-24 lg:py-32">
        <div className="mx-auto max-w-[1400px] px-6 lg:px-12">
          <div className="mb-16 max-w-3xl">
            <span className="mb-5 inline-flex items-center gap-3 text-sm font-mono text-muted-foreground">
              <span className="h-px w-8 bg-teal-300" />
              Método de entrega
            </span>
            <h2 className="font-display text-5xl tracking-tight lg:text-7xl">Processo claro para evitar retrabalho e entregar pronto para uso.</h2>
          </div>

          <div className="grid gap-px overflow-hidden rounded-[2rem] border border-white/10 bg-white/10 md:grid-cols-2 lg:grid-cols-3">
            {process.map(([number, title, description]) => (
              <article key={number} className="bg-background p-8 lg:p-10">
                <span className="font-display text-5xl text-teal-200/60">{number}</span>
                <h3 className="mt-8 font-display text-3xl">{title}</h3>
                <p className="mt-4 leading-relaxed text-muted-foreground">{description}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section id="seguranca" className="border-y border-white/10 bg-white/[0.025] py-24 lg:py-32">
        <div className="mx-auto max-w-[1400px] px-6 lg:px-12">
          <div className="grid gap-16 lg:grid-cols-[.9fr_1.1fr] lg:items-start">
            <div className="lg:sticky lg:top-28">
              <span className="mb-5 inline-flex items-center gap-3 text-sm font-mono text-muted-foreground">
                <span className="h-px w-8 bg-teal-300" />
                Engenharia sem gambiarra
              </span>
              <h2 className="font-display text-5xl tracking-tight lg:text-7xl">Visual premium só vale se a base técnica for segura.</h2>
              <p className="mt-6 text-lg leading-relaxed text-muted-foreground">
                Estrutura pensada para hospedagem PHP, componentes reaproveitáveis, CSS moderno, JavaScript leve e evolução para área administrativa sem refazer tudo.
              </p>
            </div>

            <div className="space-y-5">
              {engineering.map((item) => {
                const Icon = item.icon;
                return (
                  <article key={item.title} className="rounded-[2rem] border border-white/10 bg-background p-7">
                    <div className="flex gap-5">
                      <div className="grid size-12 shrink-0 place-items-center rounded-2xl bg-teal-300/10 text-teal-200">
                        <Icon className="size-6" />
                      </div>
                      <div>
                        <h3 className="font-display text-3xl">{item.title}</h3>
                        <p className="mt-3 leading-relaxed text-muted-foreground">{item.description}</p>
                      </div>
                    </div>
                  </article>
                );
              })}
            </div>
          </div>
        </div>
      </section>

      <section id="contato" className="py-24 lg:py-32">
        <div className="mx-auto max-w-[1400px] px-6 lg:px-12">
          <div className="relative overflow-hidden rounded-[2.5rem] border border-teal-300/20 bg-teal-300/10 p-8 lg:p-16">
            <div className="absolute right-0 top-0 size-96 rounded-full bg-teal-300/20 blur-3xl" />
            <div className="relative z-10 grid gap-12 lg:grid-cols-[1fr_.55fr] lg:items-end">
              <div>
                <p className="mb-5 font-mono text-sm uppercase tracking-[0.28em] text-teal-100">Próximo passo</p>
                <h2 className="max-w-4xl font-display text-5xl tracking-tight lg:text-7xl">
                  Vamos construir uma presença digital que parece grande, funciona bem e vende confiança.
                </h2>
                <p className="mt-7 max-w-2xl text-lg leading-relaxed text-muted-foreground">
                  Explique o que sua empresa precisa: site, sistema, SaaS, identidade visual, drone ou uma solução completa.
                </p>
              </div>

              <div className="rounded-[2rem] border border-white/10 bg-slate-950/40 p-6">
                <div className="mb-6 flex items-center gap-3">
                  <BadgeCheck className="size-6 text-teal-300" />
                  <span className="font-medium">L. DE SOUZA CORREA - ME</span>
                </div>
                <p className="text-sm text-muted-foreground">CNPJ: 65.975.879/0001-32</p>
                <Button asChild size="lg" className="mt-8 h-14 w-full rounded-full bg-teal-300 text-base text-slate-950 hover:bg-teal-200">
                  <a href={contactUrl}>
                    Abrir formulário de contato
                    <ArrowRight className="ml-2 size-4" />
                  </a>
                </Button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <footer className="border-t border-white/10 py-10">
        <div className="mx-auto flex max-w-[1400px] flex-col gap-6 px-6 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between lg:px-12">
          <p>© 2026 LJ Soluções Tecnológicas. Todos os direitos reservados.</p>
          <div className="flex flex-wrap gap-5">
            {navLinks.map((link) => (
              <a key={link.href} href={link.href} className="transition hover:text-foreground">
                {link.label}
              </a>
            ))}
          </div>
        </div>
      </footer>
    </main>
  );
}
