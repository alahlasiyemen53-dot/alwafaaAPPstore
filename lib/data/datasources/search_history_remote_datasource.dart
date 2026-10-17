import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/customer/search_history_item_model.dart';

abstract interface class SearchHistoryRemoteDataSource {
  Future<SearchHistoryResponseModel> getSearchHistory();
  Future<SearchHistoryItemModel?> recordSearch(String query);
  Future<SearchHistoryResponseModel> mergeGuestQueries(List<String> queries);
  Future<void> deleteItem(int id);
  Future<void> clearAll();
}

final class SearchHistoryRemoteDataSourceImpl
    implements SearchHistoryRemoteDataSource {
  final DioClient _dioClient;

  SearchHistoryRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<SearchHistoryResponseModel> getSearchHistory() async {
    final response = await _dioClient.get(ApiEndpoints.searchHistory);
    return ApiEnvelope<SearchHistoryResponseModel>.parse(
      response.data,
      (data) => SearchHistoryResponseModel.fromJson(
        data as Map<String, dynamic>? ?? {},
      ),
    ).data;
  }

  @override
  Future<SearchHistoryItemModel?> recordSearch(String query) async {
    final response = await _dioClient.post(
      ApiEndpoints.searchHistory,
      data: {'query': query},
    );
    return ApiEnvelope<SearchHistoryItemModel?>.parse(
      response.data,
      (data) {
        if (data == null) return null;
        if (data is Map<String, dynamic>) {
          return SearchHistoryItemModel.fromJson(data);
        }
        return null;
      },
    ).data;
  }

  @override
  Future<SearchHistoryResponseModel> mergeGuestQueries(
    List<String> queries,
  ) async {
    final response = await _dioClient.post(
      ApiEndpoints.searchHistoryMerge,
      data: {'queries': queries},
    );
    return ApiEnvelope<SearchHistoryResponseModel>.parse(
      response.data,
      (data) => SearchHistoryResponseModel.fromJson(
        data as Map<String, dynamic>? ?? {},
      ),
    ).data;
  }

  @override
  Future<void> deleteItem(int id) async {
    final response =
        await _dioClient.delete(ApiEndpoints.searchHistoryItem(id));
    ApiEnvelope<Object?>.parse(response.data, (data) => data);
  }

  @override
  Future<void> clearAll() async {
    final response = await _dioClient.delete(ApiEndpoints.searchHistory);
    ApiEnvelope<Object?>.parse(response.data, (data) => data);
  }
}
