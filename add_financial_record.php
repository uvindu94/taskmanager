<?php
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

// Only Super Admins and Division Heads can add records (or specific permissions if we had them)
if (!is_super_admin() && !is_division_head()) {
    header("Location: financeinfo.php");
    exit;
}

$message = '';
$current_user_name = $_SESSION['full_name'] ?? $_SESSION['username'];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sql = "INSERT INTO project_financial_statements (
                    reference_code, 
                    project_name, 
                    sales_category, 
                    product, 
                    product_type, 
                    sales_person_email, 
                    status, 
                    amount, 
                    sending_date, 
                    proposal_note, 
                    general_note, 
                    added_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
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
            $current_user_name
        ]);
        
        $message = '<div x-data="{ show: true }" x-show="show" class="bg-emerald-50 text-emerald-700 p-4 rounded-xl text-sm font-medium border border-emerald-100 flex justify-between items-center shadow-sm mb-6">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-check-circle text-emerald-500 text-lg"></i>
                            New financial record added successfully!
                        </div>
                        <div class="flex items-center gap-4">
                            <a href="financeinfo.php" class="text-emerald-700 hover:text-emerald-900 underline underline-offset-2">View All Records</a>
                            <button @click="show = false" class="text-emerald-500 hover:text-emerald-700"><i class="fas fa-times"></i></button>
                        </div>
                    </div>';
    } catch (PDOException $e) {
        $message = '<div x-data="{ show: true }" x-show="show" class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-medium border border-red-100 flex justify-between items-center shadow-sm mb-6">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
                            Error adding record: ' . htmlspecialchars($e->getMessage()) . '
                        </div>
                        <button @click="show = false" class="text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
                    </div>';
    }
}
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Add Financial Record</h1>
            <p class="text-slate-500 text-sm mt-1">Enter details for new proposal, quotation, or onboarded project.</p>
        </div>
        <a href="financeinfo.php" class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium rounded-xl shadow-sm transition-all flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <?= $message ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i class="fas fa-file-invoice-dollar text-emerald-500"></i> Record Details
            </h3>
        </div>
        
        <form method="POST" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Reference Code -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Reference Code <span class="text-red-500">*</span></label>
                    <input type="text" name="reference_code" placeholder="e.g. TTP-2024-001" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm" required>
                </div>
                
                <!-- Project Name -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Project Name <span class="text-red-500">*</span></label>
                    <input type="text" name="project_name" placeholder="e.g. Acme Corp Website" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm" required>
                </div>

                <!-- Sales Category -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Sales Category</label>
                    <select name="sales_category" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm cursor-pointer">
                        <option value="sales">Sales</option>
                        <option value="direct">Direct</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <!-- Product -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Product <span class="text-red-500">*</span></label>
                    <select name="product" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm cursor-pointer" required>
                        <option value="">Select Product</option>
                        <option value="Web">Web</option>
                        <option value="SSL">SSL</option>
                        <option value="Hosting">Hosting</option>
                        <option value="Cpanel">Cpanel</option>
                        <option value="Fleet Management">Fleet Management</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <!-- Product Type -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Product Type <span class="text-red-500">*</span></label>
                    <select name="product_type" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm cursor-pointer" required>
                        <option value="">Select Type</option>
                        <option value="Ecommerce">Ecommerce</option>
                        <option value="portfolio">Portfolio</option>
                        <option value="CRM">CRM</option>
                        <option value="HRM">HRM</option>
                        <option value="LMS">LMS</option>
                        <option value="Multi Domain">Multi Domain</option>
                        <option value="Single Domain">Single Domain</option>
                        <option value="Wild card">Wild card</option>
                        <option value="Tender">Tender</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <!-- Status -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Current Status <span class="text-red-500">*</span></label>
                    <select name="status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm cursor-pointer" required>
                        <option value="Proposal Send">Proposal Send</option>
                        <option value="Quotation Send">Quotation Send</option>
                        <option value="Invoice Send">Invoice Send</option>
                        <option value="Tender Send">Tender Send</option>
                        <option value="Pending">Pending</option>
                        <option value="Onboard">Onboard</option>
                        <option value="AMC Send">AMC Send</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <!-- Sales Person Email -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Sales Person Email <span class="text-red-500">*</span></label>
                    <input type="email" name="sales_person_email" placeholder="john@example.com" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm" required>
                </div>
                
                <!-- Amount -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Amount (LKR) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="amount" placeholder="0.00" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm" required>
                </div>
                
                <!-- Sending Date -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Sending Date <span class="text-red-500">*</span></label>
                    <input type="date" name="sending_date" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm cursor-pointer" required>
                </div>

                <!-- Spacer for grid alignment -->
                <div class="hidden md:block"></div>

                <!-- Notes -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Proposal / Quotation Note <span class="bg-slate-100 text-slate-500 px-2 py-0.5 rounded text-[10px] ml-1 uppercase tracking-wide">Optional</span></label>
                    <textarea name="proposal_note" placeholder="Details regarding the documents sent..." rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm resize-y"></textarea>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">General Notes <span class="bg-slate-100 text-slate-500 px-2 py-0.5 rounded text-[10px] ml-1 uppercase tracking-wide">Optional</span></label>
                    <textarea name="general_note" placeholder="Any additional information..." rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm resize-y"></textarea>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-slate-100 flex items-center justify-end gap-3">
                <button type="reset" class="px-5 py-2.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium rounded-xl shadow-sm transition-colors text-sm">
                    Clear Form
                </button>
                <button type="submit" class="px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-2 text-sm focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                    <i class="fas fa-plus-circle"></i> Save Record
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>