<!DOCTYPE html>
<html>
<head>
  <title>OSRM Street Route Example</title>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet/dist/leaflet.css"
  />
</head>
<body>
<div id="map" style="width: 100%; height: 600px;"></div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
  // Initialize the map
  var map = L.map('map').setView([11.603428, 104.885574], 15);

  // Add OpenStreetMap tiles
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  // Define start and end points
  var start = [11.603428, 104.885574];
  var end = [11.598423, 104.880735];

  // Fetch route from OSRM public server
  fetch(`https://osrm.salepro.asia/route/v1/driving/${start[1]},${start[0]};${end[1]},${end[0]}?overview=full&geometries=geojson`)
    .then(response => response.json())
    .then(data => {
      // Get the coordinates from the route
      var coords = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);

      // Draw the polyline following the streets
      L.polyline(coords, {color: 'blue', weight: 5}).addTo(map);

      // Fit map bounds to the route
      map.fitBounds(L.polyline(coords).getBounds());
    })
    .catch(err => console.error(err));
</script>
</body>
</html>
