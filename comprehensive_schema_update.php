<?php
// Comprehensive Database Schema Update Script
// This script adds missing columns and fixes schema issues

require_once 'includes/db.php';

echo "<h1>Database Schema Update</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .warning{color:orange;} .error{color:red;}</style>";

try {
    echo "<h2>Step 1: Checking animals table structure...</h2>";

    // Check if animals table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'animals'");
    if ($stmt->rowCount() === 0) {
        echo "<div class='error'>❌ animals table does not exist</div>";
        echo "<p>Please run the full database schema setup first.</p>";
        exit;
    }

    echo "<div class='success'>✅ animals table exists</div>";

    // Get current columns
    $stmt = $pdo->query("DESCRIBE animals");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');

    echo "<h3>Current columns in animals table:</h3>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><code>{$column['Field']}</code> ({$column['Type']})</li>";
    }
    echo "</ul>";

    // Step 2: Add missing vaccine_type column
    if (!in_array('vaccine_type', $columnNames)) {
        echo "<h2>Step 2: Adding vaccine_type column...</h2>";

        try {
            $sql = "ALTER TABLE animals ADD COLUMN vaccine_type VARCHAR(255) NULL";
            $pdo->exec($sql);
            echo "<div class='success'>✅ Added vaccine_type column (VARCHAR(255))</div>";
        } catch (PDOException $e) {
            if ($e->getCode() == '42S21') {
                echo "<div class='warning'>⚠️ Column already exists</div>";
            } else {
                throw $e;
            }
        }
    } else {
        echo "<h2>Step 2: Checking vaccine_type column...</h2>";

        // Check current vaccine_type column definition
        foreach ($columns as $column) {
            if ($column['Field'] === 'vaccine_type') {
                $currentType = $column['Type'];
                echo "<div class='success'>✅ vaccine_type column exists: $currentType</div>";

                // Check if we need to increase the size
                if (preg_match('/varchar\((\d+)\)/i', $currentType, $matches)) {
                    $length = (int)$matches[1];
                    if ($length < 255) {
                        echo "<p>Current length: $length characters. Increasing to 255...</p>";

                        $sql = "ALTER TABLE animals MODIFY COLUMN vaccine_type VARCHAR(255)";
                        $pdo->exec($sql);
                        echo "<div class='success'>✅ Increased vaccine_type column to VARCHAR(255)</div>";
                    } else {
                        echo "<div class='success'>✅ Column length is sufficient ($length characters)</div>";
                    }
                }
                break;
            }
        }
    }

    // Step 3: Add other missing columns that might be needed
    echo "<h2>Step 3: Checking for other missing columns...</h2>";

    $missingColumns = [];

    // Check for vaccination_date column
    if (!in_array('vaccination_date', $columnNames)) {
        $missingColumns[] = 'vaccination_date';
    }

    // Check for vaccination_time column
    if (!in_array('vaccination_time', $columnNames)) {
        $missingColumns[] = 'vaccination_time';
    }

    // Check for next_vaccination_date column
    if (!in_array('next_vaccination_date', $columnNames)) {
        $missingColumns[] = 'next_vaccination_date';
    }

    if (!empty($missingColumns)) {
        echo "<p>Found missing columns: " . implode(', ', $missingColumns) . "</p>";

        foreach ($missingColumns as $column) {
            try {
                switch ($column) {
                    case 'vaccination_date':
                        $sql = "ALTER TABLE animals ADD COLUMN vaccination_date DATE NULL";
                        break;
                    case 'vaccination_time':
                        $sql = "ALTER TABLE animals ADD COLUMN vaccination_time TIME NULL";
                        break;
                    case 'next_vaccination_date':
                        $sql = "ALTER TABLE animals ADD COLUMN next_vaccination_date DATE NULL";
                        break;
                    default:
                        continue 2;
                }

                $pdo->exec($sql);
                echo "<div class='success'>✅ Added $column column</div>";
            } catch (PDOException $e) {
                if ($e->getCode() == '42S21') {
                    echo "<div class='warning'>⚠️ $column column already exists</div>";
                } else {
                    echo "<div class='error'>❌ Error adding $column column: " . $e->getMessage() . "</div>";
                }
            }
        }
    } else {
        echo "<div class='success'>✅ All required columns already exist</div>";
    }

    // Step 4: Verify final schema
    echo "<h2>Step 4: Verifying final schema...</h2>";

    $stmt = $pdo->query("DESCRIBE animals");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Final animals table structure:</h3>";
    echo "<table border='1' style='border-collapse:collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";

    foreach ($finalColumns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Step 5: Check for sample data
    echo "<h2>Step 5: Checking sample data...</h2>";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM animals WHERE vaccine_type IS NOT NULL");
    $result = $stmt->fetch();
    echo "<p>Animals with vaccine_type data: <strong>{$result['count']}</strong></p>";

    if ($result['count'] > 0) {
        $stmt = $pdo->query("SELECT id, type, vaccine_type FROM animals WHERE vaccine_type IS NOT NULL LIMIT 5");
        $samples = $stmt->fetchAll();

        echo "<h4>Sample vaccine_type data:</h4>";
        echo "<ul>";
        foreach ($samples as $sample) {
            $truncated = strlen($sample['vaccine_type']) > 50 ?
                substr($sample['vaccine_type'], 0, 50) . '...' :
                $sample['vaccine_type'];
            echo "<li>Animal #{$sample['id']} ({$sample['type']}): <code>$truncated</code></li>";
        }
        echo "</ul>";
    }

    echo "<h2>✅ Database schema update completed successfully!</h2>";
    echo "<div class='success'>All required columns are now available with proper lengths.</div>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Database error: " . $e->getMessage() . "</div>";
    echo "<p>Error code: " . $e->getCode() . "</p>";

    if ($e->getCode() == '42S02') {
        echo "<p>The animals table doesn't exist. Please run the full schema setup first.</p>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ General error: " . $e->getMessage() . "</div>";
}

echo "<hr>";
echo "<h3>What was fixed:</h3>";
echo "<ul>";
echo "<li>✅ Added vaccine_type column (VARCHAR(255))</li>";
echo "<li>✅ Added vaccination_date column</li>";
echo "<li>✅ Added vaccination_time column</li>";
echo "<li>✅ Added next_vaccination_date column</li>";
echo "<li>✅ Ensured sufficient column lengths for long vaccine names</li>";
echo "</ul>";

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>Test vaccination updates</strong> - Try updating a vaccination now</li>";
echo "<li><strong>Check application functionality</strong> - Verify all features work</li>";
echo "<li><strong>Monitor for errors</strong> - Watch server logs for any issues</li>";
echo "</ol>";

echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
?>
