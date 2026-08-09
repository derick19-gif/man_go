<?php

/**
 * Escape output
 * 
 * @param string|null $data
 * @return string
 */
function e(?string $data): string
{
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

/**
 * Helper to render a view
 * 
 * @param string $view
 * @param array $data
 * @return void
 */
function view(string $view, array $data = []): void
{
    // Simplified view rendering logic
    extract($data);
    require __DIR__ . '/../themes/default/views/' . $view . '.php';
}
