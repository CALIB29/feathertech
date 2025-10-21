<?php
// Database connection and authentication checks
include 'includes/auth.php';

$task_id = $_GET['task_id'] ?? null;

// If a task_id is provided, fetch task details to show the user what they are completing.
$task_details = null;
if ($task_id) {
    $stmt = $pdo->prepare("SELECT vt.*, a.type as animal_type, a.breed, a.mark FROM vaccination_tasks vt JOIN animals a ON vt.animal_id = a.id WHERE vt.id = ?");
    $stmt->execute([$task_id]);
    $task_details = $stmt->fetch(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR Code</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Using specific version of ZXing library -->
    <script src="https://unpkg.com/@zxing/library@0.18.6/umd/index.min.js"></script>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #e0eafc, #cfdef3);
            min-height: 100vh;
            padding: 20px;
        }
        .scanner-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        #reader {
            width: 100%;
            height: auto;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
            border: 2px solid #0d44d1;
        }
        #result {
            text-align: center;
            font-size: 1.2rem;
            margin: 20px 0;
        }
        .btn-scan {
            background-color: #0d44d1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 0 auto;
        }
        .btn-scan:hover {
            background-color: #0b3ab7;
        }
        .form-container {
            display: none;
            margin-top: 20px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert i {
            margin-right: 10px;
        }
        .scanner-overlay {
            position: relative;
        }
        .scanner-overlay::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 2px solid #0d44d1;
            border-radius: 8px;
            pointer-events: none;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 0.7; }
            50% { opacity: 0.3; }
            100% { opacity: 0.7; }
        }
        .floating-back-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .error-actions {
            margin-top: 10px;
        }
        .spinner {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="scanner-container">
        <h2 class="text-center mb-4"><i class="fas fa-qrcode"></i> Scan to Complete Task</h2>
        
        <div class="scanner-overlay">
            <video id="reader" playsinline></video>
        </div>
        <div id="result">Point your camera at a QR code to scan</div>
        
        <button id="startScan" class="btn btn-scan">
            <i class="fas fa-play"></i> Start Scanner
        </button>
        
        <?php if ($task_details): ?>
        <div class="alert alert-info">
            <strong>Task:</strong> Complete vaccination (<?= htmlspecialchars($task_details['vaccine_type']) ?>) for Animal #<?= htmlspecialchars($task_details['animal_id']) ?> (<?= htmlspecialchars($task_details['animal_type']) ?>).
            <p><strong>Breed:</strong> <?= htmlspecialchars($task_details['breed']) ?></p>
        </div>
        <?php endif; ?>

        <div class="form-container" id="completionForm">
            <h3 class="text-center mb-3">Complete Vaccination Task</h3>
            <form action="complete_task.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="task_id" value="<?= htmlspecialchars($task_id ?? '') ?>">
                <input type="hidden" id="scanned_animal_id" name="animal_id">

                <div class="mb-3">
                    <label for="vaccine_type" class="form-label">Vaccine to Administer:</label>
                    <input type="text" id="vaccine_type" name="vaccine_type" class="form-control" value="<?= htmlspecialchars($task_details['vaccine_type'] ?? '') ?>" readonly>
                </div>

                <div class="mb-3">
                    <label for="proof" class="form-label">Upload Proof Photo:</label>
                    <input type="file" name="proof" id="proof" class="form-control" accept="image/*" required>
                    <div class="form-text">Upload a photo showing the vaccination was administered.</div>
                    <div class="progress d-none mt-2" id="uploadProgress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Completion Notes (optional):</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Any additional information..."></textarea>
                </div>

                <button type="submit" class="btn btn-success w-100">
                    <i class="fas fa-check-circle"></i> Mark as Completed
                </button>
            </form>
        </div>
    </div>
    <a href="dashboard.php" class="btn btn-primary floating-back-btn">
        <i class="fas fa-arrow-left"></i> Back
    </a>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // First check if ZXing loaded properly
            if (!window.ZXing || !ZXing.BrowserQRCodeReader) {
                document.getElementById('result').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> QR Scanner library failed to load.
                        Please refresh the page.
                    </div>
                `;
                return;
            }

            const codeReader = new ZXing.BrowserQRCodeReader();
            const startScanBtn = document.getElementById('startScan');
            const resultElement = document.getElementById('result');
            const completionForm = document.getElementById('completionForm');
            const scannedAnimalIdInput = document.getElementById('scanned_animal_id');
            const taskDetails = <?= json_encode($task_details) ?>;
            let scanning = false;
            
            // Improved QR code scanning function
            async function startScanner() {
                try {
                    // Check camera API support
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        throw new Error('Camera API not supported in this browser');
                    }

                    // First try the standard scanning method
                    try {
                        await codeReader.decodeFromVideoDevice(null, 'reader', handleScanResult);
                    } catch (err) {
                        console.error('Primary decode error:', err);
                        // Fallback to alternative method
                        await codeReader.decodeFromConstraints(
                            { audio: false, video: { facingMode: 'environment' } },
                            'reader',
                            handleScanResult
                        );
                    }
                    
                    startScanBtn.innerHTML = '<i class="fas fa-stop"></i> Stop Scanner';
                    resultElement.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-search"></i> Scanning... Point camera at QR code
                        </div>
                    `;
                    scanning = true;
                } catch (error) {
                    console.error('Scanner error:', error);
                    resultElement.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> ${error.message}
                        </div>
                    `;
                }
            }
            
            function handleScanResult(result, err) {
                if (result) {
                    handleQRResult(result.text);
                    stopScanner();
                }
                if (err && !(err instanceof ZXing.NotFoundException)) {
                    console.error(err);
                    resultElement.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> Error: ${err.message}
                        </div>
                    `;
                }
            }
            
            function stopScanner() {
                if (scanning) {
                    codeReader.reset();
                    startScanBtn.innerHTML = '<i class="fas fa-play"></i> Start Scanner';
                    scanning = false;
                }
            }
            
