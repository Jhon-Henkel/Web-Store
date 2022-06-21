<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit7f170d748ab2c3b1f532888f8136ac6b
{
    public static $prefixLengthsPsr4 = array (
        'c' => 
        array (
            'core\\' => 5,
        ),
        'J' => 
        array (
            'Jhowr\\WebStore\\' => 15,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'core\\' => 
        array (
            0 => __DIR__ . '/../..' . '/core',
        ),
        'Jhowr\\WebStore\\' => 
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
            $loader->prefixLengthsPsr4 = ComposerStaticInit7f170d748ab2c3b1f532888f8136ac6b::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit7f170d748ab2c3b1f532888f8136ac6b::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit7f170d748ab2c3b1f532888f8136ac6b::$classMap;

        }, null, ClassLoader::class);
    }
}
