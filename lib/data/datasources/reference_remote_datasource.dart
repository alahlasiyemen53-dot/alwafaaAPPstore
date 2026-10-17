import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/currency_model.dart';
import '../models/delivery/sanaa_delivery_zone_model.dart';
import '../models/reference/yemen_reference_models.dart';

abstract interface class ReferenceRemoteDataSource {
  Future<List<CountryModel>> getCountries();
  Future<List<GovernorateModel>> getGovernoratesByCountry(int countryId);
  Future<List<GovernorateModel>> getGovernorates();
  Future<List<CurrencyModel>> getCurrencies();
  Future<SanaaDeliveryZoneModel> getSanaaDeliveryZone();
}

final class ReferenceRemoteDataSourceImpl implements ReferenceRemoteDataSource {
  final DioClient _dioClient;

  ReferenceRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<List<CountryModel>> getCountries() async {
    final response = await _dioClient.get(ApiEndpoints.countries);
    return ApiEnvelope<List<CountryModel>>.parse(
      response.data,
      (data) {
        if (data is List) {
          return data
              .whereType<Map<String, dynamic>>()
              .map(CountryModel.fromJson)
              .toList();
        }
        return <CountryModel>[];
      },
    ).data;
  }

  @override
  Future<List<GovernorateModel>> getGovernoratesByCountry(int countryId) async {
    final response =
        await _dioClient.get(ApiEndpoints.countryGovernorates(countryId));
    return ApiEnvelope<List<GovernorateModel>>.parse(
      response.data,
      (data) {
        if (data is List) {
          return data
              .whereType<Map<String, dynamic>>()
              .map(GovernorateModel.fromJson)
              .toList();
        }
        return <GovernorateModel>[];
      },
    ).data;
  }

  @override
  Future<List<GovernorateModel>> getGovernorates() async {
    final response = await _dioClient.get(ApiEndpoints.governorates);
    return ApiEnvelope<List<GovernorateModel>>.parse(
      response.data,
      (data) {
        if (data is List) {
          return data
              .whereType<Map<String, dynamic>>()
              .map(GovernorateModel.fromJson)
              .toList();
        }
        return <GovernorateModel>[];
      },
    ).data;
  }

  @override
  Future<List<CurrencyModel>> getCurrencies() async {
    final response = await _dioClient.get(ApiEndpoints.currencies);
    return ApiEnvelope<List<CurrencyModel>>.parse(
      response.data,
      (data) {
        if (data is List) {
          return data
              .whereType<Map<String, dynamic>>()
              .map(CurrencyModel.fromJson)
              .toList();
        }
        return <CurrencyModel>[];
      },
    ).data;
  }

  @override
  Future<SanaaDeliveryZoneModel> getSanaaDeliveryZone() async {
    final response = await _dioClient.get(ApiEndpoints.sanaaDeliveryZone);
    return ApiEnvelope<SanaaDeliveryZoneModel>.parse(
      response.data,
      (data) {
        if (data is Map<String, dynamic>) {
          return SanaaDeliveryZoneModel.fromJson(data);
        }
        if (data is Map) {
          return SanaaDeliveryZoneModel.fromJson(
            Map<String, dynamic>.from(data),
          );
        }
        return SanaaDeliveryZoneModel.unconfigured;
      },
    ).data;
  }
}
