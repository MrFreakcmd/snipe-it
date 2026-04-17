<?php

namespace Tests\Feature\Users\Ui;

use App\Models\Department;
use App\Models\User;
use League\Csv\Reader;
use Tests\TestCase;

class ExportUsersCsvTest extends TestCase
{
    public function test_department_and_department_manager_columns_are_aligned_in_user_export(): void
    {
        $actor = User::factory()->viewUsers()->create();
        $departmentManager = User::factory()->create();

        $department = Department::factory()->create([
            'name' => 'CSV Department '.now()->timestamp,
            'manager_id' => $departmentManager->id,
        ]);

        $target = User::factory()->create([
            'username' => 'csv-user-'.now()->timestamp,
            'department_id' => $department->id,
        ]);

        $response = $this->actingAs($actor)
            ->get(route('users.export'))
            ->assertOk();

        $departmentHeader = trans('general.department');
        $departmentManagerHeader = trans('admin/users/general.department_manager');
        $usernameHeader = trans('admin/users/table.username');

        $csv = Reader::createFromString($response->streamedContent());
        $csv->setHeaderOffset(0);

        $rows = collect(iterator_to_array($csv->getRecords(), false));

        $targetRow = $rows->firstWhere($usernameHeader, $target->username);

        $this->assertNotNull($targetRow, 'Target user not found in CSV export.');
        $this->assertSame($department->name, $targetRow[$departmentHeader]);
        $this->assertSame($departmentManager->display_name, $targetRow[$departmentManagerHeader]);
    }

    public function test_department_columns_are_empty_when_user_has_no_department(): void
    {
        $actor = User::factory()->viewUsers()->create();

        $target = User::factory()->create([
            'username' => 'csv-nodept-'.now()->timestamp,
            'department_id' => null,
        ]);

        $response = $this->actingAs($actor)
            ->get(route('users.export'))
            ->assertOk();

        $departmentHeader = trans('general.department');
        $departmentManagerHeader = trans('admin/users/general.department_manager');
        $usernameHeader = trans('admin/users/table.username');

        $csv = Reader::createFromString($response->streamedContent());
        $csv->setHeaderOffset(0);

        $rows = collect(iterator_to_array($csv->getRecords(), false));

        $targetRow = $rows->firstWhere($usernameHeader, $target->username);

        $this->assertNotNull($targetRow, 'Target user not found in CSV export.');
        $this->assertSame('', $targetRow[$departmentHeader]);
        $this->assertSame('', $targetRow[$departmentManagerHeader]);
    }

    public function test_header_row_is_not_repeated_when_export_spans_multiple_chunks(): void
    {
        $actor = User::factory()->viewUsers()->create();
        User::factory()->count(505)->create();

        $response = $this->actingAs($actor)
            ->get(route('users.export'))
            ->assertOk();

        $rows = collect(Reader::createFromString($response->streamedContent())->getRecords());

        $headerId = strtolower(trans('general.id'));
        $headerCompany = trans('admin/companies/table.title');

        $headerOccurrences = $rows->filter(static function (array $row) use ($headerId, $headerCompany): bool {
            return (($row[0] ?? null) === $headerId) && (($row[1] ?? null) === $headerCompany);
        })->count();

        $this->assertSame(1, $headerOccurrences);
    }
}
