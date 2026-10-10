// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint, type=warning, deprecated_member_use, deprecated_member_use_from_same_package
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'user.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// GENERATED CODE - DO NOT MODIFY BY HAND
// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$UserRole {

 String get name;@JsonKey(name: 'display_name') String get displayName;
/// Create a copy of UserRole
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$UserRoleCopyWith<UserRole> get copyWith => _$UserRoleCopyWithImpl<UserRole>(this as UserRole, _$identity);

  /// Serializes this UserRole to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  final _this = this as UserRole;
  return identical(this, other) || (other.runtimeType == runtimeType&&other is UserRole&&(identical(other.name, _this.name) || other.name == _this.name)&&(identical(other.displayName, _this.displayName) || other.displayName == _this.displayName));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode {
  final _this = this as UserRole;
  return Object.hash(runtimeType,_this.name,_this.displayName);
}

@override
String toString() {
  final _this = this as UserRole;
  return 'UserRole(name: ${_this.name}, displayName: ${_this.displayName})';
}


}

/// @nodoc
abstract mixin class $UserRoleCopyWith<$Res>  {
  factory $UserRoleCopyWith(UserRole value, $Res Function(UserRole) _then) = _$UserRoleCopyWithImpl;
@useResult
$Res call({
 String name,@JsonKey(name: 'display_name') String displayName
});




}
/// @nodoc
class _$UserRoleCopyWithImpl<$Res>
    implements $UserRoleCopyWith<$Res> {
  _$UserRoleCopyWithImpl(this._self, this._then);

  final UserRole _self;
  final $Res Function(UserRole) _then;

/// Create a copy of UserRole
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? name = null,Object? displayName = null,}) {
  return _then(UserRole(
name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,displayName: null == displayName ? _self.displayName : displayName // ignore: cast_nullable_to_non_nullable
as String,
  ));
}

}


/// Adds pattern-matching-related methods to [UserRole].
extension UserRolePatterns on UserRole {
/// A variant of `map` that fallback to returning `orElse`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _UserRole value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _UserRole() when $default != null:
return $default(_that);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// Callbacks receives the raw object, upcasted.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case final Subclass2 value:
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _UserRole value)  $default,){
final _that = this;
switch (_that) {
case _UserRole():
return $default(_that);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `map` that fallback to returning `null`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _UserRole value)?  $default,){
final _that = this;
switch (_that) {
case _UserRole() when $default != null:
return $default(_that);case _:
  return null;

}
}
/// A variant of `when` that fallback to an `orElse` callback.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String name, @JsonKey(name: 'display_name')  String displayName)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _UserRole() when $default != null:
return $default(_that.name,_that.displayName);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// As opposed to `map`, this offers destructuring.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case Subclass2(:final field2):
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String name, @JsonKey(name: 'display_name')  String displayName)  $default,) {final _that = this;
switch (_that) {
case _UserRole():
return $default(_that.name,_that.displayName);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `when` that fallback to returning `null`
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String name, @JsonKey(name: 'display_name')  String displayName)?  $default,) {final _that = this;
switch (_that) {
case _UserRole() when $default != null:
return $default(_that.name,_that.displayName);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _UserRole implements UserRole {
  const _UserRole({required this.name, @JsonKey(name: 'display_name') required this.displayName});
  factory _UserRole.fromJson(Map<String, dynamic> json) => _$UserRoleFromJson(json);

@override final  String name;
@override@JsonKey(name: 'display_name') final  String displayName;

/// Create a copy of UserRole
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$UserRoleCopyWith<_UserRole> get copyWith => __$UserRoleCopyWithImpl<_UserRole>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$UserRoleToJson(this, );
}

@override
bool operator ==(Object other) {
    return identical(this, other) || (other.runtimeType == runtimeType&&other is _UserRole&&(identical(other.name, name) || other.name == name)&&(identical(other.displayName, displayName) || other.displayName == displayName));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode {
    return Object.hash(runtimeType,name,displayName);
}

@override
String toString() {
    return 'UserRole(name: $name, displayName: $displayName)';
}


}

/// @nodoc
abstract mixin class _$UserRoleCopyWith<$Res> implements $UserRoleCopyWith<$Res> {
  factory _$UserRoleCopyWith(_UserRole value, $Res Function(_UserRole) _then) = __$UserRoleCopyWithImpl;
@override @useResult
$Res call({
 String name,@JsonKey(name: 'display_name') String displayName
});




}
/// @nodoc
class __$UserRoleCopyWithImpl<$Res>
    implements _$UserRoleCopyWith<$Res> {
  __$UserRoleCopyWithImpl(this._self, this._then);

  final _UserRole _self;
  final $Res Function(_UserRole) _then;

/// Create a copy of UserRole
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? name = null,Object? displayName = null,}) {
  return _then(_UserRole(
name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,displayName: null == displayName ? _self.displayName : displayName // ignore: cast_nullable_to_non_nullable
as String,
  ));
}


}


