<?php

namespace Tests\Feature\Reporting;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use League\Csv\Reader;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

class UnacceptedAssetReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestResponse::macro(
            'assertSeeTextInStreamedResponse',
            function (string $needle) {
                Assert::assertTrue(
                    collect(Reader::createFromString($this->streamedContent())->getRecords())
                        ->pluck(0)
                        ->contains($needle)
                );

                return $this;
            }
        );

        TestResponse::macro(
            'assertDontSeeTextInStreamedResponse',
            function (string $needle) {
                Assert::assertFalse(
                    collect(Reader::createFromString($this->streamedContent())->getRecords())
                        ->pluck(0)
                        ->contains($needle)
                );

                return $this;
            }
        );
    }

    public function test_permission_required_to_view_unaccepted_asset_report()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports/unaccepted_items'))
            ->assertForbidden();
    }

    public function test_user_can_list_unaccepted_assets()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('reports/unaccepted_items'))
            ->assertOk();
    }

    public function test_regular_user_does_not_see_actions_column_or_buttons()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('reports/unaccepted_items'));
        $response->assertOk();
        $response->assertDontSee('Actions');
        $response->assertDontSee('Send Reminder');
        $response->assertDontSee('Delete');
    }

    public function test_admin_sees_actions_column_and_buttons()
    {
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)
            ->get(route('reports/unaccepted_items'));
        $response->assertOk();
        $response->assertSee('Actions');
        $response->assertSee('Send Reminder');
        $response->assertSee('Delete');
    }

    public function test_superuser_sees_actions_column_and_buttons()
    {
        $superuser = User::factory()->superuser()->create();
        $response = $this->actingAs($superuser)
            ->get(route('reports/unaccepted_items'));
        $response->assertOk();
        $response->assertSee('Actions');
        $response->assertSee('Send Reminder');
        $response->assertSee('Delete');
    }

    public function test_regular_user_cannot_perform_reminder_or_delete()
    {
        $user = User::factory()->create();
        $acceptanceId = 1; // Use a valid acceptance ID in your test DB or factory
        $this->actingAs($user)
            ->post(route('reports/unaccepted_items_sent_reminder'), ['acceptance_id' => $acceptanceId])
            ->assertForbidden();
        $this->actingAs($user)
            ->delete(route('reports/unaccepted_items_delete', $acceptanceId))
            ->assertForbidden();
    }

    public function test_admin_can_perform_reminder_and_delete()
    {
        $admin = User::factory()->admin()->create();
        $acceptanceId = 1; // Use a valid acceptance ID in your test DB or factory
        $this->actingAs($admin)
            ->post(route('reports/unaccepted_items_sent_reminder'), ['acceptance_id' => $acceptanceId])
            ->assertStatus(302); // Or whatever is appropriate (redirect, etc)
        $this->actingAs($admin)
            ->delete(route('reports/unaccepted_items_delete', $acceptanceId))
            ->assertStatus(302);
    }

    public function test_superuser_can_perform_reminder_and_delete()
    {
        $superuser = User::factory()->superuser()->create();
        $acceptanceId = 1; // Use a valid acceptance ID in your test DB or factory
        $this->actingAs($superuser)
            ->post(route('reports/unaccepted_items_sent_reminder'), ['acceptance_id' => $acceptanceId])
            ->assertStatus(302);
        $this->actingAs($superuser)
            ->delete(route('reports/unaccepted_items_delete', $acceptanceId))
            ->assertStatus(302);
    }
}
