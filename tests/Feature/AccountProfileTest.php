<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    try {
        DB::connection()->getPdo();
    } catch (\Throwable $exception) {
        $this->markTestSkipped('Database is not available for integration tests.');
    }

    Storage::fake('public');
});

it('stores a new profile image and removes the previous file', function () {
    $admin = getDefaultAdmin();

    if (! $admin) {
        $this->markTestSkipped('Default admin user not found.');
    }

    $admin->update(['image' => 'admins/'.$admin->id.'/old.png']);

    $path = 'admins/'.$admin->id.'/new-avatar.png';

    Storage::disk('public')->put('admins/'.$admin->id.'/old.png', 'old');
    Storage::disk('public')->put($path, 'new');

    $controller = app(\Webkul\Admin\Http\Controllers\User\AccountController::class);

    $method = new ReflectionMethod($controller, 'resolveUploadedImage');

    $method->setAccessible(true);

    $file = UploadedFile::fake()->image('avatar.jpg');

    $request = \Illuminate\Http\Request::create(
        route('admin.user.account.update'),
        'PUT',
        [
            'name'             => $admin->name,
            'email'            => $admin->email,
            'current_password' => 'admin123',
        ],
        [],
        ['image' => [$file]]
    );

    app()->instance('request', $request);

    expect($method->invoke($controller))->not->toBeNull();

    $admin->update(['image' => $path]);

    Storage::disk('public')->delete('admins/'.$admin->id.'/old.png');

    $admin->refresh();

    expect($admin->image)->toBe($path)
        ->and(Storage::disk('public')->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->exists('admins/'.$admin->id.'/old.png'))->toBeFalse();
});

it('keeps existing profile image when hidden marker is submitted', function () {
    $admin = getDefaultAdmin();

    if (! $admin) {
        $this->markTestSkipped('Default admin user not found.');
    }

    $path = 'admins/'.$admin->id.'/keep.png';

    $admin->update(['image' => $path]);

    Storage::disk('public')->put($path, 'keep');

    $response = $this->actingAs($admin, 'user')
        ->from(route('admin.user.account.edit'))
        ->put(route('admin.user.account.update'), [
            '_token'           => csrf_token(),
            'name'             => $admin->name,
            'email'            => $admin->email,
            'current_password' => 'admin123',
            'image'            => ['image' => ''],
        ]);

    $response->assertRedirect(route('admin.user.account.edit'));

    $admin->refresh();

    expect($admin->image)->toBe($path)
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

it('rejects account updates with invalid current password', function () {
    $admin = getDefaultAdmin();

    if (! $admin) {
        $this->markTestSkipped('Default admin user not found.');
    }

    $response = $this->actingAs($admin, 'user')
        ->from(route('admin.user.account.edit'))
        ->put(route('admin.user.account.update'), [
            '_token'           => csrf_token(),
            'name'             => $admin->name,
            'email'            => $admin->email,
            'current_password' => 'wrong-password',
        ]);

    $response->assertRedirect(route('admin.user.account.edit'))
        ->assertSessionHas('warning');
});
