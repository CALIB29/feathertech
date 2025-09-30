<?php
// download_qr.php
include 'includes/db.php';

if (isset($_GET['file'])) {
    $filePath = $_SERVER['DOCUMENT_ROOT'] . urldecode($_GET['file']);
    
    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="qr_code_'.basename($filePath).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

header("Location: dashboard.php?error=qr_not_found");
exit();