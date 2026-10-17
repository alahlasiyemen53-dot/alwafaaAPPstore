<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Models\SupportConversation;
use App\Services\Auth\CustomerJwtService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\SupportChatPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerSupportChatApiTest extends TestCase
{
    use RefreshDatabase;

    private CustomerJwtService $jwt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        $this->seed(SupportChatPermissionsSeeder::class);
        $this->jwt = app(CustomerJwtService::class);
    }

    private function makeCustomer(string $phone = '779990001'): Customer
    {
        return Customer::query()->create([
            'name' => 'عميل دردشة',
            'phone' => $phone,
            'normalized_phone' => '+967'.$phone,
            'is_active' => true,
            'phone_verified_at' => now(),
        ]);
    }

    public function test_customer_can_create_conversation_and_send_idempotent_message(): void
    {
        $customer = $this->makeCustomer('779990001');
        $token = $this->jwt->generateAccessToken($customer);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/support/conversations', [])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $conversationId = $create->json('data.id');
        $this->assertNotEmpty($conversationId);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/support/conversations/{$conversationId}/messages", [
                'body' => 'مرحباً، أحتاج مساعدة',
                'client_message_id' => 'client-msg-1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.text', 'مرحباً، أحتاج مساعدة');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/support/conversations/{$conversationId}/messages", [
                'body' => 'مرحباً، أحتاج مساعدة',
                'client_message_id' => 'client-msg-1',
            ])
            ->assertCreated();

        $conversation = SupportConversation::query()->where('uuid', $conversationId)->firstOrFail();
        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_customer_reuses_open_conversation_instead_of_creating_duplicates(): void
    {
        $customer = $this->makeCustomer('779990005');
        $token = $this->jwt->generateAccessToken($customer);

        $first = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/support/conversations', [])
            ->assertCreated()
            ->json('data.id');

        $second = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/support/conversations', [])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, SupportConversation::query()->where('customer_id', $customer->id)->count());
    }

    public function test_customer_cannot_access_another_customers_conversation(): void
    {
        $owner = $this->makeCustomer('779990002');
        $intruder = $this->makeCustomer('779990003');
        $conversation = SupportConversation::query()->create([
            'customer_id' => $owner->id,
        ]);

        $token = $this->jwt->generateAccessToken($intruder);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/support/conversations/'.$conversation->uuid)
            ->assertStatus(422);
    }

    public function test_closed_conversation_rejects_customer_messages(): void
    {
        $customer = $this->makeCustomer('779990004');
        $conversation = SupportConversation::query()->create([
            'customer_id' => $customer->id,
            'status' => 'closed',
        ]);
        $token = $this->jwt->generateAccessToken($customer);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/support/conversations/'.$conversation->uuid.'/messages', [
                'body' => 'بعد الإغلاق',
            ])
            ->assertStatus(422);
    }
}
