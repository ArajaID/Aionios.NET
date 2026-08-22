<?php

use Illuminate\Support\Facades\Route;

test('every registered controller route points to an existing action method', function () {
    foreach (Route::getRoutes() as $route) {
        $action = $route->getActionName();
        if (!str_contains($action, '@')) {
            continue;
        }

        [$controller, $method] = explode('@', $action, 2);
        expect(class_exists($controller), "Controller {$controller} untuk route {$route->uri()} tidak ditemukan.")->toBeTrue();
        expect(method_exists($controller, $method), "Method {$controller}@{$method} untuk route {$route->uri()} tidak ditemukan.")->toBeTrue();
    }
});
