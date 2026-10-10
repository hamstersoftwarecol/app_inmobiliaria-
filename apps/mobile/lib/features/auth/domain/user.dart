import 'package:freezed_annotation/freezed_annotation.dart';

part 'user.freezed.dart';
part 'user.g.dart';

@freezed
abstract class UserRole with _$UserRole {
  const factory UserRole({required String name, @JsonKey(name: 'display_name') required String displayName}) =
      _UserRole;

  factory UserRole.fromJson(Map<String, dynamic> json) => _$UserRoleFromJson(json);
}

/// Mirrors `UserResource` from the API (docs/api/openapi.yaml#/components/schemas/User).
@freezed
abstract class User with _$User {
  const User._();

  const factory User({
    required int id,
    @JsonKey(name: 'first_name') required String firstName,
    @JsonKey(name: 'last_name') required String lastName,
    @JsonKey(name: 'full_name') required String fullName,
    required String email,
    String? phone,
    @Default('en') String locale,
    @Default('America/New_York') String timezone,
    @Default('active') String status,
    @JsonKey(name: 'email_verified') @Default(false) bool emailVerified,
    @JsonKey(name: 'mfa_enabled') @Default(false) bool mfaEnabled,
    @JsonKey(name: 'is_staff') @Default(false) bool isStaff,
    @Default(<UserRole>[]) List<UserRole> roles,
    @Default(<String>[]) List<String> permissions,
  }) = _User;

  factory User.fromJson(Map<String, dynamic> json) => _$UserFromJson(json);

  bool hasRole(String role) => roles.any((r) => r.name == role);

  bool can(String permission) => permissions.contains(permission);

  /// Agent-mode screens (CRM, showings…) are for staff and agents.
  bool get isAgentMode => isStaff || hasRole('agent');
}
