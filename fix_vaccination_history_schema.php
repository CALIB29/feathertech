<?php
// Fix vaccination_history table schema
// This ensures the vaccination_history table has proper column lengths

require_once 'includes/db.php';

echo "<h1>Fixing vaccination_history Table Schema</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;}</style>";

try {
    // Check if vaccination_history table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'vaccination_history'");
    if ($stmt->rowCount() === 0) {
        echo "<div class='error'>❌ vaccination_history table does not exist</div>";
        echo "<p>Creating vaccination_history table...</p>";

        // Create the table with proper column lengths
        $sql = "
        CREATE TABLE vaccination_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            animal_id INT NOT NULL,
            vaccine_name VARCHAR(255) NOT NULL,
            vaccine_type VARCHAR(255) NOT NULL,
            vaccination_date DATE NOT NULL,
            next_due_date DATE,
            administered_by VARCHAR(100),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (animal_id) REFERENCES animals(id)
        )";

        $pdo->exec($sql);
        echo "<div class='success'>✅ Created vaccination_history table with proper schema</div>";
    } else {
        echo "<div class='success'>✅ vaccination_history table exists</div>";

        // Check current schema
        $stmt = $pdo->query("DESCRIBE vaccination_history");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>Current vaccination_history table structure:</h3>";
        echo "<table border='1' style='border-collapse:collapse;'>";
        echo "<tr><th>Column</th><th>Type</th><th>Null</th></tr>";

        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Check vaccine_type column specifically
        $vaccineTypeColumn = null;
        foreach ($columns as $column) {
            if ($column['Field'] === 'vaccine_type') {
                $vaccineTypeColumn = $column;
                break;
            }
        }

        if ($vaccineTypeColumn) {
            $currentType = $vaccineTypeColumn['Type'];
            echo "<div class='success'>✅ vaccine_type column exists: $currentType</div>";

            // Check if we need to increase the size
            if (preg_match('/varchar\((\d+)\)/i', $currentType, $matches)) {
                $length = (int)$matches[1];
                if ($length < 255) {
                    echo "<p>Increasing vaccine_type column from $length to 255 characters...</p>";

                    $sql = "ALTER TABLE vaccination_history MODIFY COLUMN vaccine_type VARCHAR(255) NOT NULL";
                    $pdo->exec($sql);
                    echo "<div class='success'>✅ Increased vaccine_type column to VARCHAR(255)</div>";
                } else {
                    echo "<div class='success'>✅ Column length is sufficient ($length characters)</div>";
                }
            }
        } else {
            echo "<div class='error'>❌ vaccine_type column not found in vaccination_history table</div>";
            echo "<p>Adding vaccine_type column...</p>";

            $sql = "ALTER TABLE vaccination_history ADD COLUMN vaccine_type VARCHAR(255) NOT NULL";
            $pdo->exec($sql);
            echo "<div class='success'>✅ Added vaccine_type column (VARCHAR(255))</div>";
        }
    }

    // Verify final schema
    echo "<h2>Final vaccination_history table structure:</h2>";

    $stmt = $pdo->query("DESCRIBE vaccination_history");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse:collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th></tr>";

    foreach ($finalColumns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h2>✅ vaccination_history table schema fixed successfully!</h2>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Database error: " . $e->getMessage() . "</div>";
    echo "<p>Error code: " . $e->getCode() . "</p>";
} catch (Exception $e) {
    echo "<div class='error'>❌ General error: " . $e->getMessage() . "</div>";
}

echo "<hr>";
echo "<h3>What was fixed:</h3>";
echo "<ul>";
echo "<li>✅ Ensured vaccination_history table exists</li>";
echo "<li>✅ Added vaccine_type column (VARCHAR(255)) if missing</li>";
echo "<li>✅ Increased vaccine_type column length if too small</li>";
echo "<li>✅ Verified all changes</li>";
echo "</ul>";

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>Try vaccination update again</strong> - The error should be resolved</li>";
echo "<li><strong>Check browser cache</strong> - Clear cache if needed</li>";
echo "<li><strong>Monitor server logs</strong> - Watch for any remaining issues</li>";
echo "</ol>";

echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
?>
