<?php
$contents = file_get_contents('c:/Users/Luiz Frota/Documents/portifolio/erp_eletrica/nfce/emitir.php');
// Remove line 17 try
$contents = preg_replace('/try\s*\{/', '//try {', $contents, 1);
// Remove lines 519-522
$contents = preg_replace('/\} catch \(Throwable \$e\) \{.*?\}/s', '', $contents);
file_put_contents('c:/Users/Luiz Frota/Documents/portifolio/erp_eletrica/nfce/emitir2.php', $contents);
