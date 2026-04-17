<?php

namespace Tests\Feature\Users\Ui;

use App\Models\Company;
use App\Models\Group;
use App\Models\User;
use Tests\TestCase;

class ViewUserTest extends TestCase
{
    public function test_requires_permission_to_view_user()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('users.show', User::factory()->create()))
            ->assertStatus(403);
    }

    public function test_can_view_user()
    {
        $actor = User::factory()->viewUsers()->create();

        $this->actingAs($actor)
            ->get(route('users.show', User::factory()->create()))
            ->assertOk()
            ->assertStatus(200);
    }

    public function test_cannot_view_user_from_another_company()
    {
        $this->settings->enableMultipleFullCompanySupport();

        [$companyA, $companyB] = Company::factory()->count(2)->create();

        $actor = User::factory()->for($companyA)->viewUsers()->create();
        $user = User::factory()->for($companyB)->create();

        $this->actingAs($actor)
            ->get(route('users.show', $user))
            ->assertStatus(302);
    }

    public function test_shows_effective_permissions_from_groups_and_individual_permissions()
    {
        $actor = User::factory()->viewUsers()->create();

        $group = Group::factory()->create([
            'permissions' => json_encode([
                'assets.view' => 1,
            ]),
        ]);

        $user = User::factory()->create([
            'permissions' => json_encode([
                'reports.view' => 1,
            ]),
        ]);
        $user->groups()->attach($group->id);

        $this->actingAs($actor)
            ->get(route('users.show', $user))
            ->assertOk()
            ->assertSee('assets.view')
            ->assertSee('reports.view');
    }

    public function test_shows_explicitly_denied_permissions()
    {
        $actor = User::factory()->viewUsers()->create();

        $user = User::factory()->create([
            'permissions' => json_encode([
                'reports.view' => -1,
            ]),
        ]);

        $this->actingAs($actor)
            ->get(route('users.show', $user))
            ->assertOk()
            ->assertSee('reports.view')
            ->assertSee('label-danger', false);
    }

    public function test_hides_email_when_actor_lacks_contact_permission(): void
    {
        $actor = User::factory()->viewUsers()->create();
        $target = User::factory()->create(['email' => 'hidden@example.com']);

        $this->actingAs($actor)
            ->get(route('users.show', $target))
            ->assertOk()
            ->assertDontSee('hidden@example.com');
    }

    public function test_shows_email_when_actor_has_contact_permission(): void
    {
        $actor = User::factory()->viewUsers()->manageContactInfo()->create();
        $target = User::factory()->create(['email' => 'visible@example.com']);

        $this->actingAs($actor)
            ->get(route('users.show', $target))
            ->assertOk()
            ->assertSee('visible@example.com');
    }

    public function test_superuser_can_see_email_on_user_view(): void
    {
        $actor = User::factory()->superuser()->create();
        $target = User::factory()->create(['email' => 'super@example.com']);

        $this->actingAs($actor)
            ->get(route('users.show', $target))
            ->assertOk()
            ->assertSee('super@example.com');
    }

    public function test_hides_website_when_actor_lacks_contact_permission(): void
    {
        $actor = User::factory()->viewUsers()->create();
        $target = User::factory()->create(['website' => 'https://hidden.example.com']);

        $this->actingAs($actor)
            ->get(route('users.show', $target))
            ->assertOk()
            ->assertDontSee('hidden.example.com');
    }

    public function test_superuser_can_see_website_on_user_view(): void
    {
        $actor = User::factory()->superuser()->create();
        $target = User::factory()->create(['website' => 'https://super.example.com']);

        $this->actingAs($actor)
            ->get(route('users.show', $target))
            ->assertOk()
            ->assertSee('super.example.com');
    }
}
