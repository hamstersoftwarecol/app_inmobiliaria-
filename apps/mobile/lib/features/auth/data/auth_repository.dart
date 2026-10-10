import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/user.dart';

class AuthResult {
  const AuthResult({required this.user, required this.token});

  final User user;
  final String token;
}

class RegisterRequest {
  const RegisterRequest({
    required this.firstName,
    required this.lastName,
    required this.email,
    required this.password,
    required this.accountType,
    required this.locale,
  });

  final String firstName;
  final String lastName;
  final String email;
  final String password;
  final String accountType;
  final String locale;
}

/// Mobile authentication against Sanctum personal access tokens.
class AuthRepository {
  AuthRepository(this._dio, {String? deviceName}) : _deviceName = deviceName ?? _defaultDeviceName();

  final Dio _dio;
  final String _deviceName;

  static String _defaultDeviceName() => 'Hitlease Mobile (${defaultTargetPlatform.name})';

  Future<AuthResult> login({required String email, required String password, String? mfaCode}) => guardApi(() async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/auth/token',
      data: {
        'email': email.trim(),
        'password': password,
        'device_name': _deviceName,
        if (mfaCode != null && mfaCode.isNotEmpty) 'mfa_code': mfaCode.trim(),
      },
    );
    return _authResult(response.data!);
  });

  Future<AuthResult> register(RegisterRequest request) => guardApi(() async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/auth/register',
      data: {
        'first_name': request.firstName.trim(),
        'last_name': request.lastName.trim(),
        'email': request.email.trim(),
        'password': request.password,
        'password_confirmation': request.password,
        'account_type': request.accountType,
        'locale': request.locale,
        'accept_terms': true,
        'device_name': _deviceName,
      },
    );
    return _authResult(response.data!);
  });

  Future<User> me() => guardApi(() async {
    final response = await _dio.get<Map<String, dynamic>>('/auth/me');
    return User.fromJson(response.data!['data'] as Map<String, dynamic>);
  });

  Future<void> logout() => guardApi(() async {
    await _dio.post<void>('/auth/logout');
  });

  Future<void> forgotPassword(String email) => guardApi(() async {
    await _dio.post<void>('/auth/forgot-password', data: {'email': email.trim()});
  });

  AuthResult _authResult(Map<String, dynamic> body) => AuthResult(
    user: User.fromJson(body['data'] as Map<String, dynamic>),
    token: (body['token'] as Map<String, dynamic>)['access_token'] as String,
  );
}

final authRepositoryProvider = Provider<AuthRepository>((ref) => AuthRepository(ref.watch(dioProvider)));
