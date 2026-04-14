<?php
$log = ini_get('error_log');
if ($log && file_exists($log)) {
    echo "Last 50 lines of error log ($log):\n";
    $lines = array_slice(file($log), -50);
    echo implode("", $lines);
} else {
    echo "Error log not found or empty (ini value: $log)";
}
