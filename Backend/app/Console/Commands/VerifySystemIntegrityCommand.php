<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DTOs\ReportDateRangeData;
use App\Queries\Reporting\FinancialIntegrityQuery;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class VerifySystemIntegrityCommand extends Command
{
    protected $signature = 'app:verify-integrity {--days=30 : عدد الأيام السابقة لفحص النطاق المحاسبي}';

    protected $description = 'فحص ومطابقة تكامل البيانات المالية والمخزنية وعمليات COD ومحافظ العملاء عبر الأستاذ العام';

    public function handle(FinancialIntegrityQuery $query): int
    {
        $this->info('بدء فحص تكامل النظام عبر السجلات المحاسبية والمخزنية...');

        $days = (int) $this->option('days');
        $now = CarbonImmutable::now();
        $range = new ReportDateRangeData(
            startAt: $now->subDays(max(1, $days))->startOfDay(),
            endAt: $now->startOfDay()->addDay(),
        );

        $results = $query->execute($range);
        $hasErrors = false;

        $this->newLine();
        $this->line('<fg=cyan>1. ميزان المراجعة والأستاذ العام (Trial Balance & GL):</>');
        if ($results['trial_balance']['is_balanced']) {
            $this->line('   <fg=green>✓ متزن: مجموع المدين يساوي الدائن تماماً (الفرق: '.$results['trial_balance']['difference'].')</>');
        } else {
            $this->line('   <fg=red>✗ غير متزن: يوجد فارق محاسبي قدره ('.$results['trial_balance']['difference'].')</>');
            $hasErrors = true;
        }

        $this->newLine();
        $this->line('<fg=cyan>2. مطابقة حسابات الخزينة مع الأستاذ العام (Treasury Isolation):</>');
        if ($results['treasury']['status'] === 'OK') {
            $this->line('   <fg=green>✓ متطابق: لا توجد قيود خزينة بعملات غير مطابقة لعملة الحساب</>');
        } else {
            $this->line('   <fg=red>✗ يوجد عدد ('.$results['treasury']['mismatched_currency_lines'].') سطور بعملات غير مطابقة</>');
            $hasErrors = true;
        }

        $this->newLine();
        $this->line('<fg=cyan>3. مطابقة متحصلات الدفع عند الاستلام (Driver COD Custody):</>');
        if ($results['cod']['status'] === 'MATCHED') {
            $this->line('   <fg=green>✓ متطابق: مبالغ COD المحصلة مطابقة للمسددة والمتبقية كعهدة</>');
        } else {
            $this->line('   <fg=red>✗ يوجد عدم تطابق في عهدة السائقين أو تسويات COD</>');
            $hasErrors = true;
        }

        $this->newLine();
        $this->line('<fg=cyan>4. مطابقة أرصدة المخزون وحركاته (Inventory & Movements):</>');
        if ($results['inventory']['status'] === 'OK') {
            $this->line('   <fg=green>✓ متطابق: رصيد المخزون الفعلي يطابق صافي الحركات الواردة والصادرة</>');
        } else {
            $this->line('   <fg=red>✗ يوجد عدد ('.$results['inventory']['mismatched_positions'].') أصناف بها فروقات مخزنية</>');
            $hasErrors = true;
        }

        $this->newLine();
        $this->line('<fg=cyan>5. مطابقة التزامات محافظ العملاء مع حساب الخصوم (Customer Wallet Liability):</>');
        if ($results['wallet']['status'] === 'OK') {
            $this->line('   <fg=green>✓ متطابق: رصيد محافظ العملاء ('.$results['wallet']['wallet_ledger_total'].') يطابق حساب الخصوم ('.$results['wallet']['gl_liability_total'].')</>');
        } else {
            $this->line('   <fg=red>✗ يوجد فارق بين رصيد المحافظ ('.$results['wallet']['wallet_ledger_total'].') وحساب الأستاذ العام ('.$results['wallet']['gl_liability_total'].')</>');
            $hasErrors = true;
        }

        $this->newLine();
        $this->line('<fg=cyan>6. مطابقة استخدام الكوبونات مع الطلبات المؤكدة (Coupon Usages):</>');
        if ($results['coupons']['status'] === 'OK') {
            $this->line('   <fg=green>✓ متطابق: سجلات استخدام الكوبونات مطابقة للطلبات المؤكدة المؤهلة ('.$results['coupons']['active_usages'].')</>');
        } else {
            $this->line('   <fg=red>✗ استخدامات الكوبونات النشطة ('.$results['coupons']['active_usages'].') لا تطابق الطلبات المؤكدة ('.$results['coupons']['eligible_confirmed_orders'].')</>');
            $hasErrors = true;
        }

        $this->newLine();
        if ($hasErrors) {
            $this->error('فشل فحص التكامل: تم العثور على تباين في بعض القطاعات أعلاه.');

            return self::FAILURE;
        }

        $this->info('اكتمل فحص التكامل بنجاح: جميع السجلات متطابقة ومتسقة 100%.');

        return self::SUCCESS;
    }
}
