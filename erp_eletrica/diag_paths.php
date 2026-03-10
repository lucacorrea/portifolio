<?php
echo "Current Directory: " . getcwd() . "<br>";
echo "__DIR__: " . __DIR__ . "<br>";
$storage = __DIR__ . '/storage';
if (is_dir($storage)) {
    echo "Storage exists at: " . realpath($storage) . "<br>";
    $cert = $storage . '/certificados';
    if (is_dir($cert)) {
        echo "Certificados exists at: " . realpath($cert) . "<br>";
        print_r(scandir($cert));
    }
} else {
    echo "Storage NOT found at: $storage<br>";
}
?>
