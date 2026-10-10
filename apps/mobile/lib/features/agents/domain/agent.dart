import '../../../core/network/json.dart';

/// Values accepted by the API for agent languages and specialties (AgentProfileRequest).
const agentLanguages = ['en', 'es', 'pt', 'fr', 'it', 'pl', 'zh', 'ko', 'hi', 'gu', 'ar', 'ru', 'ht', 'tl'];
const agentSpecialties = [
  'buyers',
  'sellers',
  'rentals',
  'first_time_buyers',
  'luxury',
  'condos',
  'multi_family',
  'new_construction',
  'commercial',
  'land',
  'relocation',
  'investment',
  'seniors',
];
const licenseTypes = ['salesperson', 'broker_salesperson', 'broker', 'referral_agent'];

/// An agent in the public directory (only active, opted-in agents with a verified licence).
class PublicAgent {
  const PublicAgent({
    required this.slug,
    required this.fullName,
    this.photoUrl,
    this.email,
    this.phone,
    this.bio,
    this.bioEs,
    this.languages = const [],
    this.specialties = const [],
    this.agencyName,
    this.agencyLicense,
    this.officeName,
    this.officeAddress,
    this.officePhone,
    this.licenseLabel,
    this.licenseNumber,
    this.licenseState,
  });

  factory PublicAgent.fromJson(Map<String, dynamic> j) {
    final agency = asMapOrNull(j['agency']);
    final office = asMapOrNull(j['office']);
    final license = asMapOrNull(j['license']);
    return PublicAgent(
      slug: asString(j['slug']) ?? '',
      fullName: asString(j['full_name']) ?? '',
      photoUrl: asString(j['photo_url']),
      email: asString(j['email']),
      phone: asString(j['phone']),
      bio: asString(j['bio']),
      bioEs: asString(j['bio_es']),
      languages: asStrings(j['languages']),
      specialties: asStrings(j['specialties']),
      agencyName: asString(agency?['name']),
      agencyLicense: asString(agency?['license_number']),
      officeName: asString(office?['name']),
      officeAddress: office == null
          ? null
          : [
              [asString(office['street_address']), asString(office['unit'])].whereType<String>().join(' '),
              asString(office['city']),
              [asString(office['state']), asString(office['postal_code'])].whereType<String>().join(' '),
            ].where((s) => s != null && s.isNotEmpty).join(', '),
      officePhone: asString(office?['phone']),
      licenseLabel: asString(license?['type_label']),
      licenseNumber: asString(license?['number']),
      licenseState: asString(license?['state']),
    );
  }

  final String slug;
  final String fullName;
  final String? photoUrl;
  final String? email;
  final String? phone;
  final String? bio;
  final String? bioEs;
  final List<String> languages;
  final List<String> specialties;
  final String? agencyName;
  final String? agencyLicense;
  final String? officeName;
  final String? officeAddress;
  final String? officePhone;
  final String? licenseLabel;
  final String? licenseNumber;
  final String? licenseState;

  /// Spanish bio when the app is in Spanish and the agent wrote one.
  String? bioFor(String languageCode) => languageCode == 'es' && (bioEs ?? '').isNotEmpty ? bioEs : bio;

  String get initials => fullName.trim().split(RegExp(r'\s+')).take(2).map((p) => p.isEmpty ? '' : p[0]).join();
}

class AgentLicense {
  const AgentLicense({
    required this.id,
    required this.number,
    required this.state,
    required this.type,
    required this.typeLabel,
    required this.status,
    this.issuedOn,
    this.expiresOn,
    this.statusReason,
  });

  factory AgentLicense.fromJson(Map<String, dynamic> j) => AgentLicense(
    id: asInt(j['id']) ?? 0,
    number: asString(j['license_number']) ?? '',
    state: asString(j['license_state']) ?? 'NJ',
    type: asString(j['license_type']) ?? 'salesperson',
    typeLabel: asString(j['license_type_label']) ?? '',
    status: asString(j['status']) ?? 'pending_verification',
    issuedOn: asString(j['issued_on']),
    expiresOn: asString(j['expires_on']),
    statusReason: asString(j['status_reason']),
  );

  final int id;
  final String number;
  final String state;
  final String type;
  final String typeLabel;
  final String status;
  final String? issuedOn;
  final String? expiresOn;
  final String? statusReason;
}

/// The signed-in agent's own profile (`GET /me/agent-profile`).
class MyAgentProfile {
  const MyAgentProfile({
    required this.slug,
    required this.status,
    required this.isPublic,
    required this.licenseVerified,
    this.agencyName,
    this.officeName,
    this.publicEmail,
    this.publicPhone,
    this.bio,
    this.bioEs,
    this.languages = const [],
    this.specialties = const [],
    this.licenses = const [],
  });

  factory MyAgentProfile.fromJson(Map<String, dynamic> j) => MyAgentProfile(
    slug: asString(j['slug']) ?? '',
    status: asString(j['status']) ?? 'active',
    isPublic: j['is_public'] == true,
    licenseVerified: j['license_verified'] == true,
    agencyName: asString(asMapOrNull(j['agency'])?['name']),
    officeName: asString(asMapOrNull(j['office'])?['name']),
    publicEmail: asString(j['public_email']),
    publicPhone: asString(j['public_phone']),
    bio: asString(j['bio']),
    bioEs: asString(j['bio_es']),
    languages: asStrings(j['languages']),
    specialties: asStrings(j['specialties']),
    licenses: asList(j['licenses'], AgentLicense.fromJson),
  );

  final String slug;
  final String status;
  final bool isPublic;
  final bool licenseVerified;
  final String? agencyName;
  final String? officeName;
  final String? publicEmail;
  final String? publicPhone;
  final String? bio;
  final String? bioEs;
  final List<String> languages;
  final List<String> specialties;
  final List<AgentLicense> licenses;
}