// Add validation to handleQRResult
function handleQRResult(qrData) {
    let animalId = null;
    
    try {
        const qrContent = JSON.parse(qrData);
        animalId = qrContent.animal_id || qrContent.id;
    } catch (e) {
        try {
            const url = new URL(qrData);
            animalId = url.searchParams.get('id');
        } catch (e) {
            const idMatch = qrData.match(/\b(\d+)\b/);
            animalId = idMatch ? idMatch[1] : null;
        }
    }

    if (animalId && Number.isInteger(Number(animalId))) {
        fetchAnimalDetails(animalId);
    } else {
        showError('Invalid QR code. Please scan a valid animal QR code.');
    }
}

function showCompletionForm(scannedAnimalId) {
    if (!taskDetails) {
        showError('Task details are missing. Cannot proceed.');
        return;
    }

    // Verify if the scanned animal matches the task's animal
    if (parseInt(scannedAnimalId) !== parseInt(taskDetails.animal_id)) {
        showError(`Verification failed. This QR code is for Animal #${scannedAnimalId}, but the task is for Animal #${taskDetails.animal_id}.`);
        return;
    }

    // If verification is successful
    resultElement.innerHTML = `
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Animal #${scannedAnimalId} verified successfully.
        </div>
    `;
    scannedAnimalIdInput.value = scannedAnimalId;
    completionForm.style.display = 'block';
}

function showAnimalDetails(animalData) {
    const resultElement = document.getElementById('result');
    
    // Ensure data exists and has expected structure
    if (!animalData || !animalData.id || !animalData.type) {
        showError('Invalid animal data received');
        return;
    }

    resultElement.innerHTML = `
        <div class="alert alert-success">
            <div class="d-flex align-items-start">
                <i class="fas fa-check-circle mt-1 me-2"></i>
                <div>
                    <h5 class="alert-heading mb-2">Animal Found</h5>
                    <p class="mb-1"><strong>ID:</strong> ${animalData.id}</p>
                    <p class="mb-1"><strong>Type:</strong> ${animalData.type}</p>
                    ${animalData.vaccination.type ? `
                        <p class="mb-0"><strong>Last Vaccination:</strong> 
                        ${animalData.vaccination.type} on ${animalData.vaccination.date}
                        </p>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
}

async function fetchAnimalDetails(animalId) {
    // If we are in a task workflow, we don't need to fetch details, just verify.
    if (taskDetails) {
        showCompletionForm(animalId);
    } else {
        // Original behavior if not completing a task
        try {
            showLoading('Fetching animal details...');
            const response = await fetch(`get_animal_details.php?id=${animalId}`);
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to fetch animal details');
            }
            const responseData = await response.json();
            if (!responseData.animal) {
                throw new Error('Invalid animal data structure');
            }
            showAnimalDetails(responseData.animal);
        } catch (error) {
            showError(error.message);
            console.error('Fetch error:', error);
        }
    }
}

function showLoading(message) {
    document.getElementById('result').innerHTML = `
        <div class="alert alert-info">
            <i class="fas fa-circle-notch fa-spin me-2"></i> ${message}
        </div>
    `;
}

function showError(message) {
    document.getElementById('result').innerHTML = `
        <div class="alert alert-danger">
            <div class="d-flex align-items-start">
                <i class="fas fa-exclamation-triangle mt-1 me-2"></i>
                <div>
                    <h5 class="alert-heading mb-2">Error</h5>
                    <p class="mb-2">${message}</p>
                    <div class="error-actions mt-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="startScanner()">
                            <i class="fas fa-redo me-1"></i> Try Again
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}
            
            
            // Start scanner automatically
            startScanner();
        });

        // Enhanced form submission with progress tracking
document.getElementById('completionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const formData = new FormData(form);
    const uploadProgress = document.getElementById('uploadProgress');
    const progressBar = uploadProgress.querySelector('.progress-bar');
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show progress bar
    uploadProgress.classList.remove('d-none');
    progressBar.style.width = '0%';
    progressBar.setAttribute('aria-valuenow', 0);
    
    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    
    // Create XMLHttpRequest for upload with progress tracking
    const xhr = new XMLHttpRequest();
    
    // Track upload progress
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percentComplete = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percentComplete + '%';
            progressBar.setAttribute('aria-valuenow', percentComplete);
        }
    });
    
    // Handle response
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                // Success - redirect to dashboard
                window.location.href = 'dashboard.php';
            } else {
                // Error
                try {
                    const response = JSON.parse(xhr.responseText);
                    alert('Error: ' + (response.message || 'Upload failed'));
                } catch (e) {
                    alert('Error: Upload failed. Please try again.');
                }
                
                // Reset form
                uploadProgress.classList.add('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
    };
    
    // Send request
    xhr.open('POST', form.action, true);
    xhr.send(formData);
});
    </script>
</body>
</html>