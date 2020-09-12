<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/local.php';   // lokale Funktionen

class HydrawiseConfig extends IPSModule
{
    use HydrawiseCommonLib;
    use HydrawiseLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('ImportCategoryID', 0);

        $this->ConnectParent('{5927E05C-82D0-4D78-B8E0-A973470A9CD3}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = ['ImportCategoryID'];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid > 0) {
                $this->RegisterReference($oid);
            }
        }

        $this->SetStatus(IS_ACTIVE);
    }

    private function GetFormActions()
    {
        $formActions = [];

        return $formActions;
    }

    private function SetLocation()
    {
        $category = $this->ReadPropertyInteger('ImportCategoryID');
        $tree_position = [];
        if ($category > 0 && IPS_ObjectExists($category)) {
            $tree_position[] = IPS_GetName($category);
            $parent = IPS_GetObject($category)['ParentID'];
            while ($parent > 0) {
                if ($parent > 0) {
                    $tree_position[] = IPS_GetName($parent);
                }
                $parent = IPS_GetObject($parent)['ParentID'];
            }
            $tree_position = array_reverse($tree_position);
        }
        return $tree_position;
    }

    public function getConfiguratorValues()
    {
        $entries = [];

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return $entries;
        }

        // an HydrawiseIO
        $sdata = [
            'DataID'   => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}',
            'Function' => 'CustomerDetails'
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $data = $this->SendDataToParent(json_encode($sdata));
        $customer = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'customer=' . print_r($customer, true), 0);

        if ($customer != '') {
            $controllers = $this->GetArrayElem($customer, 'controllers', '');
            if ($controllers != '') {
                $guid = '{B1B47A68-CE20-4887-B00C-E6412DAD2CFB}';
                $instIDs = IPS_GetInstanceListByModuleID($guid);
                foreach ($controllers as $controller) {
                    $controller_name = $controller['name'];
                    $controller_id = $controller['controller_id'];
                    $serial_number = $controller['serial_number'];

                    $instanceID = 0;
                    foreach ($instIDs as $instID) {
                        if (IPS_GetProperty($instID, 'controller_id') == $controller_id) {
                            $this->SendDebug(__FUNCTION__, 'controller found: ' . utf8_decode(IPS_GetName($instID)) . ' (' . $instID . ')', 0);
                            $instanceID = $instID;
                            break;
                        }
                    }

                    $entry = [
                        'instanceID'    => $instanceID,
                        'name'          => $controller_name,
                        'serial_number' => $serial_number,
                        'create'        => [
                            'moduleID'      => $guid,
                            'location'      => $this->SetLocation(),
                            'info'          => $this->Translate('Controller') . ' (' . $controller_name . ')',
                            'configuration' => [
                                'controller_id' => "$controller_id",
                            ]
                        ]
                    ];

                    $entries[] = $entry;
                    $this->SendDebug(__FUNCTION__, 'entry=' . print_r($entry, true), 0);
                }
            }
        }

        return $entries;
    }

    private function GetFormElements()
    {
        $formElements = [];

        if ($this->HasActiveParent() == false) {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => 'Instance has no active parent instance',
            ];
        }

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Hydrawise Configurator'
        ];

        $formElements[] = [
            'type'    => 'SelectCategory',
            'name'    => 'ImportCategoryID',
            'caption' => 'category'
        ];

        $entries = $this->getConfiguratorValues();
        $formElements[] = [
            'type'    => 'Configurator',
            'name'    => 'controller',
            'caption' => 'Controller',

            'rowCount' => count($entries),

            'add'     => false,
            'delete'  => false,
            'columns' => [
                [
                    'caption' => 'Name',
                    'name'    => 'name',
                    'width'   => 'auto'
                ],
                [
                    'caption' => 'Serial number',
                    'name'    => 'serial_number',
                    'width'   => '200px'
                ]
            ],
            'values' => $entries
        ];

        return $formElements;
    }
}
