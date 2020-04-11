<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/library.php';  // modul-bezogene Funktionen

class HydrawiseController extends IPSModule
{
    use HydrawiseCommon;
    use HydrawiseLibrary;

    public static $support_waterusage = false;
    public static $support_observations = false;
    public static $support_forecast = false;
    public static $support_info = false;

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
        $this->RegisterPropertyBoolean('with_info', false);
        $this->RegisterPropertyBoolean('with_observations', true);
        $this->RegisterPropertyInteger('num_forecast', 0);
        $this->RegisterPropertyBoolean('with_status_box', false);
        $this->RegisterPropertyBoolean('with_daily_value', true);

        $this->RegisterPropertyInteger('ImportCategoryID', 0);

        $this->CreateVarProfile('Hydrawise.Temperatur', VARIABLETYPE_FLOAT, ' °C', -10, 30, 0, 1, 'Temperature');
        $this->CreateVarProfile('Hydrawise.WaterSaving', VARIABLETYPE_INTEGER, ' %', 0, 0, 0, 0, 'Drops');
        $this->CreateVarProfile('Hydrawise.Rainfall', VARIABLETYPE_FLOAT, ' mm', 0, 60, 0, 1, 'Rainfall');
        $this->CreateVarProfile('Hydrawise.ProbabilityOfRain', VARIABLETYPE_INTEGER, ' %', 0, 0, 0, 0, 'Rainfall');
        $this->CreateVarProfile('Hydrawise.WindSpeed', VARIABLETYPE_FLOAT, ' km/h', 0, 100, 0, 0, 'WindSpeed');
        $this->CreateVarProfile('Hydrawise.Humidity', VARIABLETYPE_FLOAT, ' %', 0, 100, 0, 0, 'Drops');
        $this->CreateVarProfile('Hydrawise.Duration', VARIABLETYPE_INTEGER, ' min', 0, 0, 0, 0, 'Hourglass');

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
                if ($this->HookIsUsed($hook)) {
                    $this->SetStatus(self::$IS_USEDWEBHOOK);
                    return;
                }
                $this->RegisterHook($hook);
            }
            $this->SetUpdateInterval();
        }
    }

    protected function SetUpdateInterval()
    {
        $sec = $this->ReadPropertyInteger('update_interval');
        $msec = $sec > 0 ? $sec * 1000 : 0;
        $this->SetTimerInterval('UpdateController', $msec);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $controller_id = $this->ReadPropertyString('controller_id');
        $with_last_contact = $this->ReadPropertyBoolean('with_last_contact');
        $with_last_message = $this->ReadPropertyBoolean('with_last_message');
        $with_info = self::$support_info ? $this->ReadPropertyBoolean('with_info') : false;
        $with_observations = self::$support_observations ? $this->ReadPropertyBoolean('with_observations') : false;
        $num_forecast = self::$support_forecast ? $this->ReadPropertyInteger('num_forecast') : 0;
        $with_status_box = $this->ReadPropertyBoolean('with_status_box');
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');

        $vpos = 1;

        $this->MaintainVariable('Status', $this->Translate('State'), VARIABLETYPE_BOOLEAN, '~Alert.Reversed', $vpos++, true);
        $this->MaintainVariable('LastContact', $this->Translate('last contact'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, $with_last_contact);
        $this->MaintainVariable('LastMessage', $this->Translate('last message'), VARIABLETYPE_STRING, '', $vpos++, $with_last_message);
        $this->MaintainVariable('DailyReference', $this->Translate('day of cumulation'), VARIABLETYPE_INTEGER, '~UnixTimestampDate', $vpos++, $with_daily_value);
        $this->MaintainVariable('DailyWateringTime', $this->Translate('Watering time (day)'), VARIABLETYPE_INTEGER, 'Hydrawise.Duration', $vpos++, $with_info && $with_daily_value);
        $this->MaintainVariable('WateringTime', $this->Translate('Watering time (week)'), VARIABLETYPE_INTEGER, 'Hydrawise.Duration', $vpos++, $with_info);
        $this->MaintainVariable('WaterSaving', $this->Translate('Water saving'), VARIABLETYPE_INTEGER, 'Hydrawise.WaterSaving', $vpos++, $with_info);

        $this->MaintainVariable('ObsRainDay', $this->Translate('Rainfall (last day)'), VARIABLETYPE_FLOAT, 'Hydrawise.Rainfall', $vpos++, $with_observations);
        $this->MaintainVariable('ObsRainWeek', $this->Translate('Rainfall (last week)'), VARIABLETYPE_FLOAT, 'Hydrawise.Rainfall', $vpos++, $with_observations);
        $this->MaintainVariable('ObsCurTemp', $this->Translate('currrent Temperature'), VARIABLETYPE_FLOAT, 'Hydrawise.Temperatur', $vpos++, $with_observations);
        $this->MaintainVariable('ObsMaxTemp', $this->Translate('maximum Temperature (24h)'), VARIABLETYPE_FLOAT, 'Hydrawise.Temperatur', $vpos++, $with_observations);

        $words = ['today', 'tomorrow', 'overmorrow'];
        for ($i = 0; $i < 3; $i++) {
            $with_forecast = $i < $num_forecast;
            $s = ' (' . $this->Translate($words[$i]) . ')';
            $this->MaintainVariable('Forecast' . $i . 'Conditions', $this->Translate('Conditions') . $s, VARIABLETYPE_STRING, '', $vpos++, $with_forecast);
            $this->MaintainVariable('Forecast' . $i . 'TempMax', $this->Translate('maximum Temperature') . $s, VARIABLETYPE_FLOAT, 'Hydrawise.Temperatur', $vpos++, $with_forecast);
            $this->MaintainVariable('Forecast' . $i . 'TempMin', $this->Translate('minimum Temperature') . $s, VARIABLETYPE_FLOAT, 'Hydrawise.Temperatur', $vpos++, $with_forecast);
            $this->MaintainVariable('Forecast' . $i . 'ProbabilityOfRain', $this->Translate('Probability of rainfall') . $s, VARIABLETYPE_INTEGER, 'Hydrawise.ProbabilityOfRain', $vpos++, $with_forecast);
            $this->MaintainVariable('Forecast' . $i . 'WindSpeed', $this->Translate('Windspeed') . $s, VARIABLETYPE_FLOAT, 'Hydrawise.WindSpeed', $vpos++, $with_forecast);
            $this->MaintainVariable('Forecast' . $i . 'Humidity', $this->Translate('Humidity') . $s, VARIABLETYPE_FLOAT, 'Hydrawise.Humidity', $vpos++, $with_forecast);
        }

        $this->MaintainVariable('StatusBox', $this->Translate('State of irrigation'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, $with_status_box);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $hook = $this->ReadPropertyString('hook');
            if ($hook != '') {
                if ($this->HookIsUsed($hook)) {
                    $this->SetStatus(self::$IS_USEDWEBHOOK);
                    return;
                }
                $this->RegisterHook($hook);
            }
            $this->SetUpdateInterval();
        }

        $info = 'Controller (#' . $controller_id . ')';
        $this->SetSummary($info);

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = ['ImportCategoryID', 'statusbox_script', 'webhook_script'];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid > 0) {
                $this->RegisterReference($oid);
            }
        }

        $dataFilter = '.*' . $controller_id . '.*';
        $this->SendDebug(__FUNCTION__, 'set ReceiveDataFilter=' . $dataFilter, 0);
        $this->SetReceiveDataFilter($dataFilter);

        $this->SetStatus(IS_ACTIVE);
    }

    protected function GetFormActions()
    {
        $formActions = [];
        $formActions[] = ['type' => 'Button', 'caption' => 'Update Data', 'onClick' => 'Hydrawise_UpdateController($id);'];

        return $formActions;
    }

    public function getConfiguratorValues()
    {
        $controller_id = $this->ReadPropertyString('controller_id');
        $data = ['DataID' => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}', 'Function' => 'ControllerDetails', 'controller_id' => $controller_id];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $data = $this->SendDataToParent(json_encode($data));
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $controller = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'controller=' . print_r($controller, true), 0);

        $config_list = [];

        if ($controller != '') {
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
                        $model = SENSOR_NORMALLY_CLOSE_START;
                        $mode_txt = 'sensor';
                    } elseif ($type == 1 && $mode == 2) {
                        $model = SENSOR_NORMALLY_OPEN_STOP;
                        $mode_txt = 'sensor';
                    } elseif ($type == 1 && $mode == 3) {
                        $model = SENSOR_NORMALLY_CLOSE_STOP;
                        $mode_txt = 'sensor';
                    } elseif ($type == 1 && $mode == 4) {
                        $model = SENSOR_NORMALLY_OPEN_START;
                        $mode_txt = 'sensor';
                    } elseif ($type == 3 && $mode == 0) {
                        $model = SENSOR_FLOW_METER;
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

                    $create = [
                        'moduleID'      => $guid,
                        'location'      => $this->SetLocation(),
                        'configuration' => [
                            'controller_id' => "$controller_id",
                            'connector'     => $connector,
                            'model'         => $model,
                        ]
                    ];
                    $create['info'] = $ident . ' (' . $controller_name . '\\' . $sensor_name . ')';

                    $entry = [
                        'instanceID'  => $instanceID,
                        'type'        => $this->Translate($mode_txt),
                        'ident'       => $ident,
                        'name'        => $sensor_name,
                        'create'      => $create
                    ];

                    $config_list[] = $entry;
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

                    $create = [
                        'moduleID'      => $guid,
                        'location'      => $this->SetLocation(),
                        'configuration' => [
                            'controller_id' => "$controller_id",
                            'relay_id'      => "$relay_id",
                            'connector'     => $connector,
                        ]
                    ];
                    $create['info'] = $ident . ' (' . $controller_name . '\\' . $zone_name . ')';

                    $entry = [
                        'instanceID'  => $instanceID,
                        'type'        => $this->Translate('Zone'),
                        'ident'       => $ident,
                        'name'        => $zone_name,
                        'create'      => $create
                    ];

                    $config_list[] = $entry;

                    // $this->SendDebug(__FUNCTION__, 'entry=' . print_r($entry, true), 0);
                }
            }
        }

        return $config_list;
    }

    public function GetFormElements()
    {
        $opts_forecast = [];
        $opts_forecast[] = ['caption' => $this->Translate('no'), 'value' => 0];
        $opts_forecast[] = ['caption' => $this->Translate('today'), 'value' => 1];
        $opts_forecast[] = ['caption' => $this->Translate('tomorrow'), 'value' => 2];
        $opts_forecast[] = ['caption' => $this->Translate('overmorrow'), 'value' => 3];

        $formElements = [];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'module_disable', 'caption' => 'Instance is disabled'];

        $formElements[] = ['type' => 'Label', 'caption' => 'Hydrawise Controller'];

        $items = [];
        $items[] = ['type' => 'ValidationTextBox', 'name' => 'controller_id', 'caption' => 'Controller-ID'];
        $formElements[] = ['type' => 'ExpansionPanel', 'items' => $items, 'caption' => 'Basic configuration (don\'t change)'];

        $items = [];
        $items[] = ['type' => 'CheckBox', 'name' => 'with_last_contact', 'caption' => 'last contact to Hydrawise'];
        $items[] = ['type' => 'CheckBox', 'name' => 'with_last_message', 'caption' => 'last message'];
        if (self::$support_info) {
            $items[] = ['type' => 'CheckBox', 'name' => 'with_info', 'caption' => 'info'];
        }
        if (self::$support_observations) {
            $items[] = ['type' => 'CheckBox', 'name' => 'with_observations', 'caption' => 'observations'];
        }
        if (self::$support_forecast) {
            $items[] = ['type' => 'Select', 'name' => 'num_forecast', 'caption' => 'forecast', 'options' => $opts_forecast];
        }
        $items[] = ['type' => 'CheckBox', 'name' => 'with_status_box', 'caption' => 'html-box with state of irrigation'];
        $items[] = ['type' => 'SelectScript', 'name' => 'statusbox_script', 'caption' => 'alternate script to use for the "StatusBox"'];
        $items[] = ['type' => 'CheckBox', 'name' => 'with_daily_value', 'caption' => 'daily sum'];
        $formElements[] = ['type' => 'ExpansionPanel', 'items' => $items, 'caption' => 'optional controller data'];

        $items = [];
        $items[] = ['type' => 'ValidationTextBox', 'name' => 'hook', 'caption' => 'Webhook'];
        $items[] = ['type' => 'SelectScript', 'name' => 'webhook_script', 'caption' => 'alternate script to use for Webhook'];
        $formElements[] = ['type' => 'ExpansionPanel', 'items' => $items, 'caption' => 'Webhook'];

        $items = [];
        $items[] = ['type' => 'Label', 'caption' => 'Update data every X seconds'];
        $items[] = ['type' => 'NumberSpinner', 'name' => 'update_interval', 'caption' => 'Interval', 'suffix' => 'Seconds'];
        $items[] = ['type' => 'Label', 'caption' => 'Duration until the connection to hydrawise is marked disturbed'];
        $items[] = ['type' => 'IntervalBox', 'name' => 'minutes2fail', 'caption' => 'Minutes'];
        $formElements[] = ['type' => 'ExpansionPanel', 'items' => $items, 'caption' => 'Communication'];

        $items = [];
        $items[] = ['type' => 'Label', 'caption' => 'category for components to be created'];
        $items[] = ['name' => 'ImportCategoryID', 'type' => 'SelectCategory', 'caption' => 'category'];

        $entries = $this->getConfiguratorValues();
        $configurator = [
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
        ];
        $items[] = $configurator;
        $formElements[] = ['type' => 'ExpansionPanel', 'items' => $items, 'caption' => 'Sensors and zones'];

        return $formElements;
    }

    public function GetConfigurationForm()
    {
        $formElements = $this->GetFormElements();
        $formActions = $this->GetFormActions();
        $formStatus = $this->GetFormStatus();

        $form = json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
        if ($form == '') {
            $this->SendDebug(__FUNCTION__, 'json_error=' . json_last_error_msg(), 0);
            $this->SendDebug(__FUNCTION__, '=> formElements=' . print_r($formElements, true), 0);
            $this->SendDebug(__FUNCTION__, '=> formActions=' . print_r($formActions, true), 0);
            $this->SendDebug(__FUNCTION__, '=> formStatus=' . print_r($formStatus, true), 0);
        }
        return $form;
    }

    public function UpdateController()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $controller_id = $this->ReadPropertyString('controller_id');

        $data = ['DataID' => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}', 'Function' => 'UpdateController', 'controller_id' => $controller_id];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $ret = $this->SendDataToParent(json_encode($data));
    }

    public function ReceiveData($data)
    {
        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        if (isset($jdata['Buffer'])) {
            $this->DecodeData($jdata['Buffer']);
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
                    default:
                        $this->SendDebug(__FUNCTION__, 'ignore function "' . $jdata['Function'] . '"', 0);
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
        $with_info = self::$support_info ? $this->ReadPropertyBoolean('with_info') : false;
        $with_observations = self::$support_observations ? $this->ReadPropertyBoolean('with_observations') : false;
        $num_forecast = self::$support_forecast ? $this->ReadPropertyInteger('num_forecast') : 0;
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
                if ($r['VariableUpdated'] < time() - 60) {
                    $this->SetValue('LastMessage', '');
                }
            } else {
                $this->SetValue('LastMessage', $message);
            }
        }

        if ($with_observations) {
            $obs_rain_day = preg_replace('/^([0-9\.,]*).*$/', '$1', $controller['obs_rain']);
            if ($obs_rain_day != '') {
                $this->SetValue('ObsRainDay', $obs_rain_day);
            }

            $obs_rain_week = preg_replace('/^([0-9\.,]*).*$/', '$1', $controller['obs_rain_week']);
            if ($obs_rain_week != '') {
                $this->SetValue('ObsRainWeek', $obs_rain_week);
            }

            $obs_curtemp = preg_replace('/^([0-9\.,]*).*$/', '$1', $controller['obs_currenttemp']);
            if ($obs_curtemp != '') {
                $this->SetValue('ObsCurTemp', $obs_curtemp);
            }

            $obs_maxtemp = preg_replace('/^([0-9\.,]*).*$/', '$1', $controller['obs_maxtemp']);
            if ($obs_maxtemp != '') {
                $this->SetValue('ObsMaxTemp', $obs_maxtemp);
            }
        }

        if ($with_info) {
            $watering_time = preg_replace('/^([0-9\.,]*).*$/', '$1', $controller['watering_time']);
            $this->SetValue('WateringTime', $watering_time);

            $water_saving = $controller['water_saving'];
            $this->SetValue('WaterSaving', $water_saving);

            if ($with_daily_value) {
                $old_watering_time = $this->GetBuffer('WateringTime');
                if ($old_watering_time != '' && $old_watering_time < $watering_time) {
                    $new_watering_time = $this->GetValue('DailyWateringTime') + ($watering_time - $old_watering_time);
                    $this->SendDebug(__FUNCTION__, 'new_watering_time=' . $new_watering_time, 0);
                    $this->SetValue('DailyWateringTime', $new_watering_time);
                } else {
                    $this->SendDebug(__FUNCTION__, 'weekly watering_time=' . $watering_time . ' => unchanged', 0);
                }
                $this->SetBuffer('WateringTime', $watering_time);
            }
        }

        if ($num_forecast) {
            $n = 0;
            $forecast = $controller['forecast'];
            if (count($forecast) > 0) {
                foreach ($forecast as $i => $value) {
                    if ($i >= $num_forecast) {
                        continue;
                    }

                    $_forcecast = $forecast[$i];

                    $this->SendDebug(__FUNCTION__, '_forcecast=' . print_r($_forcecast, true), 0);

                    $temp_hi = preg_replace('/^([0-9\.,-]*).*$/', '$1', $_forcecast['temp_hi']);
                    $this->SendDebug(__FUNCTION__, 'temp_hi=' . $temp_hi, 0);
                    $this->SetValue('Forecast' . $i . 'TempMax', $temp_hi);

                    $temp_lo = preg_replace('/^([0-9\.,-]*).*$/', '$1', $_forcecast['temp_lo']);
                    $this->SendDebug(__FUNCTION__, 'temp_lo=' . $temp_lo, 0);
                    $this->SetValue('Forecast' . $i . 'TempMin', $temp_lo);

                    $humidity = preg_replace('/^([0-9\.,]*).*$/', '$1', $_forcecast['humidity']);
                    $this->SendDebug(__FUNCTION__, 'humidity=' . $humidity, 0);
                    $this->SetValue('Forecast' . $i . 'Humidity', $humidity);

                    $wind = preg_replace('/^([0-9\.,]*).*$/', '$1', $_forcecast['wind']);
                    $this->SendDebug(__FUNCTION__, 'wind=' . $wind, 0);
                    $this->SetValue('Forecast' . $i . 'WindSpeed', $wind);

                    $pop = preg_replace('/^([0-9\.,]*).*$/', '$1', $_forcecast['pop']);
                    $this->SendDebug(__FUNCTION__, 'pop=' . $pop, 0);
                    $this->SetValue('Forecast' . $i . 'ProbabilityOfRain', $pop);

                    $conditions = $_forcecast['conditions'];
                    $this->SendDebug(__FUNCTION__, 'conditions=' . $conditions, 0);
                    $this->SetValue('Forecast' . $i . 'Conditions', $conditions);

                    if ($n++ == 3) {
                        break;
                    }
                }
            }
        }

        // Status der Zonen (relays)
        $running_zones = [];
        $today_zones = [];
        $done_zones = [];
        $future_zones = [];

        $relays = $controller['relays'];
        if (count($relays) > 0) {

            // Daten aus den HydrawiseZone-Instanzen ergänzen
            $instIDs = IPS_GetInstanceListByModuleID('{6A0DAE44-B86A-4D50-A76F-532365FD88AE}');
            $_relays = $relays;
            $relays = [];
            foreach ($_relays as $relay) {
                $relay_id = $relay['relay_id'];

                foreach ($instIDs as $instID) {
                    $cfg = IPS_GetConfiguration($instID);
                    $jcfg = json_decode($cfg, true);
                    if ($jcfg['relay_id'] == $relay_id) {
                        $varID = @IPS_GetObjectIDByIdent('LastRun', $instID);
                        if ($varID) {
                            $relay['lastrun'] = GetValue($varID);
                        }
                        $relay['name'] = IPS_GetName($instID);
                        break;
                    }
                }

                $time = $relay['time'];

                $nextrun = 0;
                $run_seconds = 0;
                $running = false;
                $time_left = 0;
                $suspended = false;

                $type = $relay['type'];
                if ($relay['timestr'] == 'Now') {
                    $type = RELAY_TYPE_RUNNING;
                }
                switch ($type) {
                    case RELAY_TYPE_PROGRAMMED:
                        $nextrun = $server_time + $time;
                        $run_seconds = $this->GetArrayElem($relay, 'run', 0);
                        break;
                    case RELAY_TYPE_RUNNING:
                        $running = true;
                        $time_left = $this->GetArrayElem($relay, 'run', 0);
                        break;
                    case RELAY_TYPE_SUSPENDED:
                        $suspended = true;
                        break;
                    default:
                        break;

                }

                $relay['nextrun'] = $nextrun;
                $relay['run_seconds'] = $run_seconds;
                $relay['running'] = $running;
                $relay['time_left'] = $time_left;
                $relay['suspended'] = $suspended;

                $relays[] = $relay;
            }

            usort($relays, ['HydrawiseController', 'cmp_relays_nextrun']);

            // aktuell durchgeführte Bewässerung
            foreach ($relays as $relay) {
                $relay_id = $relay['relay_id'];
                if (isset($relay['running']) && $relay['running']) {
                    $name = $relay['name'];
                    $time_left = $relay['time_left'];
                    $duration = $this->seconds2duration($time_left);

                    $running_zone = [
                        'name'     => $name,
                        'duration' => $duration
                    ];
                    $running_zones[] = $running_zone;
                }
            }

            foreach ($relays as $relay) {
                $relay_id = $relay['relay_id'];
                $name = $relay['name'];

                $duration = '';
                if (isset($relay['run_seconds'])) {
                    // auf Minuten aufrunden
                    $run_seconds = (int) ceil($relay['run_seconds'] / 60) * 60;
                    $duration = $this->seconds2duration($run_seconds);
                }

                $timestr = $relay['timestr'];
                $nextrun = $relay['nextrun'];
                $is_today = false;
                if (date('d.m.Y', $nextrun) == date('d.m.Y', $now)) {
                    $is_today = true;
                }

                if ($is_today) {
                    if (isset($relay['running']) && $relay['running']) {
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
                    if (isset($relay['suspended']) && $relay['suspended']) {
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

                $duration = '';
                $daily_duration = '';
                if (self::$support_waterusage) {
                    $daily_waterusage = '';
                }
                foreach ($instIDs as $instID) {
                    $cfg = IPS_GetConfiguration($instID);
                    $jcfg = json_decode($cfg, true);
                    if ($jcfg['relay_id'] == $relay_id) {
                        $varID = @IPS_GetObjectIDByIdent('LastDuration', $instID);
                        if ($varID) {
                            $secs = GetValue($varID) * 60;
                            $duration = $this->seconds2duration($secs);
                        }
                        if ($jcfg['with_daily_value']) {
                            $varID = @IPS_GetObjectIDByIdent('DailyDuration', $instID);
                            if ($varID) {
                                $daily_duration = GetValue($varID);
                            }
                            if (self::$support_waterusage) {
                                $varID = @IPS_GetObjectIDByIdent('DailyWaterUsage', $instID);
                                if ($varID) {
                                    $daily_waterusage = GetValue($varID);
                                }
                            }
                        }
                        break;
                    }
                }

                $is_running = isset($relay['running']) ? $relay['running'] : false;

                $done_zone = [
                    'name'              => $name,
                    'timestamp'         => $lastrun,
                    'duration'          => $duration,
                    'is_running'        => $is_running,
                    'daily_duration'    => $daily_duration,
                ];
                if (self::$support_waterusage) {
                    $done_zone['daily_waterusage'] = $daily_waterusage;
                }
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
            if ($statusbox_script > 0) {
                $html = IPS_RunScriptWaitEx($statusbox_script, ['InstanceID' => $this->InstanceID]);
            } else {
                $html = $this->Build_StatusBox($controller_data);
            }
            $this->SetValue('StatusBox', $html);
        }

        $this->SetUpdateInterval();
        $this->SetStatus(IS_ACTIVE);
    }

    protected function ClearDailyValue()
    {
        $controller_id = $this->ReadPropertyString('controller_id');
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');
        $with_info = $this->ReadPropertyBoolean('with_info');

        $this->SendDebug(__FUNCTION__, '', 0);

        if ($with_daily_value && $with_info) {
            $this->SetValue('DailyWateringTime', 0);
        }

        $data = ['DataID' => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}', 'Function' => 'ClearDailyValue', 'controller_id' => $controller_id];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToParent(json_encode($data));
    }

    private function Build_StatusBox($controller_data)
    {
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
        $html .= "#spalte_rest { width: 180px; }\n";
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

            if (!$b) {
                $html .= "<br>\n";
                $html .= "<table>\n";
                $html .= "<colgroup><col></colgroup>\n";
                $html .= "<colgroup><col id='spalte_rest'></colgroup>\n";
                $html .= "<thead>\n";
                $html .= "<tr>\n";
                $html .= "<th>derzeitige Bewässerung</th>\n";
                $html .= "<th>Restdauer</th>\n";
                $html .= "</tr>\n";
                $html .= "</thead>\n";
                $html .= "<tdata>\n";
                $b = true;
            }

            $html .= "<tr>\n";
            $html .= "<td>$name</td>\n";
            $html .= "<td>$duration</td>\n";
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
            if (self::$support_waterusage) {
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
                if (self::$support_waterusage) {
                    $html .= "<colgroup><col id='spalte_volumen'></colgroup>\n";
                }
                $html .= "<thead>\n";
                $html .= "<tr>\n";
                $html .= "<th>heute bereits durchgeführte Bewässerung</th>\n";
                $html .= "<th colspan='2'>zuletzt</th>\n";
                if (self::$support_waterusage) {
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
                if (self::$support_waterusage) {
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
            if (self::$support_waterusage) {
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
        $html .= "#spalte_rest { width: 130px; }\n";
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

                if (!$b) {
                    $html .= "derzeitige Bewässerung\n";
                    $html .= "<table>\n";
                    $html .= "<colgroup><col></colgroup>\n";
                    $html .= "<colgroup><col id='spalte_rest'></colgroup>\n";
                    $html .= "<thead>\n";
                    $html .= "<tr>\n";
                    $html .= "<th>Bezeichnung</th>\n";
                    $html .= "<th>Restdauer</th>\n";
                    $html .= "</tr>\n";
                    $html .= "</thead>\n";
                    $html .= "<tdata>\n";
                    $b = true;
                }

                $html .= "<tr>\n";
                $html .= "<td>$name</td>\n";
                $html .= "<td>$duration</td>\n";
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
                if (self::$support_waterusage) {
                    $daily_waterusage = ceil($zone['daily_waterusage']);
                }

                if (!$b) {
                    $html .= "heute bereits durchgeführte Bewässerung\n";
                    $html .= "<table>\n";
                    $html .= "<colgroup><col></colgroup>\n";
                    $html .= "<colgroup><col id='spalte_dauer'></colgroup>\n";
                    if (self::$support_waterusage) {
                        $html .= "<colgroup><col id='spalte_volumen'></colgroup>\n";
                    }
                    $html .= "<thead>\n";
                    $html .= "<tr>\n";
                    $html .= "<th>Bezeichnung</th>\n";
                    $html .= "<th>Dauer</th>\n";
                    if (self::$support_waterusage) {
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
                if (self::$support_waterusage) {
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
            if ($webhook_script > 0) {
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
}
