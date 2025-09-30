<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !in_array('create', $rolePermissions[$_SESSION['role']] ?? [])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $assigned_to = $_POST['assigned_to']; // This will be an array now
    $priority = $_POST['priority'];
    $due_date = $_POST['due_date'];
    $created_by = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();

        $task_ids = [];

        // Create a task record for each assigned user
        foreach ($assigned_to as $assignee_id) {
            $stmt = $pdo->prepare("INSERT INTO tasks (title, description, assigned_to, created_by, priority, due_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $assignee_id, $created_by, $priority, $due_date]);

            $task_id = $pdo->lastInsertId();
            $task_ids[] = $task_id;

            // Log history for each task
            $stmt = $pdo->prepare("INSERT INTO task_history (task_id, action, performed_by) VALUES (?, ?, ?)");

            //get user name from id
            $sql="select * from users where id=$assignee_id";
            $res=$pdo->query($sql);
            $row=$res->fetch();
            $assignee_name=$row['username'];
            //get user name from id

            $action = "Task created with priority '$priority' and assigned to user ID $assignee_name";
            $stmt->execute([$task_id, $action, $created_by]);
        }

        $pdo->commit();

        $success_message = count($task_ids) > 1 ?
            "Task created successfully and assigned to " . count($task_ids) . " users!" :
            "Task created successfully!";

        header("Location: dashboard.php?success=" . urlencode($success_message));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error creating task: " . $e->getMessage();
    }
}

