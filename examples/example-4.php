<?php

/*

example-3.php

Square-Heatmat
With Dots underneath

*/

require_once("../config.php");

$geojson_webmer = array();

//If you want to show all points add the number of points you want to show here:
$db_max = 20000;

$sql = 'SELECT `'.$db_lat.'`, `'.$db_lng.'`, `x`, `y` FROM `'.$db_table.'` WHERE `validconversion` = 1 ORDER BY `'.$db_id.'` ASC LIMIT '.$db_max;
$result = query_mysql($sql, $link);
if ($result) {
	while ($row = mysql_fetch_assoc($result)) {
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
    <script src="js/fn.js"></script>
    <script>
    	var map, layer, layer_webmer, request;
    	$(document).ready(function() {
    		layer_webmer = L.layerGroup();
    		map = L.map('map',{layers: [layer_webmer]}).setView([52.5, 13.4], 13);
			var layer = L.layerGroup();
    		layer.addTo(map);
			L.control.layers(null,{"WebMercator":layer_webmer, "Heatmap":layer}).addTo(map);

    		map.on('zoomend', function(e){ setHeatmap(); });
    		map.on('dragend', function(e){ setHeatmap(); });
    		map.on('moveend', function(e){ setHeatmap(); });
    		
    		var osmAttr = 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>';
			var mqTilesAttr = 'Tiles &copy; <a href="http://www.mapquest.com/" target="_blank">MapQuest</a> <img src="http://developer.mapquest.com/content/osm/mq_logo.png" />';
			L.tileLayer('http://otile{s}.mqcdn.com/tiles/1.0.0/map/{z}/{x}/{y}.png',{subdomains: '1234',attribution: osmAttr + ', ' + mqTilesAttr}).addTo(map);

			Proj4js.defs["SR-ORG:7483"] = "+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +wktext  +no_defs";

			var geojson_webmer = {
				"type": "Feature",
				"geometry": {
					"type": "MultiPoint",
					"coordinates": <?php echo json_encode($geojson_webmer); ?>
				},
				"crs": {"type": "name","properties": {"name": "urn:ogc:def:crs:SR-ORG::7483"}}
			};

			var options_webmer = {radius: 3, fillColor: "#000000", color: "#000000", weight: 0, opacity: 0, fillOpacity: 0.7};

			L.Proj.geoJson(geojson_webmer, {'pointToLayer': function(feature, latlng) {return L.circleMarker(latlng, options_webmer);}}).addTo(layer_webmer);

			function setHeatmap(){
				var bounds = map.getBounds();
				var ne = ToWebMercator(bounds.getNorthEast().lng, bounds.getNorthEast().lat);
				var sw = ToWebMercator(bounds.getSouthWest().lng, bounds.getSouthWest().lat);
				ne[0]+=20037508;ne[1]+=20037508;sw[0]+=20037508;sw[1]+=20037508;
				if(ne[0]>sw[0]){max_x = ne[0];min_x = sw[0];}else{max_x = sw[0];min_x = ne[0];}
				if(ne[1]>sw[1]){max_y = ne[1];min_y = sw[1];}else{max_y = sw[1];min_y = ne[1];}
				var trequest = "deliver_hex.php?zoom="+map.getZoom()+"&min_y="+min_y+"&max_y="+max_y+"&min_x="+min_x+"&max_x="+max_x;
				if(trequest!=request){
					request = trequest;
					$.getJSON(request, function( data ) {
						var geo;
						layer.clearLayers();
						for(var i = 0; i<data.length; i++){
							geo = L.Proj.geoJson(data[i], { style: function(feature){ return feature.properties && feature.properties.style; }}).on('dblclick', function(event){map.panTo(event.latlng);map.zoomIn();});
							geo.addTo(layer);
						}
					});
				}
			}

			setHeatmap();
		});
    </script>
  </body>
</html>