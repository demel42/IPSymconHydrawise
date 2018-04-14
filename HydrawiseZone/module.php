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

class HydrawiseZone extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('controller_id', '');
        $this->RegisterPropertyString('relay_id', '');
        $this->RegisterPropertyInteger('connector', -1);
        $this->RegisterPropertyBoolean('with_daily_value', true);
        $this->RegisterPropertyInteger('visibility_script', 0);

        $associations = [];
        $associations[] = ['Wert' => -1, 'Name' => $this->Translate('Stop'), 'Farbe' => 0xEE0000];
        $associations[] = ['Wert' =>  0, 'Name' => $this->Translate('Default'), 'Farbe' => 0x32CD32];
        $associations[] = ['Wert' =>  1, 'Name' => $this->Translate('1 min'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  2, 'Name' => $this->Translate('2 min'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  5, 'Name' => $this->Translate('5 min'), 'Farbe' => -1];
        $associations[] = ['Wert' => 10, 'Name' => $this->Translate('10 min'), 'Farbe' => -1];
        $associations[] = ['Wert' => 15, 'Name' => $this->Translate('15 min'), 'Farbe' => -1];
        $associations[] = ['Wert' => 20, 'Name' => $this->Translate('20 min'), 'Farbe' => -1];
        $this->CreateVarProfile('Hydrawise.ZoneAction', IPS_INTEGER, '', 0, 0, 0, 1, '', $associations);

        $associations = [];
        $associations[] = ['Wert' =>  0, 'Name' => $this->Translate('Clear'), 'Farbe' => 0xEE0000];
        $associations[] = ['Wert' =>  1, 'Name' => $this->Translate('1 day'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  2, 'Name' => $this->Translate('2 days'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  7, 'Name' => $this->Translate('1 week'), 'Farbe' => -1];
        $this->CreateVarProfile('Hydrawise.ZoneSuspend', IPS_INTEGER, '', 0, 0, 0, 1, '', $associations);

        $this->ConnectParent('{B1B47A68-CE20-4887-B00C-E6412DAD2CFB}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $controller_id = $this->ReadPropertyString('controller_id');
        $relay_id = $this->ReadPropertyString('relay_id');
        $connector = $this->ReadPropertyInteger('connector');
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');

        $vpos = 1;
        $this->MaintainVariable('LastRun', $this->Translate('Last run'), IPS_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('NextRun', $this->Translate('Next run'), IPS_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('ZoneAction', $this->Translate('Zone operation'), IPS_INTEGER, 'Hydrawise.ZoneAction', $vpos++, true);
        $this->MaintainVariable('SuspendUntil', $this->Translate('Suspended until end of'), IPS_INTEGER, '~UnixTimestampDate', $vpos++, true);
        $this->MaintainVariable('SuspendAction', $this->Translate('Zone suspension'), IPS_INTEGER, 'Hydrawise.ZoneSuspend', $vpos++, true);
        $this->MaintainVariable('Duration', $this->Translate('Duration of run'), IPS_STRING, '', $vpos++, true);
        $this->MaintainVariable('Duration_seconds', $this->Translate('Duration of run'), IPS_INTEGER, 'Hydrawise.Duration', $vpos++, true);
        $this->MaintainVariable('TimeLeft', $this->Translate('Time left'), IPS_STRING, '', $vpos++, true);
        $this->MaintainVariable('WaterUsage', $this->Translate('Water usage'), IPS_FLOAT, 'Hydrawise.Flowmeter', $vpos++, true);
        $this->MaintainVariable('DailyDuration', $this->Translate('Duration of run (today)'), IPS_STRING, '', $vpos++, $with_daily_value);
        $this->MaintainVariable('DailyDuration_seconds', $this->Translate('Duration of run (today)'), IPS_INTEGER, 'Hydrawise.Duration', $vpos++, $with_daily_value);
        $this->MaintainVariable('DailyWaterUsage', $this->Translate('Water usage (today)'), IPS_FLOAT, 'Hydrawise.Flowmeter', $vpos++, $with_daily_value);

        $this->MaintainAction('ZoneAction', true);
        $this->MaintainAction('SuspendUntil', true);
        $this->MaintainAction('SuspendAction', true);

        if ($connector < 100) {
            $info = 'Zone ' . $connector;
        } else {
            $info = 'Expander ' . floor($connector / 100) . ' Zone ' . ($connector % 100);
        }
        $info .= ' (#' . $relay_id . ')';
        $this->SetSummary($info);

        $this->SetStatus(102);
    }

    public function GetConfigurationForm()
    {
        $opts_connector = [];
        $opts_connector[] = ['label' => $this->Translate('no'), 'value' => 0];
        for ($u = 0; $u <= 2; $u++) {
            for ($z = 1; $z <= 16; $z++) {
                $n = $u * 100 + $z;
                $l = $u ? $this->Translate('Expander') . ' ' . $u . ' ' : '';
                $l .= $this->Translate('Zone') . ' ' . $z;
                $opts_connector[] = ['label' => $l, 'value' => $n];
            }
        }

        $formElements = [];
        $formElements[] = ['type' => 'Label', 'label' => 'Hydrawise Zone'];
        $formElements[] = ['type' => 'Select', 'name' => 'connector', 'caption' => 'connector', 'options' => $opts_connector];
        $formElements[] = ['type' => 'Label', 'label' => 'optional zone data'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_daily_value', 'caption' => ' ... daily sum'];
        $formElements[] = ['type' => 'Label', 'label' => 'optional script to hide/show variables'];
        $formElements[] = ['type' => 'SelectScript', 'name' => 'visibility_script', 'caption' => 'visibility'];

        $formStatus = [];
        $formStatus[] = ['code' => '101', 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => '102', 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => '104', 'icon' => 'inactive', 'caption' => 'Instance is inactive'];

        $formStatus[] = ['code' => '201', 'icon' => 'error', 'caption' => 'Instance is inactive (no data)'];
        $formStatus[] = ['code' => '202', 'icon' => 'error', 'caption' => 'Instance is inactive (controller missing)'];
        $formStatus[] = ['code' => '205', 'icon' => 'error', 'caption' => 'Instance is inactive (zone missing)'];

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
        $relay_id = $this->ReadPropertyString('relay_id');
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');
        $visibility_script = $this->ReadPropertyInteger('visibility_script');

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

        $relays = $controller['relays'];
        $relay_found = false;
        foreach ($relays as $relay) {
            if ($relay_id == $relay['relay_id']) {
                $relay_found = true;
                break;
            }
        }
        if ($relay_found == false) {
            $err = "relay_id \"$relay_id\" not found";
            $statuscode = 205;
            $do_abort = true;
        }

        if ($do_abort) {
            echo "statuscode=$statuscode, err=$err";
            $this->SendDebug(__FUNCTION__, $err, 0);
            $this->SetStatus($statuscode);
            return -1;
        }

        $now = time();

        $running = isset($controller['running']) ? $controller['running'] : '';

        $lastwater = $relay['lastwater'];
        $lastrun = strtotime($lastwater);
        $this->SetValue('LastRun', $lastrun);

        $nicetime = $relay['nicetime'];
        $tm = date_create_from_format('D, j* F g:ia', $nicetime);
        $nextrun = $tm ? $tm->format('U') : 0;
        $this->SetValue('NextRun', $nextrun);

        $is_running = false;
        $time_left = 0;
        $water_int = 0;
        if ($running != '') {
            foreach ($running as $run) {
                if ($relay_id != $run['relay_id']) {
                    continue;
                }
                $time_left = $run['time_left'];
                $water_usage = $run['water_int'];
                $is_running = true;
                $this->SendDebug(__FUNCTION__, "time_left=$time_left, water_int=$water_int", 0);
            }
        }

        $this->SetValue('ZoneAction', $is_running ? -1 : 0);

        $suspended = isset($relay['suspended']) ? $relay['suspended'] : 0;
        $is_suspended = $suspended > 0;
        $this->SetValue('SuspendUntil', $suspended);

        $this->SetValue('SuspendAction', $is_suspended ? -1 : 1);

        $run_seconds = isset($relay['run_seconds']) ? $relay['run_seconds'] : 0;
        $this->SetValue('Duration', $this->seconds2duration($run_seconds));
        $this->SetValue('Duration_seconds', $run_seconds);

        $this->SendDebug(__FUNCTION__, "lastwater=$lastwater => $lastrun, nicetime=$nicetime => $nextrun, suspended=$suspended", 0);

        if ($is_running) {
            $this->SetValue('TimeLeft', $this->seconds2duration($time_left));
            $this->SetValue('WaterUsage', $water_usage);

            $time_begin = $lastrun;
            $time_end = $now + $time_left;

            $current_run = [
                    'time_begin'    => $time_begin,
                    'time_end'      => $time_end,
                    'time_left'     => $time_left,
                    'water_usage'   => $water_usage
                ];
            $this->SetBuffer('currentRun', json_encode($current_run));
            $this->SendDebug(__FUNCTION__, 'save: begin=' . date('d.m.Y H:i', $time_begin) . ', end=' . date('d.m.Y H:i', $time_end) . ', left=' . $time_left . ', water_usage=' . $water_usage, 0);
        } else {
            $this->SetValue('TimeLeft', '');
            $this->SetValue('WaterUsage', 0);

            $buf = $this->GetBuffer('currentRun');
            if ($buf != '') {
                $current_run = json_decode($buf, true);

                $time_begin = $current_run['time_begin'];
                $time_end = $current_run['time_end'];
                $time_left = $current_run['time_left'];
                $water_usage = $current_run['water_usage'];

                $time_duration = $time_end - $time_begin;
                $time_done = $time_end - $time_begin - $time_left;

                $water_estimated = $water_usage / $time_done * $time_duration;

                $this->SendDebug(__FUNCTION__, 'restore: begin=' . date('d.m.Y H:i', $time_begin) . ', end=' . date('d.m.Y H:i', $time_end) . ', left=' . $time_left . ', water_usage=' . $water_usage, 0);
                $this->SendDebug(__FUNCTION__, 'duration=' . $time_duration . ', done=' . $time_done . ' => water_estimated=' . $water_estimated, 0);

                if ($do_daily) {
                    $duration = $this->GetValue('DailyDuration_seconds') + $time_duration;
                    $this->SetValue('DailyDuration', $this->seconds2duration($duration));
                    $this->SetValue('DailyDuration_seconds', $duration);

                    $water_usage = $this->GetValue('DailyWaterUsage') + $water_estimated;
                    $this->SetValue('DailyWaterUsage', $water_usage);
                }
                $this->SetBuffer('currentRun', '');
            }
        }

        if ($visibility_script > 0) {
            $opts = [
                    'InstanceID'       => $this->InstanceID,
                    'suspended_until'  => $suspended,
                    'duration'         => $run_seconds,
                    'time_left'        => $time_left,
                ];
            $ret = IPS_RunScriptWaitEx($visibility_script, $opts);
            $this->SendDebug(__FUNCTION__, 'visibility_script=' . $visibility_script . ', InstanceID=' . $this->InstanceID . ' => ' . $ret, 0);
        }

        $this->SetStatus(102);
    }

    protected function ClearDailyValue()
    {
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');

        $this->SendDebug(__FUNCTION__, '', 0);

        if ($with_daily_value) {
            $this->SetValue('DailyDuration', '');
            $this->SetValue('DailyDuration_seconds', 0);
            $this->SetValue('DailyWaterUsage', 0);
        }
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'SuspendUntil':
                $dt = date('d.m.Y H:i:s', $Value);
                $this->SendDebug(__FUNCTION__, "$Ident=$Value => $dt", 0);
                $this->Suspend($Value);
                break;
            case 'SuspendAction':
                $this->SendDebug(__FUNCTION__, "$Ident=$Value", 0);
                if ($Value == 0) {
                    $this->Resume($Value);
                } else {
                    $sec = $Value * 86400;
                    $dt = new DateTime(date('d.m.Y 23:59:59', time() + $sec));
                    $ts = $dt->format('U');
                    $dt = date('d.m.Y H:i:s', $ts);
                    $this->SendDebug(__FUNCTION__, "$Ident=$Value => $dt", 0);
                    $this->Suspend($ts);
                }
                break;
            case 'ZoneAction':
                if ($Value == -1) {
                    $this->Stop();
                } elseif ($Value == 0) {
                    $this->Run();
                } else {
                    $sec = $Value * 60;
                    $this->Run($sec);
                }
                $this->SendDebug(__FUNCTION__, "$Ident=$Value", 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__, "invalid ident $Ident", 0);
                break;
        }
    }

    public function Run(int $duration = null)
    {
        $relay_id = $this->ReadPropertyString('relay_id');

        $url = 'relay_id=' . $relay_id . '&action=run';
        if ($duration > 0) {
            $url .= '&custom=' . $duration;
        }

        $SendData = ['DataID' => '{5361495C-0EF7-4319-8D2C-BEFA5BCC7F25}', 'Function' => 'CmdUrl', 'Url' => $url];
        $data = $this->SendDataToParent(json_encode($SendData));

        $this->SendDebug(__FUNCTION__, 'url=' . $url . ', got data=' . print_r($data, true), 0);
    }

    public function Stop()
    {
        $relay_id = $this->ReadPropertyString('relay_id');

        $url = 'relay_id=' . $relay_id . '&action=stop';

        $SendData = ['DataID' => '{5361495C-0EF7-4319-8D2C-BEFA5BCC7F25}', 'Function' => 'CmdUrl', 'Url' => $url];
        $data = $this->SendDataToParent(json_encode($SendData));

        $this->SendDebug(__FUNCTION__, 'url=' . $url . ', got data=' . print_r($data, true), 0);
    }

    public function Suspend(int $timestamp)
    {
        $relay_id = $this->ReadPropertyString('relay_id');
        $url = 'relay_id=' . $relay_id . '&action=suspend&custom=' . $timestamp;

        $SendData = ['DataID' => '{5361495C-0EF7-4319-8D2C-BEFA5BCC7F25}', 'Function' => 'CmdUrl', 'Url' => $url];
        $data = $this->SendDataToParent(json_encode($SendData));

        $this->SendDebug(__FUNCTION__, 'url=' . $url . ', got data=' . print_r($data, true), 0);
    }

    public function Resume()
    {
        $relay_id = $this->ReadPropertyString('relay_id');
        $url = 'relay_id=' . $relay_id . '&action=suspend&custom=' . time();

        $SendData = ['DataID' => '{5361495C-0EF7-4319-8D2C-BEFA5BCC7F25}', 'Function' => 'CmdUrl', 'Url' => $url];
        $data = $this->SendDataToParent(json_encode($SendData));

        $this->SendDebug(__FUNCTION__, 'url=' . $url . ', got data=' . print_r($data, true), 0);
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

    // Sekunden in Menschen-lesbares Format umwandeln
    private function seconds2duration(int $sec)
    {
        $duration = '';
        if ($sec > 3600) {
            $duration .= sprintf('%dh', floor($sec / 3600));
            $sec = $sec % 3600;
        }
        if ($sec > 60) {
            $duration .= sprintf('%dm', floor($sec / 60));
            $sec = $sec % 60;
        }
        if ($sec > 0) {
            $duration .= sprintf('%ds', $sec);
            $sec = floor($sec);
        }

        return $duration;
    }
}
