import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/router/app_router.dart';
import '../../../core/util/format.dart';
import '../../../shared/launch.dart';
import '../../../shared/widgets/async_view.dart';
import '../../auth/application/auth_controller.dart';
import '../../favorites/presentation/favorite_button.dart';
import '../../showings/presentation/request_showing_sheet.dart';
import '../application/search_controller.dart';
import '../domain/property.dart';

class PropertyDetailScreen extends ConsumerWidget {
  const PropertyDetailScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(propertyDetailProvider(slug));
    final title = detail.value?.summary.title;
    return Scaffold(
      appBar: AppBar(
        title: Text(title ?? ''),
        actions: [if (title != null) FavoriteButton(slug: slug, title: title, color: Colors.white)],
      ),
      body: AsyncView(
        value: detail,
        onRetry: () => ref.invalidate(propertyDetailProvider(slug)),
        data: (p) => _Body(property: p),
      ),
    );
  }
}

class _Body extends ConsumerWidget {
  const _Body({required this.property});

  final PropertyDetail property;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final user = ref.watch(authControllerProvider).value;
    final s = property.summary;
    final text = Theme.of(context).textTheme;

    final facts = <(String, String)>[
      if (s.bedrooms != null) (l10n.t('bedrooms'), '${s.bedrooms}'),
      if (s.bathrooms != null) (l10n.t('bathrooms'), formatNumber(s.bathrooms, lang)),
      if (s.livingAreaSqft != null)
        (l10n.t('livingArea'), l10n.t('sqftShort', {'n': formatNumber(s.livingAreaSqft, lang)})),
      if (property.lotSizeSqft != null)
        (l10n.t('lotSize'), l10n.t('sqftShort', {'n': formatNumber(property.lotSizeSqft, lang)})),
      if (property.yearBuilt != null) (l10n.t('yearBuilt'), '${property.yearBuilt}'),
      if (property.garageSpaces != null) (l10n.t('garage'), '${property.garageSpaces}'),
      if (property.hoaFeeMonthly != null) (l10n.t('hoaFee'), formatPrice(property.hoaFeeMonthly, lang, rent: true)),
      if (property.annualTaxes != null) (l10n.t('annualTaxes'), formatPrice(property.annualTaxes, lang)),
    ];

    return ListView(
      children: [
        _Gallery(photos: property.photos, title: s.title),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(formatPrice(s.listPrice, lang, rent: s.isRent), style: text.headlineSmall),
              Text(s.title, style: text.titleMedium),
              Text(s.addressLine),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                children: [
                  Chip(label: Text(s.isRent ? l10n.t('forRent') : l10n.t('forSale'))),
                  Chip(label: Text(l10n.t('status_${s.status}'))),
                  if (s.propertyType != null) Chip(label: Text(s.propertyType!)),
                ],
              ),
              if (facts.isNotEmpty) ...[
                const SizedBox(height: 8),
                Table(
                  children: [
                    for (final (k, v) in facts)
                      TableRow(
                        children: [
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 4),
                            child: Text(k, style: text.bodySmall),
                          ),
                          Padding(padding: const EdgeInsets.symmetric(vertical: 4), child: Text(v)),
                        ],
                      ),
                  ],
                ),
              ],
              if (property.description != null) ...[
                const SizedBox(height: 12),
                Text(l10n.t('description'), style: text.titleMedium),
                Text(property.description!),
              ],
              if (property.amenities.isNotEmpty) ...[
                const SizedBox(height: 12),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [for (final a in property.amenities) Chip(label: Text(_humanize(a)))],
                ),
              ],
              const SizedBox(height: 16),
              if (user != null && user.can('appointments.request'))
                FilledButton.icon(
                  key: const Key('request-showing'),
                  icon: const Icon(Icons.event),
                  label: Text(l10n.t('requestShowing')),
                  onPressed: () async {
                    final ok = await RequestShowingSheet.show(context, s.slug);
                    if (ok == true && context.mounted) showMessage(context, l10n.t('showingRequested'));
                  },
                ),
              if (property.listingAgent != null) ...[
                const SizedBox(height: 12),
                _AgentCard(agent: property.listingAgent!, propertySlug: s.slug, propertyTitle: s.title),
              ],
              if (property.brokerageName != null) ...[
                const SizedBox(height: 8),
                Text(
                  '${l10n.t('brokerage')}: ${property.brokerageName}${property.brokerageLicense != null ? ' · #${property.brokerageLicense}' : ''}',
                  style: text.bodySmall,
                ),
              ],
              if (s.compliance != null) ...[const SizedBox(height: 12), _ComplianceBox(compliance: s.compliance!)],
            ],
          ),
        ),
      ],
    );
  }

  static String _humanize(String code) =>
      code.isEmpty ? code : (code[0].toUpperCase() + code.substring(1)).replaceAll('_', ' ');
}

