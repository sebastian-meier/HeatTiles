<?php

/*

example-1.php
Every zoom-level has a specific grid.
This is a test for showing the grid.
The Tile-Size is defined by 2.5

*/

ini_set("precision", 20);

$max = 20037508;

$grids = array();
for($i = 17; $i<21; $i++){
	$step_size = (2.5 * pow(2, $i));
	$tgrid = array();
	for($x = -1*$max; $x<$max; $x+=$step_size){
		$grid = array();
		for($y = -1*$max; $y<$max; $y+=$step_size){
			array_push($grid, array($y, $x));
		}
		array_push($tgrid, $grid);
	}
	for($y = -1*$max; $y<$max; $y+=$step_size){
		$grid = array();
		for($x = -1*$max; $x<$max; $x+=$step_size){
			array_push($grid, array($y, $x));
		}
		array_push($tgrid, $grid);
	}
	array_push($grids, $tgrid);
}

$geo_max_x = 90;
$geo_max_y = 180;
$geo_grids = array();
for($i = 17; $i<21; $i++){
	$step_size = (0.00002245 * pow(2, $i));
	$tgeo_grids = array();
	for($x = -1*$geo_max_x; $x<$geo_max_x; $x+=$step_size){
		$grid = array();
		for($y = -1*$geo_max_y; $y<$geo_max_y; $y+=$step_size){
			array_push($grid, array($y, $x));
		}
		array_push($tgeo_grids, $grid);
	}
	for($y = -1*$geo_max_y; $y<$geo_max_y; $y+=$step_size){
		$grid = array();
		for($x = -1*$geo_max_x; $x<$geo_max_x; $x+=$step_size){
			array_push($grid, array($y, $x));
		}
		array_push($tgeo_grids, $grid);
	}
	array_push($geo_grids, $tgeo_grids);
}

function ToWebMercator($mercatorY_lat, $mercatorX_lon){
    if((abs($mercatorX_lon) > 180 || abs($mercatorY_lat) > 90)){
        return;
	}

    $x = 6378137.0 * ($mercatorX_lon * 0.017453292519943295);
    $a = $mercatorY_lat * 0.017453292519943295;
    $y = 3189068.5 * log((1.0 + sin($a)) / (1.0 - sin($a)));

    return array($y, $x);
}

?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <style>
    	body,html{margin:0;padding:0;width:100%;height:100%;}
    	#map{width:100%;height:100%;}
    </style>
    <link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.7.1/leaflet.css" />
  </head>
  <body>
    <div id="map"></div>
	<script src="http://cdn.leafletjs.com/leaflet-0.7.1/leaflet.js"></script>
	<script src="http://code.jquery.com/jquery-2.0.3.min.js"></script>
    <script src="js/proj4js-compressed.js"></script>
    <script src="js/proj4leaflet.js"></script>
    <script>
    	var map, layer, geolayer;
    	$(document).ready(function() {
    		layer = L.layerGroup();
    		geolayer = L.layerGroup();
    		map = L.map('map',{layers: [layer]}).setView([82.5, 13.4], 2);
    		L.control.layers({"WebMercator":layer, "Geographic":geolayer}).addTo(map);

    		
    		var osmAttr = 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>';
			var mqTilesAttr = 'Tiles &copy; <a href="http://www.mapquest.com/" target="_blank">MapQuest</a> <img src="http://developer.mapquest.com/content/osm/mq_logo.png" />';
			L.tileLayer('http://otile{s}.mqcdn.com/tiles/1.0.0/map/{z}/{x}/{y}.png',{subdomains: '1234',attribution: osmAttr + ', ' + mqTilesAttr}).addTo(map);

			Proj4js.defs["SR-ORG:7483"] = "+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +wktext  +no_defs";

			updateGrid();

			map.on("zoomend", function(){
				updateGrid();
			});

    	});

    	var grid = new Array();
    	var geogrid = new Array();
<?php 
for($i = count($grids)-1; $i>=0; $i--){
	echo '    	grid['.(count($grids)-$i-1).'] = {"type": "Feature","geometry": {"type": "MultiLineString","coordinates":'.json_encode($grids[$i]).'},"crs": {"type": "name","properties": {"name": "urn:ogc:def:crs:SR-ORG::7483"}}}';
	echo ';'."\n";
}
for($i = count($geo_grids)-1; $i>=0; $i--){
	echo '    	geogrid['.(count($geo_grids)-$i-1).'] = {"type": "Feature","geometry": {"type": "MultiLineString","coordinates":'.json_encode($geo_grids[$i]).'}}';
	echo ';'."\n";
}
?>

    	function updateGrid(){
    		var z = map.getZoom();
    		if(z<4){
				layer.clearLayers();
				geolayer.clearLayers();
				L.Proj.geoJson(grid[z], {style: {"color": "#000000","weight": 1,"opacity": 0.65}}).addTo(layer);
				L.geoJson(geogrid[z], {style: {"color": "#ff0000","weight": 1,"opacity": 0.65}}).addTo(geolayer);
			}
    	}
    </script>
  </body>
</html>