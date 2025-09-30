-- FeatherTech Animal Tracking System: Full Database Schema

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user','admin','superadmin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Animals Table
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
);

-- 3. Animal Archive Table
CREATE TABLE IF NOT EXISTS animal_archive (
    id INT PRIMARY KEY,
    type VARCHAR(50),
    age INT,
    breed VARCHAR(100),
    mark VARCHAR(100),
    status VARCHAR(50),
    vaccination_date DATE,
    vaccination_time TIME,
    qr_code VARCHAR(255),
    deleted_at DATETIME
);

-- 4. Health Records Table
CREATE TABLE IF NOT EXISTS health_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    animal_id INT NOT NULL,
    record_date DATETIME NOT NULL,
    vaccination VARCHAR(100),
    illness VARCHAR(100),
    treatment VARCHAR(100),
    notes TEXT,
    FOREIGN KEY (animal_id) REFERENCES animals(id)
);

-- 5. Egg Production Table
CREATE TABLE IF NOT EXISTS egg_production (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hen_id INT NOT NULL,
    production_date DATE NOT NULL,
    eggs_collected INT NOT NULL,
    FOREIGN KEY (hen_id) REFERENCES animals(id)
);

-- 6. Feeding Schedules Table
CREATE TABLE IF NOT EXISTS feeding_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    animal_id INT NOT NULL,
    feed_type VARCHAR(100),
    quantity VARCHAR(50),
    feeding_time TIME,
    FOREIGN KEY (animal_id) REFERENCES animals(id)
);

-- 7. Mortality Records Table
CREATE TABLE IF NOT EXISTS mortality_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    animal_id INT NOT NULL,
    death_date DATE NOT NULL,
    cause_of_death VARCHAR(255),
    FOREIGN KEY (animal_id) REFERENCES animals(id)
);

-- 8. Vaccination Tasks Table
CREATE TABLE IF NOT EXISTS vaccination_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    animal_id INT NOT NULL,
    task_type VARCHAR(100),
    task_details TEXT,
    due_date DATE,
    assigned_to INT,
    status VARCHAR(50) DEFAULT 'pending',
    completed_at DATETIME,
    completed_by INT,
    completion_notes TEXT,
    FOREIGN KEY (animal_id) REFERENCES animals(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (completed_by) REFERENCES users(id)
);

-- 9. Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    related_type VARCHAR(50),
    related_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
