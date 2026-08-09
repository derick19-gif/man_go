<?php
/**
 * Base Controller Class
 * 
 * All controllers extend this class
 */

class Controller
{
    /**
     * Current request
     * 
     * @var Request
     */
    protected Request $request;

    /**
     * Response object
     * 
     * @var Response
     */
    protected Response $response;

    /**
     * Constructor
     * 
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->response = new Response();
    }

    /**
     * Render view file
     * 
     * @param string $view
     * @param array $data
     * @return string
     */
    protected function render(string $view, array $data = []): string
    {
        extract($data);

        ob_start();
        include APP_PATH . '/modules/' . strtolower(basename(get_class($this), 'Controller')) . '/views/' . $view . '.php';
        $content = ob_get_clean();

        return $content;
    }

    /**
     * Send JSON response
     * 
     * @param array $data
     * @param int $statusCode
     * @return void
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        $this->response->json($data, $statusCode);
    }

    /**
     * Redirect to URL
     * 
     * @param string $url
     * @param int $statusCode
     * @return void
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        $this->response->redirect($url, $statusCode);
    }

    /**
     * Return error response
     * 
     * @param string $message
     * @param int $statusCode
     * @return void
     */
    protected function error(string $message, int $statusCode = 400): void
    {
        $this->response->error($message, $statusCode);
    }

    /**
     * Return success response
     * 
     * @param array $data
     * @param string $message
     * @param int $statusCode
     * @return void
     */
    protected function success(array $data = [], string $message = 'Success', int $statusCode = 200): void
    {
        $this->response->success($data, $message, $statusCode);
    }

    /**
     * Check if user is authenticated
     * 
     * @return boolean
     */
    protected function isAuthenticated(): bool
    {
        return Session::isAuthenticated();
    }

    /**
     * Get authenticated user ID
     * 
     * @return int|null
     */
    protected function getUserId(): ?int
    {
        return Session::getUserId();
    }

    /**
     * Require authentication
     * 
     * @return void
     */
    protected function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            $this->redirect(APP_URL . '/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        }
    }
}
