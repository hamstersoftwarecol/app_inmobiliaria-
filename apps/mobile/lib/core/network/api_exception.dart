import 'package:dio/dio.dart';

/// Typed error built from the API envelope:
/// `{"error": {"code", "message", "details": {"fields": {...}}, "request_id"}}`.
class ApiException implements Exception {
  const ApiException({
    required this.statusCode,
    required this.code,
    required this.message,
    this.fieldErrors = const {},
    this.requestId,
  });

  final int statusCode;
  final String code;
  final String message;
  final Map<String, List<String>> fieldErrors;
  final String? requestId;

  bool get isUnauthenticated => statusCode == 401;

  String? fieldError(String field) => fieldErrors[field]?.first;

  factory ApiException.fromDio(DioException error) {
    final response = error.response;
    if (response == null) {
      return const ApiException(
        statusCode: 0,
        code: 'network_error',
        message: 'Unable to reach the server. Check your connection and try again.',
      );
    }

    final data = response.data;
    final envelope = data is Map<String, dynamic> ? data['error'] : null;
    if (envelope is! Map<String, dynamic>) {
      return ApiException(
        statusCode: response.statusCode ?? 0,
        code: 'http_error',
        message: 'Request failed with status ${response.statusCode}.',
      );
    }

    final details = envelope['details'];
    final rawFields = details is Map<String, dynamic> ? details['fields'] : null;
    final fields = <String, List<String>>{};
    if (rawFields is Map<String, dynamic>) {
      rawFields.forEach((key, value) {
        if (value is List) fields[key] = value.map((e) => e.toString()).toList();
      });
    }

    return ApiException(
      statusCode: response.statusCode ?? 0,
      code: envelope['code']?.toString() ?? 'http_error',
      message: envelope['message']?.toString() ?? 'Request failed.',
      fieldErrors: fields,
      requestId: envelope['request_id']?.toString(),
    );
  }

  @override
  String toString() => 'ApiException($statusCode, $code): $message';
}
