<?php

declare(strict_types=1);

cm_require('comida_mesa.entregar');
require __DIR__ . '/consulta-cpf.php';
$pageDefinition['title'] = 'Registrar entrega';
$pageDefinition['description'] = 'Consulte o CPF da família e registre a entrega da competência atual quando o benefício estiver apto.';
$pageDefinition['actions'] = [
    ['label' => 'Beneficiários', 'icon' => 'people', 'href' => 'comida-mesa/beneficiarios.php'],
    ['label' => 'Consultar CPF', 'icon' => 'person-bounding-box', 'href' => 'comida-mesa/consulta-cpf.php'],
    ['label' => 'Nova consulta', 'icon' => 'arrow-counterclockwise', 'primary' => true, 'href' => 'comida-mesa/registrar-entrega.php'],
];
