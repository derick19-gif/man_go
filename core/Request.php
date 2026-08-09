<?php

declare(strict_types=1);

/**
 * HTTP Request Handler
 * 
 * Encapsulates HTTP request data and provides robust helper methods.
 */
class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $body;
    private array $server;
    private array $headers;
    private array $files;

    public function __construct()
    {
        $this->server = $_SERVER;
        $this->query = $_GET;
        $this->files = $_FILES;
        $this->headers = $this->parseHeaders();
        $this->method = $this->determineMethod();
        $this->path = $this->determinePath();
        $this->body = $this->parseBody();
    }

    private function determineMethod(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            if (isset($_POST['_method'])) {
                $method = strtoupper($_POST['_method']);
            } elseif (isset($this->headers['x-http-method-override'])) {
                $method = strtoupper($this->headers['x-http-method-override']);
            }
        }

        return $method;
    }

    private function determinePath(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Nettoyage du préfixe sous-dossier (ex: /man_go)
        $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
        if (!empty($basePath) && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : $path;
    }

    private function parseHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if ($headers !== false) {
                $result = [];
                foreach ($headers as $key => $value) {
                    $result[strtolower((string)$key)] = $value;
                }
                return $result;
            }
        }

        $headers = [];
        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $name = str_replace('_', '-', strtolower($key));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    private function parseBody(): array
    {
        if (in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return [];
        }

        $contentType = $this->header('content-type', '');

        if (str_contains($contentType, 'application/json')) {
            $rawInput = file_get_contents('php://input');
            if (!empty($rawInput)) {
                $data = json_decode($rawInput, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    return $data;
                }
            }
            return [];
        }

        if ($this->method === 'POST') {
            return $_POST;
        }

        $rawInput = file_get_contents('php://input');
        $data = [];
        parse_str($rawInput, $data);
        return is_array($data) ? $data : [];
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    public function isGet(): bool
    {
        return $this->isMethod('GET');
    }

    public function isPut(): bool
    {
        return $this->isMethod('PUT');
    }

    public function isDelete(): bool
    {
        return $this->isMethod('DELETE');
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->all();
        }
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array|string ...$keys): array
    {
        $keys = is_array($keys[0] ?? null) ? $keys[0] : $keys;
        $all = $this->all();
        $result = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $result[$key] = $all[$key];
            }
        }

        return $result;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function isAjax(): bool
    {
        return strtolower((string)$this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    public function getIp(): string
    {
        if (class_exists('Security') && method_exists('Security', 'getClientIp')) {
            return Security::getClientIp();
        }

        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (!empty($this->server[$key])) {
                foreach (explode(',', $this->server[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }

        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}