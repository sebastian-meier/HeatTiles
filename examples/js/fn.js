function ToGeographic(mercatorX_lon, mercatorY_lat){
	if (Math.abs(mercatorX_lon) < 180 && Math.abs(mercatorY_lat) < 90)
		return;

	if ((Math.abs(mercatorX_lon) > 20037508.3427892) || (Math.abs(mercatorY_lat) > 20037508.3427892))
		return;

	var x = mercatorX_lon;
	var y = mercatorY_lat;
	var num3 = x / 6378137.0;
	var num4 = num3 * 57.295779513082323;
	var num5 = Math.floor((double)((num4 + 180.0) / 360.0));
	var num6 = num4 - (num5 * 360.0);
	var num7 = 1.5707963267948966 - (2.0 * Math.atan(Math.exp((-1.0 * y) / 6378137.0)));
	mercatorX_lon = num6;
	mercatorY_lat = num7 * 57.295779513082323;

	return [mercatorX_lon, mercatorY_lat];
}

function ToWebMercator(mercatorX_lon, mercatorY_lat){
	if ((Math.abs(mercatorX_lon) > 180 || Math.abs(mercatorY_lat) > 90))
		return;

	var num = mercatorX_lon * 0.017453292519943295;
	var x = 6378137.0 * num;
	var a = mercatorY_lat * 0.017453292519943295;

	mercatorX_lon = x;
	mercatorY_lat = 3189068.5 * Math.log((1.0 + Math.sin(a)) / (1.0 - Math.sin(a)));

	return [mercatorX_lon, mercatorY_lat];
}