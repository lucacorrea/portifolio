<?php
function money_br($value) { return 'R$ ' . number_format((float)$value, 2, ',', '.'); }

function ky_data() {
  return [
    'clientes' => [
      ['id'=>1,'nome'=>'Mercado São José','doc'=>'12.345.678/0001-90','telefone'=>'5592991112233','email'=>'financeiro@mercadosj.com','tipo'=>'Pessoa Jurídica','cidade'=>'Manaus/AM','os_ativa'=>2,'status'=>'Ativo'],
      ['id'=>2,'nome'=>'Clínica Vida Norte','doc'=>'23.456.789/0001-10','telefone'=>'5592992223344','email'=>'adm@vidanorte.com','tipo'=>'Pessoa Jurídica','cidade'=>'Manaus/AM','os_ativa'=>1,'status'=>'Ativo'],
      ['id'=>3,'nome'=>'João Almeida','doc'=>'123.456.789-10','telefone'=>'5592993334455','email'=>'joao@email.com','tipo'=>'Pessoa Física','cidade'=>'Manaus/AM','os_ativa'=>0,'status'=>'Ativo'],
      ['id'=>4,'nome'=>'Padaria Modelo','doc'=>'34.567.890/0001-11','telefone'=>'5592994445566','email'=>'contato@padariamodelo.com','tipo'=>'Pessoa Jurídica','cidade'=>'Manaus/AM','os_ativa'=>1,'status'=>'Ativo'],
    ],
    'os' => [
      ['id'=>123,'numero'=>'OS-000123','cliente'=>'Mercado São José','servico'=>'Manutenção Split','equipamento'=>'Split 24.000 BTUs','status'=>'Em andamento','tecnico'=>'Carlos','valor'=>280,'data'=>'22/05/2026'],
      ['id'=>124,'numero'=>'OS-000124','cliente'=>'João Almeida','servico'=>'Higienização','equipamento'=>'Split 12.000 BTUs','status'=>'Agendada','tecnico'=>'Paulo','valor'=>150,'data'=>'23/05/2026'],
      ['id'=>125,'numero'=>'OS-000125','cliente'=>'Clínica Vida Norte','servico'=>'Troca de peça','equipamento'=>'Central de ar','status'=>'Aguardando peça','tecnico'=>'Rafael','valor'=>690,'data'=>'24/05/2026'],
      ['id'=>126,'numero'=>'OS-000126','cliente'=>'Padaria Modelo','servico'=>'Câmara fria','equipamento'=>'Câmara fria 4 portas','status'=>'Finalizada','tecnico'=>'Marcos','valor'=>1250,'data'=>'21/05/2026'],
      ['id'=>127,'numero'=>'OS-000127','cliente'=>'Mercado São José','servico'=>'Visita técnica','equipamento'=>'Freezer horizontal','status'=>'Aberta','tecnico'=>'A definir','valor'=>120,'data'=>'22/05/2026'],
    ],
    'orcamentos' => [
      ['id'=>301,'numero'=>'ORC-2026-0301','cliente'=>'Mercado São José','telefone'=>'5592991112233','validade'=>'05/06/2026','status'=>'Aguardando aprovação','total'=>1480,'responsavel'=>'Administrativo'],
      ['id'=>302,'numero'=>'ORC-2026-0302','cliente'=>'Clínica Vida Norte','telefone'=>'5592992223344','validade'=>'30/05/2026','status'=>'Enviado','total'=>2360,'responsavel'=>'Leonardo'],
      ['id'=>303,'numero'=>'ORC-2026-0303','cliente'=>'João Almeida','telefone'=>'5592993334455','validade'=>'28/05/2026','status'=>'Aprovado','total'=>420,'responsavel'=>'Administrativo'],
      ['id'=>304,'numero'=>'ORC-2026-0304','cliente'=>'Padaria Modelo','telefone'=>'5592994445566','validade'=>'26/05/2026','status'=>'Recusado','total'=>1890,'responsavel'=>'Leonardo'],
    ],
    'pecas' => [
      ['id'=>1,'nome'=>'Compressor 1/4 HP','codigo'=>'CP-014','categoria'=>'Compressor','estoque'=>4,'minimo'=>2,'custo'=>380,'venda'=>520,'status'=>'Normal'],
      ['id'=>2,'nome'=>'Capacitor 35µF','codigo'=>'EL-035','categoria'=>'Elétrica','estoque'=>2,'minimo'=>5,'custo'=>18,'venda'=>35,'status'=>'Estoque baixo'],
      ['id'=>3,'nome'=>'Filtro secador 1/4','codigo'=>'FT-014','categoria'=>'Filtro','estoque'=>0,'minimo'=>3,'custo'=>22,'venda'=>45,'status'=>'Sem estoque'],
      ['id'=>4,'nome'=>'Gás refrigerante R410A','codigo'=>'GAS-410','categoria'=>'Gás','estoque'=>8,'minimo'=>2,'custo'=>260,'venda'=>340,'status'=>'Normal'],
    ],
    'servicos' => [
      ['id'=>1,'nome'=>'Higienização de ar-condicionado','categoria'=>'Manutenção','valor'=>150,'tempo'=>'1h30','status'=>'Ativo'],
      ['id'=>2,'nome'=>'Instalação de Split','categoria'=>'Instalação','valor'=>450,'tempo'=>'3h','status'=>'Ativo'],
      ['id'=>3,'nome'=>'Carga de gás','categoria'=>'Manutenção','valor'=>280,'tempo'=>'1h','status'=>'Ativo'],
      ['id'=>4,'nome'=>'Visita técnica','categoria'=>'Visita técnica','valor'=>120,'tempo'=>'40min','status'=>'Ativo'],
    ],
    'notas' => [
      ['id'=>1,'numero'=>'NFS-00091','cliente'=>'Mercado São José','os'=>'OS-000123','tipo'=>'NFS-e','status'=>'Pendente','valor'=>280,'data'=>'22/05/2026'],
      ['id'=>2,'numero'=>'NFS-00090','cliente'=>'Padaria Modelo','os'=>'OS-000126','tipo'=>'NFS-e','status'=>'Emitida','valor'=>1250,'data'=>'21/05/2026'],
      ['id'=>3,'numero'=>'NF-00018','cliente'=>'Clínica Vida Norte','os'=>'OS-000125','tipo'=>'NF-e','status'=>'Não emitida','valor'=>690,'data'=>'24/05/2026'],
      ['id'=>4,'numero'=>'NFS-00089','cliente'=>'João Almeida','os'=>'OS-000124','tipo'=>'NFS-e','status'=>'Rejeitada','valor'=>150,'data'=>'23/05/2026'],
    ],
  ];
}

function json_response($payload) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
