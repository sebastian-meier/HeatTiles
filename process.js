var Client = require('pg-native'),
	proj4js = require('proj4');

var db_table = "air",
	db_prefix = "",

	//For the conversion we need a latitude & longitude column
	//If you have only one column which holds both numbers,
	//you need to modify the code accordingly.
	db_lat = "latitude",
	db_lng = "longitude",

	//Also you table needs to have a column with a unique id
	db_id = "id",

	db_conversion_table = "air",

	//The data is rasterized into a tiled grid
	//The tile-size in zoom-level "0" is set here:
	gridsize = 2.5,
	vis_type = "value_max",
	vis = "hex",
	zoom_min = 0,
	zoom_max = 21,
	zoom_single = 99;

//Based on the gridsize the higher zoom-levels
//can be calculated automatically, as every
//zoom-level always doubles (gridsize * pow(2, zoomlevel))
var max = 20037508,
	step_size = [],
	steps = [];

for(var i = zoom_min; i<zoom_max; i++){
	step_size.push((gridsize * Math.pow(2, i)));
	steps.push(Math.round(max*2.0/step_size[i]));
}

/*------------------- CONVERSION FUNCTIONS -------------------*/

function ToWebMercator(mercatorY_lat, mercatorX_lng){
    if((Math.abs(mercatorX_lng) > 180 || Math.abs(mercatorY_lat) > 90)){
        return;
	}

    var x = 6378137.0 * (mercatorX_lng * 0.017453292519943295);
    var a = mercatorY_lat * 0.017453292519943295;
    var y = 3189068.5 * Math.log((1.0 + Math.sin(a)) / (1.0 - Math.sin(a)));

    return array(y, x);
}

function ToGeographic(mercatorY_lat, mercatorX_lng){
    if ((Math.abs(mercatorX_lng) > max) || (Math.abs(mercatorY_lat) > max)){
    	return;
    }

    var x = mercatorX_lng;
    var y = mercatorY_lat;
    var num3 = x / 6378137.0;
    var num4 = num3 * 57.295779513082323;
    var num5 = Math.floor(((num4 + 180.0) / 360.0));
    var num6 = num4 - (num5 * 360.0);
    var num7 = 1.5707963267948966 - (2.0 * Math.atan(Math.exp((-1.0 * y) / 6378137.0)));
    mercatorX_lng = num6;
    mercatorY_lat = num7 * 57.295779513082323;

    return array(mercatorY_lat, mercatorX_lng);
}


/*------------------- ACTUAL CONVERSION -------------------*/


var client = new Client();
client.connect("postgres://sebastianmeier:@localhost/sebastianmeier", function(err) {
	if(err) throw err

	processData();
});

function processData(){
	//UPDATE air SET x = ST_X(ST_TRANSFORM(geom,3857)), y = ST_Y(ST_TRANSFORM(geom,3857))
	//UPDATE air SET x0 = (x+20037508.0), y0 = (y+20037508.0)

	console.log('UPDATE air SET ');
	for(var z = zoom_min; z<zoom_max; z++){

		var tx = 'ceil(x0/'+step_size[(zoom_max-1)-z]+')';
		var ty = 'ceil(y0/'+step_size[(zoom_max-1)-z]+')';
		var tcell = '(('+ty+'-1)*'+steps[(zoom_max-1)-z]+'+'+tx+')';

		//offset tiles (hex tiles 50% y-offset on even rows)
		ty = 'ceil(y0/'+step_size[(zoom_max-1)-z]+')';
		if(ty%2 == 1){
			tx = 'ceil((x0+'+step_size[(zoom_max-1)-z]+'/2)/'+step_size[(zoom_max-1)-z]+')';
		}else{
			tx = 'ceil(x0/'+step_size[(zoom_max-1)-z]+')';
		}
		var tcellh = '(('+ty+'-1)*'+steps[(zoom_max-1)-z]+'+'+tx+')';

		var komma = "";
		if(z<(zoom_max-1)){
			komma = ",";
		}

		console.log('zh'+z+' = '+tcellh+komma+' ');
	}
	console.log(';');
	//CREATE INDEX indexzh'+z+' ON air (zh'+z+')'
}


	