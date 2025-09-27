<?php
// Move these declarations to the top of the file, after require_once
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get task_id and user_id early
$task_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Validate task_id
if ($task_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

// Verify task exists and user has permission
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch();
$canUpdate=1;


// Now handle the POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_status = $_POST['status'];
    $forward_to = $_POST['forward_to'] ?? null;
    $remark = trim($_POST['remark'] ?? '');

    // Start transaction
    $pdo->beginTransaction();

    try {
        if ($new_status === 'forward_to' && $forward_to) {
            // Forward task to another user
            $stmt = $pdo->prepare("UPDATE tasks SET assigned_to = ? WHERE id = ?");
            $stmt->execute([$forward_to, $task_id]);

            // Get new assignee name for history
            $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmt->execute([$forward_to]);
            $new_assignee_name = $stmt->fetchColumn();

            // Log history
            $stmt = $pdo->prepare("INSERT INTO task_history (task_id, action, performed_by) VALUES (?, ?, ?)");
            $stmt->execute([$task_id, "Task forwarded to $new_assignee_name", $user_id]);
        } else {
            // Regular status update
            $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $task_id]);

            // Log history
            $stmt = $pdo->prepare("INSERT INTO task_history (task_id, action, performed_by) VALUES (?, ?, ?)");
            $stmt->execute([$task_id, "Status changed to '$new_status'", $user_id]);
        }

        // Insert remark if provided
        if (!empty($remark)) {
            $stmt = $pdo->prepare("INSERT INTO task_remarks (task_id, remark, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$task_id, $remark, $user_id]);

            // Log remark in history
            $stmt = $pdo->prepare("INSERT INTO task_history (task_id, action, performed_by) VALUES (?, ?, ?)");
            $stmt->execute([$task_id, "Added remark: " . substr($remark, 0, 50) . (strlen($remark) > 50 ? '...' : ''), $user_id]);
        }

        $pdo->commit();
        header('Location: dashboard.php?success=Task updated successfully');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error updating task: " . $e->getMessage());
    }
}
$task_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$canUpdate = in_array('update_status', $rolePermissions[$role] ?? []);

if ($task_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch();

if (!$task || ($task['assigned_to'] != $user_id && !$canUpdate)) {
    die("Access denied! You can only update your own tasks or if authorized.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_status = $_POST['status'];
    $forward_to = $_POST['forward_to'] ?? null;

    if ($new_status === 'forward_to' && $forward_to) {
        // Forward task to another user
        $stmt = $pdo->prepare("UPDATE tasks SET assigned_to = ? WHERE id = ?");
        $stmt->execute([$forward_to, $task_id]);

        // Get new assignee name for history
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$forward_to]);
        $new_assignee_name = $stmt->fetchColumn();

        // Log history
        $stmt = $pdo->prepare("INSERT INTO task_history (task_id, action, performed_by) VALUES (?, ?, ?)");
        $stmt->execute([$task_id, "Task forwarded to $new_assignee_name", $user_id]);

        header('Location: dashboard.php?success=Task forwarded successfully');
    } else {
        // Regular status update
        $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $task_id]);

        // Log history
        $stmt = $pdo->prepare("INSERT INTO task_history (task_id, action, performed_by) VALUES (?, ?, ?)");
        $stmt->execute([$task_id, "Status changed to '$new_status'", $user_id]);

        header('Location: dashboard.php?success=Task status updated successfully');
    }
    exit;
}

// Get assignee info
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$task['assigned_to']]);
$assignee_name = $stmt->fetchColumn();

// Get creator info
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$task['created_by']]);
$creator_name = $stmt->fetchColumn();

// Get all users for forwarding dropdown (excluding current assignee)
$stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE id != ? ORDER BY role, full_name");
$stmt->execute([$task['assigned_to']]);
$users = $stmt->fetchAll();

