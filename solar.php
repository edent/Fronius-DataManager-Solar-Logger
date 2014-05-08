<?php
/**************
* This is a simple script to communicate with a Fronius DataManager.
* It gets the realtime power being generated, and the total generated today.
* The values are appended to a simple CSV file - YYYY-MM-DD.csv
* The script can be run as often as desired.
* It will only query the inverter if the time is between sunrise and sunset.
* It will only record a value if the DataManager is able to communicate with the inverter.
* If the current time is after sunset, the script will draw a graph (if it does not already exist).
* The first time the graph is drawn, it is posted to Twitter.
* Copyright Terence Eden.  MIT Licence.
***************/

/*	Libraries to include	*/

//	Twitter Async from https://github.com/jmathai/twitter-async - no Licence specified
include 'lib/EpiTwitter/EpiCurl.php';
include 'lib/EpiTwitter/EpiOAuth.php';
include 'lib/EpiTwitter/EpiTwitter.php';

//	Uses JpGraph from http://jpgraph.net/ - QPL-1.0
require_once ('lib/jpgraph/src/jpgraph.php');
require_once ('lib/jpgraph/src/jpgraph_line.php');
require_once ('lib/jpgraph/src/jpgraph_bar.php');
require_once( 'lib/jpgraph/src/jpgraph_date.php');

/*	Global variables	*/

//	IP Address of the DataManager
$dataManagerIP = "192.168.0.88";

//	How many Watts the system is rated for
$maxSolarCapacity = 4000;

//	Payment rate - £/kWh
$paymentRate = 0.1722;

//	Latitude and Longitude of where the solar panels are installed.
$latitude  = 51.1234;
$longitude = -1.1234;

//	Width and height of the graph
$width  = 700; 
$height = 600;

//	Twitter OAuth tokens from https://dev.twitter.com/
//	Make sure app has read AND write permissions
$twitterConsumerKey    = "";
$twitterConsumerSecret = "";
$twitterToken          = "";
$twitterTokenSecret    = "";

//	Today's date - used for writing .csv and .png files.
$today = date('Y-m-d', time());

//	Path of the script - to ensure we're reading and writing the correct directory
$currentPath = dirname(__FILE__) . DIRECTORY_SEPARATOR;

//	Calculate the timezone offset
$this_timezone = new DateTimeZone(date_default_timezone_get());
$now = new DateTime("now", $this_timezone);
$offset = $this_timezone->getOffset($now);

//	Sunrise and Sunset times.
$sunriseTimestamp = date_sunrise(time(), SUNFUNCS_RET_TIMESTAMP, 
                                 $latitude, $longitude, 
                                 ini_get("date.sunrise_zenith"), $offset);
$sunsetTimestamp = date_sunset(time(), SUNFUNCS_RET_TIMESTAMP, 
	                           $latitude, $longitude, 
	                           ini_get("date.sunset_zenith"), $offset);

/*	Let's get this party started :-)	*/

