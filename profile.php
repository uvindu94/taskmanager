<?php
// profile.php - User Profile and Statistics
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle profile update
if (isset($_POST['update_profile'])) {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($username) || empty($full_name)) {
        $error = "Username and full name are required.";
    } else {
        // Check if username is already taken by another user
        $query = "SELECT id FROM users WHERE username = :username AND id != :user_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = "Username is already taken by another user.";
        } else {
            // Update basic info
            $query = "UPDATE users SET username = :username, full_name = :full_name WHERE id = :user_id";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['username'] = $username;
                $success = "Profile updated successfully!";
                
                // Handle password change if provided
                if (!empty($current_password) && !empty($new_password)) {
                    // Verify current password
                    $query = "SELECT password FROM users WHERE id = :user_id";
                    $stmt = $pdo->prepare($query);
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->execute();
                    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (password_verify($current_password, $user_data['password'])) {
                        if ($new_password === $confirm_password) {
                            if (strlen($new_password) >= 6) {
                                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $query = "UPDATE users SET password = :password WHERE id = :user_id";
                                $stmt = $pdo->prepare($query);
                                $stmt->bindParam(':password', $hashed_password);
                                $stmt->bindParam(':user_id', $user_id);
                                $stmt->execute();
                                $success .= " Password changed successfully!";
                            } else {
                                $error = "New password must be at least 6 characters long.";
                            }
                        } else {
                            $error = "New passwords do not match.";
                        }
                    } else {
                        $error = "Current password is incorrect.";
                    }
                }
            } else {
                $error = "Failed to update profile.";
            }
        }
    }
}

// Fetch user information
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch task statistics
$query = "SELECT 
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
FROM tasks WHERE assigned_to = :user_id";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$task_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch tasks created by user
$query = "SELECT COUNT(*) as tasks_created FROM tasks WHERE created_by = :user_id";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$created_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch overdue tasks
$query = "SELECT COUNT(*) as overdue_tasks 
FROM tasks 
WHERE assigned_to = :user_id 
AND due_date < CURDATE() 
AND status != 'completed'";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$overdue_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch upcoming tasks (due in next 7 days)
$query = "SELECT COUNT(*) as upcoming_tasks 
FROM tasks 
WHERE assigned_to = :user_id 
AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
AND status != 'completed'";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$upcoming_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch project statistics
$query = "SELECT COUNT(*) as total_projects FROM projects WHERE created_by = :user_id";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$project_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch recent activities
$query = "SELECT th.*, t.title as task_title 
FROM task_history th
LEFT JOIN tasks t ON th.task_id = t.id
WHERE th.performed_by = :user_id
ORDER BY th.performed_at DESC
LIMIT 10";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch task remarks count
$query = "SELECT COUNT(*) as remarks_count FROM task_remarks WHERE created_by = :user_id";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$remarks_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch projects user is working on (as sales officer or creator)
$query = "SELECT COUNT(*) as active_projects 
FROM projects 
WHERE (created_by = :user_id OR sales_officer LIKE CONCAT('%', (SELECT username FROM users WHERE id = :user_id2), '%'))
AND status != 'completed'";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':user_id2', $user_id);
$stmt->execute();
$active_projects = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate completion rate
$completion_rate = $task_stats['total_tasks'] > 0 
    ? round(($task_stats['completed_tasks'] / $task_stats['total_tasks']) * 100, 1) 
    : 0;

// Calculate member since days
$member_since = new DateTime($user['created_at']);
$today = new DateTime();
$days_member = $today->diff($member_since)->days;

