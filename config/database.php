<?php
/**
 * Database Configuration
 * 
 * This file contains database connection settings
 * Can override defaults from environment variables or .env file
 */

return [
    'driver'   => 'mysql',
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'port'     => (int)(getenv('DB_PORT') ?: 3306),
    'dbname'   => getenv('DB_NAME') ?: 'mango_db',
    'user'     => getenv('DB_USER') ?: 'root',
    'pass'     => getenv('DB_PASS') ?: '',
    'charset'  => 'utf8mb4',
    'options'  => [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES   => false,
    ]
];
