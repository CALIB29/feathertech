<?php
// Alternative Solution: Handle vaccine_type length in application code
// This provides a safety net even if database migration fails

function sanitizeVaccineType($vaccineType) {
    // Truncate to 100 characters to be safe (database column limit)
    $maxLength = 100;

    if (strlen($vaccineType) > $maxLength) {
        // Log the truncation for debugging
        error_log("Vaccine type truncated: '" . substr($vaccineType, 0, 50) . "...' (length: " . strlen($vaccineType) . ")");

        // Return truncated version
        return substr($vaccineType, 0, $maxLength);
    }

    return $vaccineType;
}

// Example usage (commented out):
/*
if (isset($_POST['vaccine_type'])) {
    $vaccineType = sanitizeVaccineType($_POST['vaccine_type']);
    // Use $vaccineType in database operations
}
*/
?>