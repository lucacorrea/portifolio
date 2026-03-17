<?php
session_start();
session_unset();
session_destroy();
header("Location: login.php?msg=Logout realizado com sucesso");
exit;
?>
