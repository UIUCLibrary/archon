<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitbf483e96fad9082dbda833cee7fd0140
{
    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'HTML_To_Markdown' => __DIR__ . '/..' . '/league/html-to-markdown/HTML_To_Markdown.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->classMap = ComposerStaticInitbf483e96fad9082dbda833cee7fd0140::$classMap;

        }, null, ClassLoader::class);
    }
}
