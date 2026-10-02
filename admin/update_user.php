<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['user_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    try {
        // Mise à jour user
        $stmt = $pdo->prepare("UPDATE user SET first_name = ?, last_name = ?, role = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $role, $id]);

        // Mise à jour email dans account
        $stmt = $pdo->prepare("UPDATE account SET email = ?, status = ?  WHERE id_user = ?");
        $stmt->execute([$email, $status, $id]);

        // Champs spécifiques
        if ($role === 'professor') {
            $grade = $_POST['grade'] ?? '';
            $stmt = $pdo->prepare("UPDATE professor SET grade = ? WHERE user_id = ?");
            $stmt->execute([$grade, $id]);
        } elseif ($role === 'student') {
            $password = $_POST['password'] ?? null;
            $deadline = $_POST['deadline'] ?? null;

            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE account SET password = ? WHERE id_user = ?");
                $stmt->execute([$hashedPassword, $id]);
            }

            if (!empty($deadline)) {
                $stmt = $pdo->prepare("
                    UPDATE project p
                    JOIN groupe g ON g.id_project = p.id
                    JOIN student s ON s.id_groupe = g.id
                    SET p.deadline = ?
                    WHERE s.user_id = ?
                ");
                $stmt->execute([$deadline, $id]);
            }
        }

        setFlashMessage('Utilisateur mis à jour avec succès.', 'success');
    } catch (Exception $e) {
        setFlashMessage('Erreur : ' . $e->getMessage(), 'danger');
    }

    // Redirection vers la page d'origine ou liste
    header('Location: dashboard.php');
    exit;
} else {
    setFlashMessage('Requête invalide.', 'warning');
    header('Location: dashboard.php');
    exit;
}
