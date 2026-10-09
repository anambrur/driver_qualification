<?php

use Tests\Feature\Modules\Drivers\DriverAdmin;

/*
| DRV-01: the delete button was built as onclick="deleteDriver(id, '<addslashes(name)>')".
| addslashes() does nothing against HTML entities: the browser decodes &#39; to ' inside the
| attribute before the JS runs, so a name like `&#39;);alert(1);//` broke out of the string.
| Public applicants choose their own name, so the script ran in the admin's session.
*/

dataset('hostile names', [
    'entity quote' => ['&#39;);alert(1);//', 'Roe'],
    'html tag' => ['<img src=x onerror=alert(1)>', '<script>alert(2)</script>'],
    'double quote' => ['" onmouseover="alert(3)', 'x'],
]);

describe('DRV-01: driver names in the list are inert', function () {
    it('puts the name in an escaped data attribute instead of inline onclick', function (string $first, string $last) {
        [$owner, $driver] = DriverAdmin::tenantWithDriver(['first_name' => $first, 'last_name' => $last]);

        $row = $this->actingAs($owner)
            ->withHeaders(DriverAdmin::ajax())
            ->get(route('admin.driver.index', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->json('data.0');

        expect($row['action'])->not->toContain('onclick')
            ->and($row['action'])->toContain('data-driver-id="'.$driver->id.'"')
            ->and($row['action'])->toContain('data-driver-name="'.e($first.' '.$last).'"')
            ->and($row['full_name'])->not->toContain('<')
            ->and($row['photo'])->not->toContain('<script');
    })->with('hostile names');

    it('shows the name in the confirm dialog as text, not HTML', function () {
        [$owner] = DriverAdmin::tenantWithDriver();

        $html = $this->actingAs($owner)->get(route('admin.driver.index'))->assertOk()->getContent();

        expect($html)->not->toContain('<strong>${name}</strong>')
            ->and($html)->toContain("[data-action=\"delete-driver\"]");
    });
});
