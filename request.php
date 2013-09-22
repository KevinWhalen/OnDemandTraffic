<?php
/* request.php
 * 
 * http://msdn.microsoft.com/en-us/library/hh441725.aspx
 * https://www.twilio.com
 * 
 * Kevin Whalen
 * Michigan Hack-a-thon
 * 2013-09-20
*/

//error_reporting(E_ALL);
//ini_set('display_errors', '1');

// Was actually inserting the key directly here
$apiKey = file_get_contents("../../bingmaps.txt");

// make an associative array of senders we know, indexed by phone number
$people = array(
		"+14158675309"=>"Curious George",
		"+14158675310"=>"Boots",
		"+14158675311"=>"Virgil",
		"+12163890866"=>"Kevin"
	);
if (!$name = $people[$_REQUEST['From']]){
	$name = "Monkey";
}

// Body text should be a 5 digit postal zip code
//$zip = 10001; // New York City
$message = "";
if ($_REQUEST['Body']){
	$zip = $_REQUEST['Body'];
	$zip = urlencode(ltrim(rtrim($zip)));
	if (!is_numeric($zip)){
		//$message = "Sorry, this protype software only supports 5 digit zip code locations.";
		$message = "is not number";//integer";
	}
} else $message = "text body empty";
//$message = "Sorry, this protype software only supports 5 digit zip code locations.";


// Control for invalid requests
if ($message == ""){


// Fetch Location bounds given from user
$locLookupUrl = "http://dev.virtualearth.net/REST/v1/Locations";

$url = $locLookupUrl.'?postalCode='.$zip.'&key='.$apiKey.'&output=xml';
$output = file_get_contents($url);
$response = new SimpleXMLElement($output);

// Bounds to be
$top = $response->ResourceSets->ResourceSet->Resources->Location->BoundingBox->NorthLatitude;
$right = $response->ResourceSets->ResourceSet->Resources->Location->BoundingBox->EastLongitude;
$bottom = $response->ResourceSets->ResourceSet->Resources->Location->BoundingBox->SouthLatitude;
$left = $response->ResourceSets->ResourceSet->Resources->Location->BoundingBox->WestLongitude;

// Resolved locality
$city = $response->ResourceSets->ResourceSet->Resources->Location->Address->Locality;

/* Scale to transform to a 50 mile rectangle. degrees +/-
latitude 0.75
longitude 
    0 degree 0.75
	10 degrees 0.80
	20 degrees 0.85
	30 degrees 0.90
	40 degrees 0.95
	50 degrees 1.5
	60 degrees 2.1
	70 degrees 3.1
	80 degrees 4.2
*/
function transformLongitude($degree){
	if ($degree < 45){
		if ($degree < 25){
			if ($degree < 15){
				if ($degree < 5) $d =  $degree + 0.75;
				else $d = $degree + 0.80;
			} else {
				$d = $degree + 0.85;
			}
		} else {
			if ($degree < 35) $d = $degree + 0.90;
			else $d = $degree + 0.95;
		}
	} else {
		if ($degree < 65){
			if ($degree < 55) $d = $degree + 1.5;
			else $d = $degree + 2.1;
		} else {
			if ($degree < 75) $d = $degree + 3.1;
			else $d = $degree + 4.2;
		}
	}

	return round($d, $precision = 1, $mode = PHP_ROUND_HALF_UP);
}
function transformLatitude($degree){
	$d = $degree + 0.75;
	return round($d, $precision = 1, $mode = PHP_ROUND_HALF_UP);
}

// Transform postal (zip) code into 50 mile bounding box
$top = transformLatitude($top);
$bottom = $bottom - (transformLatitude($bottom) - $bottom);
$right = transformLongitude($right);
$left = $left - (transformLongitude($left) - $left);

// Bing Maps API uses the order bottom, left, top, right
// South Latitude, West Longitude, North Latitude, East Longitude
$boundingBox = $bottom.','.$left.','.$top.','.$right;

// Fetch alerts
$trafficIncidentUrl = "http://dev.virtualearth.net/REST/v1/Traffic/Incidents";

// Severity s=1,2,3,4    four being highest
$url = $trafficIncidentUrl.'/'.$boundingBox.'/?s=3,4&key='.$apiKey.'&output=xml';
// This one includes locations codes for pre-defined road segments
//$url = $trafficIncidentUrl.'/'.$boundingBox.'/true/?key='.$apiKey.'&output=xml';
$output = file_get_contents($url);
$response = new SimpleXMLElement($output);

$incidentCount = $response->ResourceSets->ResourceSet->EstimatedTotal;
if ($incidentCount == 0)
	$message = "No alerts for $zip";
else if ($incidentCount > 1)
	$incidentCount .= " events\n";
else
	$incidentCount .= " event\n";

$serious = array();
$moderate = array();
foreach ($response->ResourceSets->ResourceSet->Resources->TrafficIncident as $incident){
	if ($incident->Severity == "Serious"){
		$serious[] = $incident;
	} else {
		$moderate[] = $incident;
	}
}


/* Destructive extraction
// Load the AlchemyAPI module code.
include "lib/AlchemyAPI/module/AlchemyAPI.php";

// Create an AlchemyAPI object.
$alchemyObj = new AlchemyAPI();

// Load the API key from disk.
$alchemyObj->loadAPIKey("../../AlchemyApiKey.txt");

// Extract topic keywords from a text string for a single text demonstration.
$demo = $serious[0]->{'Description'};
$demo = $alchemyObj->TextGetRankedKeywords($demo);
$demoSingleIncident = "";
foreach ($demo->results->keywords->keyword as $word){
	$demoSingleIncident .= $word->text . " ";
}
*/
$demoSingleIncident = $serious[0]->{'Description'};



/*
// Agregate the Keywords together.
function createResponseText($severity)
{
	$result = "";
	foreach ($severity as $incident){
		// Extract topic keywords from a text string.
		$result .= $alchemyObj->TextGetRankedKeywords($incident->{'Description'})." ";
	}
	return $result;
}
*/

/*
$message = $incidentCount;
$message .= createResponseText($serious);
$message .= createResponseText($moderate);
rtrim($message);
*/


} // Control for invalid requests


//wrap everything below getting the incoming text information in a control
if ($message == ""){
	$message = $incidentCount . $demoSingleIncident;
}

// Set the header information and send the message
header("content-type: text/xml");
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<Response>
<Message><?php echo $message; ?></Message>
</Response>
