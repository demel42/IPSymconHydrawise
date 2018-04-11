<?php

// Constants will be defined with IP-Symcon 5.0 and newer
if (!defined('IPS_KERNELMESSAGE')) {
    define('IPS_KERNELMESSAGE', 10100);
}
if (!defined('KR_READY')) {
    define('KR_READY', 10103);
}
class HydrawiseIO extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('api_key', '');

        $this->RegisterPropertyInteger('UpdateDataInterval', '5');

        $this->RegisterTimer('UpdateData', 0, 'HydrawiseIO_UpdateData(' . $this->InstanceID . ');');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $api_key = $this->ReadPropertyString('api_key');

        if ($api_key != '') {
            // Inspired by module SymconTest/HookServe
            // We need to call the RegisterHook function on Kernel READY
            $this->RegisterMessage(0, IPS_KERNELMESSAGE);
            $this->SetUpdateInterval();
            $this->SetStatus(102);
        } else {
            $this->SetStatus(104);
        }
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
        $min = $this->ReadPropertyInteger('UpdateDataInterval');
        $msec = $min > 0 ? $min * 1000 * 60 : 0;
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
					$this->UpdateData();
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
        $api_key = $this->ReadPropertyString('api_key');

        $url = 'https://app.hydrawise.com/api/v1/statusschedule.php?api_key=' . $api_key . '&tag=hydrawise_all';

        $do_abort = false;
        $data = $this->do_HttpRequest($url);
        if ($data != '') {
            $jdata = json_decode($data);
            // wenn man mehrere Controller hat, ist es ein array, wenn es nur einen Controller gibt, leider nicht
            if (!is_array($jdata)) {
                $controllers = [];
                $controllers[] = $jdata;
                $data = json_encode($controllers);
            }
        } else {
            $do_abort = true;
        }

        if ($do_abort) {
            $this->SendData('');
            $this->SetBuffer('LastData', '');

            return -1;
        }

        $this->SetStatus(102);

        $this->SendData($data);
        $this->SetBuffer('LastData', $data);
    }

    public function SendCommand(string $cmd_url)
    {
        $api_key = $this->ReadPropertyString('api_key');

		$url = "https://app.hydrawise.com/api/v1/setzone.php?api_key=$api_key&" . $cmd_url;

		$ret = '';
		$data = $this->do_HttpRequest($url);
        if ($data != '') {
            $jdata = json_decode($data);
			// cdata={"message":"Resuming scheduled watering for zone Gef\u00e4\u00dfe (Beet)","message_type":"info"}, httpcode=200
			// cdata={"message":"Invalid operation requested. Please contact Hydrawise.","message_type":"error"}, httpcode=200
			// cdata={"error_msg":"unauthorised"}, httpcode=200
        }

        return $ret;
	}

    private function do_HttpRequest($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
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
}
