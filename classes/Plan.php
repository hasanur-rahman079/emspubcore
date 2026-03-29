<?php

/**
 * @file plugins/generic/emspubcore/classes/Plan.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class Plan
 *
 * @ingroup plugins_generic_emspubcore
 *
 * @brief Data object representing a defined subscription plan
 */

namespace APP\plugins\generic\emspubcore\classes;

use PKP\core\DataObject;

class Plan extends DataObject
{
    /**
     * Get plan ID
     */
    public function getId(): ?int
    {
        return $this->getData('plan_id');
    }

    /**
     * Set plan ID
     */
    public function setId(int $planId)
    {
        $this->setData('plan_id', $planId);
    }

    /**
     * Get plan name
     */
    public function getName()
    {
        return $this->getData('name');
    }

    /**
     * Set plan name
     */
    public function setName($name)
    {
        $this->setData('name', $name);
    }

    /**
     * Get monthly price
     */
    public function getPrice()
    {
        return $this->getData('price');
    }

    /**
     * Set monthly price
     */
    public function setPrice($price)
    {
        $this->setData('price', $price);
    }

    /**
     * Get discounted/yearly price
     */
    public function getDiscountedPrice()
    {
        return $this->getData('discounted_price');
    }

    /**
     * Set discounted/yearly price
     */
    public function setDiscountedPrice($price)
    {
        $this->setData('discounted_price', $price);
    }

    /**
     * Get submission limit
     */
    public function getSubmissionLimit()
    {
        return (int) $this->getData('submission_limit');
    }

    /**
     * Set submission limit
     */
    public function setSubmissionLimit($limit)
    {
        $this->setData('submission_limit', $limit);
    }

    /**
     * Get description
     */
    public function getDescription()
    {
        return $this->getData('description');
    }

    /**
     * Set description
     */
    public function setDescription($description)
    {
        $this->setData('description', $description);
    }
}
