<?php
// Configuration
$appInfo = [
    'version' => '1.0.0',
    'build' => '20240828',
    'apk_file' => 'FeatherTech-v1.0.0.apk',
    'apk_path' => 'downloads/' . 'FeatherTech-v1.0.0.apk',
    'file_size' => '',  // Will be set dynamically
    'checksum_md5' => '',  // Will be set dynamically
    'checksum_sha256' => '',  // Will be set dynamically
    'last_updated' => '2024-08-28',
    'min_android_version' => '7.0',
    'download_url' => 'downloads/FeatherTech-v1.0.0.apk'
];

// Calculate file size and checksums if file exists
if (file_exists($appInfo['apk_path'])) {
    $appInfo['file_size'] = round(filesize($appInfo['apk_path']) / (1024 * 1024), 2) . ' MB';
    $appInfo['checksum_md5'] = hash_file('md5', $appInfo['apk_path']);
    $appInfo['checksum_sha256'] = hash_file('sha256', $appInfo['apk_path']);
}

// Function to force download
function forceDownload($file_path) {
    if (file_exists($file_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($file_path);
        exit;
    }
    return false;
}

// Handle direct download request
if (isset($_GET['download']) && $_GET['download'] === 'apk') {
    if (forceDownload($appInfo['apk_path'])) {
        exit;
    }
}
?>
<?php
// Check if the request is for API data
if (isset($_GET['api']) && $_GET['api'] === 'info') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'app' => [
            'name' => 'FeatherTech',
            'version' => $appInfo['version'],
            'build' => $appInfo['build'],
            'size' => $appInfo['file_size'],
            'checksum_md5' => $appInfo['checksum_md5'],
            'checksum_sha256' => $appInfo['checksum_sha256'],
            'min_android_version' => $appInfo['min_android_version'],
            'last_updated' => $appInfo['last_updated']
        ]
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download FeatherTech App (APK)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #fffbe7 0%, #ffe082 100%);
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            color: #4e342e;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .download-container {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(255,168,0,0.13), 0 2px 8px rgba(0,0,0,0.08);
            padding: 36px 28px 32px 28px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            position: relative;
            animation: popIn 0.7s cubic-bezier(.4,2,.6,1);
        }
        .download-container h1 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #ff9800;
        }
        .download-container p {
            font-size: 1.08rem;
            color: #7a5a00;
            margin-bottom: 22px;
        }
        .download-btn {
            background: linear-gradient(90deg, #ffb300, #ff9800 80%);
            color: #fff;
            font-weight: 600;
            border: none;
            border-radius: 30px;
            padding: 14px 36px;
            font-size: 1.15rem;
            box-shadow: 0 2px 8px rgba(255,168,0,0.13);
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: background 0.2s, transform 0.2s;
            outline: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .download-btn:active {
            transform: scale(0.97);
        }
        .download-btn .fa-download {
            font-size: 1.2em;
        }
        .apk-info {
            margin-top: 18px;
            font-size: 0.98rem;
            color: #a67c00;
        }
        .apk-qr {
            margin: 22px 0 10px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .apk-qr img {
            width: 140px;
            height: 140px;
            border-radius: 12px;
            border: 2px solid #ffe082;
            background: #fffbe7;
            display: block;
            margin: 0 auto;
            box-shadow: 0 2px 8px #ffe08255;
        }
        @keyframes popIn {
            0% { transform: scale(0.7); opacity: 0; }
            60% { transform: scale(1.08); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        .note {
            margin-top: 18px;
            color: #b26a00;
            font-size: 0.97rem;
        }
    </style>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="download-container">
        <img src="assets/images/FeatherTech.jpg" alt="FeatherTech Logo" style="width: 80px; height: 80px; border-radius: 18px; box-shadow: 0 2px 8px #ffe08255; margin-bottom: 12px; background: #fffbe7;">
        <h1 style="margin-bottom: 0.5rem;"><i class="fas fa-mobile-alt"></i> Download App</h1>
        <div style="font-size:1.13rem; color:#ff9800; font-weight:600; margin-bottom: 0.7rem;">FeatherTech Poultry & Gamefowl Farm</div>
        <p style="margin-bottom: 0.7rem;">Your all-in-one solution for modern poultry and gamefowl management.<br>
        <span style="color:#a67c00; font-size:1.01rem;">Track, monitor, and optimize your farm's health, breeding, vaccination, and performance—anytime, anywhere.</span></p>
        <div style="margin-bottom: 1.2rem;">
            <span style="display:inline-block; background:#fffbe7; color:#b26a00; border-radius:12px; padding:4px 14px; font-size:0.98rem; font-weight:500; margin-bottom:4px;">For Breeders, Hobbyists, and Commercial Farms</span>
        </div>
        <div class="download-options">
            <button class="download-btn" id="directDownloadBtn" type="button">
                <i class="fas fa-download"></i> Download APK v<?php echo $appInfo['version']; ?>
            </button>
            <div class="file-info">
                <div>File Size: <?php echo $appInfo['file_size']; ?></div>
                <div class="checksum" title="MD5: <?php echo $appInfo['checksum_md5']; ?>">
                    <i class="fas fa-fingerprint"></i> Verify Checksum
                </div>
            </div>
        </div>
        <div class="apk-info">
            <div class="apk-qr">
                <img src="assets/images/AppQR.jpeg" alt="QR code for APK download" style="background:#fff;display:block;width:200px;height:200px;">
            </div>
            <div style="margin-top:6px;">Scan QR code to download on your phone</div>
        </div>
        <div class="note">
            <i class="fas fa-info-circle"></i> <b>How to install:</b><br>
            1. Tap <b>Download APK</b> or scan the QR code.<br>
            2. If you see a warning, tap <b>Download anyway</b>.<br>
            3. Open the APK file and follow the prompts.<br>
            <span style="color:#d84315;">You may need to allow installs from unknown sources in your device settings.</span>
        </div>
        <div style="margin-top: 2.2rem; color:#7a5a00; font-size:0.99rem;">
            <b>Why FeatherTech?</b><br>
            <ul style="text-align:left; margin: 0.7rem auto 0; max-width: 320px; color:#7a5a00; font-size:0.97rem;">
                <li>✔️ Vaccination tracking for every bird</li>
                <li>✔️ Breeding, lineage, and egg production records</li>
                <li>✔️ Mortality analytics</li>
                <li>✔️ QR code animal identification</li>
                <li>✔️ Works for poultry & gamefowl</li>
                <li>✔️ Designed for mobile, tablet, and desktop</li>
            </ul>
        </div>
        <div style="margin-top: 1.7rem; color:#b26a00; font-size:0.97rem;">
            <b>Empowering Filipino poultry and gamefowl farms since 2025.</b>
        </div>
    </div>
    <div id="checksumModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>File Verification</h3>
            <p>Use these checksums to verify the integrity of your download:</p>
            <div class="checksum-info">
                <div><strong>MD5:</strong> <code id="md5Checksum"><?php echo $appInfo['checksum_md5']; ?></code></div>
                <div><strong>SHA-256:</strong> <code id="sha256Checksum"><?php echo $appInfo['checksum_sha256']; ?></code></div>
            </div>
            <p><small>Compare these values with the checksum of your downloaded file to ensure it hasn't been tampered with.</small></p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Direct download button
        var btn = document.getElementById('directDownloadBtn');
        if (btn) {
            btn.addEventListener('click', function() {
                window.location.href = '?download=apk';
            });
        }

        // Checksum modal functionality
        var modal = document.getElementById('checksumModal');
        var checksumBtn = document.querySelector('.checksum');
        var span = document.getElementsByClassName('close')[0];

        checksumBtn.onclick = function() {
            modal.style.display = 'block';
        }

        span.onclick = function() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Check for updates
        fetch('?api=info')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('App version:', data.app.version);
                    console.log('Last updated:', data.app.last_updated);
                }
            })
            .catch(error => console.error('Error checking for updates:', error));
    });
    </script>
    <style>
    .download-options {
        margin: 20px 0;
        text-align: center;
    }
    .file-info {
        margin-top: 12px;
        font-size: 0.9rem;
        color: #7a5a00;
    }
    .checksum {
        margin-top: 5px;
        cursor: pointer;
        color: #ff9800;
        text-decoration: underline;
        font-size: 0.85rem;
    }
    .checksum:hover {
        color: #f57c00;
    }
    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.6);
    }
    .modal-content {
        background-color: #fff9c4;
        margin: 10% auto;
        padding: 25px;
        border: 1px solid #ffe082;
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        position: relative;
    }
    .close {
        color: #7a5a00;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        position: absolute;
        right: 15px;
        top: 5px;
    }
    .close:hover {
        color: #5d4037;
    }
    .checksum-info {
        background: #fff8e1;
        padding: 15px;
        border-radius: 8px;
        margin: 15px 0;
        font-family: monospace;
        word-break: break-all;
    }
    .checksum-info div {
        margin: 8px 0;
    }
    code {
        background: #f5f5f5;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 0.9em;
    }
    </style>
</body>
</html>
