USE task_tracker;

-- Add project_link and division_id
ALTER TABLE projects ADD COLUMN project_link VARCHAR(1000) DEFAULT NULL AFTER project_contacts;
ALTER TABLE projects ADD COLUMN division_id INT DEFAULT NULL AFTER id;

-- Update division_id based on created_by user
UPDATE projects p
JOIN users u ON p.created_by = u.id
SET p.division_id = u.division_id;

-- Update ENUM
ALTER TABLE projects MODIFY status ENUM('not_yet_start', 'ongoing', 'completed', 'waiting_for_customer_info', 'on_hold', 'cancelled') DEFAULT 'not_yet_start';

-- Create remarks table
CREATE TABLE IF NOT EXISTS project_remarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    created_by INT NOT NULL,
    remark LONGTEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Migrate old remarks
INSERT INTO project_remarks (project_id, created_by, remark, created_at)
SELECT id, created_by, remarks, created_at
FROM projects
WHERE remarks IS NOT NULL AND TRIM(remarks) != '';

-- Drop old remarks column
ALTER TABLE projects DROP COLUMN remarks;
