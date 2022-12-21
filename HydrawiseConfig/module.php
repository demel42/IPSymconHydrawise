<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class HydrawiseConfig extends IPSModule
{
    use Hydrawise\StubsCommonLib;
    use HydrawiseLocalLib;

    private $ModuleDir;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->ModuleDir = __DIR__;
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('ImportCategoryID', 0);

        $this->RegisterAttributeString('UpdateInfo', '');
        $this->RegisterAttributeString('DataCache', '');

        $this->ConnectParent('{5927E05C-82D0-4D78-B8E0-A973470A9CD3}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = ['ImportCategoryID'];
        $this->MaintainReferences($propertyNames);

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $this->SetupDataCache(24 * 60 * 60);

        $this->MaintainStatus(IS_ACTIVE);
    }

    public function getConfiguratorValues()
    {
        $entries = [];

        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return $entries;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            return $entries;
        }

        $catID = $this->ReadPropertyInteger('ImportCategoryID');

        $dataCache = $this->ReadDataCache();
        if (isset($dataCache['data']['customer'])) {
            $customer = $dataCache['data']['customer'];
            $this->SendDebug(__FUNCTION__, 'customer (from cache)=' . print_r($customer, true), 0);
        } else {
            $sdata = [
                'DataID'   => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}', // an HydrawiseIO
                'CallerID' => $this->InstanceID,
                'Function' => 'CustomerDetails'
            ];
            $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
            $data = $this->SendDataToParent(json_encode($sdata));
            $customer = @json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'customer=' . print_r($customer, true), 0);
            if (is_array($customer)) {
                $dataCache['data']['customer'] = $customer;
            }
            $this->WriteDataCache($dataCache, time());
        }

        $guid = '{B1B47A68-CE20-4887-B00C-E6412DAD2CFB}'; // HydrawiseController:
        $instIDs = IPS_GetInstanceListByModuleID($guid);

        if (is_array($customer)) {
            $controllers = $this->GetArrayElem($customer, 'controllers', '');
            if ($controllers != '') {
                foreach ($controllers as $controller) {
                    $controller_name = $controller['name'];
                    $controller_id = $controller['controller_id'];
                    $serial_number = $controller['serial_number'];

                    $instanceID = 0;
                    foreach ($instIDs as $instID) {
                        if (IPS_GetProperty($instID, 'controller_id') == $controller_id) {
                            $this->SendDebug(__FUNCTION__, 'instance found: ' . IPS_GetName($instID) . ' (' . $instID . ')', 0);
                            $instanceID = $instID;
                            break;
                        }
                    }

                    if ($instanceID && IPS_GetInstance($instanceID)['ConnectionID'] != IPS_GetInstance($this->InstanceID)['ConnectionID']) {
                        continue;
                    }

                    $entry = [
                        'instanceID'    => $instanceID,
                        'name'          => $controller_name,
                        'serial_number' => $serial_number,
                        'controller_id' => $controller_id,
                        'create'        => [
                            'moduleID'      => $guid,
                            'location'      => $this->GetConfiguratorLocation($catID),
                            'info'          => $this->Translate('Controller') . ' (' . $controller_name . ')',
                            'configuration' => [
                                'controller_id' => (string) $controller_id,
                            ]
                        ]
                    ];

                    $entries[] = $entry;
                    $this->SendDebug(__FUNCTION__, 'entry=' . print_r($entry, true), 0);
                }
            }
        }
        foreach ($instIDs as $instID) {
            $fnd = false;
            foreach ($entries as $entry) {
                if ($entry['instanceID'] == $instID) {
                    $fnd = true;
                    break;
                }
            }
            if ($fnd) {
                continue;
            }

            if (IPS_GetInstance($instID)['ConnectionID'] != IPS_GetInstance($this->InstanceID)['ConnectionID']) {
                continue;
            }

            $name = IPS_GetName($instID);
            $controller_id = IPS_GetProperty($instID, 'controller_id');

            $entry = [
                'instanceID'         => $instID,
                'name'               => $name,
                'serial_number'      => '',
                'controller_id'      => $controller_id,
            ];

            $entries[] = $entry;
            $this->SendDebug(__FUNCTION__, 'missing entry=' . print_r($entry, true), 0);
        }

        return $entries;
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Hydrawise Configurator');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'SelectCategory',
            'name'    => 'ImportCategoryID',
            'caption' => 'category for controller to be created'
        ];

        $entries = $this->getConfiguratorValues();
        $formElements[] = [
            'type'     => 'Configurator',
            'name'     => 'controller',
            'caption'  => 'Controller',
            'rowCount' => count($entries),
            'add'      => false,
            'delete'   => false,
            'columns'  => [
                [
                    'caption' => 'Name',
                    'name'    => 'name',
                    'width'   => 'auto'
                ],
                [
                    'caption' => 'Serial number',
                    'name'    => 'serial_number',
                    'width'   => '200px'
                ],
                [
                    'caption' => 'Controller-ID',
                    'name'    => 'controller_id',
                    'width'   => '200px'
                ],
            ],
            'values'            => $entries,
            'discoveryInterval' => 60 * 60 * 24,
        ];
        $formElements[] = $this->GetRefreshDataCacheFormAction();

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }
}
