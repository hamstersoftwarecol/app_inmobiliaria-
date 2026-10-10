import 'package:flutter/material.dart';

class AppColors {
  const AppColors._();

  static const primary = Color(0xFF123B5D);
  static const secondary = Color(0xFF0F766E);
  static const background = Color(0xFFFFFFFF);
  static const danger = Color(0xFFB91C1C);
}

ThemeData buildAppTheme() {
  final scheme = ColorScheme.fromSeed(
    seedColor: AppColors.primary,
    primary: AppColors.primary,
    secondary: AppColors.secondary,
    surface: AppColors.background,
    error: AppColors.danger,
  );

  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: AppColors.background,
    appBarTheme: const AppBarTheme(backgroundColor: AppColors.primary, foregroundColor: Colors.white),
    inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder()),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48), backgroundColor: AppColors.primary),
    ),
  );
}
