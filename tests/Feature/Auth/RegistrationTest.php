<?php

use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'account' => 'Ab',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('auc_users', [
        'account' => 'Ab',
        'email' => 'test@example.com',
    ]);
});

test('new users must register with a valid account', function (string $account) {
    $response = $this->post(route('register.store'), [
        'account' => $account,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('account');
})->with([
    'too short' => 'a',
    'too long' => 'abcdefghijklmnopqrs',
    'contains number' => 'tester1',
    'contains underscore' => 'test_user',
    'contains chinese' => '测试用户账号',
]);
