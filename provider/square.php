<?php

/*

deliver/square.php
Needs zoom + min_lat / max_lat & min_lng / max_lng and returns an geojson for the heatmap within that area
If timing is activated you should provide time=[int timeframe] OR time=all
The later will result in a continous data stream, see example
If you have multiple datasets in your table you need to provide multi=[int ref_id]

*/

require_once("../config_local.php");

$_GET["zoom"]-=0;

$tiles = array();
$z0 = $_GET["zoom"]-2;
$z0i = $_GET["zoom"]-1;

//We need to determin the center of the map, in case we cross the continous world border this will define the world we need to focus on
$world = 1;
if($_GET["max_x"]<$_GET["min_x"]){
	if($_GET["max_x"] > ($max*2-$_GET["min_x"])){
		$world = -1;
	}else{
		$world = 1;
	}
}

//Calculating an an additional buffering area around the requested area, for better performance when the user is panning
if($_GET["max_x"]<$_GET["min_x"]){
	$dist_x = (($_GET["max_x"]+$max*2) - $_GET["min_x"])*0.5;
}else{
	$dist_x = ($_GET["max_x"] - $_GET["min_x"])*0.5;
}

$_GET["max_x"] += $dist_x;
$_GET["min_x"] -= $dist_x;

if($_GET["max_x"]>$max*2){
	$_GET["max_x"]-=$max*2;
}

if($_GET["min_x"]<0){
	$_GET["max_x"]+=$max*2;
}


if($_GET["max_y"]<$_GET["min_y"]){
	$dist_y = (($_GET["max_y"]+$max*2) - $_GET["min_y"])*0.5;	
}else{
	$dist_y = ($_GET["max_y"] - $_GET["min_y"])*0.5;	
}

$_GET["max_y"] += $dist_y;
$_GET["min_y"] -= $dist_y;

if($_GET["max_y"]>$max*2){
	$_GET["max_y"]-=$max*2;
}

if($_GET["min_y"]<0){
	$_GET["max_y"]+=$max*2;
}

/*------------------------------*/

//Collecting the maximum range for the current animation to create a range for color assignment
$sql_params = array();

$where_ref = "";
if($multiple){
	$where_ref = " AND `".$multiple_ref_col."`  = ?";
	array_push($sql_params, $_GET["ref_id"]);
}

$where_time = "";
if($time){
	if(is_numeric($_GET["time"])){
		$where_ref = " AND `".$time_col."`  = ?";
		array_push($sql_params, $_GET["time"]);
	}else{
		$where_ref = " AND `".$time_col."`  = -1";
	}
}

$group_type = "max";
if($vis_type == "value"){
	"value_max"
}

$sql = 'SELECT `value` FROM `'.$db_max_table.'` WHERE `key` = "'.$group_type.'"'.$where_ref.$where_time.' ORDER BY `value` DESC LIMIT 0,1';
$result = query_mysql($sql, $sql_params);
if ($result) {
	while ($row = $result->fetch_row()) {
		$tmax = $row[0];
	}

	$result->close();
	$mysqli->next_result();
}

//From the maximum and the color range we calculate the size of each step as well as creating arrays for each step to collect points
$size = $tmax / count($colors);
$stepps = array();
for($i=0; $i<count($colors); $i++){
	$stepps[$i] = array();
}

//This is the maximum number of zoom levels
$step_max = $zoom_max-2;

if($_GET["zoom"]==18){
	//if the zoom level is equal 18 we ignore the heatmap generation and directly deliver the raw datapoints for client side visualization
	$points = array();
	$sql = 'SELECT latitude, longitude FROM `'.$db_table.'` WHERE `validconversion` = 1 AND quake_id = '.$_GET["quake_id"].' AND x0 > '.$_GET['min_x'].' AND x0 < '.$_GET['max_x'].' AND y0 > '.$_GET['min_y'].' AND y0 < '.$_GET['max_y'];
	$result = query_mysql($sql, $link);
	if ($result) {
		while ($row = mysql_fetch_array($result)) {
			array_push($points, array($row[0], $row[1]));
		}
	}
	mysql_free_result($result);

	echo json_encode($points);
}else{
	//for smaller zoomlevels we will generate the heatmaps

	//if our heatmap goes across our continous world border we need a special WHERE request
	if($_GET["max_x"]>$_GET["min_x"]){
		$x_req = '`x0` > '.$_GET['min_x'].' AND `x0` < '.$_GET['max_x'];
	}else{
		$x_req = ' (( `x0` > '.$_GET['min_x'].' AND `x0` < '.($max*2).' ) OR ( `x0` > 0 AND `x0` < '.$_GET['max_x'].' )) ';
	}

	if($_GET["max_y"]>$_GET["min_y"]){
		$y_req = '`y0` > '.$_GET['min_y'].' AND `y0` < '.$_GET['max_y'];
	}else{
		$y_req = ' (( `y0` > '.$_GET['min_y'].' AND `y0` < '.($max*2).' ) OR ( `y0` > 0 AND `y0` < '.$_GET['max_y'].' )) ';
	}

	$sql = 'SELECT `intensity`, z'.$z0i.' FROM `'.$db_table.'` WHERE `validconversion` = 1 AND `time` = '.$_GET["time"].' AND `quake_id` = '.$_GET["quake_id"].' AND '.$x_req.' AND '.$y_req.' GROUP BY z'.$z0i.' ORDER BY `intensity` DESC';
	$result = query_mysql($sql, $link);
	if ($result) {
		while ($row = mysql_fetch_array($result)) {
			

			$y = floor($row[1]/$steps[$step_max-$z0]);
			$x = $row[1] - ($y*$steps[$step_max-$z0])-1;

			$x1 = $x*$step_size[$step_max-$z0]-$max;
			$y1 = $y*$step_size[$step_max-$z0]-$max;

			$x2 = $x1+$step_size[$step_max-$z0];
			$y2 = $y1+$step_size[$step_max-$z0];

			if($_GET["max_x"]<$_GET["min_x"]){
				$x1 += $world*2*$max;
				$x2 += $world*2*$max;
			}

			$tarray = array();

			array_push($tarray, array($x1, $y1));
			array_push($tarray, array($x2, $y1));
			array_push($tarray, array($x2, $y2));
			array_push($tarray, array($x1, $y2));

			//Calculate the range step
			$s = round(($row[0])/$size);
			if($s>(count($colors)-1)){$s=(count($colors)-1);}

			//Add the square to the range step array
			array_push($stepps[$s], $tarray);

		}
	}
	mysql_free_result($result);

	//now we take each range step and turn it into a geoJSON
	echo '[';
	$first = true;
	foreach ($stepps as $key => $step) {
	if($step != null){
		if(!$first){ echo ','; }else{ $first = false; }
			$color = $colors[$key];
			?>{"type": "Feature", "properties":{"style": {"stroke": "false", "weight":"0", "color":"#000", "fillColor":"#<?php echo $color; ?>", "opacity":"0", "fillOpacity":"0.5"}}, "geometry": {"type": "MultiPolygon","coordinates": <?php echo json_encode(array($step)); ?>},"crs": {"type": "name","properties": {"name": "urn:ogc:def:crs:SR-ORG::7483"}}}<?php
		}
	}
	echo ']';
}

?>