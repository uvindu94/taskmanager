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
    <link rel="icon" type="image/png" href="./assets/fav.png">
    <link rel="stylesheet" href="./assets/css/dashboard.css">

    <style>
    
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

        <!-- best task handlers  -->
         <?php
// Fetch top task handlers of the week (last 7 days)
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
$stmt->execute([$week_start, $week_end . ' 23:59:59',$week_start, $week_end . ' 23:59:59']);
$top_handlers = $stmt->fetchAll();
?>

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
        <!-- best task handlers  -->

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
    const cardWidth = cards[0].offsetWidth + 20; // card width + gap
    
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
<?php
// include('./chat_api.php')
?>