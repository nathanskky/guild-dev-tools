<?php

declare(strict_types=1);

// An IU Login application shaped like guild/starter's, whose tests do not
// turn persona sign-in on: extensions are discovered, and IU Login settings
// come from the environment.

use Guild\DevTools\Test\Support\FixturePermission;
use Guild\Framework\Application;
use Guild\Framework\Authorization\IdentitySource;

return Application::configure(dirname(__DIR__))
    ->addRouting()
    ->addIlluminateDatabase()
    ->addAuthentication()
    ->addAuthorization(IdentitySource::Oidc, FixturePermission::class)
    ->enableAutoWiring()
    ->create();
