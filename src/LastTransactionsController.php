<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../services/CacheService.php";
require_once __DIR__ . "/../services/ResponseService.php";
require_once __DIR__ . "/BitrixController.php";

class LastTransactionsController extends BitrixController
{
    private CacheService $cache;
    private ResponseService $response;
    private array $config;

    public function __construct()
    {
        parent::__construct();
        $this->config = require __DIR__ . '/../config/config.php';
        $this->cache = new CacheService($this->config['cache']['expiry']);
        $this->response = new ResponseService();
    }

    public function processRequest(string $method): void
    {
        if ($method !== 'GET') {
            $this->response->sendError(405, "Method Not Allowed");
            return;
        }

        $cacheKey = "last_transactions_" . date('Y-m-d');
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false && $this->config['cache']['enabled']) {
            $this->response->sendSuccess(200, $cached);
            return;
        }

        $salesDeptIds = $this->config['SALES_DEPARTMENT_IDS'];
        $salesEmployees = $this->getAllUsers(['UF_DEPARTMENT' => $salesDeptIds], [
            'ID',
            'NAME',
            'LAST_NAME',
            'WORK_POSITION',
            'UF_DEPARTMENT',
            'UF_EMPLOYMENT_DATE'
        ]);
        $salesEmployeesIds = array_column($salesEmployees, 'ID');

        $deals = $this->getDeals([
            '@ASSIGNED_BY_ID' => $salesEmployeesIds,
            '!=OPPORTUNITY' => null
        ], [
            'ID',
            'ASSIGNED_BY_ID',
            'CLOSEDATE',
            'OPPORTUNITY',
            'UF_CRM_67F77CCBC7132',
            'UF_CRM_1727626089404',
        ]);

        if (empty($deals)) {
            $this->response->sendSuccess(200, []);
            return;
        }

        $dealByEmployee = [];
        foreach ($deals as $deal) {
            $employeeId = $deal['ASSIGNED_BY_ID'];
            if (!isset($dealByEmployee[$employeeId])) {
                $dealByEmployee[$employeeId] = [];
            }
            $dealByEmployee[$employeeId][] = $deal;
        }

        $data = [];

        foreach ($salesEmployees as $emp) {
            $id = $emp['ID'];
            $name = trim(($emp['NAME'] ?? '') . ' ' . ($emp['LAST_NAME'] ?? ''));
            $joiningDate = $emp['UF_EMPLOYMENT_DATE'] ?? null;

            if (empty($dealByEmployee[$id])) {
                continue;
            }

            usort($dealByEmployee[$id], fn($a, $b) => strtotime($b['CLOSEDATE']) <=> strtotime($a['CLOSEDATE']));
            $lastDeal = $dealByEmployee[$id][0];

            $lastDealDate = $lastDeal['CLOSEDATE'];
            $monthsWithoutClosing = $this->monthsBetweenDates($lastDealDate, date('Y-m-d'));

            $data[] = [
                'agent' => $name,
                'joiningDate' => $joiningDate,
                'lastDealDate' => $lastDealDate,
                'project' => $lastDeal['UF_CRM_67F77CCBC7132'],
                'amount' => (float) $lastDeal['OPPORTUNITY'],
                'grossComms' => (float) $lastDeal['OPPORTUNITY'] * (float) $lastDeal['UF_CRM_1727626089404'] / 100,
                'monthsWithoutClosing' => $monthsWithoutClosing
            ];
        }

        $this->cache->set($cacheKey, $data);
        $this->response->sendSuccess(200, $data);
    }

    private function monthsBetweenDates(string $startDate, string $endDate): int
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);

        $diff = $start->diff($end);

        return ($diff->y * 12) + $diff->m;
    }
}
