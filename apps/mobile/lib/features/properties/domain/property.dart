import '../../../core/network/json.dart';

/// MLS attribution shown wherever an MLS listing appears (display rules come from the API).
class Compliance {
  const Compliance({required this.sourceMls, this.listingId, this.attribution, this.disclaimer, this.lastUpdatedAt});

  factory Compliance.fromJson(Map<String, dynamic> j) => Compliance(
    sourceMls: asString(j['source_mls']) ?? '',
    listingId: asString(j['listing_id']),
    attribution: asString(j['attribution']),
    disclaimer: asString(j['disclaimer']),
    lastUpdatedAt: asDate(j['last_updated_at']),
  );

  final String sourceMls;
  final String? listingId;
  final String? attribution;
  final String? disclaimer;
  final DateTime? lastUpdatedAt;
}

/// `PublicPropertySummary` from the API.
class PropertySummary {
  const PropertySummary({
    required this.slug,
    required this.title,
    required this.status,
    required this.listingType,
    this.propertyType,
    this.listPrice,
    this.street,
    this.city,
    this.state = 'NJ',
    this.postalCode,
    this.addressDisplayed = true,
    this.bedrooms,
    this.bathrooms,
    this.livingAreaSqft,
    this.coverPhoto,
    this.compliance,
  });

  factory PropertySummary.fromJson(Map<String, dynamic> j) {
    final address = asMap(j['address']);
    final compliance = asMapOrNull(j['compliance']);
    return PropertySummary(
      slug: asString(j['slug']) ?? '',
      title: asString(j['title']) ?? '',
      status: asString(j['status']) ?? 'active',
      listingType: asString(j['listing_type']) ?? 'sale',
      propertyType: asString(asMap(j['property_type'])['name']),
      listPrice: asDouble(j['list_price']),
      street: asString(address['street_address']),
      city: asString(address['city']),
      state: asString(address['state']) ?? 'NJ',
      postalCode: asString(address['postal_code']),
      addressDisplayed: address['displayed'] != false,
      bedrooms: asInt(j['bedrooms']),
      bathrooms: asDouble(j['bathrooms']),
      livingAreaSqft: asInt(j['living_area_sqft']),
      coverPhoto: asString(j['cover_photo']),
      compliance: compliance == null ? null : Compliance.fromJson(compliance),
    );
  }

  final String slug;
  final String title;
  final String status;
  final String listingType;
  final String? propertyType;
  final double? listPrice;
  final String? street;
  final String? city;
  final String state;
  final String? postalCode;
  final bool addressDisplayed;
  final int? bedrooms;
  final double? bathrooms;
  final int? livingAreaSqft;
  final String? coverPhoto;
  final Compliance? compliance;

  bool get isRent => listingType == 'rent';

  /// The street is only shown when the listing allows it.
  String get addressLine => [
    if (addressDisplayed && street != null) street,
    city,
    [state, postalCode].whereType<String>().join(' '),
  ].whereType<String>().where((s) => s.trim().isNotEmpty).join(', ');
}

class ListingAgent {
  const ListingAgent({required this.fullName, required this.slug, this.phone, this.email});

  factory ListingAgent.fromJson(Map<String, dynamic> j) => ListingAgent(
    fullName: asString(j['full_name']) ?? '',
    slug: asString(j['slug']) ?? '',
    phone: asString(j['phone']),
    email: asString(j['email']),
  );

  final String fullName;
  final String slug;
  final String? phone;
  final String? email;
}

/// `PublicProperty` (detail) from the API. Restricted MLS fields arrive as `null`.
class PropertyDetail {
  const PropertyDetail({
    required this.summary,
    this.description,
    this.lotSizeSqft,
    this.yearBuilt,
    this.parkingSpaces,
    this.garageSpaces,
    this.amenities = const [],
    this.hoaFeeMonthly,
    this.annualTaxes,
    this.photos = const [],
    this.listingAgent,
    this.brokerageName,
    this.brokerageLicense,
  });

  factory PropertyDetail.fromJson(Map<String, dynamic> j) {
    final d = asMap(j['details']);
    final agent = asMapOrNull(j['listing_agent']);
    final brokerage = asMapOrNull(j['brokerage']);
    return PropertyDetail(
      summary: PropertySummary.fromJson(j),
      description: asString(d['description']),
      lotSizeSqft: asInt(d['lot_size_sqft']),
      yearBuilt: asInt(d['year_built']),
      parkingSpaces: asInt(d['parking_spaces']),
      garageSpaces: asInt(d['garage_spaces']),
      amenities: asStrings(d['amenities']),
      hoaFeeMonthly: asDouble(d['hoa_fee_monthly']),
      annualTaxes: asDouble(d['annual_taxes']),
      photos: asList(j['photos'], (p) => asString(p['url']) ?? '').where((u) => u.isNotEmpty).toList(),
      listingAgent: agent == null ? null : ListingAgent.fromJson(agent),
      brokerageName: asString(brokerage?['name']),
      brokerageLicense: asString(brokerage?['license_number']),
    );
  }

