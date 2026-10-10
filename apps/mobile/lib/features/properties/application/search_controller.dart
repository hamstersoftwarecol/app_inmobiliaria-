import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/property_repository.dart';
import '../domain/property.dart';

class SearchFiltersController extends Notifier<SearchFilters> {
  @override
  SearchFilters build() => const SearchFilters();

  void set(SearchFilters filters) => state = filters;
}

final searchFiltersProvider = NotifierProvider<SearchFiltersController, SearchFilters>(SearchFiltersController.new);

/// Accumulated results for the current filters, with infinite scrolling.
class SearchResults {
  const SearchResults({
    required this.items,
    required this.total,
    required this.page,
    required this.hasMore,
    this.loadingMore = false,
  });

  final List<PropertySummary> items;
  final int total;
  final int page;
  final bool hasMore;
  final bool loadingMore;

  SearchResults copyWith({List<PropertySummary>? items, int? page, bool? hasMore, bool? loadingMore}) => SearchResults(
    items: items ?? this.items,
    total: total,
    page: page ?? this.page,
    hasMore: hasMore ?? this.hasMore,
    loadingMore: loadingMore ?? this.loadingMore,
  );
}

class SearchResultsController extends AsyncNotifier<SearchResults> {
  @override
  Future<SearchResults> build() async {
    final filters = ref.watch(searchFiltersProvider);
    final page = await ref.read(propertyRepositoryProvider).search(filters);
    return SearchResults(items: page.items, total: page.total, page: page.page, hasMore: page.hasMore);
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore || current.loadingMore) return;
    state = AsyncData(current.copyWith(loadingMore: true));
    try {
      final next = await ref
          .read(propertyRepositoryProvider)
          .search(ref.read(searchFiltersProvider), page: current.page + 1);
      state = AsyncData(
        current.copyWith(
          items: [...current.items, ...next.items],
          page: next.page,
          hasMore: next.hasMore,
          loadingMore: false,
        ),
      );
    } catch (_) {
      state = AsyncData(current.copyWith(loadingMore: false));
      rethrow;
    }
  }
}

final searchResultsProvider = AsyncNotifierProvider<SearchResultsController, SearchResults>(
  SearchResultsController.new,
);

final propertyDetailProvider = FutureProvider.autoDispose.family<PropertyDetail, String>(
  (ref, slug) => ref.watch(propertyRepositoryProvider).get(slug),
);
