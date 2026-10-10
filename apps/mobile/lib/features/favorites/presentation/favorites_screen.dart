import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../shared/widgets/async_view.dart';
import '../../auth/application/auth_controller.dart';
import '../../properties/presentation/property_card.dart';
import '../application/favorites_controller.dart';

class FavoritesScreen extends ConsumerWidget {
  const FavoritesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final user = ref.watch(authControllerProvider).value;
    if (user != null && !user.can('favorites.manage')) {
      return Scaffold(
        appBar: AppBar(title: Text(l10n.t('favorites'))),
        body: EmptyState(message: l10n.t('forbidden')),
      );
    }
    final list = ref.watch(favoritesListProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('favorites'))),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(favoritesListProvider.future),
        child: AsyncView(
          value: list,
          onRetry: () => ref.invalidate(favoritesListProvider),
          data: (page) => page.items.isEmpty
              ? ListView(
                  children: [
                    const SizedBox(height: 80),
                    EmptyState(message: l10n.t('noFavorites'), icon: Icons.favorite_border),
                  ],
                )
              : ListView(
                  padding: const EdgeInsets.all(12),
                  children: [for (final p in page.items) PropertyCard(property: p)],
                ),
        ),
      ),
    );
  }
}
