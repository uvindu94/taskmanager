<?php
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

if (!$task) {
    die("Task not found!");
}

$canUpdate = in_array('update_status', $rolePermissions[$role] ?? []);

if ($task['assigned_to'] != $user_id && !$canUpdate) {
    die("Access denied! You can only update your own tasks or if authorized.");
}

// Handle the POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_status = $_POST['status'] ?? null;
    $forward_to = $_POST['forward_to'] ?? null;
    $remark = trim($_POST['remark'] ?? '');
    $new_due_date = $_POST['due_date'] ?? null;
    $old_due_date = $task['due_date'];

    // Start transaction
    $pdo->beginTransaction();

    try {
        $status_changed = false;
        $due_date_changed = false;
        $task_forwarded = false;
        
        // Handle task forwarding
        if ($new_status === 'forward_to' && $forward_to) {
            $stmt = $pdo->prepare("UPDATE tasks SET assigned_to = ? WHERE id = ?");
            $stmt->execute([$forward_to, $task_id]);

            // Get new assignee name for history
            $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmt->execute([$forward_to]);
            $new_assignee_name = $stmt->fetchColumn();

            // Log history
            $stmt = $pdo->prepare("INSERT INTO task_history (task_id, action, performed_by) VALUES (?, ?, ?)");
            $stmt->execute([$task_id, "Task forwarded to $new_assignee_name", $user_id]);
            
            $task_forwarded = true;
        } 
        // Handle regular status update (only if status actually changed)
        elseif ($new_status && $new_status !== $task['status'] && $new_status !== 'forward_to') {
            $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $task_id]);

            // Log history
            $stmt = $pdo->prepare("INSERT INTO task_history (task_id, action, performed_by) VALUES (?, ?, ?)");
            $stmt->execute([$task_id, "Status changed from '{$task['status']}' to '$new_status'", $user_id]);
            
            $status_changed = true;
        }

        // Handle due date change
        if ($new_due_date && $new_due_date !== $old_due_date) {
            $stmt = $pdo->prepare("UPDATE tasks SET due_date = ? WHERE id = ?");
            $stmt->execute([$new_due_date, $task_id]);

            // Log history
            $stmt = $pdo->prepare("INSERT INTO task_history (task_id, action, performed_by) VALUES (?, ?, ?)");
            $old_date_formatted = date('M j, Y', strtotime($old_due_date));
            $new_date_formatted = date('M j, Y', strtotime($new_due_date));
            $stmt->execute([$task_id, "Due date changed from $old_date_formatted to $new_date_formatted", $user_id]);
            
            $due_date_changed = true;
        }

        // Insert remark if provided
        if (!empty($remark)) {
            $stmt = $pdo->prepare("INSERT INTO task_remarks (task_id, remark, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$task_id, $remark, $user_id]);

            // Log remark in history
            $stmt = $pdo->prepare("INSERT INTO task_history (task_id, action, performed_by) VALUES (?, ?, ?)");
            $remark_preview = substr($remark, 0, 50) . (strlen($remark) > 50 ? '...' : '');
            $stmt->execute([$task_id, "Added remark: " . $remark_preview, $user_id]);
        }

        // Determine success message
        $success_parts = [];
        if ($task_forwarded) {
            $success_parts[] = "Task forwarded";
        }
        if ($status_changed) {
            $success_parts[] = "Status updated";
        }
        if ($due_date_changed) {
            $success_parts[] = "Due date updated";
        }
        if (!empty($remark)) {
            $success_parts[] = "Remark added";
        }

        $success_message = !empty($success_parts) 
            ? implode(', ', $success_parts) . ' successfully'
            : 'Task updated successfully';

        $pdo->commit();
        header('Location: dashboard.php?success=' . urlencode($success_message));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error updating task: " . $e->getMessage());
    }
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Task Status - Task Tracker Pro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="./assets/fav.png">
    <link rel="stylesheet" href="./assets/css/update_task.css">
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
                    <h1>Update Task</h1>
                    <p class="header-subtitle">Update status, add remarks, or change due date</p>
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
                Change Status (Optional)
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

                <!-- Due Date Change Section -->
                <div class="remark-section" style="margin-top: 20px;">
                    <h3 class="form-title">
                        <i class="fas fa-calendar-alt"></i>
                        Change Due Date (Optional)
                    </h3>
                    <div class="remark-form">
                        <input 
                            type="date" 
                            name="due_date" 
                            id="due_date" 
                            value="<?php echo $task['due_date']; ?>"
                            class="filter-input"
                            style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                        <small style="color: #718096; margin-top: 5px; display: block;">
                            Current due date: <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                        </small>
                    </div>
                </div>

                <!-- Remark Section -->
                <div class="remark-section">
                    <h3 class="form-title">
                        <i class="fas fa-comment"></i>
                        Add Remark (Optional)
                    </h3>

                    <div class="remark-form">
                        <textarea
                            name="remark"
                            id="remark"
                            class="remark-input"
                            placeholder="Enter your remark here... (You can update without changing status)"
                            rows="4"></textarea>
                        <small style="color: #718096; margin-top: 5px; display: block;">
                            You can add a remark and/or change due date without changing the task status
                        </small>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                    <button type="submit" class="btn btn-primary" id="updateBtn">
                        <i class="fas fa-save"></i>
                        Update Task
                    </button>
                </div>
            </form>
        </div>

        <!-- Task History -->
        <div class="history-section">
            <h3 class="history-title">
                <i class="fas fa-history"></i>
                Task History & Remarks
            </h3>

            <?php if (empty($history) && empty($remarks)): ?>
                <div class="history-empty">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No history available for this task yet.</p>
                </div>
            <?php else: ?>
                <div class="history-timeline">
                    <?php 
                    // Combine history and remarks, then sort by date
                    $combined = [];
                    
                    foreach ($remarks as $remark) {
                        $combined[] = [
                            'type' => 'remark',
                            'data' => $remark,
                            'timestamp' => strtotime($remark['created_at'])
                        ];
                    }
                    
                    foreach ($history as $item) {
                        $combined[] = [
                            'type' => 'history',
                            'data' => $item,
                            'timestamp' => strtotime($item['performed_at'])
                        ];
                    }
                    
                    // Sort by timestamp descending
                    usort($combined, function($a, $b) {
                        return $b['timestamp'] - $a['timestamp'];
                    });
                    
                    foreach ($combined as $entry):
                        if ($entry['type'] === 'remark'):
                            $remark = $entry['data'];
                    ?>
                        <div class="history-item" style="border-left-color: #48bb78;">
                            <div class="history-action">
                                <i class="fas fa-comment" style="color: #48bb78;"></i> 
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
                    <?php else:
                            $item = $entry['data'];
                    ?>
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
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const form = document.getElementById('updateStatusForm');
        const updateBtn = document.getElementById('updateBtn');
        const currentStatus = '<?php echo $task['status']; ?>';
        const currentDueDate = '<?php echo $task['due_date']; ?>';

        // Enable update button if any change is made
        function checkForChanges() {
            const selectedStatus = document.querySelector('input[name="status"]:checked')?.value;
            const forwardTo = document.getElementById('forward_to')?.value;
            const remark = document.getElementById('remark')?.value.trim();
            const newDueDate = document.getElementById('due_date')?.value;

            const statusChanged = selectedStatus && selectedStatus !== currentStatus;
            const dueDateChanged = newDueDate && newDueDate !== currentDueDate;
            const hasRemark = remark.length > 0;
            const isForwardValid = selectedStatus !== 'forward_to' || forwardTo;

            // Enable button if ANY change is made
            const hasChanges = statusChanged || dueDateChanged || hasRemark;
            
            updateBtn.disabled = !hasChanges || !isForwardValid;
            updateBtn.style.opacity = (hasChanges && isForwardValid) ? '1' : '0.6';

            // Update button text
            if (hasChanges && isForwardValid) {
                let actions = [];
                if (statusChanged && selectedStatus === 'forward_to') {
                    const selectedUser = document.querySelector(`#forward_to option[value="${forwardTo}"]`)?.textContent;
                    actions.push('Forward to ' + (selectedUser?.split('(')[0].trim() || 'User'));
                } else if (statusChanged) {
                    actions.push('Update Status');
                }
                if (dueDateChanged) {
                    actions.push('Change Due Date');
                }
                if (hasRemark) {
                    actions.push('Add Remark');
                }
                updateBtn.innerHTML = '<i class="fas fa-save"></i> ' + actions.join(' & ');
            } else {
                updateBtn.innerHTML = '<i class="fas fa-save"></i> Update Task';
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
            checkForChanges();
        }

        // Listen for all changes
        document.querySelectorAll('input[name="status"]').forEach(radio => {
            radio.addEventListener('change', function() {
                toggleForwardSelect();
                checkForChanges();
            });
        });

        document.getElementById('forward_to').addEventListener('change', checkForChanges);
        document.getElementById('remark').addEventListener('input', checkForChanges);
        document.getElementById('due_date').addEventListener('change', checkForChanges);

        // Form submission with validation
        form.addEventListener('submit', function(e) {
            const selectedStatus = document.querySelector('input[name="status"]:checked')?.value;
            const forwardTo = document.getElementById('forward_to')?.value;
            const remark = document.getElementById('remark')?.value.trim();
            const newDueDate = document.getElementById('due_date')?.value;

            const statusChanged = selectedStatus && selectedStatus !== currentStatus;
            const dueDateChanged = newDueDate && newDueDate !== currentDueDate;
            const hasRemark = remark.length > 0;

            // Check if any changes were made
            if (!statusChanged && !dueDateChanged && !hasRemark) {
                e.preventDefault();
                alert('Please make at least one change: update status, change due date, or add a remark.');
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
            updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        });

        // Initial setup
        document.addEventListener('DOMContentLoaded', function() {
            toggleForwardSelect();
            checkForChanges();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.location.href = 'dashboard.php';
            }
        });
    </script>
</body>

</html>