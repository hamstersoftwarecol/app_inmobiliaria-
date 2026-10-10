import '../../../core/network/json.dart';

const offerStatuses = [
  'draft',
  'submitted',
  'countered',
  'accepted',
  'rejected',
  'withdrawn',
  'expired',
  'closed',
  'terminated',
];
const financingTypes = ['conventional', 'cash', 'fha', 'va', 'usda', 'other'];
const contingencyTypes = ['attorney_review', 'inspection', 'financing', 'appraisal', 'sale_of_home', 'hoa_review'];

/// What the signed-in user may do with an offer (computed by the API's policies).
class OfferAbilities {
  const OfferAbilities({this.edit = false, this.submit = false, this.withdraw = false, this.respond = false});

  factory OfferAbilities.fromJson(Map<String, dynamic>? j) => j == null
      ? const OfferAbilities()
      : OfferAbilities(
          edit: j['edit'] == true,
          submit: j['submit'] == true,
          withdraw: j['withdraw'] == true,
          respond: j['respond'] == true,
        );

  final bool edit;
  final bool submit;
  final bool withdraw;
  final bool respond;

  bool get any => edit || submit || withdraw || respond;
}

/// The negotiable terms shared by offers and counter-offers.
class OfferTerms {
  const OfferTerms({
    required this.amount,
    this.earnestMoney,
    this.financing = 'conventional',
    this.downPaymentPercent,
    this.closingDate,
    this.contingencies = const ['attorney_review', 'inspection'],
    this.terms,
    this.expiresAt,
  });

  final double amount;
  final double? earnestMoney;
  final String financing;
  final double? downPaymentPercent;

  /// Calendar date (YYYY-MM-DD).
  final String? closingDate;
  final List<String> contingencies;
  final String? terms;
  final DateTime? expiresAt;

  Map<String, dynamic> toJson() => {
    'amount': amount,
    'earnest_money': earnestMoney,
    'financing': financing,
    'down_payment_percent': financing == 'cash' ? null : downPaymentPercent,
    'closing_date': closingDate,
    'contingencies': contingencies,
    'terms': terms,
    'expires_at': expiresAt?.toUtc().toIso8601String(),
  };
}

class Offer {
  const Offer({
    required this.id,
    required this.side,
    required this.status,
    required this.buyerName,
    required this.amount,
    required this.financing,
    this.parentId,
    this.propertySlug,
    this.propertyTitle,
    this.listPrice,
    this.buyerAgentName,
    this.buyerAgentExternal,
    this.earnestMoney,
    this.downPaymentPercent,
    this.closingDate,
    this.contingencies = const [],
    this.terms,
    this.expiresAt,
    this.submittedAt,
    this.responseNote,
    this.transactionId,
    this.can = const OfferAbilities(),
  });

  factory Offer.fromJson(Map<String, dynamic> j) {
    final p = asMapOrNull(j['property']);
    return Offer(
      id: asInt(j['id']) ?? 0,
      parentId: asInt(j['parent_id']),
      side: asString(j['side']) ?? 'buyer',
      status: asString(j['status']) ?? 'draft',
      propertySlug: asString(p?['slug']),
      propertyTitle: asString(p?['title']),
      listPrice: asDouble(p?['list_price']),
      buyerName: asString(j['buyer_name']) ?? '',
      buyerAgentName: asString(asMapOrNull(j['buyer_agent'])?['full_name']),
      buyerAgentExternal: asString(j['buyer_agent_external']),
      amount: asDouble(j['amount']) ?? 0,
      earnestMoney: asDouble(j['earnest_money']),
      financing: asString(j['financing']) ?? 'other',
      downPaymentPercent: asDouble(j['down_payment_percent']),
      closingDate: asString(j['closing_date']),
      contingencies: asStrings(j['contingencies']),
      terms: asString(j['terms']),
      expiresAt: asDate(j['expires_at']),
      submittedAt: asDate(j['submitted_at']),
      responseNote: asString(j['response_note']),
      transactionId: asInt(j['transaction_id']),
      can: OfferAbilities.fromJson(asMapOrNull(j['can'])),
    );
  }

  final int id;
  final int? parentId;
  final String side;
  final String status;
  final String? propertySlug;
  final String? propertyTitle;
  final double? listPrice;
  final String buyerName;
  final String? buyerAgentName;
  final String? buyerAgentExternal;
  final double amount;
  final double? earnestMoney;
  final String financing;
  final double? downPaymentPercent;
  final String? closingDate;
  final List<String> contingencies;
  final String? terms;
  final DateTime? expiresAt;
  final DateTime? submittedAt;
  final String? responseNote;
  final int? transactionId;
  final OfferAbilities can;

  OfferTerms get asTerms => OfferTerms(
    amount: amount,
    earnestMoney: earnestMoney,
    financing: financing,
    downPaymentPercent: downPaymentPercent,
    closingDate: closingDate,
    contingencies: contingencies,
    terms: terms,
  );
}
