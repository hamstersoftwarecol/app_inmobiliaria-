import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/json.dart';
import '../../auth/domain/user.dart';

/// E-mail categories the user can opt out of (security e-mails are always sent).
const notificationEvents = [
  'messages',
  'showing_updates',
  'showing_reminders',
  'offer_updates',
  'transaction_updates',
  'saved_search_alerts',
  'lead_updates',
  'task_reminders',
  'licence_updates',
  'marketing',
];

class AuthSession {
  const AuthSession({
    required this.id,
    required this.type,
    required this.name,
    this.lastUsedAt,
    this.isCurrent = false,
  });

  factory AuthSession.fromJson(Map<String, dynamic> j) => AuthSession(
    id: asString(j['id']) ?? '',
    type: asString(j['type']) ?? 'token',
    name: asString(j['name']) ?? '',
    lastUsedAt: asDate(j['last_used_at']),
    isCurrent: j['is_current'] == true,
  );

  final String id;
  final String type;
  final String name;
  final DateTime? lastUsedAt;
  final bool isCurrent;
}

class MfaSetup {
  const MfaSetup({required this.secret, required this.otpauthUrl});

  final String secret;
  final String otpauthUrl;
}

class AccountRepository {
  AccountRepository(this._dio);

  final Dio _dio;

  Future<User> updateProfile(Map<String, dynamic> changes) => guardApi(() async {
    final res = await _dio.patch<Map<String, dynamic>>('/auth/profile', data: changes);
    return User.fromJson(asMap(res.data!['data']));
  });

  /// Signs out every other device; this one stays signed in.
  Future<void> changePassword({required String current, required String password}) => guardApi(
    () => _dio.put<void>(
      '/auth/password',
      data: {
        'current_password': current,
        'password': password,
        'password_confirmation': password,
        'logout_other_sessions': true,
      },
    ),
  );

  Future<void> resendVerification() => guardApi(() => _dio.post<void>('/auth/email/verification-notification'));

  Future<MfaSetup> mfaSetup(String password) => guardApi(() async {
    final res = await _dio.post<Map<String, dynamic>>('/auth/mfa/setup', data: {'password': password});
    return MfaSetup(secret: asString(res.data!['secret']) ?? '', otpauthUrl: asString(res.data!['otpauth_url']) ?? '');
  });

  /// Returns the one-time recovery codes.
  Future<List<String>> mfaConfirm(String code) => guardApi(() async {
    final res = await _dio.post<Map<String, dynamic>>('/auth/mfa/confirm', data: {'code': code});
    return asStrings(res.data!['recovery_codes']);
  });

  Future<List<String>> regenerateRecoveryCodes({required String password, required String code}) => guardApi(() async {
    final res = await _dio.post<Map<String, dynamic>>(
      '/auth/mfa/recovery-codes',
      data: {'password': password, 'code': code},
    );
    return asStrings(res.data!['recovery_codes']);
  });

  Future<void> mfaDisable({required String password, required String code}) =>
      guardApi(() => _dio.post<void>('/auth/mfa/disable', data: {'password': password, 'code': code}));

  Future<List<AuthSession>> sessions() => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/auth/sessions');
    return asList(res.data!['data'], AuthSession.fromJson);
  });

  Future<void> revokeSession(String id) =>
      guardApi(() => _dio.delete<void>('/auth/sessions/${Uri.encodeComponent(id)}'));

  Future<void> revokeOtherSessions() => guardApi(() => _dio.delete<void>('/auth/sessions'));

  Future<Map<String, bool>> notificationPreferences() => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/me/notification-preferences');
    return {for (final e in asMap(res.data!['data']).entries) e.key: asMap(e.value)['mail'] == true};
  });

  Future<Map<String, bool>> setNotificationPreference(String event, bool mail) => guardApi(() async {
    final res = await _dio.put<Map<String, dynamic>>(
      '/me/notification-preferences',
      data: {
        'mail': {event: mail},
      },
    );
    return {for (final e in asMap(res.data!['data']).entries) e.key: asMap(e.value)['mail'] == true};
  });

  /// Closes the account; every session and token is revoked by the API.
  Future<void> deactivate({required String password, String? code}) =>
      guardApi(() => _dio.post<void>('/auth/deactivate', data: {'password': password, 'code': ?code}));
}

final accountRepositoryProvider = Provider<AccountRepository>((ref) => AccountRepository(ref.watch(dioProvider)));

final sessionsProvider = FutureProvider.autoDispose<List<AuthSession>>(
  (ref) => ref.watch(accountRepositoryProvider).sessions(),
);

final notificationPreferencesProvider = FutureProvider.autoDispose<Map<String, bool>>(
  (ref) => ref.watch(accountRepositoryProvider).notificationPreferences(),
);
