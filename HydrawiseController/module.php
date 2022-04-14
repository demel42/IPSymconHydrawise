<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class HydrawiseController extends IPSModule
{
    use Hydrawise\StubsCommonLib;
    use HydrawiseLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('controller_id', '');

        $this->RegisterPropertyInteger('minutes2fail', 60);

        $this->RegisterPropertyInteger('statusbox_script', 0);
        $this->RegisterPropertyString('hook', '/hook/Hydrawise');
        $this->RegisterPropertyInteger('webhook_script', 0);

        $this->RegisterPropertyInteger('update_interval', '60');

        $this->RegisterPropertyBoolean('with_last_contact', true);
        $this->RegisterPropertyBoolean('with_last_message', true);
        $this->RegisterPropertyBoolean('with_waterusage', true);
        $this->RegisterPropertyBoolean('with_status_box', false);
        $this->RegisterPropertyBoolean('with_daily_value', true);

        $this->RegisterPropertyInteger('WaterMeterID', 0);
        $this->RegisterPropertyFloat('WaterMeterFactor', 1);

        $this->RegisterPropertyInteger('ImportCategoryID', 0);

        $this->InstallVarProfiles(false);

        $this->ConnectParent('{5927E05C-82D0-4D78-B8E0-A973470A9CD3}');

        $this->RegisterTimer('UpdateController', 0, 'Hydrawise_UpdateController(' . $this->InstanceID . ');');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $hook = $this->ReadPropertyString('hook');
            if ($hook != '') {
                $this->RegisterHook($hook);
            }
            $this->SetUpdateInterval();
        }
    }

    private function CheckConfiguration()
    {
        $s = '';
        $r = [];

        $hook = $this->ReadPropertyString('hook');
        if ($hook != '' && $this->HookIsUsed($hook)) {
            $this->SendDebug(__FUNCTION__, '"hook" is already used', 0);
            $r[] = $this->Translate('Webhook is already used');
        }

        if ($r != []) {
            $s = $this->Translate('The following points of the configuration are incorrect') . ':' . PHP_EOL;
            foreach ($r as $p) {
                $s .= '- ' . $p . PHP_EOL;
            }
        }

        return $s;
    }

    protected function SetUpdateInterval()
    {
        $sec = $this->ReadPropertyInteger('update_interval');
        $msec = $sec > 0 ? $sec * 1000 : 0;
        $this->MaintainTimer('UpdateController', $msec);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $controller_id = $this->ReadPropertyString('controller_id');
        $with_last_contact = $this->ReadPropertyBoolean('with_last_contact');
        $with_last_message = $this->ReadPropertyBoolean('with_last_message');
        $with_status_box = $this->ReadPropertyBoolean('with_status_box');
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');
        $with_waterusage = $this->ReadPropertyBoolean('with_waterusage');

        $vpos = 1;

        $this->MaintainVariable('Status', $this->Translate('State'), VARIABLETYPE_BOOLEAN, '~Alert.Reversed', $vpos++, true);
        $this->MaintainVariable('LastContact', $this->Translate('last contact'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, $with_last_contact);
        $this->MaintainVariable('LastMessage', $this->Translate('last message'), VARIABLETYPE_STRING, '', $vpos++, $with_last_message);
        $this->MaintainVariable('DailyReference', $this->Translate('day of cumulation'), VARIABLETYPE_INTEGER, '~UnixTimestampDate', $vpos++, $with_daily_value);
        $this->MaintainVariable('DailyDuration', $this->Translate('Watering time (today)'), VARIABLETYPE_INTEGER, 'Hydrawise.Duration', $vpos++, $with_daily_value);
        $this->MaintainVariable('DailyWaterUsage', $this->Translate('Water usage (today)'), VARIABLETYPE_FLOAT, 'Hydrawise.Flowmeter', $vpos++, $with_daily_value && $with_waterusage);

        $this->MaintainVariable('StatusBox', $this->Translate('State of irrigation'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, $with_status_box);

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = ['ImportCategoryID', 'statusbox_script', 'webhook_script', 'WaterMeterID'];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid >= 10000) {
                $this->RegisterReference($oid);
            }
        }

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateController', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateController', 0);
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $info = 'Controller (#' . $controller_id . ')';
        $this->SetSummary($info);

        $dataFilter = '.*' . $controller_id . '.*';
        $this->SendDebug(__FUNCTION__, 'set ReceiveDataFilter=' . $dataFilter, 0);
        $this->SetReceiveDataFilter($dataFilter);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $hook = $this->ReadPropertyString('hook');
            if ($hook != '') {
                $this->RegisterHook($hook);
            }
            $this->SetUpdateInterval();
        }

        $this->SetStatus(IS_ACTIVE);
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

        // an HydrawiseIO
        $controller_id = $this->ReadPropertyString('controller_id');
        $sdata = [
            'DataID'        => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}',
            'Function'      => 'ControllerDetails',
            'controller_id' => $controller_id
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $data = $this->SendDataToParent(json_encode($sdata));
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $controller = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'controller=' . print_r($controller, true), 0);

        if (is_array($controller) && count($controller)) {
            $controller_name = $this->GetArrayElem($controller, 'name', '');

            $sensors = $this->GetArrayElem($controller, 'sensors', '');
            if ($sensors != '') {
                $guid = '{56D9EFA4-8840-4DAE-A6D2-ECE8DC862874}';
                $instIDs = IPS_GetInstanceListByModuleID($guid);

                foreach ($sensors as $sensor) {
                    $connector = $sensor['input'] + 1;
                    $sensor_name = $this->GetArrayElem($sensor, 'name', 'Sensor ' . $connector);
                    $type = $sensor['type'];
                    $mode = $sensor['mode'];

                    if ($type == 1 && $mode == 1) {
                        $model = self::$SENSOR_NORMALLY_CLOSE_START;
                        $mode_txt = 'sensor';
                    } elseif ($type == 1 && $mode == 2) {
                        $model = self::$SENSOR_NORMALLY_OPEN_STOP;
                        $mode_txt = 'sensor';
                    } elseif ($type == 1 && $mode == 3) {
                        $model = self::$SENSOR_NORMALLY_CLOSE_STOP;
                        $mode_txt = 'sensor';
                    } elseif ($type == 1 && $mode == 4) {
                        $model = self::$SENSOR_NORMALLY_OPEN_START;
                        $mode_txt = 'sensor';
                    } elseif ($type == 3 && $mode == 0) {
                        $model = self::$SENSOR_FLOW_METER;
                        $mode_txt = 'flow meter';
                    } else {
                        continue;
                    }

                    $instanceID = 0;
                    foreach ($instIDs as $instID) {
                        if (IPS_GetProperty($instID, 'controller_id') == $controller_id && IPS_GetProperty($instID, 'connector') == $connector) {
                            $this->SendDebug(__FUNCTION__, 'sensor found: ' . utf8_decode(IPS_GetName($instID)) . ' (' . $instID . ')', 0);
                            $instanceID = $instID;
                            break;
                        }
                    }

                    $ident = $this->Translate('Sensor') . ' ' . $connector;
                    $entry = [
                        'instanceID'  => $instanceID,
                        'type'        => $this->Translate($mode_txt),
                        'ident'       => $ident,
                        'name'        => $sensor_name,
                        'create'      => [
                            'moduleID'      => $guid,
                            'location'      => $this->SetLocation(),
                            'info'          => $ident . ' (' . $controller_name . '\\' . $sensor_name . ')',
                            'configuration' => [
                                'controller_id' => "$controller_id",
                                'connector'     => $connector,
                                'model'         => $model,
                            ]
                        ],
                    ];

                    $entries[] = $entry;
                    $this->SendDebug(__FUNCTION__, 'entry=' . print_r($entry, true), 0);
                }
            }

            $relays = $this->GetArrayElem($controller, 'relays', '');
            if ($relays != '') {
                $guid = '{6A0DAE44-B86A-4D50-A76F-532365FD88AE}';
                $instIDs = IPS_GetInstanceListByModuleID($guid);

                foreach ($relays as $relay) {
                    $relay_id = $relay['relay_id'];
                    $connector = $relay['relay'];
                    $zone_name = $relay['name'];

                    $instanceID = 0;
                    foreach ($instIDs as $instID) {
                        if (IPS_GetProperty($instID, 'controller_id') == $controller_id && IPS_GetProperty($instID, 'relay_id') == $relay_id) {
                            $this->SendDebug(__FUNCTION__, 'zone found: ' . utf8_decode(IPS_GetName($instID)) . ' (' . $instID . ')', 0);
                            $instanceID = $instID;
                            break;
                        }
                    }

                    if ($connector < 100) {
                        $ident = $this->Translate('Zone') . ' ' . $connector;
                    } else {
                        $ident = $this->Translate('Expander') . ' ' . floor($connector / 100) . ' Zone ' . ($connector % 100);
                    }

                    $entry = [
                        'instanceID'  => $instanceID,
                        'type'        => $this->Translate('Zone'),
                        'ident'       => $ident,
                        'name'        => $zone_name,
                        'create'      => [
                            'moduleID'      => $guid,
                            'location'      => $this->SetLocation(),
                            'info'          => $ident . ' (' . $controller_name . '\\' . $zone_name . ')',
                            'configuration' => [
                                'controller_id' => (string) $controller_id,
                                'relay_id'      => (string) $relay_id,
                                'connector'     => $connector,
                            ]
                        ],
                    ];

                    $entries[] = $entry;
                }
            }
        }

        $guid = '{56D9EFA4-8840-4DAE-A6D2-ECE8DC862874}';
        $instIDs = IPS_GetInstanceListByModuleID($guid);
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

            $name = IPS_GetName($instID);
            $connector = IPS_GetProperty($instID, 'connector');
            $model = IPS_GetProperty($instID, 'model');
            switch ($model) {
                case self::$SENSOR_NORMALLY_CLOSE_START:
                case self::$SENSOR_NORMALLY_OPEN_STOP:
                case self::$SENSOR_NORMALLY_CLOSE_STOP:
                case  self::$SENSOR_NORMALLY_OPEN_START:
                    $mode_txt = 'sensor';
                    break;
                case self::$SENSOR_FLOW_METER:
                    $mode_txt = 'flow meter';
                    break;
                default:
                    $mode_txt = 'unknown';
                    break;
            }
            $ident = $this->Translate('Sensor') . ' ' . $connector;
            $entry = [
                'instanceID'  => $instanceID,
                'type'        => $this->Translate($mode_txt),
                'ident'       => $ident,
                'name'        => $name,
            ];

            $entries[] = $entry;
            $this->SendDebug(__FUNCTION__, 'missing entry=' . print_r($entry, true), 0);
        }

        $guid = '{6A0DAE44-B86A-4D50-A76F-532365FD88AE}';
        $instIDs = IPS_GetInstanceListByModuleID($guid);

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

            $name = IPS_GetName($instID);
            $connector = IPS_GetProperty($instID, 'connector');

            if ($connector < 100) {
                $ident = $this->Translate('Zone') . ' ' . $connector;
            } else {
                $ident = $this->Translate('Expander') . ' ' . floor($connector / 100) . ' Zone ' . ($connector % 100);
            }

            $entry = [
                'instanceID'  => $instanceID,
                'type'        => $this->Translate('Zone'),
                'ident'       => $ident,
                'name'        => $name,
            ];

            $entries[] = $entry;
            $this->SendDebug(__FUNCTION__, 'missing entry=' . print_r($entry, true), 0);
        }

        return $entries;
    }

    private function GetFormElements()
    {
        $formElements = [];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Hydrawise Controller'
        ];

        if ($this->HasActiveParent() == false) {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => 'Instance has no active parent instance',
            ];
        }

        @$s = $this->CheckConfiguration();
        if ($s != '') {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => $s,
            ];
            $formElements[] = [
                'type'    => 'Label',
            ];
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'controller_id',
                    'caption' => 'Controller-ID',
                    'enabled' => false
                ],
            ],
            'caption' => 'Basic configuration (don\'t change)'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_last_contact',
                    'caption' => 'last contact to Hydrawise'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_last_message',
                    'caption' => 'last message'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_status_box',
                    'caption' => 'html-box with state of irrigation'
                ],
                [
                    'type'    => 'SelectScript',
                    'name'    => 'statusbox_script',
                    'caption' => 'alternate script to use for the "StatusBox"'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_waterusage',
                    'caption' => 'water usage'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_daily_value',
                    'caption' => 'daily sum'
                ],
            ],
            'caption' => 'optional controller data'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'Label',
                    'caption' => 'using instead the Hydrawise-intern information of waterflow'
                ],
                [
                    'type'    => 'SelectVariable',
                    'name'    => 'WaterMeterID',
                    'caption' => 'Counter-variable'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'digits'  => 4,
                    'name'    => 'WaterMeterFactor',
                    'caption' => ' ... conversion factor to liter'
                ],
            ],
            'caption' => 'optional wexternal water meter'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'hook',
                    'caption' => 'Webhook'
                ],
                [
                    'type'    => 'SelectScript',
                    'name'    => 'webhook_script',
                    'caption' => 'alternate script to use for Webhook'
                ],
            ],
            'caption' => 'Webhook'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'update_interval',
                    'caption' => 'Update interval',
                    'suffix'  => 'Seconds'
                ],
                $items[] = [
                    'type'    => 'NumberSpinner',
                    'name'    => 'minutes2fail',
                    'caption' => 'Duration until the connection to hydrawise is marked disturbed',
                    'suffix'  => 'Minutes'
                ],
            ],
            'caption' => 'Communication'
        ];

        $entries = $this->getConfiguratorValues();
        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'SelectCategory',
                    'name'    => 'ImportCategoryID',
                    'caption' => 'category for components to be created'
                ],
                [
                    'type'    => 'Configurator',
                    'name'    => 'components',
                    'caption' => 'Components',

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
                            'caption' => 'Ident',
                            'name'    => 'ident',
                            'width'   => '200px'
                        ],
                        [
                            'caption' => 'Type',
                            'name'    => 'type',
                            'width'   => '250px'
                        ],
                    ],
                    'values' => $entries
                ],
            ],
            'caption' => 'Sensors and zones'
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update Data',
            'onClick' => 'Hydrawise_UpdateController($id);'
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded ' => false,
            'items'     => [
                [
                    'type'    => 'Button',
                    'caption' => 'Re-install variable-profiles',
                    'onClick' => 'Hydrawise_InstallVarProfiles($id, true);'
                ],
            ],
        ];

        $formActions[] = $this->GetInformationForm();
        $formActions[] = $this->GetReferencesForm();

        return $formActions;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }
        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }

    public function UpdateController()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }
        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return;
        }

        // an HydrawiseIO
        $controller_id = $this->ReadPropertyString('controller_id');
        $sdata = [
            'DataID'        => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}',
            'Function'      => 'UpdateController',
            'controller_id' => $controller_id
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $this->SendDataToParent(json_encode($sdata));
    }

    public function CollectZoneValues()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return false;
        }
        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return false;
        }

        // an HydrawiseIO
        $controller_id = $this->ReadPropertyString('controller_id');
        $sdata = [
            'DataID'        => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}',
            'Function'      => 'CollectZoneValues',
            'controller_id' => $controller_id
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $responses = $this->SendDataToParent(json_encode($sdata));
        $this->SendDebug(__FUNCTION__, 'responses=' . print_r($responses, true), 0);
        return $responses;
    }

    protected function CollectControllerValues()
    {
        $controller_id = $this->ReadPropertyString('controller_id');
        $WaterMeterID = $this->ReadPropertyInteger('WaterMeterID');
        $WaterMeterFactor = $this->ReadPropertyFloat('WaterMeterFactor');

        $jdata = [
            'controller_id'     => $controller_id,
            'WaterMeterID'      => $WaterMeterID,
            'waterMeterFactor'  => $WaterMeterFactor,
        ];

        $data = json_encode($jdata);
        $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);
        return $data;
    }

    public function ReceiveData($data)
    {
        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        if (isset($jdata['AllData'])) {
            $this->DecodeData($jdata['AllData']);
        } elseif (isset($jdata['Function'])) {
            $controller_id = $this->ReadPropertyString('controller_id');
            if (isset($jdata['controller_id']) && $jdata['controller_id'] != $controller_id) {
                $this->SendDebug(__FUNCTION__, 'ignore foreign controller_id ' . $jdata['controller_id'], 0);
            } else {
                switch ($jdata['Function']) {
                    case 'SetMessage':
                        $with_last_message = $this->ReadPropertyBoolean('with_last_message');
                        if ($with_last_message) {
                            $this->SetValue('LastMessage', $jdata['msg']);
                        }
                        break;
                    case 'CollectControllerValues':
                        $responses = $this->CollectControllerValues();
                        return $responses;
                        break;
                    default:
                        $this->SendDebug(__FUNCTION__, 'unknown function "' . $jdata['Function'] . '"', 0);
                        break;
                }
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown message-structure', 0);
        }
    }

    protected function DecodeData($buf)
    {
        $controller_id = $this->ReadPropertyString('controller_id');
        $with_last_contact = $this->ReadPropertyBoolean('with_last_contact');
        $with_last_message = $this->ReadPropertyBoolean('with_last_message');
        $minutes2fail = $this->ReadPropertyInteger('minutes2fail');
        $with_waterusage = $this->ReadPropertyBoolean('with_waterusage');
        $with_status_box = $this->ReadPropertyBoolean('with_status_box');
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');

        $err = '';
        $statuscode = 0;
        $do_abort = false;

        $this->SendDebug(__FUNCTION__, 'buf=' . $buf, 0);
        if ($buf != '') {
            $controller = json_decode($buf, true);
            $this->SendDebug(__FUNCTION__, 'controller=' . print_r($controller, true), 0);
            if ($controller_id != $controller['controller_id']) {
                $err = "controller_id \"$controller_id\" not found";
                $statuscode = self::$IS_CONTROLLER_MISSING;
                $do_abort = true;
            }
        } else {
            $err = 'no data';
            $statuscode = self::$IS_NODATA;
            $do_abort = true;
        }

        if ($do_abort) {
            $this->LogMessage('statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, $err, 0);
            $this->SetValue('Status', false);
            $this->SetStatus($statuscode);
            $this->SetUpdateInterval();
            return -1;
        }

        $now = time();

        if ($with_daily_value) {
            $dt = new DateTime(date('d.m.Y 00:00:00', $now));
            $ts_today = (int) $dt->format('U');
            $ts_watch = $this->GetValue('DailyReference');
            if ($ts_today != $ts_watch) {
                $this->SetValue('DailyReference', $ts_today);
                $this->ClearDailyValue();
            }
        }

        $controller_status = true;
        $status = $controller['status'];
        if ($status != 'All good!' && $status != 'Alles in Ordnung!') {
            $controller_status = false;
        }

        $controller_name = $controller['name'];

        $server_time = $controller['time'];

        $last_contact_ts = $controller['last_contact'];

        $message = $controller['message'];

        $msg = "controller \"$controller_name\": last_contact=$last_contact_ts";
        $this->SendDebug(__FUNCTION__, utf8_decode($msg), 0);

        if ($with_last_contact) {
            $this->SetValue('LastContact', $last_contact_ts);
        }

        if ($with_last_message) {
            if ($message == '') {
                $varID = $this->GetIDForIdent('LastMessage');
                $r = IPS_GetVariable($varID);
                if ($r['VariableUpdated'] < time() - 60 && GetValue($varID) != '') {
                    $this->SetValue('LastMessage', '');
                }
            } else {
                $this->SetValue('LastMessage', $message);
            }
        }

        // Status der Zonen (relays)
        $running_zones = [];
        $today_zones = [];
        $done_zones = [];
        $future_zones = [];

        $daily_duration = 0;
        $daily_waterusage = 0;

        $relays = $controller['relays'];
        if (count($relays) > 0) {
            $ret = $this->CollectZoneValues();
            $responses = $ret == false ? [] : json_decode($ret, true);

            $_relays = $relays;
            $relays = [];

            foreach ($_relays as $relay) {
                $relay_id = $relay['relay_id'];

                // Daten aus den HydrawiseZone-Instanzen ergänzen
                foreach ($responses as $response) {
                    $values = json_decode($response, true);
                    if ($values['relay_id'] == $relay_id) {
                        $relay['name'] = $values['Name'];
                        $relay['lastrun'] = $values['LastRun'];
                        $relay['last_duration'] = $values['LastDuration'];
                        $relay['suspended_until'] = $values['SuspendUntil'];
                        if (isset($values['WaterFlowrate'])) {
                            $relay['waterflow'] = $values['WaterFlowrate'];
                        }

                        if (isset($values['DailyDuration'])) {
                            $relay['daily_duration'] = $values['DailyDuration'];
                            $daily_duration += $relay['daily_duration'];
                        }
                        if (isset($values['DailyWaterUsage'])) {
                            $relay['daily_waterusage'] = $values['DailyWaterUsage'];
                            $daily_waterusage += $relay['daily_waterusage'];
                        }
                        break;
                    }
                }

                if (!isset($relay['lastrun'])) {
                    $relay['lastrun'] = 0;
                }

                $time = $relay['time'];

                $nextrun = 0;
                $duration = 0;
                $is_running = false;
                $time_left = 0;
                $is_suspended = false;
                $waterflow = '';

                $type = $relay['type'];

                $timestr = $relay['timestr'];
                switch ($timestr) {
                    case 'Now':
                        $is_running = true;
                        $time_left = $this->GetArrayElem($relay, 'run', 0);
                        $waterflow = $this->GetArrayElem($relay, 'waterflow', '');
                        $s = 'is running, time_left=' . $time_left . 's, waterflow=' . $waterflow . 'l/min';
                        break;
                    case '':
                        $is_suspended = true;
                        $suspended_until = $this->GetArrayElem($relay, 'suspended', 0);
                        $s = 'is suspended, suspended_until=' . date('d.m. H:i', (int) $suspended_until);
                        break;
                    default:
                        $nextrun = $server_time + $time;
                        $duration = $this->GetArrayElem($relay, 'run', 0);
                        $s = 'is idle, nextrun=' . date('d.m. H:i', (int) $nextrun) . ', duration=' . $duration . 's';
                        break;
                }

                $name = $relay['name'];
                $this->SendDebug(__FUNCTION__, 'relay=' . $relay_id . '(' . $name . '): ' . $s . ', lastrun=' . date('d.m. H:i', (int) $relay['lastrun']), 0);

                $relay['nextrun'] = $nextrun;
                $relay['duration'] = $duration;
                $relay['is_running'] = $is_running;
                $relay['time_left'] = $time_left;
                $relay['is_suspended'] = $is_suspended;
                $relay['waterflow'] = $waterflow;

                $relays[] = $relay;
            }

            $this->SetValue('DailyDuration', $daily_duration);
            if ($with_waterusage) {
                $this->SetValue('DailyWaterUsage', $daily_waterusage);
            }

            usort($relays, ['HydrawiseController', 'cmp_relays_nextrun']);

            // aktuell durchgeführte Bewässerung
            foreach ($relays as $relay) {
                $relay_id = $relay['relay_id'];
                if (isset($relay['is_running']) && $relay['is_running']) {
                    $name = $relay['name'];
                    $time_left = $relay['time_left'];
                    $duration = $this->seconds2duration($time_left);
                    $waterflow = $relay['waterflow'];

                    $running_zone = [
                        'name'      => $name,
                        'duration'  => $duration,
                        'waterflow' => $waterflow,
                    ];
                    $running_zones[] = $running_zone;
                }
            }

            foreach ($relays as $relay) {
                $relay_id = $relay['relay_id'];
                $name = $relay['name'];

                $duration = '';
                if (isset($relay['duration'])) {
                    // auf Minuten aufrunden
                    $duration = (int) ceil($relay['duration'] / 60) * 60;
                    $duration = $this->seconds2duration($duration);
                }

                $timestr = $relay['timestr'];
                $nextrun = $relay['nextrun'];
                $is_today = false;
                if (date('d.m.Y', $nextrun) == date('d.m.Y', $now)) {
                    $is_today = true;
                }

                if ($is_today) {
                    if (isset($relay['is_running']) && $relay['is_running']) {
                        continue;
                    }
                    // was kommt heute noch?
                    $today_zone = [
                        'name'      => $name,
                        'timestamp' => $nextrun,
                        'duration'  => $duration
                    ];
                    $today_zones[] = $today_zone;
                } elseif ($nextrun) {
                    if (isset($relay['is_suspended']) && $relay['is_suspended']) {
                        continue;
                    }
                    // was kommt in den nächsten Tagen
                    $future_zone = [
                        'name'      => $name,
                        'timestamp' => $nextrun,
                        'duration'  => $duration
                    ];
                    $future_zones[] = $future_zone;
                }
            }

            // was war heute?
            usort($relays, ['HydrawiseController', 'cmp_relays_lastrun']);
            foreach ($relays as $relay) {
                $relay_id = $relay['relay_id'];
                $name = $relay['name'];
                $lastrun = $relay['lastrun'];

                $is_today = false;
                if (date('d.m.Y', $lastrun) == date('d.m.Y', $now)) {
                    $is_today = true;
                }
                if (!$is_today) {
                    continue;
                }

                $secs = $relay['last_duration'] * 60;
                $duration = $this->seconds2duration($secs);

                $daily_duration = $this->GetArrayElem($relay, 'daily_duration', 0);
                $daily_waterusage = $this->GetArrayElem($relay, 'daily_waterusage', 0);

                $is_running = isset($relay['is_running']) ? $relay['is_running'] : false;

                $done_zone = [
                    'name'              => $name,
                    'timestamp'         => $lastrun,
                    'duration'          => $duration,
                    'is_running'        => $is_running,
                    'daily_duration'    => $daily_duration,
                    'daily_waterusage'  => $daily_waterusage
                ];
                $done_zones[] = $done_zone;
            }
        }

        $controller_data = [
            'status'            => $controller['status'],
            'last_contact_ts'   => $last_contact_ts,
            'name'              => $controller_name,
            'running_zones'     => $running_zones,
            'today_zones'       => $today_zones,
            'done_zones'        => $done_zones,
            'future_zones'      => $future_zones,
        ];

        $this->SendDebug(__FUNCTION__, 'controller_data=' . print_r($controller_data, true), 0);

        $this->SetBuffer('Data', json_encode($controller_data));

        $this->SetValue('Status', $controller_status);

        if ($with_status_box) {
            $statusbox_script = $this->ReadPropertyInteger('statusbox_script');
            if ($statusbox_script >= 10000) {
                $html = IPS_RunScriptWaitEx($statusbox_script, ['InstanceID' => $this->InstanceID]);
            } else {
                $html = $this->Build_StatusBox($controller_data);
            }
            $this->SetValue('StatusBox', $html);
        }

        $this->SetStatus(IS_ACTIVE);

        $this->SetUpdateInterval();
    }

    protected function ClearDailyValue()
    {
        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return;
        }

        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');
        if ($with_daily_value) {
            $this->SetValue('DailyDuration', 0);
            $with_waterusage = $this->ReadPropertyBoolean('with_waterusage');
            if ($with_waterusage) {
                $this->SetValue('DailyWaterUsage', 0);
            }
        }

        // an HydrawiseIO
        $controller_id = $this->ReadPropertyString('controller_id');
        $sdata = [
            'DataID'        => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}',
            'Function'      => 'ClearDailyValue',
            'controller_id' => $controller_id
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $this->SendDataToParent(json_encode($sdata));
    }

    private function Build_StatusBox($controller_data)
    {
        $with_waterusage = $this->ReadPropertyBoolean('with_waterusage');

        $html = '';

        $html .= "<style>\n";
        $html .= ".right-align { text-align: right; }\n";
        $html .= "table { border-collapse: collapse; border: 1px solid; margin: 1; width: 95%; }\n";
        $html .= "tr { border-left: 1px solid; border-top: 1px solid; border-bottom: 1px solid; } \n";
        $html .= "tr:first-child { border-top: 0 none; } \n";
        $html .= "th, td { border: 1px solid; margin: 1; padding: 3px; } \n";
        $html .= "tbody th { text-align: left; }\n";
        $html .= "#spalte_zeitpunkt { width: 120px; }\n";
        $html .= "#spalte_uhrzeit { width: 70px; }\n";
        $html .= "#spalte_dauer { width: 60px; }\n";
        $html .= "#spalte_volumen { width: 70px; }\n";
        $html .= "#spalte_flow { width: 80px; }\n";
        $html .= "#spalte_rest { width: 100px; }\n";
        $html .= "</style>\n";

        $running_zones = $controller_data['running_zones'];
        $today_zones = $controller_data['today_zones'];
        $done_zones = $controller_data['done_zones'];
        $future_zones = $controller_data['future_zones'];

        // aktuell durchgeführte Bewässerung
        $b = false;
        foreach ($running_zones as $zone) {
            $name = $zone['name'];
            $duration = $zone['duration'];
            $waterflow = $zone['waterflow'];

            if (!$b) {
                $html .= "<br>\n";
                $html .= "<table>\n";
                $html .= "<colgroup><col></colgroup>\n";
                $html .= "<colgroup><col id='spalte_rest'></colgroup>\n";
                if ($waterflow != '') {
                    $html .= "<colgroup><col id='spalte_flow'></colgroup>\n";
                }
                $html .= "<thead>\n";
                $html .= "<tr>\n";
                $html .= "<th>derzeitige Bewässerung</th>\n";
                $html .= "<th>Restdauer</th>\n";
                if ($waterflow != '') {
                    $html .= "<th>Rate</th>\n";
                }
                $html .= "</tr>\n";
                $html .= "</thead>\n";
                $html .= "<tdata>\n";
                $b = true;
            }

            $html .= "<tr>\n";
            $html .= "<td>$name</td>\n";
            $html .= "<td class='right-align'>$duration</td>\n";
            if ($waterflow != '') {
                $waterflow = ceil($waterflow * 10) / 10;
                $html .= "<td class='right-align'>$waterflow l/m</td>\n";
            }
            $html .= "</tr>\n";
        }
        if ($b) {
            $html .= "</tdata>\n";
            $html .= "</table>\n";
        }

        // was kommt heute noch?
        $b = false;
        foreach ($today_zones as $zone) {
            $name = $zone['name'];
            $timestamp = $zone['timestamp'];
            $time = date('H:i', $timestamp);
            $duration = $zone['duration'];

            if (!$b) {
                $html .= "<br>\n";
                $html .= "<table>\n";
                $html .= "<colgroup><col></colgroup>\n";
                $html .= "<colgroup><col id='spalte_zeitpunkt'></colgroup>\n";
                $html .= "<colgroup><col id='spalte_dauer'></colgroup>\n";
                $html .= "<thead>\n";
                $html .= "<tr>\n";
                $html .= "<th>heute noch geplante Bewässerung</th>\n";
                $html .= "<th>Uhrzeit</th>\n";
                $html .= "<th>Dauer</th>\n";
                $html .= "</tr>\n";
                $html .= "</thead>\n";
                $html .= "<tdata>\n";
                $b = true;
            }

            $html .= "<tr>\n";
            $html .= "<td>$name</td>\n";
            $html .= "<td class='right-align'>$time</td>\n";
            $html .= "<td class='right-align'>$duration</td>\n";
            $html .= "</tr>\n";
        }
        if ($b) {
            $html .= "</tdata>\n";
            $html .= "</table>\n";
        }

        // was war heute?
        $b = false;
        foreach ($done_zones as $zone) {
            $name = $zone['name'];
            $timestamp = $zone['timestamp'];
            $time = date('H:i', $timestamp);
            $duration = $zone['duration'];
            $daily_duration = $zone['daily_duration'];
            $_daily_duration = $this->seconds2duration($daily_duration * 60);
            if ($with_waterusage) {
                $daily_waterusage = ceil($zone['daily_waterusage']);
            }
            $is_running = $zone['is_running'];
            if ($is_running) {
                $time = 'aktuell';
                $duration = '-&nbsp';
            }

            if (!$b) {
                $html .= "<br>\n";
                $html .= "<table>\n";
                $html .= "<colgroup><col></colgroup>\n";
                $html .= "<colgroup><col id='spalte_uhrzeit'></colgroup>\n";
                $html .= "<colgroup><col id='spalte_dauer'></colgroup>\n";
                $html .= "<colgroup><col id='spalte_dauer'></colgroup>\n";
                if ($with_waterusage) {
                    $html .= "<colgroup><col id='spalte_volumen'></colgroup>\n";
                }
                $html .= "<thead>\n";
                $html .= "<tr>\n";
                $html .= "<th>heute bereits durchgeführte Bewässerung</th>\n";
                $html .= "<th colspan='2'>zuletzt</th>\n";
                if ($with_waterusage) {
                    $html .= "<th colspan='2'>gesamt</th>\n";
                } else {
                    $html .= "<th colspan='1'>gesamt</th>\n";
                }
                $html .= "</tr>\n";

                $html .= "<tr>\n";
                $html .= "<th>&nbsp;</th>\n";
                $html .= "<th>Uhrzeit</th>\n";
                $html .= "<th>Dauer</th>\n";
                $html .= "<th>Dauer</th>\n";
                if ($with_waterusage) {
                    $html .= "<th>Menge</th>\n";
                }
                $html .= "</tr>\n";

                $html .= "</thead>\n";
                $html .= "<tdata>\n";
                $b = true;
            }

            $html .= "<tr>\n";
            $html .= "<td>$name</td>\n";
            $html .= "<td class='right-align'>$time</td>\n";
            $html .= "<td class='right-align'>$duration</td>\n";
            $html .= "<td class='right-align'>$_daily_duration</td>\n";
            if ($with_waterusage) {
                $html .= "<td class='right-align'>$daily_waterusage l</td>\n";
            }
            $html .= "</tr>\n";
        }
        if ($b) {
            $html .= "</tdata>\n";
            $html .= "</table>\n";
        }

        // was kommt in den nächsten Tagen
        $b = false;
        foreach ($future_zones as $zone) {
            $name = $zone['name'];
            $timestamp = $zone['timestamp'];
            $duration = $zone['duration'];
            $date = date('d.m. H:i', (int) $timestamp);

            if (!$b) {
                $html .= "<br>\n";
                $html .= "<table>\n";
                $html .= "<colgroup><col></colgroup>\n";
                $html .= "<colgroup><col id='spalte_zeitpunkt'></colgroup>\n";
                $html .= "<colgroup><col id='spalte_dauer'></colgroup>\n";
                $html .= "<thead>\n";
                $html .= "<tr>\n";
                $html .= "<th>demnächst geplante Bewässerung</th>\n";
                $html .= "<th>Zeitpunkt</th>\n";
                $html .= "<th>Dauer</th>\n";
                $html .= "</tr>\n";
                $html .= "</thead>\n";
                $html .= "<tdata>\n";
                $b = 1;
            }

            $html .= "<tr>\n";
            $html .= "<td>$name</td>\n";
            $html .= "<td class='right-align'>$date</td>\n";
            $html .= "<td class='right-align'>$duration</td>\n";
            $html .= "</tr>\n";
        }
        if ($b) {
            $html .= "</tdata>\n";
            $html .= "</table>\n";
        }

        return $html;
    }

    private function ProcessHook_Status()
    {
        $with_waterusage = $this->ReadPropertyBoolean('with_waterusage');

        $s = $this->GetBuffer('Data');
        $controller_data = json_decode($s, true);

        $now = time();

        $html = '';

        $html .= "<!DOCTYPE html>\n";
        $html .= "<html>\n";
        $html .= "<head><meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>\n";
        $html .= "<link href='https://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet'>\n";
        $html .= "<title>Status von Hydrawise</title>\n";
        $html .= "<style>\n";
        $html .= "html { height: 100%; background-color: darkgrey; overflow: hidden; }\n";
        $html .= "body { table-cell; text-align: left; vertical-align: top; height: 100%; }\n";
        $html .= "</style>\n";
        $html .= "</head>\n";
        $html .= "<body>\n";
        $html .= "<style>\n";
        $html .= "body { margin: 1; padding: 0; font-family: 'Open Sans', sans-serif; font-size: 14px}\n";
        $html .= "table { border-collapse: collapse; border: 1px solid; margin: 0.5em; width: 95%; }\n";
        $html .= "tr { border-top: 1px solid; border-bottom: 1px solid; } \n";
        $html .= "tr:first-child { border-top: 0 none; } \n";
        $html .= "th, td { padding: 1px 2px; } \n";
        $html .= "thead tr, tr:nth-child(odd) { background-color: lightgrey; }\n";
        $html .= "thead tr, tr:nth-child(even) { background-color: white; }\n";
        $html .= "tbody th { text-align: left; }\n";
        $html .= "#spalte_zeitpunkt { width: 85px; }\n";
        $html .= "#spalte_dauer { width: 40px; }\n";
        $html .= "#spalte_uhrzeit { width: 70px; }\n";
        $html .= "#spalte_volumen { width: 50px; }\n";
        $html .= "#spalte_flow { width: 60px; }\n";
        $html .= "#spalte_rest { width: 55px; }\n";
        $html .= "</style>\n";

        if ($controller_data != '') {
            // Daten des Controllers
            $last_contact_ts = $controller_data['last_contact_ts'];
            $status = $controller_data['status'];
            $name = $controller_data['name'];

            if ($last_contact_ts) {
                $duration = $this->seconds2duration($now - $last_contact_ts);
                if ($duration != '') {
                    $contact = 'vor ' . $duration;
                } else {
                    $contact = 'jetzt';
                }
            } else {
                $contact = '';
            }

            $dt = date('d.m. H:i', $now);
            $s = '<font size="-1">Stand:</font> ';
            $s .= $dt;
            $s .= '&emsp;';
            $s .= '<font size="-1">Status:</font> ';
            $s .= $status;
            if ($contact != '') {
                $s .= " <font size='-2'>($contact)</font>";
            }
            $html .= "<center>$s</center><br>\n";

            $running_zones = $controller_data['running_zones'];
            $today_zones = $controller_data['today_zones'];
            $done_zones = $controller_data['done_zones'];
            $future_zones = $controller_data['future_zones'];

            // aktuell durchgeführte Bewässerung
            $b = false;
            foreach ($running_zones as $zone) {
                $name = $zone['name'];
                $duration = $zone['duration'];
                $waterflow = $zone['waterflow'];

                if (!$b) {
                    $html .= "derzeitige Bewässerung\n";
                    $html .= "<table>\n";
                    $html .= "<colgroup><col></colgroup>\n";
                    $html .= "<colgroup><col id='spalte_rest'></colgroup>\n";
                    if ($waterflow != '') {
                        $html .= "<colgroup><col id='spalte_flow'></colgroup>\n";
                    }
                    $html .= "<thead>\n";
                    $html .= "<tr>\n";
                    $html .= "<th>Bezeichnung</th>\n";
                    $html .= "<th>Rest</th>\n";
                    if ($waterflow != '') {
                        $html .= "<th>Rate</th>\n";
                    }
                    $html .= "</tr>\n";
                    $html .= "</thead>\n";
                    $html .= "<tdata>\n";
                    $b = true;
                }

                $html .= "<tr>\n";
                $html .= "<td>$name</td>\n";
                $html .= "<td class='right-align'>$duration</td>\n";
                if ($waterflow != '') {
                    $waterflow = ceil($waterflow);
                    $html .= "<td class='right-align'>$waterflow l/m</td>\n";
                }
                $html .= "</tr>\n";
            }
            if ($b) {
                $html .= "</tdata>\n";
                $html .= "</table>\n";
            }

            // was kommt heute noch?
            $b = false;
            foreach ($today_zones as $zone) {
                $name = $zone['name'];
                $timestamp = $zone['timestamp'];
                $time = date('H:i', $timestamp);
                $duration = $zone['duration'];

                if (!$b) {
                    $html .= "heute noch geplante Bewässerung\n";
                    $html .= "<table>\n";
                    $html .= "<colgroup><col></colgroup>\n";
                    $html .= "<colgroup><col id='spalte_zeitpunkt'></colgroup>\n";
                    $html .= "<colgroup><col id='spalte_dauer'></colgroup>\n";
                    $html .= "<thead>\n";
                    $html .= "<tr>\n";
                    $html .= "<th>Bezeichnung</th>\n";
                    $html .= "<th>Uhrzeit</th>\n";
                    $html .= "<th>Dauer</th>\n";
                    $html .= "</tr>\n";
                    $html .= "</thead>\n";
                    $html .= "<tdata>\n";
                    $b = true;
                }

                $html .= "<tr>\n";
                $html .= "<td>$name</td>\n";
                $html .= "<td align='right'>$time</td>\n";
                $html .= "<td align='right'>$duration</td>\n";
                $html .= "</tr>\n";
            }
            if ($b) {
                $html .= "</tdata>\n";
                $html .= "</table>\n";
            }

            // was war heute?
            $b = false;
            foreach ($done_zones as $zone) {
                $name = $zone['name'];
                $timestamp = $zone['timestamp'];
                $time = date('H:i', $timestamp);
                $duration = $zone['duration'];
                $daily_duration = $zone['daily_duration'];
                $_daily_duration = $this->seconds2duration($daily_duration * 60);
                if ($with_waterusage) {
                    $daily_waterusage = ceil($zone['daily_waterusage']);
                }

                if (!$b) {
                    $html .= "heute bereits durchgeführte Bewässerung\n";
                    $html .= "<table>\n";
                    $html .= "<colgroup><col></colgroup>\n";
                    $html .= "<colgroup><col id='spalte_dauer'></colgroup>\n";
                    if ($with_waterusage) {
                        $html .= "<colgroup><col id='spalte_volumen'></colgroup>\n";
                    }
                    $html .= "<thead>\n";
                    $html .= "<tr>\n";
                    $html .= "<th>Bezeichnung</th>\n";
                    $html .= "<th>Dauer</th>\n";
                    if ($with_waterusage) {
                        $html .= "<th>Menge</th>\n";
                    }
                    $html .= "</tr>\n";
                    $html .= "</thead>\n";
                    $html .= "<tdata>\n";
                    $b = true;
                }

                $html .= "<tr>\n";
                $html .= "<td>$name</td>\n";
                $html .= "<td align='right'>$_daily_duration</td>\n";
                if ($with_waterusage) {
                    $html .= "<td align='right'>$daily_waterusage l</td>\n";
                }
                $html .= "</tr>\n";
            }
            if ($b) {
                $html .= "</tdata>\n";
                $html .= "</table>\n";
            }

            // was kommt in den nächsten Tagen
            $b = false;
            foreach ($future_zones as $zone) {
                $name = $zone['name'];
                $timestamp = $zone['timestamp'];
                $duration = $zone['duration'];
                $date = date('d.m. H:i', $timestamp);

                if (!$b) {
                    $html .= "demnächst geplante Bewässerung\n";
                    $html .= "<table>\n";
                    $html .= "<colgroup><col></colgroup>\n";
                    $html .= "<colgroup><col id='spalte_zeitpunkt'></colgroup>\n";
                    $html .= "<colgroup><col id='spalte_dauer'></colgroup>\n";
                    $html .= "<thead>\n";
                    $html .= "<tr>\n";
                    $html .= "<th>Bezeichnung</th>\n";
                    $html .= "<th>Zeitpunkt</th>\n";
                    $html .= "<th>Dauer</th>\n";
                    $html .= "</tr>\n";
                    $html .= "</thead>\n";
                    $html .= "<tdata>\n";
                    $b = 1;
                }

                $html .= "<tr>\n";
                $html .= "<td>$name</td>\n";
                $html .= "<td align='right'>$date</td>\n";
                $html .= "<td align='right'>$duration</td>\n";
                $html .= "</tr>\n";
            }
            if ($b) {
                $html .= "</tdata>\n";
                $html .= "</table>\n";
            }

            $html .= "<br>\n";
        }
        $html .= "</body>\n";
        $html .= "</html>\n";

        echo $html;
    }

    public function GetRawData()
    {
        $s = $this->GetBuffer('Data');
        return $s;
    }

    // Inspired from module SymconTest/HookServe
    protected function ProcessHookData()
    {
        $this->SendDebug(__FUNCTION__, '_SERVER=' . print_r($_SERVER, true), 0);

        $root = realpath(__DIR__);
        $uri = $_SERVER['REQUEST_URI'];
        if (substr($uri, -1) == '/') {
            http_response_code(404);
            die('File not found!');
        }
        $hook = $this->ReadPropertyString('hook');
        if ($hook == '') {
            http_response_code(404);
            die('File not found!');
        }
        if (substr($uri, -1) != '/') {
            $hook .= '/';
        }
        $basename = substr($uri, strlen($hook));
        $this->SendDebug(__FUNCTION__, 'basename=' . $basename, 0);
        if ($basename == 'status') {
            $webhook_script = $this->ReadPropertyInteger('webhook_script');
            if ($webhook_script >= 10000) {
                $html = IPS_RunScriptWaitEx($webhook_script, ['InstanceID' => $this->InstanceID]);
                echo $html;
            } else {
                $this->ProcessHook_Status();
            }
            return;
        }
        $path = realpath($root . '/' . $basename);
        if ($path === false) {
            http_response_code(404);
            die('File not found!');
        }
        if (substr($path, 0, strlen($root)) != $root) {
            http_response_code(403);
            die('Security issue. Cannot leave root folder!');
        }
        header('Content-Type: ' . $this->GetMimeType(pathinfo($path, PATHINFO_EXTENSION)));
        readfile($path);
    }

    // Sortierfunkion: nach nächstem geplantem Vorgang
    // Format des Zeitstempels: "Tue, 30th May 11:00am"
    private function cmp_relays_nextrun($a, $b)
    {
        $a_nextrun = $a['nextrun'];
        $b_nextrun = $b['nextrun'];

        if ($a_nextrun != $b_nextrun) {
            return ($a_nextrun < $b_nextrun) ? -1 : 1;
        }

        $a_relay = $a['relay'];
        $b_relay = $b['relay'];

        if ($a_relay == $b_relay) {
            return 0;
        }
        return ($a_relay < $b_relay) ? -1 : 1;
    }

    // Sortierfunkion: nach letztem durchgeführtem Vorgang
    // Format des Zeitstempels: "6 hours 22 minutes ago"
    private function cmp_relays_lastrun($a, $b)
    {
        $a_lastrun = $a['lastrun'];
        $b_lastrun = $b['lastrun'];

        if ($a_lastrun != $b_lastrun) {
            return ($a_lastrun < $b_lastrun) ? -1 : 1;
        }

        $a_relay = $a['relay'];
        $b_relay = $b['relay'];

        if ($a_relay == $b_relay) {
            return 0;
        }
        return ($a_relay < $b_relay) ? -1 : 1;
    }

    private function SetLocation()
    {
        $catID = $this->ReadPropertyInteger('ImportCategoryID');
        $tree_position = [];
        if ($catID >= 10000 && IPS_ObjectExists($catID)) {
            $tree_position[] = IPS_GetName($catID);
            $parID = IPS_GetObject($catID)['ParentID'];
            while ($parID > 0) {
                if ($parID > 0) {
                    $tree_position[] = IPS_GetName($parID);
                }
                $parID = IPS_GetObject($parID)['ParentID'];
            }
            $tree_position = array_reverse($tree_position);
        }
        $this->SendDebug(__FUNCTION__, 'tree_position=' . print_r($tree_position, true), 0);
        return $tree_position;
    }
}
