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
        $this->RegisterPropertyInteger('connector', -1);

        $this->ConnectParent('{5927E05C-82D0-4D78-B8E0-A973470A9CD3}');
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
        $formElements[] = ['type' => 'Select', 'name' => 'connector', 'caption' => 'connector', 'options' => $opts_connector];

        $formStatus = [];
        $formStatus[] = ['code' => '101', 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => '102', 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => '104', 'icon' => 'inactive', 'caption' => 'Instance is inactive'];

        return json_encode(['elements' => $formElements, 'status' => $formStatus]);
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

    public function ReceiveData($data)
    {
        $jdata = json_decode($data);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);
        $buf = $jdata->Buffer;

        $controller_id = $this->ReadPropertyString('controller_id');
        $connector = $this->ReadPropertyInteger('connector');

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

            $this->SetValue('Status', false);
            return -1;
        }

        $vpos = 1;

        $now = time();

        $relays = $controller['relays'];
        $running = $controller['running'];
        if (count($relays) > 0) {
            foreach ($relays as $relay) {
                if ($connector != $relay['relay']) {
                    continue;
                }
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
                $this->MaintainVariable('SuspendUntil', $this->Translate('Suspended until'), IPS_INTEGER, '~UnixTimestamp', $vpos++, $suspended > 0);
                if ($suspended) {
                    $this->SetValue('SuspendUntil', $suspended);
                }

                $run_seconds = $relay['run_seconds'];
                $this->MaintainVariable('Duration', $this->Translate('Duration of run'), IPS_STRING, '', $vpos++, $run_seconds > 0);
                $this->MaintainVariable('Duration_seconds', $this->Translate('Duration of run'), IPS_INTEGER, 'Hydrawise.Duration', $vpos++, $run_seconds > 0);
                if ($run_seconds) {
                    $this->SetValue('Duration', $this->seconds2duration($run_seconds));
                    $this->SetValue('Duration_seconds', $run_seconds);
                }

                $this->SendDebug(__FUNCTION__, "lastwater=$lastwater => $lastrun, nicetime=$nicetime => $nextrun, suspended=$suspended", 0);

				$has_run = false;
				$time_left = 0;
				$water_int = 0;
                foreach ($running as $run) {
                    if ($connector != $run['relay']) {
                        continue;
                    }
                    $time_left = $run['time_left'];
                    $water_usage = $run['water_int'];
					$has_run = true;
                    $this->SendDebug(__FUNCTION__, "time_left=$time_left, water_int=$water_int", 0);
                }

				$this->MaintainVariable('TimeLeft', $this->Translate('Time left'), IPS_STRING, '', $vpos++, $has_run);
				$this->MaintainVariable('WaterUsage', $this->Translate('Water usage'), IPS_FLOAT, 'Hydrawise.Flowmeter', $vpos++, $has_run);
				if ($has_run) {
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

						$this->SetBuffer('currentRun', '');
					}
				}
            }
        }
        $this->SetStatus(102);
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
