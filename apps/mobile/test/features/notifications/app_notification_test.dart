import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hitlease_mobile/core/l10n/app_localizations.dart';
import 'package:hitlease_mobile/features/notifications/domain/app_notification.dart';

void main() {
  AppNotification n(String type, Map<String, dynamic> data) => AppNotification(id: '1', type: type, data: data);

  test('summaries and in-app destinations', () {
    final en = AppLocalizations(const Locale('en'));
    final es = AppLocalizations(const Locale('es'));

    final message = n('message.new', {'conversation_id': 5, 'from': 'Ana'});
    expect(message.text(en), 'New message from Ana');
    expect(message.text(es), 'Nuevo mensaje de Ana');
    expect(message.route, '/conversation/5');

    expect(n('lead.assigned', {'lead_id': 3, 'name': 'Pat'}).route, '/home/leads/3');
    expect(n('transaction.milestone_due', {'transaction_id': 7, 'summary': 'Milestone due'}).text(en), 'Milestone due');
    expect(n('transaction.milestone_due', {'transaction_id': 7}).route, '/home/transactions/7');
    expect(n('showing.confirmed', {'property_title': 'Colonial'}).text(en), 'Showing update: Colonial');
    expect(n('something.else', {}).route, isNull);
  });
}
