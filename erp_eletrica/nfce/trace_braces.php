<?php
$filename = 'c:/Users/Luiz Frota/Documents/portifolio/erp_eletrica/nfce/emitir.php';
$content = file_get_contents($filename);
$tokens = token_get_all($content);
$stack = [];
$last_keyword = null;
foreach ($tokens as $token) {
    if (is_array($token)) {
        $type = $token[0];
        $text = trim($token[1]);
        $line = $token[2];
        if (in_array(strtolower($text), ['if', 'try', 'catch', 'foreach', 'for', 'while', 'function', 'class', 'elseif', 'else'])) {
            $last_keyword = $text;
        }
    } else {
        if ($token === '{') {
            $stack[] = ['line' => $line, 'keyword' => ($last_keyword ?? 'none')];
            $last_keyword = null;
        } elseif ($token === '}') {
            if (empty($stack)) {
                echo "EXTRA } at line $line\n";
            } else {
                $p = array_pop($stack);
                if ($line > 400) {
                     echo "Popped line " . $p['line'] . " (" . $p['keyword'] . ") at line $line\n";
                }
            }
        }
    }
}

if (!empty($stack)) {
    echo "UNCLOSED BLOCKS:\n";
    foreach ($stack as $s) {
        echo "Line " . $s['line'] . " (" . $s['keyword'] . ")\n";
    }
}
