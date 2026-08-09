<?php

declare(strict_types=1);

/**
 * HTTP Response Handler
 * 
 * Manages HTTP status codes, headers, and output delivery.
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $content = '';

    private array $statusMessages = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $key, string $value): self
    {
        $cleanKey = str_replace(["\r", "\n"], '', $key);
        $cleanValue = str_replace(["\r", "\n"], '', $value);
        $this->headers[$cleanKey] = $cleanValue;
        return $this;
    }

    public function setHeaders(array $headers): self
    {
        foreach ($headers as $key => $value) {
            $this->setHeader((string)$key, (string)$value);
        }
        return $this;
    }

    public function setContentType(string $type, string $charset = 'utf-8'): self
    {
        return $this->setHeader('Content-Type', "{$type}; charset={$charset}");
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function appendContent(string $content): self
    {
        $this->content .= $content;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Envoyé une redirection HTTP en s'assurant que le BASE_URL_PATH est respecté
     */
    public function redirect(string $url, int $statusCode = 302): void
    {
        // Ne préfixe pas les URLs absolues (ex: https://...)
        if (!preg_match('#^https?://#i', $url)) {
            $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '/man_go';
            if (!empty($basePath) && !str_starts_with($url, $basePath)) {
                $url = $basePath . '/' . ltrim($url, '/');
            }
        }

        $this->setStatusCode($statusCode);
        $this->setHeader('Location', $url);
        $this->send();
    }

    public function json(mixed $data, int $statusCode = 200): void
    {
        $this->setStatusCode($statusCode);
        $this->setContentType('application/json');
        $this->setContent(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->send();
    }

    public function error(string $message, int $statusCode = 400): void
    {
        $this->json([
            'success' => false,
            'error'   => $message,
            'status'  => $statusCode,
        ], $statusCode);
    }

    public function success(array $data = [], string $message = 'Success', int $statusCode = 200): void
    {
        $this->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'status'  => $statusCode,
        ], $statusCode);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $key => $value) {
                header("{$key}: {$value}");
            }
        }

        echo $this->content;
        exit;
    }
}