  final PropertySummary summary;
  final String? description;
  final int? lotSizeSqft;
  final int? yearBuilt;
  final int? parkingSpaces;
  final int? garageSpaces;
  final List<String> amenities;
  final double? hoaFeeMonthly;
  final double? annualTaxes;
  final List<String> photos;
  final ListingAgent? listingAgent;
  final String? brokerageName;
  final String? brokerageLicense;
}

class LocationSuggestion {
  const LocationSuggestion({required this.type, required this.label, this.city, this.postalCode, this.listings = 0});

  factory LocationSuggestion.fromJson(Map<String, dynamic> j) => LocationSuggestion(
    type: asString(j['type']) ?? 'city',
    label: asString(j['label']) ?? '',
    city: asString(j['city']),
    postalCode: asString(j['postal_code']),
    listings: asInt(j['listings']) ?? 0,
  );

  final String type;
  final String label;
  final String? city;
  final String? postalCode;
  final int listings;
}

/// Search filters, mirroring the API's `PropertySearch` rules (and the web URL format).
class SearchFilters {
  const SearchFilters({
    this.listingType,
    this.city,
    this.postalCode,
    this.locationLabel,
    this.minPrice,
    this.maxPrice,
    this.minBeds,
    this.minBaths,
    this.sort = 'newest',
  });

  /// From a saved search's `filters` + `sort`.
  factory SearchFilters.fromSaved(Map<String, dynamic> f, String? sort) => SearchFilters(
    listingType: asString(f['listing_type']),
    city: asString(f['city']),
    postalCode: asString(f['postal_code']),
    locationLabel: asString(f['postal_code']) ?? asString(f['city']),
    minPrice: asInt(f['min_price']),
    maxPrice: asInt(f['max_price']),
    minBeds: asInt(f['min_beds']),
    minBaths: asInt(f['min_baths']),
    sort: sort ?? 'newest',
  );

  static const sorts = ['newest', 'price_asc', 'price_desc', 'sqft_desc'];

  final String? listingType;
  final String? city;
  final String? postalCode;
  final String? locationLabel;
  final int? minPrice;
  final int? maxPrice;
  final int? minBeds;
  final int? minBaths;
  final String sort;

  /// Query parameters for `GET /properties` (empty values omitted).
  Map<String, dynamic> toQuery({int page = 1}) => {
    ...filterMap(),
    if (sort != 'newest') 'sort': sort,
    if (page > 1) 'page': page,
  };

  /// What a saved search stores (no sort or page).
  Map<String, dynamic> filterMap() => {
    'listing_type': ?listingType,
    'city': ?city,
    'postal_code': ?postalCode,
    'min_price': ?minPrice,
    'max_price': ?maxPrice,
    'min_beds': ?minBeds,
    'min_baths': ?minBaths,
  };

  bool get isEmpty => filterMap().isEmpty;

  /// `null` means "no error"; otherwise an l10n key.
  String? validate() => minPrice != null && maxPrice != null && maxPrice! < minPrice! ? 'priceRange' : null;

  SearchFilters copyWith({
    String? Function()? listingType,
    String? Function()? city,
    String? Function()? postalCode,
    String? Function()? locationLabel,
    int? Function()? minPrice,
    int? Function()? maxPrice,
    int? Function()? minBeds,
    int? Function()? minBaths,
    String? sort,
  }) => SearchFilters(
    listingType: listingType != null ? listingType() : this.listingType,
    city: city != null ? city() : this.city,
    postalCode: postalCode != null ? postalCode() : this.postalCode,
    locationLabel: locationLabel != null ? locationLabel() : this.locationLabel,
    minPrice: minPrice != null ? minPrice() : this.minPrice,
    maxPrice: maxPrice != null ? maxPrice() : this.maxPrice,
    minBeds: minBeds != null ? minBeds() : this.minBeds,
    minBaths: minBaths != null ? minBaths() : this.minBaths,
    sort: sort ?? this.sort,
  );

  /// Free-text location: a 5-digit ZIP or a city name.
  SearchFilters withLocation(String text) {
    final value = text.trim();
    if (value.isEmpty) return copyWith(city: () => null, postalCode: () => null, locationLabel: () => null);
    final isZip = RegExp(r'^\d{5}$').hasMatch(value);
    return copyWith(
      city: () => isZip ? null : value,
      postalCode: () => isZip ? value : null,
      locationLabel: () => value,
    );
  }

  @override
  bool operator ==(Object other) =>
      other is SearchFilters &&
      other.toQuery().toString() == toQuery().toString() &&
      other.locationLabel == locationLabel;

  @override
  int get hashCode => Object.hash(toQuery().toString(), locationLabel);
}
