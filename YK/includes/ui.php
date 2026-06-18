<?php
function h($value): string {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money($value): string {
  return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function badge_class(string $text): string {
  $map = [
    'Aberta' => 'blue', 'Agendada' => 'blue', 'Agendado' => 'blue', 'Enviado' => 'blue',
    'Confirmado' => 'teal', 'Em deslocamento' => 'purple',
    'Em execução' => 'amber', 'Em andamento' => 'amber', 'Rascunho' => 'gray',
    'Aguardando peça' => 'purple', 'Aguardando pagamento' => 'purple', 'Aguardando aprovação' => 'purple',
    'Finalizada' => 'green', 'Concluído' => 'green', 'Aprovado' => 'green', 'Ativo' => 'green', 'Emitida' => 'green', 'Pago' => 'green',
    'Cancelado' => 'red', 'Urgente' => 'red', 'Alta' => 'red', 'Vencido' => 'red', 'Recusado' => 'red', 'Sem estoque' => 'red',
    'Baixa' => 'blue', 'Média' => 'amber',
    'Estoque baixo' => 'amber', 'Convertido em OS' => 'teal', 'Pendente' => 'amber',
  ];
  return $map[$text] ?? 'gray';
}

function ui_badge(string $text): string {
  return '<span class="badge-soft badge-' . h(badge_class($text)) . '">' . h($text) . '</span>';
}

function metric_card(string $label, string $value, string $icon, string $accent, string $note = ''): string {
  return '<div class="metric-card" style="--card-accent:' . h($accent) . '">
    <div class="metric-head">
      <div class="metric-label">' . h($label) . '</div>
      <div class="metric-icon-wrap" style="--icon-color:' . h($accent) . '"><i class="bi ' . h($icon) . '"></i></div>
    </div>
    <div class="metric-value">' . h($value) . '</div>
    <div class="metric-footer"><span>' . h($note ?: 'visual') . '</span></div>
  </div>';
}

function metric_grid(array $cards): void {
  echo '<div class="metrics-grid">';
  foreach ($cards as $card) {
    echo metric_card($card[0], (string) $card[1], $card[2], $card[3], $card[4] ?? '');
  }
  echo '</div>';
}

function filter_bar(array $filters, string $placeholder = 'Buscar...'): void {
  echo '<div class="filter-bar" aria-label="Filtros visuais">';
  echo '<div class="search-wrap"><i class="bi bi-search"></i><input class="search-input" type="search" placeholder="' . h($placeholder) . '"></div>';
  foreach ($filters as $filter) {
    $label = $filter[0];
    $options = $filter[1] ?? [];
    if (($filter[2] ?? '') === 'date') {
      echo '<input class="filter-select input-date" type="date" aria-label="' . h($label) . '">';
      continue;
    }
    echo '<select class="filter-select" aria-label="' . h($label) . '"><option>' . h($label) . '</option>';
    foreach ($options as $option) echo '<option>' . h($option) . '</option>';
    echo '</select>';
  }
  echo '<button class="btn-filter btn-filter-primary" type="button"><i class="bi bi-funnel"></i> Filtrar</button>';
  echo '<button class="btn-filter btn-filter-ghost" type="button"><i class="bi bi-x-lg"></i> Limpar</button>';
  echo '</div>';
}

function action_menu(): string {
  return '<div class="dropdown text-center">
    <button class="btn-action" type="button" data-bs-toggle="dropdown" aria-label="Ações"><i class="bi bi-three-dots-vertical"></i></button>
    <ul class="dropdown-menu dropdown-menu-end">
      <li><button class="dropdown-item" type="button"><i class="bi bi-eye"></i> Visualizar</button></li>
      <li><button class="dropdown-item" type="button"><i class="bi bi-pencil"></i> Editar</button></li>
      <li><button class="dropdown-item" type="button"><i class="bi bi-printer"></i> Imprimir</button></li>
      <li><hr class="dropdown-divider"></li>
      <li><button class="dropdown-item text-danger" type="button"><i class="bi bi-x-circle"></i> Cancelar</button></li>
    </ul>
  </div>';
}

function ui_table(array $columns, array $rows, array $options = []): void {
  $class = 'table-panel-wrap' . (!empty($options['scroll']) ? ' table-scroll-y' : '');
  echo '<div class="' . h($class) . '"><table class="os-table">';
  echo '<thead><tr>';
  foreach ($columns as $column) echo '<th>' . h($column) . '</th>';
  echo '</tr></thead><tbody>';
  foreach ($rows as $row) {
    echo '<tr>';
    foreach ($row as $cell) echo '<td>' . $cell . '</td>';
    echo '</tr>';
  }
  echo '</tbody></table></div>';
}

function empty_state(string $title, string $text): void {
  echo '<div class="empty-state"><i class="bi bi-inbox"></i><strong>' . h($title) . '</strong><p>' . h($text) . '</p></div>';
}

function pagination_visual(): void {
  echo '<div class="pagination-bar"><span>Exibindo dados fictícios para demonstração do layout</span><div class="pagination-controls">
    <button class="page-btn" type="button"><i class="bi bi-chevron-left"></i></button>
    <button class="page-btn active" type="button">1</button>
    <button class="page-btn" type="button">2</button>
    <button class="page-btn" type="button">3</button>
    <button class="page-btn" type="button"><i class="bi bi-chevron-right"></i></button>
  </div></div>';
}

function modal_shell(string $id, string $title, string $body, string $footer = ''): void {
  echo '<div class="modal fade" id="' . h($id) . '" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content visual-modal">
        <div class="modal-header">
          <h2 class="modal-title fs-5">' . h($title) . '</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">' . $body . '</div>
        <div class="modal-footer">' . ($footer ?: '<button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="button">Salvar visualmente</button>') . '</div>
      </div>
    </div>
  </div>';
}

function field(string $label, string $value = '', string $type = 'text'): string {
  if ($type === 'textarea') {
    return '<div class="form-group"><label class="form-label">' . h($label) . '</label><textarea class="form-control-os" rows="3">' . h($value) . '</textarea></div>';
  }
  return '<div class="form-group"><label class="form-label">' . h($label) . '</label><input class="form-control-os" type="' . h($type) . '" value="' . h($value) . '"></div>';
}

function select_field(string $label, array $options): string {
  $html = '<div class="form-group"><label class="form-label">' . h($label) . '</label><select class="form-control-os">';
  foreach ($options as $option) $html .= '<option>' . h($option) . '</option>';
  return $html . '</select></div>';
}

function form_section(string $title, string $html): string {
  return '<section class="form-section"><h3 class="form-section-title">' . h($title) . '</h3>' . $html . '</section>';
}

function mock_os(): array {
  return [
    ['OS-00258','Restaurante Sabor Norte','Câmara Fria','Manutenção preventiva','Carlos','Rafael','18/06/2026','Alta','Em execução','Pendente',1320],
    ['OS-00257','Mercado Ponto Frio','Freezer Vertical','Troca de compressor','Ana','Bruno','18/06/2026','Urgente','Aguardando peça','Aguardando pagamento',2380],
    ['OS-00256','Clínica Bem Estar','Split LG 18.000','Limpeza completa','Lucas','Mateus','19/06/2026','Média','Agendada','Pago',360],
    ['OS-00255','Padaria Santa Luzia','Balcão Refrigerado','Carga de gás','Pedro','Caio','17/06/2026','Baixa','Finalizada','Pago',520],
    ['OS-00254','João Almeida','Ar Janela','Diagnóstico técnico','Carlos','Nina','20/06/2026','Média','Aberta','Pendente',120],
    ['OS-00253','Condomínio Jardim Europa','Split 24.000','Instalação','Ana','Rafael','21/06/2026','Alta','Agendada','Pendente',1650],
    ['OS-00252','Sorveteria Polar','Freezer Horizontal','Vazamento de gás','Lucas','Bruno','19/06/2026','Urgente','Em execução','Pendente',890],
    ['OS-00251','Hotel Amazonas','Cassete 36.000','Manutenção corretiva','Pedro','Caio','22/06/2026','Alta','Aguardando peça','Pendente',1960],
    ['OS-00250','Farmácia Vida','Geladeira comercial','Troca de sensor','Ana','Nina','18/06/2026','Média','Finalizada','Pago',440],
    ['OS-00249','Escritório Norte','Piso-Teto','Higienização','Carlos','Mateus','23/06/2026','Baixa','Aberta','Pendente',760],
    ['OS-00248','Loja Tropical','Bebedouro','Manutenção preventiva','Lucas','Bruno','24/06/2026','Baixa','Agendada','Pago',220],
    ['OS-00247','Depósito FrioMax','Câmara Fria','Revisão elétrica','Pedro','Rafael','24/06/2026','Alta','Aberta','Pendente',1100],
  ];
}

function render_dashboard(): void {
  echo '<div class="page-body">';
  metric_grid([
    ['OS abertas','34','bi-folder2-open','#2563EB','aguardando atendimento'],
    ['OS em andamento','18','bi-arrow-repeat','#D97706','equipes em campo'],
    ['Serviços da semana','42','bi-calendar-week','#0EA5E9','agenda técnica'],
    ['Aguardando peça','12','bi-box-seam','#7C3AED','compras pendentes'],
    ['Aguardando pagamento','9','bi-wallet2','#9333EA','cobranças abertas'],
    ['Orçamentos pendentes','17','bi-file-earmark-text','#F59E0B','aprovação do cliente'],
    ['Estoque baixo','6','bi-exclamation-triangle','#DC2626','itens críticos'],
    ['Recebimentos do mês',money(48720),'bi-cash-stack','#16A34A','entrada visual'],
  ]);

  echo '<section class="quick-actions panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-lightning-charge"></i>Ações rápidas</div></div><div class="quick-grid">';
  foreach ([['Nova OS','bi-plus-circle','#modal-os'],['Novo Orçamento','bi-file-earmark-plus','#modal-orcamento'],['Novo Cliente','bi-person-plus','#modal-cliente'],['Venda Avulsa','bi-cart-plus','#modal-venda'],['Novo Lembrete','bi-alarm','#modal-lembrete']] as $action) {
    echo '<button class="quick-action" type="button" data-bs-toggle="modal" data-bs-target="' . h($action[2]) . '"><i class="bi ' . h($action[1]) . '"></i><span>' . h($action[0]) . '</span></button>';
  }
  echo '</div></section>';

  echo '<div class="content-area dashboard-layout"><section class="panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-calendar2-week"></i>Serviços da semana</div><span class="panel-tag">visual</span></div>';
  ui_table(['Dia','Horário','Cliente','Serviço','Instalador','Ajudante','Status'], [
    ['Segunda','08:30','Restaurante Sabor Norte','Câmara fria','Carlos','Rafael',ui_badge('Em execução')],
    ['Terça','10:00','Mercado Ponto Frio','Troca de compressor','Ana','Bruno',ui_badge('Aguardando peça')],
    ['Quarta','14:00','Clínica Bem Estar','Limpeza split','Lucas','Mateus',ui_badge('Agendada')],
    ['Quinta','09:30','Hotel Amazonas','Cassete 36.000','Pedro','Caio',ui_badge('Aberta')],
    ['Sexta','15:00','Sorveteria Polar','Vazamento de gás','Lucas','Bruno',ui_badge('Urgente')],
  ]);
  echo '</section><aside class="side-panels"><section class="panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-activity"></i>Resumo operacional</div></div><div class="mini-chart"><span style="height:78%"></span><span style="height:54%"></span><span style="height:68%"></span><span style="height:46%"></span><span style="height:82%"></span></div></section>';
  empty_state('Nenhum aviso crítico novo', 'Estado vazio visual para notificações operacionais.');
  echo '</aside></div>';

  echo '<section class="panel mt-4"><div class="panel-header"><div class="panel-title"><i class="bi bi-clock-history"></i>Últimas Ordens de Serviço</div></div>';
  $rows = array_map(fn($r) => [$r[0], $r[1], $r[3], $r[4], ui_badge($r[8]), money($r[10]), action_menu()], array_slice(mock_os(), 0, 6));
  ui_table(['Número','Cliente','Serviço','Instalador','Status','Valor','Ações'], $rows);
  pagination_visual();
  echo '</section></div>';
  render_common_modals();
}

function render_common_modals(): void {
  modal_shell('modal-os', 'Nova Ordem de Serviço', os_modal_body(), '<button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-secondary" type="button">Salvar rascunho</button><button class="btn-modal-save" type="button">Salvar OS</button><button class="btn-modal-secondary" type="button">Imprimir</button><button class="btn-modal-secondary" type="button">Gerar recibo</button><button class="btn-modal-secondary" type="button">Dar baixa no pagamento</button>');
  modal_shell('modal-orcamento', 'Novo Orçamento', budget_modal_body(), '<button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-secondary" type="button">Salvar rascunho</button><button class="btn-modal-save" type="button">Salvar orçamento</button><button class="btn-modal-warning" type="button">Transformar em Ordem de Serviço</button>');
  modal_shell('modal-cliente', 'Novo Cliente', cliente_modal_body());
  modal_shell('modal-venda', 'Venda avulsa', venda_modal_body(), '<button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar venda</button><button class="btn-modal-save" type="button">Finalizar venda</button><button class="btn-modal-secondary" type="button">Imprimir cupom</button>');
  modal_shell('modal-lembrete', 'Novo lembrete', lembrete_modal_body());
  modal_shell('modal-peca', 'Novo Produto / Peça', produto_modal_body());
  modal_shell('modal-servico', 'Novo Serviço', servico_modal_body());
  modal_shell('modal-funcionario', 'Novo Funcionário', funcionario_modal_body());
  modal_shell('modal-fornecedor', 'Novo Fornecedor', fornecedor_modal_body());
  modal_shell('modal-transportadora', 'Nova Transportadora', transportadora_modal_body());
  modal_shell('modal-recibo', 'Novo Recibo Visual', form_section('Recibo', '<div class="form-row">' . field('Cliente','João Almeida') . field('Referência','OS-00254') . field('Valor','120,00') . field('Data','2026-06-18','date') . '</div>' . field('Referente a','Diagnóstico técnico em ar-condicionado janela.','textarea')));
  modal_shell('modal-relatorio', 'Exportação Visual', form_section('Relatório', '<div class="form-row">' . select_field('Tipo',['Visão Geral','Produtividade','Serviços por Funcionário','Financeiro','Estoque']) . field('Período inicial','2026-06-01','date') . field('Período final','2026-06-30','date') . select_field('Formato visual',['Tela','PDF futuro','Planilha futura']) . '</div>' . field('Observações','Botão apenas visual; exportação real será implementada em outra etapa.','textarea')), '<button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="button">Exportar visualmente</button>');
  modal_shell('modal-config', 'Salvar Configurações Visuais', form_section('Confirmação visual', '<p class="section-note">As configurações exibidas nesta tela são apenas demonstrativas nesta etapa. Nenhuma informação será persistida.</p>'), '<button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="button">Salvar visualmente</button>');
  modal_shell('modal-servico-semanal', 'Adicionar Serviço Semanal', servico_semanal_modal_body(), '<button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="button">Adicionar visualmente</button>');
}

function os_modal_body(): string {
  $equipamentos = ['Ar-condicionado Split','Ar-condicionado Janela','Ar-condicionado Cassete','Ar-condicionado Piso-Teto','Freezer Horizontal','Freezer Vertical','Câmara Fria','Balcão Refrigerado','Bebedouro','Geladeira','Outro'];
  $ambientes = ['Quarto','Sala','Cozinha','Escritório','Loja','Salão','Área externa','Depósito','Outro'];
  return '<ul class="nav nav-tabs visual-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#os-cliente" type="button">Cliente</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#os-equipe" type="button">Equipe e diagnóstico</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#os-itens" type="button">Serviços e peças</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#os-valores" type="button">Agenda e valores</button></li>
  </ul>
  <div class="tab-content pt-3">
    <div class="tab-pane fade show active" id="os-cliente">' .
      form_section('Cliente', '<div class="form-row">' . field('Cliente','Restaurante Sabor Norte') . field('Telefone','(92) 98844-1188') . field('WhatsApp','(92) 98844-1188') . field('Endereço','Av. Djalma Batista, 1240') . '</div>') .
      form_section('Equipamento', '<div class="form-row-3">' . select_field('Tipo', $equipamentos) . field('Marca','Elgin') . field('Modelo','Eco Inverter') . field('Capacidade','18.000 BTUs') . field('Número de série','ELG-2026-118') . field('Local de instalação','Salão principal') . '</div>' . field('Descrição','Unidade com perda de rendimento e ruído no evaporador.','textarea')) .
    '</div>
    <div class="tab-pane fade" id="os-equipe">' .
      form_section('Diagnóstico', field('Problema relatado','Equipamento não está gelando adequadamente.','textarea') . field('Problema identificado','Filtros saturados e baixa pressão na linha.','textarea') . field('Diagnóstico','Necessária limpeza técnica e revisão de gás.','textarea') . field('Solução','Higienização, teste de pressão e complemento de gás.','textarea') . field('Recomendação','Manutenção preventiva trimestral.','textarea') . field('Observações internas','Cliente solicita atendimento no primeiro horário.','textarea')) .
      form_section('Equipe', '<div class="form-row">' . select_field('Instalador', ['Carlos Ferreira','Ana Martins','Lucas Ferreira','Pedro Alves']) . select_field('Ajudante', ['Rafael Souza','Bruno Lima','Mateus Costa','Nina Rocha']) . '</div>') .
    '</div>
    <div class="tab-pane fade" id="os-itens">' .
      form_section('Serviços', '<div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Serviço</th><th>Ambiente</th><th>Qtd.</th><th>Valor unit.</th><th>Desconto</th><th>Subtotal</th><th>Ações</th></tr></thead><tbody><tr><td>Limpeza técnica split</td><td>' . h($ambientes[5]) . '</td><td>2</td><td>R$ 180,00</td><td>R$ 0,00</td><td>R$ 360,00</td><td>' . action_menu() . '</td></tr></tbody></table></div>') .
      form_section('Produtos e peças', '<div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Código</th><th>Produto</th><th>Unidade</th><th>Qtd.</th><th>Valor unit.</th><th>Desconto</th><th>Subtotal</th><th>Ações</th></tr></thead><tbody><tr><td>GAS-410</td><td>Gás refrigerante R410A</td><td>kg</td><td>1</td><td>R$ 390,00</td><td>R$ 0,00</td><td>R$ 390,00</td><td>' . action_menu() . '</td></tr></tbody></table></div>') .
    '</div>
    <div class="tab-pane fade" id="os-valores">' .
      form_section('Agendamento', '<div class="form-row-3">' . field('Data','2026-06-18','date') . field('Horário inicial','08:30','time') . field('Horário final','11:30','time') . select_field('Prioridade',['Baixa','Média','Alta','Urgente']) . select_field('Status',['Aberta','Agendada','Em execução','Aguardando peça','Finalizada']) . '</div>') .
      form_section('Resumo visual', '<div class="summary-box"><div><span>Subtotal serviços</span><strong>R$ 360,00</strong></div><div><span>Subtotal produtos</span><strong>R$ 390,00</strong></div><div><span>Desconto</span><strong>R$ 0,00</strong></div><div><span>Acréscimo</span><strong>R$ 120,00</strong></div><div class="total"><span>Total</span><strong>R$ 870,00</strong></div><div><span>Recebido</span><strong>R$ 300,00</strong></div><div><span>Saldo pendente</span><strong>R$ 570,00</strong></div><div><span>Forma de pagamento</span><strong>Pix</strong></div></div>' . field('Observações','Retorno combinado após aprovação do cliente.','textarea')) .
    '</div>
  </div>';
}

function budget_modal_body(): string {
  return form_section('Dados do orçamento', '<div class="form-row">' . field('Cliente','Clínica Bem Estar') . field('Validade','2026-06-25','date') . field('Responsável','Ana Martins') . field('Status','Aguardando aprovação') . '</div>') .
    form_section('Serviços', '<div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Serviço</th><th>Qtd.</th><th>Valor</th><th>Subtotal</th></tr></thead><tbody><tr><td>Limpeza de ar-condicionado split</td><td>2</td><td>R$ 180,00</td><td>R$ 360,00</td></tr></tbody></table></div>') .
    form_section('Produtos', '<div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Produto</th><th>Qtd.</th><th>Valor</th><th>Subtotal</th></tr></thead><tbody><tr><td>Filtro universal</td><td>2</td><td>R$ 45,00</td><td>R$ 90,00</td></tr></tbody></table></div>') .
    form_section('Observações e resumo', field('Observações','Orçamento sujeito à conferência técnica no local.','textarea') . '<div class="summary-box compact"><div><span>Serviços</span><strong>R$ 360,00</strong></div><div><span>Produtos</span><strong>R$ 90,00</strong></div><div class="total"><span>Total</span><strong>R$ 450,00</strong></div></div>');
}

function cliente_modal_body(): string {
  return '<ul class="nav nav-tabs visual-tabs"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cli-dados" type="button">Dados</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cli-equip" type="button">Equipamentos</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cli-os" type="button">Ordens de Serviço</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cli-orc" type="button">Orçamentos</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cli-pag" type="button">Pagamentos</button></li></ul><div class="tab-content pt-3"><div class="tab-pane fade show active" id="cli-dados">' .
    form_section('Dados pessoais', '<div class="form-row">' . field('Nome / Razão social','Mercado Ponto Frio') . field('CPF ou CNPJ','08.441.302/0001-91') . field('Telefone','(92) 98122-5544') . field('WhatsApp','(92) 98122-5544') . '</div>') .
    form_section('Endereço e contato', '<div class="form-row">' . field('Endereço','Rua das Palmeiras, 88') . field('Cidade','Manaus') . field('E-mail','compras@pontofrio.local') . field('Status','Ativo') . '</div>' . field('Observações','Cliente com recorrência mensal em balcões e freezers.','textarea')) .
    '</div><div class="tab-pane fade" id="cli-equip"><div class="info-list"><div class="info-item"><i class="bi bi-snow2 info-icon"></i><div><strong>Freezer vertical Metalfrio</strong><span>Loja principal · Série MF-2291</span></div></div></div></div><div class="tab-pane fade" id="cli-os"><p class="section-note">Histórico visual de OS do cliente.</p></div><div class="tab-pane fade" id="cli-orc"><p class="section-note">Histórico visual de orçamentos.</p></div><div class="tab-pane fade" id="cli-pag"><p class="section-note">Pagamentos e pendências visuais.</p></div></div>';
}

function venda_modal_body(): string {
  return '<div class="cash-layout"><section>' . form_section('Produtos', '<div class="search-wrap mb-3"><i class="bi bi-search"></i><input class="search-input" placeholder="Buscar produto"></div><div class="product-pick-grid"><button class="product-pick" type="button"><strong>GAS-410</strong><span>R410A · estoque 8</span><b>R$ 390,00</b></button><button class="product-pick" type="button"><strong>FLT-009</strong><span>Filtro secador · estoque 0</span><b>R$ 49,00</b></button></div>') . '</section><aside>' . form_section('Carrinho', '<div class="summary-box"><div><span>Compressor 1/4 HP x1</span><strong>R$ 620,00</strong></div><div><span>Desconto</span><strong>R$ 20,00</strong></div><div><span>Cliente opcional</span><strong>João Almeida</strong></div><div><span>Vendedor</span><strong>Atendimento</strong></div><div><span>Forma de pagamento</span><strong>Dinheiro</strong></div><div><span>Valor recebido</span><strong>R$ 700,00</strong></div><div><span>Troco</span><strong>R$ 100,00</strong></div><div class="total"><span>Total geral</span><strong>R$ 600,00</strong></div></div>' . field('Observações','Venda avulsa visual.','textarea')) . '</aside></div>';
}

function lembrete_modal_body(): string {
  return form_section('Lembrete', '<div class="form-row">' . field('Título','Retornar orçamento') . field('Data','2026-06-20','date') . field('Horário','09:00','time') . field('Cliente','Clínica Bem Estar') . field('OS','OS-00256') . field('Responsável','Ana Martins') . select_field('Categoria',['Serviço','Financeiro','Peça','Retorno']) . '</div>' . field('Descrição','Confirmar aprovação do orçamento enviado.','textarea'));
}

function servico_semanal_modal_body(): string {
  return form_section('Ordem de Serviço', '<div class="form-row">' . field('Número da OS','OS-00272') . field('Cliente','Universal Refrigeração') . field('Telefone','(92) 98844-1188') . field('Endereço','Av. Djalma Batista, 1240') . field('Bairro','Centro') . '</div>') .
    form_section('Serviço', '<div class="form-row">' . select_field('Tipo de serviço',['Revisão de Split','Lavagem de Split','Instalação','Reinstalação','Manutenção preventiva','Manutenção corretiva','Limpeza de evaporadora','Limpeza de condensadora','Carga de gás','Troca de compressor']) . select_field('Equipamento',['Split','Janela','Cassete','Piso-Teto','Freezer Vertical','Câmara Fria','Balcão Refrigerado']) . field('Capacidade','12.000 BTUs') . select_field('Ambiente',['Sala','Quarto','Cozinha','Loja','Salão','Escritório','Depósito','Área externa']) . '</div>' . field('Descrição','Atendimento visual planejado para o painel semanal.','textarea')) .
    form_section('Data e horário', '<div class="form-row">' . field('Data','2026-05-18','date') . field('Horário inicial','08:00','time') . field('Horário final','10:00','time') . field('Duração estimada','2h') . '</div>') .
    form_section('Dupla', '<div class="form-row">' . select_field('Instalador',['Everton','Dione','Kalebe','Leonardo']) . select_field('Ajudante',['Odinez','Cristian','Inácio','Rafael']) . '</div>') .
    form_section('Classificação', '<div class="form-row">' . select_field('Status',['Agendado','Confirmado','Em deslocamento','Em execução','Aguardando peça','Concluído','Cancelado']) . select_field('Prioridade',['Baixa','Média','Alta','Urgente']) . '</div>');
}

function mock_servicos_semanais(): array {
  return [
    [
      'dia' => 'Segunda-feira',
      'curto' => 'SEG.',
      'data' => '18/05',
      'hoje' => true,
      'duplas' => [
        [
          'instalador' => 'Everton',
          'ajudante' => 'Odinez',
          'status' => 'Em atividade',
          'servicos' => [
            ['horario' => '08:00', 'os' => 'OS-00258', 'cliente' => 'Universal Refrigeração', 'servico' => 'Revisão de Split', 'equipamento' => 'Split', 'capacidade' => '12.000 BTUs', 'ambiente' => 'Sala', 'local' => 'Centro', 'status' => 'Agendado', 'prioridade' => 'Baixa'],
            ['horario' => '10:30', 'os' => 'OS-00259', 'cliente' => 'Fernanda Paula', 'servico' => 'Lavagem de Split', 'equipamento' => 'Split', 'capacidade' => '9.000 BTUs', 'ambiente' => 'Quarto', 'local' => 'Duque de Caxias', 'status' => 'Confirmado', 'prioridade' => 'Média'],
          ],
        ],
        [
          'instalador' => 'Dione',
          'ajudante' => 'Cristian',
          'status' => 'Em atividade',
          'servicos' => [
            ['horario' => '14:00', 'os' => 'OS-00260', 'cliente' => 'Mercado Ponto Frio', 'servico' => 'Manutenção preventiva', 'equipamento' => 'Freezer Vertical', 'capacidade' => '410 L', 'ambiente' => 'Loja', 'local' => 'Adrianópolis', 'status' => 'Em execução', 'prioridade' => 'Alta'],
          ],
        ],
      ],
    ],
    [
      'dia' => 'Terça-feira',
      'curto' => 'TER.',
      'data' => '19/05',
      'hoje' => false,
      'duplas' => [
        [
          'instalador' => 'Kalebe',
          'ajudante' => 'Inácio',
          'status' => 'Em atividade',
          'servicos' => [
            ['horario' => '08:30', 'os' => 'OS-00261', 'cliente' => 'Clínica Bem Estar', 'servico' => 'Limpeza de evaporadora', 'equipamento' => 'Split', 'capacidade' => '18.000 BTUs', 'ambiente' => 'Recepção', 'local' => 'Vieiralves', 'status' => 'Concluído', 'prioridade' => 'Média'],
            ['horario' => '13:30', 'os' => 'OS-00262', 'cliente' => 'Restaurante Sabor Norte', 'servico' => 'Carga de gás', 'equipamento' => 'Balcão Refrigerado', 'capacidade' => '2 metros', 'ambiente' => 'Cozinha', 'local' => 'Parque 10', 'status' => 'Aguardando peça', 'prioridade' => 'Urgente'],
          ],
        ],
      ],
    ],
    [
      'dia' => 'Quarta-feira',
      'curto' => 'QUA.',
      'data' => '20/05',
      'hoje' => false,
      'duplas' => [
        [
          'instalador' => 'Leonardo',
          'ajudante' => 'Rafael',
          'status' => 'Em atividade',
          'servicos' => [
            ['horario' => '09:00', 'os' => 'OS-00263', 'cliente' => 'Hotel Amazonas', 'servico' => 'Instalação', 'equipamento' => 'Piso-Teto', 'capacidade' => '36.000 BTUs', 'ambiente' => 'Salão', 'local' => 'Centro', 'status' => 'Confirmado', 'prioridade' => 'Alta'],
          ],
        ],
        [
          'instalador' => 'Everton',
          'ajudante' => 'Odinez',
          'status' => 'Em atividade',
          'servicos' => [
            ['horario' => '15:00', 'os' => 'OS-00264', 'cliente' => 'Sorveteria Polar', 'servico' => 'Troca de compressor', 'equipamento' => 'Freezer Horizontal', 'capacidade' => '520 L', 'ambiente' => 'Loja', 'local' => 'Aleixo', 'status' => 'Em deslocamento', 'prioridade' => 'Urgente'],
          ],
        ],
      ],
    ],
    [
      'dia' => 'Quinta-feira',
      'curto' => 'QUI.',
      'data' => '21/05',
      'hoje' => false,
      'duplas' => [
        [
          'instalador' => 'Dione',
          'ajudante' => 'Cristian',
          'status' => 'Em atividade',
          'servicos' => [
            ['horario' => '08:00', 'os' => 'OS-00265', 'cliente' => 'Padaria Santa Luzia', 'servico' => 'Manutenção corretiva', 'equipamento' => 'Balcão Refrigerado', 'capacidade' => '1,8 metro', 'ambiente' => 'Atendimento', 'local' => 'Cidade Nova', 'status' => 'Agendado', 'prioridade' => 'Média'],
            ['horario' => '11:00', 'os' => 'OS-00266', 'cliente' => 'João Almeida', 'servico' => 'Reinstalação', 'equipamento' => 'Split', 'capacidade' => '12.000 BTUs', 'ambiente' => 'Quarto', 'local' => 'Flores', 'status' => 'Confirmado', 'prioridade' => 'Baixa'],
            ['horario' => '16:00', 'os' => 'OS-00267', 'cliente' => 'Loja Tropical', 'servico' => 'Limpeza de condensadora', 'equipamento' => 'Split', 'capacidade' => '24.000 BTUs', 'ambiente' => 'Loja', 'local' => 'Chapada', 'status' => 'Agendado', 'prioridade' => 'Baixa'],
          ],
        ],
      ],
    ],
    [
      'dia' => 'Sexta-feira',
      'curto' => 'SEX.',
      'data' => '22/05',
      'hoje' => false,
      'duplas' => [
        [
          'instalador' => 'Kalebe',
          'ajudante' => 'Inácio',
          'status' => 'Em atividade',
          'servicos' => [
            ['horario' => '09:30', 'os' => 'OS-00268', 'cliente' => 'Farmácia Vida', 'servico' => 'Manutenção preventiva', 'equipamento' => 'Geladeira Comercial', 'capacidade' => '730 L', 'ambiente' => 'Loja', 'local' => 'São José', 'status' => 'Agendado', 'prioridade' => 'Média'],
          ],
        ],
        [
          'instalador' => 'Leonardo',
          'ajudante' => 'Rafael',
          'status' => 'Disponível à tarde',
          'servicos' => [
            ['horario' => '13:00', 'os' => 'OS-00269', 'cliente' => 'Condomínio Jardim Europa', 'servico' => 'Lavagem de Split', 'equipamento' => 'Split', 'capacidade' => '18.000 BTUs', 'ambiente' => 'Salão', 'local' => 'Ponta Negra', 'status' => 'Cancelado', 'prioridade' => 'Alta'],
          ],
        ],
      ],
    ],
    [
      'dia' => 'Sábado',
      'curto' => 'SÁB.',
      'data' => '23/05',
      'hoje' => false,
      'duplas' => [],
    ],
  ];
}

function render_painel_semanal(): void {
  $dias = mock_servicos_semanais();
  echo '<section class="weekly-page">';
  echo '<div class="panel weekly-toolbar">
    <div>
      <h2>Painel Semanal de Serviços</h2>
      <p class="weekly-period"><i class="bi bi-calendar-week"></i>18 a 23 de maio de 2026</p>
    </div>
    <div class="weekly-toolbar-actions" aria-label="Controles visuais da semana">
      <button class="btn-filter btn-filter-ghost" type="button"><i class="bi bi-chevron-left"></i> Semana anterior</button>
      <button class="btn-filter btn-filter-primary" type="button"><i class="bi bi-calendar-check"></i> Hoje</button>
      <button class="btn-filter btn-filter-ghost" type="button">Próxima semana <i class="bi bi-chevron-right"></i></button>
      <button class="btn-filter btn-filter-ghost" type="button" aria-label="Imprimir painel semanal"><i class="bi bi-printer"></i> Imprimir</button>
      <button class="btn-new-os" type="button" data-bs-toggle="modal" data-bs-target="#modal-servico-semanal"><i class="bi bi-calendar-plus"></i><span>Adicionar Serviço</span></button>
    </div>
  </div>';

  echo '<div class="weekly-metrics">';
  metric_grid([
    ['Serviços agendados','18','bi-calendar2-check','#2563EB','semana atual'],
    ['Concluídos','6','bi-check-circle','#16A34A','atendimentos'],
    ['Pendentes','10','bi-hourglass-split','#D97706','em aberto'],
    ['Urgentes','2','bi-exclamation-triangle','#DC2626','prioridade'],
    ['Duplas em atividade','4','bi-people','#0F766E','em campo'],
    ['Duplas disponíveis','1','bi-person-check','#7C3AED','janela livre'],
  ]);
  echo '</div>';

  echo '<div class="weekly-filters">';
  filter_bar([
    ['Dupla',['Everton / Odinez','Dione / Cristian','Kalebe / Inácio','Leonardo / Rafael']],
    ['Instalador',['Everton','Dione','Kalebe','Leonardo']],
    ['Ajudante',['Odinez','Cristian','Inácio','Rafael']],
    ['Status',['Agendado','Confirmado','Em deslocamento','Em execução','Aguardando peça','Concluído','Cancelado']],
    ['Prioridade',['Baixa','Média','Alta','Urgente']],
    ['Tipo de serviço',['Revisão de Split','Lavagem de Split','Instalação','Reinstalação','Manutenção preventiva','Manutenção corretiva','Limpeza de evaporadora','Limpeza de condensadora','Carga de gás','Troca de compressor']],
    ['Equipamento',['Split','Freezer Vertical','Freezer Horizontal','Piso-Teto','Balcão Refrigerado','Geladeira Comercial']],
  ], 'Buscar serviço, cliente ou OS...');
  echo '</div>';

  echo '<nav class="weekly-mobile-days" aria-label="Dias da semana"><span>Seg.</span><span>Ter.</span><span>Qua.</span><span>Qui.</span><span>Sex.</span><span>Sáb.</span></nav>';
  echo '<section class="weekly-board" aria-label="Quadro semanal de serviços por dupla">';
  foreach ($dias as $dia) echo render_coluna_dia($dia);
  echo '</section></section>';
}

function render_coluna_dia(array $dia): string {
  $servicos = [];
  foreach ($dia['duplas'] as $dupla) {
    foreach ($dupla['servicos'] as $servico) $servicos[] = $servico;
  }
  $total = count($servicos);
  $concluidos = count(array_filter($servicos, fn($servico) => $servico['status'] === 'Concluído'));
  $pendentes = max(0, $total - $concluidos);
  $duplas = count($dia['duplas']);
  $classes = 'week-day-column' . (!empty($dia['hoje']) ? ' is-today' : '');

  $html = '<article class="' . h($classes) . '">';
  $html .= '<header class="week-day-header"><div><span>' . h($dia['curto'] . ', ' . $dia['data']) . '</span><strong>' . h($dia['dia']) . '</strong></div><div class="week-day-count">' . h($total . ' serviços') . '</div>' . (!empty($dia['hoje']) ? '<div class="today-badge">Hoje</div>' : '') . '</header>';
  $html .= '<div class="week-day-body">';
  if ($total === 0) {
    $html .= render_estado_vazio_semanal();
  } else {
    foreach ($dia['duplas'] as $dupla) $html .= render_grupo_dupla($dupla);
  }
  $html .= '</div>';
  $html .= '<footer class="week-day-footer"><strong>' . h($total . ' serviços') . '</strong><span>' . h($concluidos . ' concluído' . ($concluidos === 1 ? '' : 's') . ' · ' . $pendentes . ' pendente' . ($pendentes === 1 ? '' : 's')) . '</span><span>' . h($duplas . ' dupla' . ($duplas === 1 ? '' : 's')) . '</span></footer>';
  return $html . '</article>';
}

function render_grupo_dupla(array $dupla): string {
  $initials = substr($dupla['instalador'], 0, 1) . substr($dupla['ajudante'], 0, 1);
  $count = count($dupla['servicos']);
  $html = '<section class="team-group">';
  $html .= '<header class="team-group-header"><div class="team-avatar" aria-hidden="true">' . h($initials) . '</div><div class="team-info"><strong class="team-names">' . h($dupla['instalador'] . ' / ' . $dupla['ajudante']) . '</strong><span class="team-role">Instalador + Ajudante</span></div><div class="team-service-count"><span>' . h($count . ' serviço' . ($count === 1 ? '' : 's')) . '</span><small>' . h($dupla['status']) . '</small></div></header>';
  foreach ($dupla['servicos'] as $servico) $html .= render_card_servico_semanal($servico);
  return $html . '</section>';
}

function render_card_servico_semanal(array $servico): string {
  $priorityMap = ['Baixa' => 'priority-low', 'Média' => 'priority-medium', 'Alta' => 'priority-high', 'Urgente' => 'priority-urgent'];
  $priorityTextMap = ['Baixa' => 'baixa', 'Média' => 'média', 'Alta' => 'alta', 'Urgente' => 'urgente'];
  $priorityClass = $priorityMap[$servico['prioridade']] ?? 'priority-medium';
  $priorityText = $priorityTextMap[$servico['prioridade']] ?? strtolower($servico['prioridade']);

  return '<article class="week-service-card ' . h($priorityClass) . '">
    <div class="week-service-header">
      <div><span class="week-service-time">' . h($servico['horario']) . '</span><span class="week-service-os">' . h($servico['os']) . '</span></div>
      <div class="week-service-actions">
        <button class="btn-action" type="button" aria-label="Visualizar ' . h($servico['os']) . '" title="Visualizar OS"><i class="bi bi-eye"></i></button>
        <div class="dropdown">
          <button class="btn-action" type="button" data-bs-toggle="dropdown" aria-label="Ações de ' . h($servico['os']) . '"><i class="bi bi-three-dots-vertical"></i></button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><button class="dropdown-item" type="button"><i class="bi bi-pencil"></i> Editar</button></li>
            <li><button class="dropdown-item" type="button"><i class="bi bi-calendar-event"></i> Reagendar</button></li>
            <li><button class="dropdown-item" type="button"><i class="bi bi-check2-circle"></i> Marcar como concluído</button></li>
            <li><button class="dropdown-item text-danger" type="button"><i class="bi bi-x-circle"></i> Cancelar</button></li>
          </ul>
        </div>
      </div>
    </div>
    <strong class="week-service-client">' . h($servico['cliente']) . '</strong>
    <span class="week-service-title">' . h($servico['servico']) . '</span>
    <div class="week-service-details">
      <span>' . h($servico['equipamento'] . ' ' . $servico['capacidade']) . '</span>
      <span>' . h($servico['ambiente'] . ' · ' . $servico['local']) . '</span>
    </div>
    <div class="week-service-meta">' . ui_badge($servico['status']) . '<span class="priority-label">Prioridade ' . h($priorityText) . '</span></div>
  </article>';
}

function render_estado_vazio_semanal(): string {
  return '<div class="week-empty-state">
    <i class="bi bi-calendar-x" aria-hidden="true"></i>
    <strong>Nenhum serviço agendado</strong>
    <p>Nenhuma dupla possui atendimento neste dia</p>
    <button class="btn-filter btn-filter-ghost" type="button" data-bs-toggle="modal" data-bs-target="#modal-servico-semanal"><i class="bi bi-calendar-plus"></i> Adicionar serviço</button>
  </div>';
}

function produto_modal_body(): string {
  return form_section('Dados do produto', '<div class="form-row-3">' . field('Código','CMP-014') . field('Descrição','Compressor 1/4 HP') . select_field('Categoria',['Compressor','Sensor','Filtro','Gás refrigerante','Tubulação','Placa eletrônica']) . field('Fabricante','Embraco') . select_field('Unidade',['un','kg','m','cx']) . field('Código de barras','789000000014') . '</div>') .
    form_section('Preços e estoque', '<div class="form-row-3">' . field('Custo','420,00') . field('Preço','620,00') . field('Estoque','3') . field('Estoque mínimo','2') . field('Localização','Prateleira A-03') . select_field('Situação',['Em estoque','Estoque baixo','Sem estoque']) . '</div>') .
    form_section('Fornecedor', '<div class="form-row">' . field('Fornecedor','FrioPeças AM') . field('Contato','Renata') . '</div>' . field('Observações','Peça usada em freezers verticais e balcões refrigerados.','textarea'));
}

function servico_modal_body(): string {
  return form_section('Serviço', '<div class="form-row">' . field('Nome','Limpeza de ar-condicionado split') . select_field('Categoria',['Manutenção preventiva','Manutenção corretiva','Instalação','Visita técnica']) . select_field('Equipamentos compatíveis',['Ar-condicionado Split','Ar-condicionado Janela','Cassete','Piso-Teto','Freezer','Câmara Fria']) . field('Duração','1h30') . field('Valor','180,00') . select_field('Status',['Ativo','Inativo']) . '</div>' . field('Descrição','Higienização da evaporadora, filtros e conferência visual da condensadora.','textarea'));
}

function funcionario_modal_body(): string {
  return form_section('Dados pessoais', '<div class="form-row">' . field('Nome','Carlos Ferreira') . field('CPF','000.000.000-00') . field('Telefone','(92) 98444-1001') . field('E-mail','carlos@yamaguchi.local') . '</div>') .
    form_section('Função e disponibilidade', '<div class="form-row">' . select_field('Função',['Instalador','Ajudante','Administrativo','Atendente']) . select_field('Especialidade',['Ar-condicionado','Câmara fria','Refrigeração comercial','Elétrica básica']) . select_field('Status',['Ativo','Disponível','Em atendimento','Inativo']) . field('Serviços no mês','28') . '</div>' . field('Observações','Disponível para atendimentos comerciais pela manhã.','textarea'));
}

function fornecedor_modal_body(): string {
  return form_section('Dados fiscais', '<div class="form-row">' . field('Razão social','FrioPeças Amazonas Ltda.') . field('Nome fantasia','FrioPeças AM') . field('CNPJ','11.222.333/0001-44') . field('Inscrição estadual','04.123.456-7') . '</div>') .
    form_section('Contato e endereço', '<div class="form-row">' . field('Telefone','(92) 3232-5522') . field('WhatsApp','(92) 98844-5522') . field('E-mail','vendas@friopecas.local') . field('Contato','Renata') . field('Endereço','Av. das Torres, 900') . field('Cidade','Manaus') . field('Estado','AM') . select_field('Status',['Ativo','Inativo']) . '</div>') .
    form_section('Categorias', '<div class="form-row">' . field('Categorias','Compressores, sensores, gás refrigerante') . field('Condição comercial','Pagamento em 14 dias') . '</div>' . field('Observações','Fornecedor visual para peças de refrigeração.','textarea'));
}

function transportadora_modal_body(): string {
  return form_section('Transportadora', '<div class="form-row">' . field('Transportadora','NorteLog Express') . field('CNPJ','44.778.111/0001-22') . field('Contato','Paulo') . field('Telefone','(92) 98822-1919') . field('WhatsApp','(92) 98822-1919') . field('E-mail','operacao@nortelog.local') . field('Cidade','Manaus') . field('Estado','AM') . '</div>') .
    form_section('Operação', '<div class="form-row">' . field('Tipos de transporte','Peças e equipamentos') . field('Prazo médio','2 dias') . select_field('Status',['Ativo','Inativo']) . field('Região atendida','Manaus e região metropolitana') . '</div>' . field('Observações','Cadastro visual sem integração logística.','textarea'));
}

function render_operational_page(string $key): void {
  echo '<div class="page-body operational-page" data-page="' . h($key) . '">';
  match ($key) {
    'ordens' => render_ordens(),
    'orcamentos' => render_orcamentos(),
    'clientes' => render_clientes(),
    'agenda' => render_agenda(),
    'painel-semanal' => render_painel_semanal(),
    'pecas' => render_pecas(),
    'servicos' => render_servicos(),
    'funcionarios' => render_funcionarios(),
    'fornecedores' => render_fornecedores(),
    'transportadoras' => render_transportadoras(),
    'caixa' => render_caixa(),
    'faturamento' => render_faturamento(),
    'relatorios' => render_relatorios(),
    'configuracoes' => render_configuracoes(),
    default => empty_state('Página não configurada', 'Layout visual ainda não definido.'),
  };
  echo '</div>';
  render_common_modals();
}

function render_ordens(): void {
  metric_grid([
    ['OS abertas','34','bi-folder2-open','#2563EB'], ['Agendadas','18','bi-calendar-check','#0EA5E9'], ['Em execução','12','bi-arrow-repeat','#D97706'], ['Aguardando peça','7','bi-box-seam','#7C3AED'], ['Finalizadas','92','bi-check-circle','#16A34A'],
  ]);
  filter_bar([
    ['Período', [], 'date'], ['Cliente',['Restaurante Sabor Norte','Mercado Ponto Frio']], ['Instalador',['Carlos','Ana','Lucas','Pedro']], ['Ajudante',['Rafael','Bruno','Mateus','Nina']], ['Status',['Aberta','Agendada','Em execução','Aguardando peça','Finalizada']], ['Prioridade',['Baixa','Média','Alta','Urgente']], ['Pagamento',['Pendente','Pago','Aguardando pagamento']],
  ], 'Buscar número, cliente ou equipamento...');
  echo '<section class="panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-clipboard2-check"></i>Ordens de Serviço</div><button class="btn-new-os" type="button" data-bs-toggle="modal" data-bs-target="#modal-os"><i class="bi bi-plus-lg"></i><span>Nova OS</span></button></div>';
  $rows = array_map(fn($r) => [$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],ui_badge($r[7]),ui_badge($r[8]),ui_badge($r[9]),money($r[10]),action_menu()], mock_os());
  ui_table(['Número','Cliente','Equipamento','Serviço','Instalador','Ajudante','Data','Prioridade','Status','Pagamento','Valor','Ações'], $rows, ['scroll' => true]);
  echo '</section>';
  empty_state('Sem OS nos filtros aplicados', 'Estado vazio visual para quando filtros não retornarem registros.');
}

function render_orcamentos(): void {
  metric_grid([['Rascunhos','5','bi-pencil-square','#64748B'],['Enviados','11','bi-send','#2563EB'],['Aguardando aprovação','17','bi-hourglass-split','#D97706'],['Aprovados','8','bi-check2-circle','#16A34A'],['Vencidos','3','bi-exclamation-circle','#DC2626']]);
  filter_bar([['Período', [], 'date'], ['Cliente',['Clínica Bem Estar','Mercado Ponto Frio']], ['Responsável',['Carlos','Ana','Lucas']], ['Status',['Rascunho','Enviado','Aguardando aprovação','Aprovado','Recusado','Vencido','Convertido em OS']]], 'Buscar orçamento...');
  echo '<section class="panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-file-earmark-text"></i>Orçamentos</div><button class="btn-new-os" data-bs-toggle="modal" data-bs-target="#modal-orcamento" type="button"><i class="bi bi-file-earmark-plus"></i><span>Novo Orçamento</span></button></div>';
  ui_table(['Número','Cliente','Data','Validade','Responsável','Valor','Status','Ações'], [
    ['ORC-00124','Clínica Bem Estar','18/06/2026','25/06/2026','Ana Martins',money(450),ui_badge('Aguardando aprovação'),action_menu()],
    ['ORC-00123','Restaurante Sabor Norte','17/06/2026','24/06/2026','Carlos Ferreira',money(1320),ui_badge('Enviado'),action_menu()],
    ['ORC-00122','Mercado Ponto Frio','16/06/2026','23/06/2026','Lucas Ferreira',money(2380),ui_badge('Aprovado'),action_menu()],
    ['ORC-00121','Hotel Amazonas','10/06/2026','17/06/2026','Pedro Alves',money(1960),ui_badge('Vencido'),action_menu()],
  ]);
  pagination_visual();
  echo '</section>';
}

function render_clientes(): void {
  metric_grid([['Total de clientes','126','bi-people','#2563EB'],['Clientes ativos','113','bi-person-check','#16A34A'],['Novos no mês','14','bi-person-plus','#0EA5E9'],['Com OS aberta','28','bi-clipboard2-check','#D97706']]);
  filter_bar([['Tipo de pessoa',['Pessoa Física','Pessoa Jurídica']], ['Cidade',['Manaus','Iranduba']], ['Status',['Ativo','Inativo']]], 'Buscar nome, CPF, CNPJ ou telefone...');
  echo '<section class="panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-people"></i>Clientes</div><button class="btn-new-os" data-bs-toggle="modal" data-bs-target="#modal-cliente" type="button"><i class="bi bi-person-plus"></i><span>Novo Cliente</span></button></div>';
  ui_table(['Nome','CPF ou CNPJ','Telefone','WhatsApp','Cidade','Qtd. OS','Status','Ações'], [
    ['Restaurante Sabor Norte','12.845.778/0001-20','(92) 98844-1188','(92) 98844-1188','Manaus','8',ui_badge('Ativo'),action_menu()],
    ['João Almeida','182.334.210-09','(92) 99134-2201','(92) 99134-2201','Manaus','2',ui_badge('Ativo'),action_menu()],
    ['Clínica Bem Estar','31.672.404/0001-75','(92) 3232-4411','(92) 98420-1199','Manaus','5',ui_badge('Ativo'),action_menu()],
  ]);
  pagination_visual();
  echo '</section>';
}

function render_agenda(): void {
  echo '<div class="panel agenda-panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-calendar3"></i>Agenda</div><div class="btn-group"><button class="btn-filter btn-filter-ghost">Semana anterior</button><button class="btn-filter btn-filter-primary">Hoje</button><button class="btn-filter btn-filter-ghost">Próxima semana</button></div></div><div class="p-3"><ul class="nav nav-pills visual-tabs"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#agenda-semana" type="button">Quadro semanal</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#agenda-calendario" type="button">Calendário</button></li></ul><div class="tab-content pt-3"><div class="tab-pane fade show active" id="agenda-semana"><div class="week-board agenda-week-board">';
  foreach (['Segunda','Terça','Quarta','Quinta','Sexta','Sábado'] as $i => $day) {
    echo '<div class="day-column"><h3>' . h($day) . '</h3><article class="schedule-card"><strong>' . sprintf('%02d:30', 8 + $i) . '</strong><span>Cliente ' . ($i + 1) . '</span><small>OS-0025' . $i . ' · Limpeza split · Split LG · Rua Exemplo, ' . ($i + 10) . '</small><div><span>Instalador: Carlos</span><span>Ajudante: Rafael</span></div>' . ui_badge($i % 2 ? 'Agendada' : 'Em execução') . '</article></div>';
  }
  echo '</div></div><div class="tab-pane fade" id="agenda-calendario"><div class="calendar-grid">';
  for ($day = 1; $day <= 30; $day++) echo '<div class="calendar-day"><strong>' . $day . '</strong>' . ($day % 4 === 0 ? '<span class="calendar-dot service">Serviço</span>' : '') . ($day % 7 === 0 ? '<span class="calendar-dot note">Lembrete</span>' : '') . '</div>';
  echo '</div><button class="btn-new-os mt-3" type="button" data-bs-toggle="modal" data-bs-target="#modal-lembrete"><i class="bi bi-alarm"></i><span>Novo lembrete</span></button></div></div></div></div>';
}

function render_pecas(): void {
  metric_grid([['Total de produtos','284','bi-box-seam','#2563EB'],['Estoque baixo','18','bi-exclamation-triangle','#D97706'],['Sem estoque','7','bi-x-octagon','#DC2626'],['Valor estimado',money(82450),'bi-cash-stack','#16A34A']]);
  filter_bar([['Categoria',['Compressor','Sensor','Filtro','Gás']], ['Fornecedor',['FrioPeças AM','Clima Parts']], ['Situação',['Em estoque','Estoque baixo','Sem estoque']]], 'Buscar código ou descrição...');
  echo '<section class="panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-box-seam"></i>Produtos / Peças e Estoque</div><div class="panel-actions"><button class="btn-filter btn-filter-primary">Novo produto</button><button class="btn-filter btn-filter-ghost">Entrada</button><button class="btn-filter btn-filter-ghost">Saída</button><button class="btn-filter btn-filter-ghost">Ajuste</button><button class="btn-filter btn-filter-ghost">Importar peças</button></div></div>';
  ui_table(['Código','Descrição','Categoria','Fabricante','Fornecedor','Unidade','Estoque','Estoque mín.','Custo','Preço','Situação','Ações'], [
    ['CMP-014','Compressor 1/4 HP','Compressor','Embraco','FrioPeças AM','un','3','2',money(420),money(620),ui_badge('Em estoque'),action_menu()],
    ['SEN-022','Sensor de temperatura','Sensor','Full Gauge','Refrigera Norte','un','2','5',money(38),money(75),ui_badge('Estoque baixo'),action_menu()],
    ['FLT-009','Filtro secador','Filtro','Danfoss','Clima Parts','un','0','6',money(24),money(49),ui_badge('Sem estoque'),action_menu()],
  ]);
  pagination_visual();
  echo '</section>';
}

function render_servicos(): void {
  metric_grid([['Serviços cadastrados','38','bi-tools','#2563EB'],['Serviços ativos','35','bi-check-circle','#16A34A'],['Mais utilizados','8','bi-star','#D97706'],['Ticket médio visual',money(420),'bi-graph-up','#7C3AED']]);
  filter_bar([['Categoria',['Preventiva','Corretiva','Instalação']], ['Equipamento',['Split','Freezer','Câmara fria']], ['Status',['Ativo','Inativo']]], 'Buscar serviço...');
  echo '<section class="panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-tools"></i>Serviços</div></div>';
  ui_table(['Código','Serviço','Categoria','Equipamento','Duração estimada','Valor','Status','Ações'], [
    ['SRV-001','Limpeza de ar-condicionado split','Preventiva','Split','1h30',money(180),ui_badge('Ativo'),action_menu()],
    ['SRV-002','Troca de compressor','Corretiva','Freezer','3h',money(650),ui_badge('Ativo'),action_menu()],
    ['SRV-003','Manutenção em câmara fria','Preventiva','Câmara Fria','4h',money(520),ui_badge('Ativo'),action_menu()],
  ]);
  pagination_visual();
  echo '</section>';
}

function render_funcionarios(): void {
  metric_grid([['Funcionários ativos','12','bi-person-check','#16A34A'],['Instaladores','6','bi-tools','#2563EB'],['Ajudantes','4','bi-person-arms-up','#0EA5E9'],['Disponíveis hoje','5','bi-calendar-check','#D97706']]);
  filter_bar([['Função',['Instalador','Ajudante','Administrativo','Atendente']], ['Especialidade',['Ar-condicionado','Câmara fria','Comercial']], ['Status',['Ativo','Disponível','Em atendimento','Inativo']]], 'Buscar funcionário...');
  echo '<section class="panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-person-badge"></i>Funcionários</div></div>';
  ui_table(['Nome','Função','Telefone','Especialidade','Serviços no mês','Status','Ações'], [
    ['Carlos Ferreira','Instalador','(92) 98444-1001','Ar-condicionado','28',ui_badge('Em atendimento'),action_menu()],
    ['Rafael Souza','Ajudante','(92) 98111-3322','Instalação','21',ui_badge('Disponível'),action_menu()],
    ['Marina Kato','Administrativo','(92) 3232-8080','Financeiro','-',ui_badge('Ativo'),action_menu()],
  ]);
  pagination_visual();
  echo '</section>';
}

function render_fornecedores(): void {
  metric_grid([['Fornecedores ativos','24','bi-building-check','#16A34A'],['Fornecedores de peças','16','bi-box-seam','#2563EB'],['Recentes','4','bi-clock-history','#D97706'],['Total cadastrado','31','bi-building','#64748B']]);
  echo '<section class="panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-building-check"></i>Fornecedores</div></div>';
  ui_table(['Razão social','Nome fantasia','CNPJ','Contato','Telefone','Cidade','Categoria','Status','Ações'], [
    ['FrioPeças Amazonas Ltda.','FrioPeças AM','11.222.333/0001-44','Renata','(92) 3232-5522','Manaus','Peças',ui_badge('Ativo'),action_menu()],
    ['Clima Parts Distribuidora','Clima Parts','22.111.900/0001-70','Marcos','(92) 99120-4411','Manaus','Componentes',ui_badge('Ativo'),action_menu()],
  ]);
  pagination_visual();
  echo '</section>';
}

function render_transportadoras(): void {
  metric_grid([['Transportadoras ativas','9','bi-truck','#16A34A'],['Entregas em andamento','14','bi-box-arrow-right','#2563EB'],['Prazo médio','2,3 dias','bi-clock','#D97706'],['Total cadastrado','12','bi-list-check','#64748B']]);
  echo '<section class="panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-truck"></i>Transportadoras</div></div>';
  ui_table(['Transportadora','CNPJ','Contato','Telefone','Cidade','Tipos de transporte','Prazo médio','Status','Ações'], [
    ['NorteLog Express','44.778.111/0001-22','Paulo','(92) 98822-1919','Manaus','Peças e equipamentos','2 dias',ui_badge('Ativo'),action_menu()],
    ['Rápido Frio Cargo','09.543.210/0001-80','Sandra','(92) 98177-4040','Manaus','Carga refrigerada','3 dias',ui_badge('Ativo'),action_menu()],
  ]);
  pagination_visual();
  echo '</section>';
}

function render_caixa(): void {
  echo '<section class="panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-cash-coin"></i>Caixa</div></div><div class="p-3"><ul class="nav nav-pills visual-tabs"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#caixa-venda" type="button">Venda avulsa</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#caixa-mov" type="button">Movimentações</button></li></ul><div class="tab-content pt-3"><div class="tab-pane fade show active" id="caixa-venda">' . venda_modal_body() . '</div><div class="tab-pane fade" id="caixa-mov">';
  ui_table(['Data','Hora','Tipo','Descrição','Responsável','Entrada','Saída','Saldo','Ações'], [
    ['18/06/2026','08:00','Abertura','Abertura de caixa','Marina',money(500),'—',money(500),action_menu()],
    ['18/06/2026','10:20','Venda','Compressor 1/4 HP','Atendimento',money(620),'—',money(1120),action_menu()],
    ['18/06/2026','16:40','Sangria','Retirada para cofre','Marina','—',money(400),money(720),action_menu()],
  ]);
  echo '</div></div></div></section>';
}

function render_faturamento(): void {
  echo '<section class="panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-receipt-cutoff"></i>Notas e Faturamento</div></div><div class="p-3"><ul class="nav nav-pills visual-tabs"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#nf" type="button">Notas Fiscais</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#recibos" type="button">Recibos</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#boletos" type="button">Boletos</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pagamentos" type="button">Pagamentos</button></li></ul><div class="tab-content pt-3"><div class="tab-pane fade show active" id="nf">';
  ui_table(['Número','Cliente','OS','Data','Valor','Status','Ações'], [['NF-00084','Clínica Bem Estar','OS-00256','18/06/2026',money(450),ui_badge('Emitida'),action_menu()],['Pendente','Restaurante Sabor Norte','OS-00258','18/06/2026',money(1320),ui_badge('Pendente'),action_menu()]]);
  echo '</div><div class="tab-pane fade" id="recibos">';
  ui_table(['Número','Cliente','Referência','Data','Valor','Ações'], [['REC-00031','João Almeida','OS-00254','18/06/2026',money(120),action_menu()]]);
  echo '<div class="receipt-preview mt-3"><div class="brand-icon"><i class="bi bi-snow2"></i></div><h3>Recibo</h3><strong>' . money(120) . '</strong><p>Recebido de João Almeida, CPF 182.334.210-09, referente a diagnóstico técnico.</p><div>Manaus, 18/06/2026</div><div class="signature-line">Assinatura e carimbo</div></div>';
  echo '</div><div class="tab-pane fade" id="boletos"><div class="alert alert-warning">Integração bancária será realizada em outra etapa.</div>';
  ui_table(['Cliente','OS','Valor','Vencimento','Status','Ações'], [['Mercado Ponto Frio','OS-00257',money(2380),'25/06/2026',ui_badge('Pendente'),action_menu()]]);
  echo '</div><div class="tab-pane fade" id="pagamentos">';
  ui_table(['Data','Cliente','Referência','Forma','Valor','Status','Ações'], [['18/06/2026','Padaria Santa Luzia','OS-00255','Pix',money(520),ui_badge('Pago'),action_menu()]]);
  echo '</div></div></div></section>';
}

function render_relatorios(): void {
  echo '<section class="panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-bar-chart-line"></i>Relatórios</div></div><div class="p-3"><ul class="nav nav-pills visual-tabs"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#rel-geral" type="button">Visão Geral</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rel-prod" type="button">Produtividade</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rel-func" type="button">Serviços por Funcionário</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rel-fin" type="button">Financeiro</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rel-est" type="button">Estoque</button></li></ul><div class="tab-content pt-3"><div class="tab-pane fade show active" id="rel-geral">';
  metric_grid([['Meta mensal','120 OS','bi-bullseye','#2563EB'],['Realizados','92','bi-check-circle','#16A34A'],['Pendentes','28','bi-hourglass','#D97706'],['Valor produzido',money(48720),'bi-cash-stack','#7C3AED']]);
  echo '<div class="report-bars"><div><span>Preventiva</span><b style="width:72%"></b></div><div><span>Corretiva</span><b style="width:54%"></b></div><div><span>Instalação</span><b style="width:44%"></b></div></div></div><div class="tab-pane fade" id="rel-prod">';
  metric_grid([['Meta mensal','120','bi-bullseye','#2563EB'],['Serviços realizados','92','bi-check2-circle','#16A34A'],['Serviços pendentes','28','bi-hourglass','#D97706'],['Percentual da meta','76%','bi-percent','#0EA5E9'],['Valor produzido',money(48720),'bi-cash-stack','#7C3AED']]);
  echo '</div><div class="tab-pane fade" id="rel-func"><h3 class="section-heading">Instaladores</h3>';
  ui_table(['Funcionário','Função','Serviços','Valor total','Percentual','Extra estimado','Situação'], [['Carlos Ferreira','Instalador','28',money(14200),'31%','R$ 620,00',ui_badge('Ativo')],['Ana Martins','Instalador','21',money(11750),'24%','R$ 480,00',ui_badge('Ativo')]]);
  echo '<h3 class="section-heading mt-4">Ajudantes</h3>';
  ui_table(['Funcionário','Função','Serviços','Valor total','Percentual','Extra estimado','Situação'], [['Rafael Souza','Ajudante','24',money(9800),'26%','R$ 320,00',ui_badge('Ativo')]]);
  echo '</div><div class="tab-pane fade" id="rel-fin"><p class="section-note">Gráficos financeiros demonstrativos.</p></div><div class="tab-pane fade" id="rel-est"><p class="section-note">Indicadores visuais de estoque.</p></div></div></div></section>';
}

function render_configuracoes(): void {
  $sections = ['Dados da empresa','Logotipo','Assinatura','Carimbo','Dados fiscais','Formas de pagamento','Tipos de equipamentos','Tipos de ambientes','Funções dos funcionários','Status de OS','Categorias','Preferências de impressão'];
  echo '<div class="settings-grid">';
  foreach ($sections as $section) {
    echo '<section class="panel settings-panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-sliders"></i>' . h($section) . '</div></div><div class="p-3">' . field('Campo visual', $section === 'Dados da empresa' ? 'K. Yamaguchi Refrigeração' : '') . field('Observações','Configuração apenas visual nesta etapa.','textarea') . '<button class="btn-modal-save" type="button">Salvar visualmente</button></div></section>';
  }
  echo '</div>';
}
