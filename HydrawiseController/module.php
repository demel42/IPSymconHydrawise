<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/library.php';  // modul-bezogene Funktionen

class HydrawiseController extends IPSModule
{
    use HydrawiseCommon;
    use HydrawiseLibrary;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('controller_id', '');

        $this->RegisterPropertyInteger('minutes2fail', 60);

        $this->RegisterPropertyInteger('statusbox_script', 0);
        $this->RegisterPropertyInteger('webhook_script', 0);

        $this->RegisterPropertyBoolean('with_last_contact', true);
        $this->RegisterPropertyBoolean('with_last_message', true);
        $this->RegisterPropertyBoolean('with_info', false);
        $this->RegisterPropertyBoolean('with_observations', true);
        $this->RegisterPropertyInteger('num_forecast', 0);
        $this->RegisterPropertyBoolean('with_status_box', false);
        $this->RegisterPropertyBoolean('with_daily_value', true);

        $this->CreateVarProfile('Hydrawise.Temperatur', VARIABLETYPE_FLOAT, ' °C', -10, 30, 0, 1, 'Temperature');
        $this->CreateVarProfile('Hydrawise.WaterSaving', VARIABLETYPE_INTEGER, ' %', 0, 0, 0, 0, 'Drops');
        $this->CreateVarProfile('Hydrawise.Rainfall', VARIABLETYPE_FLOAT, ' mm', 0, 60, 0, 1, 'Rainfall');
        $this->CreateVarProfile('Hydrawise.ProbabilityOfRain', VARIABLETYPE_INTEGER, ' %', 0, 0, 0, 0, 'Rainfall');
        $this->CreateVarProfile('Hydrawise.WindSpeed', VARIABLETYPE_FLOAT, ' km/h', 0, 100, 0, 0, 'WindSpeed');
        $this->CreateVarProfile('Hydrawise.Humidity', VARIABLETYPE_FLOAT, ' %', 0, 100, 0, 0, 'Drops');
        $this->CreateVarProfile('Hydrawise.Duration', VARIABLETYPE_INTEGER, ' min', 0, 0, 0, 0, 'Hourglass');

        $this->ConnectParent('{5927E05C-82D0-4D78-B8E0-A973470A9CD3}');

        // Inspired by module SymconTest/HookServe
        // We need to call the RegisterHook function on Kernel READY
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    // Inspired by module SymconTest/HookServe
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->RegisterHook('/hook/Hydrawise');
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $controller_id = $this->ReadPropertyString('controller_id');
        $with_last_contact = $this->ReadPropertyBoolean('with_last_contact');
        $with_last_message = $this->ReadPropertyBoolean('with_last_message');
        $with_info = $this->ReadPropertyBoolean('with_info');
        $with_observations = $this->ReadPropertyBoolean('with_observations');
        $num_forecast = $this->ReadPropertyInteger('num_forecast');
        $with_status_box = $this->ReadPropertyBoolean('with_status_box');
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');

        $vpos = 1;

        $this->MaintainVariable('Status', $this->Translate('State'), VARIABLETYPE_BOOLEAN, '~Alert.Reversed', $vpos++, true);
        $this->MaintainVariable('LastContact', $this->Translate('last contact'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, $with_last_contact);
        $this->MaintainVariable('LastMessage', $this->Translate('Message to last contact'), VARIABLETYPE_STRING, '', $vpos++, $with_last_message);
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

        // Inspired by module SymconTest/HookServe
        // Only call this in READY state. On startup the WebHook instance might not be available yet
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->RegisterHook('/hook/Hydrawise');
        }

        $info = 'Controller (#' . $controller_id . ')';
        $this->SetSummary($info);

