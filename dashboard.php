<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$canViewAll = in_array('view_all', $rolePermissions[$role] ?? []);
$canViewTeam = in_array('view_team', $rolePermissions[$role] ?? []);

// Handle filters
$period_start = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
$period_end = $_GET['end'] ?? date('Y-m-d');
$filter_user = $_GET['user'] ?? ($canViewAll ? '' : $user_id);
$filter_status = $_GET['status'] ?? '';

// Fetch users for dropdown
$users = [];
if ($canViewAll || $canViewTeam) {
    $stmt = $pdo->prepare("SELECT id, full_name, role FROM users ORDER BY role, full_name");
    $stmt->execute();
    $users = $stmt->fetchAll();
}

// Build query conditions
$where = "t.created_at BETWEEN ? AND ?";
$params = [$period_start, $period_end . ' 23:59:59'];

if (!$canViewAll && $filter_user == $user_id) {
    $where .= " AND (assigned_to = ? OR created_by = ?)";
    $params[] = $user_id;
    $params[] = $user_id;
} elseif ($filter_user) {
    $where .= " AND assigned_to = ?";
    $params[] = $filter_user;
}

if ($filter_status) {
    $where .= " AND status = ?";
    $params[] = $filter_status;
}

// Fetch tasks
try {
    $stmt = $pdo->prepare("SELECT t.*, u.full_name as assigned_name, v.full_name as creator_name 
                           FROM tasks t 
                           JOIN users u ON t.assigned_to = u.id 
                           JOIN users v ON t.created_by = v.id 
                           WHERE $where 
                           ORDER BY t.created_at DESC");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Query error: " . $e->getMessage());
}

// Calculate statistics
$stats = [
    'total' => count($tasks),
    'pending' => count(array_filter($tasks, fn($t) => $t['status'] === 'pending')),
    'in_progress' => count(array_filter($tasks, fn($t) => $t['status'] === 'in_progress')),
    'completed' => count(array_filter($tasks, fn($t) => $t['status'] === 'completed')),
    'overdue' => count(array_filter($tasks, fn($t) => $t['due_date'] < date('Y-m-d') && $t['status'] !== 'completed'))
];

