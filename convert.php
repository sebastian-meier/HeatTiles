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
	$result = mysql_query($sql, $link);
	if (!$result) {
	    echo "DB Error, could not execute request\n";
	    echo 'MySQL Error: ' . mysql_error();
	    exit;
	}else{
		$message .= 'Datebase was successfully modified!<br />';
		$message .= '<a href="convert.php?step=2">Continue</a>';
	}
	mysql_free_result($result);

//Flag Rows that have no valid latitude / longitude values
	$sql = 'UPDATE `'.$db_table.'` SET `validconversion` = 1';
	$result = mysql_query($sql, $link);
	mysql_free_result($result);

	$sql = 'UPDATE `'.$db_table.'` SET `validconversion` = 0 WHERE `'.$db_lat.'` = 0 OR `'.$db_lng.'` = 0 OR `'.$db_lng.'` > 180 OR `'.$db_lat.'` > 90';
	$result = mysql_query($sql, $link);
	mysql_free_result($result);

}else if($step==2){
/*------------------- STEP #2 -------------------*/
//In the First Conversion Step we convert all Latitude/Longitude values to WebMercator

	$sql = 'SELECT `'.$db_id.'`, `'.$db_lat.'`, `'.$db_lng.'` FROM `'.$db_table.'` ORDER BY `'.$db_id.'` ASC LIMIT '.(($page-1)*$db_max).', '.$db_max;
	$result = mysql_query($sql, $link);
	if (!$result) {
	    echo "DB Error, could execute request\n";
	    echo 'MySQL Error: ' . mysql_error();
	    exit;
	}else{
		while ($row = mysql_fetch_assoc($result)) {
	    	$latlon = ToWebMercator($row[$db_lat], $row[$db_lng]);
	    	$sql = 'UPDATE `'.$db_table.'` SET `x` = '.$latlon[1].', `y` = '.$latlon[0].', `x0` = '.($latlon[1]+$max).', `y0` = '.($latlon[0]+$max).' WHERE `'.$db_id.'` = '.$row[$db_id];
			$update = mysql_query($sql, $link);
			if (!$update) {
	    		echo "DB Error, could not execute request\n";
	    		echo 'MySQL Error: ' . mysql_error();
	    		exit;
			}
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
	$result = mysql_query($sql, $link);
	if (!$result) {
	    echo "DB Error, could execute request\n";
	    echo 'MySQL Error: ' . mysql_error();
	    exit;
	}else{
		while ($row = mysql_fetch_assoc($result)) {
	    	//normal tiles
			for($i = 0; $i<20; $i++){
				$x = ceil($row["x0"]/$step_size[19-$i]);
				$y = ceil($row["y0"]/$step_size[19-$i]);
				$cell = (($y-1)*$steps[19-$i]+$x); //-1
				$sql = 'UPDATE `'.$db_table.'` SET `z'.($i+1).'` = '.$cell.' WHERE `'.$db_id.'` = '.$row[$db_id];
				$update = mysql_query($sql, $link);
				if (!$update) {
		    		echo "DB Error, could not execute request\n";
		    		echo 'MySQL Error: ' . mysql_error();
		    		exit;
				}
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
				$update = mysql_query($sql, $link);
				if (!$update) {
		    		echo "DB Error, could not execute request\n";
		    		echo 'MySQL Error: ' . mysql_error();
		    		exit;
				}
				mysql_free_result($update);
			}

		}
		if(($page*$db_max) > $db_size){
			$message .= 'Rasterization successfully!<br />';
			$message .= '<a href="convert.php?step=4">Continue</a>';
			$message .= $page.'/'.ceil($db_size/$db_max).'<br />';
		}else{
			$redirect = 'convert.php?step=3&page='.($page+1);
		}
	}
	mysql_free_result($result);

}else if($step==4){
/*------------------- STEP #4 -------------------*/
//YAY we are done.
	$message .= 'We are done!<br />';
	$message .= 'To see if everything worked out, take a look at the example-implementations:<br />';
	$message .= '<a href="examples/index.html">Examples</a>';
}

/*------------------- CONVERSION FUNCTIONS -------------------*/

function ToWebMercator($mercatorY_lat, $mercatorX_lng){
    if((abs($mercatorX_lng) > 180 || abs($mercatorY_lat) > 90)){
        return;
	}

    $x = 6378137.0 * ($mercatorX_lng * 0.017453292519943295);
    $a = $mercatorY_lat * 0.017453292519943295;
    $y = 3189068.5 * log((1.0 + sin($a)) / (1.0 - sin($a)));

    return array($y, $x);
}

function ToGeographic($mercatorY_lat, $mercatorX_lng){
	global $max;
    if ((abs($mercatorX_lng) > $max) || (abs($mercatorY_lat) > $max)){
    	return;
    }

    $x = $mercatorX_lng;
    $y = $mercatorY_lat;
    $num3 = $x / 6378137.0;
    $num4 = $num3 * 57.295779513082323;
    $num5 = floor((($num4 + 180.0) / 360.0));
    $num6 = $num4 - ($num5 * 360.0);
    $num7 = 1.5707963267948966 - (2.0 * atan(exp((-1.0 * $y) / 6378137.0)));
    $mercatorX_lng = $num6;
    $mercatorY_lat = $num7 * 57.295779513082323;

    return array($mercatorY_lat, $mercatorX_lng);
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