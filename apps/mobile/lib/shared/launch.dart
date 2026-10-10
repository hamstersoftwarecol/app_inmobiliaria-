import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/l10n/app_localizations.dart';

/// Opens a URL (https, tel:, mailto:) outside the app; reports failures in a snackbar.
Future<void> openExternal(BuildContext context, Uri uri) async {
  final ok = await launchUrl(uri, mode: LaunchMode.externalApplication).catchError((Object _) => false);
  if (!ok && context.mounted) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.l10n.t('cannotOpen'))));
  }
}

Uri telUri(String phone) => Uri(scheme: 'tel', path: phone.replaceAll(RegExp(r'[^+\d]'), ''));

Uri mailUri(String email) => Uri(scheme: 'mailto', path: email);
