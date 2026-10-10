import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/json.dart';

/// A weekly window when the agent accepts showings (ISO weekday 1 = Monday, times "HH:mm"
/// in the agent's zone).
class AvailabilityWindow {
  const AvailabilityWindow({required this.weekday, required this.startTime, required this.endTime});

  factory AvailabilityWindow.fromJson(Map<String, dynamic> j) => AvailabilityWindow(
    weekday: asInt(j['weekday']) ?? 1,
    startTime: asString(j['start_time']) ?? '09:00',
    endTime: asString(j['end_time']) ?? '17:00',
  );

  final int weekday;
  final String startTime;
  final String endTime;

  Map<String, dynamic> toJson() => {'weekday': weekday, 'start_time': startTime, 'end_time': endTime};

  AvailabilityWindow copyWith({String? startTime, String? endTime}) =>
      AvailabilityWindow(weekday: weekday, startTime: startTime ?? this.startTime, endTime: endTime ?? this.endTime);
}

class TimeOff {
  const TimeOff({required this.id, required this.startsAt, required this.endsAt, this.reason});

  factory TimeOff.fromJson(Map<String, dynamic> j) => TimeOff(
    id: asInt(j['id']) ?? 0,
    startsAt: asDate(j['starts_at']) ?? DateTime.now(),
    endsAt: asDate(j['ends_at']) ?? DateTime.now(),
    reason: asString(j['reason']),
  );

  final int id;
  final DateTime startsAt;
  final DateTime endsAt;
  final String? reason;
}

class Availability {
  const Availability({required this.timezone, required this.windows, required this.timeOff});

  factory Availability.fromJson(Map<String, dynamic> j) => Availability(
    timezone: asString(j['timezone']) ?? 'America/New_York',
    windows: asList(j['windows'], AvailabilityWindow.fromJson),
    timeOff: asList(j['time_off'], TimeOff.fromJson),
  );

  final String timezone;
  final List<AvailabilityWindow> windows;
  final List<TimeOff> timeOff;
}

/// Windows overlapping another one on the same day (the API rejects them too).
Set<int> overlappingWindows(List<AvailabilityWindow> windows) => {
  for (final (i, w) in windows.indexed)
    for (final (j, o) in windows.indexed)
      if (i != j &&
          w.weekday == o.weekday &&
          w.startTime.compareTo(o.endTime) < 0 &&
          o.startTime.compareTo(w.endTime) < 0)
        i,
};

class AvailabilityRepository {
  AvailabilityRepository(this._dio);

  final Dio _dio;

  Future<Availability> get() => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/me/availability');
    return Availability.fromJson(asMap(res.data!['data']));
  });

  Future<Availability> save(List<AvailabilityWindow> windows) => guardApi(() async {
    final res = await _dio.put<Map<String, dynamic>>(
      '/me/availability',
      data: {'windows': windows.map((w) => w.toJson()).toList()},
    );
    return Availability.fromJson(asMap(res.data!['data']));
  });

  Future<void> addTimeOff(DateTime start, DateTime end, {String? reason}) => guardApi(
    () => _dio.post<void>(
      '/me/time-off',
      data: {'starts_at': start.toUtc().toIso8601String(), 'ends_at': end.toUtc().toIso8601String(), 'reason': ?reason},
    ),
  );

  Future<void> removeTimeOff(int id) => guardApi(() => _dio.delete<void>('/me/time-off/$id'));
}

final availabilityRepositoryProvider = Provider<AvailabilityRepository>(
  (ref) => AvailabilityRepository(ref.watch(dioProvider)),
);

final availabilityProvider = FutureProvider.autoDispose<Availability>(
  (ref) => ref.watch(availabilityRepositoryProvider).get(),
);
