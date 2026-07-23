<?php
require_once 'header.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project = trim($_POST['project'] ?? '');
    $sales_officer = trim($_POST['sales_officer'] ?? '');
    $project_contacts = trim($_POST['project_contacts'] ?? '');
    $project_link = trim($_POST['project_link'] ?? '');
    $due_date = $_POST['due_date'] ?? null;
    $initial_remark = trim($_POST['initial_remark'] ?? '');
    
    $created_by = $_SESSION['user_id'];
    $division_id = get_user_division();

    if (empty($project)) {
        $error = "Project title is required.";
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO projects 
                (project, sales_officer, project_contacts, project_link, due_date, created_by, division_id, status, completion) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'not_yet_start', 0)");
            $stmt->execute([
                $project,
                $sales_officer,
                $project_contacts,
                $project_link ?: null,
                $due_date ?: null,
                $created_by,
                $division_id
            ]);
            
            $project_id = $pdo->lastInsertId();
            
            if ($initial_remark !== '') {
                $stmt = $pdo->prepare("INSERT INTO project_remarks (project_id, created_by, remark) VALUES (?, ?, ?)");
                $stmt->execute([$project_id, $created_by, $initial_remark]);
            }
            
            $pdo->commit();
            
            header("Location: project_details.php?id=$project_id&created=1");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-4">
        <a href="projects.php" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:text-brand-600 hover:border-brand-300 hover:bg-brand-50 transition-all shadow-sm">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Create New Project</h1>
            <p class="text-slate-500 text-sm mt-1">Add a new project to your team's portfolio.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-xl text-sm font-medium border border-red-100 flex items-center gap-3">
            <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="create_project.php" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        
        <div class="p-6 md:p-8 space-y-8">
            <!-- Basic Details Section -->
            <div class="space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2">Basic Details</h3>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Project Title <span class="text-red-500">*</span></label>
                    <input type="text" name="project" required
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:bg-white transition-all text-slate-800"
                           placeholder="e.g. Website Redesign Q3">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Sales Officer</label>
                        <input type="text" name="sales_officer"
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:bg-white transition-all text-slate-800"
                               placeholder="e.g. John Doe">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Target Due Date</label>
                        <input type="date" name="due_date"
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:bg-white transition-all text-slate-800">
                    </div>
                </div>
            </div>

            <!-- Extended Details Section -->
            <div class="space-y-6 pt-2">
                <div>
                    <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2">Extended Info</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Project Contacts</label>
                        <textarea name="project_contacts" rows="2"
                                  class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:bg-white transition-all text-slate-800"
                                  placeholder="Contact info for clients or partners..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Project Link</label>
                        <div class="relative">
                            <i class="fas fa-link absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="url" name="project_link"
                                   class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:bg-white transition-all text-slate-800"
                                   placeholder="https://...">
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Initial Remarks</label>
                    <textarea name="initial_remark" rows="3"
                              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:bg-white transition-all text-slate-800"
                              placeholder="Any initial notes, context, or kick-off information?"></textarea>
                </div>
            </div>
        </div>

        <div class="px-6 py-5 bg-slate-50 border-t border-slate-200 flex justify-end gap-3">
            <a href="projects.php" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 font-medium rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white font-medium rounded-xl hover:bg-brand-700 transition-all shadow-sm shadow-brand-500/30 focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 flex items-center gap-2">
                <i class="fas fa-save"></i> Create Project
            </button>
        </div>
    </form>
</div>

<?php require_once 'footer.php'; ?>
