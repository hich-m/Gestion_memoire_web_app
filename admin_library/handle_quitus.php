<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

// Send email to student using PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

function sendQuitusEmail($to, $studentName, $status, $remark = null) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'portailumbb@gmail.com'; // Change to your email
        $mail->Password = 'gotdtrkymtvkreah'; // Use an app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom('portailumbb@gmail.com', 'Bibliotheque UMBB');
        $mail->addAddress($to, $studentName);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8'; // Set character encoding to UTF-8
        
        if ($status === 'ready') {
            $mail->Subject = 'Quitus accepté';
            $mail->Body = "Bonjour $studentName,<br><br>Votre quitus a été <b>accepté</b> par la bibliothèque. Félicitations !<br><br>Cordialement,<br>Bibliothèque UMBB";
        } elseif ($status === 'not_ready') {
            $mail->Subject = 'Quitus refusé - Action requise';
            $mail->Body = "Bonjour $studentName,<br><br>Votre quitus a été <b>refusé</b> ou nécessite une modification.<br>Raison : <i>" . htmlspecialchars($remark) . "</i><br><br>Merci de corriger et de soumettre à nouveau.<br><br>Cordialement,<br>Bibliothèque UMBB";
        }
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Erreur d\'envoi de mail quitus: ' . $mail->ErrorInfo);
        return false;
    }
}

initSession();
checkAuth(['admin_library']);

// Securely fetch POST data
$student_id = $_POST['id_etudiant'] ?? null;
$action_choice = $_POST['action_choice'] ?? null;

if ($student_id && $action_choice) {
    // Get student email and name
    $sql = "SELECT a.email, u.first_name, u.last_name FROM student s 
            JOIN user u ON s.user_id = u.id 
            JOIN account a ON u.id = a.id_user
            WHERE s.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        $studentEmail = $student['email'];
        $studentName = $student['first_name'] . ' ' . $student['last_name'];
        
        if ($action_choice === 'generate_quitus') {
            // Update quitus status in the database
            $sql = "UPDATE student SET feedback = NULL, quitus_status = 'ready' WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $student_id]);
            
            // Send email notification
            sendQuitusEmail($studentEmail, $studentName, 'ready');
            
            setFlashMessage("The quitus has been successfully granted to the student.", "success");
        } elseif ($action_choice === 'send_remark') {
            $remark = trim($_POST['remark'] ?? '');

            if (!empty($remark)) {
                $sql = "UPDATE student SET feedback = :remark, quitus_status = 'not_ready' WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':remark' => $remark, ':id' => $student_id]);
                
                // Send email notification with remark
                sendQuitusEmail($studentEmail, $studentName, 'not_ready', $remark);
                
                setFlashMessage("The remark has been successfully sent.", "success");
            } else {
                setFlashMessage("Remark cannot be empty.", "warning");
            }
        } else {
            setFlashMessage("Invalid action selected.", "danger");
        }
    } else {
        setFlashMessage("Student not found.", "danger");
    }
} else {
    setFlashMessage("Missing data. Please complete the form.", "warning");
}

// Redirect to the quitus management page
header("Location: quitus.php");
exit;
