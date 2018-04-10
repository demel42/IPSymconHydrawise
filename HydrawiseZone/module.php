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

        $this->ConnectParent('{B1B47A68-CE20-4887-B00C-E6412DAD2CFB}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

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
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'controller_id', 'caption' => 'controller_id'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'relay_id', 'caption' => 'relay_id'];
        $formElements[] = ['type' => 'Select', 'name' => 'connector', 'caption' => 'connector', 'options' => $opts_connector];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_daily_value', 'caption' => ' ... daily sum'];

        $formStatus = [];
        $formStatus[] = ['code' => '101', 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => '102', 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => '104', 'icon' => 'inactive', 'caption' => 'Instance is inactive'];

        $formStatus[] = ['code' => '201', 'icon' => 'error', 'caption' => 'Instance is inactive (no data)'];
        $formStatus[] = ['code' => '202', 'icon' => 'error', 'caption' => 'Instance is inactive (controller missing)'];
        $formStatus[] = ['code' => '203', 'icon' => 'error', 'caption' => 'Instance is inactive (no controller)'];
        $formStatus[] = ['code' => '204', 'icon' => 'error', 'caption' => 'Instance is inactive (more then one controller)'];
        $formStatus[] = ['code' => '205', 'icon' => 'error', 'caption' => 'Instance is inactive (zone missing)'];

        return json_encode(['elements' => $formElements, 'status' => $formStatus]);
    }

    public function ReceiveData($data)
    {
        $jdata = json_decode($data);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);
        $buf = $jdata->Buffer;

        $controller_id = $this->ReadPropertyString('controller_id');
        $relay_id = $this->ReadPropertyString('relay_id');
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

        $vpos = 1;

        $now = time();

        $with_duration = isset($relay['run_seconds']);
        $running = isset($controller['running']) ? $controller['running'] : '';

        $lastwater = $relay['lastwater'];
        $lastrun = strtotime($lastwater);
        $this->MaintainVariable('LastRun', $this->Translate('Last run'), IPS_INTEGER, '~UnixTimestamp', $vpos++, $lastrun > 0);
        if ($lastrun) {
            $this->SetValue('LastRun', $lastrun);
        }

        $nicetime = $relay['nicetime'];
        $tm = date_create_from_format('D, j* F g:ia', $nicetime);
        $nextrun = $tm ? $tm->format('U') : 0;
        $this->MaintainVariable('NextRun', $this->Translate('Next run'), IPS_INTEGER, '~UnixTimestamp', $vpos++, $nextrun > 0);
        if ($nextrun) {
            $this->SetValue('NextRun', $nextrun);
        }

        $suspended = isset($relay['suspended']) ? $relay['suspended'] : 0;
        $is_suspended = $suspended > 0;
        $this->MaintainVariable('SuspendUntil', $this->Translate('Suspended until until end of'), IPS_INTEGER, '~UnixTimestampDate', $vpos++, $is_suspended);
        if ($is_suspended) {
            $this->SetValue('SuspendUntil', $suspended);
        }

        $with_duration = isset($relay['run_seconds']);
        $this->MaintainVariable('Duration', $this->Translate('Duration of run'), IPS_STRING, '', $vpos++, $with_duration);
        $this->MaintainVariable('Duration_seconds', $this->Translate('Duration of run'), IPS_INTEGER, 'Hydrawise.Duration', $vpos++, $with_duration);
        if ($with_duration) {
            $run_seconds = $relay['run_seconds'];
            $this->SetValue('Duration', $this->seconds2duration($run_seconds));
            $this->SetValue('Duration_seconds', $run_seconds);
        }

        $this->SendDebug(__FUNCTION__, "lastwater=$lastwater => $lastrun, nicetime=$nicetime => $nextrun, suspended=$suspended", 0);

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

        $this->MaintainVariable('TimeLeft', $this->Translate('Time left'), IPS_STRING, '', $vpos++, $is_running);
        $this->MaintainVariable('WaterUsage', $this->Translate('Water usage'), IPS_FLOAT, 'Hydrawise.Flowmeter', $vpos++, $is_running);

        $do_daily = $with_daily_value && !$is_suspended;
        $this->MaintainVariable('DailyDuration', $this->Translate('Duration of run (today)'), IPS_STRING, '', $vpos++, $do_daily);
        $this->MaintainVariable('DailyDuration_seconds', $this->Translate('Duration of run (today)'), IPS_INTEGER, 'Hydrawise.Duration', $vpos++, $do_daily);
        $this->MaintainVariable('DailyWaterUsage', $this->Translate('Water usage (today)'), IPS_FLOAT, 'Hydrawise.Flowmeter', $vpos++, $do_daily);

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

        $this->SetStatus(102);
    }

    public function ClearDailyValue()
    {
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');

        $this->SendDebug(__FUNCTION__, '', 0);

        if ($with_daily_value) {
            $this->SetValue('DailyDuration', '');
            $this->SetValue('DailyDuration_seconds', 0);
            $this->SetValue('DailyWaterUsage', 0);
        }
    }

    public function Run(int $duration = null)
    {
        $relay_id = $this->ReadPropertyString('relay_id');

        $url = '&relay_id=' + $relay_id . '&action=run';
        if ($duration > 0) {
            $url .= '&custom=' + $duration;
        }

        $this->SendDebug(__FUNCTION__, '', 0);
    }

    public function Stop()
    {
        $relay_id = $this->ReadPropertyString('relay_id');

        $url = '&relay_id=' + $relay_id . '&action=stop';

        $this->SendDebug(__FUNCTION__, '', 0);
    }

    public function Suspend(int $timestamp)
    {
        $relay_id = $this->ReadPropertyString('relay_id');
        $url = '&relay_id=' + $relay_id . '&action=suspend&custom=' + $timestamp;

        $this->SendDebug(__FUNCTION__, '', 0);
    }

    public function Resume()
    {
        $relay_id = $this->ReadPropertyString('relay_id');
        $url = '&relay_id=' + $relay_id . '&action=suspend&custom=' . time();

        $this->SendDebug(__FUNCTION__, '', 0);
    }

    private function do_HttpRequest($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $cdata = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->SendDebug(__FUNCTION__, "url=$url, httpcode=$httpcode", 0);

        $statuscode = 0;
        $err = '';
        $data = '';
        if ($httpcode != 200) {
            if ($httpcode == 400 || $httpcode == 401) {
                $statuscode = 201;
                $err = "got http-code $httpcode (unauthorized) from hydrawise";
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = 202;
                $err = "got http-code $httpcode (server error) from hydrawise";
            } else {
                $statuscode = 203;
                $err = "got http-code $httpcode from hydrawise";
            }
        } elseif ($cdata == '') {
            $statuscode = 204;
            $err = 'no data from hydrawise';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = 204;
                $err = 'malformed response from hydrawise';
            } else {
                // cdata={"message":"Resuming scheduled watering for zone Gef\u00e4\u00dfe (Beet)","message_type":"info"}, httpcode=200
                // cdata={"message":"Invalid operation requested. Please contact Hydrawise.","message_type":"error"}, httpcode=200
                // cdata={"error_msg":"unauthorised"}, httpcode=200
                $data = $cdata;
            }
        }

        if ($statuscode) {
            echo "statuscode=$statuscode, err=$err";
            $this->SendDebug(__FUNCTION__, $err, 0);
            $this->SetStatus($statuscode);
        }

        return $data;
    }

    protected function GetValue($Ident)
    {
        return GetValue($this->GetIDForIdent($Ident));
    }

    protected function SetValue($Ident, $Value)
    {
        if (IPS_GetKernelVersion() >= 5) {
            parent::SetValue($Ident, $Value);
        } else {
            if (SetValue($this->GetIDForIdent($Ident), $Value) == false) {
                echo "fehlerhafter Datentyp: $Ident=\"$Value\"";
            }
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
