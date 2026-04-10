<?php

test('health check is publicly accessible at the spec route', function () {
    $this->get('/health')->assertOk();
});

test('legacy up route redirects to the health check route', function () {
    $this->get('/up')->assertRedirect('/health');
});
