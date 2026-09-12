<?php
// modules/stand/controllers/StandController.php

use App\Core\Controller;
use App\Core\Session;

// Inclusion absolue, directe et forcée du Modèle !
require_once __DIR__ . '/../models/Stand.php';

class StandController extends Controller {

    public function index() {
        $standModel = new Stand();
        $search   = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $location = trim(filter_input(INPUT_GET, 'location', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $category = trim(filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

        $stands = method_exists($standModel, 'getActiveStands') ? $standModel->getActiveStands($search, $location, $category) : [];

        echo $this->render('index', [
            'stands'      => $stands,
            'search'      => $search,
            'location'    => $location,
            'category'    => $category
        ]);
    }

    public function detail() {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $standModel = new Stand();
        $stand = $standModel->find($id);

        if (!$stand) {
            http_response_code(404);
            echo "Boutique introuvable.";
            return;
        }

        $listings = $standModel->getStandListings($id);

        echo $this->render('detail', [
            'stand'    => $stand,
            'listings' => $listings
        ]);
    }

    public function create() {
        echo $this->render('create', []);
    }

    public function store() {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $city        = trim($_POST['city'] ?? '');
        $address     = trim($_POST['address'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');

        $standModel = new Stand();
        $success = $standModel->createStand([
            'user_id'     => Session::get('user_id') ?? 1,
            'name'        => $name,
            'description' => $description,
            'category'    => $category,
            'city'        => $city,
            'address'     => $address,
            'phone'       => $phone,
            'logo_url'    => 'assets/images/placeholder.jpg',
            'banner_url'  => 'assets/images/placeholder.jpg'
        ]);

        if ($success) {
            header('Location: ../stands');
            exit;
        } else {
            header('Location: create?error=1');
            exit;
        }
    }
}