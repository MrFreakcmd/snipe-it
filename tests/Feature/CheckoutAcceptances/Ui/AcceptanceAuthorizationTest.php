<?php

namespace Tests\Feature\CheckoutAcceptances\Ui;

use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\User;
use Tests\TestCase;

class AcceptanceAuthorizationTest extends TestCase
{
    public function test_assigned_user_can_accept()
    {
        $assignee = User::factory()->create();
        $asset = Asset::factory()->create();
        $acceptance = CheckoutAcceptance::factory()->pending()->for($assignee, 'assignedTo')->for($asset, 'checkoutable')->create();

        $this->actingAs($assignee)
            ->post(route('account.store-acceptance', $acceptance), [
                'asset_acceptance' => 'accepted',
                'note' => 'ok',
            ])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($acceptance->fresh()->accepted_at);
    }

    public function test_other_user_cannot_accept()
    {
        $other = User::factory()->create();
        $assignee = User::factory()->create();
        $asset = Asset::factory()->create();
        $acceptance = CheckoutAcceptance::factory()->pending()->for($assignee, 'assignedTo')->for($asset, 'checkoutable')->create();

        $response = $this->actingAs($other)
            ->post(route('account.store-acceptance', $acceptance), [
                'asset_acceptance' => 'accepted',
                'note' => 'no',
            ]);
        $response->assertRedirectToRoute('account.accept');
        $response->assertSessionHas('error');
        $this->assertNull($acceptance->fresh()->accepted_at);
    }
}
