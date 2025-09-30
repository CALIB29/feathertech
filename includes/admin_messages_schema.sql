-- SQL schema for admin_messages table
CREATE TABLE IF NOT EXISTS admin_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    task_type VARCHAR(100) DEFAULT 'Vaccination',
    due_date DATE,
    status ENUM('pending','completed') DEFAULT 'pending',
    proof VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);
