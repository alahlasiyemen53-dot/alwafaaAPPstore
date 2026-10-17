import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'core/services/crashlytics_service.dart';
import 'firebase_options.dart';
import 'app.dart';
import 'data/datasources/reference_local_datasource.dart';
import 'data/repositories/reference_repository.dart';

/// Handles FCM messages while the app is terminated or in the background.
/// Must be a top-level (or static) function annotated with
/// `@pragma('vm:entry-point')` so the Android engine can invoke it in its
/// own isolate.
@pragma('vm:entry-point')
Future<void> _firebaseBackgroundMessageHandler(RemoteMessage message) async {
  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );
}

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await Hive.initFlutter();
  final geoLocal = await ReferenceLocalDataSourceImpl.create();
  final referenceRepository = ReferenceRepositoryImpl(localDataSource: geoLocal);

  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );
  await CrashlyticsService.initialize();
  FirebaseMessaging.onBackgroundMessage(_firebaseBackgroundMessageHandler);

  // Lock orientation to portrait for pristine mobile luxury shopping experience
  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

  // Set system UI styling
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.dark,
      systemNavigationBarColor: Colors.white,
      systemNavigationBarIconBrightness: Brightness.dark,
    ),
  );

  runApp(StoreApp(referenceRepository: referenceRepository));
}
