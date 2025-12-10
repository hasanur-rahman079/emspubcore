<?php

/**
 * @file plugins/generic/emspubcore/classes/PlanDAO.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class PlanDAO
 *
 * @ingroup plugins_generic_emspubcore
 *
 * @brief DAO for managing defined subscription plans
 */

namespace APP\plugins\generic\emspubcore\classes;

use PKP\db\DAO;
use Illuminate\Support\Facades\DB;

class PlanDAO extends DAO
{
    /**
     * Create a new Plan object
     */
    public function newDataObject()
    {
        return new Plan();
    }

    /**
     * Get a plan by ID
     */
    public function getById($planId)
    {
        $result = DB::table('emspubcore_plans')
            ->where('plan_id', $planId)
            ->first();

        if (!$result) {
            return null;
        }

        return $this->fromRow((array) $result);
    }

    /**
     * Get all plans
     */
    public function getAll()
    {
        $results = DB::table('emspubcore_plans')
            ->orderBy('price', 'asc')
            ->get();

        $plans = [];
        foreach ($results as $row) {
            $plans[] = $this->fromRow((array) $row);
        }
        return $plans;
    }

    /**
     * Insert a new plan
     */
    public function insertObject(Plan $plan)
    {
        $planId = DB::table('emspubcore_plans')->insertGetId([
            'name' => $plan->getName(),
            'price' => $plan->getPrice(),
            'discounted_price' => $plan->getDiscountedPrice(),
            'submission_limit' => $plan->getSubmissionLimit(),
            'description' => $plan->getDescription(),
        ], 'plan_id');

        $plan->setId($planId);
        return $planId;
    }

    /**
     * Update a plan
     */
    public function updateObject(Plan $plan)
    {
        DB::table('emspubcore_plans')
            ->where('plan_id', $plan->getId())
            ->update([
                'name' => $plan->getName(),
                'price' => $plan->getPrice(),
                'discounted_price' => $plan->getDiscountedPrice(),
                'submission_limit' => $plan->getSubmissionLimit(),
                'description' => $plan->getDescription(),
            ]);
    }

    /**
     * Delete a plan
     */
    public function deleteObject($planId)
    {
        DB::table('emspubcore_plans')
            ->where('plan_id', $planId)
            ->delete();
    }

    /**
     * Create Plan from database row
     */
    public function fromRow(array $row)
    {
        $plan = $this->newDataObject();
        $plan->setId((int) $row['plan_id']);
        $plan->setName($row['name']);
        $plan->setPrice($row['price']);
        $plan->setDiscountedPrice($row['discounted_price']);
        $plan->setSubmissionLimit((int) $row['submission_limit']);
        $plan->setDescription($row['description'] ?? '');
        return $plan;
    }
}
