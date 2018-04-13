<?php

// Constants will be defined with IP-Symcon 5.0 and newer
if (!defined('IPS_KERNELMESSAGE')) {
    define('IPS_KERNELMESSAGE', 10100);
}
if (!defined('KR_READY')) {
    define('KR_READY', 10103);
}

if (!defined('IPS_BOOLEAN')) {
    define('IPS_BOOLEAN', 0);
}
if (!defined('IPS_INTEGER')) {
    define('IPS_INTEGER', 1);
}
if (!defined('IPS_FLOAT')) {
    define('IPS_FLOAT', 2);
}
if (!defined('IPS_STRING')) {
    define('IPS_STRING', 3);
}

// Model of Sensor
if (!defined('SENSOR_NORMALLY_CLOSE_START')) {
    define('SENSOR_NORMALLY_CLOSE_START', 11);
}
if (!defined('SENSOR_NORMALLY_OPEN_STOP')) {
    define('SENSOR_NORMALLY_OPEN_STOP', 12);
}
if (!defined('SENSOR_NORMALLY_CLOSE_STOP')) {
    define('SENSOR_NORMALLY_CLOSE_STOP', 13);
}
if (!defined('SENSOR_NORMALLY_OPEN_START')) {
    define('SENSOR_NORMALLY_OPEN_START', 14);
}
if (!defined('SENSOR_FLOW_METER')) {
    define('SENSOR_FLOW_METER', 30);
}

