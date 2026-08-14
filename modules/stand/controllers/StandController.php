<?php
// modules/stands/controllers/StandController.php

if (!class_exists('Model')) {
    if (defined('APP_PATH') && file_exists(APP_PATH . '/core/Model.php')) {
        require_once APP_PATH . '/core/Model.php';
    } else {
        $fallbackPath = __DIR__ . '/../../../core/Model.php';
        if (file_exists($fallbackPath)) {
            require_once $fallbackPath;
        }
    }
}

if (!class_exists('Controller')) {
    if (defined('APP_PATH') && file_exists(APP_PATH . '/core/Controller.php')) {
        require_once APP_PATH . '/core/Controller.php';
    } else {
        $fallbackPath = __DIR__ . '/../../../core/Controller.php';
        if (file_exists($fallbackPath)) {
            require_once $fallbackPath;
        }
    }
}

$standModelPath = __DIR__ . '/../models/Stand.php';
if (file_exists($standModelPath)) {
    require_once $standModelPath;
}

class StandController extends Controller {

        public function index() {
        $standModel = new Stand();

        $search   = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $location = trim(filter_input(INPUT_GET, 'location', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $category = trim(filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 12;
        $offset = ($page - 1) * $limit;

        $totalStands = $standModel->countActiveStands($search, $location, $category);
        $totalPages  = max(1, (int)ceil($totalStands / $limit));
        $stands      = $standModel->getActiveStands($search, $location, $category, $limit, $offset);
        $categories  = $standModel->getCategories();

        echo $this->render('index', [
            'stands'      => $stands,
            'totalStands' => $totalStands,
            'totalPages'  => $totalPages,
            'page'        => $page,
            'search'      => $search,
            'location'    => $location,
            'category'    => $category,
            'categories'  => $categories
        ]);
    }
}