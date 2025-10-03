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

// Fetch MY pending tasks (always visible)
$stmt = $pdo->prepare("SELECT t.*, u.full_name as assigned_name, v.full_name as creator_name 
                       FROM tasks t 
                       JOIN users u ON t.assigned_to = u.id 
                       JOIN users v ON t.created_by = v.id 
                       WHERE t.assigned_to = ? AND t.status = 'pending'
                       ORDER BY t.due_date ASC, t.created_at ASC");
$stmt->execute([$user_id]);
$my_pending_tasks = $stmt->fetchAll();

// Handle filters
$period_start = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
$period_end = $_GET['end'] ?? date('Y-m-d');
$filter_user = $_GET['user'] ?? ($canViewAll ? '' : $user_id);
$filter_status = $_GET['status'] ?? '';
$quick_filter = $_GET['quick'] ?? '';

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

if (!$canViewAll) {
    if ($filter_user == $user_id || empty($filter_user)) {
        $where .= " AND (t.assigned_to = ? OR t.created_by = ?)";
        $params[] = $user_id;
        $params[] = $user_id;
    } elseif ($filter_user) {
        $where .= " AND t.assigned_to = ?";
        $params[] = $filter_user;
    }
} elseif ($filter_user) {
    $where .= " AND t.assigned_to = ?";
    $params[] = $filter_user;
}

if ($filter_status) {
    $where .= " AND t.status = ?";
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
    $all_tasks = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Query error: " . $e->getMessage());
}

// Calculate statistics
$stats = [
    'total' => count($all_tasks),
    'pending' => count(array_filter($all_tasks, fn($t) => $t['status'] === 'pending')),
    'in_progress' => count(array_filter($all_tasks, fn($t) => $t['status'] === 'in_progress')),
    'completed' => count(array_filter($all_tasks, fn($t) => $t['status'] === 'completed')),
    'overdue' => count(array_filter($all_tasks, fn($t) => $t['due_date'] < date('Y-m-d') && $t['status'] !== 'completed'))
];

// Apply quick filter
$tasks = $all_tasks;
if ($quick_filter === 'pending') {
    $tasks = array_filter($all_tasks, fn($t) => $t['status'] === 'pending');
} elseif ($quick_filter === 'in_progress') {
    $tasks = array_filter($all_tasks, fn($t) => $t['status'] === 'in_progress');
} elseif ($quick_filter === 'completed') {
    $tasks = array_filter($all_tasks, fn($t) => $t['status'] === 'completed');
} elseif ($quick_filter === 'overdue') {
    $tasks = array_filter($all_tasks, fn($t) => $t['due_date'] < date('Y-m-d') && $t['status'] !== 'completed');
}

// Top handlers query
$week_start = date('Y-m-d', strtotime('-7 days'));
$week_end = date('Y-m-d');

$top_handlers_query = "
SELECT 
    u.id,
    u.full_name,
    u.role,
    u.email,
    (COUNT(t.id) + COALESCE((
        SELECT COUNT(*) 
        FROM task_history th 
        WHERE th.performed_by = u.id 
        AND th.action like '%forwarded%' 
        AND th.performed_at BETWEEN ? AND ?
    ), 0)) as completed_tasks,
    COUNT(DISTINCT DATE(t.updated_at)) as active_days,
    AVG(DATEDIFF(t.updated_at, t.created_at)) as avg_completion_time
FROM users u
INNER JOIN tasks t ON t.assigned_to = u.id
WHERE t.status = 'completed'
AND t.updated_at BETWEEN ? AND ?
GROUP BY u.id
HAVING completed_tasks > 0
ORDER BY completed_tasks DESC, avg_completion_time ASC
LIMIT 5
";

$stmt = $pdo->prepare($top_handlers_query);
$stmt->execute([$week_start, $week_end . ' 23:59:59', $week_start, $week_end . ' 23:59:59']);
$top_handlers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Task Tracker Pro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/jquery.dataTables.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>
    <link rel="icon" type="image/png" href="./assets/fav.png">
    <link rel="stylesheet" href="./assets/css/dashboard.css">

    <style>
/* My Pending Tasks Section */
.my-pending-section {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 10px;
    margin-bottom: 32px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    color: #111827;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.my-pending-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    cursor: pointer;
    user-select: none;
    position: relative;
    padding: 8px 0;
}

.my-pending-header.has-pending::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: #3b82f6;
    border-radius: 2px;
    animation: attentionPulse 2s ease-in-out infinite;
}

