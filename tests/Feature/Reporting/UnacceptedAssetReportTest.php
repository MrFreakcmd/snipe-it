<?php

namespace Tests\Feature\Reporting;

use App\Models\CheckoutAcceptance;
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
        $this->actingAs(User::factory()->canViewReports()->create())
            ->get(route('reports/unaccepted_items'))
            ->assertOk();
    }

    public function test_regular_user_cannot_perform_reminder_or_delete()
    {
        $user = User::factory()->canViewReports()->create();
        $acceptance = CheckoutAcceptance::factory()->pending()->create();
        $this->actingAs($user)
            ->post(route('reports/unaccepted_items_sent_reminder'), ['acceptance_id' => $acceptance->id])
            ->assertForbidden();
        $this->actingAs($user)
            ->delete(route('reports/unaccepted_items_delete', $acceptance->id))
            ->assertForbidden();
    }

    public function test_admin_can_perform_reminder_and_delete()
    {
        $admin = User::factory()->admin()->canViewReports()->create();
        $acceptance = CheckoutAcceptance::factory()->pending()->create();
        $this->actingAs($admin)
            ->post(route('reports/unaccepted_items_sent_reminder'), ['acceptance_id' => $acceptance->id])
            ->assertStatus(302); // Or whatever is appropriate (redirect, etc)
        $this->actingAs($admin)
            ->delete(route('reports/unaccepted_items_delete', $acceptance->id))
            ->assertStatus(302);
    }

    public function test_superuser_can_perform_reminder_and_delete()
    {
        $superuser = User::factory()->superuser()->canViewReports()->create();
        $acceptance = CheckoutAcceptance::factory()->pending()->create();
        $this->actingAs($superuser)
            ->post(route('reports/unaccepted_items_sent_reminder'), ['acceptance_id' => $acceptance->id])
            ->assertStatus(302);
        $this->actingAs($superuser)
            ->delete(route('reports/unaccepted_items_delete', $acceptance->id))
            ->assertStatus(302);
    }
}
