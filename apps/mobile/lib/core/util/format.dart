import 'dart:math';

import 'package:intl/intl.dart';

String _intlLocale(String languageCode) => languageCode == 'es' ? 'es_US' : 'en_US';

/// "$650,000" (or "$2,400/mo" for rentals); "—" when unknown.
String formatPrice(num? value, String languageCode, {bool rent = false}) {
  if (value == null) return '—';
  final text = NumberFormat.currency(locale: _intlLocale(languageCode), symbol: r'$', decimalDigits: 0).format(value);
  return rent ? '$text/mo' : text;
}

String formatNumber(num? value, String languageCode) =>
    value == null ? '—' : NumberFormat.decimalPattern(_intlLocale(languageCode)).format(value);

/// Local date and time, e.g. "Oct 10, 2026, 1:15 PM".
String formatDateTime(DateTime? value, String languageCode) =>
    value == null ? '—' : DateFormat.yMMMd(_intlLocale(languageCode)).add_jm().format(value.toLocal());

String formatDate(DateTime? value, String languageCode) =>
    value == null ? '—' : DateFormat.yMMMd(_intlLocale(languageCode)).format(value.toLocal());

/// A calendar date from the API ("2027-06-07") shown without timezone drift.
String formatCalendarDate(String? ymd, String languageCode) {
  final d = ymd == null ? null : DateTime.tryParse(ymd);
  return d == null ? '—' : DateFormat.yMMMd(_intlLocale(languageCode)).format(d);
}

/// Random key for the `Idempotency-Key` header (retries of the same action reuse it).
String newIdempotencyKey() {
  final random = Random.secure();
  final suffix = List.generate(4, (_) => random.nextInt(1 << 32).toRadixString(36)).join();
  return 'm-${DateTime.now().microsecondsSinceEpoch.toRadixString(36)}-$suffix';
}
