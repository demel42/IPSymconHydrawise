<?php

declare(strict_types=1);

trait HydrawiseLocalLib
{
    public static $IS_UNAUTHORIZED = IS_EBASE + 10;
    public static $IS_SERVERERROR = IS_EBASE + 11;
    public static $IS_HTTPERROR = IS_EBASE + 12;
    public static $IS_INVALIDDATA = IS_EBASE + 13;
    public static $IS_NODATA = IS_EBASE + 14;
    public static $IS_NOCONROLLER = IS_EBASE + 15;
    public static $IS_CONTROLLER_MISSING = IS_EBASE + 16;
    public static $IS_ZONE_MISSING = IS_EBASE + 17;
    public static $IS_TOOMANYREQUESTS = IS_EBASE + 18;

    private function GetFormStatus()
    {
        $formStatus = $this->GetCommonFormStatus();

        $formStatus[] = ['code' => self::$IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        $formStatus[] = ['code' => self::$IS_NODATA, 'icon' => 'error', 'caption' => 'Instance is inactive (no data)'];
        $formStatus[] = ['code' => self::$IS_NOCONROLLER, 'icon' => 'error', 'caption' => 'Instance is inactive (no controller)'];
        $formStatus[] = ['code' => self::$IS_CONTROLLER_MISSING, 'icon' => 'error', 'caption' => 'Instance is inactive (controller missing)'];
        $formStatus[] = ['code' => self::$IS_ZONE_MISSING, 'icon' => 'error', 'caption' => 'Instance is inactive (zone missing)'];
        $formStatus[] = ['code' => self::$IS_TOOMANYREQUESTS, 'icon' => 'error', 'caption' => 'Instance is inactive (too many requests)'];

        return $formStatus;
    }

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            case self::$IS_UNAUTHORIZED:
            case self::$IS_SERVERERROR:
            case self::$IS_HTTPERROR:
            case self::$IS_NODATA:
            case self::$IS_INVALIDDATA:
                $class = self::$STATUS_RETRYABLE;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    // Sensor-Type
    public static $SENSOR_NORMALLY_CLOSE_START = 11;
    public static $SENSOR_NORMALLY_OPEN_STOP = 12;
    public static $SENSOR_NORMALLY_CLOSE_STOP = 13;
    public static $SENSOR_NORMALLY_OPEN_START = 14;
    public static $SENSOR_FLOW_METER = 30;

    // Zone-Action
    public static $ZONE_ACTION_STOP = -1;
    public static $ZONE_ACTION_DEFAULT = 0;
    public static $ZONE_ACTION_1MIN = 1;
    public static $ZONE_ACTION_2MIN = 2;
    public static $ZONE_ACTION_5MIN = 5;
    public static $ZONE_ACTION_10MIN = 10;
    public static $ZONE_ACTION_15MIN = 15;
    public static $ZONE_ACTION_20MIN = 20;

    public static $ZONE_SUSPEND_CLEAR = -1;
    public static $ZONE_SUSPEND_1DAY = 1;
    public static $ZONE_SUSPEND_2DAY = 2;
    public static $ZONE_SUSPEND_7DAY = 7;

    // aktuelle Zonen-Aktivität
    public static $ZONE_WORKFLOW_SUSPENDED = -1;
    public static $ZONE_WORKFLOW_MANUAL = 0;
    public static $ZONE_WORKFLOW_SOON = 1;
    public static $ZONE_WORKFLOW_SCHEDULED = 2;
    public static $ZONE_WORKFLOW_WATERING = 3;
    public static $ZONE_WORKFLOW_DONE = 4;
    public static $ZONE_WORKFLOW_PARTIALLY = 5;

    // aktueller Zonen-Status
    public static $ZONE_STATUS_SUSPENDED = -1;
    public static $ZONE_STATUS_IDLE = 0;
    public static $ZONE_STATUS_WATERING = 1;

    // Typ der Durchflussmessung
    public static $FLOW_RATE_NONE = 0;
    public static $FLOW_RATE_AVERAGE = 1;
    public static $FLOW_RATE_CURRENT = 2;

    private function InstallVarProfiles(bool $reInstall = false)
    {
        if ($reInstall) {
            $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);
        }

        $associations = [
            ['Wert' => false, 'Name' => $this->Translate('inactive'), 'Farbe' => -1],
            ['Wert' => true, 'Name' => $this->Translate('active'), 'Farbe' => 0xFF5D5D],
        ];
        $this->CreateVarProfile('Hydrawise.RainSensor', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$ZONE_ACTION_STOP, 'Name' => $this->Translate('Stop'), 'Farbe' => 0xEE0000],
            ['Wert' => self::$ZONE_ACTION_DEFAULT, 'Name' => $this->Translate('Default'), 'Farbe' => 0x32CD32],
            ['Wert' => self::$ZONE_ACTION_1MIN, 'Name' => $this->Translate('1 min'), 'Farbe' => -1],
            ['Wert' => self::$ZONE_ACTION_2MIN, 'Name' => $this->Translate('2 min'), 'Farbe' => -1],
            ['Wert' => self::$ZONE_ACTION_5MIN, 'Name' => $this->Translate('5 min'), 'Farbe' => -1],
            ['Wert' => self::$ZONE_ACTION_10MIN, 'Name' => $this->Translate('10 min'), 'Farbe' => -1],
            ['Wert' => self::$ZONE_ACTION_15MIN, 'Name' => $this->Translate('15 min'), 'Farbe' => -1],
            ['Wert' => self::$ZONE_ACTION_20MIN, 'Name' => $this->Translate('20 min'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('Hydrawise.ZoneAction', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$ZONE_SUSPEND_CLEAR, 'Name' => $this->Translate('Clear'), 'Farbe' => 0xEE0000],
            ['Wert' => self::$ZONE_SUSPEND_1DAY, 'Name' => $this->Translate('1 day'), 'Farbe' => -1],
            ['Wert' => self::$ZONE_SUSPEND_2DAY, 'Name' => $this->Translate('2 days'), 'Farbe' => -1],
            ['Wert' => self::$ZONE_SUSPEND_7DAY, 'Name' => $this->Translate('1 week'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('Hydrawise.ZoneSuspend', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$ZONE_WORKFLOW_SUSPENDED, 'Name' => $this->Translate('suspended'), 'Farbe' => 0xFF5D5D],
            ['Wert' => self::$ZONE_WORKFLOW_MANUAL, 'Name' => $this->Translate('manual'), 'Farbe' => 0xC0C0C0],
            ['Wert' => self::$ZONE_WORKFLOW_SOON, 'Name' => $this->Translate('soon'), 'Farbe' => 0x6CB6FF],
            ['Wert' => self::$ZONE_WORKFLOW_SCHEDULED, 'Name' => $this->Translate('scheduled'), 'Farbe' => 0x0080C0],
            ['Wert' => self::$ZONE_WORKFLOW_WATERING, 'Name' => $this->Translate('watering'), 'Farbe' => 0xFFFF00],
            ['Wert' => self::$ZONE_WORKFLOW_DONE, 'Name' => $this->Translate('done'), 'Farbe' => 0x008000],
            ['Wert' => self::$ZONE_WORKFLOW_PARTIALLY, 'Name' => $this->Translate('partially'), 'Farbe' => 0x80FF00],
        ];
        $this->CreateVarProfile('Hydrawise.ZoneWorkflow', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$ZONE_STATUS_SUSPENDED, 'Name' => $this->Translate('suspended'), 'Farbe' => 0xFF5D5D],
            ['Wert' => self::$ZONE_STATUS_IDLE, 'Name' => $this->Translate('idle'), 'Farbe' => 0xC0C0C0],
            ['Wert' => self::$ZONE_STATUS_WATERING, 'Name' => $this->Translate('watering'), 'Farbe' => 0xFFFF00],
        ];
        $this->CreateVarProfile('Hydrawise.ZoneStatus', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $this->CreateVarProfile('Hydrawise.Duration', VARIABLETYPE_INTEGER, ' min', 0, 0, 0, 0, 'Hourglass', [], $reInstall);

        $this->CreateVarProfile('Hydrawise.Flowmeter', VARIABLETYPE_FLOAT, ' l', 0, 0, 0, 0, 'Gauge', [], $reInstall);
        $this->CreateVarProfile('Hydrawise.Flowmeter', VARIABLETYPE_FLOAT, ' l', 0, 0, 0, 0, 'Gauge', [], $reInstall);
        $this->CreateVarProfile('Hydrawise.Flowmeter', VARIABLETYPE_FLOAT, ' l', 0, 0, 0, 0, 'Gauge', [], $reInstall);
        $this->CreateVarProfile('Hydrawise.WaterFlowrate', VARIABLETYPE_FLOAT, ' l/min', 0, 0, 0, 1, '', [], $reInstall);
        $this->CreateVarProfile('Hydrawise.WaterFlowrate', VARIABLETYPE_FLOAT, ' l/min', 0, 0, 0, 1, '', [], $reInstall);
    }
}
