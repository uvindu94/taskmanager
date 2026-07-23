<?php
require_once 'config.php';
require_once 'header.php';

// Authorization Check
if (!is_super_admin()) {
    $current_div_id = get_user_division();
    $stmt = $pdo->prepare("SELECT access_level FROM division_features WHERE division_id = ? AND feature_key = 'finance_info'");
    $stmt->execute([$current_div_id]);
    $feature = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$feature) {
        header("Location: dashboard.php");
        exit;
    }
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: financeinfo.php');
    exit;
}

// Fetch the specific record
try {
    $stmt = $pdo->prepare("SELECT * FROM project_financial_statements WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();

    if (!$record) {
        echo "<div class='p-8 max-w-2xl mx-auto text-center mt-12 bg-white rounded-2xl shadow-sm border border-slate-200'>
                <i class='fas fa-exclamation-triangle text-4xl text-amber-500 mb-4'></i>
                <h2 class='text-xl font-bold text-slate-800 mb-2'>Record not found</h2>
                <p class='text-slate-500 mb-6'>The financial record you are trying to view does not exist.</p>
                <a href='financeinfo.php' class='px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition-colors'>Go Back</a>
              </div>";
        require_once 'footer.php';
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Financial Record Details</h1>
            <p class="text-slate-500 text-sm mt-1">Ref Code: <span class="font-bold text-slate-700"><?= htmlspecialchars($record['reference_code']) ?></span></p>
        </div>
        <div class="flex items-center gap-3">
            <a href="financeinfo.php" class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium rounded-xl shadow-sm transition-all flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if (is_super_admin() || is_division_head()): ?>
                <a href="edit_financial.php?id=<?= $record['id'] ?>" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-medium rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-2">
                    <i class="fas fa-edit"></i> Edit Record
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 md:p-8 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight"><?= htmlspecialchars($record['project_name']) ?></h2>
                <?php
                $status_color = 'bg-slate-100 text-slate-600 border-slate-200';
                switch ($record['status']) {
                    case 'Proposal Send': $status_color = 'bg-blue-50 text-blue-600 border-blue-200'; break;
                    case 'Quotation Send': $status_color = 'bg-indigo-50 text-indigo-600 border-indigo-200'; break;
                    case 'Invoice Send': $status_color = 'bg-purple-50 text-purple-600 border-purple-200'; break;
                    case 'Onboard': $status_color = 'bg-emerald-50 text-emerald-600 border-emerald-200'; break;
                    case 'Pending': $status_color = 'bg-amber-50 text-amber-600 border-amber-200'; break;
                }
                ?>
                <div class="mt-2 flex items-center gap-2">
                    <span class="px-2.5 py-1 <?= $status_color ?> rounded-md text-xs font-bold border tracking-wide uppercase">
                        <?= htmlspecialchars($record['status']) ?>
                    </span>
                </div>
            </div>
            
            <div class="md:text-right bg-white p-3 rounded-xl border border-slate-200 shadow-sm inline-block">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Sending Date</label>
                <div class="text-lg font-bold text-slate-700 flex items-center gap-2">
                    <i class="far fa-calendar-alt text-brand-500"></i>
                    <?= date('D, M j, Y', strtotime($record['sending_date'])) ?>
                </div>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Sales Category</label>
                    <p class="text-base font-semibold text-slate-800 capitalize"><?= htmlspecialchars($record['sales_category']) ?></p>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Product & Type</label>
                    <p class="text-base font-semibold text-slate-800">
                        <?= htmlspecialchars($record['product']) ?> <span class="text-slate-400 font-normal mx-1">—</span> <?= htmlspecialchars($record['product_type']) ?>
                    </p>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Sales Person</label>
                    <p class="text-base font-semibold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-user-tie text-slate-400"></i> <?= htmlspecialchars($record['sales_person_email']) ?>
                    </p>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Financial Amount</label>
                    <p class="text-2xl font-black text-emerald-600">
                        LKR <?= number_format($record['amount'], 2) ?>
                    </p>
                </div>

                <div class="md:col-span-2 bg-brand-50 rounded-xl p-5 border-l-4 border-brand-500">
                    <label class="block text-sm font-bold text-brand-800 mb-2 flex items-center gap-2">
                        <i class="fas fa-file-invoice"></i> Proposal / Invoice Note
                    </label>
                    <p class="text-slate-700 text-sm leading-relaxed">
                        <?= nl2br(htmlspecialchars($record['proposal_note'] ?: 'No proposal notes provided.')) ?>
                    </p>
                </div>

                <div class="md:col-span-2 bg-slate-50 rounded-xl p-5 border-l-4 border-slate-400">
                    <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                        <i class="fas fa-sticky-note"></i> General Notes
                    </label>
                    <p class="text-slate-600 text-sm leading-relaxed">
                        <?= nl2br(htmlspecialchars($record['general_note'] ?: 'No general notes provided.')) ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between text-xs font-medium text-slate-500 gap-2">
            <div class="flex items-center gap-1.5">
                <i class="fas fa-user-plus text-slate-400"></i> 
                Added by: <span class="text-slate-700 font-semibold"><?= htmlspecialchars($record['added_by']) ?></span>
            </div>
            <div class="flex items-center gap-3">
                <span><i class="fas fa-hashtag text-slate-400"></i> ID: <?= $record['id'] ?></span>
                <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                <span><i class="far fa-clock text-slate-400"></i> Created: <?= date('M j, Y H:i', strtotime($record['created_at'])) ?></span>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>