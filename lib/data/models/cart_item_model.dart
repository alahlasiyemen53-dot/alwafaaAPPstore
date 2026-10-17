import 'package:equatable/equatable.dart';
import 'cart/cart_api_model.dart';
import 'category_model.dart';
import 'product_model.dart';

class CartItemModel extends Equatable {
  final int? id;
  final int? productVariantId;
  final ProductModel product;
  final int quantity;
  final String? selectedSize;
  final ProductShade? selectedShade;
  final String? variantSpecs;
  final double? unitPrice;
  final double? lineTotal;
  final double? itemDiscount;
  final CartItemAvailability? availability;

  const CartItemModel({
    this.id,
    this.productVariantId,
    required this.product,
    this.quantity = 1,
    this.selectedSize,
    this.selectedShade,
    this.variantSpecs,
    this.unitPrice,
    this.lineTotal,
    this.itemDiscount,
    this.availability,
  });

  CartItemModel copyWith({
    int? id,
    int? productVariantId,
    ProductModel? product,
    int? quantity,
    String? selectedSize,
    ProductShade? selectedShade,
    String? variantSpecs,
    double? unitPrice,
    double? lineTotal,
    double? itemDiscount,
    CartItemAvailability? availability,
  }) {
    return CartItemModel(
      id: id ?? this.id,
      productVariantId: productVariantId ?? this.productVariantId,
      product: product ?? this.product,
      quantity: quantity ?? this.quantity,
      selectedSize: selectedSize ?? this.selectedSize,
      selectedShade: selectedShade ?? this.selectedShade,
      variantSpecs: variantSpecs ?? this.variantSpecs,
      unitPrice: unitPrice ?? this.unitPrice,
      lineTotal: lineTotal ?? this.lineTotal,
      itemDiscount: itemDiscount ?? this.itemDiscount,
      availability: availability ?? this.availability,
    );
  }

  /// Specs line for cart UI: color, size, and other variant attributes.
  String get displaySpecs {
    final specs = variantSpecs?.trim();
    if (specs != null && specs.isNotEmpty) return specs;

    final parts = <String>[
      if (selectedSize != null && selectedSize!.trim().isNotEmpty)
        selectedSize!.trim(),
      if (selectedShade != null && selectedShade!.name.trim().isNotEmpty)
        selectedShade!.name.trim(),
    ];
    return parts.join(' • ');
  }

  factory CartItemModel.fromApiModel(CartItemApiModel apiItem) {
    String? size;
    ProductShade? shade;
    final specParts = <String>[];

    if (apiItem.variant != null) {
      for (final attr in apiItem.variant!.attributes) {
        final value = attr.value.trim();
        if (value.isEmpty) continue;

        final key =
            '${attr.code ?? ''} ${attr.name ?? ''}'.toLowerCase().trim();
        final isSize = key.contains('size') ||
            key.contains('volume') ||
            key.contains('حجم') ||
            key.contains('سعة') ||
            key.contains('مل');
        final isColor = key.contains('shade') ||
            key.contains('color') ||
            key.contains('لون') ||
            key.contains('colour');

        if (isSize) {
          size = value;
        } else if (isColor) {
          shade = ProductShade(name: value, hex: '#C5A880');
        }

        specParts.add(attr.displayLabel);
      }
    }

    final productModel = ProductModel(
      id: apiItem.product?.id.toString() ?? '',
      serverId: apiItem.product?.id,
      name: apiItem.product?.name ?? '',
      brand: apiItem.product?.brandName ?? '',
      category: ProductCategory.perfumes,
      priceSAR: apiItem.unitPrice,
      rating: 4.8,
      reviewsCount: 12,
      image:
          apiItem.product?.primaryImageUrl ?? 'assets/images/placeholder.png',
      images: [
        apiItem.product?.primaryImageUrl ?? 'assets/images/placeholder.png'
      ],
      description: '',
      inStock: apiItem.availability.isInStock,
      selectedVariantId: apiItem.productVariantId,
      selectedVariantSku: apiItem.variant?.sku,
    );

    return CartItemModel(
      id: apiItem.id,
      productVariantId: apiItem.productVariantId,
      product: productModel,
      quantity: apiItem.quantity,
      selectedSize: size,
      selectedShade: shade,
      variantSpecs: specParts.isEmpty ? null : specParts.join(' • '),
      unitPrice: apiItem.unitPrice,
      lineTotal: apiItem.lineTotal,
      itemDiscount: apiItem.itemDiscount,
      availability: apiItem.availability,
    );
  }

  /// Stable key for multi-select across guest sync (variant → server id).
  String get selectionKey {
    if (id != null) return 'id:$id';
    if (productVariantId != null) return 'variant:$productVariantId';
    return 'local:${product.id}:$selectedSize:${selectedShade?.name ?? ''}';
  }

  double get totalPriceSAR =>
      lineTotal ??
      (unitPrice != null ? unitPrice! * quantity : product.priceSAR * quantity);

  @override
  List<Object?> get props => [
        id,
        productVariantId,
        product,
        quantity,
        selectedSize,
        selectedShade,
        variantSpecs,
        unitPrice,
        lineTotal,
        itemDiscount,
        availability,
      ];
}
