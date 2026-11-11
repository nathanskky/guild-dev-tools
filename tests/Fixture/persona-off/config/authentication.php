<?php

declare(strict_types=1);

// Stub IU Login settings; nothing contacts the provider.
return new Guild\Access\Authentication\OIDC\OidcConfiguration(
    providerUrl: 'https://idp.example.test',
    clientId: 'stub',
    clientSecret: 'stub',
    redirectUri: 'https://localhost/signin-oidc',
);
