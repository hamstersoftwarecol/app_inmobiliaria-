// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'user.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_UserRole _$UserRoleFromJson(Map<String, dynamic> json) => _UserRole(
  name: json['name'] as String,
  displayName: json['display_name'] as String,
);

Map<String, dynamic> _$UserRoleToJson(_UserRole instance) => <String, dynamic>{
  'name': instance.name,
  'display_name': instance.displayName,
};

_User _$UserFromJson(Map<String, dynamic> json) => _User(
  id: (json['id'] as num).toInt(),
  firstName: json['first_name'] as String,
  lastName: json['last_name'] as String,
  fullName: json['full_name'] as String,
  email: json['email'] as String,
  phone: json['phone'] as String?,
  locale: json['locale'] as String? ?? 'en',
  timezone: json['timezone'] as String? ?? 'America/New_York',
  status: json['status'] as String? ?? 'active',
  emailVerified: json['email_verified'] as bool? ?? false,
  mfaEnabled: json['mfa_enabled'] as bool? ?? false,
  isStaff: json['is_staff'] as bool? ?? false,
  roles:
      (json['roles'] as List<dynamic>?)
          ?.map((e) => UserRole.fromJson(e as Map<String, dynamic>))
          .toList() ??
      const <UserRole>[],
  permissions:
      (json['permissions'] as List<dynamic>?)
          ?.map((e) => e as String)
          .toList() ??
      const <String>[],
);

Map<String, dynamic> _$UserToJson(_User instance) => <String, dynamic>{
  'id': instance.id,
  'first_name': instance.firstName,
  'last_name': instance.lastName,
  'full_name': instance.fullName,
  'email': instance.email,
  'phone': instance.phone,
  'locale': instance.locale,
  'timezone': instance.timezone,
  'status': instance.status,
  'email_verified': instance.emailVerified,
  'mfa_enabled': instance.mfaEnabled,
  'is_staff': instance.isStaff,
  'roles': instance.roles,
  'permissions': instance.permissions,
};
