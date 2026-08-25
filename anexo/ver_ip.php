<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

echo "REMOTE_ADDR: ";
echo $_SERVER['REMOTE_ADDR'] ?? 'não encontrado';

echo PHP_EOL . PHP_EOL;

echo "CF_CONNECTING_IP: ";
echo $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'não encontrado';

echo PHP_EOL . PHP_EOL;

echo "X_FORWARDED_FOR: ";
echo $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'não encontrado';