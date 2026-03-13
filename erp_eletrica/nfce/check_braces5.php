<?php
$contents = file_get_contents('c:/Users/Luiz Frota/Documents/portifolio/erp_eletrica/nfce/emitir.php');
$tokens = token_get_all($contents);
$stack = [];
$lines = [];
$last_keyword = null;
foreach ($tokens as $token) {
    if (is_array($token)) {
        $lines[] = $token[2];
        $text = trim($token[1]);
        if (in_array(strtolower($text), ['if', 'try', 'catch', 'foreach', 'for', 'while', 'function', 'class', 'elseif', 'else'])) {
            $last_keyword = ['line' => end($lines), 'text' => $text];
        }
    } else {
        $line = $lines ? end($lines) : 1;
        if ($token === '{') {
            $stack[] = ['line' => $line, 'keyword' => ($last_keyword['text'] ?? 'none')];
            $last_keyword = null;
        } elseif ($token === '}') {
            if (!empty($stack)) {
                $popped = array_pop($stack);
                // What's left on stack?
            }
        }
    }
}
echo "FINAL Stack:\n";
foreach ($stack as $s) echo "- Line " . $s['line'] . " (" . $s['keyword'] . ")\n";
