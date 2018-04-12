<?

$instID = $_IPS['InstanceID'];

$cfg = json_decode(IPS_GetConfiguration($instID), true);
$with_daily_value = $cfg['with_daily_value'];

$duration = $_IPS['duration'];
$time_left = $_IPS['time_left'];
$suspended_until = $_IPS['suspended_until'];

$msg = 'duration=' . $duration . ', time_left=' . $time_left . ', suspended_until=' . $suspended_until;
$msg_e = [];
$msg_v = [];
$msg_h = [];

$triggerVars = ['duration', 'run', 'daily'];
foreach ($triggerVars as $triggerVar) {
    switch ($triggerVar) {
        case 'duration':
            $hideVars = ['Duration', 'Duration_seconds'];
            $do_hide = ! ($duration > 0 && ! $suspended_until);
            break;
        case 'run':
            $hideVars = ['TimeLeft', 'WaterUsage'];
            $do_hide = ! ($time_left > 0);
            break;
        case 'daily':
            $hideVars = ['DailyDuration', 'DailyDuration_seconds', 'DailyWaterUsage'];
            $do_hide = ! ($with_daily_value && ! $suspended_until);
            break;
        default:
            $vars = [];
            break;
    }
    foreach ($hideVars as $hideVar) {
        $hideID = @IPS_GetObjectIDByIdent($hideVar, $instID);
        if ($hideID == false) {
            $msg_e[] = 'Variable ' . $hideVar . ' not found';
            continue;
        }
        IPS_SetHidden($hideID, $do_hide);
		if ($do_hide) {
			$msg_h[] = $hideVar;
		} else {
        	$msg_v[] = $hideVar;
		}
    }
}

if ($msg_e != []) {
	$msg .= $msg != '' ? '; ' : '';
	$msg .= implode(', ', $msg_e);
}
if ($msg_v != []) {
	$msg .= $msg != '' ? '; ' : '';
	$msg .= 'set visible: ' . implode(', ', $msg_v);
}
if ($msg_h != []) {
	$msg .= $msg != '' ? '; ' : '';
	$msg .= 'set hidden: ' . implode(', ', $msg_h);
}

echo $msg . "\n";

?>