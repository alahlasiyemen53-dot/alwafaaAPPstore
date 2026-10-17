import 'dart:typed_data';
import 'package:dio/dio.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/dio_client.dart';
import '../models/catalog/visual_search_result_model.dart';

abstract class VisualSearchRemoteDataSource {
  Future<VisualSearchResultModel> searchByImage({
    required Uint8List imageBytes,
    required String fileName,
  });
}

class VisualSearchRemoteDataSourceImpl implements VisualSearchRemoteDataSource {
  final DioClient client;

  VisualSearchRemoteDataSourceImpl({DioClient? client})
      : client = client ?? DioClient();

  @override
  Future<VisualSearchResultModel> searchByImage({
    required Uint8List imageBytes,
    required String fileName,
  }) async {
    final formData = FormData.fromMap({
      'image': MultipartFile.fromBytes(imageBytes, filename: fileName),
    });
    final response = await client.post(
      ApiEndpoints.visualSearch,
      data: formData,
    );

    return VisualSearchResultModel.fromJson(
        response.data as Map<String, dynamic>);
  }
}
