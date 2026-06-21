class PwaInstallManager {
  constructor(root) {
    this.root = root;
    this.button = root.querySelector('[data-pwa-install-button]');
    this.status = root.querySelector('[data-pwa-status]');
    this.promptEvent = null;
    this.hasLogo = root.dataset.hasLogo === '1';
    this.hasAppName = root.dataset.hasAppName === '1';

    this.onBeforeInstallPrompt = this.onBeforeInstallPrompt.bind(this);
    this.onAppInstalled = this.onAppInstalled.bind(this);
  }

  init() {
    if (!this.button || !this.status) return;

    if (!this.hasAppName || !this.hasLogo) {
      this.disable('Cadastre o nome do aplicativo e uma logo válida antes de instalar.');
      return;
    }

    if (this.isStandalone()) {
      this.disable('Aplicativo já instalado.');
      return;
    }

    this.disable(this.isIos() ? 'No iPhone/iPad, use Compartilhar e Adicionar à Tela de Início no Safari.' : 'Aguardando disponibilidade de instalação do navegador.');

    window.addEventListener('beforeinstallprompt', this.onBeforeInstallPrompt);
    window.addEventListener('appinstalled', this.onAppInstalled);
    this.button.addEventListener('click', () => this.install());
  }

  onBeforeInstallPrompt(event) {
    event.preventDefault();
    this.promptEvent = event;
    this.enable('Instalação disponível.');
  }

  async install() {
    if (!this.promptEvent) {
      this.disable(this.isIos() ? 'Use Compartilhar e Adicionar à Tela de Início no Safari.' : 'O navegador ainda não liberou o prompt de instalação.');
      return;
    }

    const event = this.promptEvent;
    this.promptEvent = null;
    this.disable('Abrindo instalação.');

    await event.prompt();
    const choice = await event.userChoice;

    if (choice && choice.outcome === 'accepted') {
      this.disable('Instalação aceita. O aplicativo ficará disponível no dispositivo.');
      return;
    }

    this.disable('Instalação cancelada. O botão voltará quando o navegador liberar novo prompt.');
  }

  onAppInstalled() {
    this.promptEvent = null;
    this.disable('Aplicativo já instalado.');
  }

  enable(message) {
    this.button.disabled = false;
    this.status.textContent = message;
    this.status.classList.remove('error');
    this.status.classList.add('success');
  }

  disable(message) {
    this.button.disabled = true;
    this.status.textContent = message;
    this.status.classList.remove('success');
    if (!this.hasLogo || !this.hasAppName) {
      this.status.classList.add('error');
    }
  }

  isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
      || window.navigator.standalone === true;
  }

  isIos() {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent || '');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-pwa-install]').forEach((root) => {
    new PwaInstallManager(root).init();
  });
});
