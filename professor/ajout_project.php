<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

// Initialiser la session avant de vérifier l'authentification
initSession();

// Vérification d'authentification et de rôle
checkAuth(['professor']);  // Cette fonction vérifie si l'utilisateur est authentifié et a le bon rôle

// Récupération de l'ID de professor
$professorAdmin = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $key_words = $_POST['key_words'] ?? '';
    $niveau = $_POST['niveau'] ?? '';
    $type = $_POST['type'] ?? '';
    $lieu = $_POST['lieu'] ?? '';
    $userId = $_SESSION['user_id'];

    if (empty($title)) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'Le titre du projet est requis.'
        ];
        header('Location: dashboard.php'); // page de retour
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM professor WHERE user_id = ?");
    $stmt->execute([$userId]);
    $professor = $stmt->fetch();

    if (!$professor) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'Professeur introuvable.'
        ];
        header('Location: dashboard.php');
        exit;
    }

    $professorId = $professor['id'];

    $query = "INSERT INTO project (title, description, key_words, level, type, place)
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);

    if ($stmt->execute([$title, $description, $key_words, $niveau, $type, $lieu])) {
        $projectId = $pdo->lastInsertId();

        $roleQuery = "INSERT INTO role (professor_id, id_project, role)
                      VALUES (?, ?, ?)";
        $roleStmt = $pdo->prepare($roleQuery);
        $role = 'supervisor';

        if ($roleStmt->execute([$professorId, $projectId, $role])) {
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Projet ajouté avec succès.'
            ];
        } else {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'Erreur lors de l’attribution du rôle.'
            ];
        }
    } else {
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'Erreur lors de l’ajout du projet.'
        ];
    }

    header('Location: dashboard.php'); // redirection finale
    exit;
}