// Get user permissions
$user_permissions = isset($rolePermissions[$user['role']]) ? $rolePermissions[$user['role']] : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo htmlspecialchars($user['full_name']); ?></title>
        <link rel="icon" type="image/png" href="./assets/fav.png">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #333;
            font-size: 24px;
        }
        
        .btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
            display: inline-block;
        }
        
        .btn:hover {
            background: #5568d3;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .profile-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .profile-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 48px;
            color: white;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .profile-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .profile-role {
            color: #667eea;
            font-size: 16px;
            font-weight: 500;
        }
        
        .profile-info {
            margin-top: 20px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            color: #333;
            font-weight: 600;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        
        .stat-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-card.primary .stat-value { color: #667eea; }
        .stat-card.success .stat-value { color: #10b981; }
        .stat-card.warning .stat-value { color: #f59e0b; }
        .stat-card.danger .stat-value { color: #ef4444; }
        .stat-card.info .stat-value { color: #3b82f6; }
        
        .content-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group input:disabled {
            background: #f9fafb;
            cursor: not-allowed;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .password-section {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .password-section h4 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            padding: 15px;
            border-left: 3px solid #667eea;
            margin-bottom: 15px;
            background: #f9fafb;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .activity-item:hover {
            background: #f3f4f6;
        }
        
        .activity-action {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .activity-details {
            font-size: 14px;
            color: #666;
            margin-bottom: 3px;
        }
        
        .activity-time {
            font-size: 12px;
            color: #999;
        }
        
        .progress-bar {
            width: 100%;
            height: 25px;
            background: #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 10px;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
            transition: width 0.5s ease;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 5px;
        }
        
        .badge-primary { background: #dbeafe; color: #1e40af; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        
        .permissions-box {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .permissions-box h4 {
            margin-bottom: 10px;
            color: #333;
            font-size: 14px;
        }
        
        .permission-tag {
            display: inline-block;
            padding: 4px 10px;
            background: #667eea;
            color: white;
            border-radius: 15px;
            font-size: 11px;
            margin: 3px;
        }
        
        @media (max-width: 968px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Profile</h1>
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✓ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">✗ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="profile-grid">
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div class="profile-role"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
                
                <div class="profile-info">
                    <div class="info-item">
                        <span class="info-label">Username</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Member Since</span>
                        <span class="info-value"><?php echo $days_member; ?> days</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Joined</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                    </div>
                </div>
                
                <div style="margin-top: 25px;">
                    <div style="font-weight: 600; margin-bottom: 8px; color: #333;">Task Completion Rate</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $completion_rate; ?>%;">
                            <?php echo $completion_rate; ?>%
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 25px;">
                    <div style="font-weight: 600; margin-bottom: 10px; color: #333;">Quick Stats</div>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <span class="badge badge-primary">Projects: <?php echo $project_stats['total_projects']; ?></span>
                        <span class="badge badge-success">Tasks Created: <?php echo $created_stats['tasks_created']; ?></span>
                        <span class="badge badge-warning">Remarks: <?php echo $remarks_stats['remarks_count']; ?></span>
                        <span class="badge badge-primary">Active Projects: <?php echo $active_projects['active_projects']; ?></span>
                    </div>
                </div>
                
                <?php if (!empty($user_permissions)): ?>
                <div class="permissions-box">
                    <h4>Your Permissions</h4>
                    <div>
                        <?php foreach ($user_permissions as $permission): ?>
                            <span class="permission-tag"><?php echo str_replace('_', ' ', ucfirst($permission)); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="content-section">
                <h2 class="section-title">Edit Profile Information</h2>
                
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            <small style="color: #666; display: block; margin-top: 5px;">Email cannot be changed</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Role</label>
                            <input type="text" id="role" value="<?php echo htmlspecialchars($user['role']); ?>" disabled>
                            <small style="color: #666; display: block; margin-top: 5px;">Role is managed by administrators</small>
                        </div>
                    </div>
                    
                    <div class="password-section">
                        <h4>Change Password (Optional)</h4>
                        <p style="color: #666; font-size: 14px; margin-bottom: 15px;">Leave blank if you don't want to change your password</p>
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" placeholder="Enter current password">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" placeholder="Enter new password (min 6 chars)">
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 25px;">
                        <button type="submit" name="update_profile" class="btn">💾 Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?php echo $task_stats['total_tasks']; ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">⏳</div>
                <div class="stat-value"><?php echo $task_stats['pending_tasks']; ?></div>
                <div class="stat-label">Pending Tasks</div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">🔄</div>
                <div class="stat-value"><?php echo $task_stats['in_progress_tasks']; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo $task_stats['completed_tasks']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-icon">⚠️</div>
                <div class="stat-value"><?php echo $overdue_stats['overdue_tasks']; ?></div>
                <div class="stat-label">Overdue Tasks</div>
            </div>
            
            <div class="stat-card primary">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?php echo $upcoming_stats['upcoming_tasks']; ?></div>
                <div class="stat-label">Due This Week</div>
            </div>
        </div>
        
        <div class="content-section">
            <h2 class="section-title">📊 Recent Activities</h2>
            <?php if (count($recent_activities) > 0): ?>
                <ul class="activity-list">
                    <?php foreach ($recent_activities as $activity): ?>
                        <li class="activity-item">
                            <div class="activity-action"><?php echo htmlspecialchars($activity['action']); ?></div>
                            <div class="activity-details">Task: <?php echo htmlspecialchars($activity['task_title']); ?></div>
                            <div class="activity-time">⏰ <?php echo date('M d, Y g:i A', strtotime($activity['performed_at'])); ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 40px 0;">No recent activities found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>