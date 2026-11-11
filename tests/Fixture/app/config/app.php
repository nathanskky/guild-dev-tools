<?php

declare(strict_types=1);

// The fixture application as an app's own config/app.php would build it:
// extensions are discovered (dev-tools is the root package, of type
// guild-extension), and PERSONA_SIGN_IN_ENABLED comes from phpunit.xml.dist.

use Guild\DevTools\Test\Support\FixturePermission;
use Guild\Framework\Application;
use Guild\Framework\Authorization\IdentitySource;

return Application::configure(dirname(__DIR__))
    ->enableAutoWiring()
    ->addIlluminateDatabase()
    ->addAuthentication()
    ->addAuthorization(IdentitySource::Oidc, FixturePermission::class)
    ->addRouting()
    ->create();
