<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/library.php';  // modul-bezogene Funktionen

// Zone-Action
if (!defined('ZONE_ACTION_STOP')) {
    define('ZONE_ACTION_STOP', -1);
    define('ZONE_ACTION_DEFAULT', 0);
    define('ZONE_SUSPEND_CLEAR', -1);
}

// aktuelle Aktivität
if (!defined('ZONE_WORKFLOW_SUSPENDED')) {
    define('ZONE_WORKFLOW_SUSPENDED', -1);
    define('ZONE_WORKFLOW_MANUAL', 0);
    define('ZONE_WORKFLOW_SOON', 1);
    define('ZONE_WORKFLOW_SCHEDULED', 2);
    define('ZONE_WORKFLOW_WATERING', 3);
    define('ZONE_WORKFLOW_DONE', 4);
    define('ZONE_WORKFLOW_PARTIALLY', 5);
}

// aktueller Status
if (!defined('ZONE_STATUS_SUSPENDED')) {
    define('ZONE_STATUS_SUSPENDED', -1);
    define('ZONE_STATUS_IDLE', 0);
    define('ZONE_STATUS_WATERING', 1);
}

// aktueller Status
if (!defined('FLOW_RATE_NONE')) {
    define('FLOW_RATE_NONE', 0);
    define('FLOW_RATE_AVERAGE', 1);
    define('FLOW_RATE_CURRENT', 2);
}

class HydrawiseZone extends IPSModule
{
    use HydrawiseCommon;
    use HydrawiseLibrary;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('controller_id', '');
        $this->RegisterPropertyString('relay_id', '');
        $this->RegisterPropertyInteger('connector', -1);
        $this->RegisterPropertyBoolean('with_daily_value', true);
        $this->RegisterPropertyBoolean('with_workflow', true);
        $this->RegisterPropertyBoolean('with_status', true);
        $this->RegisterPropertyInteger('with_flowrate', FLOW_RATE_AVERAGE);
        $this->RegisterPropertyInteger('visibility_script', 0);

