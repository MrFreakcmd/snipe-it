<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->backfillPermissionsForTable('users');
        $this->backfillPermissionsForTable('permission_groups');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a data backfill migration; no rollback is performed to avoid removing intentional permission changes.
    }

    private function backfillPermissionsForTable(string $table): void
    {
        DB::table($table)
            ->select(['id', 'permissions'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $permissions = json_decode((string) ($row->permissions ?? '{}'), true);

                    if (! is_array($permissions)) {
                        continue;
                    }

                    if (array_key_exists('users.contact', $permissions)) {
                        continue;
                    }

                    $editPermission = $permissions['users.edit'] ?? null;

                    if (! $this->isGrantedPermission($editPermission)) {
                        continue;
                    }

                    // Preserve the existing permission value style (string/int/bool) where possible.
                    $permissions['users.contact'] = $editPermission;

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([
                            'permissions' => json_encode($permissions),
                        ]);
                }
            });
    }

    private function isGrantedPermission(mixed $permissionValue): bool
    {
        return in_array($permissionValue, [1, '1', true], true);
    }
};

