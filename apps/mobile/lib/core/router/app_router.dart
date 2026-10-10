import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/account/presentation/account_screens.dart';
import '../../features/agents/presentation/agents_screen.dart';
import '../../features/agents/presentation/my_agent_profile_screen.dart';
import '../../features/auth/application/auth_controller.dart';
import '../../features/availability/presentation/availability_screen.dart';
import '../../features/auth/presentation/email_link_screens.dart';
import '../../features/auth/presentation/forgot_password_screen.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/register_screen.dart';
import '../../features/auth/presentation/splash_screen.dart';
import '../../features/crm/presentation/lead_detail_screen.dart';
import '../../features/crm/presentation/leads_screen.dart';
import '../../features/crm/presentation/new_lead_screen.dart';
import '../../features/crm/presentation/tasks_screen.dart';
import '../../features/deals/presentation/transactions_screen.dart';
import '../../features/favorites/presentation/favorites_screen.dart';
import '../../features/home/presentation/home_screen.dart';
import '../../features/home/presentation/home_shell.dart';
import '../../features/messages/presentation/conversation_screen.dart';
import '../../features/messages/presentation/conversations_screen.dart';
import '../../features/messages/presentation/new_conversation_screen.dart';
import '../../features/notifications/presentation/notifications_screen.dart';
import '../../features/offers/presentation/offer_detail_screen.dart';
import '../../features/offers/presentation/offers_screen.dart';
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
  static const offers = '/home/offers';
  static const availability = '/home/availability';
  static const newLead = '/home/leads/new';
  static const agents = '/agents';
  static const editProfile = '/profile/edit';
  static const changePassword = '/profile/password';
  static const security = '/profile/security';
  static const emailPreferences = '/profile/email-preferences';
  static const myAgentProfile = '/profile/agent';

  static String property(String slug) => '/property/${Uri.encodeComponent(slug)}';
  static String conversation(int id) => '/conversation/$id';
  static String lead(int id) => '$leads/$id';
  static String transaction(int id) => '$transactions/$id';
  static String offer(int id) => '$offers/$id';
  static String agent(String slug) => '/agents/${Uri.encodeComponent(slug)}';

  static String newOffer({String? propertySlug, String? propertyTitle}) =>
      Uri(path: '$offers/new', queryParameters: {'property': ?propertySlug, 'title': ?propertyTitle}).toString();

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

  static const resetPassword = '/reset-password';
  static const verifyEmail = '/verify-email';
  static const unsubscribe = '/unsubscribe';

  static const publicRoutes = {login, register, forgotPassword};

  /// Opened from e-mail links; usable signed in or out.
  static const openRoutes = {resetPassword, verifyEmail, unsubscribe};
}

/// Translates a link to the web app (from an e-mail or a shared URL, opened through App Links /
/// Universal Links) into the matching screen of this app; null when the path needs no change.
@visibleForTesting
String? appLocationForWebLink(Uri uri) {
  int? id(String s) => int.tryParse(s);
  String? withId(String s, String Function(int) route) => id(s) == null ? null : route(id(s)!);

  return switch (uri.pathSegments) {
    ['properties'] => Routes.search,
    ['properties', final slug] => Routes.property(slug),
    ['dashboard'] => Routes.home,
    ['dashboard', 'favorites'] => Routes.favorites,
    ['dashboard', 'saved-searches'] => Routes.savedSearches,
    ['dashboard', 'notifications'] => Routes.notifications,
    ['dashboard', 'showings'] => Routes.showings,
    ['dashboard', 'availability'] => Routes.availability,
    ['dashboard', 'messages'] => Routes.messages,
    ['dashboard', 'messages', final c] => withId(c, Routes.conversation),
    ['dashboard', 'crm'] || ['dashboard', 'crm', 'leads'] => Routes.leads,
    ['dashboard', 'crm', 'leads', final l] => withId(l, Routes.lead),
    ['dashboard', 'crm', 'tasks'] => Routes.tasks,
    ['dashboard', 'offers'] => Routes.offers,
    ['dashboard', 'offers', final o] => withId(o, Routes.offer),
    ['dashboard', 'transactions'] => Routes.transactions,
    ['dashboard', 'transactions', final t] => withId(t, Routes.transaction),
    ['dashboard', 'settings'] => Routes.profile,
    ['dashboard', 'agent-profile'] => Routes.myAgentProfile,
    _ => null,
  };
}