@keyframes attentionPulse {
    0%, 100% {
        opacity: 1;
        transform: scaleX(1);
    }
    50% {
        opacity: 0.5;
        transform: scaleX(1.05);
    }
}

.my-pending-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 20px;
    font-weight: 600;
    margin: 0;
    color: #111827;
}

.my-pending-title i {
    color: #6b7280;
}

.my-pending-badge {
    background: #f3f4f6;
    color: #374151;
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid #e5e7eb;
    transition: background 0.2s ease;
}

.my-pending-badge:hover {
    background: #e5e7eb;
}

.toggle-icon {
    font-size: 18px;
    color: #6b7280;
    transition: transform 0.3s ease;
}

.toggle-icon.expanded {
    transform: rotate(180deg);
}

.my-pending-content {
    display: none;
    animation: slideDown 0.3s ease;
}

.my-pending-content.show {
    display: block;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.pending-task-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.pending-task-card:hover {
    border-color: #d1d5db;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
}

.pending-task-card:last-child {
    margin-bottom: 0;
}

.pending-task-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.pending-task-title {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 4px 0;
    color: #111827;
}

.pending-task-description {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
    line-height: 1.5;
}

.pending-task-meta {
    display: flex;
    gap: 16px;
    align-items: center;
    margin-top: 12px;
    flex-wrap: wrap;
}

.pending-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #6b7280;
}

.pending-meta-item i {
    color: #9ca3af;
    font-size: 11px;
}

.pending-priority {
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #ffffff;
}

.pending-priority.high {
    background: #dc2626;
}

.pending-priority.medium {
    background: #ca8a04;
}

.pending-priority.low {
    background: #059669;
}

.pending-task-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}

.pending-action-btn {
    background: #f9fafb;
    color: #374151;
    border: 1px solid #d1d5db;
    padding: 8px 14px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.pending-action-btn:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
    transform: translateY(-1px);
}

.pending-action-btn i {
    font-size: 11px;
}

.empty-pending {
    text-align: center;
    padding: 32px 16px;
    color: #6b7280;
}

.empty-pending i {
    font-size: 48px;
    margin-bottom: 16px;
    color: #d1d5db;
}

.empty-pending h4 {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 600;
    color: #374151;
}

.empty-pending p {
    margin: 0;
    font-size: 14px;
    color: #6b7280;
}

/* Stat cards */
.stat-card {
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
}

.stat-card.active {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
    border-color: #3b82f6;
}

.stat-card.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: #3b82f6;
}

.stat-card.pending.active::after {
    background: #ca8a04;
}

.stat-card.in-progress.active::after {
    background: #3b82f6;
}

.stat-card.completed.active::after {
    background: #059669;
}

.stat-card.overdue.active::after {
    background: #dc2626;
}

.stat-card:hover {
    border-color: #d1d5db;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
}

.filter-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #f3f4f6;
    color: #6b7280;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    border: 1px solid #e5e7eb;
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
                             <a href="budget_cal.php" class="btn btn-secondary">
                        <i class="fa-solid fa-calculator"></i>
                        Budget Cal
                    </a>
                    <a href="profile.php" class="btn btn-secondary">
                        <i class="fas fa-user-alt"></i>
                        Profile
                    </a>
                    <a href="logout.php" class="btn btn-secondary">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>

