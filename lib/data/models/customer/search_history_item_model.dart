import 'package:equatable/equatable.dart';

class SearchHistoryItemModel extends Equatable {
  final int? id;
  final String query;
  final DateTime? lastSearchedAt;

  const SearchHistoryItemModel({
    this.id,
    required this.query,
    this.lastSearchedAt,
  });

  factory SearchHistoryItemModel.fromJson(Map<String, dynamic> json) {
    return SearchHistoryItemModel(
      id: json['id'] as int?,
      query: (json['query'] as String? ?? '').trim(),
      lastSearchedAt:
          DateTime.tryParse(json['last_searched_at'] as String? ?? ''),
    );
  }

  @override
  List<Object?> get props => [id, query, lastSearchedAt];
}

class SearchHistoryResponseModel extends Equatable {
  final List<SearchHistoryItemModel> items;

  const SearchHistoryResponseModel({this.items = const []});

  factory SearchHistoryResponseModel.fromJson(Map<String, dynamic> json) {
    final raw = json['items'];
    final items = raw is List
        ? raw
            .whereType<Map>()
            .map((e) =>
                SearchHistoryItemModel.fromJson(Map<String, dynamic>.from(e)))
            .where((e) => e.query.isNotEmpty)
            .toList()
        : <SearchHistoryItemModel>[];
    return SearchHistoryResponseModel(items: items);
  }

  @override
  List<Object?> get props => [items];
}
