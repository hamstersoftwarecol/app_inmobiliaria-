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
}
