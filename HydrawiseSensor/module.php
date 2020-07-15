<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/library.php';  // modul-bezogene Funktionen

class HydrawiseSensor extends IPSModule
{
    use HydrawiseCommon;
    use HydrawiseLibrary;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('controller_id', '');
        $this->RegisterPropertyInteger('connector', -1);
        $this->RegisterPropertyInteger('model', 0);
        $this->RegisterPropertyBoolean('with_daily_value', true);
        $this->RegisterPropertyBoolean('with_flowrate', true);

        $this->CreateVarProfile('Hydrawise.Flowmeter', VARIABLETYPE_FLOAT, ' l', 0, 0, 0, 0, 'Gauge');
        $this->CreateVarProfile('Hydrawise.WaterFlowrate', VARIABLETYPE_FLOAT, ' l/min', 0, 0, 0, 1, '');

        $associations = [];
        $associations[] = ['Wert' => false, 'Name' => $this->Translate('inactive'), 'Farbe' => -1];
        $associations[] = ['Wert' => true, 'Name' => $this->Translate('active'), 'Farbe' => 0xFF5D5D];
        $this->CreateVarProfile('Hydrawise.RainSensor', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 1, '', $associations);

        $this->ConnectParent('{5927E05C-82D0-4D78-B8E0-A973470A9CD3}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $controller_id = $this->ReadPropertyString('controller_id');
        $connector = $this->ReadPropertyInteger('connector');
        $model = $this->ReadPropertyInteger('model');
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');
        $with_flowrate = $this->ReadPropertyBoolean('with_flowrate');

        $vpos = 1;

        switch ($model) {
            case SENSOR_FLOW_METER:
                $this->MaintainVariable('WaterFlowrate', $this->Translate('Water flow rate (current)'), VARIABLETYPE_FLOAT, 'Hydrawise.WaterFlowrate', $vpos++, $with_flowrate);
                $this->MaintainVariable('DailyWaterUsage', $this->Translate('Water usage (today)'), VARIABLETYPE_FLOAT, 'Hydrawise.Flowmeter', $vpos++, $with_daily_value);
                break;
            case SENSOR_NORMALLY_CLOSE_START:
            case SENSOR_NORMALLY_OPEN_STOP:
            case SENSOR_NORMALLY_CLOSE_STOP:
            case SENSOR_NORMALLY_OPEN_START:
                $this->MaintainVariable('State', $this->Translate('State'), VARIABLETYPE_BOOLEAN, 'Hydrawise.RainSensor', $vpos++, true);
                break;
            default:
                $mode_txt = 'unsupported';
                break;
        }

        $this->UnregisterVariable('DailyFlow');
        $this->UnregisterVariable('Flow');

        switch ($model) {
            case SENSOR_FLOW_METER:
                $mode_txt = 'flow meter';
                break;
            case SENSOR_NORMALLY_CLOSE_START:
                $mode_txt = 'normally close, start zone';
                break;
            case SENSOR_NORMALLY_OPEN_STOP:
                $mode_txt = 'normally open, stop zone';
                break;
            case SENSOR_NORMALLY_CLOSE_STOP:
                $mode_txt = 'normally close, stop zone';
                break;
            case SENSOR_NORMALLY_OPEN_START:
                $mode_txt = 'normally open, start zone';
                break;
            default:
                $mode_txt = 'unsupported';
                break;
        }

        $info = 'Sensor ' . $connector . ' (' . $mode_txt . ')';
        $this->SetSummary($info);

        $dataFilter = '.*' . $controller_id . '.*';
        $this->SendDebug(__FUNCTION__, 'set ReceiveDataFilter=' . $dataFilter, 0);
        $this->SetReceiveDataFilter($dataFilter);

        $this->SetStatus(IS_ACTIVE);
    }

    protected function GetFormActions()
    {
        $formActions = [];

        return $formActions;
    }

    public function GetFormElements()
    {
        $model = $this->ReadPropertyInteger('model');
        $opts_connector = [];
        $opts_connector[] = ['caption' => $this->Translate('no'), 'value' => 0];
        for ($s = 1; $s <= 2; $s++) {
            $l = $this->Translate('Sensor') . ' ' . $s;
            $opts_connector[] = ['caption' => $l, 'value' => $s];
        }

        $opts_model = [
            [
                'caption' => 'unknown',
                'value'   => 0
            ],
            [
                'caption' => 'normally close, action start',
                'value'   => SENSOR_NORMALLY_CLOSE_START
            ],
            [
                'caption' => 'normally open, action stop',
                'value'   => SENSOR_NORMALLY_OPEN_STOP
            ],
            [
                'caption' => 'normally close, action stop',
                'value'   => SENSOR_NORMALLY_CLOSE_STOP
            ],
            [
                'caption' => 'normally open, action start',
                'value'   => SENSOR_NORMALLY_CLOSE_START
            ],
            [
                'caption' => 'flow meter',
                'value'   => SENSOR_FLOW_METER
            ],
        ];

        $formElements = [];
        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Hydrawise Sensor'
        ];

