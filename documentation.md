# EmsPubCore Plugin: Paddle Billing v2 Integration Documentation

This document provides a comprehensive overview of the Paddle Billing v2 integration implemented in the `emspubcore` plugin.

## 1. Core Integration Features

### Paddle SDK & API
- **SDK Namespace**: Uses the modern `\Paddle\SDK\Client` for all interactions.
- **Environment Support**: Handles both `Sandbox` and `Production` environments based on the "Test Mode" setting in the plugin.
- **Raw API Access**: Employs `postRaw()` and `getRaw()` for creates and status checks to ensure maximum compatibility with the latest Paddle v2 response structures.

### Plan Retrieval & Robust Lookups
- **Source of Truth**: Plans are retrieved directly from the `emspubcore_plans` database table via `PlanDAO`.
- **Case-Insensitive Lookups**: The integration handles various capitalization styles (e.g., "BSMIAB", "Bsmiab", "bsmiab") to prevent "Price ID not configured" errors during checkout transitions.

## 2. Checkout Configuration

### Non-Catalog (Ad-hoc) Pricing
To support dynamic **Journal Discounts** configured in OJS, the plugin creates "non-catalog" prices on the fly while linking them to your existing Paddle Product IDs.
- **Calculation**: `Total = (Plan Price OR Discounted Plan Price) * (1 - Journal Discount / 100)`.
- **Tax Handling**: Paddle treats the amount sent as the **Tax-Inclusive** total by default. VAT is automatically calculated and "backed out" from the subtotal based on the customer's location.

### Quantity Selection Lock & Carryover
To maintain the integrity of subscription plans and ensure fair usage:
- **Quantity Lock**: The ad-hoc price object explicitly defines `minimum: 1` and `maximum: 1` to hide the quantity selector in the overlay.
- **Submission Carryover**: Upon **upgrade** (changing to a different plan type), the system calculates unused submissions (`Limit - Usage`) and adds them to the new plan's base limit. **Renewing the same plan** resets the limit to the base amount without carryover.

## 3. Post-Payment & Verification

### Reliable Redirects
- **Transaction Tracking**: Correctly retrieves the `transaction_id` from the Paddle event callback (`data.data.transaction_id`).
- **Success Link**: Automatically redirects the user to the **Workflow Settings > Plans** tab (`#emspubcorePlan`) after a 3-second success message display.

### Payment Logging
- **Database Consistency**: Payments are logged into the `emspubcore_payment_history` table.
- **Amount Format**: Stored in **cents** (integers) to match OJS standards and ensure precise reporting.
- **Dynamic Payment Method**: Invoices detect and display "Paddle" or "Stripe" based on the transaction ID prefix (`txn_` = Paddle).

### Payment History UI
The Workflow > Plans tab includes a professional payment history table with:
- **Modern Card Design**: Rounded corners, subtle shadows, gradient header.
- **Invoice Badges**: Green "INV" badge with monospace number formatting.
- **Status Badges**: Gradient green badges with checkmark icons for completed payments.
- **Plan Badges**: Color-coded pills (Basic = blue, Premium = purple, Enterprise = gold).
- **Client-Side Pagination**: 5 items per page with Previous/Next buttons and clickable page numbers.
- **Download Button**: Animated icon that turns green on hover.

## 4. Maintenance & Support

### API Testing (Sandbox)
Verify connectivity using your Sandbox Key:
```bash
curl https://sandbox-api.paddle.com/event-types \
     -H "Authorization: Bearer YOUR_SANDBOX_KEY"
```

### Troubleshooting Results
- **"Something went wrong" (Paddle UI)**: Usually occurs if an invalid `tax_category` is sent or if `custom_data` contains non-string types.
- **500 Error (OJS)**: Ensure `Dispatcher::url` calls use arrays for the path argument (e.g., `['workflow']`).
