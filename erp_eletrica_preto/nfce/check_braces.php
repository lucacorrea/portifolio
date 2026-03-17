<?php
$contents = file_get_contents('c:/Users/Luiz Frota/Documents/portifolio/erp_eletrica/nfce/emitir.php');
$tokens = token_get_all($contents);
$stack = [];
$lines = [];
foreach ($tokens as $token) {
    if (is_string($token)) {
        if ($token === '{') {
            $stack[] = ['type' => '{', 'line' => $lines ? end($lines) : 1];
        } elseif ($token === '}') {
            if (empty($stack)) {
                echo "Unmatched } at line " . ($lines ? end($lines) : 1) . "\n";
            } else {
                array_pop($stack);
            }
        }
    } else {
        $lines[] = $token[2];
    }
}
echo "Unclosed braces:\n";
foreach ($stack as $b) {
    echo "Line: " . $b['line'] . "\n";
}
