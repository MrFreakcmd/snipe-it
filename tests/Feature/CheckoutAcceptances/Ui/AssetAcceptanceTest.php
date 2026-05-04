<?php

namespace Tests\Feature\CheckoutAcceptances\Ui;

use App\Events\CheckoutAccepted;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\CustomField;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AcceptanceItemAcceptedNotification;
use App\Notifications\AcceptanceItemAcceptedToUserNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AssetAcceptanceTest extends TestCase
{
    public function test_asset_checkout_accept_page_renders()
    {
        $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();

        $this->actingAs($checkoutAcceptance->assignedTo)
            ->get(route('account.accept.item', $checkoutAcceptance))
            ->assertViewIs('account.accept.create');
    }

    public function test_cannot_accept_asset_already_accepted()
    {
        Event::fake([CheckoutAccepted::class]);

        $checkoutAcceptance = CheckoutAcceptance::factory()->accepted()->create();

        $this->assertFalse($checkoutAcceptance->isPending());

        $this->actingAs($checkoutAcceptance->assignedTo)
            ->post(route('account.store-acceptance', $checkoutAcceptance), [
                'asset_acceptance' => 'accepted',
                'note' => 'my note',
            ])
            ->assertRedirectToRoute('account.accept')
            ->assertSessionHas('error');

        Event::assertNotDispatched(CheckoutAccepted::class);
    }

    public function test_cannot_accept_asset_for_another_user()
    {
        Event::fake([CheckoutAccepted::class]);

        $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();

        $this->assertTrue($checkoutAcceptance->isPending());

        $anotherUser = User::factory()->create();

        $this->actingAs($anotherUser)
            ->post(route('account.store-acceptance', $checkoutAcceptance), [
                'asset_acceptance' => 'accepted',
                'note' => 'my note',
            ])
            ->assertRedirectToRoute('account.accept')
            ->assertSessionHas('error');

        $this->assertTrue($checkoutAcceptance->fresh()->isPending());

        Event::assertNotDispatched(CheckoutAccepted::class);
    }

    public function test_user_can_accept_asset()
    {
        Event::fake([CheckoutAccepted::class]);

        $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();

        $this->assertTrue($checkoutAcceptance->isPending());

        $this->actingAs($checkoutAcceptance->assignedTo)
            ->post(route('account.store-acceptance', $checkoutAcceptance), [
                'asset_acceptance' => 'accepted',
                'note' => 'my note',
            ])
            ->assertRedirectToRoute('account.accept')
            ->assertSessionHas('success');

        $checkoutAcceptance->refresh();

        $this->assertFalse($checkoutAcceptance->isPending());
        $this->assertNotNull($checkoutAcceptance->accepted_at);
        $this->assertNull($checkoutAcceptance->declined_at);

        Event::assertDispatched(CheckoutAccepted::class);
    }

    public function test_user_can_accept_asset_with_required_signature()
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for signature image generation.');
        }

        $settings = Setting::query()->firstOrFail();
        $settings->require_accept_signature = 1;
        $settings->save();
        Setting::$_cache = null;

        $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();

        $canvas = imagecreatetruecolor(24, 12);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagesavealpha($canvas, true);
        $ink = imagecolorallocate($canvas, 25, 25, 25);
        imageline($canvas, 2, 10, 21, 2, $ink);

        ob_start();
        imagepng($canvas);
        $signaturePng = (string) ob_get_clean();
        imagedestroy($canvas);

        $signatureOutput = 'data:image/png;base64,'.base64_encode($signaturePng);

        $this->actingAs($checkoutAcceptance->assignedTo)
            ->post(route('account.store-acceptance', $checkoutAcceptance), [
                'asset_acceptance' => 'accepted',
                'note' => 'signed in test',
                'signature_output' => $signatureOutput,
            ])
            ->assertRedirectToRoute('account.accept')
            ->assertSessionHas('success');

        $checkoutAcceptance->refresh();

        $this->assertNotNull($checkoutAcceptance->signature_filename);
        $this->assertNotNull($checkoutAcceptance->stored_eula_file);
    }

    public function test_user_can_decline_asset()
    {
        Event::fake([CheckoutAccepted::class]);

        $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();

        $this->assertTrue($checkoutAcceptance->isPending());

        $this->actingAs($checkoutAcceptance->assignedTo)
            ->post(route('account.store-acceptance', $checkoutAcceptance), [
                'asset_acceptance' => 'declined',
                'note' => 'my note',
            ])
            ->assertRedirectToRoute('account.accept')
            ->assertSessionHas('success');

        $checkoutAcceptance->refresh();

        $this->assertFalse($checkoutAcceptance->isPending());
        $this->assertNull($checkoutAcceptance->accepted_at);
        $this->assertNotNull($checkoutAcceptance->declined_at);

        Event::assertNotDispatched(CheckoutAccepted::class);
    }

    public function test_action_logged_when_accepting_asset()
    {
        $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();

        $this->actingAs($checkoutAcceptance->assignedTo)
            ->post(route('account.store-acceptance', $checkoutAcceptance), [
                'asset_acceptance' => 'accepted',
                'note' => 'my note',
            ]);

        $this->assertTrue(Actionlog::query()
            ->where([
                'action_type' => 'accepted',
                'target_id' => $checkoutAcceptance->assignedTo->id,
                'target_type' => User::class,
                'note' => 'my note',
                'item_type' => Asset::class,
                'item_id' => $checkoutAcceptance->checkoutable->id,
            ])
            ->whereNotNull('action_date')
            ->exists()
        );
    }

    public function test_admin_acceptance_email_contains_custom_fields_marked_show_in_email(): void
    {
        Notification::fake();
        $this->settings->enableAlertEmail();

        $customField = CustomField::factory()->create([
            'name' => 'Cost Center',
            'show_in_email' => '1',
            'field_encrypted' => '0',
        ])->fresh();

        $asset = Asset::factory()->hasMultipleCustomFields([$customField])->create();
        $asset->{$customField->db_column} = 'ENG-42';
        $asset->save();

        $checkoutAcceptance = CheckoutAcceptance::factory()
            ->pending()
            ->for($asset, 'checkoutable')
            ->create();

        $this->actingAs($checkoutAcceptance->assignedTo)
            ->post(route('account.store-acceptance', $checkoutAcceptance), [
                'asset_acceptance' => 'accepted',
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            $checkoutAcceptance,
            function (AcceptanceItemAcceptedNotification $notification) {
                $rendered = $notification->toMail()->render();

                $this->assertStringContainsString('Cost Center', $rendered);
                $this->assertStringContainsString('ENG-42', $rendered);

                return true;
            }
        );
    }

    public function test_admin_acceptance_email_does_not_contain_encrypted_custom_fields(): void
    {
        Notification::fake();
        $this->settings->enableAlertEmail();

        $customField = CustomField::factory()->create([
            'name' => 'SSN',
            'show_in_email' => '1',
            'field_encrypted' => '1',
        ])->fresh();

        $asset = Asset::factory()->hasMultipleCustomFields([$customField])->create();
        $asset->{$customField->db_column} = '123-45-6789';
        $asset->save();

        $checkoutAcceptance = CheckoutAcceptance::factory()
            ->pending()
            ->for($asset, 'checkoutable')
            ->create();

        $this->actingAs($checkoutAcceptance->assignedTo)
            ->post(route('account.store-acceptance', $checkoutAcceptance), [
                'asset_acceptance' => 'accepted',
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            $checkoutAcceptance,
            function (AcceptanceItemAcceptedNotification $notification) {
                $rendered = $notification->toMail()->render();

                $this->assertStringNotContainsString('SSN', $rendered);
                $this->assertStringNotContainsString('123-45-6789', $rendered);

                return true;
            }
        );
    }

    public function test_action_logged_when_declining_asset()
    {
        $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();

        $this->actingAs($checkoutAcceptance->assignedTo)
            ->post(route('account.store-acceptance', $checkoutAcceptance), [
                'asset_acceptance' => 'declined',
                'note' => 'my note',
            ]);

        $this->assertTrue(Actionlog::query()
            ->where([
                'action_type' => 'declined',
                'target_id' => $checkoutAcceptance->assignedTo->id,
                'target_type' => User::class,
                'note' => 'my note',
                'item_type' => Asset::class,
                'item_id' => $checkoutAcceptance->checkoutable->id,
            ])
            ->whereNotNull('action_date')
            ->exists()
        );
    }

    public function test_admin_can_complete_sign_in_place_acceptance_and_is_redirected_to_selected_destination()
    {
        Event::fake([CheckoutAccepted::class]);

        $assignee = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->create();

        $checkoutAcceptance = CheckoutAcceptance::factory()
            ->pending()
            ->for($assignee, 'assignedTo')
            ->for($asset, 'checkoutable')
            ->create();

        $this->actingAs($admin)
            ->withSession([
                'sign_in_place_acceptance_id' => $checkoutAcceptance->id,
                'sign_in_place_item_id' => $asset->id,
                'sign_in_place_resource_type' => 'Assets',
                'redirect_option' => 'target',
                'checkout_to_type' => 'user',
            ])
            ->post(route('account.store-acceptance', $checkoutAcceptance), [
                'asset_acceptance' => 'accepted',
                'note' => 'signed in person',
            ])
            ->assertRedirect(route('users.show', $assignee));

        $checkoutAcceptance->refresh();

        $this->assertNotNull($checkoutAcceptance->accepted_at);
        $this->assertTrue((bool) $checkoutAcceptance->signed_in_place);
        $this->assertSame($admin->id, $checkoutAcceptance->signed_in_place_admin);
        Event::assertDispatched(CheckoutAccepted::class);
    }

    public function test_stale_sign_in_place_post_on_already_accepted_item_redirects_to_intended_destination()
    {
        $assignee = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->create();

        $checkoutAcceptance = CheckoutAcceptance::factory()
            ->accepted()
            ->for($assignee, 'assignedTo')
            ->for($asset, 'checkoutable')
            ->create();

        $this->actingAs($admin)
            ->withSession([
                'sign_in_place' => true,
                'redirect_option' => 'target',
                'checkout_to_type' => 'user',
            ])
            ->post(route('account.store-acceptance', $checkoutAcceptance), [
                'asset_acceptance' => 'accepted',
            ])
            ->assertRedirectToRoute('account.accept')
            ->assertSessionHas('error');
    }

    public function test_stale_sign_in_place_post_with_missing_assignee_does_not_throw_route_error()
    {
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->create();

        $assignee = User::factory()->create();

        $checkoutAcceptance = CheckoutAcceptance::factory()
            ->accepted()
            ->for($assignee, 'assignedTo')
            ->for($asset, 'checkoutable')
            ->create();

        CheckoutAcceptance::whereKey($checkoutAcceptance->id)->update(['assigned_to_id' => null]);

        $this->actingAs($admin)
            ->withSession([
                'sign_in_place' => true,
                'redirect_option' => 'target',
                'checkout_to_type' => 'user',
            ])
            ->post(route('account.store-acceptance', $checkoutAcceptance), [
                'asset_acceptance' => 'accepted',
            ])
            ->assertRedirectToRoute('account.accept')
            ->assertSessionHas('error');
    }

    public function test_sign_in_place_acceptance_page_uses_checkout_flow_breadcrumbs()
    {
        $assignee = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->create();

        $checkoutAcceptance = CheckoutAcceptance::factory()
            ->pending()
            ->for($assignee, 'assignedTo')
            ->for($asset, 'checkoutable')
            ->create();

        $response = $this->actingAs($admin)
            ->withSession([
                'sign_in_place_acceptance_id' => $checkoutAcceptance->id,
                'sign_in_place_item_id' => $asset->id,
                'sign_in_place_resource_type' => 'Assets',
            ])
            ->get(route('account.accept.item', $checkoutAcceptance));

        $response->assertOk()
            ->assertSeeInOrder([
                trans('general.assets'),
                $asset->display_name,
                trans('general.checkout'),
                sprintf('%s for %s', trans('general.sign_in_place'), $assignee->display_name),
            ], false)
            ->assertSee(route('hardware.index'), false)
            ->assertSee(route('hardware.show', $asset), false)
            ->assertDontSee(route('users.show', $assignee), false)
            ->assertDontSee(route('hardware.checkout.create', $asset), false);
    }

    public function test_acceptance_create_page_shows_email_info_when_always_send_email_enabled()
    {
        $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();
        config(['app.always_send_email' => true]);

        $response = $this->actingAs($checkoutAcceptance->assignedTo)
            ->get(route('account.accept.item', $checkoutAcceptance));

        $response->assertOk();
        $response->assertSee(trans('general.acceptance_email_always_sent'), false);
        $response->assertDontSee(trans('mail.send_pdf_copy'), false);
    }

    public function test_acceptance_create_page_shows_checkbox_when_always_send_email_disabled()
    {
        $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();
        config(['app.always_send_email' => false]);

        $response = $this->actingAs($checkoutAcceptance->assignedTo)
            ->get(route('account.accept.item', $checkoutAcceptance));

        $response->assertOk();
        $response->assertSee(trans('mail.send_pdf_copy'), false);
        $response->assertDontSee(trans('general.acceptance_email_always_sent'), false);
    }

    public function test_acceptance_create_page_hides_checkbox_when_always_send_eula_enabled(): void
    {
        $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();
        config([
            'app.always_send_email' => false,
            'app.always_send_eula' => true,
        ]);

        $response = $this->actingAs($checkoutAcceptance->assignedTo)
            ->get(route('account.accept.item', $checkoutAcceptance));

        $response->assertOk();
        $response->assertSee(trans('general.acceptance_email_always_sent'), false);
        $response->assertDontSee(trans('mail.send_pdf_copy'), false);
    }

    public function test_acceptance_always_sends_copy_when_always_send_eula_enabled(): void
    {
        Notification::fake();

        $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();
        config([
            'app.always_send_email' => false,
            'app.always_send_eula' => true,
        ]);

        $this->actingAs($checkoutAcceptance->assignedTo)
            ->post(route('account.store-acceptance', $checkoutAcceptance), [
                'asset_acceptance' => 'accepted',
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            $checkoutAcceptance->assignedTo,
            AcceptanceItemAcceptedToUserNotification::class
        );
    }
}
