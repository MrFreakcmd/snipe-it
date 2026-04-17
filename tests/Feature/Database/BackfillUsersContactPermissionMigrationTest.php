<?php

namespace Tests\Feature\Database;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillUsersContactPermissionMigrationTest extends TestCase
{
    public function test_migration_backfills_users_contact_permission_for_users_and_groups_with_users_edit(): void
    {
        $userWithEdit = User::factory()->create([
            'permissions' => json_encode([
                'users.edit' => '1',
            ]),
        ]);

        $userWithoutEdit = User::factory()->create([
            'permissions' => json_encode([
                'users.view' => '1',
            ]),
        ]);

        $groupWithEdit = Group::factory()->create([
            'permissions' => json_encode([
                'users.edit' => 1,
            ]),
        ]);

        $groupWithoutEdit = Group::factory()->create([
            'permissions' => json_encode([
                'users.view' => 1,
            ]),
        ]);

        $this->runBackfillMigration();

        $this->assertSame('1', (string) $this->permissionForUser($userWithEdit->id, 'users.contact'));
        $this->assertNull($this->permissionForUser($userWithoutEdit->id, 'users.contact'));

        $this->assertSame('1', (string) $this->permissionForGroup($groupWithEdit->id, 'users.contact'));
        $this->assertNull($this->permissionForGroup($groupWithoutEdit->id, 'users.contact'));
    }

    public function test_migration_does_not_override_existing_users_contact_permission(): void
    {
        $user = User::factory()->create([
            'permissions' => json_encode([
                'users.edit' => '1',
                'users.contact' => '-1',
            ]),
        ]);

        $group = Group::factory()->create([
            'permissions' => json_encode([
                'users.edit' => 1,
                'users.contact' => -1,
            ]),
        ]);

        $this->runBackfillMigration();

        $this->assertSame('-1', (string) $this->permissionForUser($user->id, 'users.contact'));
        $this->assertSame('-1', (string) $this->permissionForGroup($group->id, 'users.contact'));
    }

    private function runBackfillMigration(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_04_17_120000_backfill_users_contact_permission_from_users_edit.php');
        $migration->up();
    }

    private function permissionForUser(int $userId, string $permission): mixed
    {
        $permissions = json_decode((string) DB::table('users')->where('id', $userId)->value('permissions'), true);

        return is_array($permissions) ? ($permissions[$permission] ?? null) : null;
    }

    private function permissionForGroup(int $groupId, string $permission): mixed
    {
        $permissions = json_decode((string) DB::table('permission_groups')->where('id', $groupId)->value('permissions'), true);

        return is_array($permissions) ? ($permissions[$permission] ?? null) : null;
    }
}
