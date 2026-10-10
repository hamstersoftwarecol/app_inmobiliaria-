import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/application/auth_controller.dart';
import '../../features/auth/presentation/forgot_password_screen.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/register_screen.dart';
import '../../features/auth/presentation/splash_screen.dart';
import '../../features/crm/presentation/lead_detail_screen.dart';
import '../../features/crm/presentation/leads_screen.dart';
import '../../features/crm/presentation/tasks_screen.dart';
import '../../features/deals/presentation/transactions_screen.dart';
import '../../features/favorites/presentation/favorites_screen.dart';
import '../../features/home/presentation/home_screen.dart';
import '../../features/home/presentation/home_shell.dart';
import '../../features/messages/presentation/conversation_screen.dart';
import '../../features/messages/presentation/conversations_screen.dart';
import '../../features/messages/presentation/new_conversation_screen.dart';
import '../../features/notifications/presentation/notifications_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/properties/presentation/property_detail_screen.dart';
import '../../features/properties/presentation/search_screen.dart';
import '../../features/saved_searches/presentation/saved_searches_screen.dart';
import '../../features/showings/presentation/showings_screen.dart';
import '../../shared/widgets/coming_soon.dart';
import '../l10n/app_localizations.dart';

abstract final class Routes {
  static const splash = '/splash';
  static const login = '/login';
  static const register = '/register';
  static const forgotPassword = '/forgot-password';
  static const home = '/home';
  static const search = '/search';
  static const map = '/map';
  static const favorites = '/favorites';
  static const messages = '/messages';
  static const profile = '/profile';
  static const showings = '/home/showings';
  static const notifications = '/home/notifications';
  static const savedSearches = '/home/saved-searches';
  static const leads = '/home/leads';
  static const tasks = '/home/tasks';
  static const transactions = '/home/transactions';

  static String property(String slug) => '/property/${Uri.encodeComponent(slug)}';
  static String conversation(int id) => '/conversation/$id';
  static String lead(int id) => '$leads/$id';
  static String transaction(int id) => '$transactions/$id';

  static String newConversation({
    String? agentSlug,
    String? agentName,
    String? propertySlug,
    String? propertyTitle,
    int? transactionId,
  }) => Uri(
    path: '/conversation/new',
    queryParameters: {
      'agent': ?agentSlug,
      'name': ?agentName,
      'property': ?propertySlug,
      'title': ?propertyTitle,
      if (transactionId != null) 'transaction': '$transactionId',
    },
  ).toString();

  static const publicRoutes = {login, register, forgotPassword};
}

/// Pure redirect rule, unit-tested independently of widgets.
@visibleForTesting
String? authRedirect({required AsyncValue<Object?> auth, required String location}) {
  if (auth.isLoading && !auth.hasValue) return location == Routes.splash ? null : Routes.splash;

  final signedIn = auth.value != null;
  final onPublic = Routes.publicRoutes.contains(location);

  if (!signedIn) return onPublic ? null : Routes.login;
  if (onPublic || location == Routes.splash) return Routes.home;
  return null;
}

int _id(GoRouterState state) => int.tryParse(state.pathParameters['id'] ?? '') ?? 0;

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = ValueNotifier<int>(0);
  ref.listen(authControllerProvider, (_, _) => refresh.value++);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    initialLocation: Routes.splash,
    refreshListenable: refresh,
    redirect: (context, state) => authRedirect(auth: ref.read(authControllerProvider), location: state.matchedLocation),
    routes: [
      GoRoute(path: Routes.splash, builder: (_, _) => const SplashScreen()),
      GoRoute(path: Routes.login, builder: (_, _) => const LoginScreen()),
      GoRoute(path: Routes.register, builder: (_, _) => const RegisterScreen()),
      GoRoute(path: Routes.forgotPassword, builder: (_, _) => const ForgotPasswordScreen()),
      GoRoute(
        path: '/property/:slug',
        builder: (_, state) => PropertyDetailScreen(slug: state.pathParameters['slug']!),
      ),
      GoRoute(
        path: '/conversation/new',
        builder: (_, state) {
          final q = state.uri.queryParameters;
          return NewConversationScreen(
            agentSlug: q['agent'],
            agentName: q['name'],
            propertySlug: q['property'],
            propertyTitle: q['title'],
            transactionId: int.tryParse(q['transaction'] ?? ''),
          );
        },
      ),
      GoRoute(
        path: '/conversation/:id',
        builder: (_, state) => ConversationScreen(id: _id(state)),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => HomeShell(shell: shell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: Routes.home,
                builder: (_, _) => const HomeScreen(),
                routes: [
                  GoRoute(path: 'showings', builder: (_, _) => const ShowingsScreen()),
                  GoRoute(path: 'notifications', builder: (_, _) => const NotificationsScreen()),
                  GoRoute(path: 'saved-searches', builder: (_, _) => const SavedSearchesScreen()),
                  GoRoute(path: 'tasks', builder: (_, _) => const TasksScreen()),
                  GoRoute(
                    path: 'leads',
                    builder: (_, _) => const LeadsScreen(),
                    routes: [
                      GoRoute(
                        path: ':id',
                        builder: (_, state) => LeadDetailScreen(id: _id(state)),
                      ),
                    ],
                  ),
                  GoRoute(
                    path: 'transactions',
                    builder: (_, _) => const TransactionsScreen(),
                    routes: [
                      GoRoute(
                        path: ':id',
                        builder: (_, state) => TransactionDetailScreen(id: _id(state)),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: Routes.search, builder: (_, _) => const SearchScreen())],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: Routes.map,
                // Blocked: needs a Mapbox access token (no map tiles are scraped or faked).
                builder: (_, _) => const ComingSoon(titleKey: 'map', bodyKey: 'mapBlocked'),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: Routes.favorites, builder: (_, _) => const FavoritesScreen())],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: Routes.messages, builder: (_, _) => const ConversationsScreen())],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: Routes.profile, builder: (_, _) => const ProfileScreen())],
          ),
        ],
      ),
    ],
    errorBuilder: (context, state) => Scaffold(body: Center(child: Text(context.l10n.t('genericError')))),
  );
});
