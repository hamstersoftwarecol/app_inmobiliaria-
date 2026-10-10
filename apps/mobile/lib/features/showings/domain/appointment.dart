import '../../../core/network/json.dart';

class Appointment {
  const Appointment({
    required this.id,
    required this.status,
    required this.startsAt,
    required this.endsAt,
    this.propertySlug,
    this.propertyTitle,
    this.propertyAddress,
    this.agentName,
    this.agentPhone,
    this.contactName,
    this.contactPhone,
    this.contactEmail,
    this.notes,
    this.statusReason,
    this.canConfirm = false,
    this.canDecline = false,
    this.canCancel = false,
    this.canComplete = false,
  });

  factory Appointment.fromJson(Map<String, dynamic> j) {
    final p = asMapOrNull(j['property']);
    final agent = asMapOrNull(j['agent']);
    final contact = asMapOrNull(j['contact']);
    final can = asMap(j['can']);
    return Appointment(
      id: asInt(j['id']) ?? 0,
      status: asString(j['status']) ?? 'requested',
      startsAt: asDate(j['starts_at']) ?? DateTime.now(),
      endsAt: asDate(j['ends_at']) ?? DateTime.now(),
      propertySlug: asString(p?['slug']),
      propertyTitle: asString(p?['title']),
      propertyAddress: [asString(p?['street_address']), asString(p?['city'])].whereType<String>().join(', '),
      agentName: asString(agent?['full_name']),
      agentPhone: asString(agent?['phone']),
      contactName: asString(contact?['name']),
      contactPhone: asString(contact?['phone']),
      contactEmail: asString(contact?['email']),
      notes: asString(j['notes']),
      statusReason: asString(j['status_reason']),
      canConfirm: can['confirm'] == true,
      canDecline: can['decline'] == true,
      canCancel: can['cancel'] == true,
      canComplete: can['complete'] == true,
    );
  }

  final int id;
  final String status;
  final DateTime startsAt;
  final DateTime endsAt;
  final String? propertySlug;
  final String? propertyTitle;
  final String? propertyAddress;
  final String? agentName;
  final String? agentPhone;
  final String? contactName;
  final String? contactPhone;
  final String? contactEmail;
  final String? notes;
  final String? statusReason;
  final bool canConfirm;
  final bool canDecline;
  final bool canCancel;
  final bool canComplete;
}

class SlotDay {
  const SlotDay({required this.date, required this.slots});

  final String date;
  final List<DateTime> slots;
}

class ShowingSlots {
  const ShowingSlots({required this.bookable, this.timezone, this.days = const []});

  factory ShowingSlots.fromJson(Map<String, dynamic> j) => ShowingSlots(
    bookable: j['bookable'] == true,
    timezone: asString(j['timezone']),
    days: asList(
      j['days'],
      (d) => SlotDay(
        date: asString(d['date']) ?? '',
        slots: asList(d['slots'], (s) => asDate(s['starts_at'])).whereType<DateTime>().toList(),
      ),
    ),
  );

  final bool bookable;
  final String? timezone;
  final List<SlotDay> days;

  bool get hasSlots => days.any((d) => d.slots.isNotEmpty);
}
