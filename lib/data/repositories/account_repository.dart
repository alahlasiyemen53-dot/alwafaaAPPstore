import '../datasources/account_remote_datasource.dart';
import '../models/account/customer_address_model.dart';
import '../models/user_model.dart';

abstract interface class AccountRepository {
  Future<UserModel> getProfile();
  Future<UserModel> updateProfile({String? name, String? email});
  Future<List<CustomerAddressModel>> getAddresses();
  Future<CustomerAddressModel> createAddress(Map<String, dynamic> data);
  Future<CustomerAddressModel> updateAddress(int id, Map<String, dynamic> data);
  Future<void> deleteAddress(int id);
}

final class AccountRepositoryImpl implements AccountRepository {
  final AccountRemoteDataSource _remoteDataSource;

  AccountRepositoryImpl({AccountRemoteDataSource? remoteDataSource})
      : _remoteDataSource = remoteDataSource ?? AccountRemoteDataSourceImpl();

  @override
  Future<UserModel> getProfile() => _remoteDataSource.getProfile();

  @override
  Future<UserModel> updateProfile({String? name, String? email}) =>
      _remoteDataSource.updateProfile(name: name, email: email);

  @override
  Future<List<CustomerAddressModel>> getAddresses() =>
      _remoteDataSource.getAddresses();

  @override
  Future<CustomerAddressModel> createAddress(Map<String, dynamic> data) =>
      _remoteDataSource.createAddress(data);

  @override
  Future<CustomerAddressModel> updateAddress(int id, Map<String, dynamic> data) =>
      _remoteDataSource.updateAddress(id, data);

  @override
  Future<void> deleteAddress(int id) => _remoteDataSource.deleteAddress(id);
}
