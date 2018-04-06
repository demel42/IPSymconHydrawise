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

class HydrawiseController extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('controller_id', '');

        $this->RegisterPropertyInteger('minutes2fail', 30);

        $this->RegisterPropertyInteger('statusbox_script', 0);
        $this->RegisterPropertyInteger('webhook_script', 0);

        $this->RegisterPropertyBoolean('with_last_contact', true);
        $this->RegisterPropertyBoolean('with_message', true);
        $this->RegisterPropertyBoolean('with_status_box', false);

		/*
        $this->CreateVarProfile('Hydrawise.Temperatur', IPS_FLOAT, ' °C', -10, 30, 0, 1, 'Temperature');
        $this->CreateVarProfile('Hydrawise.Humidity', IPS_FLOAT, ' %', 10, 100, 0, 0, 'Drops');
        $this->CreateVarProfile('Hydrawise.absHumidity', IPS_FLOAT, ' g/m³', 10, 100, 0, 0, 'Drops');
        $this->CreateVarProfile('Hydrawise.Dewpoint', IPS_FLOAT, ' °C', 0, 30, 0, 0, 'Drops');
        $this->CreateVarProfile('Hydrawise.Heatindex', IPS_FLOAT, ' °C', 0, 100, 0, 0, 'Temperature');
        $this->CreateVarProfile('Hydrawise.Pressure', IPS_FLOAT, ' mbar', 500, 1200, 0, 0, 'Gauge');
        $this->CreateVarProfile('Hydrawise.WindSpeed', IPS_FLOAT, ' km/h', 0, 100, 0, 0, 'WindSpeed');
        $this->CreateVarProfile('Hydrawise.WindStrength', IPS_INTEGER, ' bft', 0, 13, 0, 0, 'WindSpeed');
        $this->CreateVarProfile('Hydrawise.WindAngle', IPS_INTEGER, ' °', 0, 360, 0, 0, 'WindDirection');
        $this->CreateVarProfile('Hydrawise.WindDirection', IPS_STRING, '', 0, 0, 0, 0, 'WindDirection');
        $this->CreateVarProfile('Hydrawise.Rainfall', IPS_FLOAT, ' mm', 0, 60, 0, 1, 'Rainfall');

        $associations = [];
        $associations[] = ['Wert' =>  0, 'Name' => '%d', 'Farbe' => 0x008040];
        $associations[] = ['Wert' => 40, 'Name' => '%d', 'Farbe' => 0xFFFF31];
        $associations[] = ['Wert' => 65, 'Name' => '%d', 'Farbe' => 0xFF8000];
        $associations[] = ['Wert' => 95, 'Name' => '%d', 'Farbe' => 0xFF0000];
        $this->CreateVarProfile('Hydrawise.Noise', IPS_INTEGER, ' dB', 0, 130, 0, 1, 'Speaker', $associations);

        $associations = [];
        $associations[] = ['Wert' =>    0, 'Name' => '%d', 'Farbe' => 0x008000];
        $associations[] = ['Wert' => 1000, 'Name' => '%d', 'Farbe' => 0xFFFF00];
        $associations[] = ['Wert' => 1250, 'Name' => '%d', 'Farbe' => 0xFF8000];
        $associations[] = ['Wert' => 1300, 'Name' => '%d', 'Farbe' => 0xFF0000];
        $this->CreateVarProfile('Hydrawise.CO2', IPS_INTEGER, ' ppm', 250, 2000, 0, 1, 'Gauge', $associations);

        $associations = [];
        $associations[] = ['Wert' => false, 'Name' => 'Nein', 'Farbe' => -1];
        $associations[] = ['Wert' => true, 'Name' => 'Ja', 'Farbe' => 0xEE0000];
        $this->CreateVarProfile('Hydrawise.Alarm', IPS_BOOLEAN, '', 0, 0, 0, 1, 'Alert', $associations);

        $associations = [];
        $associations[] = ['Wert' => 0, 'Name' => $this->wifi_status2text(0), 'Farbe' => 0xEE0000];
        $associations[] = ['Wert' => 1, 'Name' => $this->wifi_status2text(1), 'Farbe' => 0xFFFF00];
        $associations[] = ['Wert' => 2, 'Name' => $this->wifi_status2text(2), 'Farbe' => 0x32CD32];
        $associations[] = ['Wert' => 3, 'Name' => $this->wifi_status2text(3), 'Farbe' => 0x228B22];
        $this->CreateVarProfile('Hydrawise.Wifi', IPS_INTEGER, '', 0, 0, 0, 1, 'Intensity', $associations);

        $associations = [];
        $associations[] = ['Wert' => 0, 'Name' => $this->signal_status2text(0), 'Farbe' => 0xEE0000];
        $associations[] = ['Wert' => 1, 'Name' => $this->signal_status2text(1), 'Farbe' => 0xFFA500];
        $associations[] = ['Wert' => 2, 'Name' => $this->signal_status2text(2), 'Farbe' => 0xFFFF00];
        $associations[] = ['Wert' => 3, 'Name' => $this->signal_status2text(3), 'Farbe' => 0x32CD32];
        $associations[] = ['Wert' => 4, 'Name' => $this->signal_status2text(4), 'Farbe' => 0x228B22];
        $this->CreateVarProfile('Hydrawise.RfSignal', IPS_INTEGER, '', 0, 0, 0, 1, 'Intensity', $associations);

        $associations = [];
        $associations[] = ['Wert' => 0, 'Name' => $this->battery_status2text(0), 'Farbe' => 0xEE0000];
        $associations[] = ['Wert' => 1, 'Name' => $this->battery_status2text(1), 'Farbe' => 0xFFA500];
        $associations[] = ['Wert' => 2, 'Name' => $this->battery_status2text(2), 'Farbe' => 0xFFFF00];
        $associations[] = ['Wert' => 3, 'Name' => $this->battery_status2text(3), 'Farbe' => 0x32CD32];
        $associations[] = ['Wert' => 4, 'Name' => $this->battery_status2text(4), 'Farbe' => 0x228B22];
        $associations[] = ['Wert' => 5, 'Name' => $this->battery_status2text(5), 'Farbe' => 0x228B22];
        $this->CreateVarProfile('Hydrawise.Battery', IPS_INTEGER, '', 0, 0, 0, 1, 'Intensity', $associations);
		*/

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
            $this->RegisterHook('/hook/HydrawiseWeather');
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $with_last_contact = $this->ReadPropertyBoolean('with_last_contact');
        $with_status_box = $this->ReadPropertyBoolean('with_status_box');

        $vpos = 1;

		$this->RegisterVariableBoolean('Status', $this->Translate('State'), '~Alert.Reversed', $vpos);
		$vpos++;

		if ($with_last_contact) {
			$this->RegisterVariableString('Message', $this->Translate('last message'), '', $vpos);
		} else {
			$this->UnregisterVariable('Message');
		}
		$vpos++;

		if ($with_last_contact) {
			$this->RegisterVariableString('LastContact', $this->Translate('last transmission'), '', $vpos);
		} else {
			$this->UnregisterVariable('LastContact');
		}
		$vpos++;

		if ($with_status_box) {
			$this->RegisterVariableString('StatusBox', $this->Translate('State of controller and zones'), '~HTMLBox', $vpos);
		} else {
			$this->UnregisterVariable('StatusBox');
		}
		$vpos++;

		// Inspired by module SymconTest/HookServe
		// Only call this in READY state. On startup the WebHook instance might not be available yet
		if (IPS_GetKernelRunlevel() == KR_READY) {
			$this->RegisterHook('/hook/HydrawiseWeather');
		}

        $this->SetStatus(102);
    }

    public function GetConfigurationForm()
    {
        $formElements = [];
		$formElements[] = ['type' => 'Label', 'label' => 'Hydrawise Controller'];
		$formElements[] = ['type' => 'Label', 'label' => 'optional controller data'];
		$formElements[] = ['type' => 'CheckBox', 'name' => 'with_last_contact', 'caption' => ' ... last transmission to Hydrawise'];
		$formElements[] = ['type' => 'CheckBox', 'name' => 'with_messages', 'caption' => ' ... last message'];
		$formElements[] = ['type' => 'CheckBox', 'name' => 'with_status_box', 'caption' => ' ... html-box with state of controller and zones'];

        $formStatus = [];
        $formStatus[] = ['code' => '101', 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => '102', 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => '104', 'icon' => 'inactive', 'caption' => 'Instance is inactive'];

        $formStatus[] = ['code' => '201', 'icon' => 'error', 'caption' => 'Instance is inactive (no data)'];
        $formStatus[] = ['code' => '202', 'icon' => 'error', 'caption' => 'Instance is inactive (controller missing)'];

        return json_encode(['elements' => $formElements, 'status' => $formStatus]);
    }

    protected function SetValue($Ident, $Value)
    {
        if (IPS_GetKernelVersion() >= 5) {
            parent::SetValue($Ident, $Value);
        } else {
            SetValue($this->GetIDForIdent($Ident), $Value);
        }
    }

    public function ReceiveData($data)
    {
        $jdata = json_decode($data);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);
        $buf = $jdata->Buffer;

        $controller_id = $this->ReadPropertyString('controller_id');
        $with_last_contact = $this->ReadPropertyBoolean('with_last_contact');
        $with_status_box = $this->ReadPropertyBoolean('with_status_box');
        $with_messages = $this->ReadPropertyBoolean('with_messages');
        $minutes2fail = $this->ReadPropertyInteger('minutes2fail');

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

        $now = time();

        $controller_status = true;

        $controller_name = $controller['name'];

		$last_contact = $controller['last_contact'];
               $ts = strtotime($last_contact);
                if ( $ts ) {
						$sec = $now - $ts;
                        $s = $this->seconds2duration($sec);
                        if ( $s != "" )
                                $contact = "vor " . $s;
                        else
                                $contact = "jetzt";

						$s = $this->seconds2duration($sec);
						$min = floor($sec / 60);
						if ($min > $minutes2fail) {
							$controller_status = false;
						}
                } else {
                        $contact = $last_contact;
                }

		$message = $controller['message'];

        $msg = "controller \"$controller_name\": last_contact=$contact";
        $this->SendDebug(__FUNCTION__, utf8_decode($msg), 0);

        if ($with_last_contact) {
            $this->SetValue('LastContact', $contact);
        }

        if ($with_message) {
            $this->SetValue('LastMessages', $message);
        }

		$controller_data=[];
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

    // Inspired from module SymconTest/HookServe
    private function RegisterHook($WebHook)
    {
        $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');
        if (count($ids) > 0) {
            $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
            $found = false;
            foreach ($hooks as $index => $hook) {
                if ($hook['Hook'] == $WebHook) {
                    if ($hook['TargetID'] == $this->InstanceID) {
                        return;
                    }
                    $hooks[$index]['TargetID'] = $this->InstanceID;
                    $found = true;
                }
            }
            if (!$found) {
                $hooks[] = ['Hook' => $WebHook, 'TargetID' => $this->InstanceID];
            }
            IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
            IPS_ApplyChanges($ids[0]);
        }
    }

    private function Build_StatusBox($controller_data)
    {
        $img_path = '/hook/HydrawiseWeather/imgs/';

        $html = '';

        return $html;
    }

    private function ProcessHook_Status()
    {
        $s = $this->GetBuffer('Data');
        $controller_data = json_decode($s, true);

        $html = '';

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
        $basename = substr($uri, strlen('/hook/HydrawiseWeather/'));
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

    // Inspired from module SymconTest/HookServe
    private function GetMimeType($extension)
    {
        $lines = file(IPS_GetKernelDirEx() . 'mime.types');
        foreach ($lines as $line) {
            $type = explode("\t", $line, 2);
            if (count($type) == 2) {
                $types = explode(' ', trim($type[1]));
                foreach ($types as $ext) {
                    if ($ext == $extension) {
                        return $type[0];
                    }
                }
            }
        }
        return 'text/plain';
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
