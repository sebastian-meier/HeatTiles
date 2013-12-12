<?php

/*

example-2.php
Show all points on a map
one layer in lat/lng
one layer in WebMercator

!!not all of your points are shown
!!due to performance, see $db_max
*/

require_once("../config.php");

$geojson_latlng = array();
$geojson_webmer = array();

$sql = 'SELECT `'.$db_lat.'`, `'.$db_lng.'`, `x`, `y` FROM `'.$db_table.'` WHERE `validconversion` = 1 ORDER BY `'.$db_id.'` ASC LIMIT '.$db_max;
$result = query_mysql($sql, $link);
if ($result) {
	while ($row = mysql_fetch_assoc($result)) {
		array_push($geojson_latlng, array($row[$db_lng], $row[$db_lat]));
		array_push($geojson_webmer, array($row["x"], $row["y"]));
	}
}
mysql_free_result($result);

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
    	var map, layer_geo, layer_webmer;
    	$(document).ready(function() {
    		layer_geo = L.layerGroup();
    		layer_webmer = L.layerGroup();
    		map = L.map('map',{layers: [layer_geo, layer_webmer]}).setView([52.5, 13.4], 12);
			L.control.layers(null,{"WebMercator":layer_webmer, "Geographic":layer_geo}).addTo(map);
    		
    		var osmAttr = 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>';
			var mqTilesAttr = 'Tiles &copy; <a href="http://www.mapquest.com/" target="_blank">MapQuest</a> <img src="http://developer.mapquest.com/content/osm/mq_logo.png" />';
			L.tileLayer('http://otile{s}.mqcdn.com/tiles/1.0.0/map/{z}/{x}/{y}.png',{subdomains: '1234',attribution: osmAttr + ', ' + mqTilesAttr}).addTo(map);

			Proj4js.defs["SR-ORG:7483"] = "+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +wktext  +no_defs";

			var geojson_latlng = {
				"type": "Feature",
				"geometry": {
					"type": "MultiPoint",
					"coordinates": <?php echo json_encode($geojson_latlng); ?>
				}
			};

			var geojson_webmer = {
				"type": "Feature",
				"geometry": {
					"type": "MultiPoint",
					"coordinates": <?php echo json_encode($geojson_webmer); ?>
				},
				"crs": {"type": "name","properties": {"name": "urn:ogc:def:crs:SR-ORG::7483"}}
			};

			var options_latlng = {radius: 5, fillColor: "#ff0000", color: "#ff0000", weight: 1, opacity: 0, fillOpacity: 1};
			var options_webmer = {radius: 5, fillColor: "#000000", color: "#000000", weight: 1, opacity: 1, fillOpacity: 0};

			L.geoJson(geojson_latlng, {'pointToLayer': function(feature, latlng) {return L.circleMarker(latlng, options_latlng);}}).addTo(layer_geo);
			L.Proj.geoJson(geojson_webmer, {'pointToLayer': function(feature, latlng) {return L.circleMarker(latlng, options_webmer);}}).addTo(layer_webmer);

		});
    </script>
  </body>
</html>