/// @nodoc
mixin _$User {

 int get id;@JsonKey(name: 'first_name') String get firstName;@JsonKey(name: 'last_name') String get lastName;@JsonKey(name: 'full_name') String get fullName; String get email; String? get phone; String get locale; String get timezone; String get status;@JsonKey(name: 'email_verified') bool get emailVerified;@JsonKey(name: 'mfa_enabled') bool get mfaEnabled;@JsonKey(name: 'is_staff') bool get isStaff; List<UserRole> get roles; List<String> get permissions;
/// Create a copy of User
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$UserCopyWith<User> get copyWith => _$UserCopyWithImpl<User>(this as User, _$identity);

  /// Serializes this User to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  final _this = this as User;
  return identical(this, other) || (other.runtimeType == runtimeType&&other is User&&(identical(other.id, _this.id) || other.id == _this.id)&&(identical(other.firstName, _this.firstName) || other.firstName == _this.firstName)&&(identical(other.lastName, _this.lastName) || other.lastName == _this.lastName)&&(identical(other.fullName, _this.fullName) || other.fullName == _this.fullName)&&(identical(other.email, _this.email) || other.email == _this.email)&&(identical(other.phone, _this.phone) || other.phone == _this.phone)&&(identical(other.locale, _this.locale) || other.locale == _this.locale)&&(identical(other.timezone, _this.timezone) || other.timezone == _this.timezone)&&(identical(other.status, _this.status) || other.status == _this.status)&&(identical(other.emailVerified, _this.emailVerified) || other.emailVerified == _this.emailVerified)&&(identical(other.mfaEnabled, _this.mfaEnabled) || other.mfaEnabled == _this.mfaEnabled)&&(identical(other.isStaff, _this.isStaff) || other.isStaff == _this.isStaff)&&const DeepCollectionEquality().equals(other.roles, _this.roles)&&const DeepCollectionEquality().equals(other.permissions, _this.permissions));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode {
  final _this = this as User;
  return Object.hash(runtimeType,_this.id,_this.firstName,_this.lastName,_this.fullName,_this.email,_this.phone,_this.locale,_this.timezone,_this.status,_this.emailVerified,_this.mfaEnabled,_this.isStaff,const DeepCollectionEquality().hash(_this.roles),const DeepCollectionEquality().hash(_this.permissions));
}

@override
String toString() {
  final _this = this as User;
  return 'User(id: ${_this.id}, firstName: ${_this.firstName}, lastName: ${_this.lastName}, fullName: ${_this.fullName}, email: ${_this.email}, phone: ${_this.phone}, locale: ${_this.locale}, timezone: ${_this.timezone}, status: ${_this.status}, emailVerified: ${_this.emailVerified}, mfaEnabled: ${_this.mfaEnabled}, isStaff: ${_this.isStaff}, roles: ${_this.roles}, permissions: ${_this.permissions})';
}


}

