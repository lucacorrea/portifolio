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
            $stack[] = ['line' => end($lines), 'type' => $text];
        }
        if (in_array(strtolower($text), ['if', 'try', 'catch', 'foreach', 'for', 'while', 'function', 'class'])) {
            $last_keyword = ['line' => end($lines), 'text' => $text];
        }
    } else {
        $line = $lines ? end($lines) : 1;
        if ($token === '{') {
            $stack[] = ['line' => $line, 'type' => '{', 'keyword' => $last_keyword['text'] ?? 'unknown'];
            $last_keyword = null;
        } elseif ($token === '}') {
            if (!empty($stack)) {
                array_pop($stack);
            }
        }
    }
}
echo "Unclosed blocks:\n";
foreach ($stack as $s) {
    echo "Line " . $s['line'] . " (" . $s['keyword'] . ")\n";
}
