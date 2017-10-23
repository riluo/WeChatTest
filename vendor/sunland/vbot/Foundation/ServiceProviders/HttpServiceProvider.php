<?php

namespace Sunland\Vbot\Foundation\ServiceProviders;

use Sunland\Vbot\Foundation\ServiceProviderInterface;
use Sunland\Vbot\Foundation\Vbot;
use Sunland\Vbot\Support\Http;

class HttpServiceProvider implements ServiceProviderInterface
{
    /**
     * @param \Hanson\Vbot\Foundation\Vbot $vbot
     */
    public function register(Vbot $vbot)
    {
        $vbot->singleton('http', function () use ($vbot) {
            return new Http($vbot);
        });
    }
}