        $items = [];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'controller_id',
            'caption' => 'Controller-ID',
            'enabled' => false
        ];
        $items[] = [
            'type'    => 'Select',
            'name'    => 'connector',
            'caption' => 'connector',
            'options' => $opts_connector,
            'enabled' => false
        ];
        $items[] = [
            'type'    => 'Select',
            'name'    => 'model',
            'caption' => 'model',
            'options' => $opts_model,
            'enabled' => false
        ];
        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => $items,
            'caption' => 'Basic configuration (don\'t change)'
        ];

        if ($model == SENSOR_FLOW_METER) {
            $items = [];
            $items[] = [
                'type'    => 'CheckBox',
                'name'    => 'with_daily_value',
                'caption' => 'daily sum'
            ];
            $items[] = [
                'type'    => 'CheckBox',
                'name'    => 'with_flowrate',
                'caption' => 'flowrate'
            ];
            $formElements[] = [
                'type'    => 'ExpansionPanel',
                'items'   => $items,
                'caption' => 'optional sensor data'
            ];
        }

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
                    case 'ClearDailyValue':
                        $this->ClearDailyValue();
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
        $connector = $this->ReadPropertyInteger('connector');
        $model = $this->ReadPropertyInteger('model');
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');
        $with_flowrate = $this->ReadPropertyBoolean('with_flowrate');

        $err = '';
        $statuscode = 0;
        $do_abort = false;

        if ($buf != '') {
            $controller = json_decode($buf, true);
            $id = $this->GetArrayElem($controller, 'controller_id', '');
            if ($controller_id != $id) {
                $err = 'controller_id "' . $controller_id . '" not found';
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
            $this->SetStatus($statuscode);
            return -1;
        }

        $vpos = 1;

        $flow = 0;
        $sensors = $this->GetArrayElem($controller, 'sensors', '');
        if ($sensors != '') {
            foreach ($sensors as $sensor) {
                if ($connector != ($sensor['input'] + 1)) {
                    continue;
                }
                $this->SendDebug(__FUNCTION__, 'sensor=' . print_r($sensor, true), 0);
                switch ($model) {
                    case SENSOR_FLOW_METER:
                        $water_flowrate = 0;
                        $daily_waterusage = 0;
                        if (isset($sensor['relays'])) {
                            $relays = $sensor['relays'];

                            $ret = $this->CollectZoneValues();
                            $responses = json_decode($ret, true);

                            foreach ($relays as $relay) {
                                $relay_id = $relay['id'];

                                // Daten aus den HydrawiseZone-Instanzen ergänzen
                                foreach ($responses as $response) {
                                    $values = json_decode($response, true);
                                    if ($values['relay_id'] == $relay_id) {
                                        $name = $values['Name'];
                                        if (isset($values['WaterFlowrate'])) {
                                            $f = $values['WaterFlowrate'];
                                            if ($f > 0) {
                                                $water_flowrate += $f;
                                                $this->SendDebug(__FUNCTION__, '  relay_id=' . $relay_id . '(' . $name . '), water_flowrate=' . $water_flowrate, 0);
                                            }
                                        }
                                        if (isset($values['DailyWaterUsage'])) {
                                            $f = $values['DailyWaterUsage'];
                                            if ($f > 0) {
                                                $daily_waterusage += $f;
                                                $this->SendDebug(__FUNCTION__, '  relay_id=' . $relay_id . '(' . $name . '), waterusage=' . $f . ' => ' . $daily_waterusage, 0);
                                            }
                                        }
                                    }
                                }
                            }

                            if ($with_flowrate) {
                                $this->SendDebug(__FUNCTION__, 'water_flowrate=' . $water_flowrate, 0);
                                $this->SetValue('WaterFlowrate', $water_flowrate);
                            }
                            if ($with_daily_value) {
                                $this->SendDebug(__FUNCTION__, 'daily_waterusage=' . $daily_waterusage, 0);
                                $this->SetValue('DailyWaterUsage', $daily_waterusage);
                            }
                        }
                        break;
                    case SENSOR_NORMALLY_CLOSE_START:
                    case SENSOR_NORMALLY_OPEN_STOP:
                    case SENSOR_NORMALLY_CLOSE_STOP:
                    case SENSOR_NORMALLY_OPEN_START:
                        $mode = $sensor['mode'];
                        $active = $sensor['active'];
                        $timer = $sensor['timer'];
                        $offtimer = $sensor['offtimer'];
                        $offlevel = $sensor['offlevel'];
                        $this->SendDebug(__FUNCTION__, 'active=' . $this->bool2str($active) . ', timer=' . $timer . ', offtimer=' . $offtimer . ', offlevel=' . $offlevel, 0);
                        $this->SetValue('State', $active);
                        break;
                    default:
                        $this->SendDebug(__FUNCTION__, 'unsupported model ' . $model, 0);
                        break;
                }
            }
        }

        $this->SetStatus(IS_ACTIVE);
    }

    protected function ClearDailyValue()
    {
        $this->SendDebug(__FUNCTION__, '', 0);

        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');
        if ($with_daily_value) {
            $this->SetValue('DailyWaterUsage', 0);
        }
    }

    public function CollectZoneValues()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
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
}
