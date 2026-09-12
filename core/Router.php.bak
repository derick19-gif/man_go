<?php

declare(strict_types=1);

/**
 * Core Router Class
 * Gestionnaire de routes hybride : Paramètres dynamiques, résolution automatique de modules MVC et réponses HTTP.
 */
class Router
{
    private Request $request;
    private Response $response;
    private array $routes = [];

    public function __construct(?Request $request = null, ?Response $response = null)
    {
        $this->request = $request ?? new Request();
        $this->response = $response ?? new Response();
    }

    /**
     * Alias générique pour ajouter une route (défaut GET)
     */
    public function add(string $path, callable|array|string $callback, string $method = 'GET'): self
    {
        return $this->addRoute(strtoupper($method), $path, $callback);
    }

    public function get(string $path, callable|array|string $callback): self
    {
        return $this->addRoute('GET', $path, $callback);
    }

    public function post(string $path, callable|array|string $callback): self
    {
        return $this->addRoute('POST', $path, $callback);
    }

    public function put(string $path, callable|array|string $callback): self
    {
        return $this->addRoute('PUT', $path, $callback);
    }

    public function delete(string $path, callable|array|string $callback): self
    {
        return $this->addRoute('DELETE', $path, $callback);
    }

    public function match(array $methods, string $path, callable|array|string $callback): self
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $path, $callback);
        }
        return $this;
    }

    private function addRoute(string $method, string $path, callable|array|string $callback): self
    {
        $formattedPath = '/' . trim($path, '/');
        
        // Conversion du pattern {id}, {slug}, etc. en Regex nommée
        $pattern = preg_replace_callback(
            '/\{(\w+)\}/',
            fn(array $matches): string => '(?P<' . $matches[1] . '>[^/]+)',
            $formattedPath
        );

        $this->routes[strtoupper($method)][] = [
            'path'     => $formattedPath,
            'pattern'  => '#^' . $pattern . '$#i',
            'callback' => $callback
        ];

        return $this;
    }

        /**
         * Résout la route correspondant à la requête courante
         */
        public function resolve(): void
        {
            $method = strtoupper($this->request->getMethod());
        
            // Support du Method Spoofing pour les formulaires (ex: POST avec _method=DELETE ou _method=PUT)
            if ($method === 'POST' && isset($_POST['_method'])) {
                $overrideMethod = strtoupper(trim((string)$_POST['_method']));
                if (in_array($overrideMethod, ['PUT', 'PATCH', 'DELETE', 'HEAD'], true)) {
                    $method = $overrideMethod;
                }
            }

            $path = $this->normalizePath($this->request->getPath());
            $routesForMethod = $this->routes[$method] ?? [];

            foreach ($routesForMethod as $route) {
                if (preg_match($route['pattern'], $path, $matches)) {
                    // Extraction des paramètres nommés {slug}, {id}, etc.
                    $params = array_filter(
                        $matches,
                        fn($key): bool => !is_int($key),
                        ARRAY_FILTER_USE_KEY
                    );

                    $this->dispatchHandler($route['callback'], $params);
                    exit; // Arrêt immédiat de l'exécution
                }
            }

            $this->handleNotFound();
        }

        /**
     * Normalise l'URL courante en retirant les query params et le prefix BASE_URL_PATH
     */
    private function normalizePath(string $path): string
    {
        // Nettoyer les query params
        $path = parse_url($path, PHP_URL_PATH) ?? '/';
        
        $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '/man_go';
        
        // Si le chemin commence par le base path, on le retire
        if (!empty($basePath) && stripos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }

        // Assurer que le chemin commence par /
        $path = '/' . trim($path, '/');
        
        return ($path === '') ? '/' : $path;
    }

    /**
     * Exécute le contrôleur ou la fonction anonyme associée à la route
     */
    private function dispatchHandler(callable|array|string $callback, array $params): void
    {
        // 1. Exécution des Closures / Fonctions anonymes
        if (is_callable($callback)) {
            $result = call_user_func_array($callback, [$this->request, $this->response, ...array_values($params)]);
            $this->processResult($result);
            return;
        }

        // 2. Traitement des paires Contrôleur @ Méthode
        if (is_string($callback) && str_contains($callback, '@')) {
            [$className, $methodName] = explode('@', $callback, 2);
        } elseif (is_array($callback) && count($callback) === 2) {
            [$className, $methodName] = $callback;
        } else {
            $this->response->error('Format de route invalide.', 500);
            return;
        }

        // 3. Garantir la présence des classes fondamentales du Core
        $baseAppPath = defined('APP_PATH') ? APP_PATH : dirname(__DIR__);

        if (!class_exists('Model') && file_exists($baseAppPath . '/core/Model.php')) {
            require_once $baseAppPath . '/core/Model.php';
        }
        if (!class_exists('Controller') && file_exists($baseAppPath . '/core/Controller.php')) {
            require_once $baseAppPath . '/core/Controller.php';
        }

        // 4. Autoload dynamique robuste pour les modules et contrôleurs généraux
        if (!class_exists($className)) {
            $rawModuleName = str_replace('Controller', '', $className);
            $singularModule = strtolower($rawModuleName);
            
            // Gestion intelligente de la pluralisation (ex: Stand -> stands, Category -> categories)
            if (str_ends_with($singularModule, 'y')) {
                $pluralModule = substr($singularModule, 0, -1) . 'ies';
            } else {
                $pluralModule = $singularModule . 's';
            }

            $possiblePaths = [
                $baseAppPath . '/modules/' . $pluralModule . '/controllers/' . $className . '.php',
                $baseAppPath . '/modules/' . $singularModule . '/controllers/' . $className . '.php',
                $baseAppPath . '/controllers/' . $className . '.php',
                $baseAppPath . '/app/controllers/' . $className . '.php'
            ];

            foreach ($possiblePaths as $file) {
                if (file_exists($file)) {
                    require_once $file;
                    break;
                }
            }
        }

        if (!class_exists($className)) {
            $this->response->error("Contrôleur '{$className}' introuvable.", 500);
            return;
        }

        // 5. Instanciation sécurisée selon le constructeur du contrôleur
        try {
            $reflection = new ReflectionClass($className);
            $constructor = $reflection->getConstructor();

            if ($constructor !== null && $constructor->getNumberOfParameters() > 0) {
                $controller = new $className($this->request);
            } else {
                $controller = new $className();
            }
        } catch (Throwable $e) {
            $this->response->error("Erreur lors de l'instanciation de '{$className}' : " . $e->getMessage(), 500);
            return;
        }

        if (!method_exists($controller, $methodName)) {
            $this->response->error("Méthode '{$methodName}' introuvable dans le contrôleur '{$className}'.", 500);
            return;
        }

        // 6. Exécution de la méthode du contrôleur
        try {
            $result = call_user_func_array([$controller, $methodName], array_values($params));
            $this->processResult($result);
        } catch (Throwable $e) {
            $this->response->error("Erreur lors de l'exécution de {$className}@{$methodName} : " . $e->getMessage(), 500);
        }
    }

    /**
     * Traite et envoie le résultat de l'action
     */
    private function processResult(mixed $result): void
    {
        if ($result instanceof Response) {
            $result->send();
            return;
        }

        if (is_array($result) || is_object($result)) {
            $this->response->json($result);
            return;
        }

        if (is_string($result)) {
            $this->response->setContent($result)->send();
            return;
        }

        if ($this->response->getContent() !== '') {
            $this->response->send();
        }
    }

    /**
     * Gestion de la page / réponse 404
     */
    private function handleNotFound(): void
    {
        if ($this->request->isAjax() || str_contains((string)$this->request->header('accept'), 'application/json')) {
            $this->response->error('Route non trouvée', 404);
            return;
        }

        $this->response
            ->setStatusCode(404)
            ->setContent('<h1>404 - Page Non Trouvée</h1><p>La ressource demandée n\'existe pas.</p>')
            ->send();
    }
}