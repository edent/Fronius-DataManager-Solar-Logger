<?php
header('Content-type:image/png');	//	If this is being displayed on a website
//Uses JpGraph http://jpgraph.net/
require_once ('jpgraph/src/jpgraph.php');
require_once ('jpgraph/src/jpgraph_line.php');
require_once ('jpgraph/src/jpgraph_bar.php');
require_once( "jpgraph/src/jpgraph_date.php" );

//	How many Watts the system is rated for
$maxSolarCapacity = 4000;

function readSolarCSV(&$timestampArray, &$currentPowerArray, &$totalPowerArray) 
{
	global $maxSolarCapacity;

	//	Open the file, read the contents into arrays
    if (($handle = fopen("solar.csv", "r")) !== FALSE) 
	{
		while (($data = fgetcsv($handle, 64, ",")) !== FALSE) 
		{
			$timestampArray[]    = strtotime($data[0]);
			$currentPowerArray[] = $data[1];
			$totalPowerArray[]   = $data[2];
		}
		fclose($handle);
	}

	//	The current date
	$startDayYear  = date('Y', end($timestampArray));	
	$startDayMonth = date('m', end($timestampArray));
	$startDayDay   = date('d', end($timestampArray));

	//	Zeroth datum should be zero
	//	Earliest sunrise in UK is 04:43
	$timestampArray[0]    = mktime(04, 0, 0, $startDayMonth, $startDayDay, $startDayYear);
	$currentPowerArray[0] = $maxSolarCapacity;		//	Dummy entry to give us a legend of how the gradient changes
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
}

//	Set up the arrays. 0-2th element initially set to 0 
//	0 = Earliest possible sunrise. Power set to 4k to allow gradient to look correct
//	1 = A minute after 0. Power set to 0 so the gradient bar appears as a quasi-legend
//	2 = A minute before the first read. Power set to 0 so the graph looks better
$timestampArray    = array(0=>0, 1=>0, 2=>0);
$currentPowerArray = array(0=>0, 1=>0, 2=>0);
$totalPowerArray   = array(0=>0, 1=>0, 2=>0);

//	Populate the arrays
readSolarCSV($timestampArray,$currentPowerArray,$totalPowerArray);

//	Get the total generated power for the day (2nd to last element in array)
end($totalPowerArray);
prev($totalPowerArray);
$totalPower = prev($totalPowerArray);

//	Width and height of the graph
$width  = 700; 
$height = 600;

//	Create a graph instance
$graph = new Graph($width,$height);

//	Margins are Left, Right, Top, Bottom
$graph->SetMargin(50,25,50,50);

//	Specify what scale we want to use,
//	dat = date scale for the X-axis
//	int = integer scale for the Y-axis
//	Max Y is based on the total solar generating capacity
$graph->SetScale('datint', 0, $maxSolarCapacity);

//	Date
$today = date('l jS F Y', $timestampArray[0]);

//	Setup a title for the graph
$graph->title->Set("$totalPower kWh Solar Power Generated - $today - Oxford, UK");
$graph->title->SetFont(FF_FONT2);

//	Setup X-axis labels
$graph->xaxis->SetLabelAngle(50);
//	Show only every Xth label
$graph->xaxis->SetTextLabelInterval(1);

// Setup Y-axis title & labels
$graph->yaxis->title->Set('Watts');
$graph->yaxis->HideZeroLabel();
$graph->yaxis->SetTitleMargin(37);

//	Sky colouring for background
$graph->ygrid->SetFill(true,'#FFFFFF@0.5','#FFFFFF@0.5');
$graph->SetBackgroundGradient('blue', '#55eeff', GRAD_HOR, BGRAD_PLOT);

//	Create the linear plot
$lineplot = new LinePlot($currentPowerArray, $timestampArray);

//	Fill the graph area - Red for the largest, down to yellow.
$lineplot->SetFillGradient('red','yellow',256,true); 

//	Add the plot to the graph
$graph->Add($lineplot);
$lineplot->SetWeight('0');

//	Colour of the line itself
$lineplot->SetColor("#FF0000");

//	Display the graph
$graph->Stroke();
//	To write to a file
//$graph->Stroke(date('Y-m-d', $timestampArray[0]) . ".png");

die();