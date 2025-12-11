<?php

/**
 * @file plugins/generic/emspubcore/classes/JournalPlanDAO.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class JournalPlanDAO
 *
 * @ingroup plugins_generic_emspubcore
 *
 * @brief DAO for journal plan operations
 */

namespace APP\plugins\generic\emspubcore\classes;

use PKP\db\DAO;
use PKP\core\Core;
use Illuminate\Support\Facades\DB;

class JournalPlanDAO extends DAO
{
    /**
     * Create a new JournalPlan object
     */
    public function newDataObject(): JournalPlan
    {
        return new JournalPlan();
    }

    /**
     * Get a journal plan by journal ID
     */
    public function getByJournalId(int $journalId): ?JournalPlan
    {
        $result = DB::table('emspubcore_journal_plans')
            ->where('journal_id', $journalId)
            ->first();

        if (!$result) {
            return null;
        }

        return $this->fromRow((array) $result);
    }

    /**
     * Get a journal plan by Stripe subscription ID
     */
    public function getByStripeSubscriptionId(string $subscriptionId): ?JournalPlan
    {
        $result = DB::table('emspubcore_journal_plans')
            ->where('stripe_subscription_id', $subscriptionId)
            ->first();

        if (!$result) {
            return null;
        }

        return $this->fromRow((array) $result);
    }

    /**
     * Insert a new journal plan
     */
    public function insertObject(JournalPlan $plan): int
    {
        $now = Core::getCurrentDate();
        
        $planId = DB::table('emspubcore_journal_plans')->insertGetId([
            'journal_id' => $plan->getJournalId(),
            'plan_type' => $plan->getPlanType(),
            'billing_cycle' => $plan->getBillingCycle(),
            'submissions_limit' => $plan->getSubmissionsLimit(),
            'stripe_subscription_id' => $plan->getStripeSubscriptionId(),
            'stripe_customer_id' => $plan->getStripeCustomerId(),
            'plan_start_date' => $plan->getPlanStartDate(),
            'plan_end_date' => $plan->getPlanEndDate(),
            'is_active' => $plan->getIsActive() ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'plan_id');

        $plan->setPlanId($planId);
        return $planId;
    }

    /**
     * Update a journal plan
     */
    public function updateObject(JournalPlan $plan): void
    {
        DB::table('emspubcore_journal_plans')
            ->where('plan_id', $plan->getPlanId())
            ->update([
                'plan_type' => $plan->getPlanType(),
                'billing_cycle' => $plan->getBillingCycle(),
                'submissions_limit' => $plan->getSubmissionsLimit(),
                'stripe_subscription_id' => $plan->getStripeSubscriptionId(),
                'stripe_customer_id' => $plan->getStripeCustomerId(),
                'plan_start_date' => $plan->getPlanStartDate(),
                'plan_end_date' => $plan->getPlanEndDate(),
                'is_active' => $plan->getIsActive() ? 1 : 0,
                'updated_at' => Core::getCurrentDate(),
            ]);
    }

    /**
     * Delete a journal plan
     */
    public function deleteObject(int $planId): void
    {
        DB::table('emspubcore_journal_plans')
            ->where('plan_id', $planId)
            ->delete();
    }

    /**
     * Delete by journal ID
     */
    public function deleteByJournalId(int $journalId): void
    {
        DB::table('emspubcore_journal_plans')
            ->where('journal_id', $journalId)
            ->delete();
    }

    /**
     * Update or insert a plan for a journal
     */
    public function upsert(JournalPlan $plan): int
    {
        $existing = $this->getByJournalId($plan->getJournalId());
        
        if ($existing) {
            $plan->setPlanId($existing->getPlanId());
            $this->updateObject($plan);
            return $existing->getPlanId();
        }
        
        return $this->insertObject($plan);
    }

    /**
     * Create JournalPlan from database row
     */
    public function fromRow(array $row): JournalPlan
    {
        $plan = new JournalPlan();
        $plan->setPlanId((int) $row['plan_id']);
        $plan->setJournalId((int) $row['journal_id']);
        $plan->setPlanType($row['plan_type']);
        $plan->setBillingCycle($row['billing_cycle'] ?? 'yearly');
        $plan->setSubmissionsLimit((int) $row['submissions_limit']);
        $plan->setStripeSubscriptionId($row['stripe_subscription_id'] ?? null);
        $plan->setStripeCustomerId($row['stripe_customer_id'] ?? null);
        $plan->setPlanStartDate($row['plan_start_date'] ?? null);
        $plan->setPlanEndDate($row['plan_end_date'] ?? null);
        $plan->setIsActive((bool) ($row['is_active'] ?? true));
        return $plan;
    }

    /**
     * Get all active plans
     */
    public function getActivePlans(): array
    {
        $results = DB::table('emspubcore_journal_plans')
            ->where('is_active', 1)
            ->get();

        $plans = [];
        foreach ($results as $row) {
            $plans[] = $this->fromRow((array) $row);
        }
        return $plans;
    }

    /**
     * Get expired plans (for cleanup/notifications)
     */
    public function getExpiredPlans(): array
    {
        $results = DB::table('emspubcore_journal_plans')
            ->where('is_active', 1)
            ->where('plan_end_date', '<', Core::getCurrentDate())
            ->whereNotNull('plan_end_date')
            ->get();

        $plans = [];
        foreach ($results as $row) {
            $plans[] = $this->fromRow((array) $row);
        }
        return $plans;
    }
}
