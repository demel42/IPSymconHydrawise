<?php

declare(strict_types=1);

if (!defined('SENSOR_NORMALLY_CLOSE_START')) {
    define('SENSOR_NORMALLY_CLOSE_START', 11);
    define('SENSOR_NORMALLY_OPEN_STOP', 12);
    define('SENSOR_NORMALLY_CLOSE_STOP', 13);
    define('SENSOR_NORMALLY_OPEN_START', 14);
    define('SENSOR_FLOW_METER', 30);
}

if (!defined('RELAY_TYPE_PROGRAMMED')) {
    define('RELAY_TYPE_PROGRAMMED', 1);
    define('RELAY_TYPE_RUNNING', 106);
    define('RELAY_TYPE_SUSPENDED', 110);
}

trait HydrawiseLocal
{
    public static $IS_INVALIDCONFIG = IS_EBASE + 1;
    public static $IS_UNAUTHORIZED = IS_EBASE + 1;
    public static $IS_SERVERERROR = IS_EBASE + 3;
    public static $IS_HTTPERROR = IS_EBASE + 4;
    public static $IS_INVALIDDATA = IS_EBASE + 5;
    public static $IS_NODATA = IS_EBASE + 6;
    public static $IS_NOCONROLLER = IS_EBASE + 7;
    public static $IS_CONTROLLER_MISSING = IS_EBASE + 8;
    public static $IS_ZONE_MISSING = IS_EBASE + 9;
    public static $IS_USEDWEBHOOK = IS_EBASE + 10;
    public static $IS_TOOMANYREQUESTS = IS_EBASE + 11;

    private function GetFormStatus()
    {
        $formStatus = [];

        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => self::$IS_INVALIDCONFIG, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid configuration)'];
        $formStatus[] = ['code' => self::$IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        $formStatus[] = ['code' => self::$IS_NODATA, 'icon' => 'error', 'caption' => 'Instance is inactive (no data)'];
        $formStatus[] = ['code' => self::$IS_NOCONROLLER, 'icon' => 'error', 'caption' => 'Instance is inactive (no controller)'];
        $formStatus[] = ['code' => self::$IS_CONTROLLER_MISSING, 'icon' => 'error', 'caption' => 'Instance is inactive (controller missing)'];
        $formStatus[] = ['code' => self::$IS_ZONE_MISSING, 'icon' => 'error', 'caption' => 'Instance is inactive (zone missing)'];
        $formStatus[] = ['code' => self::$IS_USEDWEBHOOK, 'icon' => 'error', 'caption' => 'Instance is inactive (webhook already in use)'];
        $formStatus[] = ['code' => self::$IS_TOOMANYREQUESTS, 'icon' => 'error', 'caption' => 'Instance is inactive (too many requests)'];

        return $formStatus;
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
