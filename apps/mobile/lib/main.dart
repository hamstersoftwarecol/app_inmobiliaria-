import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'core/network/api_client.dart';
import 'features/auth/application/auth_controller.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  final container = ProviderContainer(
    overrides: [
      // When the API rejects the stored token, drop to the signed-out state.
      unauthorizedHandlerProvider.overrideWith(
        (ref) =>
            () => ref.read(authControllerProvider.notifier).sessionExpired(),
      ),
    ],
  );

  runApp(UncontrolledProviderScope(container: container, child: const HitleaseApp()));
}
