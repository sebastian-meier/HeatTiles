//Array of our earthquakes, actually we just use this to center our map over the earthquake
var quakes = [
	["PAK-ASC", "up2013ueba", "2013-10-15 00:12:35", 9.786595, 124.075],
	["PAK", "up2013ssol", "2013-09-24 11:29:48", 26.9424, 65.4466],
	["PAKISTAN", "up2013ueba", "2013-10-15 00:12:35", 9.786595, 124.075],
	["SEA-OF-OKHOTSK", "up2013kbnw", "2013-05-24 05:44:45", 54.893, 153.136],
	["SOUTH-IRAN-2", "up2013hknw", "2013-04-16 10:44:11", 27.9611, 62.0207],
	["SOUTH-OF-FIJI-ISLANDS", "up2013kapj", "2013-05-23 17:19:03", -23.0366, -177.259]
];

//which quake frome the array above do we want to show
var quake_id = 1;

//global animation state
var animated = false;

//counter for frame loading
var time = 0;

//counter for frame playback
var atime = 0;

//global variable for our map, the layer holding the visualization and the load request
var map, layer, request;

//global container holding the data for our animation
var frames = [];

//variables required for calculation the estimated time for loading all frames
var timestamp;
var avrg_speed = 0;
var avrg_size = 0;
var d = new Date();
var start = d.getTime();

//number of frames the system should load before allowing the auto play feature
var min_frame_load = 10;

//number of frames of the animation
var max_frame_load = 139;

//the miliseconds between each visualizations step/frame
var animation_speed = 100;

//The maximum spread of our coordinate system, required for calculations
var max = 20037508;

var myPlaybar;

var data_size = 0;
var interval;
var global_data;
var loadInit = false;
var maxSets = 0;


$(document).ready(function() {
    map = L.map('map', { worldCopyJump: true }).setView([quakes[quake_id][3], quakes[quake_id][4]], 6);
	L.tileLayer('http://a.tiles.mapbox.com/v3/juli84.gdc638hh/{z}/{x}/{y}.png').addTo(map);

	layer = L.layerGroup();
	layer.addTo(map);

	Proj4js.defs["SR-ORG:7483"] = "+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +wktext  +no_defs";

	//Initiate Playbar
	myPlaybar = playbar({repeat:true,x_name:'time',y_name:'value',x_type:'linear'});

	    //Add listeners to the change event - call for every change of the playhead position
	    myPlaybar.on("change", function(status){ 
	    	if(status < frames.length){
	    		layer.clearLayers();
		    	for(var i = 0; i<frames[status].length; i++){
					//Here we loop through the layers and add them to the map
					if(frames[status][i].geometry.coordinates[0].length >= 1){
						geo = L.Proj.geoJson(frames[status][i], { style: function(feature){ return feature.properties && feature.properties.style; }}).on('dblclick', function(event){map.panTo(event.latlng);map.zoomIn();});
						geo.addTo(layer);
					}
				}
			}
	    });

	    //Add listeners to the status event - call for every change on loading/playing/pause
	    myPlaybar.on("status", function(status){ 
	    	if(status=="loading" && !loadInit){
	    		loadInit = true;
	    		setHeatmap();
	    	}else{
	    		//animated=true;
				//animate();
	    		//console.log(status);
	    	}
	    });

	    //Apply playbar to an svg-container
	    d3.select('#playbar').append("svg").call(myPlaybar);

		d3.csv("../provider/meta.php?ref_id="+quake_id, function(error, data) {
			if(error){
				//In case of Data-Error -> Abort!
				console.log(error);
			}else{
				//Load Data and send it to the playbar, to generate the preview graph
				maxSets = data.length;
				myPlaybar.data(data);
			}
		});

});

/*

	optimizeCoords([longitude, latitude]){
		
		Due to leaflet using a continous world,
		which makes the tiles contine on the right
		and left end of the map, we sometimes 
		receive longitude and latitude values
		that are "off the grid". As most GIS
		function will throw an error for "off
		the grid" numbers we need to make sure
		the numbers are converted to save
		coordinates.

	}

*/
function optimizeCoords(coordArray){
	//Longitude Correction
	if(coordArray[0]<-180){
		coordArray[0]=360+coordArray[0];
	}else if(coordArray[0]>180){
		coordArray[0]=(-360+coordArray[0]);
	}

	//Latitude Correction
	if(coordArray[1]<-90){
		coordArray[1]=180+coordArray[1];
	}else if(coordArray[1]>90){
		coordArray[1]=(-180+coordArray[1]);
	}

	return coordArray;
}

/*

	setHeatmap(){
		
		Due to leaflet using a continous world,
		which makes the tiles contine on the right
		and left end of the map, we sometimes 
		receive longitude and latitude values
		that are "off the grid". As most GIS
		function will throw an error for "off
		the grid" numbers we need to make sure
		the numbers are converted to save
		coordinates.

	}

*/
function setHeatmap(){
	//Get the boundaries of the current map. We will use this square for our query to the database.
	var bounds = map.getBounds();

	//The boundaries are converted to our coordinate system
	var neA = optimizeCoords([bounds.getNorthEast().lng, bounds.getNorthEast().lat]);
	var ne = ToWebMercator(neA[0], neA[1]);
	var swA = optimizeCoords([bounds.getSouthWest().lng, bounds.getSouthWest().lat]);
	var sw = ToWebMercator(swA[0], swA[1]);

	//For faster queries we using a positive only coordinate system 
	ne[0]+=20037508; 
	ne[1]+=20037508;

	sw[0]+=20037508;
	sw[1]+=20037508;

	//Just for readability we store the variables in x/y min/max variables
	min_x = sw[0];
	max_x = ne[0];

	min_y = sw[1];
	max_y = ne[1];

	//based on the dataset you want to visualize, the zoom level and the boundaries we will request a heatmap
	var trequest = "../provider/deliver.php?zoom="+map.getZoom()+"&min_y="+min_y+"&max_y="+max_y+"&min_x="+min_x+"&max_x="+max_x+"&vis=square&vis_type=value_max&time=all&ref_id="+(quake_id+1);


	var eventSource = new EventSource(trequest);
    eventSource.onmessage = function(event){

    	myPlaybar.loading(event.lastEventId, event.data.length);

		frames.push(jQuery.parseJSON(event.data));

    	if(event.lastEventId>=maxSets-1){
    		console.log("done");
    		eventSource.close();
    	}
    };

}

/*

	function animate(){
	
		as soon as enough frames are loaded the user can "play" the visualization

	}

*/