<?php 
session_start();
// Authentication commented out as in original
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation Calculator - SLTDS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea00 0%, #764ba200 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .dslogo {
            max-width: 200px;
            height: auto;
            margin-bottom: 15px;
            
        }

        .header h1 {
            font-size: 2.5em;
            font-weight: 300;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            color: black
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }

        @media (max-width: 968px) {
            .row {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        select.form-control {
            cursor: pointer;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 10px;
            margin-top: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(56, 239, 125, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(52, 152, 219, 0.4);
        }

        .result-section {
            animation: fadeIn 0.5s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .result-header {
            color: #667eea;
            font-size: 1.8em;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }

        .result-subheader {
            color: #764ba2;
            font-size: 1.4em;
            margin-top: 30px;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table tr {
            border-bottom: 1px solid #f0f0f0;
        }

        table td {
            padding: 15px 10px;
        }

        table td:first-child {
            color: #555;
            font-weight: 500;
        }

        table td:last-child {
            text-align: right;
            font-weight: 700;
            color: #333;
        }

        table tr:last-child {
            background: #f8f9ff;
            font-size: 1.2em;
        }

        table tr:last-child td {
            color: #667eea;
            padding: 20px 10px;
        }

        .no-result {
            text-align: center;
            color: #999;
            padding: 40px;
            font-size: 1.2em;
        }

        .action-buttons {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .card {
                box-shadow: none;
            }
            .row {
                display: block;
            }
            .row > div:first-child {
                display: none;
            }
            .btn, .action-buttons {
                display: none;
            }
            .header {
                color: #333;
            }
        }

        .info-badge {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }

        .calculation-note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
            font-size: 14px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img class="dslogo" src="https://sltds.lk/wp-content/uploads/2021/05/cropped-logo1.png" alt="SLTDS Logo">
            <h1>Quotation Calculator</h1>
            <br>
                                <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
        </div>

        <div class="row">
            <div class="column">
                <div class="card">
                    <form method="post" id="quotationForm">
                        <div class="form-group">
                            <label for="name">Company Name *</label>
                            <input type="text" name="name" id="name" class="form-control" 
                                   placeholder="Enter company name" 
                                   value='<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>' 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="devcost">Development Cost (LKR) *</label>
                            <input type="number" name="devcost" id="devcost" class="form-control" 
                                   step="0.01" placeholder="Enter development cost" 
                                   value='<?php echo isset($_POST['devcost']) ? $_POST['devcost'] : ''; ?>' 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="server_cost">Server Cost (LKR) *</label>
                            <input type="number" name="server_cost" id="server_cost" class="form-control" 
                                   step="0.01" placeholder="Enter server cost" 
                                   value='<?php echo isset($_POST['server_cost']) ? $_POST['server_cost'] : ''; ?>' 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="ssl">SSCL Type *</label>
                            <select name="ssl" id="ssl" class="form-control" required>
                                <option value="">Select SSL Option</option>
                                <option value="ssl_to_dev" <?php echo (isset($_POST['ssl']) && $_POST['ssl']=='ssl_to_dev') ? 'selected' : ''; ?>>
                                    Add SSCL to Development Cost
                                </option>
                                <option value="ssl_seperate" <?php echo (isset($_POST['ssl']) && $_POST['ssl']=='ssl_seperate') ? 'selected' : ''; ?>>
                                    Add SSCL Separately (PSM)
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="discount">Discount (%) <span class="info-badge">Optional</span></label>
                            <input type="number" name="discount" id="discount" class="form-control" 
                                   step="0.01" min="0" max="100" placeholder="Enter discount percentage" 
                                   value='<?php echo isset($_POST['discount']) ? $_POST['discount'] : '0'; ?>'>
                        </div>

                        <button type="submit" class="btn btn-primary">Calculate Quotation</button>
                    </form>

                    <div class="calculation-note">
                        <strong>Note:</strong> SSL rate is 2.5641% and VAT is 18%
                    </div>
                </div>
            </div>

            <div class="column">
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
                
                <div class="card result-section" id="resultSection">
                    <h2 class="result-header">Quotation for <?php echo htmlspecialchars($name); ?></h2>
                    
                    <table>
                        <tr>
                            <td><?php echo htmlspecialchars($name); ?> – Website Development</td>
                            <td>LKR <?php echo number_format($final_dev_cost, 2, '.', ','); ?></td>
                        </tr>
                        <tr>
                            <td>Server with cPanel</td>
                            <td>LKR <?php echo number_format($server_cost, 2, '.', ','); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total without tax</strong></td>
                            <td>LKR <?php echo number_format($tot_without_tax, 2, '.', ','); ?></td>
                        </tr>
                        <tr>
                            <td>VAT (18%)</td>
                            <td>LKR <?php echo number_format($vat_18, 2, '.', ','); ?></td>
                        </tr>
                        <?php if ($ssl == 'ssl_seperate'): ?>
                        <tr>
                            <td>SSCL (2.5641%)</td>
                            <td>LKR <?php echo number_format($ssl_fee, 2, '.', ','); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($discount > 0): ?>
                        <tr>
                            <td>Discount (<?php echo $discount; ?>%)</td>
                            <td>- LKR <?php echo number_format($discount_amount, 2, '.', ','); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong>TOTAL WITH TAX</strong></td>
                            <td>LKR <?php echo number_format($final_total, 2, '.', ','); ?></td>
                        </tr>
                    </table>

                    <h3 class="result-subheader">Second Year Renewal</h3>
                    <table>
                        <tr>
                            <td>Server with cPanel</td>
                            <td>LKR <?php echo number_format($server_cost, 2, '.', ','); ?></td>
                        </tr>
                        <tr>
                            <td>Annual Maintenance Contract (AMC - 20%)</td>
                            <td>LKR <?php echo number_format($amc, 2, '.', ','); ?></td>
                        </tr>
                        <tr>
                            <td><strong>TOTAL RENEWAL</strong></td>
                            <td>LKR <?php echo number_format($total_amc, 2, '.', ','); ?></td>
                        </tr>
                    </table>

                    <div class="action-buttons">
                        <button onclick="window.print()" class="btn btn-success">🖨️ Print Quotation</button>
                        <button onclick="copyToClipboard()" class="btn btn-info">📋 Copy to Clipboard</button>
                        <button onclick="downloadAsText()" class="btn btn-info">💾 Download as Text</button>
                    </div>
                </div>

                <?php
                } else {
                    echo '<div class="card"><div class="no-result">📝 Fill in the form to generate a quotation</div></div>';
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard() {
            const resultText = document.getElementById('resultSection').innerText;
            navigator.clipboard.writeText(resultText).then(() => {
                alert('✅ Quotation copied to clipboard!');
            }).catch(err => {
                alert('❌ Failed to copy: ' + err);
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
                alert('⚠️ Costs cannot be negative values!');
                return false;
            }
        });
    </script>
</body>
</html>