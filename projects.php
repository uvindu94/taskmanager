<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Add created_by column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN created_by INT(11) DEFAULT NULL");
    $pdo->exec("ALTER TABLE projects ADD FOREIGN KEY (created_by) REFERENCES users(id)");
} catch (PDOException $e) {
    // Column already exists or other error - continue
}

// Add status column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN status ENUM('not_yet_start', 'ongoing', 'waiting_for_customer_info') DEFAULT 'not_yet_start'");
} catch (PDOException $e) {
    // Column already exists or other error - continue
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $project = $_POST['project'];
        $sales_officer = $_POST['sales_officer'];
        $project_contacts = $_POST['project_contacts'];
        $remarks = $_POST['remarks'];
        $completion = (int)$_POST['completion'];
        $due_date = $_POST['due_date'] ? $_POST['due_date'] : null;
        $status = $_POST['status'] ?? 'not_yet_start';
        
        $stmt = $pdo->prepare("INSERT INTO projects (project, sales_officer, project_contacts, remarks, completion, due_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$project, $sales_officer, $project_contacts, $remarks, $completion, $due_date, $status, $user_id]);
        
        header('Location: projects.php?success=Project created successfully');
        exit;
    }
    
    elseif ($action === 'update') {
        $id = $_POST['id'];
        $project = $_POST['project'];
        $sales_officer = $_POST['sales_officer'];
        $project_contacts = $_POST['project_contacts'];
        $remarks = $_POST['remarks'];
        $completion = (int)$_POST['completion'];
        $due_date = $_POST['due_date'] ? $_POST['due_date'] : null;
        $status = $_POST['status'] ?? 'not_yet_start';
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT created_by FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project_owner = $stmt->fetchColumn();
        
        if ($project_owner == $user_id) {
            $stmt = $pdo->prepare("UPDATE projects SET project = ?, sales_officer = ?, project_contacts = ?, remarks = ?, completion = ?, due_date = ?, status = ? WHERE id = ?");
            $stmt->execute([$project, $sales_officer, $project_contacts, $remarks, $completion, $due_date, $status, $id]);
            
            header('Location: projects.php?success=Project updated successfully');
        } else {
            header('Location: projects.php?error=Access denied');
        }
        exit;
    }
    
    elseif ($action === 'delete') {
        $id = $_POST['id'];
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT created_by FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project_owner = $stmt->fetchColumn();
        
        if ($project_owner == $user_id) {
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            
            header('Location: projects.php?success=Project deleted successfully');
        } else {
            header('Location: projects.php?error=Access denied');
        }
        exit;
    }
}

// Get user's projects
$stmt = $pdo->prepare("SELECT p.*, u.full_name as creator_name FROM projects p LEFT JOIN users u ON p.created_by = u.id WHERE p.created_by = ? OR p.created_by IS NULL ORDER BY p.created_at DESC");
$stmt->execute([$user_id]);
$my_projects = $stmt->fetchAll();

// Get all projects with user information
$stmt = $pdo->prepare("SELECT p.*, u.full_name as creator_name, u.role FROM projects p LEFT JOIN users u ON p.created_by = u.id ORDER BY u.full_name, p.created_at DESC");
$stmt->execute();
$all_projects = $stmt->fetchAll();

