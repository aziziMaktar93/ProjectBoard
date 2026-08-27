<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration rejects a password without a number or symbol', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'onlyletters',
        'password_confirmation' => 'onlyletters',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

test('registration requests are rate limited', function () {
    // Deliberately invalid so registration never succeeds (and never logs the
    // caller in, which would otherwise make the `guest` middleware redirect
    // instead of letting the request reach the throttle limiter).
    foreach (range(1, 6) as $i) {
        $this->post('/register', ['email' => "test{$i}@example.com"]);
    }

    $response = $this->post('/register', ['email' => 'test7@example.com']);

    $response->assertStatus(429);
});
