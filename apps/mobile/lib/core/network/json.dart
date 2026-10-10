/// Tolerant readers for API JSON (numbers may arrive as int, double or numeric strings).
int? asInt(Object? v) => switch (v) {
  int() => v,
  num() => v.toInt(),
  String() => int.tryParse(v),
  _ => null,
};

double? asDouble(Object? v) => switch (v) {
  num() => v.toDouble(),
  String() => double.tryParse(v),
  _ => null,
};

String? asString(Object? v) => v?.toString();

DateTime? asDate(Object? v) => v is String && v.isNotEmpty ? DateTime.tryParse(v) : null;

Map<String, dynamic> asMap(Object? v) => v is Map<String, dynamic> ? v : const {};

Map<String, dynamic>? asMapOrNull(Object? v) => v is Map<String, dynamic> ? v : null;

List<T> asList<T>(Object? v, T Function(Map<String, dynamic>) parse) => v is List
    ? [
        for (final e in v)
          if (e is Map<String, dynamic>) parse(e),
      ]
    : <T>[];

List<String> asStrings(Object? v) => v is List
    ? [
        for (final e in v)
          if (e != null) e.toString(),
      ]
    : const [];
