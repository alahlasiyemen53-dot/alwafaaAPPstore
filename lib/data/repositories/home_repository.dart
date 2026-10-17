import '../datasources/home_remote_datasource.dart';
import '../models/catalog/home_api_model.dart';

abstract class HomeRepository {
  Future<HomeApiModel> getHomeData();
}

class HomeRepositoryImpl implements HomeRepository {
  final HomeRemoteDataSource remoteDataSource;

  HomeRepositoryImpl({HomeRemoteDataSource? remoteDataSource})
      : remoteDataSource = remoteDataSource ?? HomeRemoteDataSourceImpl();

  @override
  Future<HomeApiModel> getHomeData() async {
    return await remoteDataSource.getHomeData();
  }
}
