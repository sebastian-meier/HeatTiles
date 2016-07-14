var Client = require('pg-native'),
	proj4js = require('proj4'),
	express = require('express');
	http = require('http');
	app = express();

var gridsize = 2.5,
	zoom_min = 0,
	zoom_max = 21,
	step_max = zoom_max-2;

var max = 20037508,
	step_size = [],
	steps = [];

for(var i = zoom_min; i<zoom_max; i++){
	step_size.push((gridsize * Math.pow(2, i)));
	steps.push(Math.round(max*2.0/step_size[i]));
}

var colors = [
	"253,253,253",
	"255,255,255",
	"45,112,200",
	"0,77,168",
	"160,194,155",
	"102,163,62",
	"43,130,0",
	"237,190,142",
	"255,153,77",
	"242,97,0",
	"213,0,0",
	"163,0,0",
	"122,0,0",
	"0,0,0"
];

function ToWebMercator(mercatorY_lat, mercatorX_lng){
    if((Math.abs(mercatorX_lng) > 180 || Math.abs(mercatorY_lat) > 90)){
        return;
	}

    var x = 6378137.0 * (mercatorX_lng * 0.017453292519943295);
    var a = mercatorY_lat * 0.017453292519943295;
    var y = 3189068.5 * Math.log((1.0 + Math.sin(a)) / (1.0 - Math.sin(a)));

    return [y, x];
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

    return [mercatorY_lat, mercatorX_lng];
}


var client = new Client();
client.connect("postgres://sebastianmeier:@localhost/sebastianmeier", function(err) {
	if(err) throw err

	startServer();
});

