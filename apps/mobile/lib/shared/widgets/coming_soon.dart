import 'package:flutter/material.dart';

import '../../core/l10n/app_localizations.dart';

/// Honest placeholder for modules scheduled in later sprints.
class ComingSoon extends StatelessWidget {
  const ComingSoon({super.key, required this.titleKey, this.bodyKey = 'comingSoonBody'});

  final String titleKey;
  final String bodyKey;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.t(titleKey))),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.construction_outlined, size: 48),
              const SizedBox(height: 12),
              Chip(label: Text(l10n.t('comingSoon'))),
              const SizedBox(height: 12),
              Text(l10n.t(bodyKey), textAlign: TextAlign.center),
            ],
          ),
        ),
      ),
    );
  }
}
