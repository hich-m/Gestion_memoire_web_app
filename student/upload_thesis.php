<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

// Function to send email to examiners and supervisors
function sendNotification($recipientEmail, $recipientName, $studentName, $projectTitle, $role) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'portailumbb@gmail.com';
        $mail->Password = 'gotdtrkymtvkreah'; // Use app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Enable debugging
        $mail->SMTPDebug = 2; // 2 = verbose debug output
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: $str");
        };
        
        // Set important headers to avoid spam filters
        $mail->XMailer = 'Portail UMBB Mailer';
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_8BIT;
        
        // Sender and recipient settings
        $mail->setFrom('portailumbb@gmail.com', 'Portail UMBB', false);
        $mail->addReplyTo('portailumbb@gmail.com', 'Portail UMBB');
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Add a custom Message-ID header with your domain
        $mail->MessageID = '<' . time() . '.' . md5($recipientEmail . $projectTitle) . '@gmail.com>';
        
        // Add an unsubscribe header (helps with spam prevention)
        $mail->addCustomHeader('List-Unsubscribe', '<mailto:portailumbb@gmail.com?subject=unsubscribe>');
        
        // Content settings
        $mail->isHTML(false); // Plain text is less likely to be marked as spam
        
        // Different subject and message based on role
        if ($role === 'examiner') {
            $mail->Subject = 'Nouveau dépôt de mémoire à évaluer';
            $textBody = "Bonjour $recipientName,\n\n" .
                       "L'étudiant $studentName a déposé son mémoire pour le projet intitulé :\n" .
                       "$projectTitle\n\n" .
                       "En tant qu'examinateur, vous pouvez maintenant accéder au portail pour examiner ce mémoire et fournir votre évaluation.\n\n" .
                       "Accéder au portail: https://portail.umbb.edu.dz/login.php\n\n" .
                       "Ceci est un message automatique, merci de ne pas y répondre directement.\n" .
                       "© " . date('Y') . " Portail UMBB - Université M'Hamed Bougara de Boumerdès";
        } else { // supervisor
            $mail->Subject = 'Nouveau dépôt de mémoire par votre étudiant';
            $textBody = "Bonjour $recipientName,\n\n" .
                       "L'étudiant $studentName a déposé son mémoire pour le projet intitulé :\n" .
                       "$projectTitle\n\n" .
                       "En tant qu'encadreur, vous pouvez maintenant accéder au portail pour consulter ce mémoire.\n\n" .
                       "Accéder au portail: https://portail.umbb.edu.dz/login.php\n\n" .
                       "Ceci est un message automatique, merci de ne pas y répondre directement.\n" .
                       "© " . date('Y') . " Portail UMBB - Université M'Hamed Bougara de Boumerdès";
        }
        
        $mail->Body = $textBody;
        
        // Set priority
        $mail->Priority = 1; // High priority
        
        // Attempt to send and log the result
        $result = $mail->send();
        error_log("Email sent to " . ($role === 'examiner' ? 'examiner' : 'supervisor') . " $recipientEmail for project $projectTitle: " . ($result ? 'Success' : 'Failed'));
        return $result;
    } catch (Exception $e) {
        error_log("Email Error: " . $e->getMessage() . " - " . $mail->ErrorInfo);
        return false;
    }
}

// Wrapper function for backward compatibility
function sendExaminerNotification($examinerEmail, $examinerName, $studentName, $projectTitle) {
    return sendNotification($examinerEmail, $examinerName, $studentName, $projectTitle, 'examiner');
}

// New function to send email to supervisor
function sendSupervisorNotification($supervisorEmail, $supervisorName, $studentName, $projectTitle) {
    return sendNotification($supervisorEmail, $supervisorName, $studentName, $projectTitle, 'supervisor');
}

function slugify($text)
{
    $text = strtolower(trim($text));
    // Remplace les caractères spéciaux et les espaces par des _
    $text = preg_replace('/[^a-z0-9]+/', '_', $text);
    return trim($text, '_');
}

// Initialize session and check authentication
initSession();
checkAuth(['student']);

$student_id = $_SESSION['user_id'];

