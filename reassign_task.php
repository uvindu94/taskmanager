<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !in_array('reassign', $rolePermissions[$_SESSION['role']] ?? [])) {
    header('Location: dashboard.php');
    exit;
}

$task_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

if ($task_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch();

if (!$task) {
    die("Task not found!");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_assigned_to = $_POST['assigned_to'];

    $stmt = $pdo->prepare("UPDATE tasks SET assigned_to = ? WHERE id = ?");
    $stmt->execute([$new_assigned_to, $task_id]);

    // Log history
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$new_assigned_to]);
    $new_name = $stmt->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO task_history (task_id, action, performed_by) VALUES (?, ?, ?)");
    $stmt->execute([$task_id, "Task reassigned to $new_name", $user_id]);

    header('Location: dashboard.php');
    exit;
}

// Fetch users for reassignment dropdown
$stmt = $pdo->prepare("SELECT id, full_name, role FROM users ORDER BY role, full_name");
$stmt->execute();
$users = $stmt->fetchAll();

// Fetch current assignee name
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$task['assigned_to']]);
$current_assignee = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Reassign Task</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --background-color: #f3f4f6;
            --card-background: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #4b5563;
            --border-color: #e5e7eb;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-color);
            color: var(--text-primary);
            line-height: 1.5;
            padding: 2rem;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: var(--card-background);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }

        .info-group {
            background-color: var(--background-color);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .info-group p {
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .info-group p:last-child {
            margin-bottom: 0;
        }

        .label {
            font-weight: 500;
            display: inline-block;
            width: 120px;
            color: var(--text-primary);
        }

        form {
            margin-top: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
            margin-bottom: 1.5rem;
            background-color: white;
            transition: border-color 0.2s;
        }

        select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        button {
            background-color: var(--primary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.2s;
        }

        button:hover {
            background-color: var(--primary-hover);
        }

        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .back-link:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Reassign Task: <?php echo htmlspecialchars($task['title']); ?></h2>

        <div class="info-group">
            <p><span class="label">Current Assignee:</span> <?php echo htmlspecialchars($current_assignee); ?></p>
            <p><span class="label">Status:</span> <?php echo ucfirst($task['status']); ?></p>
        </div>

        <form method="POST">
            <label for="assigned_to">New Assignee:</label>
            <select name="assigned_to" id="assigned_to" required>
                <option value="">Select New Assignee</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo ($task['assigned_to'] == $u['id']) ? 'selected' : ''; ?>>
                        <?php echo $u['full_name'] . ' (' . $u['role'] . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Reassign Task</button>
        </form>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</body>

</html>