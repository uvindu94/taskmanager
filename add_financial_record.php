<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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
            $_POST['amount'],
            $_POST['sending_date'],
            $_POST['proposal_note'],
            $_POST['general_note'],
            $current_user_name // Captured from session
        ]);
        
        $message = '<div class="alert alert-success" style="background:#d1fae5; color:#065f46; padding:15px; border-radius:8px; margin-bottom:20px;">
                        <i class="fas fa-check-circle"></i> New record added successfully! 
                        <a href="project_financial_statements.php" style="font-weight:bold; color:#065f46; margin-left:10px;">View All Records</a>
                    </div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger" style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; margin-bottom:20px;">
                        Error adding record: ' . $e->getMessage() . '
                    </div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Financial Record - Task Tracker Pro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <style>
        .form-container { background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #374151; font-size: 14px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        .form-control:focus { outline: none; border-color: #3b82f6; border-width: 2px; }
        textarea.form-control { height: 80px; resize: vertical; }
        .required-star { color: #dc2626; margin-left: 2px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1>Add New Financial Record</h1>
                    <p class="header-subtitle">Enter details for new proposal, quotation, or onboarded project</p>
                </div>
                <div class="header-actions">
                    <a href="financeinfo.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="form-container">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Reference Code <span class="required-star">*</span></label>
                        <input type="text" name="reference_code" placeholder="e.g. TTP-2024-001" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Project Name <span class="required-star">*</span></label>
                        <input type="text" name="project_name" placeholder="e.g. Acme Corp Website" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Sales Category</label>
                        <select name="sales_category" class="form-control">
                            <option value="sales">Sales</option>
                            <option value="direct">Direct</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Product <span class="required-star">*</span></label>
                        <select name="product" class="form-control" required>
                            <option value="">Select Product</option>
                            <option value="Web">Web</option>
                            <option value="SSL">SSL</option>
                            <option value="Hosting">Hosting</option>
                            <option value="Cpanel">Cpanel</option>
                            <option value="Fleet Management">Fleet Management</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Product Type <span class="required-star">*</span></label>
                        <select name="product_type" class="form-control" required>
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
                    <div class="form-group">
                        <label>Current Status <span class="required-star">*</span></label>
                        <select name="status" class="form-control" required>
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

                    <div class="form-group">
                        <label>Sales Person / Email <span class="required-star">*</span></label>
                        <input type="text" name="sales_person_email" placeholder="john@example.com" class="form-control" >
                    </div>
                    <div class="form-group">
                        <label>Amount (Numeric) <span class="required-star">*</span></label>
                        <input type="number" step="0.01" name="amount" placeholder="0.00" class="form-control" >
                    </div>
                    <div class="form-group">
                        <label>Sending Date <span class="required-star">*</span></label>
                        <input type="date" name="sending_date" value="<?php echo date('Y-m-d'); ?>" class="form-control" required>
                    </div>

                    <div class="form-group full-width">
                        <label>Note about Proposal / Quotation / Invoice</label>
                        <textarea name="proposal_note" placeholder="Details regarding the documents sent..." class="form-control"></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>General Notes</label>
                        <textarea name="general_note" placeholder="Any additional information..." class="form-control"></textarea>
                    </div>
                </div>

                <div style="margin-top: 20px; text-align: right;">
                    <button type="reset" class="btn btn-secondary" style="margin-right: 10px;">Clear Form</button>
                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px; background-color: indigo;">
                        <i class="fas fa-plus-circle" ></i> Save Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>