<?php

/**
 * Basic i18n translation fallback
 */
if (!function_exists('__t')) {
    function __t(string $key, array $params = [], string $lang = 'fr'): string
    {
        $translations = [
            'en' => [
                'login' => 'Login',
                'register' => 'Register',
                'logout' => 'Logout',
            ],
            'fr' => [
                'login' => 'Connexion',
                'register' => 'Inscription',
                'logout' => 'Dconnexion',
            ]
        ];

        $text = $translations[$lang][$key] ?? $key;

        if (!empty($params)) {
            foreach ($params as $search => $replace) {
                $text = str_replace(':' . $search, $replace, $text);
            }
        }

        return $text;
    }
}