<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../services/CacheService.php";
require_once __DIR__ . "/../services/ResponseService.php";
require_once __DIR__ . "/BitrixController.php";

class OverallDealsController extends BitrixController
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

        $cacheKey = "overall_deals_" . date('Y-m-d');
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false && $this->config['cache']['enabled']) {
            $this->response->sendSuccess(200, $cached);
            return;
        }

        $select = [
            "ID",
            "TITLE",
            "STAGE_ID",
            "DATE_CREATE",
            "OPPORTUNITY",
            "ASSIGNED_BY_ID",
            "UF_CRM_1727854143005",
            "UF_CRM_1727625804043",
            "OPPORTUNITY",
            "UF_CRM_66E3D8D1A13F7",
            "UF_CRM_1727625822094",
            "UF_CRM_1727854068559",
            "UF_CRM_67F77CCBC7132",
            "TYPE_ID",
            "UF_CRM_1727854555607",
            "SOURCE_ID",
            "UF_CRM_1727871937052",
            "UF_CRM_1727871887978",
            "UF_CRM_1727871911878",
        ];

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
            '!=UF_CRM_67F77CCBC7132' => null
        ], $select, 10, ['ID' => 'desc']);

        if (empty($deals)) {
            $this->response->sendError(204, "No Deals Found");
            return;
        }

        // Create a lookup array for employees to easily find by ID
        $employeesById = [];
        foreach ($salesEmployees as $employee) {
            $employeesById[$employee['ID']] = ($employee['NAME'] ?? '') . ' ' . ($employee['LAST_NAME'] ?? '');
        }

        $formatted = [];
        foreach ($deals as $deal) {
            // Get agent name from lookup array
            $agentName = $employeesById[$deal['ASSIGNED_BY_ID']] ?? '';

            $formatted[] = [
                'date' => date('Y-m-d', strtotime($deal['DATE_CREATE'])),
                'dealType' => $deal['TYPE_ID'] ?? '',
                'projectName' => $deal['UF_CRM_67F77CCBC7132'] ?? '',
                'unitNo' => $deal['UF_CRM_1727625804043'] ?? '',
                'developerName' => $deal['UF_CRM_1727625822094'] ?? '',
                'propertyType' => $this->mapPropertyType($deal['UF_CRM_66E3D8D1A13F7']) ?? '',
                'noOfBr' => $this->mapBedrooms($deal['UF_CRM_1727854068559']) ?? '',
                'clientName' => $deal['UF_CRM_1727854143005'] ?? '',
                'agentName' => $agentName,
                'propertyPrice' => (float)($deal['OPPORTUNITY'] ?? 0),
                'grossCommissionInclVAT' => (float)($deal['UF_CRM_1727871887978'] ?? 0),
                'grossCommission' => (float)($deal['UF_CRM_1727871887978'] ?? 0),
                'vat' => (float)($deal['UF_CRM_1727871911878'] ?? 0),
                'agentCommission' => (float)($deal['UF_CRM_1727871937052'] ?? 0),
                'leadSource' => $this->mapSourceId($deal['SOURCE_ID']) ?? '',
            ];
        }

        $this->cache->set($cacheKey, $formatted);
        $this->response->sendSuccess(200, $formatted);
    }

    private function mapSourceId($id = 0)
    {
        $sources = [
            "CALL" => "Call",
            "EMAIL" => "E-Mail",
            "WEB" => "Website",
            "ADVERTISING" => "Advertising",
            "PARTNER" => "Existing Client",
            "RECOMMENDATION" => "By Recommendation",
            "TRADE_SHOW" => "Show/Exhibition",
            "WEBFORM" => "CRM form",
            "CALLBACK" => "Callback",
            "RC_GENERATOR" => "Sales boost",
            "STORE" => "Online Store",
            "OTHER" => "Other",
            "2|FACEBOOK" => "Facebook - Open Channel",
            "UC_WXX77T" => "Company lead",
            "UC_WXHO9P" => "HubSpot Import",
            "UC_Q8BGPL" => "Facebook",
            "UC_FLEQ4P" => "Instagram",
            "UC_5D5SVF" => "Email-Camp",
            "UC_32S5MT" => "Google Adwords",
            "UC_SW1FK0" => "Bayut",
            "UC_W4DK0R" => "Property Finder",
            "UC_N8HODU" => "Dubizzle",
            "UC_ESN0RB" => "IVR / Convolo",
            "UC_JDIBT3" => "EM / Convolo",
            "UC_IPIF3D" => "Facebook / Convolo",
            "UC_2R7T9N" => "Tiktok / Convolo",
            "UC_8P22IR" => "Website / Convolo",
            "UC_SEYC1C" => "GA / Convolo",
            "UC_0RA5D0" => "Instagram / Convolo",
            "UC_PV7S3O" => "Offplan Portal Ads",
            "UC_N0X55E" => "Email/Landing Page",
            "UC_NU9VE9" => "Signature Ad",
            "UC_RVXXS7" => "Facebook Via Convolo",
            "UC_A736LI" => "Own reference buyer.",
            "UC_I8E0DH" => "Own social media lead",
            "UC_UFMJ0J" => "Own data lead",
            "UC_NKWR7D" => "Own Refrence",
            "UC_AO2TID" => "Portal",
            "UC_O8CS6J" => "Vedank",
            "UC_ABYWI9" => "Convolo",
            "UC_0AB5OH" => "Instagram Springfield",
            "UC_DMGYHC" => "Sir Farooq Social Media",
            "UC_LYJKY1" => "Snapchat",
            "UC_3TNW9U" => "Farooq Instagram",
            "UC_0OFJJ4" => "Tiktok",
            "UC_XNP1B4" => "Farooq bhai social media",
            "UC_5FBGT3" => "SMS",
            "UC_B103FQ" => "Youtube",
            "UC_PWD4BH" => "Expertise",
            "UC_K21PPH" => "-",
            "UC_DCCQN2" => "referral",
            "UC_5ZA3XT" => "PropertyFinder.ae",
            "UC_OH9H73" => "JustProperty.com",
            "UC_OLDOK0" => "Event",
            "UC_ZLCNP3" => "Roadshow",
            "UC_1DIMU5" => "Bayut.com",
            "UC_5SJTXN" => "Open House",
            "UC_QP27PS" => "Company Email",
            "UC_A8WMQA" => "Company Lead",
            "UC_US9TA3" => "Google",
            "UC_G2OZRM" => "Nigeria Roadshow",
            "UC_JGVD07" => "Reshfled"
        ];

        return $sources[$id] ?? 'Unknown Source';
    }

    private function mapBedrooms($id = 0)
    {
        $bedrooms = [
            1227 => 'STD',
            1229 => '1BR',
            1231 => '2BR',
            1233 => '3BR',
            1235 => '4BR',
            1237 => '5BR',
            1239 => '6BR',
            1241 => '7BR',
        ];

        return $bedrooms[$id] ?? 0;
    }

    private function mapPropertyType($id = 0)
    {
        $propertyTypes = [
            574 => 'Apartment',
            576 => 'Villa',
            578 => 'Townhouse',
            580 => 'Office',
            582 => 'Plot',
            1203 => 'Building',
            1205 => 'Half Floor',
            1207 => 'Full Floor',
        ];

        return $propertyTypes[$id] ?? '';
    }
}
