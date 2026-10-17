import 'dart:typed_data';
import '../datasources/visual_search_remote_datasource.dart';
import '../models/catalog/visual_search_result_model.dart';

abstract class VisualSearchRepository {
  Future<VisualSearchResultModel> searchByImage({
    required Uint8List imageBytes,
    required String fileName,
  });
}

class VisualSearchRepositoryImpl implements VisualSearchRepository {
  final VisualSearchRemoteDataSource remoteDataSource;

  VisualSearchRepositoryImpl({VisualSearchRemoteDataSource? remoteDataSource})
      : remoteDataSource =
            remoteDataSource ?? VisualSearchRemoteDataSourceImpl();

  @override
  Future<VisualSearchResultModel> searchByImage({
    required Uint8List imageBytes,
    required String fileName,
  }) async {
    return await remoteDataSource.searchByImage(
      imageBytes: imageBytes,
      fileName: fileName,
    );
  }
}
