import 'package:flutter_test/flutter_test.dart';
import 'package:app_inmobiliaria_mobile/features/properties/domain/property.dart';
import 'package:app_inmobiliaria_mobile/features/saved_searches/domain/saved_search.dart';

void main() {
  test('builds the API query, omitting empty values and the default sort', () {
    const f = SearchFilters(listingType: 'sale', city: 'Hoboken', minPrice: 300000, minBeds: 2);
    expect(f.toQuery(), {'listing_type': 'sale', 'city': 'Hoboken', 'min_price': 300000, 'min_beds': 2});
    expect(f.copyWith(sort: 'price_asc').toQuery(page: 2), containsPair('sort', 'price_asc'));
    expect(f.toQuery(page: 2)['page'], 2);
    expect(const SearchFilters().isEmpty, isTrue);
  });

  test('a 5-digit location is a ZIP code, anything else a city', () {
    final zip = const SearchFilters(city: 'Newark').withLocation(' 07030 ');
    expect(zip.postalCode, '07030');
    expect(zip.city, isNull);
    final city = zip.withLocation('Jersey City');
    expect(city.city, 'Jersey City');
    expect(city.postalCode, isNull);
    expect(city.withLocation('').filterMap(), isEmpty);
  });

  test('rejects a max price below the min price', () {
    expect(const SearchFilters(minPrice: 500000, maxPrice: 400000).validate(), 'priceRange');
    expect(const SearchFilters(minPrice: 400000, maxPrice: 500000).validate(), isNull);
  });

  test('replays saved searches', () {
    final saved = SavedSearch.fromJson({
      'id': 1,
      'name': 'Hoboken 3bd',
      'filters': {'city': 'Hoboken', 'min_beds': '3', 'listing_type': 'sale'},
      'sort': 'price_desc',
      'alert_frequency': 'weekly',
    });
    final f = saved.toFilters();
    expect(f.toQuery(), {'listing_type': 'sale', 'city': 'Hoboken', 'min_beds': 3, 'sort': 'price_desc'});
    expect(f.locationLabel, 'Hoboken');
  });

  test('hides the street when the listing does not display it', () {
    final p = PropertySummary.fromJson({
      'slug': 'x',
      'title': 'T',
      'address': {
        'street_address': '1 Secret Rd',
        'city': 'Montclair',
        'state': 'NJ',
        'postal_code': '07042',
        'displayed': false,
      },
    });
    expect(p.addressLine, 'Montclair, NJ 07042');
  });
}
