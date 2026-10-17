import 'dart:typed_data';

import 'package:image_picker/image_picker.dart';

enum VisualSearchImageSource { camera, gallery }

final class VisualSearchSelectedImage {
  final Uint8List bytes;
  final String fileName;
  final String? mimeType;

  const VisualSearchSelectedImage({
    required this.bytes,
    required this.fileName,
    this.mimeType,
  });
}

abstract interface class VisualSearchImagePicker {
  Future<VisualSearchSelectedImage?> pick(VisualSearchImageSource source);
}

final class ImagePickerVisualSearchImagePicker
    implements VisualSearchImagePicker {
  final ImagePicker _picker;

  ImagePickerVisualSearchImagePicker({ImagePicker? picker})
      : _picker = picker ?? ImagePicker();

  @override
  Future<VisualSearchSelectedImage?> pick(
      VisualSearchImageSource source) async {
    final image = await _picker.pickImage(
      source: source == VisualSearchImageSource.camera
          ? ImageSource.camera
          : ImageSource.gallery,
      maxWidth: 2000,
      maxHeight: 2000,
      imageQuality: 85,
    );
    if (image == null) return null;

    return VisualSearchSelectedImage(
      bytes: await image.readAsBytes(),
      fileName: image.name,
      mimeType: image.mimeType,
    );
  }
}
