<?php
/**************
* This is a simple script to communicate with a Fronius DataManager.
* It gets the realtime power being generated, and the total generated today.
* The values are appended to a simple CSV file.
* The script can be run as often as desired.
* It will only query the inverter if the time is between sunrise and sunset.
* It will only record a value if the DataManager is able to communicate with the inverter.
* Copyright Terence Eden.  MIT Licence.
***************/

//	IP Address of the DataManager
$dataManagerIP = "192.168.0.2";

//	Latitude and Longitude of where the solar panels are installed.
$latitude  = 51.1234;
$longitude = -1.1234;

//	No point in running unless we're confident the sun is up!

//	Calculate the timezone offset
$this_timezone = new DateTimeZone(date_default_timezone_get());
$now = new DateTime("now", $this_timezone);
$offset = $this_timezone->getOffset($now);

//	Sunrise and Sunset times.
$sunriseTimestamp = date_sunrise(time(), SUNFUNCS_RET_TIMESTAMP, $latitude, $longitude, ini_get("date.sunrise_zenith"), $offset);
$sunsetTimestamp = date_sunset(time(), SUNFUNCS_RET_TIMESTAMP, $latitude, $longitude, ini_get("date.sunset_zenith"), $offset);

//	Are we between sunrise and set?
if (time() >= $sunriseTimestamp && time() <= $sunsetTimestamp)
{
	//	API call for Fronius
	//	Documentation
	//	https://www.fronius.com/cps/rde/xbcr/SID-539A9068-7638796D/fronius_international/42_0410_2012_318486_snapshot.pdf
	$apiURL = "http://".$dataManagerIP."/solar_api/v1/GetInverterRealtimeData.cgi?Scope=Device&DeviceID=1&DataCollection=CommonInverterData";

	//	Get the raw JSON
	$jsonData = file_get_contents($apiURL);

	//	Decode into an object
	$solar = json_decode($jsonData, true);

	//	Is the inverter up and running?
	if (7 == $solar["Body"]["Data"]["DeviceStatus"]["StatusCode"])
	{
		//	Get the variables in which we're interested
		$solarTimestamp = $solar["Head"]["Timestamp"];                  //	Time according to the DataManager
		$solarPower     = $solar["Body"]["Data"]["PAC"]["Value"];       //	Real time AC being fed into the mains
		$solarDayEnergy = $solar["Body"]["Data"]["DAY_ENERGY"]["Value"];//	Total amount generated today

		//	Format for writing to CSV file
		//	Time,Energy,Total
		$solarArray = array($solarTimestamp, $solarPower, $solarDayEnergy);

		//	Append the data to a CSV file
		$solarCSV = fopen('solar.csv', 'a');
		fputcsv($solarCSV, $solarArray);
		fclose($solarCSV);
	}	
}

die();