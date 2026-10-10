import '../../../core/network/json.dart';
import '../../properties/domain/property.dart';

class SavedSearch {
  const SavedSearch({
    required this.id,
    required this.name,
    required this.filters,
    this.sort,
    required this.alertFrequency,
  });

  factory SavedSearch.fromJson(Map<String, dynamic> j) => SavedSearch(
    id: asInt(j['id']) ?? 0,
    name: asString(j['name']) ?? '',
    filters: asMap(j['filters']),
    sort: asString(j['sort']),
    alertFrequency: asString(j['alert_frequency']) ?? 'off',
  );

  static const frequencies = ['off', 'daily', 'weekly'];

  final int id;
  final String name;
  final Map<String, dynamic> filters;
  final String? sort;
  final String alertFrequency;

  SearchFilters toFilters() => SearchFilters.fromSaved(filters, sort);
}
