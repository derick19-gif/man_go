<?php
require_once '../config/config.php';
require_once '../core/Session.php';
require_once '../core/Database.php';

if (!Session::isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

$db = Database::connect();
$user_id = Session::getUserId();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $welcome = $_POST['welcome_message'] ?? '';
    $auto_reply = $_POST['auto_reply_message'] ?? '';
    $is_away = isset($_POST['is_away']) ? 1 : 0;

    $stmt = $db->prepare("REPLACE INTO business_settings (user_id, auto_reply_enabled, auto_reply_message, welcome_message, is_away) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $is_away, $auto_reply, $welcome, $is_away]);
    $msg = '<div class="bg-green-600 p-4 rounded mb-4">Paramtres enregistrs avec succs.</div>';
}

$stmt = $db->prepare("SELECT * FROM business_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$settings = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Paramtres MAN GO Business - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-6">
    <div class="max-w-2xl mx-auto bg-gray-800 p-6 rounded">
        <?php echo $msg; ?>
        <h1 class="text-2xl font-bold mb-6">Paramtres MAN GO Business</h1>
        <form method="POST">
            <div class="mb-4">
                <label class="block mb-2">Message d'accueil</label>
                <textarea name="welcome_message" class="w-full bg-gray-700 p-2 rounded" rows="3"><?php echo htmlspecialchars($settings['welcome_message'] ?? ''); ?></textarea>
            </div>
            <div class="mb-4">
                <label class="block mb-2">Message d'absence</label>
                <textarea name="auto_reply_message" class="w-full bg-gray-700 p-2 rounded" rows="3"><?php echo htmlspecialchars($settings['auto_reply_message'] ?? ''); ?></textarea>
            </div>
            <div class="flex items-center gap-4 mb-6">
                <label>
                    <input type="checkbox" name="is_away" <?php echo ($settings['is_away'] ?? 0) ? 'checked' : ''; ?>> Activer le mode absence
                </label>
            </div>
            <button type="submit" class="bg-orange-600 px-4 py-2 rounded">Enregistrer les paramtres</button>
        </form>
    </div>
</body>
</html>
