<?php

namespace Tests\Feature;

use App\Enums\SupportConversationStatus;
use App\Models\Customer;
use App\Models\Role;
use App\Models\SupportChatMessage;
use App\Models\SupportConversation;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\SupportChatPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminSupportChatWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        $this->seed(SupportChatPermissionsSeeder::class);
    }

    public function test_admin_can_access_support_chat_index(): void
    {
        $admin = $this->userWithRole('admin');

        $customer = Customer::create([
            'name' => 'عميل الدعم',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'email' => 'customer@lamma.test',
        ]);

        $conversation = SupportConversation::create([
            'uuid' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'status' => SupportConversationStatus::Open,
            'last_message_at' => now(),
            'last_message_preview' => 'مرحباً، أحتاج مساعدة في طلبي.',
        ]);

        SupportChatMessage::create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'type' => 'text',
            'body' => 'مرحباً، أحتاج مساعدة في طلبي.',
            'client_message_id' => 'msg-1',
        ]);

        $response = $this->actingAs($admin)->get('/admin/support-chat');
        $response->assertOk();
        $response->assertSee('محادثات الدعم المباشر');
        $response->assertSee('عميل الدعم');
        $response->assertSee('ردود سريعة');
        $response->assertSee('معلومات العميل والطلب');
    }

    public function test_admin_can_fetch_live_messages_and_send_reply(): void
    {
        $admin = $this->userWithRole('admin');

        $customer = Customer::create([
            'name' => 'علي صالح',
            'phone' => '779998877',
            'normalized_phone' => '+967779998877',
        ]);

        $conversation = SupportConversation::create([
            'uuid' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'status' => SupportConversationStatus::Open,
            'last_message_at' => now(),
            'last_message_preview' => 'أين وصل المندوب؟',
        ]);

        $liveResponse = $this->actingAs($admin)->getJson("/admin/support-chat/{$conversation->uuid}/live");
        $liveResponse->assertOk();
        $liveResponse->assertJsonStructure([
            'conversation' => [
                'uuid',
                'customer_name',
                'customer_phone',
                'customer_whatsapp_url',
                'status',
            ],
            'messages',
            'actions',
            'unread_count',
        ]);

        // Send reply
        $replyResponse = $this->actingAs($admin)->postJson("/admin/support-chat/{$conversation->uuid}/reply", [
            'body' => 'أهلاً بك، المندوب في طريقه إليك الآن.',
        ]);
        $replyResponse->assertOk();

        $this->assertDatabaseHas('support_chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_type' => 'admin',
            'body' => 'أهلاً بك، المندوب في طريقه إليك الآن.',
        ]);
    }

    public function test_admin_can_assign_and_close_conversation(): void
    {
        $admin = $this->userWithRole('admin');

        $customer = Customer::create([
            'name' => 'فاطمة خالد',
            'phone' => '773322110',
            'normalized_phone' => '+967773322110',
        ]);

        $conversation = SupportConversation::create([
            'uuid' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'status' => SupportConversationStatus::Open,
        ]);

        // Assign to me
        $assignResponse = $this->actingAs($admin)->postJson("/admin/support-chat/{$conversation->uuid}/assign");
        $assignResponse->assertOk();
        $this->assertSame($admin->id, $conversation->fresh()->assigned_admin_id);
        $this->assertSame(SupportConversationStatus::Assigned, $conversation->fresh()->status);

        // Close conversation
        $closeResponse = $this->actingAs($admin)->postJson("/admin/support-chat/{$conversation->uuid}/close");
        $closeResponse->assertOk();
        $this->assertSame(SupportConversationStatus::Closed, $conversation->fresh()->status);

        // Reopen
        $reopenResponse = $this->actingAs($admin)->postJson("/admin/support-chat/{$conversation->uuid}/reopen");
        $reopenResponse->assertOk();
        $this->assertSame(SupportConversationStatus::Assigned, $conversation->fresh()->status);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
