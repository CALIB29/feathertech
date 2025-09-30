<?php
function setupVaccinationSchema($pdo) {
    try {
        // Vaccination schedule template table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vaccination_schedules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bird_type ENUM('Chick', 'Hen', 'Rooster') NOT NULL,
                vaccine_name VARCHAR(100) NOT NULL,
                day_of_administration INT NOT NULL,
                administration_method VARCHAR(100),
                notes TEXT,
                is_booster BOOLEAN DEFAULT FALSE,
                booster_interval INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY (bird_type, vaccine_name, day_of_administration)
            )
        ");

        // Vaccination records table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vaccinations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                animal_id INT NOT NULL,
                schedule_id INT NOT NULL,
                vaccine_name VARCHAR(100) NOT NULL,
                administered_at DATETIME NOT NULL,
                administered_by INT,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (animal_id) REFERENCES animals(id),
                FOREIGN KEY (administered_by) REFERENCES users(id)
            )
        ");

        // Vaccination notifications table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vaccination_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                animal_id INT NOT NULL,
                schedule_id INT NOT NULL,
                vaccine_name VARCHAR(100) NOT NULL,
                due_date DATE NOT NULL,
                status ENUM('pending', 'completed', 'skipped') DEFAULT 'pending',
                notified_at DATETIME,
                completed_at DATETIME,
                completed_by INT,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (animal_id) REFERENCES animals(id),
                FOREIGN KEY (completed_by) REFERENCES users(id)
            )
        ");

        // Insert default vaccination schedules if empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM vaccination_schedules");
        if ($stmt->fetchColumn() == 0) {
            $defaultSchedules = [
                // Chick schedules
                ['Chick', "Marek's", 1, "Injection", "Essential for day-old chicks", false, null],
                ['Chick', "Newcastle + IB (Hitchner B1)", 5, "Eye drop or drinking water", "", false, null],
                ['Chick', "Gumboro", 14, "Drinking water", "Immune booster", false, null],
                ['Chick', "Gumboro booster", 21, "Drinking water", "Optional booster", true, 7],
                ['Chick', "Fowl Pox", 28, "Wing stab", "", false, null],
                ['Chick', "ND LaSota", 35, "Drinking water", "Respiratory prevention", false, null],
                
                // Hen schedules
                ['Hen', "Fowl Cholera", 56, "Injection", "Start of grower stage", false, null],
                ['Hen', "ND Clone/LaSota booster", 112, "Drinking water", "Before laying starts", true, 56],
                
                // Rooster schedules
                ['Rooster', "Fowl Cholera", 56, "Injection", "Grower stage vaccine", false, null]
            ];

            $stmt = $pdo->prepare("
                INSERT INTO vaccination_schedules 
                (bird_type, vaccine_name, day_of_administration, administration_method, notes, is_booster, booster_interval)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($defaultSchedules as $schedule) {
                $stmt->execute($schedule);
            }
        }
    } catch (PDOException $e) {
        error_log("Database schema error: " . $e->getMessage());
        throw $e;
    }
}
?>