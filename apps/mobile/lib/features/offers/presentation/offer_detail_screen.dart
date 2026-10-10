import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/router/app_router.dart';
import '../../../core/util/format.dart';
import '../../../shared/widgets/async_view.dart';
import '../../auth/presentation/validators.dart';
import '../data/offer_repository.dart';
import '../domain/offer.dart';
import 'offer_form.dart';
import 'offers_screen.dart';

/// Records a buyer's offer on a listing (agents and managers).
class NewOfferScreen extends ConsumerStatefulWidget {
  const NewOfferScreen({super.key, this.propertySlug, this.propertyTitle});

  final String? propertySlug;
  final String? propertyTitle;

  @override
  ConsumerState<NewOfferScreen> createState() => _NewOfferScreenState();
}

class _NewOfferScreenState extends ConsumerState<NewOfferScreen> {
  // One key per visit: a retry after a timeout replays the same request instead of a duplicate offer.
  final _idempotencyKey = newIdempotencyKey();
  late final _slug = TextEditingController(text: widget.propertySlug ?? '');
  String? _slugError;

  @override
  void dispose() {
    _slug.dispose();
    super.dispose();
  }

  Future<void> _create(OfferFormValues v, {required bool submit}) async {
    final slug = _slug.text.trim();
    if (slug.isEmpty) {
      setState(() => _slugError = context.l10n.t('required'));
      return;
    }
    setState(() => _slugError = null);
    final offer = await ref
        .read(offerRepositoryProvider)
        .create(
          propertySlug: slug,
          buyerName: v.buyerName,
          buyerAgentExternal: v.buyerAgentExternal,
          terms: v.terms,
          submit: submit,
          idempotencyKey: '$_idempotencyKey-${submit ? 's' : 'd'}',
        );
    ref.invalidate(offersProvider);
    if (mounted) context.pushReplacement(Routes.offer(offer.id));
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('newOffer'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (widget.propertyTitle != null && widget.propertySlug != null)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.home_outlined),
              title: Text(widget.propertyTitle!),
              subtitle: Text(l10n.t('property')),
            )
          else
            TextField(
              key: const Key('offer-property'),
              controller: _slug,
              autocorrect: false,
              decoration: InputDecoration(
                labelText: l10n.t('propertySlug'),
                helperText: l10n.t('propertySlugHint'),
                helperMaxLines: 3,
                errorText: _slugError,
              ),
            ),
          const SizedBox(height: 12),
          OfferForm(
            withBuyer: true,
            actions: [
              OfferFormAction(
                key: const Key('offer-submit-now'),
                label: l10n.t('submitNow'),
                onSubmit: (v) => _create(v, submit: true),
              ),
              OfferFormAction(
                key: const Key('offer-save-draft'),
                label: l10n.t('saveDraft'),
                primary: false,
                onSubmit: (v) => _create(v, submit: false),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// The whole negotiation (offer and counters) with the actions the API allows on the latest one.
class OfferDetailScreen extends ConsumerWidget {
  const OfferDetailScreen({super.key, required this.id});

  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final chain = ref.watch(offerChainProvider(id));

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('negotiation'))),
      body: AsyncView<List<Offer>>(
        value: chain,
        onRetry: () => ref.invalidate(offerChainProvider(id)),
        data: (offers) {
          if (offers.isEmpty) return EmptyState(message: l10n.t('notFound'));
          final first = offers.first;
          return RefreshIndicator(
            onRefresh: () => ref.refresh(offerChainProvider(id).future),
            child: ListView(
              padding: const EdgeInsets.all(12),
              children: [
                Text(first.propertyTitle ?? '—', style: Theme.of(context).textTheme.titleLarge),
                Text(
                  [
                    first.buyerName,
                    ?first.buyerAgentName,
                    ?first.buyerAgentExternal,
                    if (first.listPrice != null) l10n.t('listPriceOf', {'price': formatPrice(first.listPrice, lang)}),
                  ].join(' · '),
                ),
                if (first.propertySlug != null)
                  Align(
                    alignment: AlignmentDirectional.centerStart,
                    child: TextButton.icon(
                      icon: const Icon(Icons.home_outlined),
                      label: Text(l10n.t('viewListing')),
                      onPressed: () => context.push(Routes.property(first.propertySlug!)),
                    ),
                  ),
                const SizedBox(height: 8),
                for (final (i, o) in offers.indexed) _OfferCard(offer: o, chainId: id, latest: i == offers.length - 1),
                const SizedBox(height: 8),
                Text(l10n.t('offerDisclaimer'), style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _OfferCard extends ConsumerStatefulWidget {
  const _OfferCard({required this.offer, required this.chainId, required this.latest});

  final Offer offer;
  final int chainId;
  final bool latest;

  @override
  ConsumerState<_OfferCard> createState() => _OfferCardState();
}

class _OfferCardState extends ConsumerState<_OfferCard> {
  bool _busy = false;

  Offer get o => widget.offer;

  void _refresh() {
    ref.invalidate(offerChainProvider(widget.chainId));
    ref.invalidate(offersProvider);
  }

  Future<void> _run(Future<void> Function() action) async {
    setState(() => _busy = true);
    try {
      await action();
      _refresh();
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<bool> _confirm(String messageKey) async {
    final l10n = context.l10n;
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        content: Text(l10n.t(messageKey)),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l10n.t('cancel'))),
          FilledButton(
            key: const Key('confirm-dialog'),
            onPressed: () => Navigator.pop(context, true),
            child: Text(l10n.t('confirm')),
          ),
        ],
      ),
    );
    return ok == true;
  }

  /// Asks for an optional note; returns null when cancelled.
  Future<String?> _askNote(String titleKey) async {
    final l10n = context.l10n;
    final controller = TextEditingController();
    final note = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.t(titleKey)),
        content: TextField(
          key: const Key('offer-note'),
          controller: controller,
          maxLength: 1000,
          maxLines: 3,
          decoration: InputDecoration(labelText: l10n.t('noteOptional')),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: Text(l10n.t('cancel'))),
          FilledButton(
            key: const Key('confirm-dialog'),
            onPressed: () => Navigator.pop(context, controller.text.trim()),
            child: Text(l10n.t('confirm')),
          ),
        ],
      ),
    );
    controller.dispose();
    return note;
  }

  Future<void> _openSheet({required String titleKey, required Widget Function(BuildContext) form}) =>
      showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        builder: (sheetContext) => Padding(
          padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + MediaQuery.viewInsetsOf(sheetContext).bottom),
          child: ListView(
            shrinkWrap: true,
            children: [
              Text(context.l10n.t(titleKey), style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 12),
              form(sheetContext),
            ],
          ),
        ),
      );

