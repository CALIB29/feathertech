<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $host = 'sql305.infinityfree.com';
    $db = 'if0_37714811_animal_tracking_system';
    $user = 'if0_37714811';
    $pass = '4JlKimMRDK';
    
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create animals table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS animals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            age INT NOT NULL,
            breed VARCHAR(100) NOT NULL,
            mark VARCHAR(100) NOT NULL,
            breed_season VARCHAR(50) NOT NULL,
            status VARCHAR(50) DEFAULT 'Not Yet Ready',
            vaccination_type VARCHAR(100),
            vaccination_date DATE,
            vaccination_time TIME,
            qr_code VARCHAR(255),
            last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL
        )
    ");
    
    // Create breeding_pairs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS breeding_pairs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            male_id INT NOT NULL,
            female_id INT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            expected_hatch_date DATE,
            status ENUM('Active', 'Completed', 'Failed') DEFAULT 'Active',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (male_id) REFERENCES animals(id),
            FOREIGN KEY (female_id) REFERENCES animals(id)
        )
    ");
    
    // Insert sample data only if animals table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM animals");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Insert sample animals
        $sampleAnimals = [
            ['Rooster', 300, 'Rhode Island Red', 'RIR-001', 'National'],
            ['Hen', 280, 'Plymouth Rock', 'PR-001', 'National'],
            ['Chick', 45, 'Leghorn', 'LH-001', 'EarlyBird'],
            ['Hen', 260, 'Sussex', 'SX-001', 'National'],
            ['Rooster', 290, 'Brahma', 'BR-001', 'National']
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO animals (type, age, breed, mark, breed_season, status) 
            VALUES (?, ?, ?, ?, ?, 
                CASE 
                    WHEN type = 'Chick' AND age >= 180 THEN 'Ready for Harvesting'
                    WHEN type = 'Hen' AND age >= 240 THEN 'Ready for Breeding'
                    WHEN type = 'Rooster' AND age >= 270 THEN 'Ready for Conditioning'
                    ELSE 'Not Yet Ready'
                END
            )
        ");
        
        foreach ($sampleAnimals as $animal) {
            $stmt->execute($animal);
        }
        
        echo "Sample data has been inserted successfully!";
    }
    
    echo "Database setup completed successfully!";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
