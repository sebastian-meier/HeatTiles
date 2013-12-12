<html>
<head>
</head><?php

require_once("config.php");

$sql = 'SELECT * FROM `'.$db_table.'`';
$result = mysql_query($sql, $link);
$db_size = mysql_num_rows($result);
mysql_free_result($result);

if(isset($_GET["step"])){$step = $_GET["step"];}else{$step = 0;}
if(isset($_GET["page"])){$page = $_GET["page"];}else{$page = 1;}

if($step==0){
	$message .= 'Welcome to the data conversion and rasterization.<br />';
	$message .= '<a href="convert.php?step=1">Start Process</a>';
}else if($step==1){
/*------------------- STEP #1 -------------------*/
//In the first step we need to add new fields to the database
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

	$sql = 'ALTER TABLE `'.$db_table.'` DROP `validconversion`, DROP `x`, DROP `y`, DROP `x0`, DROP `y0`';
	for($i = 1; $i<21; $i++){ $sql .=  ', DROP `z'.$i.'`'; }
	for($i = 1; $i<21; $i++){ $sql .=  ', DROP `zh'.$i.'`'; }
	$result = mysql_query($sql, $link);

	$sql = 'ALTER TABLE `'.$db_table.'` ADD `validconversion` tinyint(1) NOT NULL, ADD `x` DOUBLE(21,12) NOT NULL, ADD `y` DOUBLE(21,12) NOT NULL, ADD `x0` DOUBLE(21,12) NOT NULL, ADD `y0` DOUBLE(21,12) NOT NULL';
	for($i = 1; $i<21; $i++){ $sql .=  ', ADD `z'.$i.'` BIGINT(20) NOT NULL'; }
	for($i = 1; $i<21; $i++){ $sql .=  ', ADD `zh'.$i.'` BIGINT(20) NOT NULL'; }
	$result = query_mysql($sql, $link);
	if ($result) {
		$message .= 'Datebase was successfully modified!<br />';
		$message .= '<a href="convert.php?step=2">Continue</a>';
	}
	mysql_free_result($result);

//Flag Rows that have no valid latitude / longitude values
	$sql = 'UPDATE `'.$db_table.'` SET `validconversion` = 1';
	$result = query_mysql($sql, $link);
	mysql_free_result($result);

	$sql = 'UPDATE `'.$db_table.'` SET `validconversion` = 0 WHERE `'.$db_lat.'` = 0 OR `'.$db_lng.'` = 0 OR `'.$db_lng.'` > 180 OR `'.$db_lat.'` > 90';
	$result = query_mysql($sql, $link);
	mysql_free_result($result);

}else if($step==2){
/*------------------- STEP #2 -------------------*/
//In the First Conversion Step we convert all Latitude/Longitude values to WebMercator

	$sql = 'SELECT `'.$db_id.'`, `'.$db_lat.'`, `'.$db_lng.'` FROM `'.$db_table.'` ORDER BY `'.$db_id.'` ASC LIMIT '.(($page-1)*$db_max).', '.$db_max;
	$result = query_mysql($sql, $link);
	if ($result) {
		while ($row = mysql_fetch_assoc($result)) {
	    	$latlon = ToWebMercator($row[$db_lat], $row[$db_lng]);
	    	$sql = 'UPDATE `'.$db_table.'` SET `x` = '.$latlon[1].', `y` = '.$latlon[0].', `x0` = '.($latlon[1]+$max).', `y0` = '.($latlon[0]+$max).' WHERE `'.$db_id.'` = '.$row[$db_id];
			$update = query_mysql($sql, $link);
			mysql_free_result($update);
		}
		if(($page*$db_max) > $db_size){
			$message .= 'Conversion to WebMercator was successfully!<br />';
			$message .= '<a href="convert.php?step=3">Continue</a>';
		}else{
			$redirect = 'convert.php?step=2&page='.($page+1);
			$message .= $page.'/'.ceil($db_size/$db_max).'<br />';
		}
	}
	mysql_free_result($result);

}else if($step==3){
/*------------------- STEP #3 -------------------*/
//Rasterizing locations into the tiled grid

	$sql = 'SELECT `'.$db_id.'`, `x0`, `y0` FROM `'.$db_table.'` WHERE `validconversion` = 1 ORDER BY `'.$db_id.'` ASC LIMIT '.(($page-1)*$db_max).', '.$db_max;
	$result = query_mysql($sql, $link);
	if ($result) {
		while ($row = mysql_fetch_assoc($result)) {
	    	//normal tiles
			for($i = 0; $i<20; $i++){
				$x = ceil($row["x0"]/$step_size[19-$i]);
				$y = ceil($row["y0"]/$step_size[19-$i]);
				$cell = (($y-1)*$steps[19-$i]+$x); //-1
				$sql = 'UPDATE `'.$db_table.'` SET `z'.($i+1).'` = '.$cell.' WHERE `'.$db_id.'` = '.$row[$db_id];
				$update = query_mysql($sql, $link);
				mysql_free_result($update);
			}

			//offset tiles (hex tiles 50% y-offset)
			for($i = 0; $i<20; $i++){
				$y = ceil($row["y0"]/$step_size[19-$i]);
				if($y%2){
					$x = ceil(($row["x0"]+$step_size[19-$i]/2)/$step_size[19-$i]);
				}else{
					$x = ceil($row["x0"]/$step_size[19-$i]);
				}
				$cell = (($y-1)*$steps[19-$i]+$x); //-1
				$sql = 'UPDATE `'.$db_table.'` SET `zh'.($i+1).'` = '.$cell.' WHERE `'.$db_id.'` = '.$row[$db_id];
				$update = query_mysql($sql, $link);
				mysql_free_result($update);
			}

		}
		if(($page*$db_max) > $db_size){
			$message .= 'Rasterization successfully!<br />';
			$message .= '<a href="convert.php?step=4">Continue</a>';
		}else{
			$message .= $page.'/'.ceil($db_size/$db_max).'<br />';
			$redirect = 'convert.php?step=3&page='.($page+1);
		}
	}
	mysql_free_result($result);

}else if($step==4){
/*------------------- STEP #4 -------------------*/
//Finally we need to find out the maximum amount of locations per tile per zoom level and store it in a new database.

//Create new database if it doesn't exists yet

	$sql = 'CREATE TABLE IF NOT EXISTS `'.$db_max_table.'` (`id` int(11) NOT NULL AUTO_INCREMENT, `zoom` int(11) NOT NULL, `key` text COLLATE latin1_german2_ci NOT NULL, `value` text COLLATE latin1_german2_ci NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci';
	$result = query_mysql($sql, $link);
	mysql_free_result($result);

//Clear Database
	$sql = 'TRUNCATE TABLE `'.$db_max_table;
	$result = query_mysql($sql, $link);
	mysql_free_result($result);

//Now add zoom level values
	for($i=1; $i<21; $i++){
		$sql = 'SELECT COUNT(*) FROM `'.$db_table.'` WHERE `validconversion` = 1 GROUP BY `z'.$i.'` ORDER BY COUNT(*) DESC LIMIT 0,1';
		$result = query_mysql($sql, $link);
		if($result) {
			while ($row = mysql_fetch_array($result)) {
				$sql = 'INSERT INTO `'.$db_max_table.'` (`zoom`, `value`, `key`)VALUES('.$i.', '.$row[0].', "max")';
				$insert = query_mysql($sql, $link);
				mysql_free_result($insert);
			}
		}
		$sql = 'SELECT COUNT(*) FROM `'.$db_table.'` WHERE `validconversion` = 1 GROUP BY `zh'.$i.'` ORDER BY COUNT(*) DESC LIMIT 0,1';
		$result = query_mysql($sql, $link);
		if ($result) {
			while ($row = mysql_fetch_array($result)) {
				$sql = 'INSERT INTO `'.$db_max_table.'` (`zoom`, `value`, `key`)VALUES('.$i.', '.$row[0].', "maxh")';
				$insert = query_mysql($sql, $link);
				mysql_free_result($insert);
			}
		}
		mysql_free_result($result);
	}

	$message .= 'Summary done!<br />';
	$message .= '<a href="convert.php?step=5">Continue</a>';

}else if($step==5){
/*------------------- STEP #5 -------------------*/
//YAY we are done.
	$message .= 'We are done!<br />';
	$message .= 'To see if everything worked out, take a look at the example-implementations:<br />';
	$message .= '<a href="examples/index.html">Examples</a>';
}

?>
<body onload="redirect()">
<?php echo $message; ?>
	<script language=javascript>
		function redirect(){
<?php if($redirect){ ?>
			window.location = "<?php echo $redirect; ?>";
<?php } ?>
		}
	</script>
</body>
</html>