<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

// Start session before checking authentication
initSession();

// Check authentication and role
checkAuth(['admin']);  // This function checks if the user is authenticated and has the correct role

// Get the admin_bibliotheque ID
$admin_id = $_SESSION['user_id'];


$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Assign group to PROFESSORS
if ($action === 'assign_professor_to_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
    $rapporteur_id = !empty($_POST['rapporteur_id']) ? (int)$_POST['rapporteur_id'] : null;
    $examiner_id = !empty($_POST['examinateur_id']) ? (int)$_POST['examinateur_id'] : null;

    if ($group_id <= 0) {
        setFlashMessage('Please select a group.', 'danger');
        redirect('assignProfessorsToGroups.php');
    }

    if (empty($rapporteur_id) && empty($examiner_id)) {
        setFlashMessage('Please select at least one professor (rapporteur or examiner).', 'danger');
        redirect('assignProfessorsToGroups.php');
    }

    try {
        $pdo->beginTransaction();

        // Récupérer id_project depuis groupe
        $stmt = $pdo->prepare("SELECT id_project FROM groupe WHERE id = :group_id");
        $stmt->execute(['group_id' => $group_id]);
        $id_project = $stmt->fetchColumn();

        if (!$id_project) {
            throw new Exception('No project found for the selected group.');
        }

        // Insérer rapporteur
        if (!empty($rapporteur_id)) {
            $stmt = $pdo->prepare("INSERT INTO role (professor_id, role, id_project) VALUES (:professor_id, 'rapporteur', :id_project)");
            $success = $stmt->execute([
                'professor_id' => $rapporteur_id,
                'id_project' => $id_project
            ]);
            if (!$success) {
                throw new Exception('Failed to insert rapporteur.');
            }
        }

        // Insérer examinateur
        if (!empty($examiner_id)) {
            $stmt = $pdo->prepare("INSERT INTO role (professor_id, role, id_project) VALUES (:professor_id, 'examiner', :id_project)");
            $success = $stmt->execute([
                'professor_id' => $examiner_id,
                'id_project' => $id_project
            ]);
            if (!$success) {
                throw new Exception('Failed to insert examiner.');
            }
        }

        $pdo->commit();
        setFlashMessage('Professors have been successfully assigned to the group.', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlashMessage('Error during assignment: ' . $e->getMessage(), 'danger');
    }

    redirect('assignProfessorsToGroups.php');
}

// Modification  Professors Examiner And Reviewer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit_assignment') {
    $groupId = intval($_POST['group_id']);
    $rapporteurId = intval($_POST['rapporteur_id']);
    $examinateurId = intval($_POST['examinateur_id']);

    // Récupérer id_project du groupe
    $stmt = $pdo->prepare("SELECT id_project FROM groupe WHERE id = ?");
    $stmt->execute([$groupId]);
    $idProject = $stmt->fetchColumn();

    if ($idProject) {
        // Supprimer les anciens rôles rapporteur & examinateur pour ce projet
        $stmtDel = $pdo->prepare("DELETE FROM role WHERE id_project = ? AND role IN ('rapporteur', 'examiner')");
        $stmtDel->execute([$idProject]);

        // Insérer les nouveaux rôles
        $stmtInsert = $pdo->prepare("INSERT INTO role (id_project, professor_id, role) VALUES (?, ?, ?)");

        $stmtInsert->execute([$idProject, $rapporteurId, 'rapporteur']);
        $stmtInsert->execute([$idProject, $examinateurId, 'examiner']);

        setFlashMessage("L'affectation a été modifiée avec succès.", "success");
    } else {
        setFlashMessage("Projet introuvable pour ce groupe.", "danger");
    }

    header("Location: assignProfessorsToGroups.php");
    exit();
}



// Suppression Professors Examiner And Reviewer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_role' && isset($_POST['role_id'])) {
    $roleId = intval($_POST['role_id']);

    // 1. Récupérer id_project pour ce rôle
    $stmt = $pdo->prepare("SELECT id_project FROM role WHERE id = ?");
    $stmt->execute([$roleId]);
    $idProject = $stmt->fetchColumn();

    if ($idProject) {
        // 2. Supprimer uniquement les rôles 'rapporteur' et 'examiner' pour ce projet
        $stmtDelete = $pdo->prepare("DELETE FROM role WHERE id_project = ? AND role IN ('rapporteur', 'examiner')");
        $stmtDelete->execute([$idProject]);

        // Message de succès avec setFlashMessage
        setFlashMessage('The reviewer and examiner roles have been successfully deleted.', 'success');
    } else {
        // Message d'erreur si id_project non trouvé
        setFlashMessage('Erreur : No project found for this group.', 'danger');
    }

    // Redirection vers la même page (évite la soumission multiple)
    header('Location: assignProfessorsToGroups.php');
    exit;
}


