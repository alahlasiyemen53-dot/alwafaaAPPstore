/// Compact Arabic relative timestamps for chat UI.
String relativeTimeAr(DateTime? dateTime, {DateTime? now}) {
  if (dateTime == null) return '';
  final anchor = now ?? DateTime.now();
  final local = dateTime.isUtc ? dateTime.toLocal() : dateTime;
  var diff = anchor.difference(local);
  if (diff.isNegative) diff = Duration.zero;

  if (diff.inSeconds < 45) return 'الآن';
  if (diff.inMinutes < 1) return 'منذ أقل من دقيقة';
  if (diff.inMinutes < 60) {
    final m = diff.inMinutes;
    if (m == 1) return 'منذ دقيقة';
    if (m == 2) return 'منذ دقيقتين';
    if (m >= 3 && m <= 10) return 'منذ $m دقائق';
    return 'منذ $m دقيقة';
  }
  if (diff.inHours < 24) {
    final h = diff.inHours;
    if (h == 1) return 'منذ ساعة';
    if (h == 2) return 'منذ ساعتين';
    if (h >= 3 && h <= 10) return 'منذ $h ساعات';
    return 'منذ $h ساعة';
  }
  if (diff.inDays < 7) {
    final d = diff.inDays;
    if (d == 1) return 'أمس';
    if (d == 2) return 'منذ يومين';
    if (d >= 3 && d <= 10) return 'منذ $d أيام';
    return 'منذ $d يوم';
  }
  final weeks = diff.inDays ~/ 7;
  if (weeks < 5) {
    if (weeks == 1) return 'منذ أسبوع';
    if (weeks == 2) return 'منذ أسبوعين';
    return 'منذ $weeks أسابيع';
  }
  return '${local.day}/${local.month}/${local.year}';
}
