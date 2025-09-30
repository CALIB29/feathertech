<?php
// Check QR code directory permissions
$qrDir = 'assets/images/';
$isWritable = is_writable($qrDir);

// Check if GD library is installed (for QR code generation)
$gdInstalled = extension_loaded('gd');

// Check results
header('Content-Type: application/json');
echo json_encode([
    'qr_directory_writable' => $isWritable,
    'gd_installed' => $gdInstalled,
    'permissions_ok' => $isWritable && $gdInstalled,
    'message' => $isWritable && $gdInstalled 
        ? 'Server is properly configured for QR generation'
        : 'Configuration issues found'
]);
?>