        $associations = [];
        $associations[] = ['Wert' => ZONE_ACTION_STOP, 'Name' => $this->Translate('Stop'), 'Farbe' => 0xEE0000];
        $associations[] = ['Wert' => ZONE_ACTION_DEFAULT, 'Name' => $this->Translate('Default'), 'Farbe' => 0x32CD32];
        $associations[] = ['Wert' =>  1, 'Name' => $this->Translate('1 min'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  2, 'Name' => $this->Translate('2 min'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  5, 'Name' => $this->Translate('5 min'), 'Farbe' => -1];
        $associations[] = ['Wert' => 10, 'Name' => $this->Translate('10 min'), 'Farbe' => -1];
        $associations[] = ['Wert' => 15, 'Name' => $this->Translate('15 min'), 'Farbe' => -1];
        $associations[] = ['Wert' => 20, 'Name' => $this->Translate('20 min'), 'Farbe' => -1];
        $this->CreateVarProfile('Hydrawise.ZoneAction', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations);

        $associations = [];
        $associations[] = ['Wert' => ZONE_SUSPEND_CLEAR, 'Name' => $this->Translate('Clear'), 'Farbe' => 0xEE0000];
        $associations[] = ['Wert' => 1, 'Name' => $this->Translate('1 day'), 'Farbe' => -1];
        $associations[] = ['Wert' => 2, 'Name' => $this->Translate('2 days'), 'Farbe' => -1];
        $associations[] = ['Wert' => 7, 'Name' => $this->Translate('1 week'), 'Farbe' => -1];
        $this->CreateVarProfile('Hydrawise.ZoneSuspend', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations);

        $associations[] = ['Wert' => ZONE_WORKFLOW_SUSPENDED, 'Name' => $this->Translate('suspended'), 'Farbe' => 0xFF5D5D];
        $associations[] = ['Wert' => ZONE_WORKFLOW_MANUAL, 'Name' => $this->Translate('manual'), 'Farbe' => 0xC0C0C0];
        $associations[] = ['Wert' => ZONE_WORKFLOW_SOON, 'Name' => $this->Translate('soon'), 'Farbe' => 0x6CB6FF];
        $associations[] = ['Wert' => ZONE_WORKFLOW_SCHEDULED, 'Name' => $this->Translate('scheduled'), 'Farbe' => 0x0080C0];
        $associations[] = ['Wert' => ZONE_WORKFLOW_WATERING, 'Name' => $this->Translate('watering'), 'Farbe' => 0xFFFF00];
        $associations[] = ['Wert' => ZONE_WORKFLOW_DONE, 'Name' => $this->Translate('done'), 'Farbe' => 0x008000];
        $associations[] = ['Wert' => ZONE_WORKFLOW_PARTIALLY, 'Name' => $this->Translate('partially'), 'Farbe' => 0x80FF00];
        $this->CreateVarProfile('Hydrawise.ZoneWorkflow', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations);

        $associations[] = ['Wert' => ZONE_STATUS_SUSPENDED, 'Name' => $this->Translate('suspended'), 'Farbe' => 0xFF5D5D];
        $associations[] = ['Wert' => ZONE_STATUS_IDLE, 'Name' => $this->Translate('idle'), 'Farbe' => 0xC0C0C0];
        $associations[] = ['Wert' => ZONE_STATUS_WATERING, 'Name' => $this->Translate('watering'), 'Farbe' => 0xFFFF00];
        $this->CreateVarProfile('Hydrawise.ZoneStatus', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations);

        $this->CreateVarProfile('Hydrawise.Flowmeter', VARIABLETYPE_FLOAT, ' l', 0, 0, 0, 0, 'Gauge');
        $this->CreateVarProfile('Hydrawise.WaterFlowrate', VARIABLETYPE_FLOAT, ' l/min', 0, 0, 0, 1, '');

        $this->ConnectParent('{5927E05C-82D0-4D78-B8E0-A973470A9CD3}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $controller_id = $this->ReadPropertyString('controller_id');
        $relay_id = $this->ReadPropertyString('relay_id');
        $connector = $this->ReadPropertyInteger('connector');
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');
        $with_workflow = $this->ReadPropertyBoolean('with_workflow');
        $with_status = $this->ReadPropertyBoolean('with_status');
        $with_flowrate = $this->ReadPropertyInteger('with_flowrate');

        $vpos = 1;

        // letzter Bewässerungszyklus
        $this->MaintainVariable('LastRun', $this->Translate('Last run'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('LastDuration', $this->Translate('Duration of last run'), VARIABLETYPE_INTEGER, 'Hydrawise.Duration', $vpos++, true);

        // nächster Bewässerungszyklus
        $this->MaintainVariable('NextRun', $this->Translate('Next run'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('NextDuration', $this->Translate('Duration of next run'), VARIABLETYPE_INTEGER, 'Hydrawise.Duration', $vpos++, true);

        // aktueller Bewässerungszyklus
        $this->MaintainVariable('TimeLeft', $this->Translate('Time left'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('WaterUsage', $this->Translate('Water usage'), VARIABLETYPE_FLOAT, 'Hydrawise.Flowmeter', $vpos++, true);
        $this->MaintainVariable('WaterFlowrate', $this->Translate('Water flow rate'), VARIABLETYPE_FLOAT, 'Hydrawise.WaterFlowrate', $vpos++, $with_flowrate != FLOW_RATE_NONE);

        // Aktionen
        $this->MaintainVariable('ZoneAction', $this->Translate('Zone operation'), VARIABLETYPE_INTEGER, 'Hydrawise.ZoneAction', $vpos++, true);
        $this->MaintainVariable('SuspendUntil', $this->Translate('Suspended until end of'), VARIABLETYPE_INTEGER, '~UnixTimestampDate', $vpos++, true);
        $this->MaintainVariable('SuspendAction', $this->Translate('Zone suspension'), VARIABLETYPE_INTEGER, 'Hydrawise.ZoneSuspend', $vpos++, true);

        // Stati
        $this->MaintainVariable('Workflow', $this->Translate('Current workflow'), VARIABLETYPE_INTEGER, 'Hydrawise.ZoneWorkflow', $vpos++, $with_workflow);
        $this->MaintainVariable('Status', $this->Translate('Zone status'), VARIABLETYPE_INTEGER, 'Hydrawise.ZoneStatus', $vpos++, $with_status);

        // Tageswerte
        $this->MaintainVariable('DailyDuration', $this->Translate('Duration of runs (today)'), VARIABLETYPE_INTEGER, 'Hydrawise.Duration', $vpos++, $with_daily_value);
        $this->MaintainVariable('DailyWaterUsage', $this->Translate('Water usage (today)'), VARIABLETYPE_FLOAT, 'Hydrawise.Flowmeter', $vpos++, $with_daily_value);

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

        $dataFilter = '.*controller_id[^:]*:' . $controller_id . ',.*';
        $this->SendDebug(__FUNCTION__, 'set ReceiveDataFilter=' . $dataFilter, 0);
        $this->SetReceiveDataFilter($dataFilter);

        $this->SetStatus(IS_ACTIVE);
    }

    protected function GetFormActions()
    {
        $formActions = [];
        $formActions[] = [
                            'type'    => 'Button',
                            'caption' => 'Module description',
                            'onClick' => 'echo "https://github.com/demel42/IPSymconHydrawise/blob/master/README.md";'
                        ];

        return $formActions;
    }

    public function GetFormElements()
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

        $opts_flowrate = [];
        $opts_flowrate[] = ['label' => $this->Translate('no value'), 'value' => FLOW_RATE_NONE];
        $opts_flowrate[] = ['label' => $this->Translate('average of cycle'), 'value' => FLOW_RATE_AVERAGE];
        $opts_flowrate[] = ['label' => $this->Translate('current value'), 'value' => FLOW_RATE_CURRENT];

        $formElements = [];
        $formElements[] = ['type' => 'Label', 'label' => 'Hydrawise Zone'];

        $items = [];
        $items[] = ['type' => 'ValidationTextBox', 'name' => 'controller_id', 'caption' => 'Controller-ID'];
        $items[] = ['type' => 'ValidationTextBox', 'name' => 'relay_id', 'caption' => 'Zone-ID'];
        $items[] = ['type' => 'Select', 'name' => 'connector', 'caption' => 'Connector', 'options' => $opts_connector];
        $formElements[] = ['type' => 'ExpansionPanel', 'items' => $items, 'caption' => 'Basis configuration (don\'t change)'];

        $items = [];
        $items[] = ['type' => 'CheckBox', 'name' => 'with_daily_value', 'caption' => 'daily sum'];
        $items[] = ['type' => 'CheckBox', 'name' => 'with_workflow', 'caption' => 'watering workflow'];
        $items[] = ['type' => 'CheckBox', 'name' => 'with_status', 'caption' => 'watering status'];
        $items[] = ['type' => 'Select', 'name' => 'with_flowrate', 'caption' => 'flowrate', 'options' => $opts_flowrate];
        $items[] = ['type' => 'SelectScript', 'name' => 'visibility_script', 'caption' => 'optional script to hide/show variables'];
        $formElements[] = ['type' => 'ExpansionPanel', 'items' => $items, 'caption' => 'optional zone data'];

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

        if (isset($jdata['Buffer'])) {
            $this->DecodeData($jdata['Buffer']);
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
        $relay_id = $this->ReadPropertyString('relay_id');
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');
        $with_workflow = $this->ReadPropertyBoolean('with_workflow');
        $with_status = $this->ReadPropertyBoolean('with_status');
        $with_flowrate = $this->ReadPropertyInteger('with_flowrate');
        $visibility_script = $this->ReadPropertyInteger('visibility_script');

        $err = '';
        $statuscode = 0;
        $do_abort = false;

        if ($buf != '') {
            $controller = json_decode($buf, true);
            if ($controller_id != $controller['controller_id']) {
                $err = 'controller_id "' . $controller_id . '" not found';
                $statuscode = IS_CONTROLLER_MISSING;
                $do_abort = true;
            }
        } else {
            $err = 'no data';
            $statuscode = IS_NODATA;
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
            $err = 'relay_id "' . $relay_id . '" not found';
            $statuscode = IS_ZONE_MISSING;
            $do_abort = true;
        }

        if ($do_abort) {
            $this->LogMessage('statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, $err, 0);
            $this->SetStatus($statuscode);
            return -1;
        }

        $now = time();
        $server_time = isset($controller['time']) ? $controller['time'] : $now;

        $this->SendDebug(__FUNCTION__, 'now=' . date('d.m.Y H:i', $now) . ', server_time=' . date('d.m.Y H:i', $server_time), 0);

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
        $water_usage = 0;
        if ($running != '') {
            foreach ($running as $run) {
                if ($relay_id != $run['relay_id']) {
                    continue;
                }
                $is_running = true;
                $time_left = $run['time_left'];
                $water_usage = $run['water_int'];
                $this->SendDebug(__FUNCTION__, 'time_left=' . $time_left . ', water_usage=' . $water_usage, 0);
            }
        }

        $this->SetValue('ZoneAction', $is_running ? -1 : 0);

        $suspended = isset($relay['suspended']) ? $relay['suspended'] : 0;
        $is_suspended = $suspended > 0;
        $this->SetValue('SuspendUntil', $suspended);

        $this->SetValue('SuspendAction', $is_suspended ? -1 : 1);

        $run_seconds = isset($relay['run_seconds']) ? $relay['run_seconds'] : 0;
        // auf ganze Minuten aufrunden, weil Läufe im Minutenraster durchgeführt werden (Ausnahme: manueller Abbruch)
        $run_seconds = ceil($run_seconds / 60);
        $this->SetValue('NextDuration', $run_seconds);

        $this->SendDebug(__FUNCTION__, 'lastwater=' . $lastwater . ' => ' . $lastrun . ', nicetime=' . $nicetime . ' => ' . $nextrun . ', suspended=' . $suspended, 0);

        if ($is_running) {
            $buf = $this->GetBuffer('currentRun');
            if ($buf != '') {
                $current_run = json_decode($buf, true);
                $last_water_usage = $current_run['water_usage'];
                $last_server_time = $current_run['server_time'];
            } else {
                $last_water_usage = 0;
                $last_server_time = $lastrun;
            }

            $time_begin = $lastrun;
            $time_end = $server_time + $time_left;

            $tot_time_duration = $server_time - $time_begin;
            $avg_water_flowrate = $tot_time_duration ? floor($water_usage / ($tot_time_duration / 60.0) * 100) / 100 : 0;

            $cur_time_duration = $server_time - $last_server_time;
            $cur_water_usage = $water_usage - $last_water_usage;
            $cur_water_flowrate = $cur_time_duration ? floor($cur_water_usage / ($cur_time_duration / 60.0) * 100) / 100 : 0;

            $this->SetValue('TimeLeft', $this->seconds2duration($time_left));
            $this->SetValue('WaterUsage', $water_usage);
            switch ($with_flowrate) {
                case FLOW_RATE_AVERAGE:
                    $this->SetValue('WaterFlowrate', $avg_water_flowrate);
                    break;
                case FLOW_RATE_CURRENT:
                    $this->SetValue('WaterFlowrate', $cur_water_flowrate);
                    break;
                default:
                    break;
            }

            $current_run = [
                    'time_begin'    => $time_begin,
                    'time_end'      => $time_end,
                    'time_left'     => $time_left,
                    'water_usage'   => $water_usage,
                    'server_time'	  => $server_time,
                ];
            $this->SetBuffer('currentRun', json_encode($current_run));
            $this->SendDebug(__FUNCTION__, 'save: begin=' . date('d.m.Y H:i', $time_begin) . ', end=' . date('d.m.Y H:i', $time_end) . ', left=' . $time_left . ', water_usage=' . $water_usage, 0);
            $this->SendDebug(__FUNCTION__, ' * avg: time_duration=' . $tot_time_duration . ', water_usage=' . $water_usage . ' => flowrate=' . $avg_water_flowrate, 0);
            $this->SendDebug(__FUNCTION__, ' * cur: time_duration=' . $cur_time_duration . ', water_usage=' . $cur_water_usage . ' => flowrate=' . $cur_water_flowrate, 0);
        } else {
            $this->SetValue('TimeLeft', '');
            $this->SetValue('WaterUsage', 0);
            if ($with_flowrate != FLOW_RATE_NONE) {
                $this->SetValue('WaterFlowrate', 0);
            }

            $buf = $this->GetBuffer('currentRun');
            if ($buf != '') {
                $current_run = json_decode($buf, true);

                $time_begin = $current_run['time_begin'];
                $time_end = $current_run['time_end'];
                $time_left = $current_run['time_left'];
                $water_usage = $current_run['water_usage'];

                $time_duration = $time_end - $time_begin;
                // auf ganze Minuten aufrunden, weil Läufe im Minutenraster durchgeführt werden (Ausnahme: manueller Abbruch)
                $time_duration = ceil($time_duration / 60);
                $time_done = $time_end - $time_begin - $time_left;

                $water_estimated = ceil($water_usage / $time_done * ($time_duration * 60));

                $this->SendDebug(__FUNCTION__, 'restore: begin=' . date('d.m.Y H:i', $time_begin) . ', end=' . date('d.m.Y H:i', $time_end) . ', left=' . $time_left . ', water_usage=' . $water_usage, 0);
                $this->SendDebug(__FUNCTION__, ' * duration=' . $time_duration . ', done=' . $time_done . ' => water_estimated=' . $water_estimated, 0);

                $this->SetValue('LastDuration', $time_duration);
                if ($with_daily_value) {
                    $duration = $this->GetValue('DailyDuration') + $time_duration;
                    $this->SetValue('DailyDuration', $duration);

                    $water_usage = $this->GetValue('DailyWaterUsage') + $water_estimated;
                    $this->SetValue('DailyWaterUsage', $water_usage);
                }
                $this->SetBuffer('currentRun', '');
            }
        }

        $zone_status = ZONE_STATUS_IDLE;
        $workflow = ZONE_WORKFLOW_MANUAL;
        if ($is_running) {
            $zone_status = ZONE_STATUS_WATERING;
            $workflow = ZONE_WORKFLOW_WATERING;
        } else {
            if ($lastrun && date('d.m.Y', $lastrun) == date('d.m.Y', $now)) {
                if ($nextrun && date('d.m.Y', $nextrun) == date('d.m.Y', $now)) {
                    $workflow = ZONE_WORKFLOW_PARTIALLY;
                } else {
                    $workflow = ZONE_WORKFLOW_DONE;
                }
            } elseif ($nextrun) {
                if (date('d.m.Y', $nextrun) == date('d.m.Y', $now)) {
                    $workflow = ZONE_WORKFLOW_SCHEDULED;
                } else {
                    $workflow = ZONE_WORKFLOW_SOON;
                }
            }
        }
        if ($is_suspended) {
            $zone_status = ZONE_STATUS_SUSPENDED;
            $workflow = ZONE_WORKFLOW_SUSPENDED;
        }
        if ($with_status) {
            $this->SetValue('Status', $zone_status);
        }
        if ($with_workflow) {
            $this->SetValue('Workflow', $workflow);
        }

        if ($visibility_script > 0) {
            $opts = [
                    'InstanceID'       => $this->InstanceID,
                    'suspended_until'  => $suspended,
                    'next_duration'    => $run_seconds,
                    'time_left'        => $time_left,
                ];
            $ret = IPS_RunScriptWaitEx($visibility_script, $opts);
            $this->SendDebug(__FUNCTION__, 'visibility_script: ' . $ret, 0);
        }

        $this->SetStatus(IS_ACTIVE);
    }

    protected function ClearDailyValue()
    {
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');

        $this->SendDebug(__FUNCTION__, '', 0);

        if ($with_daily_value) {
            $this->SetValue('DailyDuration', 0);
            $this->SetValue('DailyWaterUsage', 0);
        }
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'SuspendUntil':
                $dt = date('d.m.Y H:i:s', $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ' . $dt, 0);
                $this->Suspend($Value);
                break;
            case 'SuspendAction':
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value, 0);
                if ($Value == ZONE_SUSPEND_CLEAR) {
                    $this->Resume($Value);
                } else {
                    $sec = $Value * 86400;
                    $dt = new DateTime(date('d.m.Y 23:59:59', time() + $sec));
                    $ts = $dt->format('U');
                    $dt = date('d.m.Y H:i:s', $ts);
                    $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ' . $dt, 0);
                    $this->Suspend($ts);
                }
                break;
            case 'ZoneAction':
                switch ($Value) {
                    case ZONE_ACTION_STOP:
                        $this->Stop();
                        break;
                    case ZONE_ACTION_DEFAULT:
                        $this->Run();
                        break;
                    default:
                        $sec = $Value * 60;
                        $this->Run($sec);
                        break;
                }
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value, 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $Ident, 0);
                break;
        }
    }

    private function SendCmdUrl($url)
    {
        $SendData = ['DataID' => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}', 'Function' => 'CmdUrl', 'Url' => $url];
        $data = $this->SendDataToParent(json_encode($SendData));
        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'url=' . $url . ', got data=' . print_r($jdata, true), 0);

        $controller_id = $this->ReadPropertyString('controller_id');
        $data = ['DataID' => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}', 'Function' => 'UpdateController', 'controller_id' => $controller_id];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToParent(json_encode($data));

        return $jdata['status'];
    }

    public function Run(int $duration)
    {
        $relay_id = $this->ReadPropertyString('relay_id');
        $url = 'relay_id=' . $relay_id . '&action=run';
        if ($duration > 0) {
            $url .= '&custom=' . $duration;
        }

        return $this->SendCmdUrl($url);
    }

    public function Stop()
    {
        $relay_id = $this->ReadPropertyString('relay_id');
        $url = 'relay_id=' . $relay_id . '&action=stop';

        return $this->SendCmdUrl($url);
    }

    public function Suspend(int $timestamp)
    {
        $relay_id = $this->ReadPropertyString('relay_id');
        $url = 'relay_id=' . $relay_id . '&action=suspend&custom=' . $timestamp;

        return $this->SendCmdUrl($url);
    }

    public function Resume()
    {
        $relay_id = $this->ReadPropertyString('relay_id');
        $url = 'relay_id=' . $relay_id . '&action=suspend&custom=' . time();

        return $this->SendCmdUrl($url);
    }
}
