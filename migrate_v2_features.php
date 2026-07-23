<?php
require_once 'config.php';

echo "Starting V2 Feature Migration (Remarks & Designations)...\n";

try {
    $pdo->beginTransaction();

    // 1. Create Designations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS designations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Checked/Created designations table.\n";

    // 2. Add designation_id to users
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'designation_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN designation_id INT NULL");
        $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_user_designation FOREIGN KEY (designation_id) REFERENCES designations(id) ON DELETE SET NULL");
        echo "Added designation_id to users table.\n";
    }

    // 3. Create Task Remarks table
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_remarks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        user_id INT NOT NULL,
        remark TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Checked/Created task_remarks table.\n";

    $pdo->commit();
    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
}