<!-- My Pending Tasks Section -->
<div class="my-pending-section">
    <div class="my-pending-header <?php echo !empty($my_pending_tasks) ? 'has-pending' : ''; ?>" onclick="toggleMyPending()">
        <div style="display: flex; align-items: center; gap: 12px;">
            <h3 class="my-pending-title">
                <i class="fas fa-user-clock"></i>
                My Pending Tasks
            </h3>
            <span class="my-pending-badge"><?php echo count($my_pending_tasks); ?> Task<?php echo count($my_pending_tasks) != 1 ? 's' : ''; ?></span>
        </div>
        <i class="fas fa-chevron-down toggle-icon" id="toggleIcon"></i>
    </div>

    <div class="my-pending-content" id="myPendingContent">
        <?php if (empty($my_pending_tasks)): ?>
            <div class="empty-pending">
                <i class="fas fa-check-circle"></i>
                <h4>All Clear!</h4>
                <p>You have no pending tasks assigned to you.</p>
            </div>
        <?php else: ?>
            <?php foreach ($my_pending_tasks as $task): ?>
                <div class="pending-task-card">
                    <div class="pending-task-header">
                        <div style="flex: 1;">
                            <h4 class="pending-task-title"><?php echo htmlspecialchars($task['title']); ?></h4>
                            <p class="pending-task-description">
                                <?php echo htmlspecialchars(substr($task['description'], 0, 120)) . (strlen($task['description']) > 120 ? '...' : ''); ?>
                            </p>
                        </div>
                        <span class="pending-priority <?php echo strtolower($task['priority']); ?>">
                            <?php echo ucfirst($task['priority']); ?>
                        </span>
                    </div>

                    <div class="pending-task-meta">
                        <div class="pending-meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?></span>
                            <?php if ($task['due_date'] < date('Y-m-d')): ?>
                                <span style="color: #dc2626; font-weight: 600;">(Overdue)</span>
                            <?php endif; ?>
                        </div>
                        <div class="pending-meta-item">
                            <i class="fas fa-user"></i>
                            <span>Created by: <?php echo htmlspecialchars($task['creator_name']); ?></span>
                        </div>
                    </div>

                    <div class="pending-task-actions">
                        <a href="update_task.php?id=<?php echo $task['id']; ?>" class="pending-action-btn">
                            <i class="fas fa-edit"></i>
                            Update Status
                        </a>
                        <a href="javascript:void(0)" onclick="showTaskHistory(<?php echo $task['id']; ?>)" class="pending-action-btn">
                            <i class="fas fa-history"></i>
                            View History
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total <?php echo $quick_filter === '' ? 'active' : ''; ?>" onclick="filterByStatus('')">
                <?php if ($quick_filter === ''): ?>
                    <span class="filter-badge">Active</span>
                <?php endif; ?>
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>

            <div class="stat-card pending <?php echo $quick_filter === 'pending' ? 'active' : ''; ?>" onclick="filterByStatus('pending')">
                <?php if ($quick_filter === 'pending'): ?>
                    <span class="filter-badge">Active</span>
                <?php endif; ?>
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>

            <div class="stat-card in-progress <?php echo $quick_filter === 'in_progress' ? 'active' : ''; ?>" onclick="filterByStatus('in_progress')">
                <?php if ($quick_filter === 'in_progress'): ?>
                    <span class="filter-badge">Active</span>
                <?php endif; ?>
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-spinner"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                <div class="stat-label">In Progress</div>
            </div>

            <div class="stat-card completed <?php echo $quick_filter === 'completed' ? 'active' : ''; ?>" onclick="filterByStatus('completed')">
                <?php if ($quick_filter === 'completed'): ?>
                    <span class="filter-badge">Active</span>
                <?php endif; ?>
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Completed</div>
            </div>

            <div class="stat-card overdue <?php echo $quick_filter === 'overdue' ? 'active' : ''; ?>" onclick="filterByStatus('overdue')">
                <?php if ($quick_filter === 'overdue'): ?>
                    <span class="filter-badge">Active</span>
                <?php endif; ?>
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo $stats['overdue']; ?></div>
                <div class="stat-label">Overdue</div>
            </div>
        </div>

        <!-- Top Task Handlers Widget -->
        <?php if (count($top_handlers) > 0): ?>
        <div class="top-handlers-section">
            <div class="top-handlers-header">
                <h3 class="top-handlers-title">
                    <i class="fas fa-trophy"></i>
                    Top Task Handlers Last 7 Days
                </h3>
                <span class="week-range"><?php echo date('M j', strtotime($week_start)) . ' - ' . date('M j, Y', strtotime($week_end)); ?></span>
            </div>
            
            <div class="handlers-carousel-container">
                <div class="handlers-carousel">
                    <?php 
                    $rank = 1;
                    foreach ($top_handlers as $handler): 
                        $completion_time = round($handler['avg_completion_time'], 1);
                    ?>
                        <div class="handler-card rank-<?php echo $rank; ?>">
                            <div class="rank-badge">
                                <?php if ($rank == 1): ?>
                                    <i class="fas fa-crown"></i>
                                <?php else: ?>
                                    #<?php echo $rank; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="handler-avatar-large">
                                <?php echo strtoupper(substr($handler['full_name'], 0, 1)); ?>
                            </div>
                            
                            <div class="handler-info">
                                <div class="handler-name"><?php echo htmlspecialchars($handler['full_name']); ?></div>
                                <div class="handler-role"><?php echo htmlspecialchars($handler['role']); ?></div>
                            </div>
                            
                            <div class="handler-stats-mini">
                                <div class="mini-stat">
                                    <div class="mini-stat-icon"><i class="fas fa-check-double"></i></div>
                                    <div class="mini-stat-value"><?php echo $handler['completed_tasks']; ?></div>
                                    <div class="mini-stat-label">Completed</div>
                                </div>
                                <div class="mini-stat">
                                    <div class="mini-stat-icon"><i class="fas fa-calendar-check"></i></div>
                                    <div class="mini-stat-value"><?php echo $handler['active_days']; ?></div>
                                    <div class="mini-stat-label">Active Days</div>
                                </div>
                                <div class="mini-stat">
                                    <div class="mini-stat-icon"><i class="fas fa-clock"></i></div>
                                    <div class="mini-stat-value"><?php echo $completion_time; ?>d</div>
                                    <div class="mini-stat-label">Avg Time</div>
                                </div>
                            </div>
                            
                            <?php if ($rank == 1): ?>
                                <div class="top-performer-badge">
                                    <i class="fas fa-star"></i>
                                    Top Performer Last 7 Days
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php 
                        $rank++;
                    endforeach; 
                    ?>
                </div>
                
                <button class="carousel-nav prev" onclick="scrollHandlers('prev')">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="carousel-nav next" onclick="scrollHandlers('next')">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <div class="carousel-dots" id="carouselDots"></div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters-section">
            <h3 class="filters-title">
                <i class="fas fa-filter"></i>
                Filter Tasks
                <?php if ($quick_filter): ?>
                    <span style="background: #667eea; color: white; padding: 4px 12px; border-radius: 15px; font-size: 12px; margin-left: 10px;">
                        Showing: <?php echo ucfirst(str_replace('_', ' ', $quick_filter)); ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['quick' => ''])); ?>" 
                           style="color: white; margin-left: 5px; text-decoration: none;">×</a>
                    </span>
                <?php endif; ?>
            </h3>
            <form method="GET" class="filters-form" id="filterForm">
                <input type="hidden" name="quick" value="<?php echo htmlspecialchars($quick_filter); ?>">
                
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
                    <p>No <?php echo $quick_filter ? ucfirst(str_replace('_', ' ', $quick_filter)) : ''; ?> tasks match your current filters.</p>
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
        // Toggle My Pending Tasks section
        function toggleMyPending() {
            const content = document.getElementById('myPendingContent');
            const icon = document.getElementById('toggleIcon');
            
            if (content.classList.contains('show')) {
                content.classList.remove('show');
                icon.classList.remove('expanded');
                localStorage.setItem('myPendingExpanded', 'false');
            } else {
                content.classList.add('show');
                icon.classList.add('expanded');
                localStorage.setItem('myPendingExpanded', 'true');
            }
        }

        // Remember expanded state
        document.addEventListener('DOMContentLoaded', function() {
            const isExpanded = localStorage.getItem('myPendingExpanded');
            if (isExpanded === null || isExpanded === 'true') {
                document.getElementById('myPendingContent').classList.add('show');
                document.getElementById('toggleIcon').classList.add('expanded');
            }
        });

        // Function to filter by status when clicking stat cards
        function filterByStatus(status) {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (status) {
                urlParams.set('quick', status);
            } else {
                urlParams.delete('quick');
            }
            
            window.location.href = '?' + urlParams.toString();
        }

        $(document).ready(function() {
            // Initialize DataTables
            $('#tasksTable').DataTable({
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
                "order": [[6, "desc"]],
                "columnDefs": [
                    {"orderable": false, "targets": [7]},
                    {"width": "20%", "targets": [0]},
                    {"width": "12%", "targets": [1]},
                    {"width": "12%", "targets": [2]},
                    {"width": "8%", "targets": [3]},
                    {"width": "8%", "targets": [4]},
                    {"width": "10%", "targets": [5]},
                    {"width": "10%", "targets": [6]},
                    {"width": "20%", "targets": [7]}
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
                        clearTimeout(window.filterTimeout);
                        window.filterTimeout = setTimeout(() => {
                            document.getElementById('filterForm').submit();
                        }, 500);
                    });
                }
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
                    const cleanUrl = window.location.origin + window.location.pathname + (window.location.search.replace(/[?&]success=[^&]*/, '').replace(/^&/, '?') || '');
                    window.history.replaceState({}, document.title, cleanUrl);
                }, 5000);
            }
        });

        // Task History Functions
        function showTaskHistory(taskId) {
            document.getElementById('historyModal').style.display = 'block';

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

    <script>
        // Top Handlers Carousel
        let currentSlide = 0;
        const carousel = document.querySelector('.handlers-carousel');
        const cards = document.querySelectorAll('.handler-card');
        const dotsContainer = document.getElementById('carouselDots');

        // Create dots
        if (cards.length > 0) {
            cards.forEach((_, index) => {
                const dot = document.createElement('div');
                dot.className = 'carousel-dot' + (index === 0 ? ' active' : '');
                dot.onclick = () => goToSlide(index);
                dotsContainer.appendChild(dot);
            });
        }

        function scrollHandlers(direction) {
            const cardWidth = cards[0].offsetWidth + 20;
            
            if (direction === 'next') {
                currentSlide = (currentSlide + 1) % cards.length;
            } else {
                currentSlide = (currentSlide - 1 + cards.length) % cards.length;
            }
            
            goToSlide(currentSlide);
        }

        function goToSlide(index) {
            currentSlide = index;
            const cardWidth = cards[0].offsetWidth + 20;
            carousel.scrollLeft = cardWidth * index;
            
            // Update dots
            document.querySelectorAll('.carousel-dot').forEach((dot, i) => {
                dot.classList.toggle('active', i === index);
            });
        }

        // Auto-scroll every 5 seconds
        let autoScrollInterval = setInterval(() => {
            scrollHandlers('next');
        }, 5000);

        // Pause auto-scroll on hover
        if (carousel) {
            carousel.addEventListener('mouseenter', () => {
                clearInterval(autoScrollInterval);
            });

            carousel.addEventListener('mouseleave', () => {
                autoScrollInterval = setInterval(() => {
                    scrollHandlers('next');
                }, 5000);
            });

            // Update dots on manual scroll
            carousel.addEventListener('scroll', () => {
                const cardWidth = cards[0].offsetWidth + 20;
                const scrollPosition = carousel.scrollLeft;
                const newSlide = Math.round(scrollPosition / cardWidth);
                
                if (newSlide !== currentSlide) {
                    currentSlide = newSlide;
                    document.querySelectorAll('.carousel-dot').forEach((dot, i) => {
                        dot.classList.toggle('active', i === currentSlide);
                    });
                }
            });
        }
    </script>
</body>

</html>