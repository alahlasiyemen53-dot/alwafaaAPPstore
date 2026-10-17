final class PageInfo {
  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;
  final bool hasMore;

  const PageInfo({
    required this.currentPage,
    required this.perPage,
    required this.total,
    required this.lastPage,
    required this.hasMore,
  });

  factory PageInfo.fromJson(Map<String, dynamic> json) => PageInfo(
        currentPage: json['current_page'] as int? ?? 1,
        perPage: json['per_page'] as int? ?? 15,
        total: json['total'] as int? ?? 0,
        lastPage: json['last_page'] as int? ?? 1,
        hasMore: json['has_more'] as bool? ?? false,
      );
}
