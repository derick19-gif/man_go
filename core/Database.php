<?php
/**
 * Database Connection Manager
 * 
 * Singleton class for PDO database connections
 * Provides connection pooling and lazy initialization
 */

class Database
{
    /**
     * PDO instance
     * 
     * @var \PDO|null
     */
    private static ?\PDO $connection = null;

    /**
     * Database configuration
     * 
     * @var array
     */
    private static array $config = [];

    /**
     * Private constructor - prevent instantiation
     */
    private function __construct() {}

    /**
     * Get database connection (singleton)
     * 
     * @param array $config Database configuration
     * @return \PDO
     * @throws \PDOException
     */
    public static function connect(array $config = []): \PDO
    {
        if (self::$connection === null) {
            self::$config = $config ?: (include APP_PATH . '/config/database.php');
            
            try {
                $dsn = self::buildDSN();
                
                self::$connection = new \PDO(
                    $dsn,
                    self::$config['user'],
                    self::$config['pass'] ?? '',
                    self::$config['options'] ?? [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    ]
                );
            } catch (\PDOException $e) {
                static::handleConnectionError($e);
            }
        }

        return self::$connection;
    }

    /**
     * Build DSN from config
     * 
     * @return string
     */
    private static function buildDSN(): string
    {
        $driver = self::$config['driver'] ?? 'mysql';
        
        switch ($driver) {
            case 'mysql':
                return sprintf(
                    '%s:host=%s;port=%s;dbname=%s;charset=%s',
                    $driver,
                    self::$config['host'],
                    self::$config['port'] ?? 3306,
                    self::$config['dbname'],
                    self::$config['charset'] ?? 'utf8mb4'
                );
            
            case 'sqlite':
                return sprintf('sqlite:%s', self::$config['path']);
            
            case 'pgsql':
                return sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s',
                    self::$config['host'],
                    self::$config['port'] ?? 5432,
                    self::$config['dbname']
                );
            
            default:
                throw new \RuntimeException("Unsupported database driver: {$driver}");
        }
    }

    /**
     * Handle database connection errors
     * 
     * @param \PDOException $e
     * @return void
     */
    private static function handleConnectionError(\PDOException $e): void
    {
        if (APP_DEBUG) {
            throw $e;
        }
        
        // Log error in production
        error_log('Database Connection Error: ' . $e->getMessage());
        
        // User-friendly error
        die('Database connection failed. Please try again later.');
    }

    /**
     * Obtenir l'instance PDO (initialise automatiquement si ncessaire)
     * 
     * @return \PDO
     */
    public static function getInstance(): \PDO
    {
        if (self::$connection === null) {
            return self::connect();
        }
        return self::$connection;
    }

    /**
     * Alias de scurit pour compatibilit avec getConnection()
     * 
     * @return \PDO
     */
    public static function getConnection(): \PDO
    {
        return self::getInstance();
    }

    /**
     * Close connection
     * 
     * @return void
     */
    public static function close(): void
    {
        self::$connection = null;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserializing
     */
    public function __wakeup() {}
}