/// @nodoc
abstract mixin class $UserCopyWith<$Res>  {
  factory $UserCopyWith(User value, $Res Function(User) _then) = _$UserCopyWithImpl;
@useResult
$Res call({
 int id,@JsonKey(name: 'first_name') String firstName,@JsonKey(name: 'last_name') String lastName,@JsonKey(name: 'full_name') String fullName, String email, String? phone, String locale, String timezone, String status,@JsonKey(name: 'email_verified') bool emailVerified,@JsonKey(name: 'mfa_enabled') bool mfaEnabled,@JsonKey(name: 'is_staff') bool isStaff, List<UserRole> roles, List<String> permissions
});




}
/// @nodoc
class _$UserCopyWithImpl<$Res>
    implements $UserCopyWith<$Res> {
  _$UserCopyWithImpl(this._self, this._then);

  final User _self;
  final $Res Function(User) _then;

/// Create a copy of User
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? firstName = null,Object? lastName = null,Object? fullName = null,Object? email = null,Object? phone = freezed,Object? locale = null,Object? timezone = null,Object? status = null,Object? emailVerified = null,Object? mfaEnabled = null,Object? isStaff = null,Object? roles = null,Object? permissions = null,}) {
  return _then(User(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,firstName: null == firstName ? _self.firstName : firstName // ignore: cast_nullable_to_non_nullable
as String,lastName: null == lastName ? _self.lastName : lastName // ignore: cast_nullable_to_non_nullable
as String,fullName: null == fullName ? _self.fullName : fullName // ignore: cast_nullable_to_non_nullable
as String,email: null == email ? _self.email : email // ignore: cast_nullable_to_non_nullable
as String,phone: freezed == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String?,locale: null == locale ? _self.locale : locale // ignore: cast_nullable_to_non_nullable
as String,timezone: null == timezone ? _self.timezone : timezone // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,emailVerified: null == emailVerified ? _self.emailVerified : emailVerified // ignore: cast_nullable_to_non_nullable
as bool,mfaEnabled: null == mfaEnabled ? _self.mfaEnabled : mfaEnabled // ignore: cast_nullable_to_non_nullable
as bool,isStaff: null == isStaff ? _self.isStaff : isStaff // ignore: cast_nullable_to_non_nullable
as bool,roles: null == roles ? _self.roles : roles // ignore: cast_nullable_to_non_nullable
as List<UserRole>,permissions: null == permissions ? _self.permissions : permissions // ignore: cast_nullable_to_non_nullable
as List<String>,
  ));
}

}


