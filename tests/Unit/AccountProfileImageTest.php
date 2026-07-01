<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webkul\Admin\Http\Controllers\User\AccountController;
use Webkul\User\Models\User;

it('generates profile image urls from stored path', function () {
    $user = new User;

    $user->forceFill(['image' => 'admins/1/test.png']);

    expect($user->image_url)->toEndWith('/storage/admins/1/test.png');
});

it('resolves uploaded image via controller helper', function () {
    $controller = new AccountController;

    $method = new ReflectionMethod($controller, 'resolveUploadedImage');

    $method->setAccessible(true);

    $file = UploadedFile::fake()->image('avatar.png');

    $request = \Illuminate\Http\Request::create('/account', 'POST', [], [], [
        'image' => [$file],
    ]);

    app()->instance('request', $request);

    $resolved = $method->invoke($controller);

    expect($resolved)->not->toBeNull()
        ->and($resolved->isValid())->toBeTrue();
});

it('returns null when no valid uploaded image is present', function () {
    $controller = new AccountController;

    $method = new ReflectionMethod($controller, 'resolveUploadedImage');

    $method->setAccessible(true);

    $request = \Illuminate\Http\Request::create('/account', 'POST', [
        'image' => ['image' => ''],
    ]);

    app()->instance('request', $request);

    expect($method->invoke($controller))->toBeNull();
});
