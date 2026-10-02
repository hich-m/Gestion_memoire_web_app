<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

// Initialiser la session avant de vérifier l'authentification
initSession();

// Vérification d'authentification et de rôle
checkAuth(['admin']);  // Cette fonction vérifie si l'utilisateur est authentifié et a le bon rôle

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['deadline'])) {
        try {
            // Format the deadline properly
            $deadline = str_replace('T', ' ', $_POST['deadline']) . ':00';
            
            // Validate the date format
            $date = new DateTime($deadline);
            $formattedDeadline = $date->format('Y-m-d H:i:s');
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update all projects with the new deadline
            $stmt = $pdo->prepare("UPDATE project SET deadline = ?");
            $stmt->execute([$formattedDeadline]);
            
            // Commit transaction
            $pdo->commit();
            
            setFlashMessage("Date limite fixée avec succès pour tous les étudiants: " . date('d/m/Y H:i', strtotime($formattedDeadline)), 'success');
        } catch (Exception $e) {
            // Rollback in case of error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error setting deadline: " . $e->getMessage());
            setFlashMessage("Erreur lors de la définition de la date limite: " . $e->getMessage(), 'danger');
        }
    } else {
        setFlashMessage("Veuillez saisir une date valide.", 'warning');
    }
    
    header('Location: dashboard.php');
    exit;
} else {
    setFlashMessage("Requête non autorisée.", 'danger');
    header('Location: dashboard.php');
    exit;
}