        $this->SetStatus(102);
    }

    public function GetConfigurationForm()
    {
        $opts_forecast = [];
        $opts_forecast[] = ['label' => $this->Translate('no'), 'value' => 0];
        $opts_forecast[] = ['label' => $this->Translate('today'), 'value' => 1];
        $opts_forecast[] = ['label' => $this->Translate('tomorrow'), 'value' => 2];
        $opts_forecast[] = ['label' => $this->Translate('overmorrow'), 'value' => 3];

        $formElements = [];
        $formElements[] = ['type' => 'Label', 'label' => 'Hydrawise Controller'];
        $formElements[] = ['type' => 'Label', 'label' => 'optional controller data'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_last_contact', 'caption' => ' ... last contact to Hydrawise'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_last_message', 'caption' => ' ... last message'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_info', 'caption' => ' ... info'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_observations', 'caption' => ' ... observations'];
        $formElements[] = ['type' => 'Select', 'name' => 'num_forecast', 'caption' => ' ... forecast', 'options' => $opts_forecast];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_status_box', 'caption' => ' ... html-box with state of irrigation'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_daily_value', 'caption' => ' ... daily sum'];
        $formElements[] = ['type' => 'Label', 'label' => 'alternate script to use for ...'];
        $formElements[] = ['type' => 'SelectScript', 'name' => 'statusbox_script', 'caption' => ' ... "StatusBox"'];
        $formElements[] = ['type' => 'SelectScript', 'name' => 'webhook_script', 'caption' => ' ... Webhook'];
        $formElements[] = ['type' => 'Label', 'label' => 'Duration until the connection to hydrawise is marked disturbed'];
        $formElements[] = ['type' => 'IntervalBox', 'name' => 'minutes2fail', 'caption' => 'Minutes'];

        $formActions = [];
        $formActions[] = ['type' => 'Label', 'label' => '____________________________________________________________________________________________________'];
        $formActions[] = [
                            'type'    => 'Button',
                            'caption' => 'Module description',
                            'onClick' => 'echo "https://github.com/demel42/IPSymconHydrawise/blob/master/README.md";'
                        ];

        $formStatus = [];
        $formStatus[] = ['code' => '101', 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => '102', 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => '104', 'icon' => 'inactive', 'caption' => 'Instance is inactive'];

        $formStatus[] = ['code' => '201', 'icon' => 'error', 'caption' => 'Instance is inactive (no data)'];
        $formStatus[] = ['code' => '202', 'icon' => 'error', 'caption' => 'Instance is inactive (controller missing)'];

        return json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
    }

    public function ReceiveData($data)
    {
        $jdata = json_decode($data);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);
        if (isset($jdata->Buffer)) {
            $this->DecodeData($jdata->Buffer);
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
        $with_info = $this->ReadPropertyBoolean('with_info');
        $with_observations = $this->ReadPropertyBoolean('with_observations');
        $num_forecast = $this->ReadPropertyInteger('num_forecast');
        $with_status_box = $this->ReadPropertyBoolean('with_status_box');
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
            $this->LogMessage('statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, $err, 0);
            $this->SetStatus($statuscode);

            $this->SetValue('Status', false);
            return -1;
        }

        $now = time();

        if ($with_daily_value) {
            $dt = new DateTime(date('d.m.Y 00:00:00', $now));
            $ts_today = $dt->format('U');
            $ts_watch = $this->GetValue('DailyReference');
            if ($ts_today != $ts_watch) {
                $this->SetValue('DailyReference', $ts_today);
                $this->ClearDailyValue();
            }
        }

        $controller_status = true;
        $status = $controller['status'];
        if ($status != 'All good!') {
            $controller_status = false;
        }

        $controller_name = $controller['name'];

        $last_contact = $controller['last_contact'];
        $last_contact_ts = strtotime($last_contact);

        $message = $controller['message'];

        $msg = "controller \"$controller_name\": last_contact=$last_contact";
        $this->SendDebug(__FUNCTION__, utf8_decode($msg), 0);

        if ($with_last_contact) {
            $this->SetValue('LastContact', $last_contact_ts);
        }

        if ($with_last_message) {
            $this->SetValue('LastMessage', $message);
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
                    $_forcecast = $forecast[$i];

                    $temp_hi = preg_replace('/^([0-9\.,]*).*$/', '$1', $_forcecast['temp_hi']);
                    $this->SetValue('Forecast' . $i . 'TempMax', $temp_hi);

                    $temp_lo = preg_replace('/^([0-9\.,]*).*$/', '$1', $_forcecast['temp_lo']);
                    $this->SetValue('Forecast' . $i . 'TempMin', $temp_lo);

                    $humidity = preg_replace('/^([0-9\.,]*).*$/', '$1', $_forcecast['humidity']);
                    $this->SetValue('Forecast' . $i . 'WindSpeed', $humidity);

                    $wind = preg_replace('/^([0-9\.,]*).*$/', '$1', $_forcecast['wind']);
                    $this->SetValue('Forecast' . $i . 'Humidity', $wind);

                    $pop = preg_replace('/^([0-9\.,]*).*$/', '$1', $_forcecast['pop']);
                    $this->SetValue('Forecast' . $i . 'ProbabilityOfRain', $pop);

                    $conditions = $_forcecast['conditions'];
                    $this->SetValue('Forecast' . $i . 'Conditions', $conditions);

                    if ($n++ == 3) {
                        break;
                    }
                }
            }
        }

        // Namen der Zonen/Ventile (relay) merken
        $relay2name = [];
        $relays = $controller['relays'];
        if (count($relays) > 0) {
            foreach ($relays as $relay) {
                $relay2name[$relay['relay_id']] = $relay['name'];
            }
        }

        // Status der Zonen (relays)
        $running_zones = [];
        $today_zones = [];
        $done_zones = [];
        $future_zones = [];

        // aktuell durchgeführte Bewässerung
        $relay2running = [];
        if (isset($controller['running'])) {
            $running = $controller['running'];
            foreach ($running as $_running) {
                $relay_id = $_running['relay_id'];
                $relay2running[$relay_id] = true;

                $name = $relay2name[$relay_id];
                $time_left = $_running['time_left'];
                $duration = $this->seconds2duration($time_left);

                $running_zone = [
                        'name'     => $name,
                        'duration' => $duration
                    ];
                $running_zones[] = $running_zone;
            }
        }

        if (count($relays) > 0) {
            usort($relays, ['HydrawiseController', 'cmp_relays_nextrun']);
            foreach ($relays as $relay) {
                $relay_id = $relay['relay_id'];
                $name = $relay['name'];

                $duration = '';
                if (isset($relay['run_seconds'])) {
                    // auf Minuten aufrunden
                    $run_seconds = ceil($relay['run_seconds'] / 60) * 60;
                    $duration = $this->seconds2duration($run_seconds);
                }

                $nicetime = $relay['nicetime'];
                $ts = 0;
                $is_today = false;
                $tm = date_create_from_format('D, j* F g:ia', $nicetime);
                if ($tm) {
                    $ts = $tm->format('U');
                    if ($tm->format('d.m.Y') == date('d.m.Y', $now)) {
                        $is_today = true;
                    }
                }

                if ($is_today) {
                    $is_running = isset($relay2running[$relay_id]) ? $relay2running[$relay_id] : false;
                    if ($is_running) {
                        continue;
                    }
                    // was kommt heute noch?
                    $today_zone = [
                            'name'      => $name,
                            'timestamp' => $ts,
                            'duration'  => $duration
                        ];
                    $today_zones[] = $today_zone;
                } elseif ($ts) {
                    // was kommt in den nächsten Tagen
                    if ($nicetime == 'Not scheduled') {
                        continue;
                    }
                    $future_zone = [
                            'name'      => $name,
                            'timestamp' => $ts,
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
                $lastwater = $relay['lastwater'];

                $is_today = false;
                $ts = strtotime($lastwater);
                if ($ts) {
                    if (date('d.m.Y', $ts) == date('d.m.Y', $now)) {
                        $is_today = true;
                    }
                }

                if (!$is_today) {
                    continue;
                }

                $duration = '';
                $daily_duration = '';
                $daily_waterusage = '';
                $instIDs = IPS_GetInstanceListByModuleID('{6A0DAE44-B86A-4D50-A76F-532365FD88AE}');
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
                            $varID = @IPS_GetObjectIDByIdent('DailyWaterUsage', $instID);
                            if ($varID) {
                                $daily_waterusage = GetValue($varID);
                            }
                        }
                        break;
                    }
                }

                $is_running = isset($relay2running[$relay_id]) ? $relay2running[$relay_id] : false;

                $done_zone = [
                        'name'              => $name,
                        'timestamp'         => $ts,
                        'duration'          => $duration,
                        'is_running'        => $is_running,
                        'daily_duration'    => $daily_duration,
                        'daily_waterusage'  => $daily_waterusage,
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

        $this->SendData($buf);

        $this->SetStatus(102);
    }

    public function ForwardData($data)
    {
        $jdata = json_decode($data);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $ret = '';

        if (isset($jdata->Function)) {
            switch ($jdata->Function) {
                case 'CmdUrl':
                    $SendData = ['DataID' => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}', 'Function' => $jdata->Function, 'Url' => $jdata->Url];
                    $ret = $this->SendDataToParent(json_encode($SendData));
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'unknown function "' . $jdata->Function . '"', 0);
                    break;
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown message-structure', 0);
        }

        $this->SendDebug(__FUNCTION__, 'ret=' . print_r($ret, true), 0);
        return $ret;
    }

    protected function ClearDailyValue()
    {
        $controller_id = $this->ReadPropertyString('controller_id');
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');

        $this->SendDebug(__FUNCTION__, '', 0);

        if ($with_daily_value) {
            $this->SetValue('DailyWateringTime', 0);
        }

        $data = ['DataID' => '{5BF2F1ED-7782-457B-856F-D4F388CBF060}', 'Function' => 'ClearDailyValue', 'controller_id' => $controller_id];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode($data));
    }

    protected function SendData($buf)
    {
        $data = ['DataID' => '{5BF2F1ED-7782-457B-856F-D4F388CBF060}', 'Buffer' => $buf];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode($data));
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
            $daily_waterusage = ceil($zone['daily_waterusage']);
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
                $html .= "<colgroup><col id='spalte_volumen'></colgroup>\n";
                $html .= "<thead>\n";
                $html .= "<tr>\n";
                $html .= "<th>heute bereits durchgeführte Bewässerung</th>\n";
                $html .= "<th colspan='2'>zuletzt</th>\n";
                $html .= "<th colspan='2'>gesamt</th>\n";
                $html .= "</tr>\n";

                $html .= "<tr>\n";
                $html .= "<th>&nbsp;</th>\n";
                $html .= "<th>Uhrzeit</th>\n";
                $html .= "<th>Dauer</th>\n";
                $html .= "<th>Dauer</th>\n";
                $html .= "<th>Menge</th>\n";
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
            $html .= "<td class='right-align'>$daily_waterusage l</td>\n";
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
                $daily_waterusage = ceil($zone['daily_waterusage']);

                if (!$b) {
                    $html .= "heute bereits durchgeführte Bewässerung\n";
                    $html .= "<table>\n";
                    $html .= "<colgroup><col></colgroup>\n";
                    $html .= "<colgroup><col id='spalte_dauer'></colgroup>\n";
                    $html .= "<colgroup><col id='spalte_volumen'></colgroup>\n";
                    $html .= "<thead>\n";
                    $html .= "<tr>\n";
                    $html .= "<th>Bezeichnung</th>\n";
                    $html .= "<th>Dauer</th>\n";
                    $html .= "<th>Menge</th>\n";
                    $html .= "</tr>\n";
                    $html .= "</thead>\n";
                    $html .= "<tdata>\n";
                    $b = true;
                }

                $html .= "<tr>\n";
                $html .= "<td>$name</td>\n";
                $html .= "<td align='right'>$_daily_duration</td>\n";
                $html .= "<td align='right'>$daily_waterusage l</td>\n";
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
        $this->SendDebug('WebHook SERVER', print_r($_SERVER, true), 0);

        $root = realpath(__DIR__);
        $uri = $_SERVER['REQUEST_URI'];
        if (substr($uri, -1) == '/') {
            http_response_code(404);
            die('File not found!');
        }
        $basename = substr($uri, strlen('/hook/Hydrawise/'));
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
        $tm = date_create_from_format('D, j* F g:ia', $a['nicetime']);
        if ($tm) {
            $a_nextrun = $tm->format('U');
        } else {
            $a_nextrun = 0;
        }

        $tm = date_create_from_format('D, j* F g:ia', $b['nicetime']);
        if ($tm) {
            $b_nextrun = $tm->format('U');
        } else {
            $b_nextrun = 0;
        }

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
        $a_lastrun = strtotime($a['lastwater']);
        $b_lastrun = strtotime($b['lastwater']);

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
}
