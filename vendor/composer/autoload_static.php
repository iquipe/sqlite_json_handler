<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitaf3aa5ca1957fa8c410e35742e5cf4bc
{
    public static $prefixLengthsPsr4 = array (
        'A' => 
        array (
            'App\\' => 4,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'App\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitaf3aa5ca1957fa8c410e35742e5cf4bc::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitaf3aa5ca1957fa8c410e35742e5cf4bc::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitaf3aa5ca1957fa8c410e35742e5cf4bc::$classMap;

        }, null, ClassLoader::class);
    }
}
