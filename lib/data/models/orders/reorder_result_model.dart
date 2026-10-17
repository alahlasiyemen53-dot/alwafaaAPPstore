import 'package:equatable/equatable.dart';

class ReorderResultModel extends Equatable {
  final int addedCount;
  final int skippedCount;
  final String? message;

  const ReorderResultModel({
    required this.addedCount,
    required this.skippedCount,
    this.message,
  });

  bool get isFullSuccess => addedCount > 0 && skippedCount == 0;
  bool get isPartialSuccess => addedCount > 0 && skippedCount > 0;
  bool get isFailure => addedCount <= 0;

  factory ReorderResultModel.fromJson(
    Map<String, dynamic> json, {
    String? message,
  }) {
    return ReorderResultModel(
      addedCount: json['added_count'] as int? ?? 0,
      skippedCount: json['skipped_count'] as int? ?? 0,
      message: message,
    );
  }

  @override
  List<Object?> get props => [addedCount, skippedCount, message];
}
