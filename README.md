HeatTiles
=========

A new approach for developing realtime heatmaps for mobile devices

## Why another way for generating Heatmaps?

There are different ways for generating Heatmaps. 
The first difference is server-side usually via Image-Tiles 
or client-side by passing datapoints to the browser and
generating the Heatmap on the client-side.

The first method requires downloading a lot of data, especially if take high resolution displays into account. It also makes it neccessary to have prerendering processes in place. This makes it impossible to change the query for acquiring the heatmap data on the fly. 

The second method (if the data is not tile-based) also requires download a lot of point-data. More problematic is the actually rendering which costs time and resources, especially on mobile devices. Another problem of this method is scalability, if the number of data-points for the visible area is too big, renedering is likely to fail on mobile devices.

### New Approach 

The new approach is trying to get best of both worlds:

First the data in the location-database is converted from a Geographic-Coordinate-System to a Web-Mercator-Projection. Then the origin of the coordinate-system is set into the upper left, removing the negative range and speeding up our query requests. In the next phase the data is tiled, storing tile-location for every zoom-level for every data-point.
The last step is important as by now we can query a certain region on the map and group the data-points into tiles. Depending on the amount of data and the amount of detail we want to reach, we can switch between zoom-levels.

This data can then easily be used in existing visualization-libraries or you can directly create geo-json files that hold the visualization.

Using geojson for visualizing the grouped data is fast, resolution independet (vector) and still interactive (clickable, hover, etc.).

By using SQLs basic grouping function we can scale this method from a couple thousand points to the limits of your sql-database.

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