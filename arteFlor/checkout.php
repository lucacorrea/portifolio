<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout | Arte&Flor</title>
  <meta name="description" content="Checkout demonstrativo Arte&Flor com Pix visual e envio do pedido pelo WhatsApp.">
  <link rel="stylesheet" href="/arteFlor/assets/css/checkout.css">
</head>

<body>
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="/arteFlor/index.php">
        <span class="brand-icon">🌿</span>
        <span>Arte<span>&</span>Flor</span>
      </a>

      <nav class="main-nav" aria-label="Navegação principal">
        <a href="/arteFlor/index.php">Início</a>
        <a href="/arteFlor/catalogo.php">Catálogo</a>
        <a href="/arteFlor/blog.php">Blog</a>
        <a href="/arteFlor/cliente.php">Área do cliente</a>
        <a class="active" href="/arteFlor/checkout.php">Checkout</a>
      </nav>

      <button class="menu-toggle" type="button" data-menu-toggle aria-label="Abrir menu">☰</button>
    </div>
  </header>

  <main>
    <section class="page-hero checkout-hero">
      <div class="container">
        <div class="checkout-hero-grid">
          <div>
            <span class="badge">Finalização segura</span>
            <h1 class="section-title">Finalizar pedido</h1>
            <p class="section-subtitle">
              Revise os itens, informe a entrega e escolha a forma de pagamento para enviar o pedido pronto ao atendimento da Arte&Flor.
            </p>
          </div>

          <div class="checkout-hero-aside" aria-label="Resumo da experiência">
            <span>Pedido organizado</span>
            <span>Pix visual</span>
            <span>Envio por WhatsApp</span>
          </div>
        </div>
      </div>
    </section>

    <section class="section checkout-section">
      <div class="container checkout-layout">
        <form class="card checkout-form-card" id="checkoutForm">
          <div class="checkout-intro">
            <div>
              <span class="eyebrow">Pedido Arte&Flor</span>
              <h2>Dados do pedido</h2>
              <p>Preencha as informações para gerar uma solicitação clara para a loja.</p>
            </div>

            <div class="checkout-step-list" aria-label="Etapas do checkout">
              <span class="is-active" data-step-indicator="1"><strong>1</strong> Cliente</span>
              <span data-step-indicator="2"><strong>2</strong> Entrega</span>
              <span data-step-indicator="3"><strong>3</strong> Pagamento</span>
              <span data-step-indicator="4"><strong>4</strong> Revisão</span>
            </div>
          </div>

          <fieldset class="checkout-block checkout-step-panel" data-checkout-step="1">
            <legend><span>01</span> Cliente</legend>
            <p class="checkout-block-note">
              Comece pelos dados de contato para a loja confirmar o pedido com agilidade.
            </p>

            <div class="form-grid">
              <label class="form-group">
                <span>Nome completo</span>
                <input name="nome" autocomplete="name" placeholder="Ex: Maria Clara" required>
              </label>

              <label class="form-group">
                <span>WhatsApp</span>
                <input name="whatsapp" inputmode="tel" autocomplete="tel" placeholder="(00) 00000-0000" required>
              </label>
            </div>

            <div class="checkout-step-actions">
              <span class="checkout-step-note">Etapa 1 de 4</span>
              <button class="btn btn-primary" type="button" data-next-step>Continuar para entrega</button>
            </div>
          </fieldset>

          <fieldset class="checkout-block checkout-step-panel" data-checkout-step="2" hidden>
            <legend><span>02</span> Entrega</legend>
            <p class="checkout-block-note">
              Informe como a cliente quer receber o arranjo e quando a loja deve preparar o pedido.
            </p>

            <div class="form-grid">
              <label class="form-group full">
                <span>Endereço</span>
                <input name="endereco" autocomplete="street-address" placeholder="Rua, número e complemento" required>
              </label>

              <label class="form-group">
                <span>Bairro</span>
                <input name="bairro" required>
              </label>

              <label class="form-group">
                <span>Ponto de referência</span>
                <input name="referencia" placeholder="Opcional">
              </label>

              <label class="form-group">
                <span>Tipo de recebimento</span>
                <select name="recebimento" required>
                  <option>Entrega</option>
                  <option>Retirada</option>
                </select>
              </label>

              <label class="form-group">
                <span>Data desejada</span>
                <input name="data" type="date" required>
              </label>

              <label class="form-group">
                <span>Horário desejado</span>
                <input name="horario" type="time" required>
              </label>
            </div>

            <div class="checkout-step-actions">
              <button class="btn btn-outline" type="button" data-prev-step>Voltar</button>
              <span class="checkout-step-note">Etapa 2 de 4</span>
              <button class="btn btn-primary" type="button" data-next-step>Ir para pagamento</button>
            </div>
          </fieldset>

          <fieldset class="checkout-block checkout-step-panel" data-checkout-step="3" hidden>
            <legend><span>03</span> Pagamento</legend>
            <p class="checkout-block-note">
              Selecione uma forma de pagamento. Ao escolher Pix, o QR Code demonstrativo aparecerá automaticamente.
            </p>

            <div class="payment-options">
              <label class="payment-option payment-option-featured">
                <input type="radio" name="pagamento" value="Pix" data-payment-method required>
                <span>
                  <em>PIX</em>
                  <strong>Pix com QR Code</strong>
                  <small>Código copia e cola, status visual e finalização demonstrativa.</small>
                </span>
              </label>

              <label class="payment-option">
                <input type="radio" name="pagamento" value="Presencial" data-payment-method>
                <span>
                  <em>LOJA</em>
                  <strong>Presencial</strong>
                  <small>Para pagar na retirada ou diretamente com a loja.</small>
                </span>
              </label>

              <label class="payment-option">
                <input type="radio" name="pagamento" value="Dinheiro" data-payment-method>
                <span>
                  <em>R$</em>
                  <strong>Dinheiro</strong>
                  <small>O cliente pode informar troco nas observações.</small>
                </span>
              </label>

              <label class="payment-option">
                <input type="radio" name="pagamento" value="Cartão na entrega" data-payment-method>
                <span>
                  <em>CARD</em>
                  <strong>Cartão na entrega</strong>
                  <small>Simulação para pagamento na maquininha.</small>
                </span>
              </label>
            </div>

            <div class="pix-checkout-panel form-group full" data-pix-panel hidden>
              <div class="pix-panel-header">
                <div>
                  <span class="badge">Pix</span>
                  <h2>Pagamento via Pix</h2>
                  <p class="muted">
                    Prévia visual do pagamento. O QR Code abaixo é fictício para apresentação.
                  </p>
                </div>

                <span class="status is-waiting" data-pix-status>Aguardando pagamento</span>
              </div>

              <div class="pix-panel-grid">
                <div class="pix-qr-demo" role="img" aria-label="QR Code Pix demonstrativo">
                  <span></span><span></span><span></span><span></span><span></span><span></span>
                  <span></span><span></span><span></span><span></span><span></span><span></span>
                  <span></span><span></span><span></span><span></span><span></span><span></span>
                  <span></span><span></span><span></span><span></span><span></span><span></span>
                  <span></span><span></span><span></span><span></span><span></span><span></span>
                  <span></span><span></span><span></span><span></span><span></span><span></span>
                  <strong>PIX</strong>
                </div>

                <div class="pix-payment-box">
                  <small>Chave Pix demonstrativa</small>
                  <strong id="pixKey">arteflor@pix.demo</strong>

                  <small>Código copia e cola</small>
                  <code data-pix-code>
