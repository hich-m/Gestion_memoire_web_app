<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

// Function to send email notification to student
function sendThesisReviewEmail($to, $studentName, $status, $feedback, $examinerName) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'portailumbb@gmail.com';
        $mail->Password = 'gotdtrkymtvkreah'; // Use app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('portailumbb@gmail.com', 'Portail UMBB');
        $mail->addAddress($to, $studentName);
        
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8'; // Set character encoding to UTF-8
        
        if ($status === 'APPROVED') {
            $mail->Subject = 'Mémoire approuvé';
            $mail->Body = "Bonjour $studentName,<br><br>
                          Votre mémoire a été <b>approuvé</b> par l'examinateur $examinerName.<br><br>
                          Commentaire: <i>" . nl2br(htmlspecialchars($feedback)) . "</i><br><br>
                          Félicitations !<br><br>
                          Cordialement,<br>
                          Portail UMBB";
        } else if ($status === 'MODIFICATIONREQUEST') {
            $mail->Subject = 'Modifications requises pour votre mémoire';
            $mail->Body = "Bonjour $studentName,<br><br>
                          L'examinateur $examinerName a demandé des <b>modifications</b> pour votre mémoire.<br><br>
                          Commentaire: <i>" . nl2br(htmlspecialchars($feedback)) . "</i><br><br>
                          Veuillez effectuer les modifications nécessaires et soumettre une nouvelle version de votre mémoire.<br><br>
                          Cordialement,<br>
                          Portail UMBB";
        }
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Erreur d\'envoi de mail à l\'étudiant: ' . $mail->ErrorInfo);
        return false;
    }
}

// Initialiser la session avant de vérifier l'authentification
initSession();

// Vérification d'authentification et de rôle
checkAuth(['professor']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id_these'];
    $status = $_POST['status'];
    $feedback = $_POST['feedback'];
    
    // Get professor name
    $professor_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        SELECT CONCAT(u.first_name, ' ', u.last_name) AS examiner_name
        FROM user u
        WHERE u.id = ?
    ");
    $stmt->execute([$professor_id]);
    $examiner = $stmt->fetch(PDO::FETCH_ASSOC);
    $examinerName = $examiner['examiner_name'] ?? 'Examinateur';
    
    // Update thesis status
    $stmt = $pdo->prepare("
        UPDATE thesis 
        SET status = ?, feedback = ?, reviewed_at = NOW(), reviewed_by = ? 
        WHERE id = ?
    ");
    $stmt->execute([$status, $feedback, $professor_id, $id]);
    
    // Get student email and name
    $stmt = $pdo->prepare("
        SELECT 
            a.email,
            CONCAT(u.first_name, ' ', u.last_name) AS student_name,
            g.id AS group_id
        FROM thesis t
        JOIN pv_thesis pv ON t.id_pv = pv.id
        JOIN project p ON pv.id_project = p.id
        JOIN groupe g ON g.id_project = p.id
        JOIN student s ON s.id_groupe = g.id
        JOIN user u ON s.user_id = u.id
        JOIN account a ON u.id = a.id_user
        WHERE t.id = ?
    ");
    $stmt->execute([$id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Send email to each student in the group
    foreach ($students as $student) {
        sendThesisReviewEmail(
            $student['email'],
            $student['student_name'],
            $status,
            $feedback,
            $examinerName
        );
    }
    
    echo "Success";
    exit;
} else {
    http_response_code(405); // Method Not Allowed
    echo "Method not allowed";
    exit;
}
