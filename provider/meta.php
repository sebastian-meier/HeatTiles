<?php

/*

deliver/square.php
Needs zoom + min_lat / max_lat & min_lng / max_lng and returns an geojson for the heatmap within that area
If timing is activated you should provide time=[int timeframe] OR time=all
The later will result in a continous data stream, see example
If you have multiple datasets in your table you need to provide multi=[int ref_id]

*/

//This allows us to stream multiple json files in on request
header("Content-type: text/csv");
header("Cache-Control: no-cache");
header("Access-Control-Allow-Origin: *");

require_once("../config_local.php");

$time_select = "";
$time_where = "";
if($time){
	$time_select = ", `time`";
	$time_where = " AND `time` > -1 ORDER BY `time` ASC";
}

$params = array();

$multi_where = "";
if($multiple){
	$multi_where = ' AND `ref_id` = ?';
	array_push($params, $_GET["ref_id"]);
}

echo 'value';

if($time){
	echo ',time';
}

echo "\n";

$sql = 'SELECT `value`'.$time_select.' FROM `'.$db_max_table.'` WHERE `key` = "value_max"'.$multi_where.$time_where;
$result = sql_request($sql, $params);
while($row = sql_fetch_row($result)){
	echo number_format((double)$row[0], 12);
	if($time){
		echo ','.$row[1];
	}
	echo "\n";
}
sql_close($result);

$mysqli->close();

?>