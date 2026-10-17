import 'package:equatable/equatable.dart';

/// Sana'a service area from `GET /delivery/sanaa-zone`.
/// Vertices are `[longitude, latitude]`.
final class SanaaDeliveryZoneModel extends Equatable {
  final bool configured;
  final String name;
  final String coordinateOrder;
  final List<List<double>> vertices;

  const SanaaDeliveryZoneModel({
    required this.configured,
    required this.name,
    required this.coordinateOrder,
    required this.vertices,
  });

  static const unconfigured = SanaaDeliveryZoneModel(
    configured: false,
    name: 'sanaa',
    coordinateOrder: 'lng_lat',
    vertices: [],
  );

  factory SanaaDeliveryZoneModel.fromJson(Map<String, dynamic> json) {
    final rawVertices = json['vertices'];
    final vertices = <List<double>>[];
    if (rawVertices is List) {
      for (final point in rawVertices) {
        if (point is! List || point.length < 2) continue;
        final lng = (point[0] as num?)?.toDouble();
        final lat = (point[1] as num?)?.toDouble();
        if (lng == null || lat == null) continue;
        vertices.add([lng, lat]);
      }
    }

    final configured = (json['configured'] as bool?) ?? vertices.length >= 3;
    return SanaaDeliveryZoneModel(
      configured: configured && vertices.length >= 3,
      name: (json['name'] as String?) ?? 'sanaa',
      coordinateOrder: (json['coordinate_order'] as String?) ?? 'lng_lat',
      vertices: configured && vertices.length >= 3 ? vertices : const [],
    );
  }

  @override
  List<Object?> get props => [configured, name, coordinateOrder, vertices];
}
