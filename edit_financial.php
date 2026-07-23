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

// Only Super Admins and Division Heads can edit records
if (!is_super_admin() && !is_division_head()) {
    header("Location: financeinfo.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: financeinfo.php');
    exit;
}

// Fetch existing record
$stmt = $pdo->prepare("SELECT * FROM project_financial_statements WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    echo "<div class='p-8 max-w-2xl mx-auto text-center mt-12 bg-white rounded-2xl shadow-sm border border-slate-200'>
            <i class='fas fa-exclamation-triangle text-4xl text-amber-500 mb-4'></i>
            <h2 class='text-xl font-bold text-slate-800 mb-2'>Record not found</h2>
            <p class='text-slate-500 mb-6'>The financial record you are trying to edit does not exist.</p>
            <a href='financeinfo.php' class='px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition-colors'>Go Back</a>
          </div>";
    require_once 'footer.php';
    exit;
}

$message = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sql = "UPDATE project_financial_statements SET 
                reference_code = ?, 
                project_name = ?, 
                sales_category = ?, 
                product = ?, 
                product_type = ?, 
                sales_person_email = ?, 
                status = ?, 
                amount = ?, 
                sending_date = ?, 
                proposal_note = ?, 
                general_note = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['reference_code'],
            $_POST['project_name'],
            $_POST['sales_category'],
            $_POST['product'],
            $_POST['product_type'],
            $_POST['sales_person_email'],
            $_POST['status'],
            $_POST['amount'] ?: 0,
            $_POST['sending_date'],
            $_POST['proposal_note'],
            $_POST['general_note'],
            $id
        ]);
        
        $message = '<div x-data="{ show: true }" x-show="show" class="bg-emerald-50 text-emerald-700 p-4 rounded-xl text-sm font-medium border border-emerald-100 flex justify-between items-center shadow-sm mb-6">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-check-circle text-emerald-500 text-lg"></i>
                            Record updated successfully!
                        </div>
                        <div class="flex items-center gap-4">
                            <a href="financeinfo.php" class="text-emerald-700 hover:text-emerald-900 underline underline-offset-2">View All Records</a>
                            <button @click="show = false" class="text-emerald-500 hover:text-emerald-700"><i class="fas fa-times"></i></button>
                        </div>
                    </div>';
        
        // Refresh local record variable to show updated data in form
        $stmt = $pdo->prepare("SELECT * FROM project_financial_statements WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch();

    } catch (PDOException $e) {
        $message = '<div x-data="{ show: true }" x-show="show" class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-medium border border-red-100 flex justify-between items-center shadow-sm mb-6">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
                            Error updating record: ' . htmlspecialchars($e->getMessage()) . '
                        </div>
                        <button @click="show = false" class="text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
                    </div>';
    }
}
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Edit Financial Record</h1>
            <p class="text-slate-500 text-sm mt-1">Updating reference code: <span class="font-bold text-slate-700"><?= htmlspecialchars($record['reference_code']) ?></span></p>
        </div>
        <a href="financeinfo.php" class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium rounded-xl shadow-sm transition-all flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <?= $message ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i class="fas fa-edit text-brand-500"></i> Edit Details
            </h3>
            <span class="text-xs font-semibold bg-white border border-slate-200 px-3 py-1 rounded-full text-slate-500 shadow-sm">
                ID: <?= $record['id'] ?>
            </span>
        </div>
        
        <form method="POST" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Reference Code -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Reference Code <span class="text-red-500">*</span></label>
                    <input type="text" name="reference_code" value="<?= htmlspecialchars($record['reference_code']) ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm" required>
                </div>
                
                <!-- Project Name -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Project Name <span class="text-red-500">*</span></label>
                    <input type="text" name="project_name" value="<?= htmlspecialchars($record['project_name']) ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm" required>
                </div>

                <!-- Sales Category -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Sales Category</label>
                    <select name="sales_category" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm cursor-pointer">
                        <option value="sales" <?= $record['sales_category'] == 'sales' ? 'selected' : '' ?>>Sales</option>
                        <option value="direct" <?= $record['sales_category'] == 'direct' ? 'selected' : '' ?>>Direct</option>
                        <option value="other" <?= $record['sales_category'] == 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <!-- Product -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Product <span class="text-red-500">*</span></label>
                    <select name="product" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm cursor-pointer" required>
                        <?php 
                        $products = ['Web', 'SSL', 'Hosting', 'Cpanel', 'Fleet Management', 'Other'];
                        foreach($products as $p) {
                            $sel = ($record['product'] == $p) ? 'selected' : '';
                            echo "<option value='$p' $sel>$p</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Product Type -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Product Type <span class="text-red-500">*</span></label>
                    <select name="product_type" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm cursor-pointer" required>
                        <?php 
                        $types = ['Ecommerce', 'portfolio', 'CRM', 'HRM', 'LMS', 'Multi Domain', 'Single Domain', 'Wild card', 'Tender', 'Other'];
                        foreach($types as $t) {
                            $sel = ($record['product_type'] == $t) ? 'selected' : '';
                            echo "<option value='$t' $sel>$t</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <!-- Status -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Current Status <span class="text-red-500">*</span></label>
                    <select name="status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm cursor-pointer" required>
                        <?php 
                        $statuses = ['Proposal Send', 'Quotation Send', 'Invoice Send', 'Tender Send', 'Pending', 'Onboard', 'AMC Send', 'Other'];
                        foreach($statuses as $s) {
                            $sel = ($record['status'] == $s) ? 'selected' : '';
                            echo "<option value='$s' $sel>$s</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Sales Person Email -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Sales Person Email <span class="text-red-500">*</span></label>
                    <input type="email" name="sales_person_email" value="<?= htmlspecialchars($record['sales_person_email']) ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm" required>
                </div>
                
                <!-- Amount -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Amount (LKR) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="amount" value="<?= htmlspecialchars($record['amount']) ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm" required>
                </div>
                
                <!-- Sending Date -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Sending Date <span class="text-red-500">*</span></label>
                    <input type="date" name="sending_date" value="<?= $record['sending_date'] ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm cursor-pointer" required>
                </div>

                <!-- Spacer -->
                <div class="hidden md:block"></div>

                <!-- Notes -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Proposal / Quotation Note <span class="bg-slate-100 text-slate-500 px-2 py-0.5 rounded text-[10px] ml-1 uppercase tracking-wide">Optional</span></label>
                    <textarea name="proposal_note" rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm resize-y"><?= htmlspecialchars($record['proposal_note']) ?></textarea>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">General Notes <span class="bg-slate-100 text-slate-500 px-2 py-0.5 rounded text-[10px] ml-1 uppercase tracking-wide">Optional</span></label>
                    <textarea name="general_note" rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm resize-y"><?= htmlspecialchars($record['general_note']) ?></textarea>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="financeinfo.php" class="px-5 py-2.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium rounded-xl shadow-sm transition-colors text-sm">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-2 text-sm focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                    <i class="fas fa-save"></i> Update Record
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>