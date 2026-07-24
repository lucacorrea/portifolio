<?php
require_once __DIR__ . '/ui.php';
modal_shell(
  'modal-os-compat',
  'Nova Ordem de Serviço',
  os_modal_body(),
  '<button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-secondary" type="button">Salvar rascunho</button><button class="btn-modal-save" type="button">Salvar OS</button>'
);
