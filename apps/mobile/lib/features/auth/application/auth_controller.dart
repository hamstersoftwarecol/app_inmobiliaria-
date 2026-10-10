import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../data/auth_repository.dart';
import '../domain/user.dart';

/// Session state: `null` = signed out. Restores the session on start-up by
/// validating the stored token against `GET /auth/me`.
class AuthController extends AsyncNotifier<User?> {
  @override
  Future<User?> build() async {
    final token = await ref.read(tokenStorageProvider).read();
    if (token == null || token.isEmpty) return null;

    try {
      return await ref.read(authRepositoryProvider).me();
    } on ApiException catch (e) {
      if (e.isUnauthenticated) {
        await ref.read(tokenStorageProvider).clear();
        return null;
      }
      rethrow;
    }
  }

  Future<void> login({required String email, required String password, String? mfaCode}) async {
    final result = await ref.read(authRepositoryProvider).login(email: email, password: password, mfaCode: mfaCode);
    await _persist(result);
  }

  Future<void> register(RegisterRequest request) async {
    await _persist(await ref.read(authRepositoryProvider).register(request));
  }

  Future<void> logout() async {
    try {
      await ref.read(authRepositoryProvider).logout();
    } on ApiException {
      // Token may already be revoked server-side; local sign-out proceeds.
    } finally {
      await ref.read(tokenStorageProvider).clear();
      state = const AsyncData(null);
    }
  }

  /// Invoked when the API rejects the token (expired/revoked elsewhere).
  void sessionExpired() => state = const AsyncData(null);

  Future<void> _persist(AuthResult result) async {
    await ref.read(tokenStorageProvider).write(result.token);
    state = AsyncData(result.user);
  }
}

final authControllerProvider = AsyncNotifierProvider<AuthController, User?>(AuthController.new);
