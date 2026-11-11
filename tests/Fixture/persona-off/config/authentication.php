<?php

declare(strict_types=1);

// As guild/starter reads them: from the environment, with no defaults.
return new Guild\Access\Authentication\OIDC\OidcConfiguration(
    providerUrl: $_ENV['OIDC_ISSUER'],
    clientId: $_ENV['OIDC_CLIENT_ID'],
    clientSecret: $_ENV['OIDC_CLIENT_SECRET'],
    redirectUri: $_ENV['OIDC_REDIRECT_URI'],
    scopes: ['profile', 'email'],
);
