<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class HydrawiseIO extends IPSModule
{
    use Hydrawise\StubsCommonLib;
    use HydrawiseLocalLib;

    private static $semaphoreTM = 5 * 1000;

    private $SemaphoreID;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonConstruct(__DIR__);
        $this->SemaphoreID = __CLASS__ . '_' . $InstanceID;
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);
        $this->RegisterPropertyString('api_key', '');

        $this->RegisterPropertyString('host', '');
        $this->RegisterPropertyString('password', '');

        $this->RegisterPropertyBoolean('collectApiCallStats', true);

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->SetBuffer('CustomerDetails', '');
        $this->SetBuffer('ControllerDetails', '');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $api_key = $this->ReadPropertyString('api_key');
        if ($api_key == '') {
            $this->SendDebug(__FUNCTION__, '"api_key" is needed', 0);
            $r[] = $this->Translate('API-Key must be specified');
        }

        $host = $this->ReadPropertyString('host');
        $password = $this->ReadPropertyString('password');
        if ($host != '' && $password == '') {
            $this->SendDebug(__FUNCTION__, '"password" is needed', 0);
            $r[] = $this->Translate('Password must be specified if host ist given');
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

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

        $vpos = 1000;
        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        $this->MaintainMedia('ApiCallStats', $this->Translate('API call statistics'), MEDIATYPE_DOCUMENT, '.txt', false, $vpos++, $collectApiCallStats);

        if ($collectApiCallStats) {
            $apiLimits = [];
            $apiNotes = $this->Translate('30 calls per 5 minutes and not faster than specified in the "nextpoll" field of the response to the "statusschedule" API call');
            $this->ApiCallSetInfo($apiLimits, $apiNotes);
        }

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Hydrawise I/O');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'Label',
                    'caption' => 'API-Key from https://app.hydrawise.com/config/account-details'
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'api_key',
                    'caption' => 'API-Key'
                ],
            ],
            'caption' => 'Hydrawise Access-Details'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'host',
                    'caption' => 'Hostname'
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'password',
                    'caption' => 'Password'
                ],
            ],
            'caption' => 'local Hydrawise-Controller'
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'collectApiCallStats',
            'caption' => 'Collect data of API calls'
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
            'type'    => 'Button',
            'caption' => 'Test account',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "TestAccount", "");',
        ];

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $formActions[] = [
                'type'      => 'ExpansionPanel',
                'caption'   => 'Expert area',
                'expanded'  => false,
                'items'     => [
                    $this->GetApiCallStatsFormItem(),
                ]
            ];
        }

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'TestAccount':
                $this->TestAccount();
                break;
            default:
                $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }
        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }

    public function ForwardData($data)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $callerID = $jdata['CallerID'];
        $this->SendDebug(__FUNCTION__, 'caller=' . $callerID . '(' . IPS_GetName($callerID) . ')', 0);
        $_IPS['CallerID'] = $callerID;

        $ret = '';

        if (isset($jdata['Function'])) {
            switch ($jdata['Function']) {
                case 'CmdUrl':
                    $ret = $this->SendCommand($jdata['Url']);
                    break;
                case 'ClearDailyValue':
                    $sdata = [
                        'DataID'        => '{D957666E-B6E3-A44F-2515-9B5F009ACC2D}', // an HydrawiseSensor, HydrawiseZone
                        'Function'      => $jdata['Function'],
                        'controller_id' => $jdata['controller_id']
                    ];
                    $this->SendDebug(__FUNCTION__, 'SendDataToChildren(' . print_r($sdata, true) . ')', 0);
                    $this->SendDataToChildren(json_encode($sdata));
                    break;
                case 'UpdateController':
                    $controller_id = $jdata['controller_id'];
                    $ret = $this->UpdateControllerData($controller_id);
                    break;
                case 'UpdateLocal':
                    $controller_id = $jdata['controller_id'];
                    $ret = $this->UpdateLocalData($controller_id);
                    break;
                case 'CustomerDetails':
                    $ret = $this->GetCustomerDetails();
                    break;
                case 'ControllerDetails':
                    $controller_id = $jdata['controller_id'];
                    $ret = $this->GetControllerDetails($controller_id);
                    break;
                case 'CollectZoneValues':
                    $controller_id = $jdata['controller_id'];
                    $ret = $this->CollectZoneValues($controller_id);
                    break;
                case 'CollectControllerValues':
                    $controller_id = $jdata['controller_id'];
                    $ret = $this->CollectControllerValues($controller_id);
                    break;
                case 'SetMessage':
                    $sdata = [
                        'DataID'        => '{A800ED12-C177-80A3-A15C-0B6E0052640D}', // an HydrawiseController
                        'Function'      => $jdata['Function'],
                        'msg'           => $jdata['msg'],
                        'controller_id' => $jdata['controller_id']
                    ];
                    $this->SendDebug(__FUNCTION__, 'SendDataToChildren(' . print_r($sdata, true) . ')', 0);
                    $this->SendDataToChildren(json_encode($sdata));
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

    private function TestAccount()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            $msg = $this->GetStatusText();
            $this->PopupMessage($msg);
            return;
        }

        $api_key = $this->ReadPropertyString('api_key');
        $url = 'https://app.hydrawise.com/api/v1/customerdetails.php?api_key=' . $api_key . '&type=controllers';
        $data = $this->do_HttpRequest($url);
        if ($data == '') {
            $txt = $this->Translate('invalid account-data') . PHP_EOL;
            $txt .= PHP_EOL;
        } else {
            $txt = $this->Translate('valid account-data') . PHP_EOL;
            $customer = json_decode($data, true);
            $n_controller = isset($customer['controllers']) ? count($customer['controllers']) : 0;
            $txt .= $n_controller . ' ' . $this->Translate('registered controller found');
        }

        $this->PopupMessage($txt);
    }

    private function SendCommand(string $cmd_url)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
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
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $last_contact = '';
        $status = '';
        $name = '';
        $data = $this->GetCustomerDetails();
        if ($data != '') {
            $jdata = json_decode($data, true);
            $controllers = $this->GetArrayElem($jdata, 'controllers', '');
            if ($controllers != '') {
                foreach ($controllers as $controller) {
                    if ($controller_id == $controller['controller_id']) {
                        $name = $controller['name'];
                        $last_contact = $controller['last_contact'];
                        $status = isset($controller['status']) ? $controller['status'] : 'OK';
                        break;
                    }
                }
            }
        }

        $local_data = '';
        $data = $this->GetLocalData();
        if ($data != '') {
            $local_data = json_decode($data, true);
        }

        $data = $this->GetControllerDetails($controller_id);
        if ($data != '') {
            $jdata = json_decode($data, true);
            $jdata['controller_id'] = $controller_id;
            $jdata['name'] = $name;
            $jdata['last_contact'] = $last_contact;
            $jdata['status'] = $status;
            $jdata['local'] = $local_data;
            $data = json_encode($jdata);
            $sdata = [
                'DataID'  => '{A717FCDD-287E-44BF-A1D2-E2489A4C30B2}', // an HydrawiseSensor, HydrawiseZone
                'AllData' => $data
            ];
            $this->SendDebug(__FUNCTION__, 'SendDataToChildren(' . print_r($sdata, true) . ')', 0);
            $this->SendDataToChildren(json_encode($sdata));
            $this->MaintainStatus(IS_ACTIVE);
            $status = true;
        } else {
            $status = false;
        }

        $ret = json_encode(['status' => $status]);
        $this->SendDebug(__FUNCTION__, 'ret=' . print_r($ret, true), 0);
        return $ret;
    }

    private function UpdateLocalData(string $controller_id)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return false;
        }

        $data = $this->GetLocalData();
        if ($data != '') {
            $jdata = json_decode($data, true);
            $jdata['controller_id'] = $controller_id;
            $data = json_encode($jdata);
            $sdata = [
                'DataID'    => '{A800ED12-C177-80A3-A15C-0B6E0052640D}', // an HydrawiseController
                'LocalData' => $data
            ];
            $this->SendDebug(__FUNCTION__, 'SendDataToChildren(' . print_r($sdata, true) . ')', 0);
            $this->SendDataToChildren(json_encode($sdata));
            $this->MaintainStatus(IS_ACTIVE);
            $status = true;
        } else {
            $status = false;
        }

        $ret = json_encode(['status' => $status]);
        $this->SendDebug(__FUNCTION__, 'ret=' . print_r($ret, true), 0);
        return $ret;
    }

    private function CollectZoneValues(string $controller_id)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $sdata = [
            'DataID'        => '{C424E279-1362-96A6-7D22-B879926BF95F}', // an HydrawiseZone
            'Function'      => 'CollectZoneValues',
            'controller_id' => $controller_id
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToChildren(' . print_r($sdata, true) . ')', 0);
        $responses = $this->SendDataToChildren(json_encode($sdata));
        $this->SendDebug(__FUNCTION__, 'responses=' . print_r($responses, true), 0);
        return json_encode($responses);
    }

    private function CollectControllerValues(string $controller_id)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $sdata = [
            'DataID'        => '{A800ED12-C177-80A3-A15C-0B6E0052640D}', // an HydrawiseController
            'Function'      => 'CollectControllerValues',
            'controller_id' => $controller_id
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToChildren(' . print_r($sdata, true) . ')', 0);
        $responses = $this->SendDataToChildren(json_encode($sdata));
        $this->SendDebug(__FUNCTION__, 'responses=' . print_r($responses, true), 0);
        return json_encode($responses);
    }

    private function GetControllerDetails(string $controller_id)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return false;
        }

        $controllerDetails = json_decode($this->GetBuffer('ControllerDetails'), true);
        $this->SendDebug(__FUNCTION__, 'old controllerDetails=' . print_r($controllerDetails, true), 0);
        if ($controllerDetails == false) {
            $controllerDetails = [
                'data'   => '',
                'last'   => 0,
                'next'   => 0,
            ];
        }
        $now = time();
        $last = $controllerDetails['last'];
        $next = $controllerDetails['next'];
        $this->SendDebug(__FUNCTION__, 'now=' . date('d.m.Y H:i:s', $now) . ', last=' . date('d.m.Y H:i:s', $last) . ', next=' . date('d.m.Y H:i:s', $next), 0);
        if ($now > $next) {
            $this->SendDebug(__FUNCTION__, 'call api', 0);
            $api_key = $this->ReadPropertyString('api_key');
            $url = 'https://app.hydrawise.com/api/v1/statusschedule.php?api_key=' . $api_key . '&controller_id=' . $controller_id;
            $data = $this->do_HttpRequest($url);
            if ($this->GetStatus() == self::$IS_TOOMANYREQUESTS) {
                $next = $now + 60 * 10;
            } else {
                $next = $now + 60 * 5;
            }
            if ($data != '') {
                $controllerDetails['data'] = $data;
                $jdata = json_decode($data, true);
                if (isset($jdata['nextpoll'])) {
                    $next = $now + $jdata['nextpoll'] + 1;
                }
            }
            $controllerDetails['last'] = $now;
            $controllerDetails['next'] = $next;
            $this->SendDebug(__FUNCTION__, 'status=' . $this->GetStatusText() . ', next=' . date('d.m.Y H:i:s', $next), 0);
            $this->SendDebug(__FUNCTION__, 'new controllerDetails=' . print_r($controllerDetails, true), 0);
            $this->SetBuffer('ControllerDetails', json_encode($controllerDetails));
        } else {
            $this->SendDebug(__FUNCTION__, 'from cache', 0);
        }
        $data = $controllerDetails['data'];
        $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);
        return $data;
    }

    private function GetCustomerDetails()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $customerDetails = json_decode($this->GetBuffer('CustomerDetails'), true);
        $this->SendDebug(__FUNCTION__, 'old customerDetails=' . print_r($customerDetails, true), 0);
        if ($customerDetails == false) {
            $customerDetails = [
                'data'   => '',
                'last'   => 0,
                'next'   => 0,
            ];
        }
        $now = time();
        $last = $customerDetails['last'];
        $next = $customerDetails['next'];
        $this->SendDebug(__FUNCTION__, 'now=' . date('d.m.Y H:i:s', $now) . ', last=' . date('d.m.Y H:i:s', $last) . ', next=' . date('d.m.Y H:i:s', $next), 0);
        if ($now > $next) {
            $this->SendDebug(__FUNCTION__, 'call api', 0);
            $api_key = $this->ReadPropertyString('api_key');
            $url = 'https://app.hydrawise.com/api/v1/customerdetails.php?api_key=' . $api_key . '&type=controllers';
            $data = $this->do_HttpRequest($url);
            if ($data != '') {
                $customerDetails['data'] = $data;
            }
            $customerDetails['last'] = $now;
            if ($this->GetStatus() == self::$IS_TOOMANYREQUESTS) {
                $customerDetails['next'] = $now + 60 * 15;
            } else {
                $customerDetails['next'] = $now + 60 * 10;
            }
            $this->SendDebug(__FUNCTION__, 'status=' . $this->GetStatusText() . ', next=' . date('d.m.Y H:i:s', $next), 0);

            $this->SendDebug(__FUNCTION__, 'new customerDetails=' . print_r($customerDetails, true), 0);
            $this->SetBuffer('CustomerDetails', json_encode($customerDetails));
        } else {
            $this->SendDebug(__FUNCTION__, 'from cache', 0);
        }
        $data = $customerDetails['data'];
        $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);
        return $data;
    }

    private function do_HttpRequest($url)
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

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
        $this->SendDebug(__FUNCTION__, '    cdata=' . $cdata, 0);

        $statuscode = 0;
        $err = '';
        $data = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200) {
            if ($httpcode == 400 || $httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode == 429) {
                $statuscode = self::$IS_TOOMANYREQUESTS;
                $err = 'got http-code ' . $httpcode . ' (too many requests)';
            } else {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
        } elseif ($cdata == '') {
            $statuscode = self::$IS_INVALIDDATA;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            } elseif (isset($jdata['error_msg'])) {
                $err = $jdata['error_msg'];
                $statuscode = $err == 'unauthorised' ? self::$IS_UNAUTHORIZED : self::$IS_INVALIDDATA;
            } else {
                $data = $cdata;
            }
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
        }

        IPS_SemaphoreLeave($this->SemaphoreID);

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        return $data;
    }

    private function GetLocalData()
    {
        $host = $this->ReadPropertyString('host');
        if ($host == '') {
            return false;
        }

        $password = $this->ReadPropertyString('password');

        $url = 'http://' . $host . '/status';

        $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);
        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, "admin:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
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
        $this->SendDebug(__FUNCTION__, '    cdata=' . $cdata, 0);

        $statuscode = 0;
        $err = '';
        $data = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200) {
            if ($httpcode == 400 || $httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } else {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
        } elseif ($cdata == '') {
            $statuscode = self::$IS_INVALIDDATA;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            } else {
                $data = $cdata;
            }
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
        }
        $this->SendDebug(__FUNCTION__, ' => data=' . $data, 0);

        return $data;
    }
}
