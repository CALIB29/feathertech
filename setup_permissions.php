<?php
// Setup script for QR code directory permissions
$qrDir = 'assets/images/';

// Try to create directory if it doesn't exist
if (!file_exists($qrDir)) {
    if (!mkdir($qrDir, 0755, true)) {
        die("Failed to create directory: $qrDir");
    }
    echo "Created directory: $qrDir<br>";
}

// Set permissions
if (chmod($qrDir, 0755)) {
    echo "Set permissions to 755 for: $qrDir<br>";
} else {
    echo "Failed to set permissions for: $qrDir<br>";
}

// Verify
echo "Directory is " . (is_writable($qrDir) ? 'writable' : 'NOT writable') . "<br>";
echo "Setup complete. You can delete this file after verification.";
?>
# In assets/images/.htaccess
php_flag engine off
<Files ~ "\.php$">
  Order allow,deny
  Deny from all
</Files>