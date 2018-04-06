<?php

class HydrawiseConfig extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->ConnectParent('{26A55798-5CBC-88F6-5C7B-370B043B24F9}');
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

    private function FindOrCreateInstance($guid, $module_id, $module_name, $module_info, $properties, $pos)
    {
        $instID = '';

        $instIDs = IPS_GetInstanceListByModuleID($guid);
        foreach ($instIDs as $id) {
            $cfg = IPS_GetConfiguration($id);
            $jcfg = json_decode($cfg, true);
            if (!isset($jcfg['module_id'])) {
                continue;
            }
            if ($jcfg['module_id'] == $module_id) {
                $instID = $id;
                break;
            }
        }

        if ($instID == '') {
            $instID = IPS_CreateInstance($guid);
            if ($instID == '') {
                echo 'unablte to create instance "' . $module_name . '"';
                return $instID;
            }
            IPS_SetProperty($instID, 'module_id', $module_id);
            foreach ($properties as $key => $property) {
                IPS_SetProperty($instID, $key, $property);
            }
            IPS_SetName($instID, $module_name);
            IPS_SetInfo($instID, $module_info);
            IPS_SetPosition($instID, $pos);
        }

        $this->SetSummary($module_info);
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
            $this->SendDebug(__FUNCTION__, 'controllers=' . print_r($controllers, true), 0);

            $controllers = $netatmo['body']['controllers'];
            $this->SendDebug(__FUNCTION__, 'controllers=' . print_r($controllers, true), 0);

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

		// Instanzen anlegen
		// HydrawiseController: '{B1B47A68-CE20-4887-B00C-E6412DAD2CFB}'
		// HydrawiseZone: '{6A0DAE44-B86A-4D50-A76F-532365FD88AE}'
		// HydrawiseSensor: '{56D9EFA4-8840-4DAE-A6D2-ECE8DC862874}'
    }
}