00020126580014BR.GOV.BCB.PIX0136arteflor-demo-checkout5204000053039865802BR5910ARTE E FLOR6005COARI62070503***6304DEMO
                  </code>

                  <div class="actions">
                    <button class="btn btn-soft" type="button" data-copy-pix>Copiar código Pix</button>
                    <button class="btn btn-primary" type="button" data-system-finish>Finalizar no sistema</button>
                  </div>

                  <p class="muted" data-system-result>
                    Ao finalizar no sistema, a venda fica marcada como paga apenas nesta demonstração.
                  </p>
                </div>
              </div>
            </div>

            <div class="checkout-step-actions">
              <button class="btn btn-outline" type="button" data-prev-step>Voltar</button>
              <span class="checkout-step-note">Etapa 3 de 4</span>
              <button class="btn btn-primary" type="button" data-next-step>Revisar pedido</button>
            </div>
          </fieldset>

          <fieldset class="checkout-block checkout-step-panel" data-checkout-step="4" hidden>
            <legend><span>04</span> Mensagem e observações</legend>
            <p class="checkout-block-note">
              Finalize com uma mensagem para o cartão e confira o resumo antes de enviar pelo WhatsApp.
            </p>

            <div class="form-grid">
              <label class="form-group full">
                <span>Mensagem para cartão</span>
                <textarea name="cartao" placeholder="Mensagem que acompanha o presente"></textarea>
              </label>

              <label class="form-group full">
                <span>Observações</span>
                <textarea name="observacoes" placeholder="Preferência de flores, cores, embalagem, troco ou instruções de entrega"></textarea>
              </label>
            </div>

            <div class="checkout-review-box" data-review-box aria-live="polite">
              <strong>Resumo para conferência</strong>
              <p>Os principais dados do pedido aparecerão aqui antes do envio.</p>
            </div>

            <div class="checkout-final-note">
              <strong>Pronto para enviar</strong>
              <p>Ao clicar no botão abaixo, o pedido será aberto no WhatsApp com todos os dados preenchidos.</p>
            </div>

            <div class="checkout-step-actions checkout-step-actions-final">
              <button class="btn btn-outline" type="button" data-prev-step>Voltar</button>
              <span class="checkout-step-note">Etapa 4 de 4</span>
              <button class="btn btn-primary" type="submit">Enviar pedido pelo WhatsApp</button>
            </div>
          </fieldset>
        </form>

        <aside class="card checkout-summary">
          <span class="eyebrow">Resumo</span>
          <h2>Seu pedido</h2>

          <div id="checkoutSummary"></div>

          <div class="summary-line">
            <span>Subtotal</span>
            <strong id="checkoutSubtotal">R$ 0,00</strong>
          </div>

          <div class="summary-line">
            <span>Entrega</span>
            <strong>A combinar</strong>
          </div>

          <div class="summary-line">
            <span>Total</span>
            <strong class="price" id="checkoutTotal">R$ 0,00</strong>
          </div>

          <button class="btn btn-soft checkout-demo-button" type="button" data-load-demo-order>
            Simular pedido de apresentação
          </button>

          <div class="checkout-next-step">
            <strong>Próximo passo</strong>
            <p>Depois de preencher o formulário, o sistema monta uma mensagem organizada para o WhatsApp da loja.</p>
          </div>

          <p class="muted" style="margin-top: 14px;">
            Pagamento real e backend serão conectados na próxima etapa.
          </p>
        </aside>
      </div>
    </section>
  </main>

  <div class="toast" data-toast></div>

  <script>
    (function () {
      const WHATSAPP_NUMBER = '5597000000000'; // TROQUE AQUI PELO WHATSAPP DA LOJA
      const CART_KEY = 'arteflor_cart';
      const ORDERS_KEY = 'arteflor_orders';
      const SALES_KEY = 'arteflor_demo_sales';

      const demoItems = [
        {
          id: 1001,
          nome: 'Buquê Tons Pastel',
          preco: 119.90,
          qty: 1,
          imagem: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=500&q=80'
        },
        {
          id: 1002,
          nome: 'Cartão personalizado',
          preco: 12.00,
          qty: 1,
          imagem: 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?auto=format&fit=crop&w=500&q=80'
        }
      ];

      const $ = (selector) => document.querySelector(selector);
      const $$ = (selector) => document.querySelectorAll(selector);

      const money = (value) => {
        return Number(value || 0).toLocaleString('pt-BR', {
          style: 'currency',
          currency: 'BRL'
        });
      };

      const escapeHtml = (value) => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

      const getJson = (key, fallback = []) => {
        try {
          return JSON.parse(localStorage.getItem(key) || JSON.stringify(fallback));
        } catch (error) {
          return fallback;
        }
      };

      const setJson = (key, value) => {
        localStorage.setItem(key, JSON.stringify(value));
      };

      const getCart = () => getJson(CART_KEY, []);
      const setCart = (cart) => setJson(CART_KEY, cart);

      const getCartTotal = (cart) => cart.reduce((sum, item) => {
        return sum + Number(item.preco || 0) * Number(item.qty || 0);
      }, 0);

      const toast = (message) => {
        const toastEl = $('[data-toast]');
        if (!toastEl) return;

        toastEl.textContent = message;
        toastEl.classList.add('is-visible');

        setTimeout(() => {
          toastEl.classList.remove('is-visible');
        }, 3200);
      };

      const whatsappUrl = (message) => {
        return `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`;
      };

      const saveOrder = (order) => {
        const orders = getJson(ORDERS_KEY, []);
        orders.unshift(order);
        setJson(ORDERS_KEY, orders.slice(0, 20));
      };

      const renderSummary = () => {
        const summary = $('#checkoutSummary');
        const subtotal = $('#checkoutSubtotal');
        const total = $('#checkoutTotal');

        if (!summary || !subtotal || !total) return;

        const cart = getCart();
        const value = getCartTotal(cart);

        if (!cart.length) {
          summary.innerHTML = `
            <div class="empty-state checkout-empty-state">
              <strong>Nenhum produto no carrinho.</strong>
              <p>Use a simulação para apresentar a experiência completa sem depender de um carrinho real.</p>
            </div>
          `;
        } else {
          summary.innerHTML = cart.map((item) => `
            <div class="checkout-summary-item">
              ${item.imagem ? `<img src="${escapeHtml(item.imagem)}" alt="Imagem de ${escapeHtml(item.nome)}" loading="lazy">` : ''}
              <div>
                <strong>${escapeHtml(item.nome)}</strong>
                <span>${Number(item.qty || 0)} un. · ${money(Number(item.preco || 0))}</span>
              </div>
              <b>${money(Number(item.preco || 0) * Number(item.qty || 0))}</b>
            </div>
          `).join('');
        }

        subtotal.textContent = money(value);
        total.textContent = money(value);
      };

      const getPaymentValue = (form) => {
        const data = new FormData(form);
        return data.get('pagamento') || '';
      };

      const buildMessage = (data, cart, total, pixStatusText) => {
        const items = cart.length
          ? cart.map((item) => `- ${item.qty}x ${item.nome} (${money(Number(item.preco || 0) * Number(item.qty || 0))})`).join('\n')
          : '- Pedido personalizado sem itens no carrinho.';

        return [
          'Olá, vim pelo site da Arte&Flor.',
          '',
          'Pedido:',
          items,
          '',
          `Total demonstrativo: ${money(total)}`,
          '',
          `Cliente: ${data.nome || '-'}`,
          `WhatsApp: ${data.whatsapp || '-'}`,
          `Recebimento: ${data.recebimento || '-'}`,
          `Data desejada: ${data.data || '-'}`,
          `Horário desejado: ${data.horario || '-'}`,
          `Bairro: ${data.bairro || '-'}`,
          `Endereço: ${data.endereco || '-'}`,
          `Referência: ${data.referencia || '-'}`,
          `Pagamento: ${data.pagamento || '-'}`,
          pixStatusText ? `Status Pix demonstrativo: ${pixStatusText}` : '',
          `Mensagem para cartão: ${data.cartao || 'Nenhuma'}`,
          `Observações: ${data.observacoes || 'Nenhuma'}`
        ].filter(Boolean).join('\n');
      };

      document.addEventListener('DOMContentLoaded', () => {
        const form = $('#checkoutForm');
        const paymentMethods = $$('[data-payment-method]');
        const pixPanel = $('[data-pix-panel]');
        const pixStatus = $('[data-pix-status]');
        const pixCode = $('[data-pix-code]');
        const copyPixButton = $('[data-copy-pix]');
        const finishSystemButton = $('[data-system-finish]');
        const systemResult = $('[data-system-result]');
        const loadDemoButton = $('[data-load-demo-order]');
        const menuToggle = $('[data-menu-toggle]');
        const mainNav = $('.main-nav');
        const reviewBox = $('[data-review-box]');
        const stepPanels = $$('[data-checkout-step]');
        const stepIndicators = $$('[data-step-indicator]');
        const nextStepButtons = $$('[data-next-step]');
        const prevStepButtons = $$('[data-prev-step]');
        const dateInput = form?.querySelector('input[name="data"]');

        let systemFinished = false;
        let systemOrderCode = '';
        let currentStep = 1;

        menuToggle?.addEventListener('click', () => {
          mainNav?.classList.toggle('open');
        });

        renderSummary();

        if (dateInput) {
          const today = new Date();
          today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
          dateInput.min = today.toISOString().slice(0, 10);
        }

        const togglePixPanel = ({ scroll = false } = {}) => {
          const isPix = form ? getPaymentValue(form) === 'Pix' : false;
          const shouldShowPixPanel = isPix && currentStep === 3;

          if (pixPanel) {
            pixPanel.hidden = !shouldShowPixPanel;
          }

          if (!isPix && pixStatus) {
            pixStatus.textContent = 'Aguardando pagamento';
            pixStatus.classList.remove('is-paid');
            pixStatus.classList.add('is-waiting');
          }

          if (shouldShowPixPanel && scroll) {
            setTimeout(() => {
              pixPanel?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 80);
          }
        };

        const getStepPanel = (step) => {
          return Array.from(stepPanels).find((panel) => Number(panel.dataset.checkoutStep) === step);
        };

        const validateStep = (step) => {
          const panel = getStepPanel(step);
          if (!panel) return true;

          const fields = Array.from(panel.querySelectorAll('input, select, textarea'))
            .filter((field) => !field.disabled);

          for (const field of fields) {
            if (!field.checkValidity()) {
              field.reportValidity();
              return false;
            }
          }

          return true;
        };

        const renderReview = () => {
          if (!form || !reviewBox) return;

          const data = Object.fromEntries(new FormData(form).entries());
          const cart = getCart();
          const total = getCartTotal(cart);
          const paymentLabel = data.pagamento === 'Pix' && systemFinished
            ? `Pix confirmado (${systemOrderCode})`
            : (data.pagamento || '-');

          reviewBox.innerHTML = `
            <strong>Resumo para conferência</strong>
            <div class="checkout-review-grid">
              <span><small>Cliente</small><b>${escapeHtml(data.nome || '-')}</b></span>
              <span><small>WhatsApp</small><b>${escapeHtml(data.whatsapp || '-')}</b></span>
              <span><small>Recebimento</small><b>${escapeHtml(data.recebimento || '-')}</b></span>
              <span><small>Data e horário</small><b>${escapeHtml(data.data || '-')} às ${escapeHtml(data.horario || '-')}</b></span>
              <span><small>Pagamento</small><b>${escapeHtml(paymentLabel)}</b></span>
              <span><small>Total</small><b>${money(total)}</b></span>
            </div>
            <p>${cart.length ? 'Resumo baseado nos itens do carrinho local.' : 'Pedido sem itens no carrinho; será tratado como atendimento personalizado.'}</p>
          `;
        };

        const showStep = (step, { scroll = true } = {}) => {
          currentStep = Math.min(Math.max(step, 1), 4);

          stepPanels.forEach((panel) => {
            panel.hidden = Number(panel.dataset.checkoutStep) !== currentStep;
          });

          stepIndicators.forEach((indicator) => {
            const indicatorStep = Number(indicator.dataset.stepIndicator);
            indicator.classList.toggle('is-active', indicatorStep === currentStep);
            indicator.classList.toggle('is-complete', indicatorStep < currentStep);
          });

          togglePixPanel();

          if (currentStep === 4) {
            renderReview();
          }

          if (scroll) {
            form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        };

        const saveDemoSale = () => {
          const cart = getCart();
          const total = getCartTotal(cart);
          const sales = getJson(SALES_KEY, []);

          systemOrderCode = `AF-${String(Date.now()).slice(-6)}`;
          systemFinished = true;

          sales.unshift({
            codigo: systemOrderCode,
            status: 'Venda finalizada no sistema',
            pagamento: 'Pix',
            total,
            itens: cart,
            criadoEm: new Date().toISOString()
          });

          setJson(SALES_KEY, sales.slice(0, 10));

          if (pixStatus) {
            pixStatus.textContent = 'Pagamento confirmado';
            pixStatus.classList.remove('is-waiting');
            pixStatus.classList.add('is-paid');
          }

          if (systemResult) {
            systemResult.textContent = `Venda ${systemOrderCode} finalizada no sistema demonstrativo. Nenhum pagamento real foi processado.`;
          }

          if (finishSystemButton) {
            finishSystemButton.textContent = 'Venda finalizada';
            finishSystemButton.disabled = true;
          }

          toast(`Venda ${systemOrderCode} finalizada no sistema demonstrativo.`);
          renderReview();
        };

        paymentMethods.forEach((input) => {
          input.addEventListener('change', () => togglePixPanel({ scroll: true }));
        });

        nextStepButtons.forEach((button) => {
          button.addEventListener('click', () => {
            if (!validateStep(currentStep)) {
              toast('Preencha os campos obrigatórios para continuar.');
              return;
            }

            showStep(currentStep + 1);
          });
        });

        prevStepButtons.forEach((button) => {
          button.addEventListener('click', () => {
            showStep(currentStep - 1);
          });
        });

        showStep(1, { scroll: false });

        loadDemoButton?.addEventListener('click', () => {
          setCart(demoItems);
          renderSummary();
          renderReview();
          toast('Pedido de apresentação carregado no resumo.');
        });

        form?.addEventListener('input', renderReview);
        form?.addEventListener('change', renderReview);

        copyPixButton?.addEventListener('click', async () => {
          const code = pixCode?.textContent?.trim() || '';

          try {
            await navigator.clipboard.writeText(code);
            if (systemResult) {
              systemResult.textContent = 'Código Pix demonstrativo copiado.';
            }
            toast('Código Pix copiado.');
          } catch (error) {
            if (systemResult) {
              systemResult.textContent = 'Não foi possível copiar automaticamente. Selecione e copie o código manualmente.';
            }
            toast('Copie o código Pix manualmente.');
          }
        });

        finishSystemButton?.addEventListener('click', saveDemoSale);

        form?.addEventListener('submit', (event) => {
          event.preventDefault();

          if (!validateStep(currentStep)) {
            toast('Revise os campos obrigatórios antes de enviar.');
            return;
          }

          const cart = getCart();
          const total = getCartTotal(cart);
          const data = Object.fromEntries(new FormData(form).entries());
          const codigo = `AF-${String(Date.now()).slice(-5)}`;

          const pixStatusText = data.pagamento === 'Pix'
            ? (systemFinished ? `finalizado no sistema (${systemOrderCode})` : 'QR Code exibido, confirmação pendente')
            : '';

          saveOrder({
            codigo,
            status: 'Pedido recebido',
            pagamento: data.pagamento,
            total,
            itensResumo: cart.length
              ? cart.map((item) => `${item.qty}x ${item.nome}`).join(', ')
              : 'Atendimento manual',
            criadoEm: new Date().toISOString()
          });

          window.open(whatsappUrl(buildMessage(data, cart, total, pixStatusText)), '_blank', 'noopener');

          toast(`Pedido #${codigo} gerado para WhatsApp.`);
        });
      });
    })();
  </script>
</body>
</html>