class _Gallery extends StatefulWidget {
  const _Gallery({required this.photos, required this.title});

  final List<String> photos;
  final String title;

  @override
  State<_Gallery> createState() => _GalleryState();
}

class _GalleryState extends State<_Gallery> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    if (widget.photos.isEmpty) {
      return AspectRatio(
        aspectRatio: 16 / 9,
        child: ColoredBox(
          color: Theme.of(context).colorScheme.surfaceContainerHighest,
          child: Center(child: Text(l10n.t('noPhotos'))),
        ),
      );
    }
    return AspectRatio(
      aspectRatio: 16 / 9,
      child: Stack(
        children: [
          PageView.builder(
            itemCount: widget.photos.length,
            onPageChanged: (i) => setState(() => _index = i),
            itemBuilder: (_, i) => Image.network(
              widget.photos[i],
              fit: BoxFit.cover,
              semanticLabel: l10n.t('photoOf', {'n': '${i + 1}', 'total': '${widget.photos.length}'}),
              errorBuilder: (_, _, _) => const Center(child: Icon(Icons.broken_image_outlined)),
            ),
          ),
          Positioned(
            right: 8,
            bottom: 8,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(8)),
              child: Text('${_index + 1}/${widget.photos.length}', style: const TextStyle(color: Colors.white)),
            ),
          ),
        ],
      ),
    );
  }
}

class _AgentCard extends ConsumerWidget {
  const _AgentCard({required this.agent, required this.propertySlug, required this.propertyTitle});

  final ListingAgent agent;
  final String propertySlug;
  final String propertyTitle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final user = ref.watch(authControllerProvider).value;
    return Card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          ListTile(
            leading: const Icon(Icons.badge_outlined),
            title: Text(agent.fullName),
            subtitle: Text(l10n.t('listingAgent')),
          ),
          if (agent.phone != null)
            ListTile(
              leading: const Icon(Icons.phone_outlined),
              title: Text(agent.phone!),
              onTap: () => openExternal(context, telUri(agent.phone!)),
            ),
          if (agent.email != null)
            ListTile(
              leading: const Icon(Icons.mail_outline),
              title: Text(agent.email!),
              onTap: () => openExternal(context, mailUri(agent.email!)),
            ),
          if (user != null && user.can('messages.use'))
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
              child: OutlinedButton.icon(
                key: const Key('message-agent'),
                icon: const Icon(Icons.chat_bubble_outline),
                label: Text(l10n.t('messageAgent')),
                onPressed: () => context.push(
                  Routes.newConversation(
                    agentSlug: agent.slug,
                    agentName: agent.fullName,
                    propertySlug: propertySlug,
                    propertyTitle: propertyTitle,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _ComplianceBox extends ConsumerWidget {
  const _ComplianceBox({required this.compliance});

  final Compliance compliance;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final style = Theme.of(context).textTheme.bodySmall;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (compliance.attribution != null)
            Text(compliance.attribution!, style: style?.copyWith(fontWeight: FontWeight.w600)),
          Text(l10n.t('mlsSource', {'mls': compliance.sourceMls, 'id': compliance.listingId ?? '—'}), style: style),
          if (compliance.lastUpdatedAt != null)
            Text(l10n.t('lastUpdated', {'date': formatDateTime(compliance.lastUpdatedAt, lang)}), style: style),
          if (compliance.disclaimer != null) Text(compliance.disclaimer!, style: style),
        ],
      ),
    );
  }
}
