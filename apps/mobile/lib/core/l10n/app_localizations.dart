import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';

/// Lightweight bilingual strings (en/es). Keys are shared with the web app's
/// vocabulary so both clients speak the same language.
class AppLocalizations {
  AppLocalizations(this.locale);

  final Locale locale;

  static const supportedLocales = [Locale('en'), Locale('es')];

  static const LocalizationsDelegate<AppLocalizations> delegate = _AppLocalizationsDelegate();

  static AppLocalizations of(BuildContext context) =>
      Localizations.of<AppLocalizations>(context, AppLocalizations) ?? AppLocalizations(const Locale('en'));

  @visibleForTesting
  static const Map<String, Map<String, String>> strings = {
    'en': {
      'appName': 'Hitlease Real Estate',
      'tagline': 'Find your place in New Jersey',
      'email': 'Email address',
      'password': 'Password',
      'confirmPassword': 'Confirm password',
      'firstName': 'First name',
      'lastName': 'Last name',
      'signIn': 'Sign in',
      'signOut': 'Sign out',
      'createAccount': 'Create account',
      'forgotPassword': 'Forgot your password?',
      'sendResetLink': 'Send reset link',
      'resetLinkSent': 'If an account exists for that email, a password reset link has been sent.',
      'noAccount': "Don't have an account? Create one",
      'haveAccount': 'Already have an account? Sign in',
      'mfaCode': 'Authentication code',
      'mfaPrompt': 'Enter the 6-digit code from your authenticator app.',
      'accountType': 'I am a…',
      'buyer': 'Buyer or renter',
      'seller': 'Seller or property owner',
      'acceptTerms': 'I agree to the Terms of Use and Privacy Policy.',
      'passwordHint': 'At least 12 characters with upper and lower case letters, a number and a symbol.',
      'required': 'This field is required.',
      'invalidEmail': 'Enter a valid email address.',
      'passwordRules': 'Use 12+ characters with upper/lowercase letters, a number and a symbol.',
      'passwordMismatch': 'Passwords do not match.',
      'acceptTermsRequired': 'You must accept the terms to continue.',
      'mfaCodeInvalid': 'Enter the 6-digit code.',
      'genericError': 'Something went wrong. Please try again.',
      'home': 'Home',
      'search': 'Search',
      'map': 'Map',
      'favorites': 'Favorites',
      'messages': 'Messages',
      'profile': 'Profile',
      'welcome': 'Welcome, {name}',
      'comingSoon': 'In development',
      'comingSoonBody': 'This feature will be available in an upcoming release.',
      'searchComingSoon': 'Property search will be available once our authorized MLS data feeds are connected. No listings are shown yet.',
      'emailNotVerified': 'Your email address is not verified yet.',
      'roles': 'Roles',
      'language': 'Language',
      'mfaStatusOn': 'Two-factor authentication is enabled.',
      'mfaStatusOff': 'Two-factor authentication is not enabled. You can enable it from the web app.',
    },
    'es': {
      'appName': 'Hitlease Real Estate',
      'tagline': 'Encuentra tu lugar en Nueva Jersey',
      'email': 'Correo electrónico',
      'password': 'Contraseña',
      'confirmPassword': 'Confirmar contraseña',
      'firstName': 'Nombre',
      'lastName': 'Apellido',
      'signIn': 'Iniciar sesión',
      'signOut': 'Cerrar sesión',
      'createAccount': 'Crear cuenta',
      'forgotPassword': '¿Olvidaste tu contraseña?',
      'sendResetLink': 'Enviar enlace',
      'resetLinkSent': 'Si existe una cuenta con ese correo, se ha enviado un enlace para restablecer la contraseña.',
      'noAccount': '¿No tienes cuenta? Crea una',
      'haveAccount': '¿Ya tienes cuenta? Inicia sesión',
      'mfaCode': 'Código de autenticación',
      'mfaPrompt': 'Ingresa el código de 6 dígitos de tu aplicación de autenticación.',
      'accountType': 'Soy…',
      'buyer': 'Comprador o arrendatario',
      'seller': 'Vendedor o propietario',
      'acceptTerms': 'Acepto los Términos de uso y la Política de privacidad.',
      'passwordHint': 'Al menos 12 caracteres con mayúsculas y minúsculas, un número y un símbolo.',
      'required': 'Este campo es obligatorio.',
      'invalidEmail': 'Ingresa un correo electrónico válido.',
      'passwordRules': 'Usa 12+ caracteres con mayúsculas/minúsculas, un número y un símbolo.',
      'passwordMismatch': 'Las contraseñas no coinciden.',
      'acceptTermsRequired': 'Debes aceptar los términos para continuar.',
      'mfaCodeInvalid': 'Ingresa el código de 6 dígitos.',
      'genericError': 'Algo salió mal. Inténtalo de nuevo.',
      'home': 'Inicio',
      'search': 'Buscar',
      'map': 'Mapa',
      'favorites': 'Favoritos',
      'messages': 'Mensajes',
      'profile': 'Perfil',
      'welcome': 'Bienvenido, {name}',
      'comingSoon': 'En desarrollo',
      'comingSoonBody': 'Esta función estará disponible en una próxima versión.',
      'searchComingSoon': 'La búsqueda de propiedades estará disponible cuando se conecten nuestras fuentes MLS autorizadas. Aún no se muestran anuncios.',
      'emailNotVerified': 'Tu correo electrónico aún no está verificado.',
      'roles': 'Roles',
      'language': 'Idioma',
      'mfaStatusOn': 'La autenticación en dos pasos está activada.',
      'mfaStatusOff': 'La autenticación en dos pasos no está activada. Puedes activarla desde la aplicación web.',
    },
  };

  String t(String key, [Map<String, String> params = const {}]) {
    var value = strings[locale.languageCode]?[key] ?? strings['en']![key] ?? key;
    params.forEach((k, v) => value = value.replaceAll('{$k}', v));
    return value;
  }
}

class _AppLocalizationsDelegate extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) => ['en', 'es'].contains(locale.languageCode);

  @override
  Future<AppLocalizations> load(Locale locale) => SynchronousFuture(AppLocalizations(locale));

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

extension LocalizationContext on BuildContext {
  AppLocalizations get l10n => AppLocalizations.of(this);
}
