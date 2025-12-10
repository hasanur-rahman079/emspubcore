# EmsPubCore Plugin

Journal subscription plans with submission limits and Stripe payment integration for OJS.

## Features

- **Subscription Plans**: Free, Basic, and Premium tiers with different submission limits
- **Monthly/Yearly Billing**: Flexible billing cycles with yearly discount
- **Stripe Integration**: Secure payment processing via Stripe Checkout
- **Submission Limits**: Automatic enforcement of monthly submission caps
- **Editor Badge**: Real-time display of remaining submissions in the dashboard

## Plan Limits

| Plan | Submissions/Month | Monthly Price | Yearly Price |
|------|-------------------|---------------|--------------|
| Free | 5 | - | - |
| Basic | 100 | $29 | $290 |
| Premium | 200 | $49 | $490 |

## Installation

1. Copy the `emspubcore` folder to `plugins/generic/`
2. Navigate to **Settings > Website > Plugins**
3. Enable "EmsPubCore - Journal Subscription Plans"
4. Configure Stripe API keys in plugin settings

## Configuration

### Stripe Settings

1. Go to your [Stripe Dashboard](https://dashboard.stripe.com)
2. Copy your API keys:
   - **Publishable Key**: `pk_test_...` or `pk_live_...`
   - **Secret Key**: `sk_test_...` or `sk_live_...`
3. Create a webhook endpoint pointing to:
   ```
   https://your-site.com/index.php/{journal-path}/emspubcore/webhook
   ```
4. Copy the Webhook Secret and add it to plugin settings

### Test Mode

Enable "Test Mode" in plugin settings to use Stripe's sandbox environment.

Test card number: `4242 4242 4242 4242`

## Usage

### For Site Administrators

1. Enable the plugin for each journal
2. Assign plans via **Site Administration > Journals > Edit**

### For Journal Managers

1. View current plan at `/emspubcore/plans`
2. Click "Upgrade" to start checkout
3. Complete payment via Stripe

### For Editors

- View remaining submissions in the dashboard header badge
- Format: `Plan Name: X/Y` (e.g., "Basic: 45/100")

## Webhook Events

The plugin handles these Stripe webhook events:

- `checkout.session.completed` - Activates subscription
- `customer.subscription.updated` - Updates plan end date
- `customer.subscription.deleted` - Downgrades to Free
- `invoice.payment_failed` - Deactivates plan

## Database Tables

- `emspubcore_journal_plans` - Subscription plans
- `emspubcore_submission_usage` - Monthly usage tracking
- `emspubcore_payment_history` - Payment records

## License

GNU GPL v3

## Support

For issues and feature requests, please contact support@emspub.com
