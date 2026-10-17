<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\ReportFilterRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportFilterValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(array $query): ReportFilterRequest
    {
        $request = ReportFilterRequest::create('/admin/reports/test', 'GET', $query);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        return $request;
    }

    public function test_valid_preset_filters_produce_a_report_filter_data(): void
    {
        $request = $this->makeRequest(['preset' => 'today']);
        $request->validateResolved();

        $data = $request->toData();

        $this->assertSame('today', $data->range->startAt->isToday() ? 'today' : 'not-today');
        $this->assertSame('id', $data->sort);
        $this->assertSame('desc', $data->direction);
        $this->assertSame(20, $data->perPage);
    }

    public function test_custom_range_with_valid_dates_is_accepted(): void
    {
        $request = $this->makeRequest(['preset' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-10']);
        $request->validateResolved();

        $data = $request->toData();

        $this->assertSame('2026-09-01 00:00:00', $data->range->startAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-11 00:00:00', $data->range->endAt->format('Y-m-d H:i:s'));
    }

    public function test_invalid_preset_value_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $request = $this->makeRequest(['preset' => 'not-a-real-preset']);
        $request->validateResolved();
    }

    public function test_invalid_currency_id_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $request = $this->makeRequest(['currency_id' => 999999]);
        $request->validateResolved();
    }

    public function test_excessive_per_page_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $request = $this->makeRequest(['per_page' => 500]);
        $request->validateResolved();
    }

    public function test_injected_sort_value_with_sql_syntax_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $request = $this->makeRequest(['sort' => 'id; DROP TABLE orders;--']);
        $request->validateResolved();
    }

    public function test_invalid_direction_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $request = $this->makeRequest(['direction' => 'sideways']);
        $request->validateResolved();
    }
}
