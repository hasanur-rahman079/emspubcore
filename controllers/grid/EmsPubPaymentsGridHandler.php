<?php

namespace APP\plugins\generic\emspubcore\controllers\grid;

use APP\controllers\grid\subscriptions\PaymentsGridHandler;
use PKP\controllers\grid\GridColumn;
use Illuminate\Support\Facades\DB;
use APP\facades\Repo;
use PKP\db\DAOResultFactory;
use APP\payment\ojs\OJSCompletedPaymentDAO;
use PKP\db\DAORegistry;

class EmsPubPaymentsGridHandler extends PaymentsGridHandler
{
    /**
     * @copydoc GridHandler::initialize()
     */
    public function initialize($request, $args = null)
    {
        // Let parent initialize standard columns
        parent::initialize($request, $args);

        // Add "Article Details" column using our custom provider
        $cellProvider = new EmsPubPaymentsGridCellProvider($request);
        
        $this->addColumn(
            new GridColumn(
                'details',
                'common.title', 
                null,
                null,
                $cellProvider
            )
        );
    }

    /**
     * @copydoc GridHandler::renderFilter()
     */
    public function renderFilter($request, $filterData = [])
    {
        $filterSelectionData = $this->getFilterSelectionData($request);
        $templateMgr = \APP\template\TemplateManager::getManager($request);
        $templateMgr->assign([
            'filterSelectionData' => $filterSelectionData,
            'FBV_STYLES' => [
                'size' => ['LARGE' => 'large']
            ]
        ]);
        
        return $templateMgr->fetch('file:' . __DIR__ . '/../../templates/paymentsGridFilter.tpl');
    }

    /**
     * @copydoc GridHandler::getFilterSelectionData()
     */
    public function getFilterSelectionData($request)
    {
        return [
            'search' => $request->getUserVar('search') ? $request->getUserVar('search') : ''
        ];
    }

    /**
     * @copydoc GridHandler::getFilterForm()
     */
    protected function getFilterForm()
    {
        return 'file:' . __DIR__ . '/../../templates/paymentsGridFilter.tpl';
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        $context = $request->getContext();
        $paymentDao = DAORegistry::getDAO('OJSCompletedPaymentDAO'); /** @var OJSCompletedPaymentDAO $paymentDao */
        
        // Fetch ALL payments for the context (no range info yet)
        // We must fetch all to filter by User/Article which are not in the main table effectively for DAO queries
        $allPayments = $paymentDao->getByContextId($context->getId());

        $search = $filter['search'] ?? '';
        $filteredPayments = [];

        if (empty($search)) {
            $filteredPayments = $allPayments;
        } else {
            $searchLower = strtolower($search);
            $userDao = Repo::user();

            foreach ($allPayments as $payment) {
                $match = false;

                // Search by Article ID (if numeric)
                if (is_numeric($search) && $payment->getAssocId() == $search) {
                     $filteredPayments[$payment->getId()] = $payment;
                     continue;
                }

                // Search by User Name / Email
                // We have to load the user (cached by Repo usually)
                try {
                    $user = $userDao->get($payment->getUserId(), true);
                    if ($user) {
                        if (strpos(strtolower($user->getUsername()), $searchLower) !== false || 
                            strpos(strtolower($user->getEmail()), $searchLower) !== false ||
                            strpos(strtolower($user->getFullName()), $searchLower) !== false) {
                            $match = true;
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore missing users
                }

                if ($match) {
                    $filteredPayments[$payment->getId()] = $payment;
                }
            }
        }

        // Manual Pagination
        $rangeInfo = $this->getGridRangeInfo($request, $this->getId());
        $totalCount = count($filteredPayments);
        
        if ($rangeInfo && $rangeInfo->isValid()) {
            $offset = $rangeInfo->getCount() * ($rangeInfo->getPage() - 1);
            $pageRows = array_slice($filteredPayments, $offset, $rangeInfo->getCount(), true);
        } else {
            $pageRows = $filteredPayments;
        }

        // Return iterator for the grid
        return new \PKP\core\VirtualArrayIterator($pageRows, $totalCount, $rangeInfo->getPage(), $rangeInfo->getCount());
    }
}
