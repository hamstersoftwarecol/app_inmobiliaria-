import '../../../core/l10n/app_localizations.dart';
import '../../../core/network/json.dart';

class AppNotification {
  const AppNotification({required this.id, this.type, this.data = const {}, this.readAt, this.createdAt});

  factory AppNotification.fromJson(Map<String, dynamic> j) => AppNotification(
    id: asString(j['id']) ?? '',
    type: asString(j['type']),
    data: asMap(j['data']),
    readAt: asDate(j['read_at']),
    createdAt: asDate(j['created_at']),
  );

  final String id;
  final String? type;
  final Map<String, dynamic> data;
  final DateTime? readAt;
  final DateTime? createdAt;

  bool get unread => readAt == null;

  /// One-line translated summary (same vocabulary as the web notification center).
  String text(AppLocalizations l10n) {
    String d(String k) => asString(data[k]) ?? '';
    final t = type ?? '';
    return switch (t) {
      'lead.assigned' => l10n.t('ntf_lead_assigned', {'name': d('name')}),
      'lead.unassigned' => l10n.t('ntf_lead_unassigned', {'name': d('name')}),
      'crm.task_due' => l10n.t('ntf_task_due', {'title': d('title')}),
      'saved_search.matches' => l10n.t('ntf_saved_search', {'total': d('total'), 'name': d('name')}),
      'showing.reminder' => l10n.t('ntf_showing_reminder', {'title': d('property_title')}),
      'license.status_changed' => l10n.t('ntf_licence', {'status': d('status')}),
      'message.new' => l10n.t('ntf_message', {'from': d('from')}),
      _ when t.startsWith('offer.') || t.startsWith('transaction.') => d('summary'),
      _ when t.startsWith('showing.') => l10n.t('ntf_showing', {'title': d('property_title')}),
      _ => l10n.t('ntf_generic'),
    };
  }

  /// In-app destination, if the app has a screen for it.
  String? get route {
    final t = type ?? '';
    if (t == 'message.new' && data['conversation_id'] != null) return '/conversation/${data['conversation_id']}';
    if (t.startsWith('lead.') && data['lead_id'] != null) return '/home/leads/${data['lead_id']}';
    if (t == 'crm.task_due') return '/home/tasks';
    if (t.startsWith('showing.')) return '/home/showings';
    if (t == 'saved_search.matches') return '/home/saved-searches';
    if (t.startsWith('transaction.') && data['transaction_id'] != null) {
      return '/home/transactions/${data['transaction_id']}';
    }
    return null;
  }
}
