import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/router/app_router.dart';
import '../../../core/util/format.dart';
import '../../favorites/presentation/favorite_button.dart';
import '../domain/property.dart';

class PropertyCard extends ConsumerWidget {
  const PropertyCard({super.key, required this.property});

  final PropertySummary property;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final p = property;
    final facts = [
      if (p.bedrooms != null) l10n.t('bedsShort', {'n': '${p.bedrooms}'}),
      if (p.bathrooms != null) l10n.t('bathsShort', {'n': formatNumber(p.bathrooms, lang)}),
      if (p.livingAreaSqft != null) l10n.t('sqftShort', {'n': formatNumber(p.livingAreaSqft, lang)}),
    ].join(' · ');

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push(Routes.property(p.slug)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                AspectRatio(
                  aspectRatio: 16 / 9,
                  child: p.coverPhoto == null
                      ? ColoredBox(
                          color: Theme.of(context).colorScheme.surfaceContainerHighest,
                          child: const Center(child: Icon(Icons.home_outlined, size: 40)),
                        )
                      : Image.network(
                          p.coverPhoto!,
                          fit: BoxFit.cover,
                          semanticLabel: p.title,
                          errorBuilder: (_, _, _) => const Center(child: Icon(Icons.broken_image_outlined)),
                        ),
                ),
                Positioned(
                  right: 4,
                  top: 4,
                  child: CircleAvatar(
                    backgroundColor: Colors.white.withValues(alpha: 0.9),
                    child: FavoriteButton(slug: p.slug, title: p.title),
                  ),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(formatPrice(p.listPrice, lang, rent: p.isRent), style: Theme.of(context).textTheme.titleLarge),
                  Text(p.title, maxLines: 1, overflow: TextOverflow.ellipsis),
                  if (facts.isNotEmpty) Text(facts, style: Theme.of(context).textTheme.bodySmall),
                  Text(p.addressLine, style: Theme.of(context).textTheme.bodySmall),
                  if (p.compliance?.attribution != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(p.compliance!.attribution!, style: Theme.of(context).textTheme.labelSmall),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