class HydrawiseSensor extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('controller_id', '');
        $this->RegisterPropertyInteger('connector', -1);
        $this->RegisterPropertyInteger('model', 0);
        $this->RegisterPropertyBoolean('with_daily_value', true);

        $this->CreateVarProfile('Hydrawise.Flowmeter', IPS_FLOAT, ' l', 0, 0, 0, 0, 'Gauge');

        $this->ConnectParent('{B1B47A68-CE20-4887-B00C-E6412DAD2CFB}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $controller_id = $this->ReadPropertyString('controller_id');
        $connector = $this->ReadPropertyInteger('connector');
        $model = $this->ReadPropertyInteger('model');
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');

        $vpos = 1;

        switch ($model) {
            case SENSOR_FLOW_METER:
                $this->MaintainVariable('DailyFlow', $this->Translate('Water usage (day)'), IPS_FLOAT, 'Hydrawise.Flowmeter', $vpos++, $with_daily_value);
                $this->MaintainVariable('Flow', $this->Translate('Water usage (week)'), IPS_FLOAT, 'Hydrawise.Flowmeter', $vpos++, true);
                $mode_txt = 'flow meter';
                break;
            case SENSOR_NORMALLY_CLOSE_START:
                $mode_txt = 'normally close, action start';
                break;
            case SENSOR_NORMALLY_OPEN_STOP:
                $mode_txt = 'normally open, action stop';
                break;
            case SENSOR_NORMALLY_CLOSE_STOP:
                $mode_txt = 'normally close, action stop';
                break;
            case SENSOR_NORMALLY_OPEN_START:
                $mode_txt = 'normally open, action start';
                break;
            default:
                $mode_txt = 'unsupported';
                break;
        }

        $info = 'Sensor ' . $connector . ' (' . $mode_txt . ')';
        $this->SetSummary($info);

        $this->SetStatus(102);
    }

    public function GetConfigurationForm()
    {
        $opts_connector = [];
        $opts_connector[] = ['label' => $this->Translate('no'), 'value' => 0];
        for ($s = 1; $s <= 2; $s++) {
            $l = $this->Translate('Sensor') . ' ' . $s;
            $opts_connector[] = ['label' => $l, 'value' => $s];
        }

        $opts_model = [];
        $opts_model[] = ['label' => $this->Translate('no'), 'value' => 0];
        $opts_model[] = ['label' => $this->Translate('normally close, action start'), 'value' => SENSOR_NORMALLY_CLOSE_START];
        $opts_model[] = ['label' => $this->Translate('normally open, action stop'), 'value' => SENSOR_NORMALLY_OPEN_STOP];
        $opts_model[] = ['label' => $this->Translate('normally close, action stop'), 'value' => SENSOR_NORMALLY_CLOSE_STOP];
        $opts_model[] = ['label' => $this->Translate('normally open, action start'), 'value' => SENSOR_NORMALLY_CLOSE_START];
        $opts_model[] = ['label' => $this->Translate('flow meter'), 'value' => SENSOR_FLOW_METER];

        $formElements = [];
        $formElements[] = ['type' => 'Label', 'label' => 'Hydrawise Sensor'];
        $formElements[] = ['type' => 'Select', 'name' => 'connector', 'caption' => 'connector', 'options' => $opts_connector];
        $formElements[] = ['type' => 'Select', 'name' => 'model', 'caption' => 'model', 'options' => $opts_model];
        $formElements[] = ['type' => 'Label', 'label' => 'optional sensor data'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_daily_value', 'caption' => ' ... daily sum'];

        $formStatus = [];
        $formStatus[] = ['code' => '101', 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => '102', 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => '104', 'icon' => 'inactive', 'caption' => 'Instance is inactive'];

        $formStatus[] = ['code' => '201', 'icon' => 'error', 'caption' => 'Instance is inactive (no data)'];
        $formStatus[] = ['code' => '202', 'icon' => 'error', 'caption' => 'Instance is inactive (controller missing)'];

        return json_encode(['elements' => $formElements, 'status' => $formStatus]);
    }

    public function ReceiveData($data)
    {
        $controller_id = $this->ReadPropertyString('controller_id');

        $jdata = json_decode($data);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        if (isset($jdata->Buffer)) {
            $this->DecodeData($jdata->Buffer);
        } elseif (isset($jdata->Function)) {
            if (isset($jdata->controller_id) && $jdata->controller_id != $controller_id) {
                $this->SendDebug(__FUNCTION__, 'ignore foreign controller_id ' . $jdata->controller_id, 0);
            } else {
                switch ($jdata->Function) {
                    case 'ClearDailyValue':
                        $this->ClearDailyValue();
                        break;
                    default:
                        $this->SendDebug(__FUNCTION__, 'unknown function "' . $jdata->Function . '"', 0);
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

        $err = '';
        $statuscode = 0;
        $do_abort = false;

        if ($buf != '') {
            $controllers = json_decode($buf, true);

            $controller_found = false;
            foreach ($controllers as $controller) {
                if ($controller_id == $controller['controller_id']) {
                    $controller_found = true;
                    break;
                }
            }
            if ($controller_found == false) {
                $err = "controller_id \"$controller_id\" not found";
                $statuscode = 202;
                $do_abort = true;
            }
        } else {
            $err = 'no data';
            $statuscode = 201;
            $do_abort = true;
        }

        if ($do_abort) {
            echo "statuscode=$statuscode, err=$err";
            $this->SendDebug(__FUNCTION__, $err, 0);
            $this->SetStatus($statuscode);
            return -1;
        }

        $now = time();

        $vpos = 1;

        $flow = 0;
        $sensors = $controller['sensors'];
        if (count($sensors) > 0) {
            foreach ($sensors as $sensor) {
                if ($connector != ($sensor['input'] + 1)) {
                    continue;
                }
                switch ($model) {
                    case SENSOR_FLOW_METER:
                        if (isset($sensor['flow']['week'])) {
                            $flow = preg_replace('/^([0-9\.,]*).*$/', '$1', $sensor['flow']['week']);
                            $this->SetValue('Flow', $flow);
                            if ($with_daily_value) {
                                $old_flow = $this->GetBuffer('Flow');
                                $this->SendDebug(__FUNCTION__, 'flow=' . $flow . ', old_flow=' . $old_flow, 0);
                                if ($old_flow != '' && $old_flow < $flow) {
                                    $new_flow = $this->GetValue('DailyFlow') + ($flow - $old_flow);
                                    $this->SendDebug(__FUNCTION__, 'new_flow=' . $new_flow, 0);
                                    $this->SetValue('DailyFlow', $new_flow);
                                }
                                $this->SetBuffer('Flow', $flow);
                            }
                        }
                        break;
                    default:
                        $this->SendDebug(__FUNCTION_, 'unsupported model ' . $model, 0);
                        break;
                }
            }
        }

        $this->SetStatus(102);
    }

    protected function ClearDailyValue()
    {
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');

        $this->SendDebug(__FUNCTION__, '', 0);

        if ($with_daily_value) {
            $this->SetValue('DailyFlow', 0);
        }
    }

    protected function GetValue($Ident)
    {
        return GetValue($this->GetIDForIdent($Ident));
    }

    protected function SetValue($Ident, $Value)
    {
        @$varID = $this->GetIDForIdent($Ident);
        if ($varID == false) {
            $this->SendDebug(__FUNCTION__, 'missing variable ' . $Ident, 0);
            return;
        }

        if (IPS_GetKernelVersion() >= 5) {
            $ret = parent::SetValue($Ident, $Value);
        } else {
            $ret = SetValue($varID, $Value);
        }
        if ($ret == false) {
            $this->SendDebug(__FUNCTION__, 'mismatch of value "' . $Value . '" for variable ' . $Ident, 0);
        }
    }

    // Variablenprofile erstellen
    private function CreateVarProfile($Name, $ProfileType, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Icon, $Asscociations = '')
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, $ProfileType);
            IPS_SetVariableProfileText($Name, '', $Suffix);
            IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
            IPS_SetVariableProfileDigits($Name, $Digits);
            IPS_SetVariableProfileIcon($Name, $Icon);
            if ($Asscociations != '') {
                foreach ($Asscociations as $a) {
                    $w = isset($a['Wert']) ? $a['Wert'] : '';
                    $n = isset($a['Name']) ? $a['Name'] : '';
                    $i = isset($a['Icon']) ? $a['Icon'] : '';
                    $f = isset($a['Farbe']) ? $a['Farbe'] : 0;
                    IPS_SetVariableProfileAssociation($Name, $w, $n, $i, $f);
                }
            }
        }
    }
}
