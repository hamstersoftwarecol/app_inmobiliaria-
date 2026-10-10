import 'dart:ui';

import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Current UI language (English default, Spanish secondary).
class LocaleController extends Notifier<Locale> {
  @override
  Locale build() {
    final system = PlatformDispatcher.instance.locale.languageCode;
    return Locale(system == 'es' ? 'es' : 'en');
  }

  void setLocale(Locale locale) => state = locale;
}

final localeControllerProvider = NotifierProvider<LocaleController, Locale>(LocaleController.new);
