import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/router/app_router.dart';
import '../../../core/util/format.dart';
import '../../../shared/widgets/async_view.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/domain/user.dart';
import '../data/offer_repository.dart';
import '../domain/offer.dart';

bool canUseOffers(User user) => ['offers.view_any', 'offers.manage_own', 'offers.manage_any'].any(user.can);

bool canRecordOffers(User user) => user.can('offers.manage_own') || user.can('offers.manage_any');

/// Negotiations the agent (or manager) takes part in, newest activity first.
class OffersScreen extends ConsumerStatefulWidget {
  const OffersScreen({super.key});

  @override
  ConsumerState<OffersScreen> createState() => _OffersScreenState();
}

class _OffersScreenState extends ConsumerState<OffersScreen> {
  String? _status;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final user = ref.watch(authControllerProvider).value;
    final list = ref.watch(offersProvider(_status));

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('offers'))),
      floatingActionButton: user != null && canRecordOffers(user)
          ? FloatingActionButton.extended(
              key: const Key('new-offer'),
              icon: const Icon(Icons.add),
              label: Text(l10n.t('newOffer')),
              onPressed: () => context.push(Routes.newOffer()),
            )
          : null,
      body: Column(
        children: [
          SizedBox(
            height: 56,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              children: [
                for (final s in [null, ...offerStatuses])
                  Padding(
                    padding: const EdgeInsets.only(right: 6),
                    child: ChoiceChip(
                      label: Text(s == null ? l10n.t('all') : l10n.t('offer_$s')),
                      selected: _status == s,
                      onSelected: (_) => setState(() => _status = s),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => ref.refresh(offersProvider(_status).future),
              child: AsyncView(
                value: list,
                onRetry: () => ref.invalidate(offersProvider(_status)),
                data: (page) => page.items.isEmpty
                    ? ListView(
                        children: [
                          const SizedBox(height: 80),
                          EmptyState(message: l10n.t('noOffers'), icon: Icons.request_quote_outlined),
                        ],
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.only(bottom: 88),
                        itemCount: page.items.length,
                        separatorBuilder: (_, _) => const Divider(height: 1),
                        itemBuilder: (context, i) {
                          final o = page.items[i];
                          return ListTile(
                            title: Text(o.propertyTitle ?? '—'),
                            subtitle: Text(
                              [formatPrice(o.amount, lang), o.buyerName, l10n.t('side_${o.side}')].join(' · '),
                            ),
                            trailing: OfferStatusChip(status: o.status),
                            onTap: () => context.push(Routes.offer(o.id)),
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

class OfferStatusChip extends StatelessWidget {
  const OfferStatusChip({super.key, required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final (bg, fg) = switch (status) {
      'accepted' || 'closed' => (scheme.primaryContainer, scheme.onPrimaryContainer),
      'rejected' || 'terminated' => (scheme.errorContainer, scheme.onErrorContainer),
      'submitted' || 'countered' => (scheme.secondaryContainer, scheme.onSecondaryContainer),
      _ => (scheme.surfaceContainerHighest, scheme.onSurfaceVariant),
    };
    return Chip(
      backgroundColor: bg,
      side: BorderSide.none,
      label: Text(context.l10n.t('offer_$status'), style: TextStyle(color: fg)),
    );
  }
}