// Fetch users for assignment dropdown
$stmt = $pdo->prepare("SELECT id, full_name, role FROM users ORDER BY role, full_name");
$stmt->execute();
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Task - Task Tracker Pro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Select2 for multi-select -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <link rel="icon" type="image/png" href="./assets/fav.png">
        <link rel="stylesheet" href="./assets/css/create_task.css">


    <style>
    
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="header-icon">
                <i class="fas fa-plus-circle"></i>
            </div>
            <h2>Create New Task</h2>
            <p class="header-subtitle">Define and assign a new task to your team</p>
        </div>

        <div class="form-container">
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="createTaskForm">
                <div class="form-group">
                    <label class="form-label" for="title">
                        Task Title<span class="required-indicator">*</span>
                    </label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        class="form-input"
                        placeholder="Enter a clear and descriptive task title..."
                        required
                        maxlength="200"
                        autocomplete="off"
                        value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    <div class="form-help">Choose a title that clearly describes what needs to be accomplished</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">
                        Description<span class="required-indicator">*</span>
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        class="form-textarea"
                        placeholder="Provide detailed information about the task including requirements, expectations, and any relevant context that will help the assignee understand and complete the work effectively..."
                        required
                        maxlength="2000"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    <div class="form-help">Include all necessary details, requirements, and context for the task</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="assigned_to">
                        Assign To<span class="required-indicator">*</span>
                    </label>
                    <select id="assigned_to" name="assigned_to[]" class="form-select" multiple="multiple" required>
                        <?php
                        $previouslySelected = isset($_POST['assigned_to']) ? $_POST['assigned_to'] : [];
                        foreach ($users as $u):
                            $selected = in_array($u['id'], $previouslySelected) ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($u['id']); ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($u['full_name']) . ' (' . ucfirst(htmlspecialchars($u['role'])) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-help">Select one or more team members to assign this task to. Each person will get their own copy of the task.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Priority Level<span class="required-indicator">*</span>
                    </label>
                    <div class="priority-options">
                        <div class="priority-option high">
                            <input type="radio" id="priority_high" name="priority" value="high" class="priority-radio"
                                <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'high') ? 'checked' : ''; ?>>
                            <label for="priority_high" class="priority-label">
                                <div class="priority-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="priority-text">High</div>
                            </label>
                        </div>
                        <div class="priority-option medium">
                            <input type="radio" id="priority_medium" name="priority" value="medium" class="priority-radio"
                                <?php echo (!isset($_POST['priority']) || $_POST['priority'] === 'medium') ? 'checked' : ''; ?>>
                            <label for="priority_medium" class="priority-label">
                                <div class="priority-icon">
                                    <i class="fas fa-minus-circle"></i>
                                </div>
                                <div class="priority-text">Medium</div>
                            </label>
                        </div>
                        <div class="priority-option low">
                            <input type="radio" id="priority_low" name="priority" value="low" class="priority-radio"
                                <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'low') ? 'checked' : ''; ?>>
                            <label for="priority_low" class="priority-label">
                                <div class="priority-icon">
                                    <i class="fas fa-arrow-down"></i>
                                </div>
                                <div class="priority-text">Low</div>
                            </label>
                        </div>
                    </div>
                    <div class="form-help">Set the urgency level to help prioritize this task</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="due_date">
                        Due Date<span class="required-indicator">*</span>
                    </label>
                    <input
                        type="date"
                        id="due_date"
                        name="due_date"
                        class="form-input"
                        required
                        min="<?php echo date('Y-m-d'); ?>"
                        value="<?php echo isset($_POST['due_date']) ? htmlspecialchars($_POST['due_date']) : date('Y-m-d'); ?>">
                    <div class="form-help">Set a realistic deadline for task completion</div>
                </div>

                <div class="form-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-plus"></i>
                        Create Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize Select2 for multi-select
            $('#assigned_to').select2({
                placeholder: "Select team members to assign this task to...",
                allowClear: true,
                width: '100%',
                templateResult: function(option) {
                    if (!option.id) {
                        return option.text;
                    }

                    // Extract name and role from the option text
                    const match = option.text.match(/^(.+?)\s+\((.+?)\)$/);
                    if (match) {
                        const name = match[1];
                        const role = match[2];
                        return $(`
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 12px;">
                                    ${name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #2d3748;">${name}</div>
                                    <div style="font-size: 12px; color: #718096;">${role}</div>
                                </div>
                            </div>
                        `);
                    }
                    return option.text;
                }
            });

            // Auto-resize textarea
            const textarea = document.getElementById('description');

            function autoResize() {
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
            }

            textarea.addEventListener('input', autoResize);
            textarea.addEventListener('focus', autoResize);
            autoResize(); // Initial resize

            // Form submission with enhanced validation
            document.getElementById('createTaskForm').addEventListener('submit', function(e) {
                const submitBtn = document.getElementById('submitBtn');
                const title = document.getElementById('title').value.trim();
                const description = document.getElementById('description').value.trim();
                const assignedTo = $('#assigned_to').val();
                const priority = document.querySelector('input[name="priority"]:checked');
                const dueDate = document.getElementById('due_date').value;

                // Client-side validation
                if (!title || title.length < 3) {
                    e.preventDefault();
                    alert('Please enter a task title with at least 3 characters.');
                    document.getElementById('title').focus();
                    return false;
                }

                if (!description || description.length < 10) {
                    e.preventDefault();
                    alert('Please provide a detailed description with at least 10 characters.');
                    document.getElementById('description').focus();
                    return false;
                }

                if (!assignedTo || assignedTo.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one team member to assign this task to.');
                    $('#assigned_to').select2('open');
                    return false;
                }

                if (!priority) {
                    e.preventDefault();
                    alert('Please select a priority level for this task.');
                    return false;
                }

                if (!dueDate) {
                    e.preventDefault();
                    alert('Please set a due date for this task.');
                    document.getElementById('due_date').focus();
                    return false;
                }

                // Check if due date is in the past
                const today = new Date();
                const selectedDate = new Date(dueDate);
                if (selectedDate < today.setHours(0, 0, 0, 0)) {
                    e.preventDefault();
                    alert('Due date cannot be in the past. Please select today or a future date.');
                    document.getElementById('due_date').focus();
                    return false;
                }

                // Show loading state
                submitBtn.classList.add('btn-loading');
                submitBtn.disabled = true;

                const assigneeCount = assignedTo.length;
                const loadingText = assigneeCount > 1 ?
                    `<i class="fas fa-spinner fa-spin"></i> Creating ${assigneeCount} Tasks...` :
                    '<i class="fas fa-spinner fa-spin"></i> Creating Task...';
                submitBtn.innerHTML = loadingText;

                // Add success animation to form
                this.classList.add('success-animation');
            });

            // Character counter for title
            const titleInput = document.getElementById('title');
            titleInput.addEventListener('input', function() {
                updateCharCounter(this, 200);
            });

            // Character counter for description
            const descriptionTextarea = document.getElementById('description');
            descriptionTextarea.addEventListener('input', function() {
                updateCharCounter(this, 2000);
            });

            function updateCharCounter(element, maxLength) {
                const currentLength = element.value.length;

                // Remove existing counter
                const existingCounter = element.parentNode.querySelector('.char-counter');
                if (existingCounter) {
                    existingCounter.remove();
                }

                // Add counter if approaching limit
                if (currentLength > maxLength * 0.7) {
                    const counter = document.createElement('div');
                    counter.className = 'char-counter';
                    counter.style.cssText = 'font-size: 12px; color: #718096; margin-top: 5px; text-align: right;';
                    counter.textContent = `${currentLength}/${maxLength} characters`;

                    if (currentLength > maxLength * 0.9) {
                        counter.style.color = '#e53e3e';
                    }

                    element.parentNode.appendChild(counter);
                }
            }

            // Show selected count for multi-select
            $('#assigned_to').on('change', function() {
                const selectedCount = $(this).val() ? $(this).val().length : 0;
                const helpText = $(this).closest('.form-group').find('.form-help');

                if (selectedCount > 1) {
                    helpText.html(`Selected ${selectedCount} team members. Each person will get their own copy of the task.`);
                } else if (selectedCount === 1) {
                    helpText.html('One team member selected for this task.');
                } else {
                    helpText.html('Select one or more team members to assign this task to. Each person will get their own copy of the task.');
                }
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + Enter to submit form
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('createTaskForm').dispatchEvent(new Event('submit', {
                        bubbles: true
                    }));
                }

                // Escape to go back
                if (e.key === 'Escape') {
                    if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                        window.location.href = 'dashboard.php';
                    }
                }
            });

            // Save draft functionality
            function saveDraft() {
                const formData = {
                    title: document.getElementById('title').value,
                    description: document.getElementById('description').value,
                    assigned_to: $('#assigned_to').val(),
                    priority: document.querySelector('input[name="priority"]:checked')?.value,
                    due_date: document.getElementById('due_date').value
                };
                sessionStorage.setItem('taskDraft', JSON.stringify(formData));
            }

            function loadDraft() {
                const draft = sessionStorage.getItem('taskDraft');
                if (draft) {
                    const formData = JSON.parse(draft);
                    if (formData.title) document.getElementById('title').value = formData.title;
                    if (formData.description) document.getElementById('description').value = formData.description;
                    if (formData.assigned_to) $('#assigned_to').val(formData.assigned_to).trigger('change');
                    if (formData.priority) document.querySelector(`input[name="priority"][value="${formData.priority}"]`).checked = true;
                    if (formData.due_date) document.getElementById('due_date').value = formData.due_date;

                    // Trigger auto-resize for textarea
                    autoResize();
                }
            }

            // Load draft on page load
            loadDraft();

            // Save draft periodically
            setInterval(saveDraft, 15000); // Save every 15 seconds

            // Clear draft on successful submission
            document.getElementById('createTaskForm').addEventListener('submit', function() {
                sessionStorage.removeItem('taskDraft');
            });

            // Warn about unsaved changes
            let formChanged = false;
            const formInputs = document.querySelectorAll('#title, #description, #due_date');
            formInputs.forEach(input => {
                input.addEventListener('input', function() {
                    formChanged = true;
                });
            });

            $('#assigned_to').on('change', function() {
                formChanged = true;
            });

            document.querySelectorAll('input[name="priority"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    formChanged = true;
                });
            });

            window.addEventListener('beforeunload', function(e) {
                if (formChanged) {
                    e.preventDefault();
                    e.returnValue = '';
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });

            // Priority selection visual feedback
            document.querySelectorAll('input[name="priority"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    // Add subtle animation to selected priority
                    document.querySelectorAll('.priority-label').forEach(label => {
                        label.style.transform = '';
                    });

                    if (this.checked) {
                        this.nextElementSibling.style.transform = 'scale(1.05)';
                        setTimeout(() => {
                            this.nextElementSibling.style.transform = 'translateY(-2px)';
                        }, 200);
                    }
                });
            });

            // Enhanced form validation feedback
            function showFieldError(field, message) {
                // Remove existing error
                const existingError = field.parentNode.querySelector('.field-error');
                if (existingError) {
                    existingError.remove();
                }

                // Add new error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'field-error';
                errorDiv.style.cssText = 'color: #e53e3e; font-size: 12px; margin-top: 5px; display: flex; align-items: center; gap: 5px;';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
                field.parentNode.appendChild(errorDiv);

                // Add error styling to field
                field.style.borderColor = '#feb2b2';

                // Remove error after user starts typing
                field.addEventListener('input', function removeError() {
                    field.style.borderColor = '';
                    if (errorDiv.parentNode) {
                        errorDiv.remove();
                    }
                    field.removeEventListener('input', removeError);
                }, {
                    once: true
                });
            }

            // Real-time validation
            titleInput.addEventListener('blur', function() {
                if (this.value.trim().length < 3 && this.value.trim().length > 0) {
                    showFieldError(this, 'Title must be at least 3 characters long');
                }
            });

            descriptionTextarea.addEventListener('blur', function() {
                if (this.value.trim().length < 10 && this.value.trim().length > 0) {
                    showFieldError(this, 'Description must be at least 10 characters long');
                }
            });
        });
    </script>
</body>

</html>