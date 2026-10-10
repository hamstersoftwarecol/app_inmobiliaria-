/// Build-time configuration passed with `--dart-define`.
///
/// Example: `flutter run --dart-define=API_BASE_URL=https://api.example.com`
/// Defaults target a local API from the Android emulator (10.0.2.2 maps to
/// the host machine). Never put secrets here: the binary can be inspected.
class AppConfig {
  const AppConfig._();

  static const String apiBaseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8000');

  static String get apiV1 => '${apiBaseUrl.replaceAll(RegExp(r'/$'), '')}/api/v1';

  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 30);
}
