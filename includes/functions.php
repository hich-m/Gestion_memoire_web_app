<?php
// Traduction des messages d'erreur et des fonctions en français
function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function initSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function afficherMessageErreur($message)
{
    echo "<div class='erreur'>" . htmlspecialchars($message) . "</div>";
}
function isLoggedIn()
{
    initSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function hasRole($role)
{
    initSession();
    if (!isLoggedIn()) {
        return false;
    }
    return $_SESSION['role'] === $role;
}
/**
 * Vérifie l'authentification et les droits d'accès
 * @param array $allowedRoles Les rôles autorisés (optionnel)
 */
function checkAuth($allowedRoles = [])
{
    initSession();

    if (!isset($_SESSION['user'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        $_SESSION['error'] = 'Veuillez vous connecter';
        header('Location: /gestion_memoire_web_app/login.php');
        exit();
    }

    if (!empty($allowedRoles) && !in_array($_SESSION['user']['role'], $allowedRoles)) {
        $_SESSION['error'] = 'Accès non autorisé';
        header('Location: /index.php');
        exit();
    }
}

function redirect($page)
{
    header("Location: $page");
    exit;
}

function generatePassword($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

function displayFlashMessage()
{
    initSession();
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        echo "<div class='alert alert-$type'>$message</div>";
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

function setFlashMessage($message, $type = 'info')
{
    initSession();
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function getUserInfo($userId, $conn)
{
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }

    return null;
}

function isPDF($file)
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    return $mimeType === 'application/pdf';
}

function generateUniqueFilename($originalName)
{
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

function formatDate($date)
{
    return date("F j, Y", strtotime($date));
}

function getStatusBadge($status)
{
    $badgeClass = '';
    $text = '';

    switch (strtolower($status)) {
        case 'approved':
            $badgeClass = 'status-approved';
            $text = 'Approved';
            break;
        case 'rejected':
            $badgeClass = 'status-rejected';
            $text = 'Rejected';
            break;
        case 'pending':
        default:
            $badgeClass = 'status-pending';
            $text = 'Under Review';
            break;
    }

    return "<span class='status-badge $badgeClass'>$text</span>";
}
