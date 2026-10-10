import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../shared/widgets/async_view.dart';
import '../../auth/application/auth_controller.dart';
import '../../saved_searches/presentation/save_search_dialog.dart';
import '../application/search_controller.dart';
import '../data/property_repository.dart';
import '../domain/property.dart';
import 'filter_sheet.dart';
import 'property_card.dart';

class SearchScreen extends ConsumerStatefulWidget {
  const SearchScreen({super.key});

  @override
  ConsumerState<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends ConsumerState<SearchScreen> {
  late final _location = TextEditingController(text: ref.read(searchFiltersProvider).locationLabel ?? '');
  final _focus = FocusNode();
  final _scroll = ScrollController();
  Timer? _debounce;
  List<LocationSuggestion> _suggestions = const [];

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 400) {
        ref.read(searchResultsProvider.notifier).loadMore().catchError((Object e) {
          if (mounted) showError(context, e);
        });
      }
    });
    _focus.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _location.dispose();
    _focus.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _onTyping(String text) {
    _debounce?.cancel();
    if (text.trim().length < 2) return setState(() => _suggestions = const []);
    _debounce = Timer(const Duration(milliseconds: 300), () async {
      try {
        final found = await ref.read(propertyRepositoryProvider).locations(text.trim());
        if (mounted && _location.text == text) setState(() => _suggestions = found);
      } catch (_) {
        // Suggestions are optional; typing a city or ZIP still works.
      }
    });
  }

  void _applyLocation(String text, {LocationSuggestion? suggestion}) {
    final filters = ref.read(searchFiltersProvider);
    final next = suggestion == null
        ? filters.withLocation(text)
        : filters.copyWith(
            city: () => suggestion.type == 'postal_code' ? null : suggestion.city,
            postalCode: () => suggestion.type == 'postal_code' ? suggestion.postalCode : null,
            locationLabel: () => suggestion.label,
          );
    if (suggestion != null) _location.text = suggestion.label;
    setState(() => _suggestions = const []);
    _focus.unfocus();
    ref.read(searchFiltersProvider.notifier).set(next);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final filters = ref.watch(searchFiltersProvider);
    final results = ref.watch(searchResultsProvider);
    final user = ref.watch(authControllerProvider).value;
    ref.listen(searchFiltersProvider, (_, next) {
      if ((next.locationLabel ?? '') != _location.text) _location.text = next.locationLabel ?? '';
    });

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.t('search')),
        actions: [
          if (user != null && user.can('saved_searches.manage') && !filters.isEmpty)
            IconButton(
              key: const Key('save-search'),
              tooltip: l10n.t('saveSearch'),
              icon: const Icon(Icons.bookmark_add_outlined),
              onPressed: () => SaveSearchDialog.show(context, filters),
            ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    key: const Key('search-location'),
                    controller: _location,
                    focusNode: _focus,
                    textInputAction: TextInputAction.search,
                    decoration: InputDecoration(
                      labelText: l10n.t('locationLabel'),
                      hintText: l10n.t('locationHint'),
                      prefixIcon: const Icon(Icons.search),
                      isDense: true,
                    ),
                    onChanged: _onTyping,
                    onSubmitted: (v) => _applyLocation(v),
                  ),
                ),
                const SizedBox(width: 8),
                Badge(
                  isLabelVisible:
                      filters.filterMap().keys.any((k) => k != 'city' && k != 'postal_code') ||
                      filters.sort != 'newest',
                  child: IconButton.outlined(
                    key: const Key('open-filters'),
                    tooltip: l10n.t('filters'),
                    icon: const Icon(Icons.tune),
                    onPressed: () async {
                      final next = await FilterSheet.show(context, filters);
                      if (next != null) ref.read(searchFiltersProvider.notifier).set(next);
                    },
                  ),
                ),
              ],
            ),
          ),
          if (_focus.hasFocus && _suggestions.isNotEmpty)
            Card(
              margin: const EdgeInsets.symmetric(horizontal: 12),
              child: Column(
                children: [
                  for (final s in _suggestions.take(6))
                    ListTile(
                      dense: true,
                      leading: Icon(s.type == 'postal_code' ? Icons.markunread_mailbox_outlined : Icons.location_city),
                      title: Text(s.label),
                      trailing: Text(l10n.t('listingsCount', {'n': '${s.listings}'})),
                      onTap: () => _applyLocation(s.label, suggestion: s),
                    ),
                ],
              ),
            ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => ref.refresh(searchResultsProvider.future),
              child: AsyncView(
                value: results,
                onRetry: () => ref.invalidate(searchResultsProvider),
                data: (r) => r.items.isEmpty
                    ? ListView(
                        children: [
                          const SizedBox(height: 80),
                          EmptyState(message: l10n.t('noResults'), icon: Icons.search_off),
                        ],
                      )
                    : ListView.builder(
                        controller: _scroll,
                        padding: const EdgeInsets.all(12),
                        itemCount: r.items.length + 1,
                        itemBuilder: (context, i) {
                          if (i == 0) {
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Text(
                                l10n.t('resultsCount', {'n': '${r.total}'}),
                                style: Theme.of(context).textTheme.labelLarge,
                              ),
                            );
                          }
                          final item = r.items[i - 1];
                          final last = i == r.items.length;
                          return Column(
                            children: [
                              PropertyCard(property: item),
                              if (last && r.loadingMore)
                                const Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator()),
                            ],
                          );
                        },
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
