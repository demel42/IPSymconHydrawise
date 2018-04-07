<?php

class HydrawiseConfig extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->ConnectParent('{5927E05C-82D0-4D78-B8E0-A973470A9CD3}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SetStatus(102);
    }

    public function GetConfigurationForm()
    {
        $SendData = ['DataID' => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}'];
        $data = $this->SendDataToParent(json_encode($SendData));

        $this->SendDebug(__FUNCTION__, "data=$data", 0);

        $options = [];
        if ($data != '') {
            $controllers = json_decode($data, true);
            foreach ($controllers as $controller) {
                $controller_name = $controller['name'];
                $controller_id = $controller['controller_id'];
                $options[] = ['label' => $controller_name, 'value' => $controller_id];
            }
        } else {
            $this->SetStatus(201);
        }

        $formActions = [];
        $formActions[] = ['type' => 'Label', 'label' => 'Controller-Name only needs to be selected if you have more then one'];
        $formActions[] = ['type' => 'Select', 'name' => 'controller_id', 'caption' => 'Controller-Name', 'options' => $options];
        $formActions[] = ['type' => 'Button', 'label' => 'Import of controller', 'onClick' => 'HydrawiseConfig_Doit($id, $controller_id);'];

        $formStatus = [];
        $formStatus[] = ['code' => '101', 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => '102', 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => '104', 'icon' => 'inactive', 'caption' => 'Instance is inactive'];

        $formStatus[] = ['code' => '201', 'icon' => 'error', 'caption' => 'Instance is inactive (no data)'];
        $formStatus[] = ['code' => '202', 'icon' => 'error', 'caption' => 'Instance is inactive (controller missing)'];
        $formStatus[] = ['code' => '203', 'icon' => 'error', 'caption' => 'Instance is inactive (no controller)'];
        $formStatus[] = ['code' => '204', 'icon' => 'error', 'caption' => 'Instance is inactive (more then one controller)'];

        return json_encode(['actions' => $formActions, 'status' => $formStatus]);
    }

    private function FindOrCreateInstance($guid, $controller_id, $channel, $name, $info, $properties, $pos)
    {
        $instID = '';

        $instIDs = IPS_GetInstanceListByModuleID($guid);
        foreach ($instIDs as $id) {
            $cfg = IPS_GetConfiguration($id);
            $jcfg = json_decode($cfg, true);
            if (!isset($jcfg['controller_id'])) {
                continue;
            }
            if ($jcfg['controller_id'] == $controller_id) {
                if ($channel == '' || jcfg['channel'] == $channel) {
                    $instID = $id;
                    break;
                }
            }
        }

        if ($instID == '') {
            $instID = IPS_CreateInstance($guid);
            if ($instID == '') {
                echo 'unable to create instance "' . $name . '"';
                return $instID;
            }
            IPS_SetProperty($instID, 'controller_id', $controller_id);
            if (is_numeric($channel)) {
                IPS_SetProperty($instID, 'channel', $channel);
            }
            foreach ($properties as $key => $property) {
                IPS_SetProperty($instID, $key, $property);
            }
            IPS_SetName($instID, $name);
            IPS_SetInfo($instID, $info);
            IPS_SetPosition($instID, $pos);
        }

        $this->SetSummary($info);
        IPS_ApplyChanges($instID);

        return $instID;
    }

    public function Doit(string $controller_id)
    {
        $SendData = ['DataID' => '{B54B579C-3992-4C1D-B7A8-4A129A78ED03}'];
        $data = $this->SendDataToParent(json_encode($SendData));

        $this->SendDebug(__FUNCTION__, "data=$data", 0);

        $statuscode = 0;
        $do_abort = false;

        if ($data != '') {
            $controllers = json_decode($data, true);
            if ($controller_id != '') {
                $controller_found = false;
                foreach ($controllers as $controller) {
                    if ($controller_id == $controller['controller_id']) {
                        $controller_found = true;
                        break;
                    }
                }
                if (!$controller_found) {
                    $err = "controller \"$controller_id\" don't exists";
                    $statuscode = 202;
                }
            } else {
                switch (count($controllers)) {
                    case 1:
                        $controller = $controllers[0];
                        $controller_id = $controller['controller_id'];
                        break;
                    case 0:
                        $err = 'data contains no controller';
                        $statuscode = 203;
                        break;
                    default:
                        $err = 'data contains to many controller';
                        $statuscode = 204;
                        break;
                }
            }
            if ($statuscode) {
                echo "statuscode=$statuscode, err=$err";
                $this->SendDebug(__FUNCTION__, $err, 0);
                $this->SetStatus($statuscode);
                $do_abort = true;
            }
        } else {
            $err = 'no data';
            $statuscode = 201;
            echo "statuscode=$statuscode, err=$err";
            $this->SendDebug(__FUNCTION__, $err, 0);
            $this->SetStatus($statuscode);
            $do_abort = true;
        }

        if ($do_abort) {
            return -1;
        }

        $this->SetStatus(102);

        $this->SendDebug(__FUNCTION__, 'controller=' . print_r($controller, true), 0);

        // HydrawiseController: '{B1B47A68-CE20-4887-B00C-E6412DAD2CFB}'
        $name = $controller['name'];
        $info = 'Controller (' . $name . ')';
        $properties = [];

        $pos = 1000;
        $instID = $this->FindOrCreateInstance('{B1B47A68-CE20-4887-B00C-E6412DAD2CFB}', $controller_id, '', $name, $info, $properties, $pos++);

        // Instanzen anlegen
        // HydrawiseZone: '{6A0DAE44-B86A-4D50-A76F-532365FD88AE}'

        // HydrawiseSensor: '{56D9EFA4-8840-4DAE-A6D2-ECE8DC862874}'
        $pos = 1100;
        $sensors = $controller['sensors'];
        if (count($sensors) > 0) {
            foreach ($sensors as $i => $value) {
                $sensor = $sensors[$i];
                $channel = $sensor['input'];
                $name = $sensor['name'];
                $type = $sensor['type'];
                $mode = $sensor['mode'];

                // type=1, mode=1 => normally close - start
                // type=1, mode=2 => normally open - stop
                // type=1, mode=3 => normally close - stop
                // type=1, mode=4 => normally open - start
                // type=3, mode=0 => flow meter

                if ($type == 1 && $mode == 1) {
                    $sensor_mode = 11; // normally close - start
                } elseif ($type == 1 && $mode == 2) {
                    $sensor_mode = 12; // normally open - stop
                } elseif ($type == 1 && $mode == 3) {
                    $sensor_mode = 13; // normally close - stop
                } elseif ($type == 1 && $mode == 4) {
                    $sensor_mode = 14; // normally close - start
                } elseif ($type == 3 && $mode == 0) {
                    $sensor_mode = 30; // flow meter
                } else {
                    $sensor_mode = '';
                }

                if ($sensor_mode != '') {
                    $properties = ['mode' => $sensor_mode];
                    $instID = $this->FindOrCreateInstance('{56D9EFA4-8840-4DAE-A6D2-ECE8DC862874}', $controller_id, $channel, $name, $info, $properties, $pos++);
                }
            }
        }
    }
}