  void _edit() => _openSheet(
    titleKey: 'editOffer',
    form: (sheet) => OfferForm(
      initial: o,
      withBuyer: true,
      actions: [
        OfferFormAction(
          label: context.l10n.t('save'),
          onSubmit: (v) async {
            await ref
                .read(offerRepositoryProvider)
                .update(o.id, buyerName: v.buyerName, buyerAgentExternal: v.buyerAgentExternal, terms: v.terms);
            _refresh();
            if (sheet.mounted) Navigator.pop(sheet);
          },
        ),
      ],
    ),
  );

  void _counter() => _openSheet(
    titleKey: 'counterOffer',
    form: (sheet) => OfferForm(
      initial: o,
      withNote: true,
      actions: [
        OfferFormAction(
          key: const Key('send-counter'),
          label: context.l10n.t('sendCounter'),
          onSubmit: (v) async {
            await ref.read(offerRepositoryProvider).counter(o.id, v.terms, note: v.note);
            _refresh();
            if (sheet.mounted) Navigator.pop(sheet);
          },
        ),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final text = Theme.of(context).textTheme;
    final repo = ref.read(offerRepositoryProvider);

    Widget row(String label, String value) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: Text(label, style: text.bodySmall)),
          Expanded(child: Text(value, textAlign: TextAlign.end)),
        ],
      ),
    );

    final can = widget.latest ? o.can : const OfferAbilities();
    final actions = <Widget>[
      if (can.edit) OutlinedButton(onPressed: _busy ? null : _edit, child: Text(l10n.t('edit'))),
      if (can.submit)
        FilledButton(
          key: const Key('offer-submit'),
          onPressed: _busy ? null : () => _run(() => repo.submit(o.id)),
          child: Text(l10n.t('submitOffer')),
        ),
      if (can.respond) ...[
        FilledButton(
          key: const Key('offer-accept'),
          onPressed: _busy
              ? null
              : () async {
                  if (!await _confirm('confirmAccept')) return;
                  await _run(() async {
                    final txId = await repo.accept(o.id);
                    if (mounted) unawaited(this.context.push(Routes.transaction(txId)));
                  });
                },
          child: Text(l10n.t('accept')),
        ),
        OutlinedButton(
          key: const Key('offer-counter'),
          onPressed: _busy ? null : _counter,
          child: Text(l10n.t('counterOffer')),
        ),
        OutlinedButton(
          key: const Key('offer-reject'),
          onPressed: _busy
              ? null
              : () async {
                  final note = await _askNote('rejectOffer');
                  if (note != null) await _run(() => repo.reject(o.id, note: nullIfBlank(note)));
                },
          child: Text(l10n.t('reject')),
        ),
      ],
      if (can.withdraw)
        TextButton(
          key: const Key('offer-withdraw'),
          onPressed: _busy
              ? null
              : () async {
                  if (await _confirm('confirmWithdraw')) await _run(() => repo.withdraw(o.id));
                },
          child: Text(l10n.t('withdraw')),
        ),
      if (o.transactionId != null)
        FilledButton.tonal(
          onPressed: () => context.push(Routes.transaction(o.transactionId!)),
          child: Text(l10n.t('openTransaction')),
        ),
    ];

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text('${l10n.t('side_${o.side}')} · ${formatPrice(o.amount, lang)}', style: text.titleMedium),
                ),
                OfferStatusChip(status: o.status),
              ],
            ),
            if (o.submittedAt != null)
              Text(l10n.t('submittedOn', {'date': formatDateTime(o.submittedAt, lang)}), style: text.bodySmall),
            const Divider(),
            row(l10n.t('earnestMoney'), formatPrice(o.earnestMoney, lang)),
            row(
              l10n.t('financing'),
              l10n.t('fin_${o.financing}') +
                  (o.downPaymentPercent != null ? ' · ${formatNumber(o.downPaymentPercent, lang)}%' : ''),
            ),
            row(l10n.t('closingDate'), formatCalendarDate(o.closingDate, lang)),
            row(
              l10n.t('contingencies'),
              o.contingencies.isEmpty ? '—' : o.contingencies.map((c) => l10n.t('cont_$c')).join(', '),
            ),
            if (o.terms != null) ...[
              const SizedBox(height: 4),
              Text(l10n.t('otherTerms'), style: text.bodySmall),
              Text(o.terms!),
            ],
            if (o.expiresAt != null && o.status == 'submitted')
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  l10n.t('expiresOn', {'date': formatDateTime(o.expiresAt, lang)}),
                  style: TextStyle(color: Theme.of(context).colorScheme.tertiary),
                ),
              ),
            if (o.responseNote != null) ...[
              const SizedBox(height: 4),
              Text(l10n.t('note'), style: text.bodySmall),
              Text(o.responseNote!),
            ],
            if (actions.isNotEmpty) ...[
              const SizedBox(height: 8),
              if (_busy) const LinearProgressIndicator(),
              Wrap(spacing: 8, runSpacing: 4, children: actions),
            ],
          ],
        ),
      ),
    );
  }
}
