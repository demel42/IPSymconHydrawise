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

class HydrawiseSensor extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('controller_id', '');
        $this->RegisterPropertyInteger('channel', -1);
        $this->RegisterPropertyInteger('mode', -1);

		$this->CreateVarProfile('Hydrawise.Flowmeter', IPS_FLOAT, ' l', 0, 0, 0, 1, '');

        $this->ConnectParent('{5927E05C-82D0-4D78-B8E0-A973470A9CD3}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

		$mode = $this->ReadPropertyInteger('mode');

        $vpos = 1;
		$this->MaintainVariable('Flow', $this->Translate('Flow'), IPS_FLOAT, 'Hydrawise.Flowmeter', $vpos++, $mode == 30);

        $this->SetStatus(102);
    }

    public function GetConfigurationForm()
    {
        $formElements = [];
        $formElements[] = ['type' => 'Label', 'label' => 'Hydrawise Sensor'];
		$formElements[] = ['type' => 'ValidationTextBox', 'name' => 'controller_id', 'caption' => 'controller_id'];
		$formElements[] = ['type' => 'ValidationTextBox', 'name' => 'channel', 'caption' => 'channel'];
		$formElements[] = ['type' => 'ValidationTextBox', 'name' => 'mode', 'caption' => 'mode'];

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
        $channel = $this->ReadPropertyInteger('channel');
        $mode = $this->ReadPropertyInteger('mode');

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

		$sensors = $controller['sensors'];
		if (count($sensors) > 0) {
			foreach ($sensors as $i => $value) {
				$sensor = $sensors[$i];
				if ($channel != $sensor['input'])
					continue;
				if ($mode == 30) {
					if (isset($sensor['flow']['week'])) {
						$flow = preg_replace('/^([0-9\.,]*).*$/', '$1', $sensor['flow']['week']);
					} else {
						$flow = 0;
					}
					$this->SetValue('Flow', $flow);
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

}
