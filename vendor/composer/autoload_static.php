<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInita0cf403f9ce849a52757484c4d35013f
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'PhpAmqpLib\\' => 11,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'PhpAmqpLib\\' => 
        array (
            0 => __DIR__ . '/..' . '/php-amqplib/php-amqplib/PhpAmqpLib',
        ),
    );

    public static $prefixesPsr0 = array (
        'P' => 
        array (
            'PHPQRCode' => 
            array (
                0 => __DIR__ . '/..' . '/aferrandini/phpqrcode/lib',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInita0cf403f9ce849a52757484c4d35013f::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInita0cf403f9ce849a52757484c4d35013f::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInita0cf403f9ce849a52757484c4d35013f::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}
