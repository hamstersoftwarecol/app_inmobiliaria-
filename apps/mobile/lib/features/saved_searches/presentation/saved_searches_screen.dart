import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/router/app_router.dart';
import '../../../shared/widgets/async_view.dart';
import '../../properties/application/search_controller.dart';
import '../data/saved_search_repository.dart';
import '../domain/saved_search.dart';

class SavedSearchesScreen extends ConsumerWidget {
  const SavedSearchesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final searches = ref.watch(savedSearchesProvider);
    final repo = ref.read(savedSearchRepositoryProvider);

    Future<void> run(Future<void> Function() action) async {
      try {
        await action();
        ref.invalidate(savedSearchesProvider);
      } catch (e) {
        if (context.mounted) showError(context, e);
      }
    }

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('savedSearches'))),
      body: AsyncView(
        value: searches,
        onRetry: () => ref.invalidate(savedSearchesProvider),
        data: (list) => list.isEmpty
            ? EmptyState(message: l10n.t('noSavedSearches'), icon: Icons.bookmark_border)
            : ListView.separated(
                itemCount: list.length,
                separatorBuilder: (_, _) => const Divider(height: 1),
                itemBuilder: (context, i) {
                  final s = list[i];
                  return ListTile(
                    title: Text(s.name),
                    subtitle: DropdownButton<String>(
                      value: s.alertFrequency,
                      isDense: true,
                      items: [
                        for (final f in SavedSearch.frequencies)
                          DropdownMenuItem(value: f, child: Text(l10n.t('alert_$f'))),
                      ],
                      onChanged: (v) => v == null ? null : run(() => repo.setFrequency(s.id, v)),
                    ),
                    trailing: IconButton(
                      tooltip: l10n.t('delete'),
                      icon: const Icon(Icons.delete_outline),
                      onPressed: () => run(() => repo.remove(s.id)),
                    ),
                    onTap: () {
                      ref.read(searchFiltersProvider.notifier).set(s.toFilters());
                      context.go(Routes.search);
                    },
                  );
                },
              ),
      ),
    );
  }
}
