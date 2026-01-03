<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #1e293b;
            font-size: 13px;
            line-height: 1.4;
        }
        .header {
            background: #057F5F;
            color: #fff;
            padding: 30px 40px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: middle;
        }
        .journal-title {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .invoice-title {
            font-size: 32px;
            font-weight: bold;
            text-align: right;
            letter-spacing: 2px;
        }
        .invoice-info {
            text-align: right;
            font-size: 14px;
            margin-top: 5px;
        }
        .status-paid {
            background: #22c55e;
            color: #fff;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
            margin-top: 10px;
        }
        .content {
            padding: 40px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .details-table td {
            width: 50%;
            vertical-align: top;
        }
        .details-box {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #0ABF96;
            margin-right: 15px;
        }
        .details-box.right {
            margin-right: 0;
            margin-left: 15px;
        }
        .details-box h4 {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 12px;
            letter-spacing: 1px;
            margin-top: 0;
        }
        .details-box .primary {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .detail-line {
            font-size: 12px;
            margin-bottom: 5px;
            color: #475569;
        }
        .detail-line b { color: #1e293b; }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .invoice-table th {
            background: #057F5F;
            color: #fff;
            padding: 12px 15px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        .invoice-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .desc-main { font-weight: bold; font-size: 14px; }
        .desc-sub { color: #64748b; font-size: 12px; }
        
        .total-section {
            text-align: right;
            margin-top: 20px;
            font-size: 22px;
            font-weight: bold;
            color: #057F5F;
        }

        .footer {
            margin-top: 40px;
            width: 100%;
            background: #f8fafc;
            padding: 30px 40px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer .brand { font-weight: bold; color: #057F5F; }
        .footer .contact { font-size: 11px; color: #64748b; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="journal-title">{$journal->getLocalizedName()}</div>
                    <div style="font-size: 12px; opacity: 0.9; margin-top: 5px;">Payment Receipt / Official Invoice</div>
                </td>
                <td>
                    <div class="invoice-title">INVOICE</div>
                    <div class="invoice-info"># {$payment->getId()}</div>
                    <div style="text-align: right;">
                        <span class="status-paid">PAID</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="content">
        <table class="details-table">
            <tr>
                <td>
                    <div class="details-box">
                        <h4>Billed To</h4>
                        <div class="primary">{$user->getFullName()|escape}</div>
                        <div class="detail-line">Email: <b>{$user->getEmail()|escape}</b></div>
                    </div>
                </td>
                <td>
                    <div class="details-box right">
                        <h4>Payment Information</h4>
                        <div class="detail-line">Date: <b>{$dateClean}</b></div>
                        <div class="detail-line">Method: <b>{$paymentMethod|default:"Stripe"}</b></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th style="width: 70%;">Description</th>
                    <th style="width: 30%; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="desc-main">Publication Fee</div>
                        <div class="desc-sub">
                            Submission: {$submission->getCurrentPublication()->getLocalizedTitle()|escape} (ID: {$submission->getId()})
                        </div>
                    </td>
                    <td style="text-align: right; font-weight: bold;">
                        {$payment->getAmount()|string_format:"%.2f"} {$payment->getCurrencyCode()}
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="total-section">
            Total: {$payment->getAmount()|string_format:"%.2f"} {$payment->getCurrencyCode()}
        </div>
    </div>

    <div class="footer">
        <div class="brand">Editorial Management System (ems.pub)</div>
        <div class="contact">
            For support, contact support@ems.pub | www.ems.pub<br>
            Thank you for publishing with us!
        </div>
    </div>
</body>
</html>
