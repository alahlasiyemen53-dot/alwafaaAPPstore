import '../datasources/loyalty_remote_datasource.dart';
import '../models/loyalty/customer_loyalty_model.dart';

abstract interface class LoyaltyRepository {
  Future<CustomerLoyaltyModel> getLoyalty();
}

final class LoyaltyRepositoryImpl implements LoyaltyRepository {
  final LoyaltyRemoteDataSource _remoteDataSource;

  LoyaltyRepositoryImpl({LoyaltyRemoteDataSource? remoteDataSource})
      : _remoteDataSource = remoteDataSource ?? LoyaltyRemoteDataSourceImpl();

  @override
  Future<CustomerLoyaltyModel> getLoyalty() => _remoteDataSource.getLoyalty();
}
