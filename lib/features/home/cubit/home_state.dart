import 'package:equatable/equatable.dart';
import '../../../data/models/catalog/home_api_model.dart';
import '../../../data/models/product_model.dart';

abstract class HomeState extends Equatable {
  const HomeState();

  @override
  List<Object?> get props => [];
}

class HomeInitial extends HomeState {
  const HomeInitial();
}

class HomeLoading extends HomeState {
  const HomeLoading();
}

class HomeLoaded extends HomeState {
  final HomeApiModel homeData;
  final List<ProductModel> bestSellers;
  final List<ProductModel> newArrivals;
  final List<ProductModel> exclusive;

  HomeLoaded({
    required this.homeData,
  })  : bestSellers = homeData.sections.bestSellers
            .map((item) => ProductModel.fromApiListItem(item))
            .toList(),
        newArrivals = homeData.sections.newArrivals
            .map((item) => ProductModel.fromApiListItem(item))
            .toList(),
        exclusive = homeData.sections.exclusive
            .map((item) => ProductModel.fromApiListItem(item))
            .toList();

  List<HomeBannerModel> get banners => homeData.banners;
  List<HomeCategorySummary> get featuredCategories => homeData.featuredCategories;
  List<HomeSectionModel> get homeSections => homeData.homeSections;
  bool get hasServerDrivenSections => homeData.hasServerDrivenSections;

  @override
  List<Object?> get props => [homeData, bestSellers, newArrivals, exclusive];
}

class HomeError extends HomeState {
  final String message;

  const HomeError(this.message);

  @override
  List<Object?> get props => [message];
}
