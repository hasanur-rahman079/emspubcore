# EmsPubCore Plugin

A comprehensive OJS plugin that provides journal subscription plans with submission limits, Stripe payment integration, and article processing charge (APC) management.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Architecture](#architecture)
- [API Reference](#api-reference)
- [Troubleshooting](#troubleshooting)
- [Development](#development)
- [License](#license)

---

## Features

### 🎯 Journal Subscription Plans
- **Tiered Plans**: Free, Basic, and Premium tiers with configurable submission limits
- **Yearly Billing**: Annual subscription billing with Stripe integration
- **Usage Tracking**: Automatic yearly submission counting per journal
- **Real-time Display**: Editor dashboard badge showing remaining submissions

### 💳 Stripe Payment Integration
- **Secure Checkout**: Stripe Checkout for PCI-compliant payment processing
- **Webhook Support**: Automatic plan updates via Stripe webhooks
- **Test Mode**: Development sandbox for testing payments
- **Multiple Currencies**: Supports various currencies via Stripe

### 💰 Advanced Discount & Pricing
- **Plan Discounts**: Set global sale prices for any plan tier (e.g., $350 → $320).
- **Journal-Specific Discounts**: Apply additional %-based discounts to specific journals.
- **Stacked Logic**: Journal discounts apply on top of plan sale prices automatically.
- **Admin Management**: Dedicated tabs for Journal Management and Global Payment History.

---

## Requirements

- **OJS Version**: 3.4.0 or higher
- **PHP Version**: 8.1 or higher
- **Stripe Account**: For payment processing
- **SSL Certificate**: Required for Stripe integration

---

## Installation

### Step 1: Install Plugin Files

```bash
# Clone or copy plugin to OJS plugins directory
cp -r emspubcore /path/to/ojs/plugins/generic/
```

### Step 2: Install Dependencies

```bash
cd /path/to/ojs/plugins/generic/emspubcore
composer install
```

### Step 3: Enable Plugin

1. Navigate to **Settings → Website → Plugins → Generic Plugins**
2. Find "EmsPubCore - Journal Subscription Plans"
3. Click **Enable**

### Step 4: Run Database Migrations

The plugin automatically creates required database tables on first enable:
- `emspubcore_journal_plans`
- `emspubcore_submission_usage`
- `emspubcore_payment_history`
- `emspubcore_plans` (site-level plan definitions)

---

## Configuration

### Stripe API Keys

1. Go to [Stripe Dashboard](https://dashboard.stripe.com/apikeys)
2. Copy your API keys

| Setting | Description | Example |
|---------|-------------|---------|
| Publishable Key | Public key for frontend | `pk_test_...` or `pk_live_...` |
| Secret Key | Private key for backend | `sk_test_...` or `sk_live_...` |
| Webhook Secret | Validates webhook events | `whsec_...` |

### Webhook Configuration

Create a webhook endpoint in Stripe Dashboard:

```
https://your-domain.com/index.php/{journal-path}/emspubcore/webhook
```

**Required Events:**
- `checkout.session.completed`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.payment_failed`

### Test Mode

Enable test mode in plugin settings to use Stripe's sandbox:

| Test Card | Behavior |
|-----------|----------|
| `4242 4242 4242 4242` | Successful payment |
| `4000 0000 0000 0002` | Card declined |
| `4000 0000 0000 3220` | 3D Secure required |

Use any future expiry date, any 3-digit CVC, and any postal code.

---

## Usage

### For Site Administrators

**Managing Plans:**
1. Navigate to **Site Administration → Settings**
2. Find the "EmsPubCore Plans" section
3. Create/edit plan tiers with submission limits and pricing

**Assigning Plans to Journals:**
1. Go to **Site Administration → Journals**
2. Edit a journal's settings
3. Select the subscription plan from the dropdown

### For Journal Managers

**Viewing Current Plan:**
- Access the plans page at `/emspubcore/plans`
- See current plan tier, usage, and expiration date

**Upgrading Plan:**
1. Click "Upgrade" on the plans page
2. Complete payment via Stripe Checkout
3. Plan is activated immediately for one year

### For Authors

**Viewing Payment Status:**
1. Access **My Submissions → My Invoices** from the sidebar
2. View all submissions with payment requirements
3. See payment status: Pending, Paid, or Waived

**Making a Payment:**
1. Find the submission with "Pending" status
2. Click "Pay Now"
3. Complete payment via Stripe
4. Download invoice when status shows "Paid"

### For Editors/Admins

**Pending Payments Tab (Admin View):**
- Navigate to **Payments → Pending Payments** tab
- View all submissions with requested publication payments
- Columns: ID, Title, Amount, Payment Type, Actions (View link)
- Sorted by most recent first
- Only shows articles where payment was explicitly requested

**Plugin Management (Site Admins Only):**
- Only Site Administrators can enable/disable the EmsPubCore plugin
- Journal Managers and Editors cannot modify plugin settings
- Plugin tab is hidden from non-admin users in Website Settings

**Monitoring Usage:**
- View remaining submissions in the dashboard header badge
- Format: `Plan Name: Used/Limit` (e.g., "Basic: 45/100")

### 🏷️ Discount Documentation

The plugin supports two types of discounts that **stack** together:

1.  **Standard Plan Discount**: Set a "Discounted Price" in the **Submission Plans** tab. This is a global sale price visible to all journals.
2.  **Journal-Specific Discount**: Set a "Discount (%)" override in the **Journal Management** tab. This is a special rate for a specific journal.

**Calculation Formula:**
`Final Price = (Plan Base or Sale Price) * (1 - Journal Discount %)`

*Example: If a Plan is on sale for $320 and the Journal has a 10% discount, the final checkout price will be $288 ($320 - $32).*

---

## Architecture

### Directory Structure

```
plugins/generic/emspubcore/
├── EmsPubCorePlugin.php          # Main plugin class
├── EmsPubCorePageHandler.php     # HTTP route handlers
├── EmsPubCoreSettingsForm.php    # Admin settings form
├── classes/
│   ├── JournalPlan.php           # Journal plan entity
│   ├── JournalPlanDAO.php        # Journal plan database operations
│   ├── Plan.php                  # Site-level plan entity
│   ├── PlanDAO.php               # Site-level plan database operations
│   ├── PaymentHistoryDAO.php     # Payment logging
│   ├── StripePaymentHandler.php  # Stripe API integration
│   └── SubmissionUsageDAO.php    # Yearly usage tracking
├── controllers/
│   └── grid/                     # Grid handlers for admin views
├── templates/
│   ├── pendingPayments.tpl       # Author payment dashboard
│   ├── plans.tpl                 # Plan selection page
│   ├── invoice.tpl               # Invoice template
│   ├── settingsForm.tpl          # Plugin settings form
│   └── admin*.tpl                # Admin interface templates
├── locale/
│   └── en/locale.po              # English translations
├── schema.xml                    # Database schema definitions
└── version.xml                   # Plugin version info
```

### Database Schema

**`emspubcore_journal_plans`**
| Column | Type | Description |
|--------|------|-------------|
| journal_plan_id | INTEGER | Primary key |
| journal_id | INTEGER | FK to journals |
| plan_type | VARCHAR | free/basic/premium |
| submissions_limit | INTEGER | Yearly submission cap |
| billing_cycle | VARCHAR | yearly (default) |
| plan_start_date | DATETIME | Subscription start |
| plan_end_date | DATETIME | Subscription expiry |
| stripe_customer_id | VARCHAR | Stripe customer reference |
| is_active | BOOLEAN | Plan status |

**`emspubcore_submission_usage`**
| Column | Type | Description |
|--------|------|-------------|
| usage_id | INTEGER | Primary key |
| journal_id | INTEGER | FK to journals |
| year_month | VARCHAR | YYYY-MM format |
| submission_count | INTEGER | Submissions this month |

**`emspubcore_payment_history`**
| Column | Type | Description |
|--------|------|-------------|
| payment_id | INTEGER | Primary key |
| journal_id | INTEGER | FK to journals |
| amount | DECIMAL | Payment amount |
| currency | VARCHAR | Currency code |
| stripe_payment_intent_id | VARCHAR | Stripe reference |
| status | VARCHAR | Payment status |
| plan_type | VARCHAR | Associated plan |
| payment_date | DATETIME | Transaction date |

---

## API Reference

### Routes

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/emspubcore/pendingPayments` | Author payment dashboard |
| GET | `/emspubcore/pendingPaymentsAdmin` | Admin pending payments view |
| GET | `/emspubcore/plans` | Plan selection page |
| POST | `/emspubcore/checkout` | Initiate Stripe checkout |
| GET | `/emspubcore/success` | Payment success callback |
| GET | `/emspubcore/cancel` | Payment cancel callback |
| POST | `/emspubcore/webhook` | Stripe webhook endpoint |
| GET | `/emspubcore/downloadInvoice` | Download payment invoice |

### Hooks Used

| Hook | Purpose |
|------|---------|
| `LoadHandler` | Register custom page handler |
| `TemplateManager::display` | Inject dashboard badge |
| `Schema::get::context` | Add plan fields to context |
| `Submission::add` | Track submission usage |

---

## Troubleshooting

### Common Issues

**Webhook not receiving events:**
1. Verify webhook URL is publicly accessible
2. Check webhook secret matches Stripe Dashboard
3. Ensure SSL certificate is valid

**Plan not updating after payment:**
1. Check PHP error logs for webhook failures
2. Verify Stripe secret key is correct
3. Confirm webhook events are enabled in Stripe

**Submission limit not enforcing:**
1. Ensure plugin is enabled for the journal
2. Check current month's usage in database
3. Verify plan has not expired

### Debug Mode

Enable OJS debug mode in `config.inc.php`:
```php
[debug]
show_stacktrace = On
log_level = INFO
```

---

## Development

### Local Setup

```bash
# Start development server
cd /path/to/ojs
php -S localhost:8000
```

### Repository

```
https://github.com/hasanur-rahman079/emspubcore
```

### Git Workflow

Since this plugin is nested within OJS, run git commands from the plugin directory:

```bash
cd plugins/generic/emspubcore
git add .
git commit -m "Description of changes"
git push origin main
```

### Testing Stripe Locally

Use [Stripe CLI](https://stripe.com/docs/stripe-cli) for local webhook testing:

```bash
stripe listen --forward-to localhost:8000/index.php/journal/emspubcore/webhook
```

---

## Plan Limits

| Plan | Submissions/Month | Monthly | Yearly |
|------|-------------------|---------|--------|
| Free | 5 | - | - |
| Basic | 100 | $29 | $290 |
| Premium | 200 | $49 | $490 |

*Note: Plan limits are configurable by Site Administrators.*

---

## License

GNU GPL v3. See [LICENSE](../../docs/COPYING) for details.

---

## Support

For issues and feature requests:
- **Email**: support@emspub.com
- **GitHub Issues**: [Report a bug](https://github.com/hasanur-rahman079/emspubcore/issues)

---

*Last updated: December 2025*
