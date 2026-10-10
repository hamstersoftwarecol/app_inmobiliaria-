import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/paginated.dart';
import '../../auth/application/auth_controller.dart';
import '../../properties/domain/property.dart';
import '../data/favorites_repository.dart';

/// Saved listing slugs for the signed-in user (empty for roles without `favorites.manage`).
class FavoriteSlugsController extends AsyncNotifier<Set<String>> {
  @override
  Future<Set<String>> build() async {
    final user = ref.watch(authControllerProvider).value;
    if (user == null || !user.can('favorites.manage')) return {};
    return ref.read(favoritesRepositoryProvider).slugs();
  }

  /// Optimistic toggle; reverts if the API refuses.
  Future<void> toggle(String slug) async {
    final current = state.value ?? {};
    final saved = current.contains(slug);
    state = AsyncData(saved ? ({...current}..remove(slug)) : {...current, slug});
    try {
      final repo = ref.read(favoritesRepositoryProvider);
      saved ? await repo.remove(slug) : await repo.add(slug);
      ref.invalidate(favoritesListProvider);
    } catch (_) {
      state = AsyncData(current);
      rethrow;
    }
  }
}

final favoriteSlugsProvider = AsyncNotifierProvider<FavoriteSlugsController, Set<String>>(FavoriteSlugsController.new);

final favoritesListProvider = FutureProvider.autoDispose<Paginated<PropertySummary>>(
  (ref) => ref.watch(favoritesRepositoryProvider).list(),
);
