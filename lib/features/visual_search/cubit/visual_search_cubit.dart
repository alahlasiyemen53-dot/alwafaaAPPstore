import 'package:equatable/equatable.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/network/api_failure.dart';
import '../../../data/datasources/visual_search_image_picker.dart';
import '../../../data/models/product_model.dart';
import '../../../data/repositories/visual_search_repository.dart';

class VisualSearchState extends Equatable {
  final bool isScanning;
  final Uint8List? selectedImageBytes;
  final String? selectedImageName;
  final VisualSearchImageSource? source;
  final bool hasSearched;
  final List<ProductModel> matchedProducts;
  final String? errorMessage;

  const VisualSearchState({
    this.isScanning = false,
    this.selectedImageBytes,
    this.selectedImageName,
    this.source,
    this.hasSearched = false,
    this.matchedProducts = const [],
    this.errorMessage,
  });

  bool get hasSelectedImage => selectedImageBytes != null;

  VisualSearchState copyWith({
    bool? isScanning,
    Uint8List? selectedImageBytes,
    String? selectedImageName,
    VisualSearchImageSource? source,
    bool? hasSearched,
    List<ProductModel>? matchedProducts,
    String? errorMessage,
    bool clearImage = false,
    bool clearError = false,
  }) {
    return VisualSearchState(
      isScanning: isScanning ?? this.isScanning,
      selectedImageBytes:
          clearImage ? null : (selectedImageBytes ?? this.selectedImageBytes),
      selectedImageName:
          clearImage ? null : (selectedImageName ?? this.selectedImageName),
      source: clearImage ? null : (source ?? this.source),
      hasSearched: hasSearched ?? this.hasSearched,
      matchedProducts: matchedProducts ?? this.matchedProducts,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }

  @override
  List<Object?> get props => [
        isScanning,
        selectedImageBytes,
        selectedImageName,
        source,
        hasSearched,
        matchedProducts,
        errorMessage,
      ];
}

class VisualSearchCubit extends Cubit<VisualSearchState> {
  static const _maxUploadBytes = 5 * 1024 * 1024;
  static const _acceptedMimeTypes = {
    'image/jpeg',
    'image/png',
    'image/webp',
  };

  final VisualSearchRepository _repository;
  final VisualSearchImagePicker _imagePicker;

  VisualSearchCubit({
    VisualSearchRepository? repository,
    VisualSearchImagePicker? imagePicker,
  })  : _repository = repository ?? VisualSearchRepositoryImpl(),
        _imagePicker = imagePicker ?? ImagePickerVisualSearchImagePicker(),
        super(const VisualSearchState());

  @override
  void emit(VisualSearchState state) {
    if (isClosed) return;
    super.emit(state);
  }

  Future<void> pickFromCamera() => _pick(VisualSearchImageSource.camera);

  Future<void> pickFromGallery() => _pick(VisualSearchImageSource.gallery);

  Future<void> _pick(VisualSearchImageSource source) async {
    if (state.isScanning) return;

    try {
      final image = await _imagePicker.pick(source);
      if (image == null) return;
      if (!_isValidImage(image)) return;

      emit(state.copyWith(
        selectedImageBytes: image.bytes,
        selectedImageName: image.fileName,
        source: source,
        hasSearched: false,
        matchedProducts: const [],
        clearError: true,
      ));
    } on PlatformException {
      final sourceName =
          source == VisualSearchImageSource.camera ? 'الكاميرا' : 'الاستديو';
      emit(state.copyWith(
        errorMessage:
            'تعذر الوصول إلى $sourceName. يمكنك السماح بالوصول من إعدادات التطبيق.',
      ));
    }
  }

  bool _isValidImage(VisualSearchSelectedImage image) {
    if (image.bytes.isEmpty) {
      emit(state.copyWith(errorMessage: 'تعذر قراءة ملف الصورة المختار.'));
      return false;
    }
    if (image.bytes.lengthInBytes > _maxUploadBytes) {
      emit(state.copyWith(
        errorMessage: 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت.',
      ));
      return false;
    }
    if (image.mimeType != null &&
        !_acceptedMimeTypes.contains(image.mimeType!.toLowerCase())) {
      emit(state.copyWith(
        errorMessage: 'الصيغ المدعومة هي JPEG وPNG وWEBP.',
      ));
      return false;
    }
    return true;
  }

  Future<void> searchSelectedImage() async {
    final bytes = state.selectedImageBytes;
    final fileName = state.selectedImageName;
    if (bytes == null || fileName == null || state.isScanning) return;

    emit(state.copyWith(
      isScanning: true,
      hasSearched: true,
      matchedProducts: const [],
      clearError: true,
    ));

    try {
      final result = await _repository.searchByImage(
        imageBytes: bytes,
        fileName: fileName,
      );
      emit(state.copyWith(
        isScanning: false,
        matchedProducts:
            result.items.map(ProductModel.fromApiListItem).toList(),
        clearError: true,
      ));
    } on ApiFailure catch (failure) {
      emit(state.copyWith(
        isScanning: false,
        errorMessage: failure.message,
      ));
    }
  }

  void reset() => emit(const VisualSearchState());
}
