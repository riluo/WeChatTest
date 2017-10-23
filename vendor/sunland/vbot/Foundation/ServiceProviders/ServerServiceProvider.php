<?php

namespace Sunland\Vbot\Foundation\ServiceProviders;

use Sunland\Vbot\Core\Server;
//use Sunland\Vbot\Core\Swoole;
//use Sunland\Vbot\Core\Sync;
use Sunland\Vbot\Foundation\ServiceProviderInterface;
use Sunland\Vbot\Foundation\Vbot;

class ServerServiceProvider implements ServiceProviderInterface
{
    public function register(Vbot $vbot)
    {
        $vbot->singleton('server', function () use ($vbot) {
            return new Server($vbot);
        });

        /*$vbot->singleton('swoole', function () use ($vbot) {
            return new Swoole($vbot);
        });
        $vbot->singleton('sync', function () use ($vbot) {
            return new Sync($vbot);
        });*/
    }
}
