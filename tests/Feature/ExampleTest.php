<?php

test('guest is redirected to login from the application root', function () {
    $this->get('/')->assertRedirect('/login');
});
