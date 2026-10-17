<?php

namespace App\Http\Controllers;

use App\Contracts\Notifications\PushNotificationSender;
use App\Data\Notifications\ComposeAdminNotificationInput;
use App\Data\Notifications\TestNotificationData;
use App\Enums\NotificationStatus;
use App\Enums\ProductStatus;
use App\Enums\PushDeviceStatus;
use App\Http\Requests\Admin\ComposeAdminNotificationRequest;
use App\Http\Requests\Admin\NotificationLogFilterRequest;
use App\Http\Requests\Admin\SendTestNotificationRequest;
use App\Infrastructure\Notifications\FirebaseCloudMessagingSender;
use App\Jobs\SendPushNotificationJob;
use App\Models\BusinessNotification;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\PushDevice;
use App\Queries\Notifications\NotificationLogQuery;
use App\Services\AuditLogger;
use App\Services\Notifications\AdminNotificationComposer;
use App\Services\Notifications\NotificationMessageFactory;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AdminNotificationWebController extends Controller
{
    public function __construct(
        private readonly NotificationLogQuery $logQuery,
        private readonly NotificationMessageFactory $messageFactory,
        private readonly AdminNotificationComposer $composer,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(NotificationLogFilterRequest $request): View
    {
        $filters = $request->validated();
        $user = $request->user();

        // view_failures alone may only browse failed notifications.
        if ($user && ! $user->hasPermission('admin.notifications.view')
            && $user->hasPermission('admin.notifications.view_failures')) {
            $filters['status'] = NotificationStatus::Failed->value;
        }

        $notifications = $this->logQuery->paginate($filters);

        $sentCount = BusinessNotification::query()->where('status', NotificationStatus::Sent)->count();
        $readCount = BusinessNotification::query()->whereNotNull('read_at')->count();
        $scheduledCount = BusinessNotification::query()->whereNotNull('scheduled_at')->where('status', NotificationStatus::Queued)->count();
        $openRate = $sentCount > 0 ? round(($readCount / $sentCount) * 100, 1) : 0.0;

        $metrics = [
            'total' => BusinessNotification::query()->count(),
            'queued' => BusinessNotification::query()->where('status', NotificationStatus::Queued)->count(),
            'sent' => $sentCount,
            'failed' => BusinessNotification::query()->where('status', NotificationStatus::Failed)->count(),
            'invalid_devices' => PushDevice::query()->where('status', PushDeviceStatus::Invalidated)->count(),
            'read_count' => $readCount,
            'scheduled_count' => $scheduledCount,
            'open_rate' => $openRate,
        ];

        $health = $this->fcmHealth();

        return view('admin.notifications.index', compact('notifications', 'metrics', 'filters', 'health'));
    }

    public function compose(): View
    {
        $customers = Customer::query()
            ->where('is_active', true)
            ->whereHas('pushDevices', fn ($query) => $query->where('status', PushDeviceStatus::Active))
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'phone']);

        $products = Product::query()
            ->where('status', ProductStatus::Active)
            ->orderBy('name_ar')
            ->limit(50)
            ->get(['id', 'name_ar', 'name_en']);

        $collections = ProductCollection::query()
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->limit(50)
            ->get(['id', 'name_ar', 'name_en']);

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->limit(50)
            ->get(['id', 'name_ar', 'name_en']);

        $governorates = \App\Models\Governorate::query()
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en']);

        $generalTopic = (string) config('services.fcm.general_topic', 'lamma_general');
        $segmentStats = $this->composer->getSegmentStatistics();
        $health = $this->fcmHealth();

        return view('admin.notifications.compose', compact(
            'customers',
            'products',
            'collections',
            'categories',
            'governorates',
            'generalTopic',
            'segmentStats',
            'health',
        ));
    }

    public function storeCompose(ComposeAdminNotificationRequest $request): RedirectResponse
    {
        /** @var \App\Models\User $admin */
        $admin = $request->user();

        $sendType = (string) $request->validated('send_type', 'now');
        $scheduledAt = $sendType === 'scheduled' && $request->filled('scheduled_at')
            ? \Carbon\Carbon::parse($request->validated('scheduled_at'))
            : null;

        $result = $this->composer->compose(new ComposeAdminNotificationInput(
            audience: (string) $request->validated('audience'),
            title: (string) $request->validated('title'),
            body: (string) $request->validated('body'),
            customerId: $request->validated('customer_id') !== null
                ? (int) $request->validated('customer_id')
                : null,
            image: $request->file('image'),
            destinationType: $request->validated('destination_type'),
            destinationId: $request->validated('destination_id') !== null
                ? (string) $request->validated('destination_id')
                : null,
            segment: $request->validated('segment'),
            sendType: $sendType,
            scheduledAt: $scheduledAt,
            platform: (string) $request->validated('platform', 'all'),
            governorateId: $request->validated('governorate_id') !== null
                ? (int) $request->validated('governorate_id')
                : null,
            governorateTarget: (string) $request->validated('governorate_target', 'any'),
        ), $admin);

        $this->auditLogger->record('admin.notification.composed', null, metadata: [
            'admin_id' => $admin->id,
            'audience' => $request->validated('audience'),
            'segment' => $request->validated('segment'),
            'customer_id' => $request->validated('customer_id'),
            'destination_type' => $request->validated('destination_type'),
            'destination_id' => $request->validated('destination_id'),
            'platform' => $request->validated('platform', 'all'),
            'governorate_id' => $request->validated('governorate_id'),
            'governorate_target' => $request->validated('governorate_target', 'any'),
            'send_type' => $sendType,
            'scheduled_at' => $scheduledAt?->toIso8601String(),
            'mode' => $result['mode'],
            'topic' => $result['topic'],
            'queued' => $result['queued'],
            'skipped' => $result['skipped'],
            'scheduled' => $result['scheduled'] ?? false,
            'has_image' => $result['image_url'] !== null,
        ]);

        if ($result['queued'] === 0) {
            $error = $result['mode'] === 'topic'
                ? 'فشل إرسال الإشعار العام عبر موضوع FCM. تحقق من إعدادات Firebase.'
                : 'لم يتم جدولة أي إشعار. تأكد من وجود عملاء لديهم أجهزة نشطة ضمن الجمهور المحدد.';

            return redirect()
                ->route('admin.notifications.compose')
                ->withInput()
                ->with('error', $error);
        }

        $isScheduled = ! empty($result['scheduled']);
        if ($result['mode'] === 'topic') {
            $message = $isScheduled
                ? "تمت جدولة إرسال الإشعار العام إلى موضوع {$result['topic']} في موعده المحدد ({$scheduledAt?->format('Y-m-d H:i')})."
                : "تم إرسال الإشعار العام إلى موضوع {$result['topic']} (جميع مستخدمي التطبيق المشتركين).";
        } else {
            $message = $isScheduled
                ? "تمت جدولة {$result['queued']} إشعار/إشعارات للإرسال في الموعد المحدد ({$scheduledAt?->format('Y-m-d H:i')})."
                : "تمت جدولة {$result['queued']} إشعار/إشعارات في طابور notifications.";
        }

        if ($result['skipped'] > 0) {
            $message .= " تم تخطي {$result['skipped']}.";
        }

        return redirect()
            ->route('admin.notifications.index')
            ->with('success', $message);
    }

    public function show(int $id): View
    {
        $notification = BusinessNotification::query()
            ->with(['recipient', 'deliveries.pushDevice', 'deliveries.attempts'])
            ->findOrFail($id);

        return view('admin.notifications.show', compact('notification'));
    }

    public function sendTest(SendTestNotificationRequest $request): RedirectResponse
    {
        $device = PushDevice::query()->with('owner')->findOrFail($request->push_device_id);
        $customer = $device->owner;

        if (! $customer instanceof Customer) {
            throw new DomainException('مالك الجهاز المحدد ليس عميلاً صالحاً.');
        }

        $testData = new TestNotificationData($request->title, $request->body);
        $message = $this->messageFactory->test($testData);

        $notification = new BusinessNotification([
            'type' => $message->type,
            'category' => $message->category,
            'channel' => $message->channel,
            'title' => $message->title,
            'body' => $message->body,
            'payload_version' => (int) ($message->payload['payload_version'] ?? 1),
            'business_reference' => 'TEST-'.Str::upper(Str::random(8)),
            'idempotency_key' => 'test:'.$device->id.':'.now()->timestamp.':'.Str::random(6),
        ]);
        $notification->recipient()->associate($customer);
        $notification->payload = $message->payload;
        $notification->status = NotificationStatus::Queued;
        $notification->queued_at = now();
        $notification->save();

        SendPushNotificationJob::dispatch($notification->id)
            ->onQueue('notifications')
            ->afterCommit();

        $this->auditLogger->record('admin.notification.test_sent', $notification, metadata: [
            'device_id' => $device->id,
            'admin_id' => auth()->id(),
        ]);

        return redirect()->route('admin.notifications.index')
            ->with('success', 'تم إرسال الإشعار التجريبي إلى طابور المعالجة بنجاح.');
    }

    public function retry(int $id): RedirectResponse
    {
        $notification = BusinessNotification::query()->with('deliveries')->findOrFail($id);

        if ($notification->status === NotificationStatus::Sent) {
            return redirect()->back()->with('info', 'هذا الإشعار مرسل بنجاح بالفعل.');
        }

        foreach ($notification->deliveries as $delivery) {
            if ($delivery->status === NotificationStatus::Failed && $delivery->retryable) {
                $delivery->status = NotificationStatus::Pending;
                $delivery->save();
            }
        }

        $notification->status = NotificationStatus::Queued;
        $notification->save();

        SendPushNotificationJob::dispatch($notification->id)
            ->onQueue('notifications');

        $this->auditLogger->record('admin.notification.retry_requested', $notification, metadata: [
            'admin_id' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'تمت إعادة جدولة الإشعار في طابور الإرسال بنجاح.');
    }

    /**
     * @return array{enabled: bool, has_credentials: bool, project_id: bool, sender_ready: bool, queue_connection: mixed, configuration_error: ?string}
     */
    private function fcmHealth(): array
    {
        $credentialsPath = (string) config('services.fcm.credentials_path');
        $senderReady = false;
        $configurationError = null;

        try {
            $sender = app(PushNotificationSender::class);
            if ($sender instanceof FirebaseCloudMessagingSender) {
                $senderReady = $sender->isReady();
                $configurationError = $sender->configurationIssue();
            } else {
                $senderReady = true;
            }
        } catch (\Throwable) {
            $configurationError = 'FCM sender could not be initialized.';
        }

        return [
            'enabled' => (bool) config('services.fcm.enabled', false),
            'has_credentials' => $credentialsPath !== '' && is_readable($credentialsPath),
            'project_id' => ! empty(config('services.fcm.project_id')),
            'sender_ready' => $senderReady,
            'queue_connection' => config('queue.default'),
            'configuration_error' => is_string($configurationError) ? $configurationError : null,
        ];
    }
}
