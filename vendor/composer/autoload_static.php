<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit64fd0d64cba01d7b2efe935ef41b5bcf
{
    public static $files = array (
        '7e9bd612cc444b3eed788ebbe46263a0' => __DIR__ . '/..' . '/laminas/laminas-zendframework-bridge/src/autoload.php',
    );

    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'Laminas\\ZendFrameworkBridge\\' => 28,
            'Laminas\\Dom\\' => 12,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Laminas\\ZendFrameworkBridge\\' => 
        array (
            0 => __DIR__ . '/..' . '/laminas/laminas-zendframework-bridge/src',
        ),
        'Laminas\\Dom\\' => 
        array (
            0 => __DIR__ . '/..' . '/laminas/laminas-dom/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit64fd0d64cba01d7b2efe935ef41b5bcf::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit64fd0d64cba01d7b2efe935ef41b5bcf::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
