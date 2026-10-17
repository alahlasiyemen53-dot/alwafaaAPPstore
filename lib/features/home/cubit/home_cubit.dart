import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../data/repositories/home_repository.dart';
import 'home_state.dart';

class HomeCubit extends Cubit<HomeState> {
  final HomeRepository homeRepository;

  HomeCubit({required this.homeRepository}) : super(const HomeInitial());

  Future<void> loadHomeData() async {
    emit(const HomeLoading());
    try {
      final data = await homeRepository.getHomeData();
      emit(HomeLoaded(homeData: data));
    } catch (e) {
      emit(HomeError(e.toString()));
    }
  }

  Future<void> refresh() async {
    try {
      final data = await homeRepository.getHomeData();
      emit(HomeLoaded(homeData: data));
    } catch (e) {
      // Keep previous state if available or emit error
      if (state is! HomeLoaded) {
        emit(HomeError(e.toString()));
      }
    }
  }
}
