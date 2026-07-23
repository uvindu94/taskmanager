<?php 
require_once 'header.php';

// Authorization Check
if (!is_super_admin()) {
    $current_div_id = get_user_division();
    $stmt = $pdo->prepare("SELECT access_level FROM division_features WHERE division_id = ? AND feature_key = 'budget_calculator'");
    $stmt->execute([$current_div_id]);
    $feature = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$feature) {
        // Division does not have access
        header("Location: dashboard.php");
        exit;
    }

    if ($feature['access_level'] === 'division_heads_only' && !is_division_head()) {
        // User is not a division head
        header("Location: dashboard.php");
        exit;
    }
}
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-brand-100 text-brand-600 rounded-xl flex items-center justify-center text-xl shadow-sm border border-brand-200">
                <i class="fas fa-calculator"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Budget Calculator</h1>
                <p class="text-slate-500 text-sm mt-1">Generate dynamic quotations for project development and server costs.</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left Column: Input Form -->
        <div class="lg:col-span-5">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 relative overflow-hidden h-full">
                <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-brand-500 to-brand-600"></div>
                <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <i class="fas fa-edit text-brand-500"></i> Project Details
                </h3>
                
                <form method="post" id="quotationForm" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Company / Project Name <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <i class="fas fa-building absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" name="name" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm" 
                                   placeholder="Enter company name" 
                                   value='<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>' required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Development Cost (LKR) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-xs">LKR</span>
                            <input type="number" name="devcost" id="devcost" class="w-full pl-12 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm" 
                                   step="0.01" placeholder="0.00" 
                                   value='<?php echo isset($_POST['devcost']) ? $_POST['devcost'] : ''; ?>' required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Server Cost (LKR) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-xs">LKR</span>
                            <input type="number" name="server_cost" id="server_cost" class="w-full pl-12 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm" 
                                   step="0.01" placeholder="0.00" 
                                   value='<?php echo isset($_POST['server_cost']) ? $_POST['server_cost'] : ''; ?>' required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">SSCL Tax Type <span class="text-red-500">*</span></label>
                        <select name="ssl" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm" required>
                            <option value="">Select SSCL Option</option>
                            <option value="ssl_to_dev" <?php echo (isset($_POST['ssl']) && $_POST['ssl']=='ssl_to_dev') ? 'selected' : ''; ?>>
                                Add SSCL to Development Cost
                            </option>
                            <option value="ssl_seperate" <?php echo (isset($_POST['ssl']) && $_POST['ssl']=='ssl_seperate') ? 'selected' : ''; ?>>
                                Add SSCL Separately (PSM)
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Discount (%) <span class="bg-slate-100 text-slate-500 px-2 py-0.5 rounded text-[10px] ml-1 uppercase tracking-wide">Optional</span></label>
                        <div class="relative">
                            <i class="fas fa-percent absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="number" name="discount" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors text-sm" 
                                   step="0.01" min="0" max="100" placeholder="0" 
                                   value='<?php echo isset($_POST['discount']) ? $_POST['discount'] : '0'; ?>'>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-sm hover:shadow-md transition-all flex items-center justify-center gap-2 mt-4 focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                        <i class="fas fa-magic"></i> Calculate Quotation
                    </button>
                    
                    <div class="bg-amber-50 border border-amber-200 text-amber-700 p-3 rounded-lg text-xs flex gap-2 items-start mt-4 shadow-sm">
                        <i class="fas fa-info-circle mt-0.5 text-amber-500"></i>
                        <div>
                            <strong>Note:</strong> SSCL rate is fixed at <strong>2.5641%</strong> and VAT is calculated at <strong>18%</strong>.
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column: Results -->
        <div class="lg:col-span-7">
            <?php
            if(isset($_POST['name'])) {
                extract($_POST);
                
                $ssl_rate = 0.025641;
                $discount = isset($discount) && $discount > 0 ? floatval($discount) : 0;
                
                if ($ssl == 'ssl_to_dev') {
                    $ssl_fee = ($devcost + $server_cost) * $ssl_rate;
                    $final_dev_cost = $devcost + $ssl_fee;
                    $tot_without_tax = $final_dev_cost + $server_cost;
                    $vat_18 = $tot_without_tax * 0.18;
                    $tot_with_tax = $tot_without_tax + $vat_18;
                } else {
                    $final_dev_cost = $devcost;
                    $tot_without_tax = $devcost + $server_cost;
                    $ssl_fee = $tot_without_tax * $ssl_rate;
                    $vat_18 = ($tot_without_tax + $ssl_fee) * 0.18;
                    $tot_with_tax = $tot_without_tax + $vat_18 + $ssl_fee;
                }
                
                // Apply discount if present
                $discount_amount = 0;
                $final_total = $tot_with_tax;
                if ($discount > 0) {
                    $discount_amount = $tot_with_tax * ($discount / 100);
                    $final_total = $tot_with_tax - $discount_amount;
                }
                
                $amc = $final_dev_cost * 0.2;
                $total_amc = $amc + $server_cost;
            ?>
            
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-full animate-[fadeIn_0.5s_ease-out]">
                <div class="bg-slate-50/50 p-6 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-brand-700 tracking-tight">Quotation Results</h2>
                        <p class="text-sm font-medium text-slate-500 mt-1">For: <span class="text-slate-800 font-bold"><?php echo htmlspecialchars($name); ?></span></p>
                    </div>
                    <img class="h-10 opacity-80 mix-blend-multiply" src="https://sltds.lk/wp-content/uploads/2021/05/cropped-logo1.png" alt="SLTDS Logo">
                </div>
                
                <div class="p-6 flex-1 bg-white" id="resultSection">
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-center py-3 border-b border-slate-100">
                            <span class="text-sm font-medium text-slate-600">Website Development</span>
                            <span class="font-bold text-slate-800">LKR <?php echo number_format($final_dev_cost, 2, '.', ','); ?></span>
                        </div>
                        <div class="flex justify-between items-center py-3 border-b border-slate-100">
                            <span class="text-sm font-medium text-slate-600">Server with cPanel</span>
                            <span class="font-bold text-slate-800">LKR <?php echo number_format($server_cost, 2, '.', ','); ?></span>
                        </div>
                        <div class="flex justify-between items-center py-3 border-b border-slate-100 bg-slate-50 -mx-6 px-6">
                            <span class="text-sm font-bold text-slate-700">Total without tax</span>
                            <span class="font-black text-slate-900">LKR <?php echo number_format($tot_without_tax, 2, '.', ','); ?></span>
                        </div>
                        <div class="flex justify-between items-center py-3 border-b border-slate-100">
                            <span class="text-sm font-medium text-slate-600">VAT (18%)</span>
                            <span class="font-bold text-slate-800">LKR <?php echo number_format($vat_18, 2, '.', ','); ?></span>
                        </div>
                        
                        <?php if ($ssl == 'ssl_seperate'): ?>
                        <div class="flex justify-between items-center py-3 border-b border-slate-100">
                            <span class="text-sm font-medium text-slate-600">SSCL (2.5641%)</span>
                            <span class="font-bold text-slate-800">LKR <?php echo number_format($ssl_fee, 2, '.', ','); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($discount > 0): ?>
                        <div class="flex justify-between items-center py-3 border-b border-slate-100 text-green-600">
                            <span class="text-sm font-semibold">Discount (<?php echo $discount; ?>%)</span>
                            <span class="font-bold">- LKR <?php echo number_format($discount_amount, 2, '.', ','); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-between items-center py-4 bg-brand-50 border border-brand-100 rounded-xl px-4 mt-4 shadow-inner">
                            <span class="text-base font-black text-brand-800">TOTAL WITH TAX</span>
                            <span class="text-xl font-black text-brand-700">LKR <?php echo number_format($final_total, 2, '.', ','); ?></span>
                        </div>
                    </div>

                    <div class="mt-8 border-t-2 border-slate-100 pt-6">
                        <h3 class="text-lg font-bold text-slate-700 mb-4 flex items-center gap-2">
                            <i class="fas fa-calendar-check text-indigo-500"></i> Second Year Renewal
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center py-2 border-b border-slate-50">
                                <span class="text-sm text-slate-600">Server with cPanel</span>
                                <span class="font-bold text-slate-800">LKR <?php echo number_format($server_cost, 2, '.', ','); ?></span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-slate-50">
                                <span class="text-sm text-slate-600">Annual Maintenance Contract (20%)</span>
                                <span class="font-bold text-slate-800">LKR <?php echo number_format($amc, 2, '.', ','); ?></span>
                            </div>
                            <div class="flex justify-between items-center py-3 px-4 bg-slate-100 rounded-lg mt-2 font-bold">
                                <span class="text-sm text-slate-700">TOTAL RENEWAL</span>
                                <span class="text-slate-800 text-lg">LKR <?php echo number_format($total_amc, 2, '.', ','); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 p-4 border-t border-slate-100 flex flex-wrap gap-3 justify-end no-print">
                    <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors flex items-center gap-2">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button onclick="copyToClipboard()" class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium rounded-lg shadow-sm transition-colors flex items-center gap-2">
                        <i class="fas fa-copy text-brand-500"></i> Copy
                    </button>
                    <button onclick="downloadAsText()" class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium rounded-lg shadow-sm transition-colors flex items-center gap-2">
                        <i class="fas fa-download text-emerald-500"></i> Download
                    </button>
                </div>
            </div>

            <?php
            } else {
            ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-12 flex flex-col items-center justify-center text-center h-full min-h-[400px]">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-4xl text-slate-300 mb-6 border border-slate-100">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-700 mb-2">No Quotation Generated</h3>
                    <p class="text-slate-500">Fill in the project details on the left and click calculate to generate a detailed quotation here.</p>
                </div>
            <?php
            }
            ?>
        </div>
    </div>
</div>

<style>
    @media print {
        body * { visibility: hidden; }
        #resultSection, #resultSection * { visibility: visible; }
        #resultSection {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            background: white !important;
            padding: 0 !important;
        }
        .no-print { display: none !important; }
    }
</style>

<script>
    function copyToClipboard() {
        const resultText = document.getElementById('resultSection').innerText;
        navigator.clipboard.writeText(resultText).then(() => {
            alert('Quotation copied to clipboard successfully!');
        }).catch(err => {
            alert('Failed to copy: ' + err);
        });
    }

    function downloadAsText() {
        const resultText = document.getElementById('resultSection').innerText;
        const blob = new Blob([resultText], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'quotation_<?php echo isset($name) ? preg_replace('/[^a-zA-Z0-9]/', '_', $name) : 'document'; ?>.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    // Form validation enhancement
    document.getElementById('quotationForm').addEventListener('submit', function(e) {
        const devcost = parseFloat(document.getElementById('devcost').value);
        const servercost = parseFloat(document.getElementById('server_cost').value);
        
        if (devcost < 0 || servercost < 0) {
            e.preventDefault();
            alert('Costs cannot be negative values!');
            return false;
        }
    });
</script>

<?php require_once 'footer.php'; ?>