<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

// Initialize response with detailed structure
$response = [
    'success' => false,
    'message' => '',
    'errors' => [],
    'data' => null
];

try {
    // Validate session first
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        throw new RuntimeException("Authentication required", 401);
    }

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException("Invalid request method", 405);
    }

    // Validate required fields with specific error messages
    $requiredFields = [
        'animal_id' => 'Animal ID',
        'vaccination_type' => 'Vaccination Type',
        'vaccination_date' => 'Vaccination Date'
    ];
    
    $missingFields = [];
    foreach ($requiredFields as $field => $name) {
        if (empty($_POST[$field])) {
            $missingFields[] = $name;
        }
    }
    
    if (!empty($missingFields)) {
        throw new InvalidArgumentException(
            "Missing required fields: " . implode(', ', $missingFields),
            400
        );
    }

    // Sanitize and validate inputs
    $animalId = filter_var($_POST['animal_id'], FILTER_VALIDATE_INT);
    if ($animalId === false || $animalId < 1) {
        throw new InvalidArgumentException("Invalid Animal ID", 400);
    }

    $vaccineType = trim(htmlspecialchars($_POST['vaccination_type']));
    if (empty($vaccineType)) {
        throw new InvalidArgumentException("Vaccination type cannot be empty", 400);
    }

    $vaccDate = $_POST['vaccination_date'];
    if (!DateTime::createFromFormat('Y-m-d', $vaccDate)) {
        throw new InvalidArgumentException("Invalid date format. Use YYYY-MM-DD", 400);
    }

    // Prepare other fields
    $vaccineName = $vaccineType;
    $notes = !empty($_POST['notes']) ? trim(htmlspecialchars($_POST['notes'])) : 'Routine vaccination';
    $administeredBy = $_SESSION['username'] ?? 'System';
    $nextDueDate = date('Y-m-d', strtotime($vaccDate . ' +6 months'));

    // Sanitize vaccine type to prevent database errors
    function sanitizeVaccineType($vaccineType) {
        $maxLength = 250; // Leave some buffer for database constraints
        if (strlen($vaccineType) > $maxLength) {
            error_log("Vaccine type truncated in update_vaccination: '" . substr($vaccineType, 0, 50) . "...'");
            return substr($vaccineType, 0, $maxLength);
        }
        return $vaccineType;
    }

    $vaccineType = sanitizeVaccineType($vaccineType);

    // Begin transaction
    $pdo->beginTransaction();

    try {

        // 2. Add to health records
        $stmt = $pdo->prepare("INSERT INTO health_records 
            (animal_id, record_date, description, treatment)
            VALUES (?, ?, ?, ?)");
        
        if (!$stmt->execute([
            $animalId,
            $vaccDate,
            "Vaccination: $vaccineType",
            $notes
        ])) {
            throw new RuntimeException("Failed to create health record", 500);
        }

        // Add to vaccination history (MAIN FIX)
        $stmt = $pdo->prepare("INSERT INTO vaccination_history 
            (animal_id, vaccine_name, vaccine_type, vaccination_date, 
             next_due_date, administered_by, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $historyResult = $stmt->execute([
            $animalId,
            $vaccineName,
            $vaccineType,
            $vaccDate,
            $nextDueDate,
            $administeredBy,
            $notes
        ]);
        
        if (!$historyResult) {
            throw new RuntimeException("Failed to save vaccination history", 500);
        }

        // Get the inserted history record ID for verification
        $historyId = $pdo->lastInsertId();
        
        // Commit only if all operations succeeded
        $pdo->commit();

        // Verify the record was actually created
        $verifyStmt = $pdo->prepare("SELECT 1 FROM vaccination_history WHERE id = ?");
        $verifyStmt->execute([$historyId]);
        if (!$verifyStmt->fetch()) {
            throw new RuntimeException("Vaccination history record verification failed", 500);
        }

        // Successful response
        $response = [
            'success' => true,
            'message' => 'Vaccination recorded successfully',
            'data' => [
                'history_id' => $historyId,
                'next_due_date' => $nextDueDate,
                'animal_id' => $animalId
            ]
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database Transaction Error: " . $e->getMessage());
        throw new RuntimeException("Database operation failed", 500, $e);
    }

} catch (InvalidArgumentException $e) {
    http_response_code($e->getCode());
    $response['message'] = $e->getMessage();
    $response['errors'] = ['validation' => $e->getMessage()];
    
} catch (RuntimeException $e) {
    http_response_code($e->getCode());
    $response['message'] = $e->getMessage();
    $response['errors'] = ['system' => $e->getMessage()];
    
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = "An unexpected error occurred";
    $response['errors'] = ['unexpected' => $e->getMessage()];
}

// Log the final response for debugging
error_log("Vaccination Update Response: " . json_encode($response));

echo json_encode($response);