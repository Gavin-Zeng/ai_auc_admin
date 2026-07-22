<?php

use App\Models\User;

test('profile page is displayed', function () {
    $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertOk();
});

test('profile updates account and name without email', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'account' => 'Profile_01', 'name' => 'Test User',
    ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

    expect($user->refresh()->only(['account', 'name']))->toBe(['account' => 'Profile_01', 'name' => 'Test User']);
});

test('user can delete their own account with password confirmation', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect(route('home'));
    $this->assertGuest();
});
