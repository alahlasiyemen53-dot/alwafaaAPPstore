<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class VerifyProductionConfigCommand extends Command
{
    protected $signature = 'app:verify-production-config';

    protected $description = 'التحقق من إعدادات الإنتاج الحرجة قبل النشر (يُشغَّل يدوياً/ضمن CI، وليس تلقائياً وقت التشغيل)';

    public function handle(): int
    {
        $failures = [];

        if (app()->environment('production')) {
            if (config('app.debug') === true) {
                $failures[] = 'APP_DEBUG=true في بيئة الإنتاج — يجب تعيينه إلى false.';
            }

            if (! config('session.secure')) {
                $failures[] = 'SESSION_SECURE_COOKIE غير مفعّل في بيئة الإنتاج — يجب تعيينه إلى true (يتطلب HTTPS).';
            }

            if (! str_starts_with((string) config('app.url'), 'https://')) {
                $failures[] = 'APP_URL لا يبدأ بـ https:// في بيئة الإنتاج.';
            }

            if (config('queue.default') !== 'redis') {
                $failures[] = 'QUEUE_CONNECTION يجب أن يكون redis في بيئة الإنتاج (تم إلغاء طابور database).';
            }

            if (config('cors.allowed_origins') === ['http://localhost:3000']) {
                $failures[] = 'CORS_ALLOWED_ORIGINS ما زال على القيمة الافتراضية للتطوير.';
            }
        }

        if (empty($failures)) {
            $this->info('اجتاز فحص إعدادات الإنتاج بنجاح.');

            return self::SUCCESS;
        }

        $this->error('فشل فحص إعدادات الإنتاج:');
        foreach ($failures as $failure) {
            $this->line('- '.$failure);
        }

        return self::FAILURE;
    }
}
