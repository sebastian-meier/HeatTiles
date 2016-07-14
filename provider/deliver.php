<?php

/*

deliver/square.php
Needs zoom + min_lat / max_lat & min_lng / max_lng and returns an geojson for the heatmap within that area
If timing is activated you should provide time=[int timeframe] OR time=all
The later will result in a continous data stream, see example
If you have multiple datasets in your table you need to provide multi=[int ref_id]

*/

//This allows us to stream multiple json files in on request
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Access-Control-Allow-Origin: *");

require_once("../config_local.php");

//For Continous DataStream
$lastEventId = floatval(isset($_SERVER["HTTP_LAST_EVENT_ID"]) ? $_SERVER["HTTP_LAST_EVENT_ID"] : 0);
if ($lastEventId == 0) {
	$lastEventId = floatval(isset($_GET["lastEventId"]) ? $_GET["lastEventId"] : 0);
}

if(isset($_GET["time"])&&($_GET["time"]=="all")){
	// 2 kB padding for IE, STACKOVERFLOW says so
	echo ":" . str_repeat(" ", 2048) . "\n";
	echo "retry: 1000\n";
}

if(isset($_GET["vis_type"])){
	$vis_type = $_GET["vis_type"];
}

if(isset($_GET["vis"])){
	$vis = $_GET["vis"];
}

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
$where_ref_max = "";
if($multiple){
	$where_ref = " AND `".$multiple_ref_col."`  = ?";
	$where_ref_max = " AND `ref_id` = ?";
	array_push($sql_params, $_GET["ref_id"]);
}

$where_time = "";
if($time){
	if(is_numeric($_GET["time"])){
		$where_time = " AND `".$time_col."` = ?";
		array_push($sql_params, $_GET["time"]);
	}else{
		$where_time = " AND `".$time_col."` = -1";
	}
}

$group_type_query = "COUNT";
$group_type = "max";
if($vis_type == "value_max"){
	$group_type_query = "MAX";
	$group_type = "value_max";
}elseif($vis_type == "value_min"){
	$group_type_query = "MIN";
	$group_type = "value_min";
}elseif($vis_type == "value_avg"){
	$group_type_query = "AVG";
	$group_type = "value_avg";
}

$sql = 'SELECT `value` FROM `'.$db_max_table.'` WHERE `key` = "'.$group_type.'"'.$where_ref_max.$where_time.' ORDER BY `value` DESC LIMIT 1';
$result = sql_request($sql, $sql_params);
if ($result) {
	while ($row = sql_fetch_row($result)) {
		$tmax = $row[0];
	}

	sql_close($result);
	$mysqli->next_result();
}

//From the maximum and the color range we calculate the size of each step as well as creating arrays for each step to collect points
$size = $tmax / (double)count($colors);
$stepps = array();
for($i=0; $i<count($colors); $i++){
	$stepps[$i] = array();
}

//This is the maximum number of zoom levels
$step_max = $zoom_max-2;

