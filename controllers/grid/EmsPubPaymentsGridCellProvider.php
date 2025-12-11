<?php

namespace APP\plugins\generic\emspubcore\controllers\grid;

use APP\controllers\grid\subscriptions\PaymentsGridCellProvider;
use APP\facades\Repo;
use APP\payment\ojs\OJSPaymentManager;

class EmsPubPaymentsGridCellProvider extends PaymentsGridCellProvider
{
    /**
     * @copydoc GridCellProvider::getTemplateVarsFromRowColumn()
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        // Handle our custom column
        if ($column->getId() == 'details') {
            $payment = $row->getData();
            $label = '-';

            try {
                switch ($payment->getType()) {
                    case OJSPaymentManager::PAYMENT_TYPE_PUBLICATION:
                    case OJSPaymentManager::PAYMENT_TYPE_SUBMISSION:
                    case OJSPaymentManager::PAYMENT_TYPE_PURCHASE_ARTICLE:
                        $submission = Repo::submission()->get($payment->getAssocId());
                        if ($submission) {
                            $title = $submission->getCurrentPublication()->getLocalizedTitle();
                            // Format: "Title (ID: 123)"
                            $label = $title . ' (ID: ' . $submission->getId() . ')';
                        } else {
                             $label = __('common.none');
                        }
                        break;
                    case OJSPaymentManager::PAYMENT_TYPE_PURCHASE_ISSUE:
                         $issue = Repo::issue()->get($payment->getAssocId());
                         if ($issue) {
                             $label = $issue->getIdentification();
                         }
                         break;
                }
            } catch (\Exception $e) {
                // If record not found or other error, mostly likely record deleted
                $label = __('common.none') . ' (Record Missing)';
            }

            return ['label' => $label];
        }

        // Delegate to parent for default columns
        return parent::getTemplateVarsFromRowColumn($row, $column);
    }
}
