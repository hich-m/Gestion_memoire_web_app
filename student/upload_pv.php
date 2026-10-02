<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

initSession();
checkAuth(['student']);

function slugify($text)
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '_', $text);
    return trim($text, '_');
}

$student_id = $_SESSION['user_id'];

$successMessage = $_SESSION['upload_success'] ?? '';
$errorMessage = $_SESSION['upload_error'] ?? '';
unset($_SESSION['upload_success'], $_SESSION['upload_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['pv_file']) || $_FILES['pv_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['upload_error'] = "No file received or an error occurred during upload.";
        header("Location: uploadPV.php");
        exit;
    }

    $file = $_FILES['pv_file'];

    // Vérification de l'extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        $_SESSION['upload_error'] = "The file must be in PDF format.";
        header("Location: uploadPV.php");
        exit;
    }

    // Récupération du projet et du groupe
    $stmt = $pdo->prepare("
        SELECT p.id AS project_id, g.code AS group_code
        FROM student s
        JOIN groupe g ON s.id_groupe = g.id
        JOIN project p ON g.id_project = p.id
        WHERE s.user_id = ?
    ");
    $stmt->execute([$student_id]);
    $data = $stmt->fetch();

    if (!$data) {
        $_SESSION['upload_error'] = "Unable to find your group or project.";
        header("Location: uploadPV.php");
        exit;
    }

    $projectId = $data['project_id'];
    $groupCode = strtoupper($data['group_code']);
    $slugGroup = slugify($groupCode);
    $filename = $slugGroup . "_PV.pdf";

    $relativeDir = "uploads/pv/";
    $relativePath = $relativeDir . $filename;
    $targetDir = __DIR__ . "/../" . $relativeDir;
    $targetPath = $targetDir . $filename;

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // Vérifie si un PV existe déjà
    $stmt = $pdo->prepare("SELECT id FROM pv_thesis WHERE id_project = ?");
    $stmt->execute([$projectId]);
    if ($stmt->fetch()) {
        $_SESSION['upload_error'] = "A PV has already been submitted for this project.";
        header("Location: uploadPV.php");
        exit;
    }

    // Déplacement du fichier
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $_SESSION['upload_error'] = "Error while saving the file.";
        header("Location: uploadPV.php");
        exit;
    }

    // Insertion en base
    $stmt = $pdo->prepare("
        INSERT INTO pv_thesis (id_project, file_path_pv, filename)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$projectId, $relativePath, $filename]);

    $_SESSION['upload_success'] = "PV successfully uploaded with the name: <strong>$filename</strong>";
    header("Location: uploadPV.php");
    exit;
}
