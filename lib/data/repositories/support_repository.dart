import '../datasources/support_remote_datasource.dart';
import '../models/support/customer_support_model.dart';

abstract interface class SupportRepository {
  Future<CustomerSupportModel> getSupportInfo();
}

final class SupportRepositoryImpl implements SupportRepository {
  final SupportRemoteDataSource _remoteDataSource;

  SupportRepositoryImpl({SupportRemoteDataSource? remoteDataSource})
      : _remoteDataSource = remoteDataSource ?? SupportRemoteDataSourceImpl();

  @override
  Future<CustomerSupportModel> getSupportInfo() =>
      _remoteDataSource.getSupportInfo();
}
