/// Ray-casting point-in-polygon matching Backend `SanaaServiceAreaValidator`.
///
/// [vertices] are `[longitude, latitude]` pairs (same as `SANAA_DELIVERY_POLYGON`).
bool pointInPolygonLngLat({
  required double latitude,
  required double longitude,
  required List<List<double>> vertices,
}) {
  if (vertices.length < 3) return false;

  var inside = false;
  var lastIndex = vertices.length - 1;
  for (var index = 0; index < vertices.length; lastIndex = index++) {
    final pointA = vertices[index];
    final pointB = vertices[lastIndex];
    if (pointA.length < 2 || pointB.length < 2) continue;

    final longitudeA = pointA[0];
    final latitudeA = pointA[1];
    final longitudeB = pointB[0];
    final latitudeB = pointB[1];

    final intersects = ((latitudeA > latitude) != (latitudeB > latitude)) &&
        (longitude <
            (longitudeB - longitudeA) *
                    (latitude - latitudeA) /
                    (latitudeB - latitudeA) +
                longitudeA);
    if (intersects) {
      inside = !inside;
    }
  }
  return inside;
}
