import 'dart:async';
import 'package:app_links/app_links.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:store/core/notifications/push_notification_router.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/discovery/presentation/screens/collection_detail_screen.dart';
import 'package:store/features/product_details/presentation/screens/product_details_screen.dart';

enum DeepLinkTargetType { product, offer, collection }

class DeepLinkTarget {
  final DeepLinkTargetType type;
  final int id;
  final String? slug;

  const DeepLinkTarget({
    required this.type,
    required this.id,
    this.slug,
  });

  @override
  String toString() => 'DeepLinkTarget(type: $type, id: $id, slug: $slug)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is DeepLinkTarget &&
          runtimeType == other.runtimeType &&
          type == other.type &&
          id == other.id &&
          slug == other.slug;

  @override
  int get hashCode => type.hashCode ^ id.hashCode ^ slug.hashCode;
}

/// Service to handle App Links, Universal Links, and Custom Scheme URLs.
class DeepLinkService {
  DeepLinkService({
    AppLinks? appLinks,
    GlobalKey<NavigatorState>? navigatorKey,
  })  : _appLinks = appLinks ?? AppLinks(),
        _navigatorKey = navigatorKey ?? PushNotificationRouter.navigatorKey;

  final AppLinks _appLinks;
  final GlobalKey<NavigatorState> _navigatorKey;
  StreamSubscription<Uri>? _sub;
  DeepLinkTarget? _pendingTarget;

  static final DeepLinkService instance = DeepLinkService();

  Future<void> initialize() async {
    // 1. Handle app launched from deep link (cold start)
    try {
      final initialUri = await _appLinks.getInitialLink();
      if (initialUri != null) {
        handleUri(initialUri);
      }
    } catch (_) {}

    // 2. Handle app opened via deep link while in background or foreground
    _sub = _appLinks.uriLinkStream.listen(
      (uri) {
        handleUri(uri);
      },
      onError: (_) {},
    );
  }

  void dispose() {
    _sub?.cancel();
  }

  /// Parses a deep link Uri into a structured [DeepLinkTarget].
  static DeepLinkTarget? parseUri(Uri uri) {
    final scheme = uri.scheme.toLowerCase();
    final host = uri.host.toLowerCase();
    final pathSegments = uri.pathSegments;

    // 1. Custom URL scheme: storeapp://product/123 or storeapp://offer/456 or storeapp://collection/789
    if (scheme == 'storeapp') {
      if (host == 'product' && pathSegments.isNotEmpty) {
        final id = int.tryParse(pathSegments.first);
        if (id != null && id > 0) {
          return DeepLinkTarget(type: DeepLinkTargetType.product, id: id);
        }
      } else if ((host == 'offer' || host == 'offers') && pathSegments.isNotEmpty) {
        final id = int.tryParse(pathSegments.first);
        if (id != null && id > 0) {
          return DeepLinkTarget(type: DeepLinkTargetType.offer, id: id);
        }
      } else if ((host == 'collection' || host == 'collections') && pathSegments.isNotEmpty) {
        final id = int.tryParse(pathSegments.first);
        if (id != null && id > 0) {
          return DeepLinkTarget(type: DeepLinkTargetType.collection, id: id);
        }
      }
    }

    // 2. HTTP/HTTPS App Links & Universal Links: https://wafapp.alwafaamed.com/p/123
    if (scheme == 'https' || scheme == 'http') {
      if (pathSegments.isEmpty) return null;

      final firstSegment = pathSegments.first.toLowerCase();

      // Product: /p/{id} or /products/{id}
      if (firstSegment == 'p' || firstSegment == 'products') {
        if (pathSegments.length >= 2) {
          final idPart = pathSegments[1].split('-').first;
          final id = int.tryParse(idPart);
          if (id != null && id > 0) {
            return DeepLinkTarget(
              type: DeepLinkTargetType.product,
              id: id,
              slug: pathSegments[1],
            );
          }
        }
      }

      // Offer / Promotion: /offers/{id}
      if (firstSegment == 'offers' || firstSegment == 'promotions') {
        if (pathSegments.length >= 2) {
          final idPart = pathSegments[1].split('-').first;
          final id = int.tryParse(idPart);
          if (id != null && id > 0) {
            return DeepLinkTarget(type: DeepLinkTargetType.offer, id: id);
          }
        }
      }

      // Collection: /c/{id} or /collections/{id}
      if (firstSegment == 'c' || firstSegment == 'collections') {
        if (pathSegments.length >= 2) {
          final idPart = pathSegments[1].split('-').first;
          final id = int.tryParse(idPart);
          if (id != null && id > 0) {
            return DeepLinkTarget(type: DeepLinkTargetType.collection, id: id);
          }
        }
      }
    }

    return null;
  }

  /// Handles incoming URI and attempts navigation.
  void handleUri(Uri uri) {
    final target = parseUri(uri);
    if (target == null) return;
    navigateTarget(target);
  }

  /// Navigates to the parsed target destination.
  void navigateTarget(DeepLinkTarget target) {
    final context = _navigatorKey.currentContext;
    if (context == null) {
      _pendingTarget = target;
      return;
    }

    try {
      // Ensure AppShellCubit is in scope; if on SplashScreen it will throw
      context.read<AppShellCubit>();
      switch (target.type) {
        case DeepLinkTargetType.product:
          ProductDetailsScreen.open(context, target.id);
          break;
        case DeepLinkTargetType.offer:
        case DeepLinkTargetType.collection:
          CollectionDetailScreen.open(context, target.id);
          break;
      }
    } catch (_) {
      // AppShellCubit not available yet (e.g. still during splash)
      _pendingTarget = target;
    }
  }

  /// Called after splash screen completes to flush any pending target.
  void flushPendingTarget() {
    if (_pendingTarget != null) {
      final target = _pendingTarget!;
      _pendingTarget = null;
      navigateTarget(target);
    }
  }
}