/// Adds pattern-matching-related methods to [User].
extension UserPatterns on User {
/// A variant of `map` that fallback to returning `orElse`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _User value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _User() when $default != null:
return $default(_that);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// Callbacks receives the raw object, upcasted.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case final Subclass2 value:
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _User value)  $default,){
final _that = this;
switch (_that) {
case _User():
return $default(_that);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `map` that fallback to returning `null`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _User value)?  $default,){
final _that = this;
switch (_that) {
case _User() when $default != null:
return $default(_that);case _:
  return null;

}
}
/// A variant of `when` that fallback to an `orElse` callback.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'first_name')  String firstName, @JsonKey(name: 'last_name')  String lastName, @JsonKey(name: 'full_name')  String fullName,  String email,  String? phone,  String locale,  String timezone,  String status, @JsonKey(name: 'email_verified')  bool emailVerified, @JsonKey(name: 'mfa_enabled')  bool mfaEnabled, @JsonKey(name: 'is_staff')  bool isStaff,  List<UserRole> roles,  List<String> permissions)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _User() when $default != null:
return $default(_that.id,_that.firstName,_that.lastName,_that.fullName,_that.email,_that.phone,_that.locale,_that.timezone,_that.status,_that.emailVerified,_that.mfaEnabled,_that.isStaff,_that.roles,_that.permissions);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// As opposed to `map`, this offers destructuring.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case Subclass2(:final field2):
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'first_name')  String firstName, @JsonKey(name: 'last_name')  String lastName, @JsonKey(name: 'full_name')  String fullName,  String email,  String? phone,  String locale,  String timezone,  String status, @JsonKey(name: 'email_verified')  bool emailVerified, @JsonKey(name: 'mfa_enabled')  bool mfaEnabled, @JsonKey(name: 'is_staff')  bool isStaff,  List<UserRole> roles,  List<String> permissions)  $default,) {final _that = this;
switch (_that) {
case _User():
return $default(_that.id,_that.firstName,_that.lastName,_that.fullName,_that.email,_that.phone,_that.locale,_that.timezone,_that.status,_that.emailVerified,_that.mfaEnabled,_that.isStaff,_that.roles,_that.permissions);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `when` that fallback to returning `null`
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id, @JsonKey(name: 'first_name')  String firstName, @JsonKey(name: 'last_name')  String lastName, @JsonKey(name: 'full_name')  String fullName,  String email,  String? phone,  String locale,  String timezone,  String status, @JsonKey(name: 'email_verified')  bool emailVerified, @JsonKey(name: 'mfa_enabled')  bool mfaEnabled, @JsonKey(name: 'is_staff')  bool isStaff,  List<UserRole> roles,  List<String> permissions)?  $default,) {final _that = this;
switch (_that) {
case _User() when $default != null:
return $default(_that.id,_that.firstName,_that.lastName,_that.fullName,_that.email,_that.phone,_that.locale,_that.timezone,_that.status,_that.emailVerified,_that.mfaEnabled,_that.isStaff,_that.roles,_that.permissions);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _User extends User {
  const _User({required this.id, @JsonKey(name: 'first_name') required this.firstName, @JsonKey(name: 'last_name') required this.lastName, @JsonKey(name: 'full_name') required this.fullName, required this.email, this.phone, this.locale = 'en', this.timezone = 'America/New_York', this.status = 'active', @JsonKey(name: 'email_verified') this.emailVerified = false, @JsonKey(name: 'mfa_enabled') this.mfaEnabled = false, @JsonKey(name: 'is_staff') this.isStaff = false,  List<UserRole> roles = const <UserRole>[],  List<String> permissions = const <String>[]}): _roles = roles,_permissions = permissions,super._();
  factory _User.fromJson(Map<String, dynamic> json) => _$UserFromJson(json);

@override final  int id;
@override@JsonKey(name: 'first_name') final  String firstName;
@override@JsonKey(name: 'last_name') final  String lastName;
@override@JsonKey(name: 'full_name') final  String fullName;
@override final  String email;
@override final  String? phone;
@override@JsonKey() final  String locale;
@override@JsonKey() final  String timezone;
@override@JsonKey() final  String status;
@override@JsonKey(name: 'email_verified') final  bool emailVerified;
@override@JsonKey(name: 'mfa_enabled') final  bool mfaEnabled;
@override@JsonKey(name: 'is_staff') final  bool isStaff;
 final  List<UserRole> _roles;
@override@JsonKey() List<UserRole> get roles {
  if (_roles is EqualUnmodifiableListView) return _roles;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_roles);
}

 final  List<String> _permissions;
@override@JsonKey() List<String> get permissions {
  if (_permissions is EqualUnmodifiableListView) return _permissions;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_permissions);
}


/// Create a copy of User
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$UserCopyWith<_User> get copyWith => __$UserCopyWithImpl<_User>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$UserToJson(this, );
}

