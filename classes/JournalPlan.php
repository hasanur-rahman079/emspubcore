<?php

/**
 * @file plugins/generic/emspubcore/classes/JournalPlan.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class JournalPlan
 *
 * @ingroup plugins_generic_emspubcore
 *
 * @brief Data object representing a journal's subscription plan
 */

namespace APP\plugins\generic\emspubcore\classes;

use PKP\core\DataObject;

class JournalPlan extends DataObject
{
    // Plan types
    const PLAN_FREE = 'free';
    const PLAN_BASIC = 'basic';
    const PLAN_PREMIUM = 'premium';

    // Billing cycles
    const BILLING_MONTHLY = 'monthly';
    const BILLING_YEARLY = 'yearly';

    /**
     * Get plan ID
     */
    public function getPlanId(): int
    {
        return $this->getData('plan_id');
    }

    /**
     * Set plan ID
     */
    public function setPlanId(int $planId): void
    {
        $this->setData('plan_id', $planId);
    }

    /**
     * Get journal ID
     */
    public function getJournalId(): int
    {
        return $this->getData('journal_id');
    }

    /**
     * Set journal ID
     */
    public function setJournalId(int $journalId): void
    {
        $this->setData('journal_id', $journalId);
    }

    /**
     * Get plan type (free, basic, premium)
     */
    public function getPlanType(): string
    {
        return $this->getData('plan_type') ?? self::PLAN_FREE;
    }

    /**
     * Set plan type
     */
    public function setPlanType(string $planType): void
    {
        $this->setData('plan_type', $planType);
    }

    /**
     * Get billing cycle (monthly, yearly)
     */
    public function getBillingCycle(): string
    {
        return $this->getData('billing_cycle') ?? self::BILLING_YEARLY;
    }

    /**
     * Set billing cycle
     */
    public function setBillingCycle(string $billingCycle): void
    {
        $this->setData('billing_cycle', $billingCycle);
    }

    /**
     * Get submissions limit
     */
    public function getSubmissionsLimit(): int
    {
        return (int) $this->getData('submissions_limit');
    }

    /**
     * Set submissions limit
     */
    public function setSubmissionsLimit(int $limit): void
    {
        $this->setData('submissions_limit', $limit);
    }

    /**
     * Get Stripe subscription ID
     */
    public function getStripeSubscriptionId(): ?string
    {
        return $this->getData('stripe_subscription_id');
    }

    /**
     * Set Stripe subscription ID
     */
    public function setStripeSubscriptionId(?string $subscriptionId): void
    {
        $this->setData('stripe_subscription_id', $subscriptionId);
    }

    /**
     * Get Stripe customer ID
     */
    public function getStripeCustomerId(): ?string
    {
        return $this->getData('stripe_customer_id');
    }

    /**
     * Set Stripe customer ID
     */
    public function setStripeCustomerId(?string $customerId): void
    {
        $this->setData('stripe_customer_id', $customerId);
    }

    /**
     * Get plan start date
     */
    public function getPlanStartDate(): ?string
    {
        return $this->getData('plan_start_date');
    }

    /**
     * Set plan start date
     */
    public function setPlanStartDate(?string $date): void
    {
        $this->setData('plan_start_date', $date);
    }

    /**
     * Get plan end date
     */
    public function getPlanEndDate(): ?string
    {
        return $this->getData('plan_end_date');
    }

    /**
     * Set plan end date
     */
    public function setPlanEndDate(?string $date): void
    {
        $this->setData('plan_end_date', $date);
    }

    /**
     * Check if plan is active
     */
    public function getIsActive(): bool
    {
        return (bool) $this->getData('is_active');
    }

    /**
     * Set plan active status
     */
    public function setIsActive(bool $isActive): void
    {
        $this->setData('is_active', $isActive ? 1 : 0);
    }

    /**
     * Check if plan is a paid plan
     */
    public function isPaidPlan(): bool
    {
        return in_array($this->getPlanType(), [self::PLAN_BASIC, self::PLAN_PREMIUM]);
    }

    /**
     * Check if plan is expired
     */
    public function isExpired(): bool
    {
        $endDate = $this->getPlanEndDate();
        if (!$endDate) {
            return false;
        }
        return strtotime($endDate) < time();
    }
}
