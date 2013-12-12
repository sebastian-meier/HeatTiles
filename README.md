HeatTiles
=========

A new approach for developing realtime heatmaps for mobile devices

## Setup

### Requirements

- PHP
- MySQL

You need a database holding georeferenced data.
The three mandatory columns are one column for latitude, one for longitude and an id column.
The names of these columns can be set in the config.php.

## Conversion

First i would recommend makeing an backup of your database. 
Then go to the config file, enter your database credentials,
add the names of the mandatory table columns.
Now you could take a look at the additional parameters,
but for most cases your are done now and can start convert.php.

## Usage

When the conversion process is done, you should take a look at
the examples, as they give you a chance to see if the conversion
worked out (example-2.php) as well as showing you several usecases
and ways to implement your updated database structure.

So far the implementation is only shown for Leaflet.js,
but implementations for Google and other Map-Libraries should
work similar.


## License

The heatcanvas library included in this repository belongs to sunng87 and is published under MIT
https://github.com/sunng87/heatcanvas

The WebGL Heatmap library included in this repository belongs to ursudio and is published under MIT
https://github.com/ursudio/webgl-heatmap-leaflet

My code is published under the MIT/GPL.

* http://en.wikipedia.org/wiki/MIT_License
* http://en.wikipedia.org/wiki/GNU_General_Public_License

If you make enhancements or code changes i would love to know so i can reshare your findings.