if($_GET["zoom"]>=$zoom_single){
	//!!!!Attention this way of outputting single points is not not build for time-series data, i suggest you disable this for your time series data config.php:$zoom_single = 99;

	//if the zoom level is >= $zoom_single (see config) we ignore the heatmap generation and directly deliver the raw datapoints for client side visualization
	$points = array();
	$sql_params = array($_GET['min_x'], $_GET['max_x'], $_GET['min_y'], $_GET['max_y']);

	//if you want to add more metadata to you points, e.g. adress, name, ect. add this here and...
	//!!!!TIME IS MISSING HERE
	$sql = 'SELECT `ref`.`latitude`, `ref`.`longitude` FROM `'.$db_table.'` AS `ref` JOIN `'.$db_conversion_table.'` AS `conversion` ON `ref`.`'.$multiple_ref_col.'` = `conversion`.`ref_id` WHERE `conversion`.`x0` > ? AND `conversion`.`x0` < ? AND `conversion`.`y0` > ? AND `conversion`.`y0` < ?'.$where_ref.$where_time;
	$result = sql_request($sql, $sql_params);
	if ($result) {
		while ($row = $result->fetch_row()) {
			//...and here
			array_push($points, array($row[0], $row[1]));
		}

		$result->close();
		$mysqli->next_result();
	}

	//simply output the points
	echo json_encode($points);
}else{
	//for smaller zoomlevels we will generate the heatmaps
	$max_time = 0;
	if($time){
		$sql_params = false;
		$multi = "";
		if($multiple){
			 $multi = "WHERE `".$multiple_ref_col."`  = ?";
			 $sql_params = $_GET["ref_id"];
		}
		$sql = 'SELECT `'.$time_col.'` FROM `'.$db_table.'`'.$multi.' ORDER BY `'.$time_col.'` DESC LIMIT 1';
		$result = sql_request($sql, $sql_params);
		if ($result) {
			while ($row = sql_fetch_row($result)) {
				$max_time = $row[0];
			}

			sql_close($result);
			$mysqli->next_result();
		}
	}

	//if our heatmap goes across our continous world border we need a special WHERE request
	if($_GET["max_x"]>$_GET["min_x"]){
		$x_req = '`conversion`.`x0` > '.$_GET['min_x'].' AND `conversion`.`x0` < '.$_GET['max_x'];
	}else{
		$x_req = ' (( `conversion`.`x0` > '.$_GET['min_x'].' AND `conversion`.`x0` < '.($max*2).' ) OR ( `conversion`.`x0` > 0 AND `conversion`.`x0` < '.$_GET['max_x'].' )) ';
	}

	if($_GET["max_y"]>$_GET["min_y"]){
		$y_req = '`conversion`.`y0` > '.$_GET['min_y'].' AND `conversion`.`y0` < '.$_GET['max_y'];
	}else{
		$y_req = ' (( `conversion`.`y0` > '.$_GET['min_y'].' AND `conversion`.`y0` < '.($max*2).' ) OR ( `conversion`.`y0` > 0 AND `conversion`.`y0` < '.$_GET['max_y'].' )) ';
	}

	do{
		$stepps = array();
		for($i=0; $i<count($colors); $i++){
			$stepps[$i] = array();
		}

		$sql_params = array();

		$time_query = "";
		if($time){
			if(is_numeric($_GET["time"])){
				$time_query = ' `ref`.`time` = ? AND ';
				array_push($sql_params, $_GET["time"]);
			}else{
				$time_query = ' `ref`.`time` = '.$lastEventId.' AND ';
			}
		}

		$multi_query = "";
		if($multiple){
			$multi_query = ' `ref`.`'.$multiple_ref_col.'` = ? AND ';
			array_push($sql_params, $_GET["ref_id"]);
		}

		$h = "";
		if($vis == "hex"){
			$h = "h";
		}

		$sql = 'SELECT '.$group_type_query.'(`ref`.`'.$cluster_value_col.'`), `conversion`.`z'.$h.$z0i.'` FROM `'.$db_table.'` AS `ref` JOIN `'.$db_conversion_table.'` AS `conversion` ON `ref`.`'.$db_id.'` = `conversion`.`ref_id` WHERE '.$time_query.$multi_query.$x_req.' AND '.$y_req.' GROUP BY `conversion`.`z'.$h.$z0i.'`';
		$result = sql_request($sql, $sql_params);
		while ($row = sql_fetch_row($result)) {

			$y = floor($row[1]/$steps[$step_max-$z0]);
			$x = $row[1] - ($y*$steps[$step_max-$z0])-1;

			$tarray = array();

			if($vis == "hex"){
				if($y%2){
					$x += 0.5; 
				}

				$mStep = $step_size[$step_max-$z0]/14;

				$cornerStep = 2.5;
				$topStep = 2.5;

				array_push($tarray, array($x*$step_size[$step_max-$z0]-$max, $y*$step_size[$step_max-$z0]-$max+$mStep*$cornerStep));
				//Top of the hex
				array_push($tarray, array($x*$step_size[$step_max-$z0]-$max+$step_size[$step_max-$z0]/2, $y*$step_size[$step_max-$z0]-$max-$mStep*$topStep));
				array_push($tarray, array($x*$step_size[$step_max-$z0]-$max+$step_size[$step_max-$z0], $y*$step_size[$step_max-$z0]-$max+$mStep*$cornerStep));
				array_push($tarray, array($x*$step_size[$step_max-$z0]-$max+$step_size[$step_max-$z0], $y*$step_size[$step_max-$z0]-$max+$step_size[$step_max-$z0]-$mStep*$cornerStep));
				//Bottom of the hex
				array_push($tarray, array($x*$step_size[$step_max-$z0]-$max+$step_size[$step_max-$z0]-$step_size[$step_max-$z0]/2, $y*$step_size[$step_max-$z0]-$max+$step_size[$step_max-$z0]+$mStep*$topStep));
				array_push($tarray, array($x*$step_size[$step_max-$z0]-$max, $y*$step_size[$step_max-$z0]-$max+$step_size[$step_max-$z0]-$mStep*$cornerStep));

			}elseif($vis == "square"){

				$x1 = $x*$step_size[$step_max-$z0]-$max;
				$y1 = $y*$step_size[$step_max-$z0]-$max;

				$x2 = $x1+$step_size[$step_max-$z0];
				$y2 = $y1+$step_size[$step_max-$z0];

				if($_GET["max_x"]<$_GET["min_x"]){
					$x1 += $world*2*$max;
					$x2 += $world*2*$max;
				}


				array_push($tarray, array($x1, $y1));
				array_push($tarray, array($x2, $y1));
				array_push($tarray, array($x2, $y2));
				array_push($tarray, array($x1, $y2));
			}

			//Calculate the range step
			$s = floor(((double)$row[0])/$size)+1;
			if($s > (count($colors)-1)){
				$s = (count($colors)-1);
			}

			//Add the square to the range step array
			array_push($stepps[$s], $tarray);

		}
		sql_close($result);
		$mysqli->next_result();

		if(isset($_GET["time"])&&($_GET["time"]=="all")){
			echo "id: " . $lastEventId . PHP_EOL;
		}

		//now we take each range step and turn it into a geoJSON
		if(isset($_GET["time"])&&($_GET["time"]=="all")){
			echo 'data:';
		}
		echo '[';
		$first = true;
		foreach ($stepps as $key => $step) {
		if($step != null){
			if(!$first){ echo ','; }else{ $first = false; }
				$color = $colors[$key];
				?>{"type": "Feature", "properties":{"style": {"stroke": "false", "weight":"0", "color":"#000", "fillColor":"#<?php echo $color; ?>", "opacity":"0", "fillOpacity":"0.5"}}, "geometry": {"type": "MultiPolygon","coordinates": <?php echo json_encode(array($step)); ?>},"crs": {"type": "name","properties": {"name": "urn:ogc:def:crs:SR-ORG::7483"}}}<?php
			}
		}
		echo ']'."\n".PHP_EOL;

		//Output time frame
  		flush();

		//Next Time frame
		$lastEventId++;

		if(($lastEventId>$max_time)||is_numeric($_GET["time"])){
			if(isset($_GET["time"])&&($_GET["time"]=="all")){
				echo "id: " . $lastEventId . PHP_EOL;
				echo "event: close" . PHP_EOL;
			}
  			flush();

			die();
			exit;
		}

	} while(true);
}

$mysqli->close();

?>