// Group projects by user
$projects_by_user = [];
foreach ($all_projects as $project) {
    $user_key = $project['created_by'] ?? 'unassigned';
    if (!isset($projects_by_user[$user_key])) {
        $projects_by_user[$user_key] = [
            'user' => [
                'name' => $project['creator_name'] ?? 'Unassigned',
                'role' => $project['role'] ?? 'Unknown'
            ],
            'projects' => []
        ];
    }
    $projects_by_user[$user_key]['projects'][] = $project;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects Management - Task Tracker Pro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #2d3748;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.2);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-left h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header-subtitle {
            opacity: 0.9;
            font-size: 16px;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            backdrop-filter: blur(10px);
        }

        .btn-primary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
        }

        .btn-secondary:hover {
            background: #f7fafc;
            transform: translateY(-2px);
        }

        /* Tab Navigation */
        .tab-nav {
            display: flex;
            background: white;
            border-radius: 12px;
            padding: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .tab-btn {
            flex: 1;
            padding: 15px 20px;
            background: none;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #718096;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .tab-btn:hover:not(.active) {
            background: #f7fafc;
            color: #4a5568;
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* My Projects Section */
        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
        }

        .projects-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 30px;
        }

        .project-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
            position: relative;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .project-title {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .project-meta {
            color: #718096;
            font-size: 14px;
        }

        .project-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .action-btn.edit {
            background: #e6fffa;
            color: #319795;
        }

        .action-btn.delete {
            background: #fed7d7;
            color: #e53e3e;
        }

        .action-btn:hover {
            transform: scale(1.1);
        }

        .project-details {
            margin-bottom: 20px;
        }

        .project-field {
            margin-bottom: 15px;
        }

        .field-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #718096;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .field-value {
            color: #2d3748;
            font-weight: 500;
        }

        /* Progress Bar */
        .progress-container {
            margin-top: 20px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .progress-label {
            font-size: 14px;
            font-weight: 600;
            color: #4a5568;
        }

        .progress-percentage {
            font-size: 14px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 20px;
            color: white;
        }

        .progress-bar {
            width: 100%;
            height: 10px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 10px;
            transition: width 0.8s ease;
            position: relative;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Due Date Badge */
        .due-date-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
        }

        .due-date-badge.upcoming {
            background: #fef5e7;
            color: #d69e2e;
            border: 1px solid #f6ad55;
        }

        .due-date-badge.overdue {
            background: #fed7d7;
            color: #e53e3e;
            border: 1px solid #f56565;
        }

        /* Status Labels */
        .status-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
            border: 2px solid;
        }

        .status-not_yet_start {
            background: #fef5e7;
            color: #d69e2e;
            border-color: #f6ad55;
        }

        .status-ongoing {
            background: #e6fffa;
            color: #319795;
            border-color: #4fd1c7;
        }

        .status-waiting_for_customer_info {
            background: #fed7d7;
            color: #e53e3e;
            border-color: #f56565;
        }

        .status-completed {
            background: #f0fff4;
            color: #38a169;
            border-color: #68d391;
        }

        .status-icon {
            font-size: 10px;
        }

        /* Status selector in modal */
        .status-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }

        .status-option {
            position: relative;
        }

        .status-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .status-option label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8fafc;
            font-weight: 500;
            font-size: 14px;
        }

        .status-option label:hover {
            border-color: #cbd5e0;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .status-option input:checked + label {
            border-color: currentColor;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: scale(1.02);
        }

        .status-option.not-yet-start label {
            color: #d69e2e;
        }

        .status-option.not-yet-start input:checked + label {
            background: #fef5e7;
            border-color: #f6ad55;
        }

        .status-option.ongoing label {
            color: #319795;
        }

        .status-option.ongoing input:checked + label {
            background: #e6fffa;
            border-color: #4fd1c7;
        }

        .status-option.waiting label {
            color: #e53e3e;
        }

        .status-option.waiting input:checked + label {
            background: #fed7d7;
            border-color: #f56565;
        }

        .status-badge-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: white;
            flex-shrink: 0;
        }

        .status-option.not-yet-start .status-badge-icon {
            background: #f6ad55;
        }

        .status-option.ongoing .status-badge-icon {
            background: #4fd1c7;
        }

        .status-option.waiting .status-badge-icon {
            background: #f56565;
        }

        /* Team Overview */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .user-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .user-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            position: relative;
            overflow: hidden;
        }

        .user-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="white" opacity="0.1"/><circle cx="80" cy="40" r="1.5" fill="white" opacity="0.1"/></svg>');
            pointer-events: none;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            backdrop-filter: blur(10px);
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .user-details h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .user-role {
            opacity: 0.9;
            font-size: 14px;
        }

        .user-stats {
            display: flex;
            justify-content: space-between;
            padding: 20px 25px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
        }

        .stat-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .user-projects {
            max-height: 300px;
            overflow-y: auto;
        }

        .mini-project {
            padding: 15px 25px;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.3s ease;
        }

        .mini-project:hover {
            background: #f8fafc;
        }

        .mini-project:last-child {
            border-bottom: none;
        }

        .mini-project-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .mini-progress {
            height: 6px;
            background: #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }

        .mini-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 6px;
            transition: width 0.8s ease;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            animation: slideIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px) scale(0.9); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
        }

        .close-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .modal-body {
            padding: 30px;
        }

        .form-grid {
            display: grid;
            gap: 20px;
        }

        .form-group {
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a5568;
            font-size: 14px;
        }

        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            background: #f7fafc;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .range-container {
            margin-top: 10px;
        }

        .range-input {
            width: 100%;
            height: 8px;
            border-radius: 5px;
            background: #e2e8f0;
            outline: none;
            appearance: none;
        }

        .range-input::-webkit-slider-thumb {
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(102, 126, 234, 0.3);
        }

        .range-value {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 700;
            color: #667eea;
            font-size: 18px;
            margin-left: 10px;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            padding: 25px 30px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        .btn-modal {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-cancel:hover {
            background: #cbd5e0;
        }

        /* Alert Messages */
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            z-index: 2000;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            animation: slideInRight 0.3s ease;
        }

        .alert.success {
            background: linear-gradient(135deg, #48bb78, #38a169);
        }

        .alert.error {
            background: linear-gradient(135deg, #f56565, #e53e3e);
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-state i {
            font-size: 64px;
            color: #e2e8f0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: #4a5568;
            margin-bottom: 10px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header, .tab-nav {
                margin: 15px 0;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .team-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                margin: 20px;
                width: calc(100% - 40px);
            }

            .modal-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1>Projects Management</h1>
                    <p class="header-subtitle">Manage and track project progress across your team</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openModal('create')">
                        <i class="fas fa-plus"></i>
                        New Project
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('my-projects')">
                <i class="fas fa-user-circle"></i>
                My Projects (<?php echo count($my_projects); ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('team-overview')">
                <i class="fas fa-users"></i>
                Team Overview
            </button>
        </div>

        <!-- My Projects Tab -->
        <div id="my-projects" class="tab-content active">
            <div class="section-header">
                <h2 class="section-title">My Projects</h2>
            </div>

            <?php if (empty($my_projects)): ?>
                <div class="empty-state">
                    <i class="fas fa-project-diagram"></i>
                    <h3>No Projects Yet</h3>
                    <p>Start by creating your first project to track your work progress.</p>
                    <button class="btn btn-primary" onclick="openModal('create')" style="margin-top: 20px;">
                        <i class="fas fa-plus"></i>
                        Create First Project
                    </button>
                </div>
            <?php else: ?>
                <div class="projects-grid">
                    <?php foreach ($my_projects as $project): ?>
                        <div class="project-card">
                            <div class="project-header">
                                <div>
                                    <h3 class="project-title"><?php echo htmlspecialchars($project['project']); ?></h3>
                                    <div class="project-meta">
                                        Created <?php echo date('M j, Y', strtotime($project['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="project-actions">
                                    <button class="action-btn edit" onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($project)); ?>)" title="Edit Project">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" onclick="deleteProject(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['project']); ?>')" title="Delete Project">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Status Label (only show for non-completed projects) -->
                            <?php 
                            $completion = (int)$project['completion'];
                            $status = $project['status'] ?? 'not_yet_start';
                            if ($completion < 100): 
                            ?>
                                <div class="status-label status-<?php echo $status; ?>">
                                    <i class="fas fa-<?php 
                                        echo $status === 'not_yet_start' ? 'hourglass-start' : 
                                            ($status === 'ongoing' ? 'spinner' : 'clock'); 
                                    ?> status-icon"></i>
                                    <?php 
                                    echo $status === 'not_yet_start' ? 'Not Yet Started' : 
                                        ($status === 'ongoing' ? 'Ongoing' : 'Waiting for Customer Info'); 
                                    ?>
                                </div>
                            <?php else: ?>
                                <div class="status-label status-completed">
                                    <i class="fas fa-check-circle status-icon"></i>
                                    Completed
                                </div>
                            <?php endif; ?>

                            <div class="project-details">
                                <?php if ($project['sales_officer']): ?>
                                    <div class="project-field">
                                        <div class="field-label">Sales Officer</div>
                                        <div class="field-value"><?php echo htmlspecialchars($project['sales_officer']); ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($project['project_contacts']): ?>
                                    <div class="project-field">
                                        <div class="field-label">Project Contacts</div>
                                        <div class="field-value"><?php echo nl2br(htmlspecialchars($project['project_contacts'])); ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($project['remarks']): ?>
                                    <div class="project-field">
                                        <div class="field-label">Remarks</div>
                                        <div class="field-value"><?php echo nl2br(htmlspecialchars($project['remarks'])); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="progress-container">
                                <div class="progress-header">
                                    <span class="progress-label">Project Progress</span>
                                    <span class="progress-percentage" style="background: <?php 
                                        $completion = (int)$project['completion'];
                                        echo $completion >= 100 ? '#48bb78' : ($completion >= 75 ? '#38a169' : ($completion >= 50 ? '#d69e2e' : '#f6ad55'));
                                    ?>;">
                                        <?php echo $completion; ?>%
                                    </span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $completion; ?>%"></div>
                                </div>
                            </div>

                            <?php if ($project['due_date']): ?>
                                <div class="due-date-badge <?php 
                                    $due_date = strtotime($project['due_date']);
                                    $now = time();
                                    $completion = (int)$project['completion'];
                                    
                                    if ($completion >= 100) {
                                        echo 'completed';
                                    } elseif ($due_date < $now) {
                                        echo 'overdue';
                                    } else {
                                        echo 'upcoming';
                                    }
                                ?>">
                                    <i class="fas fa-calendar-alt"></i>
                                    Due: <?php echo date('M j, Y', $due_date); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Team Overview Tab -->
        <div id="team-overview" class="tab-content">
            <div class="section-header">
                <h2 class="section-title">Team Projects Overview</h2>
            </div>

            <div class="team-grid">
                <?php foreach ($projects_by_user as $user_data): ?>
                    <div class="user-card">
                        <div class="user-header">
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user_data['user']['name'], 0, 1)); ?>
                                </div>
                                <div class="user-details">
                                    <h3><?php echo htmlspecialchars($user_data['user']['name']); ?></h3>
                                    <div class="user-role"><?php echo htmlspecialchars($user_data['user']['role']); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="user-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo count($user_data['projects']); ?></div>
                                <div class="stat-label">Projects</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">
                                    <?php 
                                    $completed = array_filter($user_data['projects'], function($p) { return (int)$p['completion'] >= 100; });
                                    echo count($completed); 
                                    ?>
                                </div>
                                <div class="stat-label">Completed</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">
                                    <?php 
                                    $total_progress = array_sum(array_column($user_data['projects'], 'completion'));
                                    $avg_progress = count($user_data['projects']) > 0 ? round($total_progress / count($user_data['projects'])) : 0;
                                    echo $avg_progress; 
                                    ?>%
                                </div>
                                <div class="stat-label">Avg Progress</div>
                            </div>
                        </div>

                        <div class="user-projects">
                            <?php foreach ($user_data['projects'] as $project): ?>
                                <div class="mini-project">
                                    <div class="mini-project-title">
                                        <?php echo htmlspecialchars($project['project']); ?>
                                        
                                        <!-- Mini status indicator -->
                                        <?php 
                                        $completion = (int)$project['completion'];
                                        $status = $project['status'] ?? 'not_yet_start';
                                        if ($completion < 100): 
                                        ?>
                                            <span class="status-label status-<?php echo $status; ?>" style="font-size: 10px; padding: 2px 6px; margin-left: 8px;">
                                                <?php 
                                                echo $status === 'not_yet_start' ? 'Not Started' : 
                                                    ($status === 'ongoing' ? 'Ongoing' : 'Waiting'); 
                                                ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-label status-completed" style="font-size: 10px; padding: 2px 6px; margin-left: 8px;">
                                                Completed
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mini-progress">
                                        <div class="mini-progress-fill" style="width: <?php echo (int)$project['completion']; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Project Modal -->
    <div id="projectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Create New Project</h3>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="projectForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="projectId" value="">

                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="project">Project Name *</label>
                            <input type="text" id="project" name="project" class="form-input" 
                                   placeholder="Enter project name..." required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="sales_officer">Sales Officer</label>
                            <input type="text" id="sales_officer" name="sales_officer" class="form-input" 
                                   placeholder="Enter sales officer name...">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="project_contacts">Project Contacts</label>
                            <textarea id="project_contacts" name="project_contacts" class="form-textarea" 
                                      placeholder="Enter project contact details..."></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" class="form-textarea" 
                                      placeholder="Enter any additional remarks..."></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="status">Project Status</label>
                            <div class="status-options">
                                <div class="status-option not-yet-start">
                                    <input type="radio" id="status_not_yet_start" name="status" value="not_yet_start" checked>
                                    <label for="status_not_yet_start">
                                        <div class="status-badge-icon">
                                            <i class="fas fa-hourglass-start"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;">Not Yet Started</div>
                                            <div style="font-size: 12px; color: #718096;">Project planning phase</div>
                                        </div>
                                    </label>
                                </div>

                                <div class="status-option ongoing">
                                    <input type="radio" id="status_ongoing" name="status" value="ongoing">
                                    <label for="status_ongoing">
                                        <div class="status-badge-icon">
                                            <i class="fas fa-spinner"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;">Ongoing</div>
                                            <div style="font-size: 12px; color: #718096;">Work in progress</div>
                                        </div>
                                    </label>
                                </div>

                                <div class="status-option waiting">
                                    <input type="radio" id="status_waiting_for_customer_info" name="status" value="waiting_for_customer_info">
                                    <label for="status_waiting_for_customer_info">
                                        <div class="status-badge-icon">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;">Waiting for Customer Info</div>
                                            <div style="font-size: 12px; color: #718096;">Blocked by external dependency</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="completion">
                                Project Completion 
                                <span class="range-value">
                                    <span id="completionValue">0</span>%
                                </span>
                            </label>
                            <div class="range-container">
                                <input type="range" id="completion" name="completion" class="range-input" 
                                       min="0" max="100" value="0" step="5">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="due_date">Due Date</label>
                            <input type="datetime-local" id="due_date" name="due_date" class="form-input">
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-modal btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-modal btn-save" id="saveBtn">
                        <i class="fas fa-save"></i>
                        Save Project
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab Switching
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab button
            event.target.classList.add('active');
        }

        // Modal Functions
        function openModal(action, projectData = null) {
            const modal = document.getElementById('projectModal');
            const form = document.getElementById('projectForm');
            const title = document.getElementById('modalTitle');
            const saveBtn = document.getElementById('saveBtn');
            
            // Reset form
            form.reset();
            
            if (action === 'create') {
                title.textContent = 'Create New Project';
                document.getElementById('formAction').value = 'create';
                document.getElementById('projectId').value = '';
                saveBtn.innerHTML = '<i class="fas fa-plus"></i> Create Project';
                document.getElementById('completionValue').textContent = '0';
            } else if (action === 'edit' && projectData) {
                title.textContent = 'Edit Project';
                document.getElementById('formAction').value = 'update';
                document.getElementById('projectId').value = projectData.id;
                
                // Fill form with project data
                document.getElementById('project').value = projectData.project || '';
                document.getElementById('sales_officer').value = projectData.sales_officer || '';
                document.getElementById('project_contacts').value = projectData.project_contacts || '';
                document.getElementById('remarks').value = projectData.remarks || '';
                document.getElementById('completion').value = projectData.completion || 0;
                document.getElementById('completionValue').textContent = projectData.completion || 0;
                
                // Set status
                const statusValue = projectData.status || 'not_yet_start';
                const statusRadio = document.querySelector(`input[name="status"][value="${statusValue}"]`);
                if (statusRadio) {
                    statusRadio.checked = true;
                }
                
                if (projectData.due_date) {
                    // Convert PHP datetime to HTML datetime-local format
                    const date = new Date(projectData.due_date);
                    const localDate = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
                    document.getElementById('due_date').value = localDate.toISOString().slice(0, 16);
                }
                
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Update Project';
            }
            
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Focus on first input
            setTimeout(() => {
                document.getElementById('project').focus();
            }, 300);
        }

        function closeModal() {
            const modal = document.getElementById('projectModal');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        function deleteProject(id, name) {
            if (confirm(`Are you sure you want to delete the project "${name}"?\n\nThis action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const idInput = document.createElement('input');
                idInput.name = 'id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-hide status labels for completed projects
        function updateStatusVisibility() {
            const completionSlider = document.getElementById('completion');
            const statusSection = document.querySelector('.status-options').parentElement;
            
            if (parseInt(completionSlider.value) >= 100) {
                statusSection.style.opacity = '0.5';
                statusSection.style.pointerEvents = 'none';
                // Add a note
                let completedNote = statusSection.querySelector('.completed-note');
                if (!completedNote) {
                    completedNote = document.createElement('div');
                    completedNote.className = 'completed-note';
                    completedNote.style.cssText = 'font-size: 12px; color: #38a169; font-weight: 600; margin-top: 8px;';
                    completedNote.innerHTML = '<i class="fas fa-check-circle"></i> Project marked as completed - status automatically set';
                    statusSection.appendChild(completedNote);
                }
            } else {
                statusSection.style.opacity = '1';
                statusSection.style.pointerEvents = 'auto';
                const completedNote = statusSection.querySelector('.completed-note');
                if (completedNote) {
                    completedNote.remove();
                }
            }
        }

        // Range slider update
        document.getElementById('completion').addEventListener('input', function() {
            document.getElementById('completionValue').textContent = this.value;
            
            // Update slider color based on progress
            const progress = parseInt(this.value);
            let color = '#667eea';
            if (progress >= 100) color = '#48bb78';
            else if (progress >= 75) color = '#38a169';
            else if (progress >= 50) color = '#d69e2e';
            else if (progress >= 25) color = '#f6ad55';
            
            this.style.background = `linear-gradient(to right, ${color} 0%, ${color} ${progress}%, #e2e8f0 ${progress}%, #e2e8f0 100%)`;
            
            // Update status visibility based on completion
            updateStatusVisibility();
        });

        // Form submission
        document.getElementById('projectForm').addEventListener('submit', function(e) {
            const saveBtn = document.getElementById('saveBtn');
            const action = document.getElementById('formAction').value;
            
            saveBtn.disabled = true;
            saveBtn.innerHTML = action === 'create' ? 
                '<i class="fas fa-spinner fa-spin"></i> Creating...' : 
                '<i class="fas fa-spinner fa-spin"></i> Updating...';
        });

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Close modal on backdrop click
        document.getElementById('projectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Show success/error messages
        <?php if (isset($_GET['success'])): ?>
            showAlert('<?php echo htmlspecialchars($_GET['success']); ?>', 'success');
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            showAlert('<?php echo htmlspecialchars($_GET['error']); ?>', 'error');
        <?php endif; ?>

        function showAlert(message, type) {
            const alert = document.createElement('div');
            alert.className = `alert ${type}`;
            alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            
            document.body.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
                // Clean URL
                const url = new URL(window.location);
                url.searchParams.delete('success');
                url.searchParams.delete('error');
                window.history.replaceState({}, document.title, url);
            }, 5000);
        }

        // Auto-resize textareas
        document.querySelectorAll('.form-textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        });

        // Initialize progress animations on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Animate progress bars
            document.querySelectorAll('.progress-fill, .mini-progress-fill').forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
            
            // Initialize completion slider and status visibility
            const completionSlider = document.getElementById('completion');
            if (completionSlider) {
                completionSlider.dispatchEvent(new Event('input'));
                updateStatusVisibility();
            }
        });

        // Search functionality (bonus feature)
        function searchProjects(query) {
            const cards = document.querySelectorAll('.project-card');
            const lowercaseQuery = query.toLowerCase();
            
            cards.forEach(card => {
                const title = card.querySelector('.project-title').textContent.toLowerCase();
                const salesOfficer = card.querySelector('.field-value') ? 
                    card.querySelector('.field-value').textContent.toLowerCase() : '';
                
                if (title.includes(lowercaseQuery) || salesOfficer.includes(lowercaseQuery)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N to create new project
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                openModal('create');
            }
        });
    </script>
</body>
</html>