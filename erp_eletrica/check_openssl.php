<?php
echo "OpenSSL version: " . OPENSSL_VERSION_TEXT . "\n";
echo "OpenSSL header version: " . OPENSSL_VERSION_NUMBER . "\n";
if (defined('OPENSSL_ALGO_SHA256')) echo "SHA256 supported\n";
