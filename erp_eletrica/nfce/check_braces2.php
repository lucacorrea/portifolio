<?php
$contents = file_get_contents('c:/Users/Luiz Frota/Documents/portifolio/erp_eletrica/nfce/emitir.php');
$tokens = token_get_all($contents);
$stack = [];
$lines = [];
foreach ($tokens as $token) {
    if (is_array($token)) {
        $lines[] = $token[2];
        $text = trim($token[1]);
        if ($token[0] == T_CURLY_OPEN || $token[0] == T_DOLLAR_OPEN_CURLY_BRACES) {
            $stack[] = ['line' => end($lines), 'text' => $text];
        }
    } else {
        $line = $lines ? end($lines) : 1;
        if ($token === '{') {
            // grab some context before the brace
            $start = max(0, strlen($contents) - strlen(strstr($contents, '{'))); 
            $stack[] = ['line' => $line, 'text' => '{'];
        } elseif ($token === '}') {
            if (!empty($stack)) {
                array_pop($stack);
            }
        }
    }
}
echo "Remaining open braces:\n";
foreach ($stack as $b) {
    echo "Line " . $b['line'] . "\n";
}
