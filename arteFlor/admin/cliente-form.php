<?php
$adminTitle = 'Cadastro de cliente';
$activeAdmin = 'cliente-form';
require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Relacionamento</span>
    <h1>Cadastrar cliente</h1>
    <p>Ficha visual com dados, preferências, datas importantes e histórico de compras.</p>
  </div>
  <div class="admin-hero-actions"><a class="btn btn-soft" href="<?= site_url('admin/clientes.php') ?>">Voltar para clientes</a></div>
</section>

<form class="admin-form-shell">
  <section class="admin-form-card">
    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Dados do cliente</strong><p>Informações fictícias para cadastro visual.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field"><span>Nome</span><input placeholder="Maria Clara" required></label>
        <label class="admin-field"><span>Contato fictício</span><input placeholder="(97) 90000-0000"></label>
        <label class="admin-field"><span>Bairro</span><input placeholder="Centro"></label>
        <label class="admin-field"><span>Perfil</span><select><option>Novo</option><option>Recorrente</option><option>Especial</option></select></label>
        <label class="admin-field full"><span>Endereço</span><input placeholder="Rua, número e complemento"></label>
        <label class="admin-field full"><span>Observações</span><textarea placeholder="Preferências de entrega, atendimento e restrições."></textarea></label>
      </div>
    </div>

    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Preferências e datas</strong><p>Campos úteis para relacionamento futuro.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field"><span>Flores preferidas</span><input placeholder="Rosas, orquídeas, tons pastel"></label>
        <label class="admin-field"><span>Canal preferido</span><select><option>Telefone</option><option>WhatsApp atendimento</option><option>E-mail</option></select></label>
        <label class="admin-field"><span>Aniversário</span><input type="date"></label>
        <label class="admin-field"><span>Data importante</span><input type="date"></label>
      </div>
    </div>
  </section>

  <aside class="admin-form-card admin-side-card">
    <div class="client-profile-preview">
      <span>MC</span>
      <h3>Maria Clara</h3>
      <p>Cliente recorrente · Centro</p>
    </div>
    <div class="admin-metric-list">
      <div class="admin-metric-row"><span>Compras</span><strong>6</strong></div>
      <div class="admin-metric-row"><span>Ticket médio</span><strong>R$ 142,50</strong></div>
      <div class="admin-metric-row"><span>Última compra</span><strong>17/05</strong></div>
    </div>
    <div class="admin-alert-card"><strong>Histórico visual</strong>Buquê Jardim Pastel, Arranjo Premium e Mini Buquê Delicado.</div>
    <button class="btn btn-primary" type="button">Salvar demonstração</button>
  </aside>
</form>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
