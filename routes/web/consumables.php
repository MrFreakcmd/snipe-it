<?php

use App\Http\Controllers\Consumables;
use App\Models\Consumable;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

Route::group(['prefix' => 'consumables', 'middleware' => ['auth']], function () {

    Route::get('{consumable}/checkout', [Consumables\ConsumableCheckoutController::class, 'create'])
        ->breadcrumbs(fn (Trail $trail, Consumable $consumable) => $trail->parent('consumables.show', $consumable)
            ->push(trans('general.checkout'), route('consumables.index'))
        )->name('consumables.checkout.show');

    Route::post(
        '{consumable}/checkout',
        [Consumables\ConsumableCheckoutController::class, 'store']
    )->name('consumables.checkout.store');

    Route::get('{consumable}/clone',
        [Consumables\ConsumablesController::class, 'clone']
    )->name('consumables.clone.create');

});

Route::resource('consumables', Consumables\ConsumablesController::class, [
    'middleware' => ['auth'],
    'parameters' => ['consumable' => 'consumable_id'],
]);
