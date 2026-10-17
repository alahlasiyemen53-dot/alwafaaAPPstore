import 'package:equatable/equatable.dart';
import 'currency_model.dart';

enum OrderStatus { confirmed, preparing, withCourier, delivered }

class OrderItemModel extends Equatable {
  final String id;
  final String productName;
  final String brand;
  final int quantity;
  final double price;
  final String image;
  final String? sizeOrShade;

  const OrderItemModel({
    required this.id,
    required this.productName,
    required this.brand,
    required this.quantity,
    required this.price,
    required this.image,
    this.sizeOrShade,
  });

  @override
  List<Object?> get props => [id, productName, brand, quantity, price, image, sizeOrShade];
}

class ShippingAddressModel extends Equatable {
  final String recipientName;
  final String phone;
  final String city;
  final String district;
  final String street;

  const ShippingAddressModel({
    required this.recipientName,
    required this.phone,
    required this.city,
    required this.district,
    required this.street,
  });

  @override
  List<Object?> get props => [recipientName, phone, city, district, street];
}

class OrderModel extends Equatable {
  final String id;
  final String orderNumber;
  final String date;
  final OrderStatus status;
  final String statusAr;
  final List<OrderItemModel> items;
  final double subtotal;
  final double shipping;
  final double discount;
  final double total;
  final CurrencyCode currencyCode;
  final String paymentMethod;
  final ShippingAddressModel shippingAddress;
  final String estimatedDelivery;
  final String trackingNumber;
  final String courierName;

  const OrderModel({
    required this.id,
    required this.orderNumber,
    required this.date,
    required this.status,
    required this.statusAr,
    required this.items,
    required this.subtotal,
    required this.shipping,
    required this.discount,
    required this.total,
    required this.currencyCode,
    required this.paymentMethod,
    required this.shippingAddress,
    required this.estimatedDelivery,
    required this.trackingNumber,
    required this.courierName,
  });

  @override
  List<Object?> get props => [
        id,
        orderNumber,
        date,
        status,
        statusAr,
        items,
        subtotal,
        shipping,
        discount,
        total,
        currencyCode,
        paymentMethod,
        shippingAddress,
        estimatedDelivery,
        trackingNumber,
        courierName,
      ];
}