//	Are we between sunrise and set?
//	If so, start reading from the solar panels
if (time() >= $sunriseTimestamp && time() <= $sunsetTimestamp)
{
	//	API call for Fronius
	//	Documentation:
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

		//	Append the data to a CSV file named YYYY-MM-DD.csv
		$solarCSV = fopen($currentPath . $today . ".csv", 'a');
		fputcsv($solarCSV, $solarArray);
		fclose($solarCSV);
	}	
}
else if (time() >= $sunsetTimestamp && file_exists($currentPath . $today . ".csv") && !file_exists($currentPath . $today . ".png"))
{	//	Only do this the once - if it's after sunset, the csv exists and the graph hasn't yet been created.

	/*	Draw the graph	*/

	//	Set up the arrays. 0-2th element initially set to 0 
	//	0 = Earliest possible sunrise. Power set to 4k to allow gradient to look correct
	//	1 = A minute after 0. Power set to 0 so the gradient bar appears as a quasi-legend
	//	2 = A minute before the first read. Power set to 0 so the graph looks better
	$timestampArray    = array(0=>0, 1=>0, 2=>0);
	$currentPowerArray = array(0=>0, 1=>0, 2=>0);
	$totalPowerArray   = array(0=>0, 1=>0, 2=>0);

	//	Open the file, read the contents into arrays
	//	File will be names YYYY-MM-DD.csv
	if (($handle = fopen($currentPath . $today.".csv", "r")) !== FALSE) 
	{
		while (($data = fgetcsv($handle, 64, ",")) !== FALSE) 
		{
			$timestampArray[]    = strtotime($data[0]);
			$currentPowerArray[] = $data[1];
			$totalPowerArray[]   = $data[2];
		}
		fclose($handle);
	}

	//	Calculate the total kWh generated.
	//	Sum the readings and divide by 60. (Because we read every minute & there are 60 minutes in an hour).
	//	Divide by 1,000 to get kWh
	//	Only need 2 decimal places of precision
	$kWh = round( (array_sum($currentPowerArray) / 60 / 1000), 2);
	$moneyMade = number_format(round(($kWh * $paymentRate) , 2),2);


	//	The current date
	$startDayYear  = date('Y', end($timestampArray));	
	$startDayMonth = date('m', end($timestampArray));
	$startDayDay   = date('d', end($timestampArray));

	//	Zeroth datum should be zero
	//	Earliest sunrise in UK is 04:43
	$timestampArray[0]    = mktime(04, 0, 0, $startDayMonth, $startDayDay, $startDayYear);
	$currentPowerArray[0] = $maxSolarCapacity;	//	Dummy entry to give us a legend of how the gradient changes
	$totalPowerArray[0]   = 0;

	//	Dummy entry to give us a legend of how the gradient changes
	$timestampArray[1]    = mktime(04, 0, 1, $startDayMonth, $startDayDay, $startDayYear);
	$currentPowerArray[1] = 0;
	$totalPowerArray[1]   = 0;

	//	2nd datum should be zero at one minute before the first genuine reading
	$startDayPreviousMinute = date("Y-m-d H:i:s",strtotime("-1 minute",$timestampArray[3]));

	$timestampArray[2]    = strtotime($startDayPreviousMinute);
	$currentPowerArray[2] = 0;
	$totalPowerArray[2]   = 0;

	//	Penultimate datum should be zero at one minute after the last genuine reading
	$startDayFinalMinute = date("Y-m-d H:i:s",strtotime("+1 minute",end($timestampArray)));

	$timestampArray[]    = strtotime($startDayFinalMinute);
	$currentPowerArray[] = 0;
	$totalPowerArray[]   = 0;

	//	Last datum should be zero
	//	Latest sunset in UK is 21:21
	$timestampArray[]    = mktime(22, 0, 0, $startDayMonth, $startDayDay, $startDayYear);
	$currentPowerArray[] = 0;
	$totalPowerArray[]   = 0;

	//	Get the total generated power for the day (2nd to last element in array)
	end($totalPowerArray);
	prev($totalPowerArray);
	$totalPower = prev($totalPowerArray);

	//	Create a graph instance
	$graph = new Graph($width,$height);

	//	Margins are Left, Right, Top, Bottom
	//	Space out the graph so it looks balanced
	$graph->SetMargin(50,25,50,50);

	//	Specify what scale we want to use,
	//	dat = date scale for the X-axis
	//	int = integer scale for the Y-axis
	//	Max Y is based on the total solar generating capacity
	$graph->SetScale('datint', 0, $maxSolarCapacity);

	//	Date
	$todayDisplay = date('l jS F Y', $timestampArray[0]);

	//	Setup a title for the graph
	$graph->title->Set("~$kWh kWh Solar Power Generated - $todayDisplay.\nEarning GBP ".$moneyMade." - Oxford, UK");
	$graph->title->SetFont(FF_FONT2);	//	The largest built in font

	//	Setup X-axis labels
	//	Rotate slightly so easier to read
	$graph->xaxis->SetLabelAngle(50);
	//	Show only every Xth label
	$graph->xaxis->SetTextLabelInterval(1);

	// Setup Y-axis title & labels
	$graph->yaxis->title->Set('Watts');
	$graph->yaxis->HideZeroLabel();
	$graph->yaxis->SetTitleMargin(37);	//	Gives us enough space to see the Y-Axis title

	//	Sky colouring for background
	$graph->ygrid->SetFill(true,'#FFFFFF@0.5','#FFFFFF@0.5');
	$graph->SetBackgroundGradient('blue', '#55eeff', GRAD_HOR, BGRAD_PLOT);

	//	Create the linear plot
	$lineplot = new LinePlot($currentPowerArray, $timestampArray);

	//	Fill the graph area - Red for the largest, down to yellow. 256 is the number of colours in the gradient
	$lineplot->SetFillGradient('red','yellow',256,true); 

	//	Add the plot to the graph
	$graph->Add($lineplot);
	$lineplot->SetWeight('0');	//	Make the line invisible - we just want the fill gradient

	//	Colour of the line itself
	$lineplot->SetColor("#FF0000");

	//	Display the graph
	//$graph->Stroke();
	//	To write to a file
	$graphFilename = "$today.png";
	$graph->Stroke($currentPath . $graphFilename);

	//	Post the image to Twitter
	$twitterObj = new EpiTwitter($twitterConsumerKey, $twitterConsumerSecret, $twitterToken, $twitterTokenSecret);
	 
	$graphImage = "$currentPath" . "$graphFilename";
	$status = "Today, my solar panels generated ~$kWh kWh, earning £$moneyMade!";

	$uploadResp = $twitterObj->post('/statuses/update_with_media.json', 
                                    array('@media[]' => "@{$graphImage};type=png;filename={$graphFilename}",
                                    'status' => $status));
}
/*	All done, let's clean up after ourselves	*/
die();