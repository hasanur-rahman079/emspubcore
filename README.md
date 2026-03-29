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
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Development](#development)
- [Roadmap](#roadmap)
- [License](#license)

---

## Features

### 🎯 Journal Subscription Plans
- **Tiered Plans**: Free, Basic, and Premium tiers with configurable submission limits
- **Yearly Billing**: Annual subscription billing with payment gateway integration
- **Usage Tracking**: Automatic yearly submission counting per journal
- **Real-time Display**: Editor dashboard badge showing remaining submissions

### 💳 Payment Gateway Integration
- **Stripe Support**: Stripe Checkout for PCI-compliant payment processing
- **Paddle Support**: Paddle Billing for simplified international payments
- **Webhook Support**: Automatic plan updates via signed webhooks
- **Test Mode**: Development sandbox for testing payments
- **Multiple Currencies**: Supports various currencies via both gateways

### 💰 Advanced Discount & Pricing
- **Plan Discounts**: Set global sale prices for any plan tier (e.g., $350 → $320).
- **Journal-Specific Discounts**: Apply additional %-based discounts to specific journals.
- **Stacked Logic**: Journal discounts apply on top of plan sale prices automatically.
- **Admin Management**: Dedicated tabs for Journal Management and Global Payment History.

### 📄 Article Processing Charges (APC)
- **Journal-Level Paddle Product ID**: Configure Paddle Product ID per journal for APC payments
- **My Invoices Dashboard**: Authors can view and pay pending APCs
- **Invoice Generation**: PDF invoices for completed payments

---

## Requirements

- **OJS Version**: 3.4.0 or higher
- **PHP Version**: 8.1 or higher
- **Payment Gateway**: Stripe or Paddle account
- **SSL Certificate**: Required for payment integration

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
composer install --no-dev
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

### Paddle API Keys

1. Go to [Paddle Dashboard](https://vendors.paddle.com/authentication)
2. Copy your API keys

| Setting | Description | Example |
|---------|-------------|---------|
| Vendor ID | Your Paddle vendor ID | `12345` |
| API Key | Server-side API key | `...` |
| Client Token | For Paddle.js frontend | `live_...` or `test_...` |
| Webhook Secret | For signature verification | `pdl_ntfset_...` |

### Paddle APC Product ID (Journal-Level)

For journal-specific APC payments via Paddle:
1. Navigate to **Payments → Payment Types** in the journal settings
2. Enter the Paddle Product ID for APC in the "Paddle Product ID for APC" field
3. Save - the value is stored via AJAX and persists across page loads

### Webhook Configuration

**Stripe Webhook:**
```
https://your-domain.com/index.php/{journal-path}/emspubcore/webhook
```

**Paddle Webhook:**
```
https://your-domain.com/index.php/{journal-path}/emspubcore/webhook
```

**Required Stripe Events:**
- `checkout.session.completed`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.payment_failed`

**Required Paddle Events:**
- `subscription.created`
- `subscription.updated`
- `subscription.canceled`
- `transaction.completed`

### Test Mode

Enable test mode in plugin settings to use sandbox environments.

**Stripe Test Cards:**
| Test Card | Behavior |
|-----------|----------|
| `4242 4242 4242 4242` | Successful payment |
| `4000 0000 0000 0002` | Card declined |
| `4000 0000 0000 3220` | 3D Secure required |

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
2. Complete payment via Stripe/Paddle Checkout
3. Plan is activated immediately for one year

**Configuring APC Payments (Paddle):**
1. Go to **Payments → Payment Types**
2. Enter the Paddle Product ID for APC
3. Save the form

### For Authors

**Viewing Payment Status:**
1. Access **My Submissions → My Invoices** from the sidebar
2. View all submissions with payment requirements
3. See payment status: Pending, Paid, or Waived

**Making a Payment:**
1. Find the submission with "Pending" status
2. Click "Pay Now"
3. Complete payment via Stripe or Paddle
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

## 💰 Subscription & Pricing

The EmsPubCore plugin implements a tiered subscription model designed for sustainability and scale. All plans are billed **annually** and integrated with Stripe and Paddle for seamless global transactions.

### 📊 Subscription Tiers

| Tier | Submission Limit | Ideal For | Key Benefit |
|------|------------------|-----------|-------------|
| **Free** | 5 Submissions/Year | New Journals | Zero-cost setup |
| **Basic** | 100 Submissions/Year | Regular Journals | Affordable scaling |
| **Premium** | 200 Submissions/Year | High-volume Journals | Lower cost per article |
| **Enterprise** | Custom Limits | Publishing Houses | Dedicated support & unlimited scale |
| **BSMIAB** | Custom Limits | Special Partners | Exclusive partnership rates |

> [!NOTE]
> All limits and prices are fully configurable by Site Administrators through the **Submission Plans** dashboard.

### 🔄 Subscription Lifecycle & Carryover Rules

We use a "Customer-First" carryover policy to ensure you never lose what you've paid for:

1.  **Upgrade (Switching Plans)**: If you upgrade from Basic to Premium before your year is up, your **remaining unused submissions** from the Basic plan are automatically added to your new Premium limit.
2.  **Renewal (Same Plan)**: When you renew the same plan tier, the submission counter resets to 0 and your new annual limit is applied for the upcoming year.
3.  **Expiry**: If a plan expires without renewal, the journal reverts to the **Free Tier** (5 submissions/year) until a new plan is purchased.

---

## ❓ Frequently Asked Questions (FAQ)

### Payments & Billing

**Q: Which payment methods are supported?**
A: We support all major credit cards, digital wallets (Apple Pay, Google Pay), and regional payment methods via Stripe and Paddle.

**Q: Can I get a formal invoice for my institution?**
A: Yes. Every successful transaction automatically generates a professional PDF invoice available for download in the **My Invoices** (for authors) or **Plan** (for journals) dashboard.

**Q: How do stacked discounts work?**
A: If a plan is on sale globally (e.g., 10% off Original Price) and your journal has a specific partner discount (e.g., 20% off), the discounts stack. Your final price will be `Sale Price * (1 - Partner Discount)`.

### Plan Management

**Q: What happens if I reach my submission limit?**
A: New submissions will be temporarily restricted for that journal. You can either **Renew** your current plan early to reset the counter or **Upgrade** to a higher tier instantly.

**Q: Do my unused submissions expire?**
A: Unused submissions carry over **only during an upgrade**. During a standard annual renewal of the same plan, the counter resets to providing the full new limit for the next year.

**Q: Can a Site Admin manually change my plan?**
A: Yes. Site Administrators have an "Admin Override" feature to activate or upgrade plans manually (e.g., for partner journals or institutional grants) without a checkout step.

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
│   ├── PaddlePaymentHandler.php  # Paddle API integration
│   └── SubmissionUsageDAO.php    # Yearly usage tracking
├── controllers/
│   ├── StripeWebhookHandler.php  # Stripe webhook processing
│   ├── PaddleWebhookHandler.php  # Paddle webhook processing
│   └── grid/                     # Grid handlers for admin views
├── templates/
│   ├── pendingPayments.tpl       # Author payment dashboard
│   ├── plans.tpl                 # Plan selection page
│   ├── invoice.tpl               # Invoice template
│   ├── settingsForm.tpl          # Plugin settings form
│   ├── payments/                 # Payment form overrides
│   └── admin*.tpl                # Admin interface templates
├── locale/
│   ├── en/locale.po              # English translations
│   └── en_US/locale.po           # US English translations
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
| paddle_customer_id | VARCHAR | Paddle customer reference |
| paddle_subscription_id | VARCHAR | Paddle subscription reference |
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
| transaction_id | VARCHAR | Gateway transaction reference |
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
| POST | `/emspubcore/checkout` | Initiate payment checkout |
| GET | `/emspubcore/success` | Payment success callback |
| GET | `/emspubcore/paddleSuccess` | Paddle payment success |
| GET | `/emspubcore/cancel` | Payment cancel callback |
| POST | `/emspubcore/webhook` | Webhook endpoint (Stripe/Paddle) |
| GET | `/emspubcore/downloadInvoice` | Download payment invoice |
| GET | `/emspubcore/getPaddleApcProductId` | AJAX: Get Paddle Product ID |
| POST | `/emspubcore/savePaddleApcProductId` | AJAX: Save Paddle Product ID |

### Hooks Used

| Hook | Purpose |
|------|---------|
| `LoadHandler` | Register custom page handler |
| `TemplateManager::display` | Inject dashboard badge |
| `Schema::get::context` | Add plan fields to context |
| `Submission::add` | Track submission usage |
| `Templates::Payments::*` | Override payment templates |

---

## Security

### Security Audit Summary (January 2026)

#### Production Readiness Ratings

| Plugin | Security | Stability | Production Ready |
|--------|----------|-----------|------------------|
| **emspubcore** | 85% | 80% | ✅ Yes |
| **emspubstripe** | 80% | 75% | ✅ Yes |
| **emspubpaddle** | 85% | 80% | ✅ Yes |

#### ✅ Implemented Security Measures

| Feature | Status |
|---------|--------|
| **Webhook Signature Verification** | ✅ Paddle SDK verifier + Stripe HMAC-SHA256 |
| **Rate Limiting** | ✅ 60 requests/minute per IP on webhook endpoints |
| **Replay Attack Protection** | ✅ 5-minute timestamp tolerance on webhooks |
| **Authorization Checks** | ✅ Role-based access control (Site Admin, Manager) |
| **SQL Injection Prevention** | ✅ Uses Eloquent ORM / Query Builder exclusively |
| **XSS Prevention** | ✅ Template output uses `\|escape` modifiers |
| **Error Handling** | ✅ Try/catch blocks with `error_log()` logging |

#### Security Best Practices

1. **Webhook Secrets Required**: Always configure webhook secrets for both Stripe and Paddle
2. **HTTPS Required**: Never run payment integrations over HTTP
3. **Test Mode**: Always test in sandbox before going live
4. **Log Monitoring**: Monitor PHP error logs for webhook failures
5. **Secret Rotation**: Rotate API keys and webhook secrets periodically

#### ⚠️ Known Limitations

- **CSRF Tokens**: Not fully validated on some AJAX endpoints (templates need token passing fix)
- **Stripe Webhook Secret**: Currently optional - recommend making required in production

---

## Troubleshooting

### Common Issues

**Webhook not receiving events:**
1. Verify webhook URL is publicly accessible
2. Check webhook secret matches gateway dashboard
3. Ensure SSL certificate is valid
4. Check server logs for 4xx/5xx responses

**Plan not updating after payment:**
1. Check PHP error logs for webhook failures
2. Verify API keys are correct
3. Confirm webhook events are enabled in gateway dashboard

**Submission limit not enforcing:**
1. Ensure plugin is enabled for the journal
2. Check current month's usage in database
3. Verify plan has not expired

**Paddle APC Product ID not saving:**
1. Ensure emspubcore plugin is enabled at site level
2. Check browser console for AJAX errors
3. Verify user has Manager or Site Admin role

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

### Related Repositories

- **emspubstripe**: `plugins/paymethod/emspubstripe` - Stripe payment method plugin 
```
https://github.com/hasanur-rahman079/emspubstripe.git
```
- **emspubpaddle**: `plugins/paymethod/emspubpaddle` - Paddle payment method plugin
```
https://github.com/hasanur-rahman079/emspubpaddle.git
```

### Git Workflow

Since this plugin is nested within OJS, run git commands from the plugin directory:

```bash
cd plugins/generic/emspubcore
git add .
git commit -m "Description of changes"
git push origin main
```

### Testing Payments Locally

**Stripe CLI:**
```bash
stripe listen --forward-to localhost:8000/index.php/journal/emspubcore/webhook
```

**Paddle (use ngrok):**
```bash
ngrok http 8000
# Update webhook URL in Paddle dashboard with ngrok URL
```

---

## Roadmap

### Future Improvements

#### High Priority (January 2026)
- [x] **Paddle Webhook URL Display**: Added webhook URL field with copy button and webhook secret input to Site Settings → Payment Gateways.
- [ ] **CSRF Token Validation**: Fix template token passing.
- [x] **Rate Limiting**: Implemented 60 req/min/IP limiter.
- [x] **Unit Tests**: Created test suite in `tests/`.
- [x] **Paddle APC Payment Bug Fix**: Fixed payment completion status.

#### Medium Priority
- [ ] Refactor handler into controllers. Refactor `EmsPubCorePageHandler.php` (1400+ lines) into smaller controllers:
  - `PaymentController`
  - `PlanController`
  - `WebhookController`
  - `InvoiceController`
- [ ] Add input sanitization.
- [x] **Admin Assignment Feature**: Implemented site-admin manual plan assignment with professional UI and carryover logic.

#### Low Priority
- [ ] Email notifications.
- [ ] Dashboard analytics.

---

## License

GNU GPL v3. See [LICENSE](../../docs/COPYING) for details.

---

## Support

For issues and feature requests:
- **Email**: support@ems.pub
- **GitHub Issues**: [Report a bug](https://github.com/hasanur-rahman079/emspubcore/issues)

---

*Last updated: January 3, 2026*

