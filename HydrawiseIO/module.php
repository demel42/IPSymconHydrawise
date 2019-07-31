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
        $this->RegisterPropertyInteger('ignore_http_error', '0');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        $api_key = $this->ReadPropertyString('api_key');
        if ($api_key == '') {
            $this->SetStatus(IS_INVALIDCONFIG);
            return;
        }

        $this->SetStatus(IS_ACTIVE);
    }

    protected function GetFormActions()
    {
        $formActions = [];
        $formActions[] = ['type' => 'Button', 'label' => 'Test account', 'onClick' => 'Hydrawise_TestAccount($id);'];
        $formActions[] = ['type' => 'Label', 'label' => '____________________________________________________________________________________________________'];
        $formActions[] = [
                            'type'    => 'Button',
                            'caption' => 'Module description',
                            'onClick' => 'echo "https://github.com/demel42/IPSymconHydrawise/blob/master/README.md";'
                        ];

        return $formActions;
    }

    public function GetFormElements()
    {
        $formElements = [];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'module_disable', 'caption' => 'Instance is disabled'];

        $items = [];
        $items[] = ['type' => 'Label', 'label' => 'API-Key from https://app.hydrawise.com/config/account'];
        $items[] = ['type' => 'ValidationTextBox', 'name' => 'api_key', 'caption' => 'API-Key'];
        $formElements[] = ['type' => 'ExpansionPanel', 'items' => $items, 'caption' => 'Hydrawise Access-Details'];

        $items = [];
        $items[] = ['type' => 'NumberSpinner', 'name' => 'ignore_http_error', 'caption' => 'Ignore HTTP-Error X times', 'suffix' => 'Count'];
        $formElements[] = ['type' => 'ExpansionPanel', 'items' => $items, 'caption' => 'Communication'];

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

    protected function SendData($data)
    {
        $data['DataID'] = '{A717FCDD-287E-44BF-A1D2-E2489A4C30B2}';
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

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $ret = '';

        if (isset($jdata['Function'])) {
            switch ($jdata['Function']) {
                case 'CmdUrl':
                    $ret = $this->SendCommand($jdata['Url']);
                    break;
                case 'ClearDailyValue':
                    $data = ['Function' => $jdata['Function'], 'controller_id' => $jdata['controller_id']];
					$this->SendData($data);
                    break;
                case 'UpdateController':
                    $controller_id = $jdata['controller_id'];
                    $ret = $this->UpdateControllerData($controller_id);
                    break;
                case 'CustomerDetails':
                    $ret = $this->GetCustomerDetails();
                    break;
                case 'ControllerDetails':
                    $controller_id = $jdata['controller_id'];
                    $ret = $this->GetControllerDetails($controller_id);
                    break;
                case 'SetMessage':
                    $data = ['Function' => $jdata['Function'], 'msg' => $jdata['msg'], 'controller_id' => $jdata['controller_id']];
					$this->SendData($data);
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'unknown function "' . $jdata['Function'] . '"', 0);
                    break;
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown message-structure', 0);
        }

        $this->SendDebug(__FUNCTION__, 'ret=' . print_r($ret, true), 0);
        return $ret;
    }

    public function TestAccount()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $api_key = $this->ReadPropertyString('api_key');
        $url = 'https://app.hydrawise.com/api/v1/customerdetails.php?api_key=' . $api_key . '&type=controllers';
        $data = $this->do_HttpRequest($url);
        if ($data == '') {
            $txt .= $this->translate('invalid account-data') . PHP_EOL;
            $txt .= PHP_EOL;
        } else {
            $txt = $this->translate('valid account-data') . PHP_EOL;
            $customer = json_decode($data, true);
            $n_controller = isset($customer['controllers']) ? count($customer['controllers']) : 0;
            $txt .= $n_controller . ' ' . $this->Translate('registered controller found');
        }

        echo $txt;
    }

    private function SendCommand(string $cmd_url)
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return false;
        }

        $api_key = $this->ReadPropertyString('api_key');
        $url = "https://app.hydrawise.com/api/v1/setzone.php?api_key=$api_key&" . $cmd_url;
        $data = $this->do_HttpRequest($url);
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

    private function UpdateControllerData(string $controller_id)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return false;
        }

        $data = $this->GetControllerDetails($controller_id);
        if ($data != '') {
            $this->SendData(['Buffer' => $data]);
            $this->SetStatus(IS_ACTIVE);
            $status = true;
        } else {
            $status = false;
        }

        $ret = json_encode(['status' => $status]);
        $this->SendDebug(__FUNCTION__, 'ret=' . print_r($ret, true), 0);
        return $ret;
    }

    private function GetControllerDetails(string $controller_id)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return false;
        }

        $api_key = $this->ReadPropertyString('api_key');
        $url = 'https://app.hydrawise.com/api/v1/statusschedule.php?api_key=' . $api_key . '&controller_id=' . $controller_id;
        $data = $this->do_HttpRequest($url);
        return $data;
    }

    private function GetCustomerDetails()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $api_key = $this->ReadPropertyString('api_key');
        $url = 'https://app.hydrawise.com/api/v1/customerdetails.php?api_key=' . $api_key . '&type=controllers';
        $data = $this->do_HttpRequest($url);
        return $data;
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
            } elseif ($httpcode == 429) {
                $statuscode = IS_TOOMANYREQUESTS;
                $err = 'got http-code ' . $httpcode . ' (too many requests)';
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
