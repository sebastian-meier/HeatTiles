<?php

require_once("config_local.php");

//Pagination
if(!isset($_SESSION["last_id"])){
	$_SESSION["last_id"] = 0;
}

//Receive the database size for pagination
//And store it in the session for performance
if(!isset($_SESSION["db_size"])){
	$sql = 'SELECT COUNT(*) FROM `'.$db_table.'`';
	$result = sql_request($sql, false);
	$row = $result->fetch_row();
	$db_size = $row[0];
	$_SESSION["db_size"] = $db_size;
	$result->close();
	$mysqli->next_result();
	$message .= $db_size.' (new)<br />';
}else{
	$db_size = $_SESSION["db_size"];
	$message .= $db_size.' (stored)<br />';
}

if(isset($_GET["step"])){$step = $_GET["step"];}else{$step = 0;}
if(isset($_GET["page"])){$page = $_GET["page"];}else{$page = 1;}

if($step==0){
	$message .= 'Welcome to the data conversion and rasterization.<br />';
	$message .= '<a href="convert.php?step=1">Start Process</a>';
}else if($step==1){

/*------------------- STEP #1 -------------------*/
//In the first step we need to add a table to the database
//COLUMNS:

//Lat/Lng-Conversion in WebMercator Projection
//x, double(21,12)
//y, double(21,12)

//Lat/Lng-Conversion in WebMercator Projection
//The center of the coordinate-system if moved to the upper left
//By doing so, we only have values > 0, for easier queries
//x0, double(21,12)
//y0, double(21,12)

//Zoom-Level 0-20, normal grid
//z0 - z20, bigint(20)

//Zoom-Level 0-20, grid with offset in rows for hexagon-visualization
//zh0 - zh20, bigint(20)

	$sql = 'CREATE TABLE `'.$db_conversion_table.'` (';
	$columns = array('ref_id', 'x', 'y', 'x0', 'y0');
	for($i = $zoom_min; $i<$zoom_max; $i++){ array_push($columns, 'z'.$i); }
	for($i = $zoom_min; $i<$zoom_max; $i++){ array_push($columns, 'zh'.$i); }

	foreach ($columns as $column) {
		if($column == 'ref_id'){
			$specs = ' INT(10) NOT NULL';
		}elseif(($column == 'y')||($column == 'x')||($column == 'y0')||($column == 'x0')){
			$specs = ' DOUBLE(21,12) NULL';
		}else{
			$specs = ' BIGINT(20) NULL';
		}
		$sql .= ' `'.$column.'` '.$specs.', ';
	}

	$sql .= 'UNIQUE ( `ref_id` ))';

	$result = sql_request($sql, false);
	if(!$result){
		printf("Error: %s\n", $mysqli->error);
		exit();
	}

	$result = sql_request('TRUNCATE TABLE `'.$db_conversion_table.'`', false);
	if($result){
		$_SESSION["last_id"] = 0;
		$message .= 'Datebase was successfully created!<br />';
		$message .= '<a href="convert.php?step=2">Continue</a>';
	}else{
		printf("Error: %s\n", $mysqli->error);
		exit();
	}

}else if($step==2){
/*------------------- STEP #2 -------------------*/
//In the First Conversion Step we convert all Latitude/Longitude values to WebMercator

	$end = false;
	$msql = '';
	$msqls = array();
	$sql = 'SELECT `'.$db_id.'`, `'.$db_lat.'`, `'.$db_lng.'` FROM `'.$db_table.'` WHERE `ref_id` > '.$_SESSION["last_id"].' ORDER BY `'.$db_id.'` ASC LIMIT '.$db_max;
	$result = sql_request($sql, false);
	if ($result && $result->num_rows >= 1) {
		while ($row = $result->fetch_assoc()) {
			if($row[$db_lng] < -180){
				$row[$db_lng] = 360 + $row[$db_lng];
			}
	    	
	    	//Coordinate Conversion
	    	$latlon = ToWebMercator($row[$db_lat], $row[$db_lng]);
	    	$msql .= 'INSERT INTO `'.$db_conversion_table.'` (`x`, `y`, `x0`, `y0`, `ref_id`)VALUES('.$latlon[1].', '.$latlon[0].', '.($latlon[1]+$max).', '.($latlon[0]+$max).', '.$row[$db_id].');';

	    	if(strlen($msql)>(1048576*0.75)){
				array_push($msqls, $msql);
				$msql = "";
			}
		}
		array_push($msqls, $msql);
	}elseif($result->num_rows<1){
		$end = true;
	}else{
		printf("Error: %s\n", $mysqli->error);
		exit();
	}
	$result->close();
	$mysqli->next_result();

	foreach ($msqls as $msql) {
		exec_multi($msql);		
	}

	if($end){
		$_SESSION["last_id"] = 0;
		$message .= 'Conversion to WebMercator was successfully!<br />';
		$message .= '<a href="convert.php?step=3">Continue</a>';
	}else{
		$redirect = 'convert.php?step=2&page='.($page+1);
		$message .= $page.'/'.ceil($db_size/$db_max).'<br />';
	}

}else if($step==3){
/*------------------- STEP #3 -------------------*/
//Rasterizing locations into the tiled grid

	$end = false;
	$msqls = array();
	$msql = "";
	$sql = 'SELECT `ref_id`, `x0`, `y0` FROM `'.$db_conversion_table.'` WHERE `ref_id` > '.$_SESSION["last_id"].' ORDER BY `ref_id` ASC LIMIT '.$db_max;
	$result = sql_request($sql, false);
	if ($result && $result->num_rows >= 1) {
		while ($row = $result->fetch_assoc()) {
			$tsql = "UPDATE `".$db_conversion_table."` SET ";
			$ftsql = true;
			for($i = $zoom_min; $i<$zoom_max; $i++){

		    	//normal tiles
				$x = ceil($row["x0"]/$step_size[($zoom_max-1)-$i]);
				$y = ceil($row["y0"]/$step_size[($zoom_max-1)-$i]);
				$cell = (($y-1)*$steps[($zoom_max-1)-$i]+$x); //-1

				//offset tiles (hex tiles 50% y-offset on even rows)
				$y = ceil($row["y0"]/$step_size[($zoom_max-1)-$i]);
				if($y%2){
					$x = ceil(($row["x0"]+$step_size[($zoom_max-1)-$i]/2)/$step_size[($zoom_max-1)-$i]);
				}else{
					$x = ceil($row["x0"]/$step_size[($zoom_max-1)-$i]);
				}
				$cellh = (($y-1)*$steps[($zoom_max-1)-$i]+$x); //-1
				
				if($ftsql){
					$ftsql = false;
				}else{
					$tsql .= ' , ';
				}

				$tsql .= '`zh'.$i.'` = '.$cellh.', `z'.$i.'` = '.$cell;

			}

			$tsql .= ' WHERE `ref_id` = '.$row["ref_id"].';';
			$_SESSION["last_id"] = $row["ref_id"];

			$msql .= $tsql;

			if(strlen($msql)>(1048576*0.75)){
				array_push($msqls, $msql);
				$msql = "";
			}

		}
		array_push($msqls, $msql);
	}elseif($result->num_rows<1){
		$end = true;
	}else{
		printf("Error: %s\n", $mysqli->error);
		exit();
	}
	$result->close();
	$mysqli->next_result();

	foreach ($msqls as $msql) {
		exec_multi($msql);		
	}

	if($end){
		$message .= 'Rasterization successfully!<br />';
		$message .= '<a href="convert.php?step=4">Continue</a>';
	}else{
		$message .= $page.'/'.ceil($db_size/$db_max).'<br />';
		$redirect = 'convert.php?step=3&page='.($page+1);
	}


}else if($step==4){
/*------------------- STEP #4 -------------------*/
//Finally we need to find out the maximum amount of locations per tile per zoom level and store it in a new database.
//If you added more features than "SUM" to $clustering than this will be done here as well.

//Create new database if it doesn't exists yet

	if($page < 1){
		$sql = 'CREATE TABLE IF NOT EXISTS `'.$db_max_table.'` (`id` int(11) NOT NULL AUTO_INCREMENT, `ref_id` int(11) NOT NULL, `time` int(11) NOT NULL, `zoom` int(11) NOT NULL, `key` text NOT NULL, `value` DOUBLE(21,12) NULL, PRIMARY KEY (`id`))';
		$result = sql_request($sql, false);
		if(!$result){
			printf("Error: %s\n", $mysqli->error);
			exit();
		}

	//Clear Database
		$sql = 'TRUNCATE TABLE `'.$db_max_table;
		$result = sql_request($sql, false);
		if(!$result){
			printf("Error: %s\n", $mysqli->error);
			exit();
		}
	}

//This sql request is creating summaries by grouping grid-cells on every zoom level and findind max/min/... within those groups
//If time and/or multi is activated the grouping is done for every timeframe/subset
	$sql = "SELECT";
	
	if($time){
		$sql .= " `count_max`.`time` AS `time`,";
	}

	if($multiple){
		$sql .= " `count_max`.`id` AS `id`,";
	}

	$sql .= "\n";

	if(in_array("COUNT", $clustering)||in_array("COUNT_MAX", $clustering)||in_array("COUNT_MIN", $clustering)||in_array("COUNT_AVG", $clustering)){
		$sql .= " 					SUM(`count_max`.`countmax`) AS `count`, 
					MAX(`count_max`.`countmax`) AS `max`, 
					MIN(`count_max`.`countmax`) AS `min`, 
					AVG(`count_max`.`countmax`) AS `avg`,";
					$sql .= "\n";
	}

	if(in_array("SUM", $clustering)||in_array("MIN", $clustering)||in_array("MAX", $clustering)||in_array("AVG", $clustering)){
		$sql .= "					SUM(`count_max`.`sum_value`) AS `value_sum`,
					MIN(`count_max`.`min_value`) AS `value_min`,
					MAX(`count_max`.`max_value`) AS `value_max`,
					AVG(`count_max`.`avg_value`) AS `value_avg`";
					$sql .= "\n";
	}

	$sql .= " 	FROM 
				(SELECT ";

	if($time){
		$sql .= " `ref_table`.`".$time_col."` AS `time`,";
	}

	if($multiple){
		$sql .= " `ref_table`.`".$multiple_ref_col."` AS `id`,";
	}

	if(in_array("COUNT", $clustering)||in_array("COUNT_MAX", $clustering)||in_array("COUNT_MIN", $clustering)||in_array("COUNT_AVG", $clustering)){
		$sql .= '	COUNT(*) AS `countmax`,';
	}
	if(in_array("SUM", $clustering)||in_array("MIN", $clustering)||in_array("MAX", $clustering)||in_array("AVG", $clustering)){
		$sql .= '	SUM(`ref_table`.`'.$cluster_value_col.'`) AS `sum_value`,
					MIN(`ref_table`.`'.$cluster_value_col.'`) AS `min_value`,
					MAX(`ref_table`.`'.$cluster_value_col.'`) AS `max_value`,
					AVG(`ref_table`.`'.$cluster_value_col.'`) AS `avg_value`';
    }

    $sql .= ' 	FROM 
					`'.$db_conversion_table.'` 
				INNER JOIN 
					`'.$db_table.'` AS `ref_table` 
				ON `ref_table`.`'.$db_id.'` =  `'.$db_conversion_table.'`.`ref_id` 
				GROUP BY ';

	if($time){
		$sql .= '`ref_table`.`'.$time_col.'`,';
	}

	if($multiple){
		$sql .= '`ref_table`.`'.$multiple_ref_col.'`,';
	}

    $sql .= '`z'.($page+$zoom_min).'`
              ) 
			AS 
				`count_max`';

	if($time||$multiple){
		$sql .= " GROUP BY ";
		if($time){
			$sql .= '`count_max`.`time`';
		}

		if($time && $multiple){
			$sql .= ",";
		}

		if($multiple){
			$sql .= '`count_max`.`id`';
		}
    }


	$msql = '';
	$msqls = array();

	$result = sql_request($sql, false);
	if($result){
	    while($row = $result->fetch_assoc()){

			foreach ($row as $key => $value) {
				$id = 0;
				$time = 0;
				if(isset($row["id"])){
					$id = $row["id"];
				}
				if(isset($row["time"])){
					$time = $row["time"];
				}
				if(($key != "time")&&($key != "id")){
					//The results are now stored in the meta table
					//zoom
					//key holds the attribute
					//value
					//ref_id is for multiple datasets in one table
					//time
					$msql .= 'INSERT INTO `'.$db_max_table.'` (`zoom`, `value`, `key`, `ref_id`, `time`)VALUES('.($page+$zoom_min).', '.$value.', "'.$key.'", '.$id.', '.$time.');';
					if(strlen($msql)>(1048576*0.75)){
						array_push($msqls, $msql);
						$msql = "";
					}
				}				
			}
			
		}
		array_push($msqls, $msql);
	}else{
	    printf("Error: %s\n", $mysqli->error);
		exit();
	}

	$result->close();
	$mysqli->next_result();

	foreach ($msqls as $msql) {
		exec_multi($msql);		
	}

	if($page+$zoom_min < $zoom_max){

		if($time){
			//Through the interval we calculated the meta data for every individual time frame
			//Now we need to Calculate the overall meta data 

			$msql = '';
			$msqls = array();
			for($i = $zoom_min; $i<$zoom_max; $i++){
				foreach ($clustering as $value) {
					switch ($value) {
						case 'COUNT_MAX':
							$selection = 'MAX(`value`)';
							$where = 'max';
							break;

						case 'COUNT_MIN':
							$selection = 'MIN(`value`)';
							$where = 'min';
							break;

						case 'COUNT_AVG':
							$selection = 'AVG(`value`)';
							$where = 'avg';
							break;

						case 'COUNT':
							$selection = 'SUM(`value`)';
							$where = 'count';
							break;

						case 'MAX':
							$selection = 'MAX(`value`)';
							$where = 'value_max';
							break;

						case 'MIN':
							$selection = 'MIN(`value`)';
							$where = 'value_min';
							break;

						case 'AVG':
							$selection = 'AVG(`value`)';
							$where = 'value_avg';
							break;

						case 'SUM':
							$selection = 'SUM(`value`)';
							$where = 'value_sum';
							break;
						
						default:
							$selection = 'COUNT(`value`)';
							$where = 'count';
							break;
					}

					$msql .= 'SELECT '.$selection.' AS `calc_value`, `key`, `zoom`, `ref_id` FROM `'.$db_max_table.'` WHERE `zoom` = '.$i.' AND `key` = "'.$where.'" GROUP BY `ref_id`;';	

				}
			}

			if($mysqli->multi_query($msql)){
				$msql = '';
				$msqls = array();
				do {
					if($result = $mysqli->store_result()){
						while ($row = $result->fetch_assoc()){
							//time = -1 marks the timing overview metadata
							$msql .= 'INSERT INTO `'.$db_max_table.'` (`zoom`, `value`, `key`, `ref_id`, `time`)VALUES('.$row["zoom"].', '.$row["calc_value"].', "'.$row["key"].'", '.$row["ref_id"].', -1);';

							if(strlen($msql)>(1048576*0.75)){
								array_push($msqls, $msql);
								$msql = "";
							}

						}
					}else{
						printf("Error: %s\n", $mysqli->error);
						exit();
					}
					$result->close();

					if (!$mysqli->more_results()) {
						break;
					}

					if (!$mysqli->next_result()) {
						printf("Error: %s\n", $mysqli->error);
						exit();
					}

				} while (true);

			}else{
				printf("Error: %s\n", $mysqli->error);
				exit();
			}

			array_push($msqls, $msql);

			foreach ($msqls as $msql) {
				exec_multi($msql);		
			}

		}

		$message .= 'Summary done!<br />';
		$message .= '<a href="convert.php?step=5">Continue</a>';
	}else{
		$message .= ($page+$zoom_min).'/'.$zoom_max.'<br />';
		$redirect = 'convert.php?step=4&page='.($page+1);
	}

}else if($step==5){
/*------------------- STEP #5 -------------------*/
//YAY we are done.
	$message .= 'We are done!<br />';
	$message .= 'To see if everything worked out, take a look at the example-implementations:<br />';
	$message .= '<a href="examples/index.html">Examples</a>';
}

$mysqli->close();

?>
<html>
<head>
</head>
<body onload="redirect()">
<?php echo $message; ?>
	<script language=javascript>
		function redirect(){
<?php
if($redirect){
?>
			window.location = "<?php echo $redirect; ?>";
<?php
}
?>
		}
	</script>
</body>