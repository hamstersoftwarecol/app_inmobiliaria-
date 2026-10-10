import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/json.dart';
import '../../../core/network/paginated.dart';
import '../domain/agent.dart';

class AgentDirectoryFilters {
  const AgentDirectoryFilters({this.search, this.language, this.specialty, this.city});

  final String? search;
  final String? language;
  final String? specialty;
  final String? city;

  Map<String, dynamic> toQuery() => {'search': ?search, 'language': ?language, 'specialty': ?specialty, 'city': ?city};

  @override
  bool operator ==(Object other) =>
      other is AgentDirectoryFilters &&
      other.search == search &&
      other.language == language &&
      other.specialty == specialty &&
      other.city == city;

  @override
  int get hashCode => Object.hash(search, language, specialty, city);
}

class AgentRepository {
  AgentRepository(this._dio);

  final Dio _dio;

  Future<Paginated<PublicAgent>> directory(AgentDirectoryFilters filters, {int page = 1}) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>(
      '/agents',
      queryParameters: {...filters.toQuery(), 'page': page, 'per_page': 30},
    );
    return Paginated.fromJson(res.data!, PublicAgent.fromJson);
  });

  Future<PublicAgent> agent(String slug) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/agents/${Uri.encodeComponent(slug)}');
    return PublicAgent.fromJson(asMap(res.data!['data']));
  });

  Future<MyAgentProfile> myProfile() => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/me/agent-profile');
    return MyAgentProfile.fromJson(asMap(res.data!['data']));
  });

  /// Only the self-editable fields (contact, bios, languages, specialties, visibility).
  Future<MyAgentProfile> updateMyProfile(Map<String, dynamic> changes) => guardApi(() async {
    final res = await _dio.patch<Map<String, dynamic>>('/me/agent-profile', data: changes);
    return MyAgentProfile.fromJson(asMap(res.data!['data']));
  });

  Future<void> submitLicense({
    required String number,
    required String type,
    String state = 'NJ',
    String? issuedOn,
    String? expiresOn,
  }) => guardApi(
    () => _dio.post<void>(
      '/me/agent-profile/licenses',
      data: {
        'license_number': number,
        'license_type': type,
        'license_state': state,
        'issued_on': issuedOn,
        'expires_on': expiresOn,
      },
    ),
  );
}

final agentRepositoryProvider = Provider<AgentRepository>((ref) => AgentRepository(ref.watch(dioProvider)));

final agentDirectoryProvider = FutureProvider.autoDispose.family<Paginated<PublicAgent>, AgentDirectoryFilters>(
  (ref, filters) => ref.watch(agentRepositoryProvider).directory(filters),
);

final publicAgentProvider = FutureProvider.autoDispose.family<PublicAgent, String>(
  (ref, slug) => ref.watch(agentRepositoryProvider).agent(slug),
);

final myAgentProfileProvider = FutureProvider.autoDispose<MyAgentProfile>(
  (ref) => ref.watch(agentRepositoryProvider).myProfile(),
);
