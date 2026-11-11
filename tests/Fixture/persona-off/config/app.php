<?php

declare(strict_types=1);

// An IU Login application whose tests do not turn persona sign-in on.

use Guild\DevTools\PersonaSignInExtension;
use Guild\Framework\Application;

return Application::configure(dirname(__DIR__), [new PersonaSignInExtension(['PERSONA_SIGN_IN_ENABLED' => 'false'])])
    ->enableAutoWiring()
    ->addAuthentication()
    ->addRouting()
    ->create();
