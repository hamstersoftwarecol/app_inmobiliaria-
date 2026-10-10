import 'json.dart';

/// One page of a Laravel paginated response (`data` + `meta.current_page/last_page/total`).
class Paginated<T> {
  const Paginated({required this.items, required this.page, required this.lastPage, required this.total});

  factory Paginated.fromJson(Map<String, dynamic> json, T Function(Map<String, dynamic>) parse) {
    final meta = asMap(json['meta']);
    return Paginated(
      items: asList(json['data'], parse),
      page: asInt(meta['current_page']) ?? 1,
      lastPage: asInt(meta['last_page']) ?? 1,
      total: asInt(meta['total']) ?? 0,
    );
  }

  final List<T> items;
  final int page;
  final int lastPage;
  final int total;

  bool get hasMore => page < lastPage;
}
