<?php

namespace App\Services;

use App\Contracts\StripeCheckoutGateway;
use Stripe\StripeClient;

class StripeSdkCheckoutGateway implements StripeCheckoutGateway
{
    public function createSession(array $parameters, array $options = []): object
    {
        return (new StripeClient(config('services.stripe.secret')))
            ->checkout->sessions->create($parameters, $options);
    }
}
