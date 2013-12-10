<?php

//This feature is really important
//If the data later on added to your database
//seams to be not precise enough, it might be
//due to the lag of this feature
ini_set("precision", 20);

//ATTENTION !!!!!
//Before adding your database credentials here,
//please make a backup of your data or first try
//this conversion-tool on a subset of your database
//the conversion is just adding data and not deleting
//or modifing data, but well, you never know.

//Database Connection
$db_server = "YOUR_SERVER";
$db_user = "YOUR_USER_NAME";
$db_pass = "YOUR_PASSWORD";
$db_database = "YOUR_DATABASE_NAME";
$db_table = "YOUR_DATABASE_TABLE_NAME";

//For the conversion we need a latitude & longitude column
//If you have only one column which holds both numbers,
//you need to modify the code accordingly.
$db_lat = "latitude";
$db_lng = "longitude";

//Also you table needs to have a column with a unique id
$db_id = "id";

//If the conversion process taking longer then your server
//timeout, modify the following value, making it less rows
//per cycle to be modified
$db_max = 1000;

//If you use bigger numbers for the variable above you 
//should also increase the maximum execution timeout
set_time_limit(30000);

$redirect = false;
$message = false;

//The data is rasterized into a tiled grid
//The tile-size in zoom-level "0" is set here:
$gridsize = 2.5;

//Based on the gridsize the higher zoom-levels
//can be calculated automatically, as every
//zoom-level always doubles (gridsize * pow(2, zoomlevel))
$max = 20037508;
$step_size = array();
$steps = array();
for($i = 0; $i<20; $i++){
	array_push($step_size, (2.5 * pow(2, $i)));
	array_push($steps, round($max*2.0/$step_size[$i]));
}

/*------------------- MYSQL -------------------*/

if(!$link = mysql_connect($db_server, $db_user, $db_pass)){
    echo 'Problem connecting to the MySql Server';
    exit;
}

if(!mysql_select_db($db_database, $link)){
    echo 'Unable to connect to database-table ';
    exit;
}

?>