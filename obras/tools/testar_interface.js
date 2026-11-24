/**
 * OBRAS360 — teste preventivo de renderização (sem banco, navegador ou pacotes externos).
 * Execute no VS Code: node obras/tools/testar_interface.js
 * Se alguma constante/função necessária desaparecer durante alterações no app.js,
 * este teste falha em vez de permitir uma nova publicação com o painel vazio.
 */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/app.js'), 'utf8');
const pages = [
  'dashboard', 'obras', 'mapa', 'ordens', 'planejamento', 'fiscalizacao',
  'projetos', 'orcamentos', 'materiais', 'patrimonio', 'protocolos',
  'relatorios', 'formularios', 'usuarios', 'auditoria', 'cadastro',
  'marco', 'movimentacao', 'encaminhamento', 'usuario-form'
];
let failed = 0;
for (const page of pages) {
  const app = {innerHTML: ''};
  const root = {innerHTML: ''};
  const document = {
    body: {dataset: {page, module: 'obras'}, classList: {remove(){}, add(){}}},
    title: '',
    querySelector(sel) {return sel === '#app' ? app : sel === '#page-root' ? root : null;},
    querySelectorAll() {return [];},
    addEventListener() {}
  };
  const location = {href: '', search: '', assign() {}};
  const window = {
    location,
    OBRAS_BOOTSTRAP: {
      records: {
        obras: [{id: 'OBR-TESTE', title: 'Obra de teste', local: 'Coari',
          responsavel: 'Equipe', status: 'Em andamento', progresso: 50,
          orcamento: 100000, inicio: '2026-11-01', fim: '2026-12-31'}],
        ordens: []
      },
      user: {id: 1, name: 'Suporte de teste', role: 'suporte'},
      csrf: 'somente-teste'
    }
  };
  const sandbox = {
    window, document, location, console, Intl, Date, URLSearchParams,
    FormData: function FormData(){},
    setTimeout, clearTimeout,
    requestAnimationFrame() {} // Os mapas Leaflet requerem DOM real; foco no carregamento inicial.
  };
  try {
    vm.runInNewContext(source, sandbox, {filename: 'public/assets/app.js', timeout: 5000});
    if (!app.innerHTML.includes('class="sidebar"') ||
        !root.innerHTML.includes('class="page-head"') ||
        root.innerHTML.length < 300) {
      throw new Error('O conteúdo central do módulo não foi renderizado.');
    }
    if (page === 'dashboard' && !root.innerHTML.includes('Indicadores estratégicos')) {
      throw new Error('Os indicadores do painel não foram renderizados.');
    }
    console.log('OK  ', page);
  } catch (error) {
    failed++;
    console.error('ERRO', page, String(error && error.stack || error));
  }
}
console.log(`\nVerificação: ${pages.length - failed}/${pages.length} telas renderizadas.`);
if (failed) process.exitCode = 1;