@override
bool operator ==(Object other) {
    return identical(this, other) || (other.runtimeType == runtimeType&&other is _User&&(identical(other.id, id) || other.id == id)&&(identical(other.firstName, firstName) || other.firstName == firstName)&&(identical(other.lastName, lastName) || other.lastName == lastName)&&(identical(other.fullName, fullName) || other.fullName == fullName)&&(identical(other.email, email) || other.email == email)&&(identical(other.phone, phone) || other.phone == phone)&&(identical(other.locale, locale) || other.locale == locale)&&(identical(other.timezone, timezone) || other.timezone == timezone)&&(identical(other.status, status) || other.status == status)&&(identical(other.emailVerified, emailVerified) || other.emailVerified == emailVerified)&&(identical(other.mfaEnabled, mfaEnabled) || other.mfaEnabled == mfaEnabled)&&(identical(other.isStaff, isStaff) || other.isStaff == isStaff)&&const DeepCollectionEquality().equals(other.roles, _roles)&&const DeepCollectionEquality().equals(other.permissions, _permissions));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode {
    return Object.hash(runtimeType,id,firstName,lastName,fullName,email,phone,locale,timezone,status,emailVerified,mfaEnabled,isStaff,const DeepCollectionEquality().hash(_roles),const DeepCollectionEquality().hash(_permissions));
}

@override
String toString() {
    return 'User(id: $id, firstName: $firstName, lastName: $lastName, fullName: $fullName, email: $email, phone: $phone, locale: $locale, timezone: $timezone, status: $status, emailVerified: $emailVerified, mfaEnabled: $mfaEnabled, isStaff: $isStaff, roles: $roles, permissions: $permissions)';
}


}

/// @nodoc
abstract mixin class _$UserCopyWith<$Res> implements $UserCopyWith<$Res> {
  factory _$UserCopyWith(_User value, $Res Function(_User) _then) = __$UserCopyWithImpl;
@override @useResult
$Res call({
 int id,@JsonKey(name: 'first_name') String firstName,@JsonKey(name: 'last_name') String lastName,@JsonKey(name: 'full_name') String fullName, String email, String? phone, String locale, String timezone, String status,@JsonKey(name: 'email_verified') bool emailVerified,@JsonKey(name: 'mfa_enabled') bool mfaEnabled,@JsonKey(name: 'is_staff') bool isStaff, List<UserRole> roles, List<String> permissions
});




}
/// @nodoc
class __$UserCopyWithImpl<$Res>
    implements _$UserCopyWith<$Res> {
  __$UserCopyWithImpl(this._self, this._then);

  final _User _self;
  final $Res Function(_User) _then;

/// Create a copy of User
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? firstName = null,Object? lastName = null,Object? fullName = null,Object? email = null,Object? phone = freezed,Object? locale = null,Object? timezone = null,Object? status = null,Object? emailVerified = null,Object? mfaEnabled = null,Object? isStaff = null,Object? roles = null,Object? permissions = null,}) {
  return _then(_User(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,firstName: null == firstName ? _self.firstName : firstName // ignore: cast_nullable_to_non_nullable
as String,lastName: null == lastName ? _self.lastName : lastName // ignore: cast_nullable_to_non_nullable
as String,fullName: null == fullName ? _self.fullName : fullName // ignore: cast_nullable_to_non_nullable
as String,email: null == email ? _self.email : email // ignore: cast_nullable_to_non_nullable
as String,phone: freezed == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String?,locale: null == locale ? _self.locale : locale // ignore: cast_nullable_to_non_nullable
as String,timezone: null == timezone ? _self.timezone : timezone // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,emailVerified: null == emailVerified ? _self.emailVerified : emailVerified // ignore: cast_nullable_to_non_nullable
as bool,mfaEnabled: null == mfaEnabled ? _self.mfaEnabled : mfaEnabled // ignore: cast_nullable_to_non_nullable
as bool,isStaff: null == isStaff ? _self.isStaff : isStaff // ignore: cast_nullable_to_non_nullable
as bool,roles: null == roles ? _self._roles : roles // ignore: cast_nullable_to_non_nullable
as List<UserRole>,permissions: null == permissions ? _self._permissions : permissions // ignore: cast_nullable_to_non_nullable
as List<String>,
  ));
}


}

// dart format on