// Get task history
$stmt = $pdo->prepare("SELECT h.*, u.full_name FROM task_history h JOIN users u ON h.performed_by = u.id WHERE task_id = ? ORDER BY performed_at DESC");
$stmt->execute([$task_id]);
$history = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Task Status - Task Tracker Pro</title>
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
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* Header Section */
        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="25" cy="25" r="3" fill="white" opacity="0.1"/><circle cx="75" cy="35" r="2" fill="white" opacity="0.1"/><circle cx="45" cy="75" r="1.5" fill="white" opacity="0.1"/><circle cx="85" cy="75" r="1" fill="white" opacity="0.1"/></svg>');
            pointer-events: none;
        }

        .header-content {
            position: relative;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-icon {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            flex-shrink: 0;
        }

        .header-text h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header-subtitle {
            font-size: 16px;
            opacity: 0.9;
        }

        /* Task Details Section */
        .task-details {
            padding: 40px;
            border-bottom: 1px solid #e2e8f0;
        }

        .task-title {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 20px;
            line-height: 1.3;
        }

        .task-description {
            color: #4a5568;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
            padding: 20px;
            background: #f7fafc;
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }

        .task-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .meta-item {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .meta-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .meta-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #718096;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .meta-value {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-current {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #fef5e7;
            color: #d69e2e;
            border: 2px solid #f6ad55;
        }

        .status-in_progress {
            background: #e6fffa;
            color: #319795;
            border: 2px solid #4fd1c7;
        }

        .status-completed {
            background: #f0fff4;
            color: #38a169;
            border: 2px solid #68d391;
        }

        .assignee-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .assignee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }

        /* Form Section */
        .form-section {
            padding: 40px;
        }

        .form-title {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-options {
            display: grid;
            gap: 15px;
            margin-bottom: 30px;
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
            gap: 15px;
            padding: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8fafc;
            font-weight: 500;
        }

        .status-option label:hover {
            border-color: #cbd5e0;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .status-option input:checked+label {
            border-color: #667eea;
            background: #f0f7ff;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: scale(1.02);
        }

        .status-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .status-option.pending .status-icon {
            background: #f6ad55;
        }

        .status-option.in-progress .status-icon {
            background: #4fd1c7;
        }

        .status-option.completed .status-icon {
            background: #68d391;
        }

        .status-option.forward .status-icon {
            background: #9f7aea;
        }

        .forward-user-select {
            margin-top: 15px;
            padding: 20px;
            background: #f7fafc;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            display: none;
        }

        .forward-user-select.show {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .forward-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 45px;
        }

        .forward-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Task History Section */
        .history-section {
            padding: 40px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        .history-title {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .history-timeline {
            position: relative;
            padding-left: 40px;
        }

        .history-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #667eea, #e2e8f0);
        }

        .history-item {
            position: relative;
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #667eea;
        }

        .history-item::before {
            content: '';
            position: absolute;
            left: -46px;
            top: 20px;
            width: 12px;
            height: 12px;
            background: #667eea;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }

        .history-action {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .history-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #718096;
            font-size: 14px;
        }

        .history-performer {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .history-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 11px;
        }

        .history-time {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .history-empty {
            text-align: center;
            padding: 40px 20px;
            color: #718096;
            font-style: italic;
        }

        .history-empty i {
            font-size: 48px;
            color: #e2e8f0;
            margin-bottom: 15px;
        }

        .status-text {
            flex: 1;
        }

        .status-name {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .status-description {
            font-size: 14px;
            color: #718096;
            line-height: 1.4;
        }

        /* Action Buttons */
        .form-actions {
            display: flex;
            gap: 15px;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
        }

        .btn {
            flex: 1;
            padding: 18px 30px;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
            border-color: #cbd5e0;
            transform: translateY(-2px);
        }

        /* Loading state */
        .btn-loading {
            opacity: 0.8;
            cursor: not-allowed;
            pointer-events: none;
        }

        .btn-loading::after {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Progress indicators */
        .progress-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f0f7ff;
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }

        .progress-steps {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }

        .progress-step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            color: white;
            position: relative;
        }

        .progress-step.completed {
            background: #48bb78;
        }

        .progress-step.current {
            background: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
        }

        .progress-step.pending {
            background: #cbd5e0;
            color: #718096;
        }

        .progress-step::after {
            content: '';
            position: absolute;
            right: -22px;
            top: 50%;
            transform: translateY(-50%);
            width: 15px;
            height: 2px;
            background: #cbd5e0;
        }

        .progress-step:last-child::after {
            display: none;
        }

        .progress-step.completed::after {
            background: #48bb78;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .container {
                border-radius: 15px;
                margin: 10px 0;
            }

            .header {
                padding: 30px 25px;
            }

            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .header h1 {
                font-size: 24px;
            }

            .task-details,
            .form-section {
                padding: 30px 25px;
            }

            .task-meta {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .form-actions {
                flex-direction: column;
                gap: 12px;
            }

            .btn {
                padding: 16px 24px;
            }

            .status-option label {
                padding: 15px;
                gap: 12px;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 25px 20px;
            }

            .task-details,
            .form-section {
                padding: 25px 20px;
            }

            .task-title {
                font-size: 20px;
            }

            .header h1 {
                font-size: 22px;
            }

            .progress-steps {
                justify-content: center;
            }
        }

        /* Accessibility */
        .btn:focus-visible {
            outline: 3px solid rgba(102, 126, 234, 0.5);
            outline-offset: 2px;
        }

        .status-option input:focus+label {
            outline: 3px solid rgba(102, 126, 234, 0.5);
            outline-offset: 2px;
        }

        /* Animation for status change */
        @keyframes statusChange {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        .status-change-animation {
            animation: statusChange 0.5s ease-in-out;
        }

        .remark-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
        }

        .remark-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .remark-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="header-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="header-text">
                    <h1>Update Task Status</h1>
                    <p class="header-subtitle">Change the progress status of your task</p>
                </div>
            </div>
        </div>

        <!-- Task Details -->
        <div class="task-details">
            <h2 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h2>

            <?php if (!empty($task['description'])): ?>
                <div class="task-description">
                    <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                </div>
            <?php endif; ?>
                                <div class="history-timeline">
    <?php 
    // Get remarks
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name 
        FROM task_remarks r 
        JOIN users u ON r.created_by = u.id 
        WHERE r.task_id = ? 
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$task_id]);
    $remarks = $stmt->fetchAll();

    // Display remarks in history
    foreach ($remarks as $remark): ?>
        <div class="history-item">
            <div class="history-action">
                <i class="fas fa-comment"></i> 
                <?php echo nl2br(htmlspecialchars($remark['remark'])); ?>
            </div>
            <div class="history-meta">
                <div class="history-performer">
                    <div class="history-avatar">
                        <?php echo strtoupper(substr($remark['full_name'], 0, 1)); ?>
                    </div>
                    <?php echo htmlspecialchars($remark['full_name']); ?>
                </div>
                <div class="history-time">
                    <i class="far fa-clock"></i>
                    <?php echo date('M j, Y g:i A', strtotime($remark['created_at'])); ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <?php foreach ($history as $item): ?>
        <!-- ... existing history items ... -->
    <?php endforeach; ?>
</div>

            <!-- Progress Indicator -->
            <div class="progress-indicator">
                <div class="progress-steps">
                    <div class="progress-step <?php echo $task['status'] === 'pending' ? 'current' : ($task['status'] === 'in_progress' || $task['status'] === 'completed' ? 'completed' : 'pending'); ?>">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="progress-step <?php echo $task['status'] === 'in_progress' ? 'current' : ($task['status'] === 'completed' ? 'completed' : 'pending'); ?>">
                        <i class="fas fa-spinner"></i>
                    </div>
                    <div class="progress-step <?php echo $task['status'] === 'completed' ? 'current completed' : 'pending'; ?>">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
                <div style="font-size: 14px; color: #718096; font-weight: 500;">
                    Task Progress
                </div>
            </div>

            <div class="task-meta">
                <div class="meta-item">
                    <div class="meta-label">Current Status</div>
                    <div class="meta-value">
                        <span class="status-current status-<?php echo $task['status']; ?>">
                            <i class="fas fa-<?php echo $task['status'] === 'pending' ? 'clock' : ($task['status'] === 'in_progress' ? 'spinner' : 'check-circle'); ?>"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                        </span>
                    </div>
                </div>

                <div class="meta-item">
                    <div class="meta-label">Assigned To</div>
                    <div class="meta-value">
                        <div class="assignee-info">
                            <div class="assignee-avatar">
                                <?php echo strtoupper(substr($assignee_name, 0, 1)); ?>
                            </div>
                            <?php echo htmlspecialchars($assignee_name); ?>
                        </div>
                    </div>
                </div>

                <div class="meta-item">
                    <div class="meta-label">Due Date</div>
                    <div class="meta-value">
                        <i class="fas fa-calendar-alt" style="color: <?php echo ($task['due_date'] < date('Y-m-d') && $task['status'] !== 'completed') ? '#e53e3e' : '#718096'; ?>;"></i>
                        <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                        <?php if ($task['due_date'] < date('Y-m-d') && $task['status'] !== 'completed'): ?>
                            <span style="color: #e53e3e; font-size: 12px; margin-left: 8px;">(Overdue)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="meta-item">
                    <div class="meta-label">Created By</div>
                    <div class="meta-value">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($creator_name); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Update Form -->
        <div class="form-section">
            <h3 class="form-title">
                <i class="fas fa-exchange-alt"></i>
                Change Status
            </h3>

            <form method="POST" id="updateStatusForm">
                <div class="status-options">
                    <div class="status-option pending">
                        <input type="radio" id="status_pending" name="status" value="pending"
                            <?php echo ($task['status'] === 'pending') ? 'checked' : ''; ?>>
                        <label for="status_pending">
                            <div class="status-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="status-text">
                                <div class="status-name">Pending</div>
                                <div class="status-description">Task is waiting to be started</div>
                            </div>
                        </label>
                    </div>

                    <div class="status-option in-progress">
                        <input type="radio" id="status_progress" name="status" value="in_progress"
                            <?php echo ($task['status'] === 'in_progress') ? 'checked' : ''; ?>>
                        <label for="status_progress">
                            <div class="status-icon">
                                <i class="fas fa-spinner"></i>
                            </div>
                            <div class="status-text">
                                <div class="status-name">In Progress</div>
                                <div class="status-description">Task is currently being worked on</div>
                            </div>
                        </label>
                    </div>

                    <div class="status-option completed">
                        <input type="radio" id="status_completed" name="status" value="completed"
                            <?php echo ($task['status'] === 'completed') ? 'checked' : ''; ?>>
                        <label for="status_completed">
                            <div class="status-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="status-text">
                                <div class="status-name">Completed</div>
                                <div class="status-description">Task has been finished successfully</div>
                            </div>
                        </label>
                    </div>

                    <div class="status-option forward">
                        <input type="radio" id="status_forward" name="status" value="forward_to">
                        <label for="status_forward">
                            <div class="status-icon">
                                <i class="fas fa-share"></i>
                            </div>
                            <div class="status-text">
                                <div class="status-name">Forward To</div>
                                <div class="status-description">Forward this task to another team member</div>
                            </div>
                        </label>
                        <div class="forward-user-select" id="forwardUserSelect">
                            <label for="forward_to" style="font-size: 14px; font-weight: 600; color: #4a5568; margin-bottom: 10px; display: block;">
                                Select team member:
                            </label>
                            <select name="forward_to" id="forward_to" class="forward-select">
                                <option value="">Choose a team member...</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['full_name']) . ' (' . ucfirst($user['role']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="remark-section">
                    <h3 class="form-title">
                        <i class="fas fa-comment"></i>
                        Add/Edit Remark
                    </h3>

                    <div class="remark-form">
                        <textarea
                            name="remark"
                            id="remark"
                            class="remark-input"
                            placeholder="Enter your remark here..."
                            rows="4"></textarea>
                    </div>
                </div>


                <div class="form-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                    <button type="submit" class="btn btn-primary" id="updateBtn" >
                        <i class="fas fa-save"></i>
                        Update Status
                    </button>
                </div>
            </form>
        </div>

        <!-- Task History -->
        <div class="history-section">
            <h3 class="history-title">
                <i class="fas fa-history"></i>
                Task History
            </h3>

            <?php if (empty($history)): ?>
                <div class="history-empty">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No history available for this task yet.</p>
                </div>
            <?php else: ?>
                <div class="history-timeline">

                    <?php foreach ($history as $item): ?>
                        <div class="history-item">
                            <div class="history-action"><?php echo htmlspecialchars($item['action']); ?></div>
                            <div class="history-meta">
                                <div class="history-performer">
                                    <div class="history-avatar">
                                        <?php echo strtoupper(substr($item['full_name'], 0, 1)); ?>
                                    </div>
                                    <span><?php echo htmlspecialchars($item['full_name']); ?></span>
                                </div>
                                <div class="history-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('M j, Y g:i A', strtotime($item['performed_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const form = document.getElementById('updateStatusForm');
        const updateBtn = document.getElementById('updateBtn');
        const currentStatus = '<?php echo $task['status']; ?>';

        // Enable/disable update button based on status change
        function checkStatusChange() {
            const selectedStatus = document.querySelector('input[name="status"]:checked')?.value;
            const forwardTo = document.getElementById('forward_to')?.value;
            const hasChanged = selectedStatus && selectedStatus !== currentStatus;
            const isForwardValid = selectedStatus !== 'forward_to' || forwardTo;

            updateBtn.disabled = !hasChanged || !isForwardValid;
            updateBtn.style.opacity = (hasChanged && isForwardValid) ? '1' : '0.6';

            if (hasChanged && isForwardValid) {
                if (selectedStatus === 'forward_to') {
                    const selectedUser = document.querySelector(`#forward_to option[value="${forwardTo}"]`)?.textContent;
                    updateBtn.innerHTML = '<i class="fas fa-share"></i> Forward to ' + (selectedUser?.split('(')[0].trim() || 'Selected User');
                } else {
                    updateBtn.innerHTML = '<i class="fas fa-save"></i> Update to ' +
                        document.querySelector(`label[for="status_${selectedStatus.replace('_', '')}"] .status-name`).textContent;
                }
            } else {
                updateBtn.innerHTML = '<i class="fas fa-save"></i> Update Status';
            }
        }

        // Show/hide forward user select
        function toggleForwardSelect() {
            const forwardRadio = document.getElementById('status_forward');
            const forwardSelect = document.getElementById('forwardUserSelect');

            if (forwardRadio && forwardRadio.checked) {
                forwardSelect.classList.add('show');
                document.getElementById('forward_to').focus();
            } else {
                forwardSelect.classList.remove('show');
                document.getElementById('forward_to').value = '';
            }
            checkStatusChange();
        }

        // Listen for status changes
        document.querySelectorAll('input[name="status"]').forEach(radio => {
            radio.addEventListener('change', function() {
                toggleForwardSelect();
                checkStatusChange();
            });
        });

        // Listen for forward user selection
        document.getElementById('forward_to').addEventListener('change', checkStatusChange);

        // Form submission with validation
        form.addEventListener('submit', function(e) {
            const selectedStatus = document.querySelector('input[name="status"]:checked')?.value;
            const forwardTo = document.getElementById('forward_to')?.value;

            if (!selectedStatus) {
                e.preventDefault();
                alert('Please select a status.');
                return false;
            }

            if (selectedStatus === currentStatus) {
                e.preventDefault();
                alert('Please select a different status to update.');
                return false;
            }

            if (selectedStatus === 'forward_to') {
                if (!forwardTo) {
                    e.preventDefault();
                    alert('Please select a team member to forward the task to.');
                    document.getElementById('forward_to').focus();
                    return false;
                }

                const selectedUserName = document.querySelector(`#forward_to option[value="${forwardTo}"]`)?.textContent.split('(')[0].trim();
                const confirmForward = confirm(`Are you sure you want to forward this task to ${selectedUserName}?`);
                if (!confirmForward) {
                    e.preventDefault();
                    return false;
                }
            }

            // Show loading state
            updateBtn.classList.add('btn-loading');
            updateBtn.disabled = true;
            updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' +
                (selectedStatus === 'forward_to' ? 'Forwarding...' : 'Updating...');

            // Add animation to the form
            document.querySelector('.status-options').classList.add('status-change-animation');
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape to go back
            if (e.key === 'Escape') {
                window.location.href = 'dashboard.php';
            }

            // Number keys to select status
            if (e.key === '1') {
                document.getElementById('status_pending').checked = true;
                toggleForwardSelect();
                checkStatusChange();
            } else if (e.key === '2') {
                document.getElementById('status_progress').checked = true;
                toggleForwardSelect();
                checkStatusChange();
            } else if (e.key === '3') {
                document.getElementById('status_completed').checked = true;
                toggleForwardSelect();
                checkStatusChange();
            } else if (e.key === '4') {
                document.getElementById('status_forward').checked = true;
                toggleForwardSelect();
                checkStatusChange();
            }

            // Enter to submit if status changed
            if (e.key === 'Enter' && !updateBtn.disabled) {
                form.submit();
            }
        });

        // Auto-focus and initial setup
        document.addEventListener('DOMContentLoaded', function() {
            const statusOptions = ['pending', 'in_progress', 'completed'];
            const nextStatus = statusOptions[statusOptions.indexOf(currentStatus) + 1] || statusOptions[0];

            if (nextStatus !== currentStatus) {
                const nextRadio = document.querySelector(`input[value="${nextStatus}"]`);
                if (nextRadio) {
                    nextRadio.focus();
                }
            }

            // Initial checks
            toggleForwardSelect();
            checkStatusChange();
        });

        // Smooth animations for radio button changes
        document.querySelectorAll('.status-option input').forEach(input => {
            input.addEventListener('change', function() {
                // Remove animation class from all options
                document.querySelectorAll('.status-option label').forEach(label => {
                    label.style.transition = 'all 0.3s ease';
                });

                // Add emphasis to selected option
                if (this.checked) {
                    this.nextElementSibling.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        this.nextElementSibling.style.transform = 'scale(1)';
                    }, 200);
                }
            });
        });

        // Confirmation for completing overdue tasks
        const completedRadio = document.getElementById('status_completed');
        if (completedRadio) {
            completedRadio.addEventListener('change', function() {
                if (this.checked) {
                    const isOverdue = <?php echo ($task['due_date'] < date('Y-m-d') && $task['status'] !== 'completed') ? 'true' : 'false'; ?>;
                    if (isOverdue) {
                        const confirmComplete = confirm('This task is overdue. Are you sure you want to mark it as completed?');
                        if (!confirmComplete) {
                            // Reset to previous status
                            document.querySelector(`input[value="${currentStatus}"]`).checked = true;
                            checkStatusChange();
                        }
                    }
                }
            });
        }
    </script>

    <script>
        
    </script>
</body>

</html>