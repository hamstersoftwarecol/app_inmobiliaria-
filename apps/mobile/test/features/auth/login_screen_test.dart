import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hitlease_mobile/core/l10n/app_localizations.dart';
import 'package:hitlease_mobile/core/network/api_exception.dart';
import 'package:hitlease_mobile/features/auth/application/auth_controller.dart';
import 'package:hitlease_mobile/features/auth/domain/user.dart';
import 'package:hitlease_mobile/features/auth/presentation/login_screen.dart';

/// Scripted controller: first call requires MFA, then fails or succeeds.
class FakeAuthController extends AuthController {
  final List<String?> mfaCodes = [];
  ApiException? nextError;

  @override
  Future<User?> build() async => null;

  @override
  Future<void> login({required String email, required String password, String? mfaCode}) async {
    mfaCodes.add(mfaCode);
    final error = nextError;
    if (error != null) {
      nextError = null;
      throw error;
    }
  }
}

void main() {
  late FakeAuthController controller;

  Future<void> pump(WidgetTester tester, {Locale locale = const Locale('en')}) async {
    controller = FakeAuthController();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [authControllerProvider.overrideWith(() => controller)],
        child: MaterialApp(
          locale: locale,
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          home: const LoginScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('validates required fields before calling the API', (tester) async {
    await pump(tester);

    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    expect(find.text('This field is required.'), findsNWidgets(2));
    expect(controller.mfaCodes, isEmpty);
  });

  testWidgets('asks for an MFA code when the API requires it', (tester) async {
    await pump(tester);
    controller.nextError = const ApiException(statusCode: 422, code: 'mfa_required', message: 'Code required');

    await tester.enterText(find.byKey(const Key('login-email')), 'ana@example.com');
    await tester.enterText(find.byKey(const Key('login-password')), 'secret');
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('login-mfa')), findsOneWidget);

    await tester.enterText(find.byKey(const Key('login-mfa')), '12');
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    expect(find.text('Enter the 6-digit code.'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('login-mfa')), '123456');
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    expect(controller.mfaCodes, [null, '123456']);
  });

  testWidgets('shows API errors and supports Spanish', (tester) async {
    await pump(tester, locale: const Locale('es'));
    controller.nextError = const ApiException(
      statusCode: 422,
      code: 'invalid_credentials',
      message: 'These credentials do not match our records.',
    );

    expect(find.text('Iniciar sesión'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('login-email')), 'ana@example.com');
    await tester.enterText(find.byKey(const Key('login-password')), 'wrong');
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    expect(find.text('These credentials do not match our records.'), findsOneWidget);
  });

  test('translations cover the same keys in both languages', () {
    expect(AppLocalizations.strings['es']!.keys.toSet(), AppLocalizations.strings['en']!.keys.toSet());
  });
}