// Fetch history for selected task
$history = [];
if (isset($_GET['task_id'])) {
    $stmt = $pdo->prepare("SELECT h.*, u.full_name FROM task_history h JOIN users u ON h.performed_by = u.id WHERE task_id = ? ORDER BY performed_at DESC");
    $stmt->execute([$_GET['task_id']]);
    $history = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Task Tracker Pro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/jquery.dataTables.min.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>

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

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color, #667eea);
        }

        .stat-card.pending::before {
            background: #f6ad55;
        }

        .stat-card.in-progress::before {
            background: #4299e1;
        }

        .stat-card.completed::before {
            background: #48bb78;
        }

        .stat-card.overdue::before {
            background: #f56565;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .stat-card.pending .stat-icon {
            background: #f6ad55;
        }

        .stat-card.in-progress .stat-icon {
            background: #4299e1;
        }

        .stat-card.completed .stat-icon {
            background: #48bb78;
        }

        .stat-card.overdue .stat-icon {
            background: #f56565;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
        }

        .stat-label {
            color: #718096;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Filters */
        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .filters-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #2d3748;
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a5568;
            font-size: 14px;
        }

        .filter-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: #f7fafc;
            transition: all 0.3s ease;
        }

        .filter-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Tasks Table */
        .tasks-section {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .tasks-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tasks-title {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
        }

        .tasks-count {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .table-container {
            overflow-x: auto;
            padding: 20px;
        }

        /* Custom DataTables Styling */
        #tasksTable {
            width: 100% !important;
            border-collapse: collapse;
            background: white;
        }

        #tasksTable thead th {
            background: #f8fafc !important;
            padding: 15px 20px !important;
            text-align: left;
            font-weight: 600;
            color: #4a5568 !important;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0 !important;
            border-top: none !important;
        }

        #tasksTable tbody td {
            padding: 20px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            vertical-align: middle;
            border-top: none !important;
        }

        #tasksTable tbody tr:hover {
            background: #f8fafc !important;
        }

        /* DataTables Control Styling */
        .dataTables_wrapper {
            font-family: inherit;
        }

        .dataTables_length,
        .dataTables_filter,
        .dataTables_info,
        .dataTables_paginate {
            margin: 15px 0;
        }

        .dataTables_length label,
        .dataTables_filter label {
            font-weight: 600;
            color: #4a5568;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dataTables_length select,
        .dataTables_filter input {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: #f7fafc;
            transition: all 0.3s ease;
            margin-left: 10px;
        }

        .dataTables_filter input {
            width: 250px;
        }

        .dataTables_length select:focus,
        .dataTables_filter input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .dataTables_paginate {
            float: right;
        }

        .dataTables_paginate .paginate_button {
            padding: 8px 12px;
            margin: 0 2px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #4a5568 !important;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .dataTables_paginate .paginate_button:hover {
            background: #667eea !important;
            color: white !important;
            border-color: #667eea;
            transform: translateY(-1px);
        }

        .dataTables_paginate .paginate_button.current {
            background: #667eea !important;
            color: white !important;
            border-color: #667eea;
        }

        .dataTables_paginate .paginate_button.disabled {
            color: #a0aec0 !important;
            cursor: not-allowed;
        }

        .dataTables_info {
            color: #718096;
            font-size: 14px;
            font-weight: 500;
        }

        .dataTables_controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            padding: 0 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        /* Task Content Styling */
        .task-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .task-description {
            color: #718096;
            font-size: 14px;
            line-height: 1.4;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .status-pending {
            background: #fef5e7;
            color: #d69e2e;
            border: 1px solid #f6ad55;
        }

        .status-in_progress {
            background: #e6fffa;
            color: #319795;
            border: 1px solid #4fd1c7;
        }

        .status-completed {
            background: #f0fff4;
            color: #38a169;
            border: 1px solid #68d391;
        }

        .priority-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .priority-high {
            background: #fed7d7;
            color: #c53030;
        }

        .priority-medium {
            background: #faf089;
            color: #d69e2e;
        }

        .priority-low {
            background: #c6f6d5;
            color: #38a169;
        }

        .task-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .action-btn-primary {
            background: #667eea;
            color: white;
        }

        .action-btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        .due-date {
            font-size: 14px;
            color: #4a5568;
            white-space: nowrap;
        }

        .due-date.overdue {
            color: #e53e3e;
            font-weight: 600;
        }

        .assignee-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .assignee-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 12px;
            flex-shrink: 0;
        }

        .assignee-details {
            flex: 1;
            min-width: 0;
        }

        .assignee-name {
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
        }

        .assignee-role {
            color: #718096;
            font-size: 12px;
            text-transform: capitalize;
        }

        .creator-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .creator-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #48bb78, #38a169);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 11px;
            flex-shrink: 0;
        }

        .creator-name {
            font-weight: 500;
            color: #2d3748;
            font-size: 13px;
        }

        /* History Modal */
        .history-section {
            background: #f8fafc;
            padding: 20px;
            margin: 0 -20px -20px;
            border-top: 1px solid #e2e8f0;
        }

        .history-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 15px;
        }

        .history-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }

        .history-action {
            color: #2d3748;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .history-meta {
            color: #718096;
            font-size: 12px;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .filters-form {
                grid-template-columns: 1fr 1fr;
            }

            .dataTables_controls {
                flex-direction: column;
                gap: 15px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header,
            .filters-section,
            .tasks-section {
                padding: 20px 15px;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
                justify-content: stretch;
            }

            .btn {
                flex: 1;
                justify-content: center;
            }

            .filters-form {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .table-container {
                padding: 10px;
            }

            .dataTables_filter input {
                width: 100%;
            }

            .task-actions {
                flex-direction: column;
                gap: 5px;
            }
        }

        /* Loading States */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 50px;
            color: #718096;
        }

        .spinner {
            width: 32px;
            height: 32px;
            border: 3px solid #e2e8f0;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 15px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Empty States */
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
            font-size: 18px;
            color: #4a5568;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1>Task Dashboard</h1>
                    <p class="header-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo ucfirst($role); ?>)</p>
                </div>
                <div class="header-actions">
                    <?php if (in_array('create', $rolePermissions[$role] ?? [])): ?>
                        <a href="create_task.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            New Task
                        </a>
                    <?php endif; ?>
                    <a href="projects.php" class="btn btn-secondary">
                        <i class="fas fa-file-alt"></i>
                        Projects
                    </a>
                    <a href="logout.php" class="btn btn-secondary">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>

            <div class="stat-card pending">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>

            <div class="stat-card in-progress">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-spinner"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                <div class="stat-label">In Progress</div>
            </div>

            <div class="stat-card completed">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Completed</div>
            </div>

            <div class="stat-card overdue">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo $stats['overdue']; ?></div>
                <div class="stat-label">Overdue</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <h3 class="filters-title">
                <i class="fas fa-filter"></i>
                Filter Tasks
            </h3>
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="start">Start Date</label>
                    <input type="date" id="start" name="start" value="<?php echo $period_start; ?>" class="filter-input">
                </div>

                <div class="filter-group">
                    <label for="end">End Date</label>
                    <input type="date" id="end" name="end" value="<?php echo $period_end; ?>" class="filter-input">
                </div>

                <?php if ($canViewAll || $canViewTeam): ?>
                    <div class="filter-group">
                        <label for="user">Assigned To</label>
                        <select id="user" name="user" class="filter-input">
                            <option value="">All Users</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo ($filter_user == $u['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['full_name']) . ' (' . ucfirst($u['role']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="filter-input">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="in_progress" <?php echo ($filter_status == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo ($filter_status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>

                <div class="filter-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-search"></i>
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Tasks Table -->
        <div class="tasks-section">
            <div class="tasks-header">
                <h3 class="tasks-title">Tasks</h3>
                <span class="tasks-count"><?php echo count($tasks); ?> tasks</span>
            </div>

            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No tasks found</h3>
                    <p>No tasks match your current filters. Try adjusting your search criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table id="tasksTable" class="display">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Assigned To</th>
                                <th>Created By</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Due Date</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td>
                                        <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <div class="task-description">
                                            <?php echo htmlspecialchars(substr($task['description'], 0, 80)) . (strlen($task['description']) > 80 ? '...' : ''); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="assignee-info">
                                            <div class="assignee-avatar">
                                                <?php echo strtoupper(substr($task['assigned_name'], 0, 1)); ?>
                                            </div>
                                            <div class="assignee-details">
                                                <div class="assignee-name"><?php echo htmlspecialchars($task['assigned_name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="creator-info">
                                            <div class="creator-avatar">
                                                <?php echo strtoupper(substr($task['creator_name'], 0, 1)); ?>
                                            </div>
                                            <div class="creator-name"><?php echo htmlspecialchars($task['creator_name']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $task['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="priority-badge priority-<?php echo $task['priority'] ?? 'medium'; ?>">
                                            <?php echo ucfirst($task['priority'] ?? 'Medium'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="due-date <?php echo ($task['due_date'] < date('Y-m-d') && $task['status'] !== 'completed') ? 'overdue' : ''; ?>">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($task['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="task-actions">
                                            <?php if ($task['assigned_to'] == $user_id || in_array('update_status', $rolePermissions[$role] ?? [])): ?>
                                                <a href="update_task.php?id=<?php echo $task['id']; ?>" class="action-btn action-btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                    Update
                                                </a>
                                            <?php endif; ?>
                                            <?php if (in_array('reassign', $rolePermissions[$role] ?? [])): ?>
                                                <a href="reassign_task.php?id=<?php echo $task['id']; ?>" class="action-btn action-btn-secondary">
                                                    <i class="fas fa-user-edit"></i>
                                                    Reassign
                                                </a>
                                            <?php endif; ?>
                                            <a href="javascript:void(0)" onclick="showTaskHistory(<?php echo $task['id']; ?>)" class="action-btn action-btn-secondary">
                                                <i class="fas fa-history"></i>
                                                History
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Task History Modal -->
        <div id="historyModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 16px; width: 90%; max-width: 600px; max-height: 80vh; overflow-y: auto;">
                <div style="padding: 25px; border-bottom: 1px solid #e2e8f0;">
                    <h3 style="margin: 0; color: #2d3748; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-history"></i>
                        Task History
                    </h3>
                    <button onclick="closeHistoryModal()" style="position: absolute; top: 25px; right: 25px; background: none; border: none; font-size: 24px; color: #718096; cursor: pointer;">×</button>
                </div>
                <div id="historyContent" style="padding: 25px;">
                    <!-- History content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#tasksTable').DataTable({
                "pageLength": 10,
                "lengthMenu": [
                    [10, 25, 50, 100],
                    [10, 25, 50, 100]
                ],
                "order": [
                    [6, "desc"]
                ], // Order by Created date descending
                "columnDefs": [{
                        "orderable": false,
                        "targets": [7]
                    }, // Disable ordering on Actions column
                    {
                        "width": "20%",
                        "targets": [0]
                    }, // Task column width
                    {
                        "width": "12%",
                        "targets": [1]
                    }, // Assigned To column width
                    {
                        "width": "12%",
                        "targets": [2]
                    }, // Created By column width
                    {
                        "width": "8%",
                        "targets": [3]
                    }, // Status column width
                    {
                        "width": "8%",
                        "targets": [4]
                    }, // Priority column width
                    {
                        "width": "10%",
                        "targets": [5]
                    }, // Due Date column width
                    {
                        "width": "10%",
                        "targets": [6]
                    }, // Created column width
                    {
                        "width": "20%",
                        "targets": [7]
                    } // Actions column width
                ],
                "responsive": true,
                "language": {
                    "search": "Search tasks:",
                    "lengthMenu": "Show _MENU_ tasks per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ tasks",
                    "infoEmpty": "No tasks available",
                    "infoFiltered": "(filtered from _MAX_ total tasks)",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    }
                },
                "dom": '<"dataTables_controls"lf>t<"bottom"ip><"clear">'
            });

            // Auto-submit filters on change
            document.querySelectorAll('.filter-input').forEach(input => {
                if (input.type !== 'submit') {
                    input.addEventListener('change', function() {
                        // Add slight delay to allow multiple quick changes
                        clearTimeout(window.filterTimeout);
                        window.filterTimeout = setTimeout(() => {
                            this.form.submit();
                        }, 500);
                    });
                }
            });

            // Add loading states to action buttons
            document.querySelectorAll('.action-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (this.href && !this.href.includes('javascript') && !this.onclick) {
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                        this.style.pointerEvents = 'none';
                    }
                });
            });

            // Success message handling
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success')) {
                const message = urlParams.get('success');
                const alert = document.createElement('div');
                alert.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #48bb78;
                    color: white;
                    padding: 15px 20px;
                    border-radius: 10px;
                    box-shadow: 0 5px 15px rgba(72, 187, 120, 0.3);
                    z-index: 1000;
                    font-weight: 600;
                `;
                alert.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
                document.body.appendChild(alert);

                setTimeout(() => {
                    alert.remove();
                    // Clean URL without page refresh
                    const cleanUrl = window.location.origin + window.location.pathname + (window.location.search.replace(/[?&]success=[^&]*/, '').replace(/^&/, '?') || '');
                    window.history.replaceState({}, document.title, cleanUrl);
                }, 5000);
            }
        });

        // Task History Functions
        function showTaskHistory(taskId) {
            // Show modal
            document.getElementById('historyModal').style.display = 'block';

            // Load history content
            fetch(`get_task_history.php?task_id=${taskId}`)
                .then(response => response.json())
                .then(data => {
                    const historyContent = document.getElementById('historyContent');
                    if (data.success && data.history.length > 0) {
                        let html = '';
                        data.history.forEach(item => {
                            html += `
                                <div class="history-item">
                                    <div class="history-action">${item.action}</div>
                                    <div class="history-meta">
                                        by ${item.full_name} • ${new Date(item.performed_at).toLocaleDateString('en-US', {
                                            month: 'short',
                                            day: 'numeric',
                                            year: 'numeric',
                                            hour: 'numeric',
                                            minute: '2-digit'
                                        })}
                                    </div>
                                </div>
                            `;
                        });
                        historyContent.innerHTML = html;
                    } else {
                        historyContent.innerHTML = '<p style="color: #718096; font-style: italic; text-align: center; padding: 20px;">No history available for this task.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching history:', error);
                    document.getElementById('historyContent').innerHTML = '<p style="color: #f56565; text-align: center; padding: 20px;">Error loading history. Please try again.</p>';
                });
        }

        function closeHistoryModal() {
            document.getElementById('historyModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('historyModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeHistoryModal();
            }
        });
    </script>
</body>

</html>
<?php
// include('./chat_api.php')
?>