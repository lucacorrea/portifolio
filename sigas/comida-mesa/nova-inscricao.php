<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/bootstrap.php';
cm_require('comida_mesa.cadastrar');
header('Location: beneficiarios.php?action=new');
exit;
