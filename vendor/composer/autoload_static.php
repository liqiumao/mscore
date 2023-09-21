<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite8965b03fa038303ecf926df2a534c92
{
    public static $prefixLengthsPsr4 = array (
        'M' => 
        array (
            'Mscore\\Core\\' => 12,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Mscore\\Core\\' => 
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
            $loader->prefixLengthsPsr4 = ComposerStaticInite8965b03fa038303ecf926df2a534c92::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite8965b03fa038303ecf926df2a534c92::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInite8965b03fa038303ecf926df2a534c92::$classMap;

        }, null, ClassLoader::class);
    }
}
