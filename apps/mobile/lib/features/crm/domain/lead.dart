import '../../../core/network/json.dart';

class LeadActivity {
  const LeadActivity({required this.id, required this.type, this.body, this.userName, this.occurredAt});

  factory LeadActivity.fromJson(Map<String, dynamic> j) => LeadActivity(
    id: asInt(j['id']) ?? 0,
    type: asString(j['type']) ?? 'note',
    body: asString(j['body']),
    userName: asString(asMap(j['user'])['full_name']),
    occurredAt: asDate(j['occurred_at']),
  );

  final int id;
  final String type;
  final String? body;
  final String? userName;
  final DateTime? occurredAt;
}

class CrmTask {
  const CrmTask({
    required this.id,
    required this.title,
    required this.dueAt,
    this.notes,
    this.completedAt,
    this.isOverdue = false,
    this.leadId,
    this.leadName,
  });

  factory CrmTask.fromJson(Map<String, dynamic> j) {
    final lead = asMapOrNull(j['lead']);
    return CrmTask(
      id: asInt(j['id']) ?? 0,
      title: asString(j['title']) ?? '',
      notes: asString(j['notes']),
      dueAt: asDate(j['due_at']) ?? DateTime.now(),
      completedAt: asDate(j['completed_at']),
      isOverdue: j['is_overdue'] == true,
      leadId: asInt(lead?['id']),
      leadName: asString(lead?['full_name']),
    );
  }

  final int id;
  final String title;
  final String? notes;
  final DateTime dueAt;
  final DateTime? completedAt;
  final bool isOverdue;
  final int? leadId;
  final String? leadName;
}

class Lead {
  const Lead({
    required this.id,
    required this.status,
    required this.source,
    required this.fullName,
    this.intent,
    this.email,
    this.phone,
    this.message,
    this.budgetMin,
    this.budgetMax,
    this.propertyTitle,
    this.propertySlug,
    this.agentName,
    this.contactConsent = false,
    this.lastActivityAt,
    this.createdAt,
    this.activities = const [],
    this.tasks = const [],
    this.canUpdate = false,
  });

  factory Lead.fromJson(Map<String, dynamic> j, {Map<String, dynamic>? meta}) {
    final property = asMapOrNull(j['property']);
    return Lead(
      id: asInt(j['id']) ?? 0,
      status: asString(j['status']) ?? 'new',
      source: asString(j['source']) ?? 'other',
      fullName: asString(j['full_name']) ?? '',
      intent: asString(j['intent']),
      email: asString(j['email']),
      phone: asString(j['phone']),
      message: asString(j['message']),
      budgetMin: asDouble(j['budget_min']),
      budgetMax: asDouble(j['budget_max']),
      propertyTitle: asString(property?['title']),
      propertySlug: asString(property?['slug']),
      agentName: asString(asMapOrNull(j['assigned_agent'])?['full_name']),
      contactConsent: j['contact_consent'] == true,
      lastActivityAt: asDate(j['last_activity_at']),
      createdAt: asDate(j['created_at']),
      activities: asList(j['activities'], LeadActivity.fromJson),
      tasks: asList(j['tasks'], CrmTask.fromJson),
      canUpdate: asMap(asMap(meta)['can'])['update'] == true,
    );
  }

  static const statuses = ['new', 'contacted', 'qualified', 'showing', 'negotiating', 'won', 'lost'];
  static const manualActivities = ['note', 'call', 'email', 'meeting'];

  final int id;
  final String status;
  final String source;
  final String fullName;
  final String? intent;
  final String? email;
  final String? phone;
  final String? message;
  final double? budgetMin;
  final double? budgetMax;
  final String? propertyTitle;
  final String? propertySlug;
  final String? agentName;
  final bool contactConsent;
  final DateTime? lastActivityAt;
  final DateTime? createdAt;
  final List<LeadActivity> activities;
  final List<CrmTask> tasks;
  final bool canUpdate;
}
