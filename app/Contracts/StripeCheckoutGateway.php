<?php

namespace App\Contracts;

interface StripeCheckoutGateway
{
    public function createSession(array $parameters, array $options = []): object;
}
