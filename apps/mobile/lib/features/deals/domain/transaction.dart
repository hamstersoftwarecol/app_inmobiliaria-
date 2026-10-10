import '../../../core/network/json.dart';

class Milestone {
  const Milestone({required this.id, required this.type, required this.title, this.dueDate, this.completedAt});

  factory Milestone.fromJson(Map<String, dynamic> j) => Milestone(
    id: asInt(j['id']) ?? 0,
    type: asString(j['type']) ?? 'custom',
    title: asString(j['title']) ?? '',
    dueDate: asString(j['due_date']),
    completedAt: asDate(j['completed_at']),
  );

  final int id;
  final String type;
  final String title;

  /// Calendar date (YYYY-MM-DD) in the agency's zone.
  final String? dueDate;
  final DateTime? completedAt;
}

class DealTransaction {
  const DealTransaction({
    required this.id,
    required this.status,
    required this.salePrice,
    this.propertyTitle,
    this.address,
    this.closingDate,
    this.listingAgent,
    this.buyerAgent,
    this.participants = const [],
    this.milestones = const [],
    this.canManage = false,
  });

  factory DealTransaction.fromJson(Map<String, dynamic> j) {
    final p = asMapOrNull(j['property']);
    return DealTransaction(
      id: asInt(j['id']) ?? 0,
      status: asString(j['status']) ?? 'open',
      salePrice: asDouble(j['sale_price']) ?? 0,
      propertyTitle: asString(p?['title']),
      address: [asString(p?['street_address']), asString(p?['city'])].whereType<String>().join(', '),
      closingDate: asString(j['closing_date']),
      listingAgent: asString(asMapOrNull(j['listing_agent'])?['full_name']),
      buyerAgent: asString(asMapOrNull(j['buyer_agent'])?['full_name']),
      participants: asList(
        j['participants'],
        (x) => (role: asString(x['role']) ?? 'other', name: asString(x['name']) ?? ''),
      ),
      milestones: asList(j['milestones'], Milestone.fromJson),
      canManage: asMap(j['can'])['manage'] == true,
    );
  }

  final int id;
  final String status;
  final double salePrice;
  final String? propertyTitle;
  final String? address;
  final String? closingDate;
  final String? listingAgent;
  final String? buyerAgent;
  final List<({String role, String name})> participants;
  final List<Milestone> milestones;
  final bool canManage;

  String get title => (address ?? '').isNotEmpty ? address! : (propertyTitle ?? '#$id');

  Milestone? get nextMilestone => milestones.where((m) => m.completedAt == null && m.dueDate != null).firstOrNull;
}

class DealDocument {
  const DealDocument({
    required this.id,
    required this.title,
    required this.category,
    required this.visibility,
    this.latestVersionId,
    this.fileName,
    this.scanStatus,
  });

  factory DealDocument.fromJson(Map<String, dynamic> j) {
    final versions = j['versions'];
    final latest = versions is List && versions.isNotEmpty ? asMap(versions.first) : null;
    return DealDocument(
      id: asInt(j['id']) ?? 0,
      title: asString(j['title']) ?? '',
      category: asString(j['category']) ?? 'other',
      visibility: asString(j['visibility']) ?? 'internal',
      latestVersionId: asInt(latest?['id']),
      fileName: asString(latest?['original_name']),
      scanStatus: asString(latest?['scan_status']),
    );
  }

  final int id;
  final String title;
  final String category;
  final String visibility;
  final int? latestVersionId;
  final String? fileName;
  final String? scanStatus;
}
