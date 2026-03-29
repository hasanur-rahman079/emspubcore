{**
 * plugins/generic/emspubcore/templates/plans.tpl
 *
 * Plan selection / upgrade page (Yearly billing)
 *}
{include file="frontend/components/header.tpl"}

{literal}
<style>
/* Plans page – scoped to .emspubcore-plans-page */
.emspubcore-plans-page {
    max-width: 960px;
    margin: 40px auto;
    padding: 0 24px 60px;
}

.emspubcore-plans-page h1 {
    font-size: 26px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

.emspubcore-plans-page .plans-subtitle {
    font-size: 15px;
    color: #64748b;
    margin-bottom: 32px;
}

/* Current plan notice */
.ems-current-notice {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 8px;
    padding: 14px 20px;
    margin-bottom: 32px;
    font-size: 14px;
    color: #0369a1;
}

.ems-current-notice svg {
    flex-shrink: 0;
}

/* Cards grid */
.emspubcore-plan-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

/* Individual card */
.emspubcore-plan-card {
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 28px 24px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
    position: relative;
}

.emspubcore-plan-card:hover {
    border-color: #006798;
    box-shadow: 0 6px 24px rgba(0, 103, 152, 0.12);
    transform: translateY(-2px);
}

.emspubcore-plan-card.is-current {
    border-color: #0ABF96;
    box-shadow: 0 4px 16px rgba(10, 191, 150, 0.12);
}

.emspubcore-plan-card.is-popular {
    border-color: #006798;
    box-shadow: 0 4px 20px rgba(0, 103, 152, 0.14);
}

/* Popular badge */
.plan-popular-badge {
    position: absolute;
    top: -12px;
    left: 50%;
    transform: translateX(-50%);
    background: #006798;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 4px 12px;
    border-radius: 20px;
    white-space: nowrap;
}

/* Plan name */
.plan-name {
    font-size: 17px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
}

/* Price block */
.plan-price-wrap {
    margin-bottom: 12px;
}

.plan-price {
    font-size: 36px;
    font-weight: 800;
    color: #006798;
    line-height: 1;
}

.plan-price-original {
    font-size: 18px;
    color: #94a3b8;
    text-decoration: line-through;
    margin-right: 6px;
}

.plan-period {
    font-size: 14px;
    color: #64748b;
    font-weight: 400;
    margin-left: 2px;
}

.plan-discount-badge {
    display: inline-block;
    background: #dcfce7;
    color: #166534;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    margin-top: 6px;
}

/* Limit */
.plan-limit {
    font-size: 13px;
    color: #475569;
    margin-bottom: 24px;
    padding-top: 12px;
    border-top: 1px solid #f1f5f9;
    width: 100%;
}

/* Buttons */
.plan-cta {
    margin-top: auto;
    width: 100%;
    display: block;
    text-align: center;
    padding: 11px 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.15s, color 0.15s;
}

.plan-cta-primary {
    background: #006798;
    color: #fff;
    border: 2px solid #006798;
}

.plan-cta-primary:hover {
    background: #00517a;
    border-color: #00517a;
    color: #fff;
    text-decoration: none;
}

.plan-cta-current {
    background: #f1f5f9;
    color: #64748b;
    border: 2px solid #e2e8f0;
    cursor: default;
    pointer-events: none;
}

/* Info footer */
.plans-info-footer {
    text-align: center;
    font-size: 13px;
    color: #94a3b8;
    margin-top: 8px;
}

.plans-info-footer a {
    color: #006798;
}

@media (max-width: 600px) {
    .emspubcore-plan-cards {
        grid-template-columns: 1fr;
    }
    .emspubcore-plans-page h1 {
        font-size: 22px;
    }
}
</style>
{/literal}

<div class="page emspubcore-plans-page">

    <h1>{translate key="plugins.generic.emspubcore.selectPlan"}</h1>
    <p class="plans-subtitle">All plans are billed yearly. Upgrade or renew anytime.</p>

    {if $currentPlan}
        <div class="ems-current-notice">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>
                <strong>Current plan:</strong> {$currentPlan->getPlanType()|ucfirst}
                &mdash; {$currentUsage} / {if $currentPlan->getSubmissionsLimit() == 0}Unlimited{else}{$currentPlan->getSubmissionsLimit()}{/if}
                {translate key="plugins.generic.emspubcore.submissionsPerYear"}
            </span>
        </div>
    {/if}

    <div class="emspubcore-plan-cards">

        <!-- Free Plan -->
        <div class="emspubcore-plan-card {if !$currentPlan || $currentPlan->getPlanType() == 'free'}is-current{/if}">
            <div class="plan-name">{translate key="plugins.generic.emspubcore.plan.free"}</div>
            <div class="plan-price-wrap">
                <span class="plan-price">$0</span>
                <span class="plan-period">/yr</span>
            </div>
            <div class="plan-limit">
                {$planLimits.free} {translate key="plugins.generic.emspubcore.submissionsPerYear"}
            </div>
            {if !$currentPlan || $currentPlan->getPlanType() == 'free'}
                <span class="plan-cta plan-cta-current">{translate key="common.current"}</span>
            {/if}
        </div>

        <!-- Basic Plan -->
        <div class="emspubcore-plan-card {if $currentPlan && $currentPlan->getPlanType() == 'basic'}is-current{/if}">
            <div class="plan-name">{translate key="plugins.generic.emspubcore.plan.basic"}</div>
            <div class="plan-price-wrap">
                <span class="plan-price">${math equation="x/100" x=$planPrices.basic.yearly}</span>
                <span class="plan-period">/yr</span>
            </div>
            <div class="plan-limit">
                {$planLimits.basic} {translate key="plugins.generic.emspubcore.submissionsPerYear"}
            </div>
            {if $currentPlan && $currentPlan->getPlanType() == 'basic'}
                <span class="plan-cta plan-cta-current">{translate key="common.current"}</span>
            {else}
                <a href="{url page="emspubcore" op="checkout" plan="basic" billing="yearly"}"
                   class="plan-cta plan-cta-primary">
                    {translate key="plugins.generic.emspubcore.upgrade"}
                </a>
            {/if}
        </div>

        <!-- Premium Plan -->
        <div class="emspubcore-plan-card {if $currentPlan && $currentPlan->getPlanType() == 'premium'}is-current{/if} is-popular">
            <div class="plan-popular-badge">Most Popular</div>
            <div class="plan-name">{translate key="plugins.generic.emspubcore.plan.premium"}</div>
            <div class="plan-price-wrap">
                <span class="plan-price">${math equation="x/100" x=$planPrices.premium.yearly}</span>
                <span class="plan-period">/yr</span>
            </div>
            <div class="plan-limit">
                {$planLimits.premium} {translate key="plugins.generic.emspubcore.submissionsPerYear"}
            </div>
            {if $currentPlan && $currentPlan->getPlanType() == 'premium'}
                <span class="plan-cta plan-cta-current">{translate key="common.current"}</span>
            {else}
                <a href="{url page="emspubcore" op="checkout" plan="premium" billing="yearly"}"
                   class="plan-cta plan-cta-primary">
                    {translate key="plugins.generic.emspubcore.upgrade"}
                </a>
            {/if}
        </div>

    </div>

    <p class="plans-info-footer">
        Payments are processed securely via Stripe. All prices in USD.
    </p>

</div>

{include file="frontend/components/footer.tpl"}
