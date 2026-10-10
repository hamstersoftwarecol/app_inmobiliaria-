/// Client-side checks mirroring the API rules (the API stays authoritative).
/// Each returns a localization key or null.
abstract final class Validators {
  static final _email = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

  static String? required(String? value) => (value == null || value.trim().isEmpty) ? 'required' : null;

  static String? email(String? value) {
    if (required(value) != null) return 'required';
    return _email.hasMatch(value!.trim()) ? null : 'invalidEmail';
  }

  static String? strongPassword(String? value) {
    final v = value ?? '';
    final ok =
        v.length >= 12 &&
        v.length <= 128 &&
        RegExp(r'[a-z]').hasMatch(v) &&
        RegExp(r'[A-Z]').hasMatch(v) &&
        RegExp(r'[0-9]').hasMatch(v) &&
        RegExp(r'[^A-Za-z0-9]').hasMatch(v);
    return ok ? null : 'passwordRules';
  }

  static String? mfaCode(String? value) => RegExp(r'^\d{6}$').hasMatch(value?.trim() ?? '') ? null : 'mfaCodeInvalid';

  static final _money = RegExp(r'^\d+(\.\d{1,2})?$');
  static final _phone = RegExp(r'^\+?[0-9 ().-]{7,20}$');

  /// A positive dollar amount (up to 2 decimals).
  static String? money(String? value) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return 'required';
    if (!_money.hasMatch(v) || double.parse(v) > 999999999999) return 'invalidNumber';
    return double.parse(v) > 0 ? null : 'mustBePositive';
  }

  static String? optionalMoney(String? value) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return null;
    return _money.hasMatch(v) && double.parse(v) <= 999999999999 ? null : 'invalidNumber';
  }

  static String? optionalPercent(String? value) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return null;
    if (!RegExp(r'^\d{1,3}(\.\d{1,2})?$').hasMatch(v)) return 'invalidNumber';
    return double.parse(v) <= 100 ? null : 'percentRange';
  }

  static String? optionalEmail(String? value) => (value == null || value.trim().isEmpty) ? null : email(value);

  static String? optionalPhone(String? value) {
    final v = value?.trim() ?? '';
    return v.isEmpty || _phone.hasMatch(v) ? null : 'invalidPhone';
  }

  /// Required text of at most [max] characters.
  static String? Function(String?) requiredMax(int max) =>
      (value) => required(value) ?? ((value!.trim().length > max) ? 'tooLong' : null);
}

/// Parses an optional decimal form field ('' → null).
double? parseOptionalNumber(String text) => text.trim().isEmpty ? null : double.tryParse(text.trim());

/// Trimmed text, or null when empty.
String? nullIfBlank(String text) => text.trim().isEmpty ? null : text.trim();