function startServer(){
	app.set('port', 10060);

	app.use(function(req, res, next) {
		res.header("Access-Control-Allow-Origin", "*");
		res.header("Access-Control-Allow-Headers", "Origin, X-Requested-With, Content-Type, Accept");
		res.header("Content-Type", "application/json");
		next();
	});

	app.get(/^\/heattiles\/(.*)\/(\d+)\/(.*)\/(.*)\/(.*)\/(.*).geojson$/, function(req, res){
		var z0 = req.params[1]-2,
			z0i = req.params[1]-1;
			min_lng = parseFloat(req.params[2]),
			max_lng = parseFloat(req.params[3]),
			min_lat = parseFloat(req.params[4]),
			max_lat = parseFloat(req.params[5]),
			type = req.params[0];

		var tmin = ToWebMercator(min_lat, min_lng);
		var tmax = ToWebMercator(max_lat, max_lng);

		var min_x = tmin[1]+max,
			min_y = tmin[0]+max,
			max_x = tmax[1]+max,	
			max_y = tmax[0]+max;

		//We need to determin the center of the map, in case we cross the continous world border this will define the world we need to focus on
		var world = 1;
		if(max_x<min_x){
			if(max_x > (max*2-min_x)){
				world = -1;
			}else{
				world = 1;
			}
		}

		//Calculating an an additional buffering area around the requested area, for better performance when the user is panning
		var dist_x;
		if(max_x<min_x){
			dist_x = ((max_x+max*2) - min_x)*0.5;
		}else{
			dist_x = (max_x - min_x)*0.5;
		}

		max_x += dist_x;
		min_x -= dist_x;


		if(max_x>max*2){
			max_x-=max*2;
		}

		if(min_x<0){
			max_x+=max*2;
		}

		if(max_y<min_y){
			dist_y = ((max_y+max*2) - min_y)*0.5;	
		}else{
			dist_y = (max_y - min_y)*0.5;	
		}

		max_y += dist_y;
		min_y -= dist_y;

		if(max_y>max*2){
			max_y-=max*2;
		}

		if(min_y<0){
			max_y+=max*2;
		}

		if(min_x>max_x){
			var t_maxx = max_x;
			max_x = min_x;
			min_x = t_maxx;
		}

		if(min_y>max_y){
			var t_maxy = max_y;
			max_y = min_y;
			min_y = t_maxy;
		}

		//if our heatmap goes across our continous world border we need a special WHERE request
		var x_req;
		if(max_x>min_x){
			x_req = 'x0 > '+min_x+' AND x0 < '+max_x;
		}else{
			x_req = ' (( x0 > '+min_x+' AND x0 < '+(max*2)+' ) OR ( x0 > 0 AND x0 < '+max_x+' )) ';
		}

		var y_req;
		if(max_y>min_y){
			y_req = 'y0 > '+min_y+' AND y0 < '+max_y;
		}else{
			y_req = ' (( y0 > '+min_y+' AND y0 < '+(max*2)+' ) OR ( y0 > 0 AND y0 < '+max_y+' )) ';
		}

		var h = "";
		if(type == "hex"){
			h = "h";
		}

		var sql = 'SELECT MAX(value) AS v, z'+h+z0i+' AS z FROM air WHERE '+x_req+' AND '+y_req+' GROUP BY z';
		console.log(sql);
		client.query(sql, function(err, rows) {
			if(err) throw err;

			var data = [];
			for(var i in colors){
				data.push({
					type: "Feature",
					properties:{
						style: {
							stroke:"false",
							weight:0,
							color:"#000",
							fillColor:"rgb("+colors[i]+")",
							opacity:0,
							fillOpacity:0.5
						}
					},
					geometry:{
						type: "MultiPolygon",
						coordinates:[[]]
					},
					crs: {
						type: "name",
						properties: {
							name: "urn:ogc:def:crs:SR-ORG::7483"
						}
					}
				});
			}

			for(var i = 0; i<rows.length; i++){
				var v = rows[i].v;
				var z = rows[i].z;

				var y = Math.floor(z/steps[step_max-z0]);
				var x = z - (y*steps[step_max-z0])-1;

				var tarray = [];

				if(type == "hex"){
					if(y%2 == 1){
						x += 0.5; 
					}

					var mStep = step_size[step_max-z0]/14;
					var cornerStep = 2.5;
					var topStep = 2.5;

					tarray.push([x*step_size[step_max-z0]-max, y*step_size[step_max-z0]-max+mStep*cornerStep]);
					//Top of the hex
					tarray.push([x*step_size[step_max-z0]-max+step_size[step_max-z0]/2, y*step_size[step_max-z0]-max-mStep*topStep]);
					tarray.push([x*step_size[step_max-z0]-max+step_size[step_max-z0], y*step_size[step_max-z0]-max+mStep*cornerStep]);
					tarray.push([x*step_size[step_max-z0]-max+step_size[step_max-z0], y*step_size[step_max-z0]-max+step_size[step_max-z0]-mStep*cornerStep]);
					//Bottom of the hex
					tarray.push([x*step_size[step_max-z0]-max+step_size[step_max-z0]-step_size[step_max-z0]/2, y*step_size[step_max-z0]-max+step_size[step_max-z0]+mStep*topStep]);
					tarray.push([x*step_size[step_max-z0]-max, y*step_size[step_max-z0]-max+step_size[step_max-z0]-mStep*cornerStep]);

				}else{

					x1 = x*step_size[step_max-z0]-max;
					y1 = y*step_size[step_max-z0]-max;

					x2 = x1+step_size[step_max-z0];
					y2 = y1+step_size[step_max-z0];

					if(max_x<min_x){
						x1 += world*2*max;
						x2 += world*2*max;
					}

					tarray.push([x1, y1]);
					tarray.push([x2, y1]);
					tarray.push([x2, y2]);
					tarray.push([x1, y2]);

				}

				data[v].geometry.coordinates[0].push(tarray);
			}

			//Send GeoJson
			res.send(JSON.stringify(data));
		})
	});

	http.createServer(app).listen(app.get('port'), function() {
		console.log('Express server listening on port ' + app.get('port'));
	});
}