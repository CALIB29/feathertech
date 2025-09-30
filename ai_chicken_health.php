<?php
include 'includes/db.php';
include 'includes/auth.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chicken Health Scanner | FeatherTech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ed 100%);
            min-height: 100vh;
            padding-bottom: 80px;
        }

        .scanner-container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            padding: 25px;
        }

        .camera-preview {
            width: 100%;
            height: 400px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            position: relative;
            border: 2px dashed #ddd;
        }

        #videoElement {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .capture-btn {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #0d44d1;
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            box-shadow: 0 4px 15px rgba(13, 68, 209, 0.3);
            transition: all 0.3s;
        }

        .capture-btn:active {
            transform: scale(0.95);
        }

        .result-container {
            display: none;
            margin-top: 30px;
            padding: 20px;
            border-radius: 12px;
            background: #f8f9fa;
        }

        .disease-card {
            border-left: 4px solid #0d44d1;
            margin-bottom: 15px;
        }

        .medication-card {
            background: #e8f4ff;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 30px;
        }

        .back-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
        }
    </style>
</head>

<body>
    <div class="app-header">
        <div class="app-title">
            <img src="/assets/images/FeatherTech.jpg" alt="Logo" class="app-logo">
            AI Health Scanner
        </div>
        <a href="dashboard.php" class="btn btn-sm btn-light">
            <i class="fas fa-arrow-left"></i>
        </a>
    </div>

    <div class="scanner-container">
        <h3 class="text-center mb-4"><i class="fas fa-camera"></i> Chicken Health Scanner</h3>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Capture clear images of chickens to detect signs of illness.
        </div>

        <div class="camera-preview">
            <video id="videoElement" autoplay playsinline></video>
            <canvas id="canvas" style="display:none;"></canvas>
        </div>

        <div class="text-center">
            <button id="captureBtn" class="capture-btn">
                <i class="fas fa-camera fa-2x"></i>
            </button>
        </div>

        <div class="loading-spinner" id="loadingSpinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Analyzing chicken health...</p>
        </div>

        <div class="result-container" id="resultContainer">
            <h4 class="mb-3"><i class="fas fa-diagnoses"></i> Analysis Results</h4>

            <div id="healthResults">
                <!-- Results will be populated here -->
            </div>

            <button id="newScanBtn" class="btn btn-primary mt-3">
                <i class="fas fa-redo"></i> Scan Another Chicken
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const video = document.getElementById('videoElement');
            const canvas = document.getElementById('canvas');
            const captureBtn = document.getElementById('captureBtn');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const resultContainer = document.getElementById('resultContainer');
            const healthResults = document.getElementById('healthResults');
            const newScanBtn = document.getElementById('newScanBtn');

            let stream = null;
            let capturedImage = null;

            // Start camera
            async function startCamera() {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'environment',
                            width: {
                                ideal: 1280
                            },
                            height: {
                                ideal: 720
                            }
                        }
                    });
                    video.srcObject = stream;
                } catch (err) {
                    console.error("Camera error: ", err);
                    alert("Could not access the camera. Please check permissions.");
                }
            }

            // Capture image
            captureBtn.addEventListener('click', function() {
                // Set canvas dimensions to match video
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;

                // Draw video frame to canvas
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                // Get image data
                capturedImage = canvas.toDataURL('image/jpeg');

                // Show loading spinner
                loadingSpinner.style.display = 'block';

                // Stop camera stream
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }

                // Simulate AI analysis (replace with actual API call)
                setTimeout(() => {
                    analyzeChickenHealth(capturedImage);
                }, 2000);
            });

            // New scan button
            newScanBtn.addEventListener('click', function() {
                resultContainer.style.display = 'none';
                loadingSpinner.style.display = 'none';
                healthResults.innerHTML = '';
                startCamera();
            });

            // Analyze chicken health (simulated - replace with real API)
            // Real implementation with API call
            // Real implementation with API call
            function analyzeChickenHealth(imageData) {
                fetch('api/analyze_chicken.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            image: imageData.split(',')[1] // Send base64 without prefix
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Process and display real AI results
                        displayResults(data);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        resultContainer.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> 
                Error analyzing image: ${error.message}
            </div>
            <button class="btn btn-primary" onclick="window.location.reload()">
                Try Again
            </button>`;
                    });
            }

            // Display analysis results
            function displayResults(results) {
                resultContainer.style.display = 'block';

                if (results.status !== "success") {
                    healthResults.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Error analyzing image. Please try again.
                        </div>
                    `;
                    return;
                }

                const analysis = results.analysis;

                let html = `
                    <div class="alert ${analysis.overallHealth.includes('Good') ? 'alert-success' : 
                                      analysis.overallHealth.includes('Moderate') ? 'alert-warning' : 'alert-danger'}">
                        <h5><i class="fas fa-heartbeat"></i> Overall Health: ${analysis.overallHealth}</h5>
                        <p>Confidence: ${analysis.confidence}%</p>
                    </div>
                `;

                if (analysis.detectedIssues.length > 0) {
                    html += `<h5 class="mt-4"><i class="fas fa-bug"></i> Detected Health Issues</h5>`;

                    analysis.detectedIssues.forEach(issue => {
                        html += `
                            <div class="card disease-card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">${issue.condition} 
                                        <span class="badge ${issue.severity === 'Severe' ? 'bg-danger' : 
                                                          issue.severity === 'Moderate' ? 'bg-warning' : 'bg-info'}">
                                            ${issue.severity}
                                        </span>
                                        <span class="badge bg-secondary float-end">
                                            ${issue.confidence}% confidence
                                        </span>
                                    </h5>
                                    <p class="card-text">${issue.description}</p>
                                    
                                    <h6><i class="fas fa-exclamation-circle"></i> Symptoms:</h6>
                                    <ul>
                                        ${issue.symptoms.map(s => `<li>${s}</li>`).join('')}
                                    </ul>
                                    
                                    <div class="medication-card">
                                        <h6><i class="fas fa-pills"></i> Recommended Treatment:</h6>
                                        <p><strong>${issue.medication.name}</strong></p>
                                        <p><strong>Dosage:</strong> ${issue.medication.dosage}</p>
                                        <p><strong>Instructions:</strong> ${issue.medication.instructions}</p>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    html += `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> 
                            No significant health issues detected. Chicken appears healthy.
                        </div>
                    `;
                }

                if (analysis.recommendations.length > 0) {
                    html += `
                        <div class="card mt-3">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-clipboard-list"></i> General Recommendations
                            </div>
                            <div class="card-body">
                                <ul class="mb-0">
                                    ${analysis.recommendations.map(r => `<li>${r}</li>`).join('')}
                                </ul>
                            </div>
                        </div>
                    `;
                }

                healthResults.innerHTML = html;
            }

            // Initialize camera when page loads
            startCamera();
        });
    </script>
</body>

</html>