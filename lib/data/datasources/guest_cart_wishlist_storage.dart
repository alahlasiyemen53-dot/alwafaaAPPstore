import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../models/product_model.dart';

final class GuestCartLine {
  final int productVariantId;
  final int productId;
  final String name;
  final String imageUrl;
  final double displayPrice;
  final int quantity;
  final String? sku;
  final String? size;
  final ProductShade? shade;
  final String syncKey;

  const GuestCartLine({
    required this.productVariantId,
    required this.productId,
    required this.name,
    required this.imageUrl,
    required this.displayPrice,
    required this.quantity,
    required this.syncKey,
    this.sku,
    this.size,
    this.shade,
  });

  GuestCartLine copyWith({int? quantity}) => GuestCartLine(
        productVariantId: productVariantId,
        productId: productId,
        name: name,
        imageUrl: imageUrl,
        displayPrice: displayPrice,
        quantity: quantity ?? this.quantity,
        syncKey: syncKey,
        sku: sku,
        size: size,
        shade: shade,
      );

  Map<String, dynamic> toJson() => {
        'product_variant_id': productVariantId,
        'product_id': productId,
        'name': name,
        'image_url': imageUrl,
        'display_price': displayPrice,
        'quantity': quantity,
        'sync_key': syncKey,
        'sku': sku,
        'size': size,
        'shade_name': shade?.name,
        'shade_hex': shade?.hex,
      };

  factory GuestCartLine.fromJson(Map<String, dynamic> json) => GuestCartLine(
        productVariantId: json['product_variant_id'] as int? ?? 0,
        productId: json['product_id'] as int? ?? 0,
        name: json['name'] as String? ?? 'منتج',
        imageUrl:
            json['image_url'] as String? ?? 'assets/images/placeholder.png',
        displayPrice: (json['display_price'] as num?)?.toDouble() ?? 0,
        quantity: json['quantity'] as int? ?? 1,
        syncKey: json['sync_key'] as String? ?? '',
        sku: json['sku'] as String?,
        size: json['size'] as String?,
        shade: json['shade_name'] is String
            ? ProductShade(
                name: json['shade_name'] as String,
                hex: json['shade_hex'] as String? ?? '#C5A880',
              )
            : null,
      );
}

final class GuestWishlistEntry {
  final int productId;
  final String name;
  final String imageUrl;
  final double displayPrice;

  const GuestWishlistEntry({
    required this.productId,
    required this.name,
    required this.imageUrl,
    required this.displayPrice,
  });

  Map<String, dynamic> toJson() => {
        'product_id': productId,
        'name': name,
        'image_url': imageUrl,
        'display_price': displayPrice,
      };

  factory GuestWishlistEntry.fromJson(Map<String, dynamic> json) =>
      GuestWishlistEntry(
        productId: json['product_id'] as int? ?? 0,
        name: json['name'] as String? ?? 'منتج',
        imageUrl:
            json['image_url'] as String? ?? 'assets/images/placeholder.png',
        displayPrice: (json['display_price'] as num?)?.toDouble() ?? 0,
      );
}

abstract interface class GuestCartWishlistStorage {
  Future<List<GuestCartLine>> cartLines();
  Future<void> saveCartLines(List<GuestCartLine> lines);
  Future<List<GuestWishlistEntry>> wishlistEntries();
  Future<void> saveWishlistEntries(List<GuestWishlistEntry> entries);
}

final class SharedPreferencesGuestCartWishlistStorage
    implements GuestCartWishlistStorage {
  static const _cartKey = 'guest_cart_v1';
  static const _wishlistKey = 'guest_wishlist_v1';

  Future<SharedPreferences> get _preferences => SharedPreferences.getInstance();

  @override
  Future<List<GuestCartLine>> cartLines() async => _read(
        _cartKey,
        GuestCartLine.fromJson,
      );

  @override
  Future<void> saveCartLines(List<GuestCartLine> lines) =>
      _save(_cartKey, lines.map((line) => line.toJson()).toList());

  @override
  Future<List<GuestWishlistEntry>> wishlistEntries() async => _read(
        _wishlistKey,
        GuestWishlistEntry.fromJson,
      );

  @override
  Future<void> saveWishlistEntries(List<GuestWishlistEntry> entries) =>
      _save(_wishlistKey, entries.map((entry) => entry.toJson()).toList());

  Future<List<T>> _read<T>(
    String key,
    T Function(Map<String, dynamic>) parser,
  ) async {
    final raw = (await _preferences).getString(key);
    if (raw == null) return <T>[];
    final decoded = jsonDecode(raw);
    if (decoded is! List) return <T>[];
    return decoded
        .whereType<Map>()
        .map((entry) => parser(Map<String, dynamic>.from(entry)))
        .toList();
  }

  Future<void> _save(String key, List<Map<String, dynamic>> entries) async {
    await (await _preferences).setString(key, jsonEncode(entries));
  }
}
