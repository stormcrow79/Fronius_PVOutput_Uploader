<?php

// Configuration Options
$dataManagerIP = "192.168.1.103";
$dataFile = "~/projects/solar/data/solar.dat";
$pvOutputApiURL = "http://pvoutput.org/service/r2/addstatus.jsp?";
$pvOutputApiKEY = "cc641a0c329f000bfdd43c87b0d3545396297da1";
$pvOutputSID = "46016";

// Inverter & Smart Meter API URLs
$inverterDataURL = "http://".$dataManagerIP."/solar_api/v1/GetInverterRealtimeData.cgi?Scope=Device&DeviceID=1&DataCollection=CommonInverterData";
$meterDataURL = "http://".$dataManagerIP."/solar_api/v1/GetMeterRealtimeData.cgi?Scope=Device&DeviceId=0";

// Define Date & Time
date_default_timezone_set("Australia/Perth");
$system_time= time();
$date = date('Ymd', time());
$time = date('H:i', time());

// Read Meter Data
do {
  sleep(5);
  $meterJSON = file_get_contents($meterDataURL);

  $dt = new DateTime();
  file_put_contents(expand_tilde("~/projects/solar/data/") . $dt->format("Ymd-Hi") . ".json", $meterJSON);

  $meterData = json_decode($meterJSON, true);
  if (empty($meterData["Body"]))
    break;
  $meterPowerLive = $meterData["Body"]["Data"]["PowerReal_P_Sum"];
  $meterImportTotal = $meterData["Body"]["Data"]["EnergyReal_WAC_Plus_Absolute"];
  $meterExportTotal = $meterData["Body"]["Data"]["EnergyReal_WAC_Minus_Absolute"];
} while (empty($meterPowerLive) || empty($meterImportTotal) || empty($meterExportTotal));

// Read Inverter Data
sleep(5);
$inverterJSON = file_get_contents($inverterDataURL);
$inverterData = json_decode($inverterJSON, true);
$inverterPowerLive = $inverterData["Body"]["Data"]["PAC"]["Value"];
$inverterEnergyDayTotal = $inverterData["Body"]["Data"]["DAY_ENERGY"]["Value"];
$inverterVoltageLive = $inverterData["Body"]["Data"]["UAC"]["Value"];

// Read Previous Days Meter Totals From Data File
if (file_exists($dataFile)) {
    echo "Reading data from $dataFile\n";
} else {
    echo "The file $dataFile does not exist, creating... \n";
    $saveData = serialize(array('import' => $meterImportTotal, 'export' => $meterExportTotal));
    file_put_contents($dataFile, $saveData);
}
$readData = unserialize(file_get_contents($dataFile));
$meterImportDayStartTotal = $readData['import'];
$meterExportDayStartTotal = $readData['export'];

// Calculate Day Totals For Meter Data
$meterImportDayTotal = $meterImportTotal - $meterImportDayStartTotal;
$meterExportDayTotal = $meterExportTotal - $meterExportDayStartTotal;

// Calculate Consumption Data
$consumptionPowerLive = $inverterPowerLive + $meterPowerLive;
$consumptionEnergyDayTotal = $inverterEnergyDayTotal + $meterImportDayTotal - $meterExportDayTotal;

// Calculate Live Import/Export Values
if ($meterPowerLive > 0) {
    $meterPowerLiveImport = $meterPowerLive;
    $meterPowerLiveExport = 0;
} else {
    $meterPowerLiveImport = 0;
    $meterPowerLiveExport = $meterPowerLive;
}

// Push to PVOutput
$pvOutputURL = $pvOutputApiURL
                . "key=" .  $pvOutputApiKEY
                . "&sid=" . $pvOutputSID
                . "&d=" .   $date
                . "&t=" .   $time
                . "&v1=" .  $inverterEnergyDayTotal
                . "&v2=" .  $inverterPowerLive
                . "&v6=" .  $inverterVoltageLive;

if (!empty($meterData["Body"]))
{
  $pvOutputURL = $pvOutputURL
                . "&v3=" .  $consumptionEnergyDayTotal
                . "&v4=" .  $consumptionPowerLive
                . "&v7=" .  $meterExportDayTotal
                . "&v8=" .  $meterImportDayTotal
                . "&v9=" .  $meterPowerLive
                . "&v10=" . $meterPowerLiveExport
                . "&v11=" . $meterPowerLiveImport;
}

file_get_contents(trim($pvOutputURL));

//Print Values to Console
Echo "\n";
Echo "d \t $date\n";
Echo "t \t $time\n";
Echo "v1 \t $inverterEnergyDayTotal\n";
Echo "v2 \t $inverterPowerLive\n";
Echo "v3 \t $consumptionEnergyDayTotal\n";
Echo "v4 \t $consumptionPowerLive\n";
Echo "v6 \t $inverterVoltageLive\n";
Echo "v7 \t $meterExportDayTotal\n";
Echo "v8 \t $meterImportDayTotal\n";
Echo "v9 \t $meterPowerLive\n";
Echo "v10 \t $meterPowerLiveExport\n";
Echo "v11 \t $meterPowerLiveImport\n";
Echo "\n";
Echo "Sending data to PVOutput.org \n";
Echo "$pvOutputURL \n";
Echo "\n";

// Update data file with new EOD totals
if ($system_time > strtotime('Today 11:55pm') && $system_time < strtotime('Today 11:59pm')) {
  $saveData = serialize(array('import' => $meterImportTotal, 'export' => $meterExportTotal));
  file_put_contents($dataFile, $saveData);
}

$localUrl = "http://localhost:9400/service/r2/addstatus.jsp";
$data = array(
    'dayEnergy' => $inverterEnergyDayTotal,
    'instantPower' => $inverterPowerLive,
    'instantVoltage' => $inverterVoltageLive
);
// use key 'http' even if you send the request to https://...
$options = array(
    'http' => array(
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data)
    )
);
$context  = stream_context_create($options);
$result = file_get_contents($localUrl, false, $context);

function expand_tilde($path)
{
    if (function_exists('posix_getuid') && strpos($path, '~') !== false) {
        $info = posix_getpwuid(posix_getuid());
        $path = str_replace('~', $info['dir'], $path);
    }

    return $path;
}
?>
