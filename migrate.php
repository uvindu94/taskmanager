<?php
require_once 'config.php';

try {
    // 1. Create Divisions Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS divisions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        division_head_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    echo "divisions table created or exists.\n";

    // 2. Add division_id to users and update role enum
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN division_id INT NULL AFTER id");
        echo "Added division_id to users.\n";
    } catch (PDOException $e) {
        // Ignore if column exists
    }

    try {
        // Note: altering enum can be tricky if there's existing data, but we'll expand it first
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('Business Analyst','UI Developer','Intern Web Developer','Associate Web Developer','Senior Web Developer','Team Lead','Assistant Manager','Manager', 'super_admin', 'division_head', 'user') NOT NULL");
        echo "Updated users role ENUM.\n";
    } catch (PDOException $e) {
        echo "Error updating role ENUM: " . $e->getMessage() . "\n";
    }

    // 3. Update Tasks table
    try {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN target_value INT NULL AFTER title,
                                      ADD COLUMN unit VARCHAR(50) NULL AFTER target_value,
                                      ADD COLUMN division_id INT NULL AFTER assigned_to,
                                      ADD COLUMN completed_at DATETIME NULL AFTER due_date,
                                      ADD COLUMN reopened_at DATETIME NULL AFTER completed_at");
        echo "Added new columns to tasks.\n";
    } catch (PDOException $e) {}

    try {
        $pdo->exec("ALTER TABLE tasks MODIFY COLUMN status ENUM('pending','in_progress','completed','forwarded','reopened') DEFAULT 'pending'");
        echo "Updated tasks status ENUM.\n";
    } catch (PDOException $e) {
        echo "Error updating tasks status ENUM: " . $e->getMessage() . "\n";
    }

    // 4. Create Task Progress Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        date DATE NOT NULL,
        achievement_value INT NOT NULL,
        updated_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    echo "task_progress table created.\n";

    // 5. Create Task Forward History Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_forward_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        from_user INT NOT NULL,
        to_user INT NOT NULL,
        reason TEXT NULL,
        forwarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    echo "task_forward_history table created.\n";

    // 6. Create Task Status History Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        changed_by INT NOT NULL,
        old_status VARCHAR(50) NOT NULL,
        new_status VARCHAR(50) NOT NULL,
        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    echo "task_status_history table created.\n";

    // 7. Create Notifications Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        task_id INT NULL,
        type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    echo "notifications table created.\n";
    
    // Add Super Admin and a dummy Division
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'super_admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password, full_name, role) VALUES ('superadmin', '$hash', 'Super Admin', 'super_admin')");
        echo "Created super_admin user (username: superadmin, password: admin123)\n";
    }
    
    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}
?>
