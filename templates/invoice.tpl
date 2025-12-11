<!DOCTYPE html>
<html>
<head>
    <title>Invoice #{$payment->getId()}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; padding: 40px; color: #333; line-height: 1.6; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, 0.05); }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 50px; border-bottom: 2px solid #eee; padding-bottom: 20px; }
        .logo h1 { margin: 0; color: #006798; font-size: 24px; }
        .meta { text-align: right; }
        .meta p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { border-bottom: 1px solid #eee; padding: 15px; text-align: left; }
        th { background: #f8f9fa; font-weight: 600; text-transform: uppercase; font-size: 12px; color: #555; }
        td { vertical-align: top; }
        .total { text-align: right; margin-top: 20px; font-size: 1.5em; font-weight: bold; color: #000; }
        .btn { display: inline-block; padding: 12px 24px; background: #006798; color: #fff; text-decoration: none; border-radius: 4px; border: none; font-size: 16px; cursor: pointer; transition: background 0.3s; }
        .btn:hover { background: #005680; }
        @media print { 
            .no-print { display: none !important; } 
            .invoice-box { border: none; box-shadow: none; padding: 0; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="header">
            <div class="logo">
                <h1>{$journal->getLocalizedName()}</h1>
                <p style="color: #666; margin-top: 5px;">Payment Receipt / Invoice</p>
            </div>
            <div class="meta">
                <p><strong>Invoice #:</strong> {$payment->getId()}</p>
                <p><strong>Date:</strong> {$dateClean}</p>
                <p><strong>Status:</strong> <span style="color: green; font-weight: bold;">PAID</span></p>
            </div>
        </div>
        
        <div style="margin-bottom: 40px; display: flex; justify-content: space-between;">
            <div>
                <strong>Bill To:</strong><br>
                {$user->getFullName()|escape}<br>
                {$user->getEmail()|escape}
            </div>
            <div style="text-align: right;">
                <strong>Payment Method:</strong><br>
                Card (Stripe)
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="width: 150px; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Publication Fee</strong><br>
                        <span style="color: #666; font-size: 0.9em;">Submission: {$submission->getCurrentPublication()->getLocalizedTitle()|escape} (ID: {$submission->getId()})</span>
                    </td>
                    <td style="text-align: right;">{$payment->getAmount()|string_format:"%.2f"} {$payment->getCurrencyCode()}</td>
                </tr>
            </tbody>
        </table>
        
        <div class="total">
            Total: {$payment->getAmount()|string_format:"%.2f"} {$payment->getCurrencyCode()}
        </div>
        
        <div style="margin-top: 60px; color: #777; font-size: 0.9em; text-align: center; border-top: 1px solid #eee; padding-top: 20px;">
            Thank you for publishing with {$journal->getLocalizedName()}.
        </div>
        
        <div class="no-print" style="margin-top: 40px; text-align: center;">
            <button onclick="window.print()" class="btn">Print / Download PDF</button>
            <br><br>
            <a href="javascript:window.close()" style="color: #666; text-decoration: none;">Close Window</a>
        </div>
    </div>
</body>
</html>
