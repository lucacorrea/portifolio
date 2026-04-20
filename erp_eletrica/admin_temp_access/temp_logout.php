<?php

declare(strict_types=1);

session_start();
unset($_SESSION['temp_auth']);
header('Location: acesso_temporario.php');
exit;
