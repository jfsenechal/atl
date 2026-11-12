<?php

namespace AcMarche\Mercredi\Spam\Handler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class SpamHandler
{
    public function __construct(
        private RateLimiterFactoryInterface $anonymousApiLimiter,
    ) {
    }

    public function isAccepted(Request $request): bool
    {
        $limiter = $this->anonymousApiLimiter->create($request->getClientIp());

        return $limiter->consume()->isAccepted();
    }


}