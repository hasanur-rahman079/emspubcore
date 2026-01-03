<?php

/**
 * @file plugins/generic/emspubcore/classes/SubmissionUsageDAO.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class SubmissionUsageDAO
 *
 * @ingroup plugins_generic_emspubcore
 *
 * @brief DAO for tracking monthly submission usage per journal
 */

namespace APP\plugins\generic\emspubcore\classes;

use PKP\db\DAO;
use Illuminate\Support\Facades\DB;

class SubmissionUsageDAO extends DAO
{
    /**
     * Get current month's submission count for a journal
     */
    public function getCurrentMonthCount(int $journalId): int
    {
        $yearMonth = date('Y-m');
        
        $result = DB::table('emspubcore_submission_usage')
            ->where('journal_id', $journalId)
            ->where('year_month', $yearMonth)
            ->first();

        return $result ? (int) $result->submission_count : 0;
    }

    /**
     * Increment submission count for current month
     */
    public function incrementCount(int $journalId): void
    {
        $yearMonth = date('Y-m');
        
        // Try to update existing record
        $affected = DB::table('emspubcore_submission_usage')
            ->where('journal_id', $journalId)
            ->where('year_month', $yearMonth)
            ->increment('submission_count');

        // If no record exists, create one
        if ($affected === 0) {
            DB::table('emspubcore_submission_usage')->insert([
                'journal_id' => $journalId,
                'year_month' => $yearMonth,
                'submission_count' => 1,
            ]);
        }
    }

    /**
     * Get submission count for a specific month
     */
    public function getMonthCount(int $journalId, string $yearMonth): int
    {
        $result = DB::table('emspubcore_submission_usage')
            ->where('journal_id', $journalId)
            ->where('year_month', $yearMonth)
            ->first();

        return $result ? (int) $result->submission_count : 0;
    }

    /**
     * Get usage history for a journal
     */
    public function getUsageHistory(int $journalId, int $months = 12): array
    {
        $results = DB::table('emspubcore_submission_usage')
            ->where('journal_id', $journalId)
            ->orderBy('year_month', 'desc')
            ->limit($months)
            ->get();

        $history = [];
        foreach ($results as $row) {
            $history[$row->year_month] = (int) $row->submission_count;
        }
        return $history;
    }

    /**
     * Reset count for a specific month (for admin use)
     */
    public function resetMonthCount(int $journalId, ?string $yearMonth = null): void
    {
        $yearMonth = $yearMonth ?? date('Y-m');
        
        DB::table('emspubcore_submission_usage')
            ->where('journal_id', $journalId)
            ->where('year_month', $yearMonth)
            ->update(['submission_count' => 0]);
    }

    /**
     * Delete all usage records for a journal
     */
    public function deleteByJournalId(int $journalId): void
    {
        DB::table('emspubcore_submission_usage')
            ->where('journal_id', $journalId)
            ->delete();
    }

    /**
     * Get total submissions for a journal (all time)
     */
    public function getTotalSubmissions(int $journalId): int
    {
        return (int) DB::table('emspubcore_submission_usage')
            ->where('journal_id', $journalId)
            ->sum('submission_count');
    }

    /**
     * Get submissions count for the current year
     */
    public function getYearlyCount(int $journalId, ?int $year = null): int
    {
        $year = $year ?? (int) date('Y');
        $pattern = $year . '-%';
        
        return (int) DB::table('emspubcore_submission_usage')
            ->where('journal_id', $journalId)
            ->where('year_month', 'like', $pattern)
            ->sum('submission_count');
    }

    /**
     * Reset submissions count for the current year (for plan renewals/assignments)
     */
    public function resetYearlyCount(int $journalId, ?int $year = null): void
    {
        $year = $year ?? (int) date('Y');
        $pattern = $year . '-%';
        
        DB::table('emspubcore_submission_usage')
            ->where('journal_id', $journalId)
            ->where('year_month', 'like', $pattern)
            ->delete();
    }
}
