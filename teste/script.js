(() => {
  const $ = (selector, parent = document) => parent.querySelector(selector);
  const $$ = (selector, parent = document) => Array.from(parent.querySelectorAll(selector));

  const header = $('[data-header]');
  const menuToggle = $('[data-menu-toggle]');
  const mobileMenu = $('[data-mobile-menu]');

  const onScroll = () => {
    header.classList.toggle('scrolled', window.scrollY > 20);
  };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  menuToggle?.addEventListener('click', () => {
    const open = header.classList.toggle('menu-open');
    menuToggle.setAttribute('aria-expanded', String(open));
    document.body.style.overflow = open ? 'hidden' : '';
  });
  $$('a', mobileMenu).forEach((link) => {
    link.addEventListener('click', () => {
      header.classList.remove('menu-open');
      menuToggle?.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    });
  });

  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        revealObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });
  $$('.reveal').forEach((el, index) => {
    el.style.transitionDelay = `${Math.min(index % 6, 5) * 70}ms`;
    revealObserver.observe(el);
  });

  const rotatingWords = ['criar', 'gerir', 'automatizar', 'escalar'];
  let wordIndex = 0;
  const rotatingEl = $('[data-rotating-word]');
  function renderRotatingWord() {
    if (!rotatingEl) return;
    const word = rotatingWords[wordIndex];
    rotatingEl.innerHTML = word.split('').map((char, index) => (
      `<span class="char" style="animation-delay:${index * 50}ms">${char}</span>`
    )).join('');
  }
  renderRotatingWord();
  setInterval(() => {
    wordIndex = (wordIndex + 1) % rotatingWords.length;
    renderRotatingWord();
  }, 2500);

  function drawFeatureVisuals() {
    const visuals = {
      deploy: `
        <svg viewBox="0 0 200 160" aria-hidden="true">
          <defs><clipPath id="deployClip"><rect x="30" y="20" width="140" height="120" rx="4" /></clipPath></defs>
          <rect x="30" y="20" width="140" height="120" rx="4" fill="none" stroke="currentColor" stroke-width="2" />
          <g clip-path="url(#deployClip)">
            ${[0,1,2,3,4,5].map(i => `<rect x="40" y="${35 + i * 16}" width="120" height="10" rx="2" fill="currentColor" opacity="0.15"><animate attributeName="opacity" values="0.15;0.8;0.15" dur="2s" begin="${i * 0.15}s" repeatCount="indefinite"/><animate attributeName="width" values="20;120;20" dur="2s" begin="${i * 0.15}s" repeatCount="indefinite"/></rect>`).join('')}
          </g>
          <circle cx="100" cy="155" r="3" fill="currentColor" opacity="0.3"><animate attributeName="opacity" values="0.3;1;0.3" dur="1s" repeatCount="indefinite" /></circle>
        </svg>`,
      ai: `
        <svg viewBox="0 0 200 160" aria-hidden="true">
          <circle cx="100" cy="80" r="12" fill="currentColor"><animate attributeName="r" values="12;14;12" dur="2s" repeatCount="indefinite" /></circle>
          ${[0,1,2,3,4,5].map(i => {
            const angle = i * 60 * Math.PI / 180;
            const x = 100 + Math.cos(angle) * 50;
            const y = 80 + Math.sin(angle) * 50;
            return `<g><line x1="100" y1="80" x2="${x}" y2="${y}" stroke="currentColor" stroke-width="1" opacity="0.3"><animate attributeName="opacity" values="0.3;0.8;0.3" dur="2s" begin="${i * 0.3}s" repeatCount="indefinite"/></line><circle cx="${x}" cy="${y}" r="6" fill="none" stroke="currentColor" stroke-width="2"><animate attributeName="r" values="6;8;6" dur="2s" begin="${i * 0.3}s" repeatCount="indefinite"/></circle></g>`;
          }).join('')}
          <circle cx="100" cy="80" r="30" fill="none" stroke="currentColor" stroke-width="1" opacity="0"><animate attributeName="r" values="20;60" dur="2s" repeatCount="indefinite"/><animate attributeName="opacity" values="0.5;0" dur="2s" repeatCount="indefinite"/></circle>
        </svg>`,
      collab: `
        <svg viewBox="0 0 200 160" aria-hidden="true">
          <g><rect x="30" y="50" width="50" height="60" rx="4" fill="none" stroke="currentColor" stroke-width="2"/><text x="55" y="85" text-anchor="middle" font-size="20" font-family="monospace" fill="currentColor">UX</text><circle cx="55" cy="35" r="12" fill="none" stroke="currentColor" stroke-width="2"/></g>
          <g><rect x="120" y="50" width="50" height="60" rx="4" fill="none" stroke="currentColor" stroke-width="2"/><text x="145" y="85" text-anchor="middle" font-size="20" font-family="monospace" fill="currentColor">UI</text><circle cx="145" cy="35" r="12" fill="none" stroke="currentColor" stroke-width="2"/></g>
          <line x1="80" y1="80" x2="120" y2="80" stroke="currentColor" stroke-width="2" stroke-dasharray="4 4"><animate attributeName="stroke-dashoffset" values="0;-8" dur="0.5s" repeatCount="indefinite"/></line>
          <circle r="4" fill="currentColor"><animateMotion dur="1.5s" repeatCount="indefinite"><mpath href="#dataPath" /></animateMotion></circle>
          <path id="dataPath" d="M 80 80 L 120 80" fill="none" />
          <g transform="translate(100, 130)"><circle r="6" fill="none" stroke="currentColor" stroke-width="2"><animate attributeName="r" values="6;10;6" dur="1s" repeatCount="indefinite"/><animate attributeName="opacity" values="1;0.3;1" dur="1s" repeatCount="indefinite"/></circle></g>
        </svg>`,
      security: `
        <svg viewBox="0 0 200 160" aria-hidden="true">
          <path d="M 100 20 L 150 40 L 150 90 Q 150 130 100 145 Q 50 130 50 90 L 50 40 Z" fill="none" stroke="currentColor" stroke-width="2"/>
          <path d="M 100 35 L 135 50 L 135 85 Q 135 115 100 128 Q 65 115 65 85 L 65 50 Z" fill="currentColor" opacity="0.1"><animate attributeName="opacity" values="0.1;0.2;0.1" dur="2s" repeatCount="indefinite"/></path>
          <rect x="85" y="70" width="30" height="25" rx="3" fill="currentColor"/>
          <path d="M 90 70 L 90 60 Q 90 50 100 50 Q 110 50 110 60 L 110 70" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
          <circle cx="100" cy="80" r="4" fill="white"/><rect x="98" y="82" width="4" height="8" fill="white"/>
          <line x1="60" y1="60" x2="140" y2="60" stroke="currentColor" stroke-width="1" opacity="0"><animate attributeName="y1" values="40;120;40" dur="3s" repeatCount="indefinite"/><animate attributeName="y2" values="40;120;40" dur="3s" repeatCount="indefinite"/><animate attributeName="opacity" values="0;0.5;0" dur="3s" repeatCount="indefinite"/></line>
        </svg>`
    };
    $$('[data-visual]').forEach(el => { el.innerHTML = visuals[el.dataset.visual] || visuals.deploy; });
  }
  drawFeatureVisuals();

  const steps = [
    {
      number: 'I',
      title: 'Diagnosticar o objetivo',
      description: 'Entender operação, público, dor principal, prioridade comercial e escopo mínimo que realmente gera resultado.',
      file: 'diagnostico.php',
      code: `<?php
$objetivo = diagnosticar([
  'empresa' => 'cliente',
  'dor' => 'processo manual',
  'segmento' => 'publico | industria | empresa | autonomo',
  'prioridade' => 'organizar e vender melhor'
]);`
    },
    {
      number: 'II',
      title: 'Projetar a solução',
      description: 'Definir páginas, módulos, banco de dados, fluxos, permissões, protótipo e pontos de segurança antes de codar.',
      file: 'arquitetura.js',
      code: `const projeto = planejar({
  interfaces: ['app', 'web', 'dashboard'],
  modulos: ['clientes', 'vendas', 'relatorios', 'permissoes'],
  seguranca: ['csrf', 'validacao', 'sanitizacao']
})`
    },
    {
      number: 'III',
      title: 'Publicar e evoluir',
      description: 'Entregar responsivo, testar links, formulários, performance e deixar caminho pronto para novas etapas.',
      file: 'deploy.sh',
      code: `npm run build
# ou HTML/PHP estático
testar_app_web
validar_responsivo
publicar_hostinger

# projeto pronto para evoluir`
    }
  ];
  let activeStep = 0;
  const stepsContainer = $('[data-steps]');
  const processCode = $('#processCode');
  const processFileName = $('#processFileName');

  function renderCode(container, code, withNumbers = true) {
    container.innerHTML = code.split('\n').map((line, lineIndex) => {
      const chars = line.split('').map((char, charIndex) => `<span class="code-char" style="animation-delay:${lineIndex * 80 + charIndex * 15}ms">${char === ' ' ? '&nbsp;' : escapeHTML(char)}</span>`).join('');
      const number = withNumbers ? `<span class="num">${lineIndex + 1}</span>` : '';
      return `<span class="code-line" style="animation-delay:${lineIndex * 80}ms">${number}${chars || '&nbsp;'}</span>`;
    }).join('');
  }
  function escapeHTML(value) {
    return value.replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
  }
  function renderSteps() {
    if (!stepsContainer) return;
    stepsContainer.innerHTML = steps.map((step, index) => `
      <button class="step-button ${index === activeStep ? 'active' : ''}" type="button" data-step="${index}">
        <div class="step-flex">
          <span class="step-number">${step.number}</span>
          <span>
            <h3>${step.title}</h3>
            <p>${step.description}</p>
            ${index === activeStep ? '<span class="step-progress"><i></i></span>' : ''}
          </span>
        </div>
      </button>
    `).join('');
    processFileName.textContent = steps[activeStep].file;
    renderCode(processCode, steps[activeStep].code, true);
    $$('[data-step]', stepsContainer).forEach(btn => btn.addEventListener('click', () => {
      activeStep = Number(btn.dataset.step);
      renderSteps();
    }));
  }
  renderSteps();
  setInterval(() => {
    activeStep = (activeStep + 1) % steps.length;
    renderSteps();
  }, 5000);

  const locations = [
    ['Apps e web', 'Produto digital', 'UI'],
    ['Gestão pública', 'Protocolos e setores', 'Gov'],
    ['Indústria', 'Processos e produção', 'Ops'],
    ['Empresários', 'Vendas e clientes', 'ERP'],
    ['Autônomos', 'Agenda e cobrança', 'CRM'],
    ['JL Media Vault', 'Fotos e vídeos', 'Mídia']
  ];
  let activeLocation = 0;
  const locationsEl = $('[data-locations]');
  function renderLocations() {
    if (!locationsEl) return;
    locationsEl.innerHTML = locations.map((location, index) => `
      <div class="location ${index === activeLocation ? 'active' : ''}">
        <div class="location-left"><span class="location-dot"></span><div><strong>${location[0]}</strong><span>${location[1]}</span></div></div>
        <code>${location[2]}</code>
      </div>
    `).join('');
  }
  renderLocations();
  setInterval(() => { activeLocation = (activeLocation + 1) % locations.length; renderLocations(); }, 2000);

  const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting || entry.target.dataset.done) return;
      entry.target.dataset.done = 'true';
      const end = Number(entry.target.dataset.counter || 0);
      const duration = 1700;
      const startTime = performance.now();
      const animate = (now) => {
        const progress = Math.min((now - startTime) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        entry.target.textContent = Math.floor(eased * end).toLocaleString('pt-BR');
        if (progress < 1) requestAnimationFrame(animate);
      };
      requestAnimationFrame(animate);
    });
  }, { threshold: 0.55 });
  $$('[data-counter]').forEach(el => counterObserver.observe(el));

  const liveTime = $('#liveTime');
  function updateTime() { if (liveTime) liveTime.textContent = new Date().toLocaleTimeString('pt-BR'); }
  updateTime();
  setInterval(updateTime, 1000);

  const integrations = [
    ['Apps', 'Mobile'], ['Web', 'Front-end'], ['PHP leve', 'Back-end'], ['MySQL', 'Banco'], ['JavaScript', 'Interação'], ['Gestão pública', 'Setores'], ['Indústria', 'Operação'], ['Empresários', 'Gestão'], ['Autônomos', 'CRM'], ['CSRF', 'Segurança'], ['UX', 'Conversão'], ['Automação', 'Operação']
  ];
  function renderIntegrations(target, reverse = false) {
    const list = reverse ? [...integrations].reverse() : integrations;
    target.innerHTML = [0, 1].map(set => list.map(item => `<div class="integration-card"><strong>${item[0]}</strong><span>${item[1]}</span></div>`).join('')).join('');
  }
  const intA = $('[data-integrations-a]');
  const intB = $('[data-integrations-b]');
  if (intA) renderIntegrations(intA, false);
  if (intB) renderIntegrations(intB, true);

  const iconSvgs = {
    shield: '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>',
    lock: '<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
    eye: '<svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>',
    file: '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="m9 15 2 2 4-4"/></svg>'
  };
  $$('[data-icon]').forEach(icon => { icon.innerHTML = iconSvgs[icon.dataset.icon] || iconSvgs.shield; });

  const codeExamples = [
    {
      label: 'Contato',
      code: `const lead = validarFormulario({
  nome,
  telefone,
  mensagem,
  origem: 'portfolio',
  interesse: 'app | web | sistema'
})`
    },
    {
      label: 'Segurança',
      code: `if (!csrf_token_valido($_POST['token'])) {
  bloquear_requisicao();
}

$nome = sanitize($_POST['nome']);`
    },
    {
      label: 'Deploy',
      code: `compactar_arquivos
subir_para_public_html
testar_menu_mobile
testar_app_web
testar_formulario
validar_performance`
    }
  ];
  let activeCode = 0;
  let copied = false;
  const tabsEl = $('[data-code-tabs]');
  const devCode = $('#devCode');
  function copyIcon() {
    return copied
      ? '<svg viewBox="0 0 24 24"><path d="m20 6-11 11-5-5"/></svg>'
      : '<svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
  }
  function renderDevCode() {
    if (!tabsEl || !devCode) return;
    tabsEl.innerHTML = codeExamples.map((example, index) => `<button class="tab-button ${index === activeCode ? 'active' : ''}" type="button" data-code-tab="${index}">${example.label}</button>`).join('') + `<button class="copy-button" type="button" aria-label="Copiar código" data-copy>${copyIcon()}</button>`;
    renderCode(devCode, codeExamples[activeCode].code, false);
    $$('[data-code-tab]', tabsEl).forEach(btn => btn.addEventListener('click', () => {
      activeCode = Number(btn.dataset.codeTab);
      copied = false;
      renderDevCode();
    }));
    $('[data-copy]', tabsEl)?.addEventListener('click', async () => {
      try { await navigator.clipboard.writeText(codeExamples[activeCode].code); } catch (_) {}
      copied = true;
      renderDevCode();
      setTimeout(() => { copied = false; renderDevCode(); }, 1800);
    });
  }
  renderDevCode();

  const quotes = [
    {
      quote: 'Não é só página bonita. É estratégia, produto e engenharia para a operação evoluir.',
      author: 'JL Soluções Tecnológicas',
      role: 'Estúdio de produto digital',
      metric: 'Estratégia + execução'
    },
    {
      quote: 'O app precisa ser simples. O sistema precisa organizar. A web precisa vender.',
      author: 'Linha de posicionamento',
      role: 'Presença digital e operação',
      metric: 'Objetivo claro por dobra'
    },
    {
      quote: 'Visual premium só vale se a base técnica for segura, leve e fácil de manter.',
      author: 'Princípio técnico',
      role: 'Segurança, performance e manutenção',
      metric: 'Sem gambiarra'
    },
    {
      quote: 'Portfólio forte mostra domínio de produto, gestão e operação, não apenas criação de telas.',
      author: 'Ecossistema JL',
      role: 'Apps, sistemas personalizados e Media Vault',
      metric: 'Produto + recorrência'
    }
  ];
  let quoteIndex = 0;
  const quoteText = $('#quoteText');
  const quoteAuthor = $('#quoteAuthor');
  const quoteMetric = $('#quoteMetric');
  const quoteCount = $('#quoteCount');
  function renderQuote(withAnimation = false) {
    if (!quoteText || !quoteAuthor || !quoteMetric) return;
    const quote = quotes[quoteIndex];
    const apply = () => {
      quoteText.innerHTML = `“${quote.quote}”`;
      quoteAuthor.innerHTML = `<span class="author-avatar">${quote.author.charAt(0)}</span><span><strong>${quote.author}</strong><span>${quote.role}</span></span>`;
      quoteMetric.innerHTML = `<span>Resultado-chave</span><strong>${quote.metric}</strong>`;
      quoteCount.textContent = `${String(quoteIndex + 1).padStart(2, '0')} / ${String(quotes.length).padStart(2, '0')}`;
    };
    if (!withAnimation) { apply(); return; }
    [quoteText, quoteAuthor, quoteMetric].forEach(el => el.classList.add('animating'));
    setTimeout(() => { apply(); [quoteText, quoteAuthor, quoteMetric].forEach(el => el.classList.remove('animating')); }, 300);
  }
  renderQuote();
  setInterval(() => { quoteIndex = (quoteIndex + 1) % quotes.length; renderQuote(true); }, 5000);

  const brandTrack = $('[data-brand-track]');
  if (brandTrack) {
    const brands = ['Apps', 'Web', 'Gestão Pública', 'Indústria', 'Empresários', 'Autônomos', 'Media Vault', 'Automação', 'UX profissional'];
    brandTrack.innerHTML = [0, 1].map(() => brands.map(brand => `<span>${brand}</span>`).join('')).join('');
  }

  const pricing = {
    initial: [
      ['01', 'App + Web', 'Para transformar atendimento e operação em produto digital.', 'Diagnóstico', ['Interface mobile e web', 'Fluxos de cadastro', 'Painel administrativo', 'Responsivo e rápido', 'Pronto para evoluir'], false, 'Planejar app'],
      ['02', 'Sistema personalizado', 'Para gestão pública, indústria, empresários e autônomos.', 'Sob medida', ['Permissões por setor', 'Relatórios e status', 'Banco estruturado', 'PDF e documentos', 'Validação e segurança'], true, 'Planejar sistema'],
      ['03', 'Presença premium', 'Para fortalecer marca e gerar contato comercial.', 'Projeto', ['Landing page ou institucional', 'Copy e estrutura comercial', 'SEO base', 'CTA para WhatsApp', 'Hospedagem simples'], false, 'Começar presença']
    ],
    evolution: [
      ['01', 'Otimização', 'Melhorias em performance, conteúdo e conversão.', 'Evolução', ['Ajustes de UX', 'Novas seções', 'SEO técnico', 'Eventos e métricas', 'Correções contínuas'], false, 'Otimizar'],
      ['02', 'Automação', 'Fluxos digitais para reduzir retrabalho operacional.', 'Sob demanda', ['Formulários inteligentes', 'Notificações', 'Integrações', 'Dashboards', 'Relatórios automáticos'], true, 'Automatizar'],
      ['03', 'Mídia + Vault', 'Captação com drone e organização técnica dos arquivos.', 'Pacote', ['Fotos e vídeos', 'Organização por projeto', 'Controle de acesso', 'Backup', 'Compartilhamento seguro'], false, 'Falar de mídia']
    ]
  };
  let billing = 'evolution';
  const pricingGrid = $('[data-pricing-grid]');
  const billingToggle = $('[data-billing-toggle]');
  const initialLabel = $('[data-billing-label="initial"]');
  const evolutionLabel = $('[data-billing-label="evolution"]');
  function checkIcon() { return '<svg viewBox="0 0 24 24"><path d="m20 6-11 11-5-5"/></svg>'; }
  function renderPricing() {
    if (!pricingGrid) return;
    billingToggle?.classList.toggle('active', billing === 'evolution');
    billingToggle?.setAttribute('aria-pressed', String(billing === 'evolution'));
    initialLabel?.classList.toggle('active', billing === 'initial');
    evolutionLabel?.classList.toggle('active', billing === 'evolution');
    pricingGrid.innerHTML = pricing[billing].map(plan => `
      <article class="plan-card ${plan[5] ? 'popular' : ''}">
        ${plan[5] ? '<span class="popular-badge">Principal</span>' : ''}
        <span class="plan-num">${plan[0]}</span>
        <h3>${plan[1]}</h3>
        <p>${plan[2]}</p>
        <div class="plan-price"><strong>${plan[3]}</strong></div>
        <ul class="plan-features">${plan[4].map(item => `<li>${checkIcon()}<span>${item}</span></li>`).join('')}</ul>
        <a href="https://ljsolucoestech.com.br/contato.php" class="btn ${plan[5] ? 'btn-dark' : 'btn-outline'} plan-button group-arrow">${plan[6]}<svg viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6" /></svg></a>
      </article>
    `).join('');
  }
  billingToggle?.addEventListener('click', () => { billing = billing === 'initial' ? 'evolution' : 'initial'; renderPricing(); });
  renderPricing();

  const spotlightBox = $('[data-spotlight]');
  const spotlightBg = $('[data-spotlight-bg]');
  spotlightBox?.addEventListener('mousemove', (event) => {
    const rect = spotlightBox.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width) * 100;
    const y = ((event.clientY - rect.top) / rect.height) * 100;
    spotlightBg.style.background = `radial-gradient(600px circle at ${x}% ${y}%, rgba(0,0,0,0.15), transparent 40%)`;
  });

  function setupCanvas(canvas, render) {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let frame = 0;
    function resize() {
      const dpr = window.devicePixelRatio || 1;
      const rect = canvas.getBoundingClientRect();
      canvas.width = rect.width * dpr;
      canvas.height = rect.height * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    resize();
    window.addEventListener('resize', resize, { passive: true });
    function loop() {
      const rect = canvas.getBoundingClientRect();
      ctx.clearRect(0, 0, rect.width, rect.height);
      render(ctx, rect, frame++);
      requestAnimationFrame(loop);
    }
    requestAnimationFrame(loop);
  }

  const chars = '░▒▓█▀▄▌▐│─┤├┴┬╭╮╰╯';
  setupCanvas($('#sphereCanvas'), (ctx, rect, frame) => {
    const time = frame * 0.02;
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const radius = Math.min(rect.width, rect.height) * 0.525;
    ctx.font = '12px monospace';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    const points = [];
    for (let phi = 0; phi < Math.PI * 2; phi += 0.15) {
      for (let theta = 0; theta < Math.PI; theta += 0.15) {
        const x = Math.sin(theta) * Math.cos(phi + time * 0.5);
        const y = Math.sin(theta) * Math.sin(phi + time * 0.5);
        const z = Math.cos(theta);
        const rotY = time * 0.3;
        const newX = x * Math.cos(rotY) - z * Math.sin(rotY);
        const newZ = x * Math.sin(rotY) + z * Math.cos(rotY);
        const rotX = time * 0.2;
        const newY = y * Math.cos(rotX) - newZ * Math.sin(rotX);
        const finalZ = y * Math.sin(rotX) + newZ * Math.cos(rotX);
        const depth = (finalZ + 1) / 2;
        points.push({ x: centerX + newX * radius, y: centerY + newY * radius, z: finalZ, char: chars[Math.floor(depth * (chars.length - 1))] });
      }
    }
    points.sort((a, b) => a.z - b.z);
    points.forEach(point => {
      const alpha = 0.2 + (point.z + 1) * 0.4;
      ctx.fillStyle = frame % 7 === 0 ? `rgba(37, 99, 235, ${Math.min(alpha, .36)})` : `rgba(0, 0, 0, ${alpha})`;
      ctx.fillText(point.char, point.x, point.y);
    });
  });

  setupCanvas($('#tetraCanvas'), (ctx, rect, frame) => {
    const time = frame * 0.015;
    const vertices = [
      { x: 0, y: 1, z: 0 },
      { x: -0.943, y: -0.333, z: -0.5 },
      { x: 0.943, y: -0.333, z: -0.5 },
      { x: 0, y: -0.333, z: 1 }
    ];
    const edges = [[0,1],[0,2],[0,3],[1,2],[2,3],[3,1]];
    const faces = [[0,1,2],[0,2,3],[0,3,1],[1,3,2]];
    const rotateY = (p, angle) => ({ x: p.x * Math.cos(angle) - p.z * Math.sin(angle), y: p.y, z: p.x * Math.sin(angle) + p.z * Math.cos(angle) });
    const rotateX = (p, angle) => ({ x: p.x, y: p.y * Math.cos(angle) - p.z * Math.sin(angle), z: p.y * Math.sin(angle) + p.z * Math.cos(angle) });
    const rotateZ = (p, angle) => ({ x: p.x * Math.cos(angle) - p.y * Math.sin(angle), y: p.x * Math.sin(angle) + p.y * Math.cos(angle), z: p.z });
    const transform = (point) => rotateZ(rotateX(rotateY(point, time * 0.4), time * 0.3), time * 0.2);
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const scale = Math.min(rect.width, rect.height) * 0.7;
    ctx.font = '18px monospace';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    const points = [];
    edges.forEach(([i, j]) => {
      const v1 = vertices[i], v2 = vertices[j];
      for (let t = 0; t <= 1; t += 0.05) {
        const point = transform({ x: v1.x + (v2.x - v1.x) * t, y: v1.y + (v2.y - v1.y) * t, z: v1.z + (v2.z - v1.z) * t });
        const depth = (point.z + 1.5) / 3;
        points.push({ x: centerX + point.x * scale, y: centerY - point.y * scale, z: point.z, char: chars[Math.min(Math.floor(depth * (chars.length - 1)), chars.length - 1)] });
      }
    });
    faces.forEach(([i, j, k]) => {
      const v1 = vertices[i], v2 = vertices[j], v3 = vertices[k];
      for (let u = 0; u <= 1; u += 0.12) {
        for (let v = 0; v <= 1 - u; v += 0.12) {
          const w = 1 - u - v;
          const point = transform({ x: v1.x * u + v2.x * v + v3.x * w, y: v1.y * u + v2.y * v + v3.y * w, z: v1.z * u + v2.z * v + v3.z * w });
          const depth = (point.z + 1.5) / 3;
          points.push({ x: centerX + point.x * scale, y: centerY - point.y * scale, z: point.z, char: chars[Math.min(Math.floor(depth * (chars.length - 1)), chars.length - 1)] });
        }
      }
    });
    points.sort((a, b) => a.z - b.z);
    points.forEach(point => {
      const alpha = Math.min(0.15 + (point.z + 1.5) * 0.25, 0.9);
      ctx.fillStyle = frame % 7 === 0 ? `rgba(37, 99, 235, ${Math.min(alpha, .36)})` : `rgba(0, 0, 0, ${alpha})`;
      ctx.fillText(point.char, point.x, point.y);
    });
  });

  const waveChars = '·∘○◯◌●◉';
  setupCanvas($('#waveCanvas'), (ctx, rect, frame) => {
    const time = frame * 0.03;
    ctx.font = '14px monospace';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    const cols = Math.floor(rect.width / 20);
    const rows = Math.floor(rect.height / 20);
    for (let y = 0; y < rows; y++) {
      for (let x = 0; x < cols; x++) {
        const px = (x + 0.5) * (rect.width / cols);
        const py = (y + 0.5) * (rect.height / rows);
        const wave1 = Math.sin(x * 0.2 + time * 2) * Math.cos(y * 0.15 + time);
        const wave2 = Math.sin((x + y) * 0.1 + time * 1.5);
        const wave3 = Math.cos(x * 0.1 - y * 0.1 + time * 0.8);
        const normalized = ((wave1 + wave2 + wave3) / 3 + 1) / 2;
        ctx.fillStyle = `rgba(0, 0, 0, ${0.15 + normalized * 0.5})`;
        ctx.fillText(waveChars[Math.floor(normalized * (waveChars.length - 1))], px, py);
      }
    }
  });
})();
