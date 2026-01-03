<?php
/**
 * @file plugins/generic/emspubcore/classes/PaymentHistoryDAO.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class PaymentHistoryDAO
 * @ingroup plugins_generic_emspubcore_classes
 */

namespace APP\plugins\generic\emspubcore\classes;

use Illuminate\Support\Facades\DB;
use PKP\db\DAO;

class PaymentHistoryDAO extends DAO
{
    /**
     * Get payment history for a journal
     *
     * @param int $journalId
     * @return array
     */
    public function getByJournalId($journalId)
    {
        return DB::table('emspubcore_payment_history')
            ->where('journal_id', (int) $journalId)
            ->orderBy('payment_date', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get all payment history for all journals
     *
     * @return array
     */
    public function getAll()
    {
        return DB::table('emspubcore_payment_history as ph')
            ->leftJoin('journals as j', 'ph.journal_id', '=', 'j.journal_id')
            ->select('ph.*', 'j.path as journal_path')
            ->orderBy('ph.payment_date', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Log a payment
     *
     * @param array $data
     */
    public function logPayment($data)
    {
        $transactionId = $data['transaction_id'] ?? null;
        $stripeId = $data['stripe_payment_intent_id'] ?? null;
        $paddleId = $data['paddle_transaction_id'] ?? null;

        if (!$stripeId && !$paddleId && $transactionId) {
            if (strpos($transactionId, 'pi_') === 0) {
                $stripeId = $transactionId;
            } elseif (strpos($transactionId, 'txn_') === 0 || strpos($transactionId, 'trn_') === 0) {
                $paddleId = $transactionId;
            }
        }

        DB::table('emspubcore_payment_history')->insert([
            'journal_id' => (int) $data['journal_id'],
            'amount' => (int) $data['amount'],
            'tax_amount' => (int) ($data['tax_amount'] ?? 0),
            'currency' => $data['currency'] ?? 'USD',
            'stripe_payment_intent_id' => $stripeId,
            'paddle_transaction_id' => $paddleId,
            'status' => $data['status'] ?? 'succeeded',
            'payment_date' => $data['payment_date'] ?? date('Y-m-d H:i:s'),
            'plan_type' => $data['plan_type'] ?? null,
            'billing_cycle' => $data['billing_cycle'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
