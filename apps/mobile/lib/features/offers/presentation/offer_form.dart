import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/util/format.dart';
import '../../auth/presentation/validators.dart';
import '../domain/offer.dart';

/// What the form collected (already validated on the device; the API stays authoritative).
class OfferFormValues {
  const OfferFormValues({required this.terms, this.buyerName = '', this.buyerAgentExternal, this.note});

  final OfferTerms terms;
  final String buyerName;
  final String? buyerAgentExternal;
  final String? note;
}

/// One submit button of the form ("Submit now", "Save draft", "Send counter"…).
class OfferFormAction {
  const OfferFormAction({required this.label, required this.onSubmit, this.key, this.primary = true});

  final String label;
  final Key? key;
  final bool primary;

  /// Throws [ApiException] on rejection; field errors are shown next to the fields.
  final Future<void> Function(OfferFormValues values) onSubmit;
}

/// Offer terms (amount, financing, dates, contingencies…), optionally with the buyer's name
/// (new offer / edit) or a note (counter-offer). Mirrors the web form and its validation.
class OfferForm extends ConsumerStatefulWidget {
  const OfferForm({super.key, required this.actions, this.initial, this.withBuyer = false, this.withNote = false});

  final List<OfferFormAction> actions;

  /// Pre-fills terms and buyer (edit or counter).
  final Offer? initial;
  final bool withBuyer;
  final bool withNote;

  @override
  ConsumerState<OfferForm> createState() => _OfferFormState();
}

class _OfferFormState extends ConsumerState<OfferForm> {
  final _formKey = GlobalKey<FormState>();
  late final _amount = TextEditingController(text: _num(widget.initial?.amount));
  late final _earnest = TextEditingController(text: _num(widget.initial?.earnestMoney));
  late final _downPayment = TextEditingController(text: _num(widget.initial?.downPaymentPercent));
  late final _terms = TextEditingController(text: widget.initial?.terms ?? '');
  late final _buyer = TextEditingController(text: widget.initial?.buyerName ?? '');
  late final _buyerAgent = TextEditingController(text: widget.initial?.buyerAgentExternal ?? '');
  final _note = TextEditingController();
  late String _financing = widget.initial?.financing ?? 'conventional';
  late String? _closingDate = widget.initial?.closingDate;
  late final Set<String> _contingencies = widget.initial == null
      ? {'attorney_review', 'inspection'}
      : widget.initial!.contingencies.toSet();
  DateTime? _expiresAt;
  Map<String, List<String>> _serverErrors = const {};
  String? _error;
  int? _busy;

  static String _num(double? v) => v == null ? '' : (v == v.roundToDouble() ? v.toInt().toString() : v.toString());

  @override
  void dispose() {
    for (final c in [_amount, _earnest, _downPayment, _terms, _buyer, _buyerAgent, _note]) {
      c.dispose();
    }
    super.dispose();
  }

  String? _fieldError(String field) => _serverErrors[field]?.first;

  String? Function(String?) _v(String? Function(String?) rule) => (value) {
    final key = rule(value);
    return key == null ? null : context.l10n.t(key);
  };

  Future<void> _pickClosingDate() async {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final current = DateTime.tryParse(_closingDate ?? '');
    final picked = await showDatePicker(
      context: context,
      firstDate: today,
      lastDate: today.add(const Duration(days: 730)),
      initialDate: current != null && !current.isBefore(today) ? current : today.add(const Duration(days: 45)),
    );
    if (picked != null) setState(() => _closingDate = picked.toIso8601String().substring(0, 10));
  }

