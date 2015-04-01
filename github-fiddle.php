<?php
set_time_limit(0);
// Create connection
global $con;
$con=mysqli_connect("192.168.0.57","root","NewRoot","dbo");

// Check connection
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
}
include 'LifeDB.php';
// function truncateTables() {
// 	global $con;
// 	$queryToTruncateFiddle = "TRUNCATE TABLE  Fiddle;"
// 	$queryToTruncateFiddleToCategory = "TRUNCATE TABLE  FiddleToCategory";
// 	mysqli_query($queryToTruncateFiddle);
// 	mysqli_query($queryToTruncateFiddleToCategory);
// }
function insertFiddleData() {
	$db1 = new LifeDB('NewFiddles.js');
	$db2 = new LifeDB('fiddleData_new.js');
	$newFiddleArray = array();
	$index = 0;
	foreach(json_decode($db1->find("FiddlesData", "[\"fiddle_new_link\"]"), true) as $fiddleLinkObject) {
		$newSingleFiddle = array();
		$newSingleFiddle['fiddle_id'] = $index;//*
		$fiddleLink = $fiddleLinkObject["fiddle_new_link"];
		$newSingleFiddle['fiddle_url'] = $fiddleLink;//*
		$fiddleLinkArray = explode("_", $fiddleLink);
		$fiddleIdOriginal = intval(trim($fiddleLinkArray[count($fiddleLinkArray)-1],'/'))+1;
		$fiddleOldObject = json_decode($db2->find("FiddlesData", "[\"fiddle_url\",\"fiddle_description\"]","fiddle_id @eq :".$fiddleIdOriginal), true)[0];
		$array = explode("/",$fiddleOldObject["fiddle_url"]);//*
		$newSingleFiddle['fiddle_thumb'] = $array[count($array)-2].".png";

		$newSingleFiddle['fiddle_description'] = $fiddleOldObject["fiddle_description"];//*

		array_push($newFiddleArray, $newSingleFiddle);
		$index++;
	}
	return ($newFiddleArray);
}
$fiddleArray = insertFiddleData();
?>