/// Pure redirect rule, unit-tested independently of widgets.
@visibleForTesting
///
/// [uri] is the full requested location: when a link to a protected screen is opened while the
/// session is restoring or signed out, it travels as `?from=` and is reopened after sign-in.
String? authRedirect({required AsyncValue<Object?> auth, required String location, Uri? uri}) {
  if (Routes.openRoutes.contains(location)) return null;

  final atEntry = location == Routes.splash || Routes.publicRoutes.contains(location);
  final from = atEntry ? uri?.queryParameters['from'] : (uri == null || location == Routes.home ? null : '$uri');
  String keeping(String route) => from == null ? route : Uri(path: route, queryParameters: {'from': from}).toString();

  if (auth.isLoading && !auth.hasValue) return location == Routes.splash ? null : keeping(Routes.splash);

  final signedIn = auth.value != null;
  final onPublic = Routes.publicRoutes.contains(location);

  if (!signedIn) return onPublic ? null : keeping(Routes.login);
  if (atEntry) return from != null && from.startsWith('/') ? from : Routes.home;
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
    redirect: (context, state) =>
        appLocationForWebLink(state.uri) ??
        authRedirect(auth: ref.read(authControllerProvider), location: state.matchedLocation, uri: state.uri),
    routes: [
      GoRoute(path: Routes.splash, builder: (_, _) => const SplashScreen()),
      GoRoute(path: Routes.login, builder: (_, _) => const LoginScreen()),
      GoRoute(path: Routes.register, builder: (_, _) => const RegisterScreen()),
      GoRoute(path: Routes.forgotPassword, builder: (_, _) => const ForgotPasswordScreen()),
      GoRoute(
        path: Routes.resetPassword,
        builder: (_, state) =>
            ResetPasswordScreen(token: state.uri.queryParameters['token'], email: state.uri.queryParameters['email']),
      ),
      GoRoute(
        path: Routes.verifyEmail,
        builder: (_, state) => VerifyEmailScreen(params: state.uri.queryParameters),
      ),
      GoRoute(
        path: Routes.unsubscribe,
        builder: (_, state) => UnsubscribeScreen(token: state.uri.queryParameters['token']),
      ),
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
      GoRoute(
        path: Routes.agents,
        builder: (_, _) => const AgentsScreen(),
        routes: [
          GoRoute(
            path: ':slug',
            builder: (_, state) => AgentProfileScreen(slug: state.pathParameters['slug']!),
          ),
        ],
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
                  GoRoute(path: 'availability', builder: (_, _) => const AvailabilityScreen()),
                  GoRoute(
                    path: 'offers',
                    builder: (_, _) => const OffersScreen(),
                    routes: [
                      GoRoute(
                        path: 'new',
                        builder: (_, state) => NewOfferScreen(
                          propertySlug: state.uri.queryParameters['property'],
                          propertyTitle: state.uri.queryParameters['title'],
                        ),
                      ),
                      GoRoute(
                        path: ':id',
                        builder: (_, state) => OfferDetailScreen(id: _id(state)),
                      ),
                    ],
                  ),
                  GoRoute(
                    path: 'leads',
                    builder: (_, _) => const LeadsScreen(),
                    routes: [
                      GoRoute(path: 'new', builder: (_, _) => const NewLeadScreen()),
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
            routes: [
              GoRoute(
                path: Routes.profile,
                builder: (_, _) => const ProfileScreen(),
                routes: [
                  GoRoute(path: 'edit', builder: (_, _) => const EditProfileScreen()),
                  GoRoute(path: 'password', builder: (_, _) => const ChangePasswordScreen()),
                  GoRoute(path: 'security', builder: (_, _) => const SecurityScreen()),
                  GoRoute(path: 'email-preferences', builder: (_, _) => const NotificationPreferencesScreen()),
                  GoRoute(path: 'agent', builder: (_, _) => const MyAgentProfileScreen()),
                ],
              ),
            ],
          ),
        ],
      ),
    ],
    errorBuilder: (context, state) => Scaffold(body: Center(child: Text(context.l10n.t('genericError')))),
  );
});
