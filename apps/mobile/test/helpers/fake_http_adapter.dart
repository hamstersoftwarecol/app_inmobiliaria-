import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

typedef FakeHandler = (int, Object?) Function(RequestOptions options);

/// Minimal Dio adapter returning canned JSON, recording requests.
class FakeHttpAdapter implements HttpClientAdapter {
  FakeHttpAdapter(this.handler);

  final FakeHandler handler;
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    final (status, body) = handler(options);
    return ResponseBody.fromString(
      body == null ? '' : jsonEncode(body),
      status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

Map<String, dynamic> userJson({bool mfa = false, List<String> roles = const ['buyer']}) => {
  'id': 7,
  'first_name': 'Ana',
  'last_name': 'Ruiz',
  'full_name': 'Ana Ruiz',
  'email': 'ana@example.com',
  'phone': null,
  'locale': 'en',
  'timezone': 'America/New_York',
  'status': 'active',
  'email_verified': false,
  'mfa_enabled': mfa,
  'is_staff': false,
  'roles': [
    for (final r in roles) {'name': r, 'display_name': r},
  ],
  'permissions': ['favorites.manage'],
  'last_login_at': null,
  'created_at': '2026-10-01T00:00:00+00:00',
};

Map<String, dynamic> errorJson(String code, String message, [Map<String, List<String>>? fields]) => {
  'error': {
    'code': code,
    'message': message,
    'details': fields == null ? null : {'fields': fields},
    'request_id': 'req-1',
  },
};
