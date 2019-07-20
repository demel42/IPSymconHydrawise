<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/library.php';  // modul-bezogene Funktionen

class HydrawiseIO extends IPSModule
{
    use HydrawiseCommon;
    use HydrawiseLibrary;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('api_key', '');

        $this->RegisterPropertyInteger('UpdateDataInterval', '60');
        $this->RegisterPropertyInteger('ignore_http_error', '0');

        $this->RegisterTimer('UpdateData', 0, 'HydrawiseIO_UpdateData(' . $this->InstanceID . ');');
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        $api_key = $this->ReadPropertyString('api_key');
        if ($api_key != '') {
            $this->SetUpdateInterval();
            // Inspired by module SymconTest/HookServe
            // We need to call the RegisterHook function on Kernel READY
            if (IPS_GetKernelRunlevel() == KR_READY) {
                $this->UpdateData();
            }
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetStatus(IS_INACTIVE);
        }
    }

    public function GetConfigurationForm()
    {
        $formElements = [];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'module_disable', 'caption' => 'Instance is disabled'];
        $formElements[] = ['type' => 'Label', 'label' => 'Hydrawise Access-Details'];
        $formElements[] = ['type' => 'Label', 'label' => 'API-Key from https://app.hydrawise.com/config/account'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'api_key', 'caption' => 'API-Key'];
        $formElements[] = ['type' => 'Label', 'label' => 'Ignore HTTP-Error X times'];
        $formElements[] = ['type' => 'NumberSpinner', 'name' => 'ignore_http_error', 'caption' => 'Count'];
        $formElements[] = ['type' => 'Label', 'label' => ''];
        $formElements[] = ['type' => 'Label', 'label' => 'Update data every X seconds'];
        $formElements[] = ['type' => 'NumberSpinner', 'name' => 'UpdateDataInterval', 'caption' => 'Seconds'];

        $formActions = [];
        $formActions[] = ['type' => 'Button', 'label' => 'Update Data', 'onClick' => 'HydrawiseIO_UpdateData($id);'];
        $formActions[] = ['type' => 'Label', 'label' => '____________________________________________________________________________________________________'];
        $formActions[] = ['type' => 'Button', 'label' => 'Module description', 'onClick' => 'echo \'https://github.com/demel42/IPSymconHydrawise/blob/master/README.md\';'];

        $formStatus = [];
        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        $formStatus[] = ['code' => IS_NODATA, 'icon' => 'error', 'caption' => 'Instance is inactive (no data)'];
        $formStatus[] = ['code' => IS_NOCONROLLER, 'icon' => 'error', 'caption' => 'Instance is inactive (no controller)'];
        $formStatus[] = ['code' => IS_CONTROLLER_MISSING, 'icon' => 'error', 'caption' => 'Instance is inactive (controller missing)'];
        $formStatus[] = ['code' => IS_ZONE_MISSING, 'icon' => 'error', 'caption' => 'Instance is inactive (zone missing)'];

        return json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
    }

    // Inspired by module SymconTest/HookServe
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->UpdateData();
        }
    }

    protected function SetUpdateInterval()
    {
        $sec = $this->ReadPropertyInteger('UpdateDataInterval');
        $msec = $sec > 0 ? $sec * 1000 : 0;
        $this->SetTimerInterval('UpdateData', $msec);
    }

    protected function SendData($buf)
    {
        $data = ['DataID' => '{A717FCDD-287E-44BF-A1D2-E2489A4C30B2}', 'Buffer' => $buf];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode($data));
    }

    public function ForwardData($data)
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $jdata = json_decode($data);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $ret = '';

        if (isset($jdata->Function)) {
            switch ($jdata->Function) {
                case 'LastData':
                    $ret = $this->GetBuffer('LastData');
                    break;
                case 'CmdUrl':
                    $ret = $this->SendCommand($jdata->Url);
                    $this->SetTimerInterval('UpdateData', 500);
                    break;
                case 'ClearDailyValue':
                    $data = ['DataID' => '{A717FCDD-287E-44BF-A1D2-E2489A4C30B2}', 'Function' => 'ClearDailyValue', 'controller_id' => $jdata->controller_id];
                    $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
                    $this->SendDataToChildren(json_encode($data));
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

    public function UpdateData()
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $api_key = $this->ReadPropertyString('api_key');

        $do_abort = false;

		$url = 'https://app.hydrawise.com/api/v1/customerdetails.php?api_key=' . $api_key . '&type=controllers';;
        $data = $this->do_HttpRequest($url);
        if ($data != '') {
			$all_controllers = [];
            $jdata = json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
			$controllers = $jdata['controllers'];
            $this->SendDebug(__FUNCTION__, 'controllers=' . print_r($controllers, true), 0);
			foreach ($controllers as $controller) {
				$controller_id = $controller['controller_id'];
				$url = 'https://app.hydrawise.com/api/v1/statusschedule.php?api_key=' . $api_key . '&controller_id=' . $controller_id;
				$data = $this->do_HttpRequest($url);
				if ($data != '') {
					$all_controllers[] = json_decode($data);
				} else {
					$do_abort = true;
					break;
				}
			}
		} else {
            $do_abort = true;
        }

        if ($do_abort) {
            $this->SetBuffer('LastData', '');
            return;
        }

		$data = json_encode($all_controllers);

        $this->SetStatus(IS_ACTIVE);

        $this->SendData($data);
        $this->SetBuffer('LastData', $data);

        $this->SetUpdateInterval();
    }

    public function SendCommand(string $cmd_url)
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return false;
        }

        $api_key = $this->ReadPropertyString('api_key');

        $url = "https://app.hydrawise.com/api/v1/setzone.php?api_key=$api_key&" . $cmd_url;

        $ret = '';

        $data = $this->do_HttpRequest($url);
        $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);

        if ($data != '') {
            $jdata = json_decode($data, true);
            if (isset($jdata['error_msg'])) {
                $status = false;
                $msg = $jdata['error_msg'];
            } elseif (isset($jdata['message_type'])) {
                $mtype = $jdata['message_type'];
                $status = $mtype == 'error';
                $msg = $jdata['message'];
            } else {
                $status = false;
                $msg = 'unknown';
            }
        } else {
            $status = false;
            $msg = 'no data';
        }

        $ret = json_encode(['status' => $status, 'msg' => $msg]);
        $this->SendDebug(__FUNCTION__, 'ret=' . print_r($ret, true), 0);
        return $ret;
    }

    private function do_HttpRequest($url)
    {
        $ignore_http_error = $this->ReadPropertyInteger('ignore_http_error');

        $this->SendDebug(__FUNCTION__, 'http-get: url=' . $url, 0);
        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $cdata = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        $data = '';
        if ($cerrno) {
            $statuscode = IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200) {
            if ($httpcode == 400 || $httpcode == 401) {
                $statuscode = IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } else {
                $statuscode = IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
        } elseif ($cdata == '') {
            $statuscode = IS_INVALIDDATA;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = IS_INVALIDDATA;
                $err = 'malformed response';
            } else {
                $data = $cdata;
            }
        }

        if ($statuscode) {
            $cstat = $this->GetBuffer('LastStatus');
            if ($cstat != '') {
                $jstat = json_decode($cstat, true);
            } else {
                $jstat = [];
            }
            $jstat[] = ['statuscode' => $statuscode, 'err' => $err, 'tstamp' => time()];
            $n_stat = count($jstat);
            $cstat = json_encode($jstat);
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err . ', status #' . $n_stat, KL_WARNING);

            if ($n_stat >= $ignore_http_error) {
                $this->SetStatus($statuscode);
                $cstat = '';
            }
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err . ', status #' . $n_stat, 0);
        } else {
            $cstat = '';
        }
        $this->SetBuffer('LastStatus', $cstat);

        return $data;
    }
}
