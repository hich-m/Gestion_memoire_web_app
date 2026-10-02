<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

// Initialize session and auth
initSession();
checkAuth(['admin']);

// Fonction d'envoi d'email de notification
function sendStatusEmail($to, $status) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'portailumbb@gmail.com';
        $mail->Password = 'gotdtrkymtvkreah'; // App password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Anti-spam settings
        $mail->XMailer = 'Portail UMBB Mailer';
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_8BIT;
        
        // Set important headers to avoid spam filters
        $mail->setFrom('portailumbb@gmail.com', 'Portail UMBB', false);
        $mail->addReplyTo('portailumbb@gmail.com', 'Portail UMBB Support');
        $mail->addAddress($to);
        
        // Add a custom Message-ID header with your domain
        $mail->MessageID = '<' . time() . '.' . md5($to . $status) . '@gmail.com>';
        
        // Add an unsubscribe header (helps with spam prevention)
        $mail->addCustomHeader('List-Unsubscribe', '<mailto:portailumbb@gmail.com?subject=unsubscribe>');
        
        // Content settings - simple plain text
        $mail->isHTML(false); // Plain text emails are less likely to be marked as spam
        $mail->Subject = 'Mise à jour du statut de votre compte';
        
        // Create simple text email with different messages based on status
        if ($status === 'approved') {
            $textBody = "Cher(e) utilisateur/utilisatrice,\n\n" .
                       "Nous vous informons que votre compte a été approuvé.\n\n" .
                       "Vous pouvez maintenant vous connecter à votre compte et accéder à toutes les fonctionnalités du portail.\n\n" .
                       "Cordialement,\n" .
                       "L'équipe Portail UMBB";
        } elseif ($status === 'rejected') {
            $textBody = "Cher(e) utilisateur/utilisatrice,\n\n" .
                       "Nous vous informons que votre compte a été refusé.\n\n" .
                       "Vous ne pourrez pas vous connecter au portail. Pour plus d'informations, veuillez contacter l'administration.\n\n" .
                       "Cordialement,\n" .
                       "L'équipe Portail UMBB";
        } else {
            $textBody = "Cher(e) utilisateur/utilisatrice,\n\n" .
                       "Nous vous informons que le statut de votre compte a été mis à jour.\n\n" .
                       "Cordialement,\n" .
                       "L'équipe Portail UMBB";
        }
        
        $mail->Body = $textBody;
        
        // Set priority
        $mail->Priority = 3; // Normal priority (1=High, 5=Low)
        
        $mail->send();
        error_log("Email sent to $to for status $status");
        return true;
    } catch (Exception $e) {
        error_log("Email Error: {$mail->ErrorInfo}");
        return false;
    }
}

$action = $_REQUEST['action'] ?? '';
$id = (int) ($_REQUEST['id'] ?? ($_POST['id'] ?? 0));

if (empty($action)) {
    setFlashMessage('Invalid action', 'danger');
    redirect('dashboard.php');
}

// Accept account
if ($action === 'accept' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE account SET status = 'approved' WHERE id_user = ?");
    if ($stmt->execute([$id])) {
        // Récupérer l'email depuis la table account
        $stmtEmail = $pdo->prepare("SELECT email FROM account WHERE id_user = ?");
        $stmtEmail->execute([$id]);
        $user = $stmtEmail->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['email'])) {
            sendStatusEmail($user['email'], 'approved');
        } else {
            error_log("No email found for user id: $id (accept)");
        }
        setFlashMessage('Account approved successfully', 'success');
    } else {
        setFlashMessage('Failed to approve account', 'danger');
    }
    redirect('dashboard.php');
}

// Reject account
if ($action === 'reject' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE account SET status = 'rejected' WHERE id_user = ?");
    if ($stmt->execute([$id])) {
        // Récupérer l'email depuis la table account
        $stmtEmail = $pdo->prepare("SELECT email FROM account WHERE id_user = ?");
        $stmtEmail->execute([$id]);
        $user = $stmtEmail->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['email'])) {
            sendStatusEmail($user['email'], 'rejected');
        } else {
            error_log("No email found for user id: $id (reject)");
        }
        setFlashMessage('Account rejected successfully', 'success');
    } else {
        setFlashMessage('Failed to reject account', 'danger');
    }
    redirect('dashboard.php');
}

// Delete account
if ($action === 'delete' && $id > 0) {
    // Récupérer l'email depuis la table account AVANT suppression
    $stmtEmail = $pdo->prepare("SELECT email FROM account WHERE id_user = ?"); 
    $stmtEmail->execute([$id]);
    $account = $stmtEmail->fetch(PDO::FETCH_ASSOC);
    $userEmail = $account && !empty($account['email']) ? $account['email'] : null;

    $stmt = $pdo->prepare("SELECT * FROM user WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Mark account as deleted and rejected
        $pdo->prepare("UPDATE user SET is_deleted = 1 WHERE id = :id")->execute(['id' => $id]);
        $pdo->prepare("UPDATE account SET is_deleted = 1, status = 'rejected' WHERE id_user = :id")->execute(['id' => $id]);

        if ($user['role'] === 'student') {
            $pdo->prepare("UPDATE student SET is_deleted = 1, id_groupe = NULL WHERE user_id = :id")->execute(['id' => $id]);
        } elseif ($user['role'] === 'professor') {
            $pdo->prepare("UPDATE professor SET is_deleted = 1 WHERE user_id = :id")->execute(['id' => $id]);

            $stmtProf = $pdo->prepare("SELECT id FROM professor WHERE user_id = :id");
            $stmtProf->execute(['id' => $id]);
            $professor = $stmtProf->fetch(PDO::FETCH_ASSOC);
            if ($professor) {
                $prof_id = $professor['id'];
                $pdo->prepare("UPDATE role SET role = 'deleted' WHERE professor_id = :prof_id")->execute(['prof_id' => $prof_id]);
                $pdo->prepare("UPDATE project SET is_deleted = 1 WHERE id IN (SELECT id_project FROM role WHERE professor_id = :prof_id)")->execute(['prof_id' => $prof_id]);
            }
        }

        if ($userEmail) {
            sendStatusEmail($userEmail, 'rejected');
        } else {
            error_log("No email found for user id: $id (delete)");
        }

        setFlashMessage("Utilisateur supprimé avec succès.", 'success');
    } else {
        setFlashMessage("Utilisateur introuvable.", 'danger');
    }

    redirect('dashboard.php');
}


setFlashMessage('Invalid action', 'danger');
redirect('dashboard.php');