$successMessage = $_SESSION['upload_success'] ?? '';
$errorMessage = $_SESSION['upload_error'] ?? '';

// Clear messages after displaying
unset($_SESSION['upload_success'], $_SESSION['upload_error']);

// Check if deadline has passed
$stmt = $pdo->prepare("
    SELECT p.deadline
    FROM student s
    JOIN groupe g ON s.id_groupe = g.id
    JOIN project p ON g.id_project = p.id
    WHERE s.user_id = ?
");
$stmt->execute([$student_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$deadline = $result['deadline'] ?? null;

// Check if deadline has passed
$deadlinePassed = false;
if ($deadline) {
    $timezone = new DateTimeZone('Africa/Algiers');
    $today = new DateTime('now', $timezone);
    try {
        $deadlineDate = new DateTime($deadline, $timezone);
        if ($today > $deadlineDate) {
            $deadlinePassed = true;
        }
    } catch (Exception $e) {
        $deadlinePassed = true;
    }
}

// Check if thesis is already approved
$stmt = $pdo->prepare("
    SELECT t.status 
    FROM student s 
    JOIN groupe g ON s.id_groupe = g.id 
    JOIN project p ON g.id_project = p.id 
    JOIN pv_thesis pv ON pv.id_project = p.id 
    JOIN thesis t ON t.id_pv = pv.id 
    WHERE s.user_id = ? AND t.is_deleted = 0
");
$stmt->execute([$student_id]);
$thesis = $stmt->fetch(PDO::FETCH_ASSOC);
$thesisApproved = ($thesis && $thesis['status'] === 'approved');

// If deadline passed or thesis already approved, redirect with error
if ($deadlinePassed) {
    $_SESSION['upload_error'] = "La date limite de dépôt est dépassée. Vous ne pouvez plus déposer votre mémoire.";
    header("Location: upload.php");
    exit;
} elseif ($thesisApproved) {
    $_SESSION['upload_error'] = "Votre mémoire a déjà été validé. Le dépôt de nouveaux fichiers n'est plus autorisé.";
    header("Location: upload.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['thesis_file']) || $_FILES['thesis_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['upload_error'] = "Aucun fichier reçu ou une erreur s'est produite lors du téléchargement.";
        header("Location: upload.php");
        exit;
    }

    $file = $_FILES['thesis_file'];
    
    // Vérifier le type de fichier
    $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $fileType = mime_content_type($file['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        $_SESSION['upload_error'] = "Le fichier doit être au format PDF, DOC ou DOCX.";
        header("Location: upload.php");
        exit;
    }

    // Récupérer les informations du projet et de l'étudiant
    $stmt = $pdo->prepare("
        SELECT 
            CONCAT(u.first_name, ' ', u.last_name) AS student_name,
            p.id AS project_id,
            p.title AS project_title,
            g.code AS group_code,
            pv.id AS pv_id
        FROM student s
        JOIN user u ON s.user_id = u.id
        JOIN groupe g ON s.id_groupe = g.id
        JOIN project p ON g.id_project = p.id
        LEFT JOIN pv_thesis pv ON pv.id_project = p.id
        WHERE s.user_id = ?
    ");
    $stmt->execute([$student_id]);
    $projectInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$projectInfo) {
        $_SESSION['upload_error'] = "Impossible de trouver les informations du projet.";
        header("Location: upload.php");
        exit;
    }
    
    // Vérifier si le PV existe
    if (!$projectInfo['pv_id']) {
        $_SESSION['upload_error'] = "Vous devez d'abord déposer la fiche d'évaluation (PV) avant de pouvoir déposer votre mémoire.";
        header("Location: upload.php");
        exit;
    }
    
    // Generate a unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $originalName = $file['name'];
    $generatedName = uniqid('thesis_') . '.' . $extension;

    // Ensure the uploads directory exists
    $uploadDir = '../uploads/memoires/' . $projectInfo['project_title'] . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Set the paths
    $absolutePath = $uploadDir . $generatedName;
    $relativePath = 'uploads/memoires/' . $projectInfo['project_title'] . '/' . $generatedName;

    // Log the paths for debugging
    error_log("Absolute path: " . $absolutePath);
    error_log("Relative path: " . $relativePath);

    // Vérifier s'il existe déjà un mémoire pour ce PV
    $stmtCheck = $pdo->prepare("
        SELECT id FROM thesis
        WHERE id_pv = ? AND is_deleted = 0
    ");
    $stmtCheck->execute([$projectInfo['pv_id']]);
    $existingThesis = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    // Déplacer le fichier
    if (move_uploaded_file($file['tmp_name'], $absolutePath)) {
        $fileSize = $file['size'];
        
        if ($existingThesis) {
            // Mettre à jour le mémoire existant
            $stmt = $pdo->prepare("
                UPDATE thesis 
                SET filename = ?, 
                    original_filename = ?, 
                    file_path = ?, 
                    status = 'pending', 
                    feedback = NULL,
                    submitted_at = NOW(), 
                    reviewed_at = NULL, 
                    reviewed_by = NULL, 
                    fileSize = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $generatedName,
                $originalName,
                $relativePath,
                $fileSize,
                $existingThesis['id']
            ]);
            
            $_SESSION['upload_success'] = "✅ Mémoire mis à jour avec succès: <strong>$originalName</strong>. Les examinateurs ont été notifiés.";
        } else {
            // Insérer un nouveau mémoire
            $stmt = $pdo->prepare("
                INSERT INTO thesis (
                    id_pv, filename, original_filename, file_path, status, feedback,
                    submitted_at, reviewed_at, reviewed_by, is_deleted, fileSize
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NULL, NULL, 0, ?)
            ");
            
            $stmt->execute([
                $projectInfo['pv_id'],
                $generatedName,
                $originalName,
                $relativePath,
                'pending',
                NULL,
                $fileSize
            ]);
            
            $_SESSION['upload_success'] = "✅ Mémoire déposé avec succès: <strong>$originalName</strong>. Les examinateurs ont été notifiés.";
        }
        
        $_SESSION['last_uploaded_file'] = $originalName;
        
        // Send email notifications
        $projectId = $projectInfo['project_id'];
        $studentName = $projectInfo['student_name'];
        $projectTitle = $projectInfo['project_title'];
        
        // Get the examiners for this project
        $stmt = $pdo->prepare("
            SELECT 
                CONCAT(u.first_name, ' ', u.last_name) AS examiner_name,
                a.email AS examiner_email
            FROM role r
            JOIN professor p ON r.professor_id = p.id
            JOIN user u ON p.user_id = u.id
            JOIN account a ON u.id = a.id_user
            WHERE r.id_project = ? AND r.role = 'examiner' AND u.is_deleted = 0
        ");
        
        if (!$stmt->execute([$projectId])) {
            error_log("SQL Error fetching examiners: " . implode(", ", $stmt->errorInfo()));
        }
        
        $examiners = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Found " . count($examiners) . " examiners for project ID: $projectId");
        
        // Get the supervisors for this project
        $stmt = $pdo->prepare("
            SELECT 
                CONCAT(u.first_name, ' ', u.last_name) AS supervisor_name,
                a.email AS supervisor_email
            FROM role r
            JOIN professor p ON r.professor_id = p.id
            JOIN user u ON p.user_id = u.id
            JOIN account a ON u.id = a.id_user
            WHERE r.id_project = ? AND r.role = 'supervisor' AND u.is_deleted = 0
        ");
        
        if (!$stmt->execute([$projectId])) {
            error_log("SQL Error fetching supervisors: " . implode(", ", $stmt->errorInfo()));
        }
        
        $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Found " . count($supervisors) . " supervisors for project ID: $projectId");
        
        // Send emails to all examiners
        foreach ($examiners as $examiner) {
            sendExaminerNotification(
                $examiner['examiner_email'],
                $examiner['examiner_name'],
                $studentName,
                $projectTitle
            );
        }

        // Send emails to all supervisors
        foreach ($supervisors as $supervisor) {
            sendSupervisorNotification(
                $supervisor['supervisor_email'],
                $supervisor['supervisor_name'],
                $studentName,
                $projectTitle
            );
        }
    } else {
        $_SESSION['upload_error'] = "Erreur lors de l'enregistrement du fichier.";
    }
    
    header("Location: upload.php");
    exit;
}
