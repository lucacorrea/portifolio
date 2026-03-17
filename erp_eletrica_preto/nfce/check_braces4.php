<?php
$contents = file_get_contents('c:/Users/Luiz Frota/Documents/portifolio/erp_eletrica/nfce/emitir.php');
$tokens = token_get_all($contents);
$stack = [];
$lines = [];
foreach ($tokens as $token) {
    if (is_array($token)) {
        $lines[] = $token[2];
        $text = trim($token[1]);
        if (in_array(strtolower($text), ['if', 'try', 'catch', 'foreach', 'for', 'while', 'function', 'class'])) {
            $last_keyword = ['line' => end($lines), 'text' => $text];
        }
    } else {
        $line = $lines ? end($lines) : 1;
        if ($token === '{') {
            $stack[] = ['line' => $line, 'keyword' => $last_keyword['text'] ?? 'unknown'];
            $last_keyword = null;
        } elseif ($token === '}') {
            if ($line == 519) {
                echo "Stack at line 519 catch:\n";
                foreach ($stack as $s) echo "- Line " . $s['line'] . " (" . $s['keyword'] . ")\n";
            }
            if (!empty($stack)) array_pop($stack);
        }
    }
}
