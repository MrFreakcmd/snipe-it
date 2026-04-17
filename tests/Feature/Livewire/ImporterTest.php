<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Importer;
use App\Models\Import;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class ImporterTest extends TestCase
{
    public function test_renders_successfully()
    {
        Livewire::actingAs(User::factory()->canImport()->create())
            ->test(Importer::class)
            ->assertStatus(200);
    }

    public function test_requires_permission()
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Importer::class)
            ->assertStatus(403);
    }

    public function test_shows_user_import_contact_warning_when_actor_lacks_contact_permission(): void
    {
        $import = Import::factory()->users()->create();

        Livewire::actingAs(User::factory()->canImport()->create())
            ->test(Importer::class)
            ->call('selectFile', $import->id)
            ->set('typeOfImport', 'user')
            ->assertSee(trans('admin/users/general.import_contact_fields_permission_warning'));
    }

    public function test_hides_user_import_contact_warning_when_actor_has_contact_permission(): void
    {
        $import = Import::factory()->users()->create();

        Livewire::actingAs(User::factory()->canImport()->manageContactInfo()->create())
            ->test(Importer::class)
            ->call('selectFile', $import->id)
            ->set('typeOfImport', 'user')
            ->assertDontSee(trans('admin/users/general.import_contact_fields_permission_warning'));
    }
}
