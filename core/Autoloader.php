<?php
/**
 * PSR-4 & Class Fallback Autoloader
 * 
 * Auto-charge les classes avec ou sans Namespace PSR-4.
 */

class Autoloader
{
    /**
     * Carte des namespaces vers les répertoires
     */
    private static array $namespaces = [];

    /**
     * Enregistre l'autoloader avec spl_autoload_register
     */
    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
        
        $baseDir = dirname(__DIR__);

        // Mapping PSR-4 de base
        self::addNamespace('App\\', $baseDir);
        self::addNamespace('App\\Core\\', $baseDir . '/core');
        self::addNamespace('App\\Modules\\', $baseDir . '/modules');
        self::addNamespace('App\\Classes\\', $baseDir . '/classes');
    }

    /**
     * Ajoute un namespace et son répertoire
     */
    public static function addNamespace(string $namespace, string $directory): void
    {
        self::$namespaces[$namespace] = rtrim($directory, '/\\');
    }

    /**
     * Charge une classe ou un interface
     */
    public static function load(string $class): bool
    {
        // 1. Essai de chargement via Namespace PSR-4
        foreach (self::$namespaces as $namespace => $directory) {
            if (strpos($class, $namespace) === 0) {
                $path = str_replace('\\', '/', substr($class, strlen($namespace)));
                $file = $directory . '/' . $path . '.php';
                
                if (file_exists($file)) {
                    require_once $file;
                    return true;
                }
            }
        }

        // 2. Fallback pour les classes sans Namespace (Core, Classes & Modules)
        $baseDir = dirname(__DIR__);
        $classFile = str_replace('\\', '/', $class) . '.php';

        $locations = [
            $baseDir . '/core/' . $classFile,
            $baseDir . '/classes/' . $classFile,
        ];

        foreach ($locations as $file) {
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }

        return false;
    }
}

// Enregistrement immédiat
Autoloader::register();