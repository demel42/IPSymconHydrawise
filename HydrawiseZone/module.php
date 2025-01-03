<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class HydrawiseZone extends IPSModule
{
    use Hydrawise\StubsCommonLib;
    use HydrawiseLocalLib;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonConstruct(__DIR__);
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('log_no_parent', true);

        $this->RegisterPropertyString('controller_id', '');
        $this->RegisterPropertyString('relay_id', '');
        $this->RegisterPropertyInteger('connector', -1);
        $this->RegisterPropertyBoolean('with_daily_value', true);
        $this->RegisterPropertyBoolean('with_workflow', true);
        $this->RegisterPropertyBoolean('with_status', true);
        $this->RegisterPropertyBoolean('with_waterusage', true);
        $this->RegisterPropertyInteger('with_flowrate', self::$FLOW_RATE_AVERAGE);
        $this->RegisterPropertyInteger('visibility_script', 0);

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->ConnectParent('{5927E05C-82D0-4D78-B8E0-A973470A9CD3}');
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $with_waterusage = $this->ReadPropertyBoolean('with_waterusage');
        $with_flowrate = $this->ReadPropertyInteger('with_flowrate');
        if ($with_waterusage == false && $with_flowrate != self::$FLOW_RATE_NONE) {
            $this->SendDebug(__FUNCTION__, '"with_waterusage" w/o with_flowrate is not possible', 0);
            $r[] = $this->Translate('Determination of the water consumption is not possible without measuring the flow rate');
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = ['visibility_script'];
        $this->MaintainReferences($propertyNames);

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');
        $with_workflow = $this->ReadPropertyBoolean('with_workflow');
        $with_status = $this->ReadPropertyBoolean('with_status');
        $with_waterusage = $this->ReadPropertyBoolean('with_waterusage');
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
        $this->MaintainVariable('WaterUsage', $this->Translate('Water usage'), VARIABLETYPE_FLOAT, 'Hydrawise.Flowmeter', $vpos++, $with_waterusage);
        $this->MaintainVariable('WaterFlowrate', $this->Translate('Water flow rate'), VARIABLETYPE_FLOAT, 'Hydrawise.WaterFlowrate', $vpos++, $with_flowrate != self::$FLOW_RATE_NONE);

        // Aktionen
        $this->MaintainVariable('ZoneAction', $this->Translate('Zone operation'), VARIABLETYPE_INTEGER, 'Hydrawise.ZoneAction', $vpos++, true);
        $this->MaintainVariable('SuspendUntil', $this->Translate('Suspended until end of'), VARIABLETYPE_INTEGER, '~UnixTimestampDate', $vpos++, true);
        $this->MaintainVariable('SuspendAction', $this->Translate('Zone suspension'), VARIABLETYPE_INTEGER, 'Hydrawise.ZoneSuspend', $vpos++, true);

        // Stati
        $this->MaintainVariable('Workflow', $this->Translate('Current workflow'), VARIABLETYPE_INTEGER, 'Hydrawise.ZoneWorkflow', $vpos++, $with_workflow);
        $this->MaintainVariable('Status', $this->Translate('Zone status'), VARIABLETYPE_INTEGER, 'Hydrawise.ZoneStatus', $vpos++, $with_status);

        // Tageswerte
        $this->MaintainVariable('DailyDuration', $this->Translate('Watering time (today)'), VARIABLETYPE_INTEGER, 'Hydrawise.Duration', $vpos++, $with_daily_value);
        $this->MaintainVariable('DailyWaterUsage', $this->Translate('Water usage (today)'), VARIABLETYPE_FLOAT, 'Hydrawise.Flowmeter', $vpos++, $with_daily_value && $with_waterusage);

        $this->MaintainAction('ZoneAction', true);
        $this->MaintainAction('SuspendUntil', true);
        $this->MaintainAction('SuspendAction', true);

        $relay_id = $this->ReadPropertyString('relay_id');
        $connector = $this->ReadPropertyInteger('connector');
        if ($connector < 100) {
            $info = 'Zone ' . $connector;
        } else {
            $info = 'Expander ' . floor($connector / 100) . ' Zone ' . ($connector % 100);
        }
        $info .= ' (#' . $relay_id . ')';
        $this->SetSummary($info);

        $controller_id = $this->ReadPropertyString('controller_id');
        $dataFilter = '.*' . $controller_id . '.*';
        $this->SendDebug(__FUNCTION__, 'set ReceiveDataFilter=' . $dataFilter, 0);
        $this->SetReceiveDataFilter($dataFilter);

        $this->MaintainStatus(IS_ACTIVE);
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Hydrawise Zone');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $opts_connector = [
            [
                'caption' => 'no',
                'value'   => 0
            ],
        ];
        for ($u = 0; $u <= 2; $u++) {
            for ($z = 1; $z <= 16; $z++) {
                $n = $u * 100 + $z;
                $l = $u ? $this->Translate('Expander') . ' ' . $u . ' ' : '';
                $l .= $this->Translate('Zone') . ' ' . $z;
                $opts_connector[] = ['caption' => $l, 'value' => $n];
            }
        }

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'enabled' => false,
                    'name'    => 'controller_id',
                    'caption' => 'Controller-ID',
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'enabled' => false,
                    'name'    => 'relay_id',
                    'caption' => 'Zone-ID',
                ],
                [
                    'type'    => 'Select',
                    'enabled' => false,
                    'options' => $opts_connector,
                    'name'    => 'connector',
                    'caption' => 'Connector',
                ],
            ],
            'caption' => 'Basic configuration (don\'t change)',
        ];

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
                    'name'    => 'with_workflow',
                    'caption' => 'watering workflow'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_status',
                    'caption' => 'watering status'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_waterusage',
                    'caption' => 'water usage'
                ],
                [
                    'type'    => 'Select',
                    'name'    => 'with_flowrate',
                    'caption' => 'flowrate',
                    'options' => [
                        [
                            'caption' => 'no value',
                            'value'   => self::$FLOW_RATE_NONE
                        ],
                        [
                            'caption' => 'average of cycle',
                            'value'   => self::$FLOW_RATE_AVERAGE
                        ],
                        [
                            'caption' => 'current value',
                            'value'   => self::$FLOW_RATE_CURRENT
                        ]
                    ],
                ],
                [
                    'type'    => 'SelectScript',
                    'name'    => 'visibility_script',
                    'caption' => 'optional script to hide/show variables'
                ],
            ],
            'caption' => 'optional zone data'
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'log_no_parent',
            'caption' => 'Generate message when the gateway is inactive',
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
            ],
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded'  => false,
            'items'     => [
                [
                    'type'    => 'TestCenter',
                ],
            ]
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
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
                    case 'CollectZoneValues':
                        $responses = $this->CollectZoneValues();
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

    private function DecodeData($buf)
    {
        $controller_id = $this->ReadPropertyString('controller_id');
        $relay_id = $this->ReadPropertyString('relay_id');
        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');
        $with_workflow = $this->ReadPropertyBoolean('with_workflow');
        $with_status = $this->ReadPropertyBoolean('with_status');
        $with_waterusage = $this->ReadPropertyBoolean('with_waterusage');
        $with_flowrate = $this->ReadPropertyInteger('with_flowrate');
        $visibility_script = $this->ReadPropertyInteger('visibility_script');

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

        $relay_found = false;
        $relays = $this->GetArrayElem($controller, 'relays', '');
        if ($relays != '') {
            foreach ($relays as $relay) {
                if ($relay_id == $relay['relay_id']) {
                    $relay_found = true;
                    break;
                }
            }
        }
        if ($relay_found == false) {
            $err = 'relay_id "' . $relay_id . '" not found';
            $statuscode = self::$IS_ZONE_MISSING;
            $do_abort = true;
        }

        if ($do_abort) {
            $this->LogMessage('statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, $err, 0);
            $this->MaintainStatus($statuscode);
            return -1;
        }

        $now = time();
        $server_time = $this->GetArrayElem($controller, 'time', $now);
        $this->SendDebug(__FUNCTION__, 'now=' . date('d.m.Y H:i', $now) . ', server_time=' . date('d.m.Y H:i', $server_time), 0);

        $local_relays = $this->GetArrayElem($controller, 'local.relays', '');
        if ($local_relays != '') {
            $this->SendDebug(__FUNCTION__, 'local_relays=' . print_r($local_relays, true), 0);
            foreach ($local_relays as $local_relay) {
                if ($local_relay['relay_id'] == $relay_id) {
                    $suspended = $this->GetArrayElem($local_relay, 'suspended', 0);
                    if ($suspended > 0) {
                        $relay['suspended'] = $suspended;
                        $this->SendDebug(__FUNCTION__, 'relay_id=' . $relay_id . '(' . $relay['name'] . '), suspended=' . date('d.m. H:i', (int) $relay['suspended']), 0);
                    }
                }
            }
        }

        $local_running = $this->GetArrayElem($controller, 'local.running', '');
        if ($local_running != '') {
            $this->SendDebug(__FUNCTION__, 'local_running=' . print_r($local_running, true), 0);
            foreach ($local_running as $local_run) {
                if ($local_run['relay_id'] == $relay_id) {
                    $relay['waterflow'] = $this->GetArrayElem($local_run, 'current', 0);
                    $this->SendDebug(__FUNCTION__, 'relay_id=' . $relay_id . '(' . $relay['name'] . '), waterflow=' . $relay['waterflow'], 0);
                }
            }
        }

        $relay['name'] = IPS_GetName($this->InstanceID);

        $time = $relay['time'];
        $period = $relay['period'];

        $nextrun = 0;
        $duration = 0;
        $is_running = false;
        $time_left = 0;
        $is_suspended = false;
        $suspended_until = 0;
        $waterflow = 0;

        $type = $relay['type'];

        $timestr = $relay['timestr'];
        $suspended_until = $this->GetArrayElem($relay, 'suspended', 0);
        if ($suspended_until > 0 && $timestr != 'Now') {
            $timestr = '';
        }
        switch ($timestr) {
            case 'Now':
                $is_running = true;
                $time_left = $this->GetArrayElem($relay, 'run', 0);
                $waterflow = $this->GetArrayElem($relay, 'waterflow', 0);
                $this->SendDebug(__FUNCTION__, 'is running, time_left=' . $time_left . 's, waterflow=' . $waterflow . 'l/min', 0);
                break;
            case '':
                $is_suspended = true;
                $suspended_until = $this->GetArrayElem($relay, 'suspended', 0);
                $this->SendDebug(__FUNCTION__, 'is suspended, suspended_until=' . date('d.m. H:i', (int) $suspended_until), 0);
                break;
            default:
                $nextrun = $server_time + $time;
                $duration = $this->GetArrayElem($relay, 'run', 0);
                $this->SendDebug(__FUNCTION__, 'is idle, nextrun=' . date('d.m. H:i', (int) $nextrun) . ', duration=' . $duration . 's', 0);
                break;
        }

        if ($is_running) {
            $this->SetValue('ZoneAction', self::$ZONE_ACTION_STOP);
        } else {
            $lastrun = $this->GetValue('LastRun');
            $this->SetValue('ZoneAction', self::$ZONE_ACTION_DEFAULT);
        }

        if (!$is_running && !$is_suspended) {
            $this->SetValue('NextRun', $nextrun);
            $this->SetValue('NextDuration', ceil($duration / 60));
        }

        if ($is_suspended) {
            $this->SetValue('SuspendUntil', $suspended_until);
            $this->SetValue('SuspendAction', self::$ZONE_SUSPEND_CLEAR);
        } else {
            $this->SetValue('SuspendUntil', 0);
            $this->SetValue('SuspendAction', self::$ZONE_SUSPEND_1DAY);
        }

        if ($is_running) {
            $waterMeterID = 1;
            $waterMeterFactor = 0.0;

            $ret = $this->CollectControllerValues();
            $responses = $ret != false ? json_decode($ret, true) : [];
            foreach ($responses as $response) {
                $values = json_decode($response, true);
                if ($values['controller_id'] == $controller_id) {
                    $waterMeterID = $values['WaterMeterID'];
                    $waterMeterFactor = $values['WaterMeterFactor'];
                    break;
                }
            }

            $water_counter = IPS_VariableExists($waterMeterID) ? (float) GetValue($waterMeterID) : 0;

            $buf = $this->GetBuffer('currentRun');
            if ($buf != '') {
                $current_run = json_decode($buf, true);
                $last_water_usage = $current_run['water_usage'];
                $last_water_counter = $current_run['water_counter'];
                $time_begin = $current_run['time_begin'];
                $last_server_time = $current_run['server_time'];
            } else {
                $last_water_usage = 0;
                $last_water_counter = $water_counter;
                $last_server_time = (int) $this->GetValue('NextRun'); // bis zum Ende des Laufs ist "NextRun" der Zeitpunkt des aktuellen Laufs
                if (abs($last_server_time - $server_time) > 60) {
                    $last_server_time = $server_time;
                }
                $time_begin = $last_server_time;
            }

            $time_end = $server_time + $time_left;

            $this->SetValue('TimeLeft', $this->seconds2duration($time_left));

            $tot_time_duration = $server_time - $time_begin;
            $cur_time_duration = $server_time - $last_server_time;

            $begin = date('d.m.Y H:i', $time_begin);
            $end = date('d.m.Y H:i', $time_end);

            if ($with_waterusage) {
                if (IPS_VariableExists($waterMeterID)) {
                    $water_usage = round(($water_counter - $last_water_counter) * $waterMeterFactor);
                    $cur_water_usage = $water_usage - $this->GetValue('WaterUsage');
                    if ($cur_water_usage > 0 && $cur_time_duration > 0) {
                        $cur_water_flowrate = $cur_water_usage / ($cur_time_duration / 60.0);
                        $cur_water_flowrate = floor($cur_water_flowrate * 100) / 100;
                    } else {
                        $cur_water_flowrate = 0;
                    }
                } else {
                    $water_usage = $this->GetValue('WaterUsage');
                    $cur_water_flowrate = $waterflow;
                    if ($cur_time_duration > 0) {
                        $cur_water_usage = $cur_water_flowrate * ($cur_time_duration / 60.0);
                    } else {
                        $cur_water_usage = 0;
                    }
                    $water_usage += $cur_water_usage;
                }

                if ($water_usage > 0 && $tot_time_duration > 0) {
                    $avg_water_flowrate = $water_usage / ($tot_time_duration / 60.0);
                    $avg_water_flowrate = floor($avg_water_flowrate * 100) / 100;
                } else {
                    $avg_water_flowrate = 0;
                }

                $this->SetValue('WaterUsage', $water_usage);
                switch ($with_flowrate) {
                case self::$FLOW_RATE_AVERAGE:
                    $this->SetValue('WaterFlowrate', $avg_water_flowrate);
                    break;
                case self::$FLOW_RATE_CURRENT:
                    $this->SetValue('WaterFlowrate', $cur_water_flowrate);
                    break;
                default:
                    break;
                }

                $this->SendDebug(__FUNCTION__, 'save: begin=' . $begin . ', end=' . $end . ', left=' . $time_left . ', water_usage=' . $water_usage, 0);
                $this->SendDebug(__FUNCTION__, ' * avg: duration=' . $tot_time_duration . 's, water_usage=' . $water_usage . ' => flowrate=' . $avg_water_flowrate, 0);
                $this->SendDebug(__FUNCTION__, ' * cur: duration=' . $cur_time_duration . 's, water_usage=' . $cur_water_usage . ' => flowrate=' . $cur_water_flowrate, 0);
                if (IPS_VariableExists($waterMeterID)) {
                    $this->SendDebug(__FUNCTION__, ' * watermeter: start=' . $last_water_counter . ', cur=' . $water_counter, 0);
                }
            } else {
                $this->SendDebug(__FUNCTION__, 'save: begin=' . $begin . ', end=' . $end . ', left=' . $time_left . 's, duration=' . $tot_time_duration . 's', 0);
            }

            $current_run = [
                'time_begin'    => $time_begin,
                'time_end'      => $time_end,
                'time_left'     => $time_left,
                'server_time'   => $server_time,
                'water_counter'	=> $last_water_counter,
            ];
            if ($with_waterusage) {
                $current_run['water_usage'] = $water_usage;
            }

            $this->SetBuffer('currentRun', json_encode($current_run));
        } else {
            $this->SetValue('TimeLeft', '');
            if ($with_waterusage) {
                $this->SetValue('WaterUsage', 0);
                if ($with_flowrate != self::$FLOW_RATE_NONE) {
                    $this->SetValue('WaterFlowrate', 0);
                }
            }

            $buf = $this->GetBuffer('currentRun');
            if ($buf != '') {
                $waterMeterID = 1;
                $waterMeterFactor = 0.0;

                $ret = $this->CollectControllerValues();
                $responses = $ret != false ? json_decode($ret, true) : [];
                foreach ($responses as $response) {
                    $values = json_decode($response, true);
                    if ($values['controller_id'] == $controller_id) {
                        $waterMeterID = $values['WaterMeterID'];
                        $waterMeterFactor = $values['WaterMeterFactor'];
                        break;
                    }
                }

                $current_run = json_decode($buf, true);

                $time_begin = $current_run['time_begin'];
                $time_end = $current_run['time_end'];

                // Abbruch eines Laufs
                if ($time_end > $server_time) {
                    $time_end = $server_time;
                }
                $duration = $time_end - $time_begin;
                // auf ganze Minuten aufrunden, weil Läufe im Minutenraster durchgeführt werden (Ausnahme: manueller Abbruch)
                $time_duration = ceil($duration / 60);

                $begin = date('d.m.Y H:i', $time_begin);
                $end = date('d.m.Y H:i', $time_end);

                if ($with_waterusage) {
                    $time_left = $current_run['time_left'];
                    $time_done = $time_end - $time_begin - $time_left;

                    if (IPS_VariableExists($waterMeterID)) {
                        $water_counter = (float) GetValue($waterMeterID);
                        $last_water_counter = $current_run['water_counter'];
                        $water_usage = round(($water_counter - $last_water_counter) * $waterMeterFactor);
                        $water_estimated = $water_usage;
                    } else {
                        $water_usage = $current_run['water_usage'];
                        if ($water_usage > 0 && $time_done > 0) {
                            $water_estimated = ceil($water_usage / $time_done * $duration);
                        } else {
                            $water_estimated = 0;
                        }
                    }

                    $this->SendDebug(__FUNCTION__, 'restore: begin=' . $begin . ', end=' . $end . ', left=' . $time_left . ', water_usage=' . $water_usage, 0);
                    $this->SendDebug(__FUNCTION__, ' * duration=' . $duration . 's/' . $time_duration . 'm, done=' . $time_done . ' => water_estimated=' . $water_estimated, 0);
                    if (IPS_VariableExists($waterMeterID)) {
                        $this->SendDebug(__FUNCTION__, ' * watermeter: start=' . $last_water_counter . ', cur=' . $water_counter, 0);
                    }
                } else {
                    $this->SendDebug(__FUNCTION__, 'restore: begin=' . $begin . ', end=' . $end . ', duration=' . $duration . 's/' . $time_duration . 'm', 0);
                }

                $this->SetValue('LastDuration', $time_duration);
                if ($with_daily_value) {
                    $duration = $this->GetValue('DailyDuration') + $time_duration;
                    $this->SetValue('DailyDuration', $duration);

                    if ($with_waterusage) {
                        $water_usage = $this->GetValue('DailyWaterUsage') + $water_estimated;
                        $this->SetValue('DailyWaterUsage', $water_usage);
                    }
                }
                $this->SetValue('LastRun', $time_begin);
                $this->SetBuffer('currentRun', '');
            }
        }

        $zone_status = self::$ZONE_STATUS_IDLE;
        $workflow = self::$ZONE_WORKFLOW_MANUAL;
        if ($is_running) {
            $zone_status = self::$ZONE_STATUS_WATERING;
            $workflow = self::$ZONE_WORKFLOW_WATERING;
        } else {
            if ($lastrun && date('d.m.Y', $lastrun) == date('d.m.Y', $now)) {
                if ($nextrun && date('d.m.Y', $nextrun) == date('d.m.Y', $now)) {
                    $workflow = self::$ZONE_WORKFLOW_PARTIALLY;
                } else {
                    $workflow = self::$ZONE_WORKFLOW_DONE;
                }
            } elseif ($nextrun) {
                if (date('d.m.Y', $nextrun) == date('d.m.Y', $now)) {
                    $workflow = self::$ZONE_WORKFLOW_SCHEDULED;
                } else {
                    $workflow = self::$ZONE_WORKFLOW_SOON;
                }
            }
        }
        if ($is_suspended) {
            $zone_status = self::$ZONE_STATUS_SUSPENDED;
            $workflow = self::$ZONE_WORKFLOW_SUSPENDED;
        }
        if ($with_status) {
            $this->SetValue('Status', $zone_status);
        }
        if ($with_workflow) {
            $this->SetValue('Workflow', $workflow);
        }

        if (IPS_ScriptExists($visibility_script)) {
            $opts = [
                'InstanceID'       => $this->InstanceID,
                'suspended_until'  => $suspended_until,
                'next_duration'    => $duration,
                'time_left'        => $time_left,
            ];
            $ret = IPS_RunScriptWaitEx($visibility_script, $opts);
            $this->SendDebug(__FUNCTION__, 'visibility_script: ' . $ret, 0);
        }

        $this->MaintainStatus(IS_ACTIVE);
    }

    private function ClearDailyValue()
    {
        $this->SendDebug(__FUNCTION__, '', 0);

        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');
        if ($with_daily_value) {
            $this->SetValue('DailyDuration', 0);
            $with_waterusage = $this->ReadPropertyBoolean('with_waterusage');
            if ($with_waterusage) {
                $this->SetValue('DailyWaterUsage', 0);
            }
        }
    }

    private function CollectZoneValues()
    {
        $relay_id = $this->ReadPropertyString('relay_id');

        $with_daily_value = $this->ReadPropertyBoolean('with_daily_value');
        $with_waterusage = $this->ReadPropertyBoolean('with_waterusage');
        $with_flowrate = $this->ReadPropertyInteger('with_flowrate');

        $jdata = [
            'relay_id'     => $relay_id,
            'Name'         => IPS_GetName($this->InstanceID),
            'LastRun'      => (int) $this->GetValue('LastRun'),
            'SuspendUntil' => (int) $this->GetValue('SuspendUntil'),
            'LastDuration' => (int) $this->GetValue('LastDuration'),
        ];

        if ($with_daily_value) {
            $jdata['DailyDuration'] = (int) $this->GetValue('DailyDuration');
        }

        if ($with_daily_value && $with_waterusage) {
            $jdata['DailyWaterUsage'] = (float) $this->GetValue('DailyWaterUsage');
        }

        if ($with_flowrate != self::$FLOW_RATE_NONE) {
            $jdata['WaterFlowrate'] = (float) $this->GetValue('WaterFlowrate');
        }

        $data = json_encode($jdata);
        $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);
        return $data;
    }

    private function CollectControllerValues()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }
        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return;
        }

        $controller_id = $this->ReadPropertyString('controller_id');
        $sdata = [
            'DataID'        => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}', // an HydrawiseIO
            'CallerID'      => $this->InstanceID,
            'Function'      => 'CollectControllerValues',
            'controller_id' => $controller_id
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $responses = $this->SendDataToParent(json_encode($sdata));
        $this->SendDebug(__FUNCTION__, 'responses=' . print_r($responses, true), 0);
        return $responses;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        switch ($ident) {
            case 'SuspendUntil':
                $dt = date('d.m.Y H:i:s', $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ' . $dt, 0);
                $this->Suspend($value);
                break;
            case 'SuspendAction':
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value, 0);
                if ($value == self::$ZONE_SUSPEND_CLEAR) {
                    $this->Resume($value);
                } else {
                    $sec = $value * 86400;
                    $dt = new DateTime(date('d.m.Y 23:59:59', time() + $sec));
                    $ts = (int) $dt->format('U');
                    $dt = date('d.m.Y H:i:s', $ts);
                    $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ' . $dt, 0);
                    $this->Suspend($ts);
                }
                break;
            case 'ZoneAction':
                switch ($value) {
                    case self::$ZONE_ACTION_STOP:
                        $this->Stop();
                        break;
                    case self::$ZONE_ACTION_DEFAULT:
                        $this->Run();
                        break;
                    default:
                        $sec = $value * 60;
                        $this->Run($sec);
                        break;
                }
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value, 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }

    private function SendCmdUrl($url)
    {
        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return;
        }

        $sdata = [
            'DataID'   => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}', // an HydrawiseIO
            'CallerID' => $this->InstanceID,
            'Function' => 'CmdUrl',
            'Url'      => $url
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $data = $this->SendDataToParent(json_encode($sdata));
        $jdata = json_decode($data, true);

        $this->SendDebug(__FUNCTION__, 'url=' . $url . ', got data=' . print_r($jdata, true), 0);

        if (isset($jdata['msg'])) {
            $controller_id = $this->ReadPropertyString('controller_id');
            $sdata = [
                'DataID'        => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}', // an HydrawiseIO
                'CallerID'      => $this->InstanceID,
                'Function'      => 'SetMessage',
                'msg'           => $jdata['msg'],
                'controller_id' => $controller_id
            ];
            $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
            $data = $this->SendDataToParent(json_encode($sdata));
        }

        $controller_id = $this->ReadPropertyString('controller_id');
        $sdata = [
            'DataID'        => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}', // an HydrawiseIO
            'CallerID'      => $this->InstanceID,
            'Function'      => 'UpdateController',
            'controller_id' => $controller_id
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $data = $this->SendDataToParent(json_encode($sdata));

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
