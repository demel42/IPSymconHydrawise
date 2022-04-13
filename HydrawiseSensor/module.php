<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class HydrawiseSensor extends IPSModule
{
    use Hydrawise\StubsCommonLib;
    use HydrawiseLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('controller_id', '');
        $this->RegisterPropertyInteger('connector', -1);
        $this->RegisterPropertyInteger('model', 0);
        $this->RegisterPropertyBoolean('with_daily_value', true);
        $this->RegisterPropertyBoolean('with_flowrate', true);

        $this->InstallVarProfiles(false);

        $this->ConnectParent('{5927E05C-82D0-4D78-B8E0-A973470A9CD3}');
    }

    private function CheckConfiguration()
    {
        $s = '';
        $r = [];

        $model = $this->ReadPropertyInteger('model');
        switch ($model) {
            case self::$SENSOR_FLOW_METER:
            case self::$SENSOR_NORMALLY_CLOSE_START:
            case self::$SENSOR_NORMALLY_OPEN_STOP:
            case self::$SENSOR_NORMALLY_CLOSE_STOP:
            case self::$SENSOR_NORMALLY_OPEN_START:
                break;
            default:
                $this->SendDebug(__FUNCTION__, '"mode" is unsupported', 0);
                $r[] = $this->Translate('Model is not supported');
                break;
        }

        if ($r != []) {
            $s = $this->Translate('The following points of the configuration are incorrect') . ':' . PHP_EOL;
            foreach ($r as $p) {
                $s .= '- ' . $p . PHP_EOL;
            }
        }

        return $s;
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
            case self::$SENSOR_FLOW_METER:
                $this->MaintainVariable('WaterFlowrate', $this->Translate('Water flow rate (current)'), VARIABLETYPE_FLOAT, 'Hydrawise.WaterFlowrate', $vpos++, $with_flowrate);
                $this->MaintainVariable('DailyWaterUsage', $this->Translate('Water usage (today)'), VARIABLETYPE_FLOAT, 'Hydrawise.Flowmeter', $vpos++, $with_daily_value);
                break;
            case self::$SENSOR_NORMALLY_CLOSE_START:
            case self::$SENSOR_NORMALLY_OPEN_STOP:
            case self::$SENSOR_NORMALLY_CLOSE_STOP:
            case self::$SENSOR_NORMALLY_OPEN_START:
                $this->MaintainVariable('State', $this->Translate('State'), VARIABLETYPE_BOOLEAN, 'Hydrawise.RainSensor', $vpos++, true);
                break;
            default:
                break;
        }

        if ($this->CheckConfiguration() != false) {
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        switch ($model) {
            case self::$SENSOR_FLOW_METER:
                $mode_txt = 'flow meter';
                break;
            case self::$SENSOR_NORMALLY_CLOSE_START:
                $mode_txt = 'normally close, start zone';
                break;
            case self::$SENSOR_NORMALLY_OPEN_STOP:
                $mode_txt = 'normally open, stop zone';
                break;
            case self::$SENSOR_NORMALLY_CLOSE_STOP:
                $mode_txt = 'normally close, stop zone';
                break;
            case self::$SENSOR_NORMALLY_OPEN_START:
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

    private function GetFormElements()
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
                'value'   => self::$SENSOR_NORMALLY_CLOSE_START
            ],
            [
                'caption' => 'normally open, action stop',
                'value'   => self::$SENSOR_NORMALLY_OPEN_STOP
            ],
            [
                'caption' => 'normally close, action stop',
                'value'   => self::$SENSOR_NORMALLY_CLOSE_STOP
            ],
            [
                'caption' => 'normally open, action start',
                'value'   => self::$SENSOR_NORMALLY_CLOSE_START
            ],
            [
                'caption' => 'flow meter',
                'value'   => self::$SENSOR_FLOW_METER
            ],
        ];

        $formElements = [];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Hydrawise Sensor'
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
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'controller_id',
                    'caption' => 'Controller-ID',
                    'enabled' => false
                ],
                [
                    'type'    => 'Select',
                    'name'    => 'connector',
                    'caption' => 'connector',
                    'options' => $opts_connector,
                    'enabled' => false
                ],
                [
                    'type'    => 'Select',
                    'name'    => 'model',
                    'caption' => 'model',
                    'options' => $opts_model,
                    'enabled' => false
                ],
            ],
            'caption' => 'Basic configuration (don\'t change)'
        ];

        if ($model == self::$SENSOR_FLOW_METER) {
            $formElements[] = [
                'type'    => 'ExpansionPanel',
                'items'   => [
                    [
                        'type'    => 'CheckBox',
                        'name'    => 'with_daily_value',
                        'caption' => 'daily sum'
                    ],
                    [
                        'type'    => 'CheckBox',
                        'name'    => 'with_flowrate',
                        'caption' => 'flowrate'
                    ],
                ],
                'caption' => 'optional sensor data'
            ];
        }

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

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
                    case self::$SENSOR_FLOW_METER:
                        $water_flowrate = 0;
                        $daily_waterusage = 0;
                        if (isset($sensor['relays'])) {
                            $relays = $sensor['relays'];

                            $ret = $this->CollectZoneValues();
                            $responses = $ret == false ? [] : json_decode($ret, true);

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
                    case self::$SENSOR_NORMALLY_CLOSE_START:
                    case self::$SENSOR_NORMALLY_OPEN_STOP:
                    case self::$SENSOR_NORMALLY_CLOSE_STOP:
                    case self::$SENSOR_NORMALLY_OPEN_START:
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
}