  Future<void> _pickExpiry() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      firstDate: DateTime(now.year, now.month, now.day),
      lastDate: now.add(const Duration(days: 60)),
      initialDate: _expiresAt ?? now.add(const Duration(days: 1)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: _expiresAt != null ? TimeOfDay.fromDateTime(_expiresAt!) : const TimeOfDay(hour: 17, minute: 0),
    );
    if (time == null) return;
    setState(() => _expiresAt = DateTime(date.year, date.month, date.day, time.hour, time.minute));
  }

  Future<void> _submit(int index) async {
    final l10n = context.l10n;
    setState(() {
      _serverErrors = const {};
      _error = null;
    });
    if (!_formKey.currentState!.validate()) return;
    if (_expiresAt != null && !_expiresAt!.isAfter(DateTime.now())) {
      return setState(() => _error = l10n.t('futureTime'));
    }

    final values = OfferFormValues(
      terms: OfferTerms(
        amount: double.parse(_amount.text.trim()),
        earnestMoney: parseOptionalNumber(_earnest.text),
        financing: _financing,
        downPaymentPercent: _financing == 'cash' ? null : parseOptionalNumber(_downPayment.text),
        closingDate: _closingDate,
        contingencies: [
          for (final c in contingencyTypes)
            if (_contingencies.contains(c)) c,
        ],
        terms: nullIfBlank(_terms.text),
        expiresAt: _expiresAt,
      ),
      buyerName: _buyer.text.trim(),
      buyerAgentExternal: nullIfBlank(_buyerAgent.text),
      note: nullIfBlank(_note.text),
    );

    setState(() => _busy = index);
    try {
      await widget.actions[index].onSubmit(values);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _serverErrors = e.fieldErrors;
          _error = e.message;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    const gap = SizedBox(height: 12);

    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
          if (widget.withBuyer) ...[
            TextFormField(
              key: const Key('offer-buyer'),
              controller: _buyer,
              maxLength: 200,
              textCapitalization: TextCapitalization.words,
              decoration: InputDecoration(labelText: l10n.t('buyerName'), errorText: _fieldError('buyer_name')),
              validator: _v(Validators.requiredMax(200)),
            ),
            TextFormField(
              controller: _buyerAgent,
              maxLength: 200,
              decoration: InputDecoration(
                labelText: l10n.t('buyerAgentExternal'),
                errorText: _fieldError('buyer_agent_external'),
              ),
            ),
          ],
          TextFormField(
            key: const Key('offer-amount'),
            controller: _amount,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(
              labelText: l10n.t('offerAmount'),
              prefixText: r'$ ',
              errorText: _fieldError('amount'),
            ),
            validator: _v(Validators.money),
          ),
          gap,
          TextFormField(
            controller: _earnest,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(
              labelText: l10n.t('earnestMoney'),
              prefixText: r'$ ',
              errorText: _fieldError('earnest_money'),
            ),
            validator: _v(Validators.optionalMoney),
          ),
          gap,
          DropdownButtonFormField<String>(
            initialValue: _financing,
            decoration: InputDecoration(labelText: l10n.t('financing'), errorText: _fieldError('financing')),
            items: [for (final f in financingTypes) DropdownMenuItem(value: f, child: Text(l10n.t('fin_$f')))],
            onChanged: (v) => setState(() => _financing = v ?? _financing),
          ),
          if (_financing != 'cash') ...[
            gap,
            TextFormField(
              controller: _downPayment,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(
                labelText: l10n.t('downPayment'),
                suffixText: '%',
                errorText: _fieldError('down_payment_percent'),
              ),
              validator: _v(Validators.optionalPercent),
            ),
          ],
          gap,
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.event_outlined),
            title: Text(l10n.t('closingDate')),
            subtitle: Text(
              _closingDate == null ? l10n.t('noDate') : formatCalendarDate(_closingDate, lang),
              style: _fieldError('closing_date') == null ? null : TextStyle(color: Theme.of(context).colorScheme.error),
            ),
            trailing: _closingDate == null
                ? null
                : IconButton(
                    tooltip: l10n.t('clear'),
                    icon: const Icon(Icons.clear),
                    onPressed: () => setState(() => _closingDate = null),
                  ),
            onTap: _pickClosingDate,
          ),
          Text(l10n.t('contingencies'), style: Theme.of(context).textTheme.titleSmall),
          Wrap(
            spacing: 6,
            children: [
              for (final c in contingencyTypes)
                FilterChip(
                  label: Text(l10n.t('cont_$c')),
                  selected: _contingencies.contains(c),
                  onSelected: (on) => setState(() => on ? _contingencies.add(c) : _contingencies.remove(c)),
                ),
            ],
          ),
          gap,
          TextFormField(
            controller: _terms,
            maxLength: 5000,
            maxLines: 3,
            decoration: InputDecoration(labelText: l10n.t('otherTerms'), errorText: _fieldError('terms')),
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.timer_outlined),
            title: Text(l10n.t('responseDeadline')),
            subtitle: Text(_expiresAt == null ? l10n.t('noDeadline') : formatDateTime(_expiresAt, lang)),
            trailing: _expiresAt == null
                ? null
                : IconButton(
                    tooltip: l10n.t('clear'),
                    icon: const Icon(Icons.clear),
                    onPressed: () => setState(() => _expiresAt = null),
                  ),
            onTap: _pickExpiry,
          ),
          if (widget.withNote)
            TextFormField(
              controller: _note,
              maxLength: 1000,
              maxLines: 2,
              decoration: InputDecoration(labelText: l10n.t('noteOptional'), errorText: _fieldError('note')),
            ),
          const SizedBox(height: 8),
          for (final (i, action) in widget.actions.indexed)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: action.primary
                  ? FilledButton(
                      key: action.key,
                      onPressed: _busy == null ? () => _submit(i) : null,
                      child: _busy == i ? const _Spinner() : Text(action.label),
                    )
                  : OutlinedButton(
                      key: action.key,
                      onPressed: _busy == null ? () => _submit(i) : null,
                      child: _busy == i ? const _Spinner() : Text(action.label),
                    ),
            ),
          Text(l10n.t('offerDisclaimer'), style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}

class _Spinner extends StatelessWidget {
  const _Spinner();

  @override
  Widget build(BuildContext context) =>
      const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2));
}
