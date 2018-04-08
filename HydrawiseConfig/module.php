<?php

// Model of Sensor
if (!defined('SENSOR_NORMALLY_CLOSE_START')) {
    define('SENSOR_NORMALLY_CLOSE_START', 11);
}
if (!defined('SENSOR_NORMALLY_OPEN_STOP')) {
    define('SENSOR_NORMALLY_OPEN_STOP', 12);
}
if (!defined('SENSOR_NORMALLY_CLOSE_STOP')) {
    define('SENSOR_NORMALLY_CLOSE_STOP', 13);
}
if (!defined('SENSOR_NORMALLY_OPEN_START')) {
    define('SENSOR_NORMALLY_OPEN_START', 14);
}
if (!defined('SENSOR_FLOW_METER')) {
    define('SENSOR_FLOW_METER', 30);
}

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

    private function FindOrCreateInstance($guid, $controller_id, $connector, $name, $info, $properties, $pos)
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
                if ($connector == '' || $jcfg['connector'] == $connector) {
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
            if (is_numeric($connector)) {
                IPS_SetProperty($instID, 'connector', $connector);
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

        // HydrawiseController
        $controller_name = $controller['name'];
        $info = 'Controller (' . $controller_name . ')';
        $properties = [];

        $pos = 1000;
        $instID = $this->FindOrCreateInstance('{B1B47A68-CE20-4887-B00C-E6412DAD2CFB}', $controller_id, '', $controller_name, $info, $properties, $pos++);

        // HydrawiseSensor
        $pos = 1100;
        $sensors = $controller['sensors'];
        if (count($sensors) > 0) {
            foreach ($sensors as $i => $value) {
                $sensor = $sensors[$i];
                $connector = $sensor['input'] + 1;
                $sensor_name = $sensor['name'];
                $type = $sensor['type'];
                $mode = $sensor['mode'];

                // type=1, mode=1 => normally close - start
                // type=1, mode=2 => normally open - stop
                // type=1, mode=3 => normally close - stop
                // type=1, mode=4 => normally open - start
                // type=3, mode=0 => flow meter

                if ($type == 1 && $mode == 1) {
                    $model = SENSOR_NORMALLY_CLOSE_START;
                } elseif ($type == 1 && $mode == 2) {
                    $model = SENSOR_NORMALLY_OPEN_STOP;
                } elseif ($type == 1 && $mode == 3) {
                    $model = SENSOR_NORMALLY_CLOSE_STOP;
                } elseif ($type == 1 && $mode == 4) {
                    $model = SENSOR_NORMALLY_OPEN_START;
                } elseif ($type == 3 && $mode == 0) {
                    $model = SENSOR_FLOW_METER;
                } else {
                    continue;
                }

                $info = 'Sensor ' . ($connector + 1) . ' (' . $controller_name . '\\' . $sensor_name . ')';
                $properties = ['model' => $model];
                $instID = $this->FindOrCreateInstance('{56D9EFA4-8840-4DAE-A6D2-ECE8DC862874}', $controller_id, $connector, $sensor_name, $info, $properties, $pos++);
            }
        }

        $pos = 1200;
        $relays = $controller['relays'];
        if (count($relays) > 0) {
            foreach ($relays as $i => $value) {
                $relay = $relays[$i];
                $connector = $relay['relay'];
                $zone_name = $relay['name'];
                if ($connector < 100) {
                    $info = 'Zone ' . $connector;
                } else {
                    $info = 'Expander ' . floor($connector / 100) . ' Zone ' . ($connector % 100);
                }
                $info .= ' (' . $controller_name . '\\' . $zone_name . ')';
                $properties = [];
                $instID = $this->FindOrCreateInstance('{6A0DAE44-B86A-4D50-A76F-532365FD88AE}', $controller_id, $connector, $zone_name, $info, $properties, $pos++);
            }
        }
    }
}
