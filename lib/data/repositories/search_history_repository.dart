import '../datasources/search_history_remote_datasource.dart';
import '../models/customer/search_history_item_model.dart';

abstract interface class SearchHistoryRepository {
  Future<SearchHistoryResponseModel> getSearchHistory();
  Future<SearchHistoryItemModel?> recordSearch(String query);
  Future<SearchHistoryResponseModel> mergeGuestQueries(List<String> queries);
  Future<void> deleteItem(int id);
  Future<void> clearAll();
}

final class SearchHistoryRepositoryImpl implements SearchHistoryRepository {
  final SearchHistoryRemoteDataSource _remote;

  SearchHistoryRepositoryImpl({SearchHistoryRemoteDataSource? remote})
      : _remote = remote ?? SearchHistoryRemoteDataSourceImpl();

  @override
  Future<SearchHistoryResponseModel> getSearchHistory() =>
      _remote.getSearchHistory();

  @override
  Future<SearchHistoryItemModel?> recordSearch(String query) =>
      _remote.recordSearch(query);

  @override
  Future<SearchHistoryResponseModel> mergeGuestQueries(List<String> queries) =>
      _remote.mergeGuestQueries(queries);

  @override
  Future<void> deleteItem(int id) => _remote.deleteItem(id);

  @override
  Future<void> clearAll() => _remote.clearAll();
}
