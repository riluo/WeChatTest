<?php

namespace Sunland\Vbot\Foundation\ServiceProviders;

use Sunland\Vbot\Console\Console;
use Sunland\Vbot\Console\QrCode;
use Sunland\Vbot\Foundation\ServiceProviderInterface;
use Sunland\Vbot\Foundation\Vbot;

class ConsoleServiceProvider implements ServiceProviderInterface
{
    public function register(Vbot $vbot)
    {
        $vbot->bind('qrCode', function () use ($vbot) {
            return new QrCode($vbot);
        });
        $vbot->singleton('console', function () use ($vbot) {
            return new Console($vbot);
        });
    }
}
