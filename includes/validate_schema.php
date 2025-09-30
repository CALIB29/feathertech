<?php
function validateDatabaseSchema($pdo) {
    $requiredColumns = [
        'animals' => [
            'vaccination_type' => 'VARCHAR(100)',
            'vaccination_date' => 'DATE',
            'vaccination_time' => 'TIME',
            'qr_code' => 'VARCHAR(255)',
            'last_updated' => 'DATETIME'
        ],
        'health_records' => [
            'vaccination' => 'VARCHAR(100)',
            'record_date' => 'DATETIME'
        ]
    ];

    foreach ($requiredColumns as $table => $columns) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            throw new RuntimeException("Table $table does not exist");
        }

        foreach ($columns as $column => $type) {
            $stmt = $pdo->prepare("
                SELECT COLUMN_TYPE 
                FROM information_schema.columns 
                WHERE table_name = ? 
                AND column_name = ?
            ");
            $stmt->execute([$table, $column]);
            
            if (!$stmt->fetch()) {
                throw new RuntimeException(
                    "Column $column of type $type is missing in table $table. " .
                    "Run database migrations to fix this."
                );
            }
        }
    }
}