// Assign group to project§
if ($action === 'assign_group_to_project' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $project_id = $_POST['project_id'];
    $group_id = $_POST['group_id'];

    // Valider que les deux champs sont non vides
    if (!empty($project_id) && !empty($group_id)) {
        try {
            // Préparer la requête SQL pour affecter le groupe à un projet
            $sql = "UPDATE groupe SET id_project = :project_id WHERE id = :group_id";

            // Préparer la déclaration PDO
            $stmt = $pdo->prepare($sql);

            // Lier les paramètres
            $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
            $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);

            // Exécuter la requête
            $stmt->execute();

            // Check if the update was successful
            if ($stmt->rowCount() > 0) {
                // Redirect or success message
                setFlashMessage("The group was successfully assigned to the project.", "success");
            } else {
                setFlashMessage("Error during assignment. Please try again.", "danger");
            }
        } catch (PDOException $e) {
            setFlashMessage("Database connection error: " . $e->getMessage(), "danger");
        }
    } else {
        setFlashMessage("All fields must be filled out.", "danger");
    }
    redirect('assignGroupsToProjects.php');
}

// Remove project from group
if ($_GET['action'] === 'remove_project_group' && isset($_GET['id'])) {
    $groupId = intval($_GET['id']);  // Get group ID and ensure it's an integer
    try {
        // Execute SQL query to remove the group from the project
        $stmt = $pdo->prepare("UPDATE groupe SET id_project = NULL WHERE id = :group_id");
        $stmt->bindParam(':group_id', $groupId, PDO::PARAM_INT);
        $stmt->execute();

        // Success message
        setFlashMessage("The group has been successfully removed from the project.", "success");
    } catch (Exception $e) {
        // Error message on failure
        setFlashMessage("Error while removing the group: " . $e->getMessage(), "danger");
    }

    // Redirect after action
    redirect('assignGroupsToProjects.php');
}

// Group management functions
if ($action === 'create_group') {
    $code = sanitizeInput($_POST['code'] ?? '');
    if (!empty($code)) {
        try {
            // Prepare query with named parameter :code
            $stmt = $pdo->prepare("INSERT INTO groupe (code) VALUES (:code)");

            // Bind parameter
            $stmt->bindParam(':code', $code, PDO::PARAM_STR);

            // Execute query
            if ($stmt->execute()) {
                setFlashMessage("Group « {$code} » created successfully!", "success");
            } else {
                // Throw exception with detailed error message
                throw new Exception("Query execution failed: " . implode(", ", $stmt->errorInfo()));
            }
        } catch (Exception $e) {
            // Show error message
            setFlashMessage("Error creating the group: " . $e->getMessage(), "danger");
        }
    } else {
        setFlashMessage("Group name cannot be empty.", "warning");
    }

    redirect('manageGroups.php');
}

// Assign one or more students to a selected group
if ($_POST['action'] === 'assign_group') {
    $groupeId = (int) ($_POST['groupe_id'] ?? 0);
    $etudiants = $_POST['etudiants'] ?? [];

    if ($groupeId > 0 && is_array($etudiants) && count($etudiants) > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE student SET id_groupe = :groupe_id WHERE id = :etudiant_id");

            foreach ($etudiants as $etudiantId) {
                $stmt->bindValue(':groupe_id', $groupeId, PDO::PARAM_INT);
                $stmt->bindValue(':etudiant_id', (int)$etudiantId, PDO::PARAM_INT);
                $stmt->execute();
            }

            setFlashMessage("Student(s) successfully assigned to the group.", "success");
        } catch (PDOException $e) {
            setFlashMessage("Error during assignment: " . $e->getMessage(), "danger");
        }
    } else {
        setFlashMessage("Please select a group and at least one student.", "warning");
    }

    redirect('manageGroups.php');
}

// Remove group from a student
if ($_GET['action'] === 'remove_from_group') {
    // Get student ID
    $etudiantId = (int)($_GET['id'] ?? 0);

    if ($etudiantId > 0) {
        try {
            // Query to set id_groupe to NULL, removing the student from the group
            $stmt = $pdo->prepare("UPDATE student SET id_groupe = NULL WHERE id = :etudiant_id");
            $stmt->bindParam(':etudiant_id', $etudiantId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                setFlashMessage("The student has been successfully removed from the group.", "success");
            } else {
                throw new Exception("Failed to remove the student.");
            }
        } catch (PDOException $e) {
            setFlashMessage("Error during removal: " . $e->getMessage(), "danger");
        }
    } else {
        setFlashMessage("Invalid student ID.", "warning");
    }

    // Redirect after operation
    redirect('manageGroups.php');
}

// If we get here, something went wrong
setFlashMessage('Invalid action', 'danger');
redirect('projectList.php');
