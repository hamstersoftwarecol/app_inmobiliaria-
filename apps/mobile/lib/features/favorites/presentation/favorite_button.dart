import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../shared/widgets/async_view.dart';
import '../../auth/application/auth_controller.dart';
import '../application/favorites_controller.dart';

/// Heart toggle; hidden for roles without `favorites.manage`.
class FavoriteButton extends ConsumerWidget {
  const FavoriteButton({super.key, required this.slug, required this.title, this.color});

  final String slug;
  final String title;
  final Color? color;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).value;
    if (user == null || !user.can('favorites.manage')) return const SizedBox.shrink();
    final saved = ref.watch(favoriteSlugsProvider).value?.contains(slug) ?? false;
    final l10n = context.l10n;
    return IconButton(
      key: Key('favorite-$slug'),
      tooltip: saved ? l10n.t('removeFavorite', {'title': title}) : l10n.t('addFavorite', {'title': title}),
      isSelected: saved,
      icon: Icon(Icons.favorite_border, color: color),
      selectedIcon: const Icon(Icons.favorite, color: Colors.redAccent),
      onPressed: () async {
        try {
          await ref.read(favoriteSlugsProvider.notifier).toggle(slug);
        } catch (e) {
          if (context.mounted) showError(context, e);
        }
      },
    );
  }
}
