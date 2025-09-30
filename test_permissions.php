<?php
$dir = __DIR__ . '/assets/images/';
$testFile = $dir . 'test.txt';

// Check directory
if (!is_dir($dir)) {
    die("Directory doesn't exist");
}

// Check writable
if (!is_writable($dir)) {
    die("Directory not writable");
}

// Test file creation
if (file_put_contents($testFile, 'test') === false) {
    die("Failed to create file");
}

unlink($testFile);
echo "All permissions OK! QR directory is writable.";
?>