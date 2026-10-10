import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:hitlease_mobile/core/l10n/app_localizations.dart';
import 'package:hitlease_mobile/core/network/api_client.dart';
import 'package:hitlease_mobile/features/auth/application/auth_controller.dart';
import 'package:hitlease_mobile/features/auth/domain/user.dart';

import 'fake_http_adapter.dart';

/// Auth state fixed to [user] (no network).
class StaticAuthController extends AuthController {
  StaticAuthController(this.user);

  final User? user;

  @override
  Future<User?> build() async => user;
}

User testUser({List<String> roles = const ['buyer'], List<String> permissions = const [], bool staff = false}) =>
    User.fromJson({...userJson(roles: roles), 'permissions': permissions, 'is_staff': staff});

Dio fakeDio(FakeHttpAdapter adapter) =>
    Dio(BaseOptions(baseUrl: 'https://api.example.test/api/v1'))..httpClientAdapter = adapter;

/// Pumps [child] inside a router (so `context.push` works) with a fake API and user.
Future<void> pumpScreen(
  WidgetTester tester,
  Widget child, {
  required FakeHttpAdapter adapter,
  User? user,
  List<Object> overrides = const [],
  Locale locale = const Locale('en'),
}) async {
  final router = GoRouter(
    routes: [
      GoRoute(path: '/', builder: (_, _) => child),
      GoRoute(
        path: '/:rest(.*)',
        builder: (_, state) => Scaffold(body: Text('route:${state.uri}')),
      ),
    ],
  );
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        dioProvider.overrideWithValue(fakeDio(adapter)),
        authControllerProvider.overrideWith(() => StaticAuthController(user)),
        ...overrides.cast(),
      ],
      child: MaterialApp.router(
        routerConfig: router,
        locale: locale,
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Map<String, dynamic> page(List<Map<String, dynamic>> items, {int current = 1, int last = 1}) => {
  'data': items,
  'links': {'first': null, 'last': null, 'prev': null, 'next': null},
  'meta': {'current_page': current, 'last_page': last, 'per_page': 20, 'total': items.length},
};

Map<String, dynamic> propertyJson({
  String slug = 'e2e-12-example-lane-hoboken',
  String title = 'Colonial',
  bool mls = false,
}) => {
  'slug': slug,
  'source': mls ? 'mls' : 'internal',
  'status': 'active',
  'listing_type': 'sale',
  'property_type': {'code': 'single_family', 'name': 'Single family'},
  'title': title,
  'list_price': 650000,
  'currency': 'USD',
  'address': {
    'street_address': '12 Example Lane',
    'unit': null,
    'city': 'Hoboken',
    'county': 'Hudson',
    'state': 'NJ',
    'postal_code': '07030',
    'displayed': true,
  },
  'location': null,
  'bedrooms': 3,
  'bathrooms': 2.5,
  'living_area_sqft': 1800,
  'cover_photo': null,
  'compliance': mls
      ? {
          'source_mls': 'Garden State MLS',
          'listing_id': 'GS123',
          'attribution': 'Listing courtesy of Example Realty',
          'disclaimer': 'Information deemed reliable but not guaranteed.',
          'last_updated_at': '2026-10-01T12:00:00Z',
        }
      : null,
};
