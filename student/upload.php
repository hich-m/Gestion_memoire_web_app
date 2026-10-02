<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
$currentPage = basename($_SERVER['PHP_SELF']);

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
        
        // Enable detailed debugging
        $mail->SMTPDebug = 3; // 3 = even more verbose debug output
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug [$level]: $str");
        };
        
        // Set important headers to avoid spam filters
        $mail->XMailer = 'Portail UMBB Mailer';
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_8BIT;
        
        // Sender and recipient settings - ensure these are correct
        $mail->setFrom('portailumbb@gmail.com', 'Portail UMBB');
        $mail->addReplyTo('portailumbb@gmail.com', 'Portail UMBB');
        
        // Verify email address format before adding
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid email format: $recipientEmail");
            return false;
        }
        
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content settings
        $mail->isHTML(false); // Use plain text to avoid formatting issues
        
        // Different subject and message based on role
        if ($role === 'examiner') {
            $mail->Subject = 'Nouveau dépôt de mémoire à évaluer';
            $textBody = "Bonjour $recipientName,\n\n" .
                       "L'étudiant $studentName a déposé son mémoire pour le projet intitulé :\n" .
                       "$projectTitle\n\n" .
                       "En tant qu'examinateur, vous pouvez maintenant accéder au portail pour examiner ce mémoire et fournir votre évaluation.\n\n" .
                       "Ceci est un message automatique, merci de ne pas y répondre directement.\n" .
                       "© " . date('Y') . " Portail UMBB - Université M'Hamed Bougara de Boumerdès";
        } else { // supervisor
            $mail->Subject = 'Nouveau dépôt de mémoire par votre étudiant';
            $textBody = "Bonjour $recipientName,\n\n" .
                       "L'étudiant $studentName a déposé son mémoire pour le projet intitulé :\n" .
                       "$projectTitle\n\n" .
                       "En tant qu'encadreur, vous pouvez maintenant accéder au portail pour consulter ce mémoire.\n\n" .
                       "Ceci est un message automatique, merci de ne pas y répondre directement.\n" .
                       "© " . date('Y') . " Portail UMBB - Université M'Hamed Bougara de Boumerdès";
        }
        
        $mail->Body = $textBody;
        
        // Set priority
        $mail->Priority = 1; // High priority
        
        // Attempt to send and log the result
        $result = $mail->send();
        error_log("Email sent to " . ($role === 'examiner' ? 'examiner' : 'supervisor') . " $recipientEmail for project $projectTitle: " . ($result ? 'Success' : 'Failed'));
        
        if (!$result) {
            error_log("Email sending failed. Last error: " . $mail->ErrorInfo);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Email Error: " . $e->getMessage());
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
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

// Initialiser la session
initSession();

// Vérification d'authentification et de rôle
checkAuth(['student']);

// ID de l'utilisateur connecté
$admin_id = $_SESSION['user_id'];

try {
    // Récupération infos de base de l'utilisateur
    $stmt = $pdo->prepare("
        SELECT u.first_name, u.last_name, a.email 
        FROM user u 
        JOIN account a ON u.id = a.id_user 
        WHERE u.id = ?
    ");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();

    if (!$admin) {
        throw new Exception("Utilisateur introuvable");
    }

    $nomComplet = htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']);
    $email = htmlspecialchars($admin['email']);

    // Récupérer ID étudiant
    $stmt = $pdo->prepare("SELECT id FROM student WHERE user_id = ?");
    $stmt->execute([$admin_id]);
    $studentBasic = $stmt->fetch();

    if (!$studentBasic) {
        throw new Exception("Aucun étudiant associé à cet utilisateur.");
    }

    $studentId = $studentBasic['id'];

    // Récupérer les détails du projet, groupe, encadrants, etc.
    $stmt = $pdo->prepare("
        SELECT 
            u.first_name,
            u.last_name,
            s.matricule,
            d.title AS department,

            g.code AS group_code,

            p.id AS project_id,
            p.title AS project_title,
            p.description AS project_description,
            p.key_words,
            p.level AS project_level,
            p.type AS project_type,
            p.place,
            p.created_at,

            r.role AS project_role,
            pu.first_name AS professor_first_name,
            pu.last_name AS professor_last_name,
            prof.grade AS professor_grade

        FROM student s
        JOIN user u ON s.user_id = u.id
        LEFT JOIN account a ON a.id_user = u.id
        LEFT JOIN departement d ON s.department_id = d.departement_id
        LEFT JOIN groupe g ON s.id_groupe = g.id
        LEFT JOIN project p ON g.id_project = p.id
        LEFT JOIN role r ON r.id_project = p.id
        LEFT JOIN professor prof ON r.professor_id = prof.id
        LEFT JOIN user pu ON prof.user_id = pu.id

        WHERE s.user_id = ?
    ");
    $stmt->execute([$admin_id]);
    $studentDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors de la récupération des données.";
    header('Location: login.php');
    exit();
}
// Initialize success and error variables
$success = '';
$error = '';


$stmt = $pdo->prepare("SELECT p.id AS project_id, g.code AS group_code,
     t.status FROM student s 
     JOIN groupe g ON s.id_groupe = g.id 
     JOIN project p ON g.id_project = p.id 
     JOIN pv_thesis pv ON pv.id_project = p.id 
     JOIN thesis t ON t.id_pv = pv.id 
        WHERE s.user_id = ?
");
$stmt->execute([$admin_id]);
$lastThesis = $stmt->fetch(PDO::FETCH_ASSOC);


// La requête pour récupérer la deadline
$stmt = $pdo->prepare("SELECT p.deadline
        FROM student s
        JOIN groupe g ON s.id_groupe = g.id
        JOIN project p ON g.id_project = p.id
        WHERE s.user_id = ?");
$stmt->execute([$admin_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$deadline = $result['deadline'] ?? null;

// Use DateTime for accurate comparison
$timezone = new DateTimeZone('Africa/Algiers'); 
$today = new DateTime('now', $timezone);
$deadlinePassed = false;

if ($deadline !== null) {
    try {
        $deadlineDate = new DateTime($deadline, $timezone);
        // Student can upload if now <= deadline
        if ($today > $deadlineDate) {
            $deadlinePassed = true;
        }
    } catch (Exception $e) {
        $deadlinePassed = true;
        error_log("Error parsing deadline: " . $e->getMessage());
    }
}

// Format deadline for display
$formattedDeadline = $deadline ? date('d/m/Y H:i', strtotime($deadline)) : 'Non définie';
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Étudiant - Dépôt</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../admin_library/css/adminstyle.css">
    <style>
        /* Table styling */
        .file-upload-table {
            width: 100%;
            margin: 20px 0;
        }

        .file-upload-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .file-upload-table td {
            padding: 20px;
            text-align: center;
            border: 2px dashed #ccc;
            border-radius: 5px;
        }

        /* Drop zone styling */
        .drop-zone {
            cursor: pointer;
            transition: all 0.3s;
        }

        .drop-zone:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }

        .drop-zone i {
            font-size: 2em;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .drop-zone p {
            margin: 5px 0;
            color: #6c757d;
        }

        /* File input styling */
        #fileInput {
            display: none;
        }

        .browse-btn {
            background-color: #6c757d;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-block;
            margin-top: 10px;
        }

        /* File preview styling */
        .file-preview {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .file-preview i {
            font-size: 1.5em;
            color: #d93025;
        }

        .file-info {
            flex-grow: 1;
            text-align: left;
        }

        .delete-btn {
            background-color: transparent;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 5px;
        }

        .delete-btn:hover {
            text-decoration: underline;
        }

        /* Upload button styling */
        .btn-upload {
            background-color: #007bff;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1em;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 15px;
        }

        .btn-upload:hover {
            background-color: #0069d9;
        }

        /* Highlight when dragging */
        .highlight {
            border-color: #007bff;
            background-color: #e7f1ff;
        }

        .alert {
            padding: 12px 20px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-weight: bold;
            border: 1px solid transparent;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c2c7;
            color: #842029;
        }

        .alert-success {
            background-color: #d1e7dd;
            border-color: #badbcc;
            color: #0f5132;
        }
    </style>

</head>

<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../assets/img/logo_without backg.png" alt="Logo" width="70" height="50">
                <h3 class="app-name">Gestion de Projets Universitaires</h3>
                <div class="user-profile">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($_SESSION['user']['first_name'] ?? '') . ' ' . htmlspecialchars($_SESSION['user']['last_name'] ?? '') ?></span>
                        <?php if (($_SESSION['user']['role'] ?? '') === 'student' && isset($_SESSION['user']['details']['matricule'])): ?>
                            <span class="user-matricule"><?= htmlspecialchars($_SESSION['user']['details']['matricule']) ?></span>
                        <?php endif; ?>
                        <span class="user-role"><?= ucfirst($_SESSION['user']['role'] ?? '') ?></span>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="uploadPV.php" class="nav-item <?= $currentPage == 'uploadPV.php' ? 'active' : '' ?>">
                    <i class="fas fa-file-upload"></i>
                    <span>Déposer la fiche d'évaluation</span>
                </a>
                <a href="upload.php" class="nav-item <?= $currentPage == 'upload.php' ? 'active' : '' ?>">
                    <i class="fas fa-file-upload"></i>
                    <span>Déposer le mémoire</span>
                </a>
                <a href="status.php" class="nav-item <?= $currentPage == 'status.php' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i>
                    <span>Statut des soumissions</span>
                </a>
                <a href="../logout.php" class="nav-item logout"> <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </nav>
        </aside>
        <!-- Main Content Area -->
        <div class="main-content">
            <div class="content-content">
                <div class="header">
                    <h1>Déposer votre mémoire</h1>
                    <?php if ($deadline): ?>
                        <p class="alert alert-info">Date limite de dépôt: <strong><?= htmlspecialchars($formattedDeadline) ?></strong></p>
                    <?php endif; ?>
                </div>

                <?php
                // Check if PV exists
                $stmt = $pdo->prepare("
                    SELECT pv.id 
                    FROM pv_thesis pv
                    JOIN project p ON pv.id_project = p.id
                    JOIN groupe g ON p.id = g.id_project
                    JOIN student s ON g.id = s.id_groupe
                    WHERE s.user_id = ?
                ");
                $stmt->execute([$admin_id]);
                $pvExists = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$pvExists): ?>
                    <div class="upload-card">
                        <div class="alert alert-warning">
                            <h4><i class="fas fa-exclamation-triangle"></i> Fiche d'évaluation manquante</h4>
                            <p>Vous devez d'abord déposer la fiche d'évaluation (PV) avant de pouvoir déposer votre mémoire.</p>
                            <a href="uploadPV.php" class="btn btn-primary mt-3">
                                <i class="fas fa-file-upload"></i> Déposer la fiche d'évaluation
                            </a>
                        </div>
                    </div>
                <?php elseif ($lastThesis && $lastThesis['status'] === 'approved'): ?>
                    <div class="upload-card">
                        <p class="alert alert-success">Votre mémoire a déjà été validé. Le dépôt de nouveaux fichiers n'est plus autorisé.</p>
                    </div>
                <?php elseif ($deadlinePassed): ?>
                    <div class="upload-card">
                        <p class="alert alert-danger">La date limite de dépôt du mémoire pour ce projet est dépassée (<?= htmlspecialchars($formattedDeadline) ?>).</p>
                    </div>
                <?php else: ?>
                    <?php if (isset($_SESSION['upload_success'])): ?>
                        <div class="alert alert-success" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold;">
                            <i class="fas fa-check-circle" style="margin-right: 10px;"></i>
                            <?= $_SESSION['upload_success']; unset($_SESSION['upload_success']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['upload_error'])): ?>
                        <div class="alert alert-danger" style="background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold;">
                            <i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>
                            <?= $_SESSION['upload_error']; unset($_SESSION['upload_error']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php
                    // Check if a thesis has already been uploaded
                    $stmt = $pdo->prepare("
                        SELECT t.original_filename, t.submitted_at
                        FROM student s
                        JOIN groupe g ON s.id_groupe = g.id
                        JOIN project p ON g.id_project = p.id
                        JOIN pv_thesis pv ON pv.id_project = p.id
                        JOIN thesis t ON t.id_pv = pv.id
                        WHERE s.user_id = ? AND t.is_deleted = 0
                        ORDER BY t.submitted_at DESC
                        LIMIT 1
                    ");
                    $stmt->execute([$admin_id]);
                    $existingThesis = $stmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    
                    <div class="upload-card">
                        <?php if ($existingThesis): ?>
                            <div class="alert alert-info">
                                <p><strong>Dernier fichier déposé:</strong> <?= htmlspecialchars($existingThesis['original_filename']) ?></p>
                                <p><strong>Date de dépôt:</strong> <?= date('d/m/Y H:i', strtotime($existingThesis['submitted_at'])) ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <form action="upload_thesis.php" method="post" enctype="multipart/form-data" id="uploadForm">
                            <div class="file-upload-table">
                                <table>
                                    <tr>
                                        <td class="drop-zone" id="dropZone">
                                            <i class="fas fa-cloud-upload-alt fa-2x"></i>
                                            <p>Glissez-déposez votre fichier ici</p>
                                            <p>ou</p>
                                            <input type="file" name="thesis_file" id="fileInput" accept=".pdf,.doc,.docx" required hidden>
                                            <label for="fileInput" class="browse-btn">Choisir un fichier</label>
                                        </td>
                                    </tr>
                                    <tr id="fileRow" style="display:none;">
                                        <td>
                                            <div class="file-preview">
                                                <i class="fas fa-file-pdf"></i>
                                                <div class="file-info">
                                                    <span id="fileNameDisplay"></span>
                                                    <button type="button" id="clearSelection" class="delete-btn">
                                                        <i class="fas fa-times"></i> Supprimer
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <button type="submit" class="btn-upload">
                                <?php echo $existingThesis ? 'Modifier le mémoire' : 'Déposer le mémoire'; ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer>
        <small>&copy; 2025 Gestion des Mémoires Universitaires. Tous droits réservés.</small>
    </footer>

    <style>
        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffecb5;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }
        
        .btn-primary:hover {
            background-color: #0069d9;
            text-decoration: none;
            color: white;
        }
        
        .mt-3 {
            margin-top: 15px;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('fileInput');
            const fileRow = document.getElementById('fileRow');
            const fileNameDisplay = document.getElementById('fileNameDisplay');
            const clearSelection = document.getElementById('clearSelection');
            const dropZone = document.getElementById('dropZone');

            if (fileInput && fileRow && fileNameDisplay && clearSelection && dropZone) {
                // Handle file selection
                fileInput.addEventListener('change', function() {
                    if (fileInput.files.length > 0) {
                        fileNameDisplay.textContent = fileInput.files[0].name;
                        fileRow.style.display = 'table-row';
                        dropZone.style.display = 'none';
                    }
                });

                // Clear file selection
                clearSelection.addEventListener('click', function() {
                    fileInput.value = '';
                    fileRow.style.display = 'none';
                    dropZone.style.display = 'table-cell';
                });

                // Drag and drop support
                dropZone.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    dropZone.style.borderColor = '#1d4ed8';
                });

                dropZone.addEventListener('dragleave', function() {
                    dropZone.style.borderColor = '#ccc';
                });

                dropZone.addEventListener('drop', function(e) {
                    e.preventDefault();
                    dropZone.style.borderColor = '#ccc';
                    
                    if (e.dataTransfer.files.length) {
                        fileInput.files = e.dataTransfer.files;
                        if (fileInput.files.length > 0) {
                            fileNameDisplay.textContent = fileInput.files[0].name;
                            fileRow.style.display = 'table-row';
                            dropZone.style.display = 'none';
                        }
                    }
                });
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uploadForm = document.getElementById('uploadForm');
            
            if (uploadForm) {
                uploadForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const fileInput = document.getElementById('fileInput');
                    if (!fileInput.files.length) {
                        alert('Veuillez sélectionner un fichier à déposer.');
                        return;
                    }
                    
                    const formData = new FormData(this);
                    
                    // Show loading indicator
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement en cours...';
                    submitBtn.disabled = true;
                    
                    fetch('upload_thesis.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        // Show immediate notification
                        const notification = document.createElement('div');
                        notification.className = 'alert alert-success';
                        notification.style.position = 'fixed';
                        notification.style.top = '20px';
                        notification.style.left = '50%';
                        notification.style.transform = 'translateX(-50%)';
                        notification.style.zIndex = '9999';
                        notification.style.padding = '15px 25px';
                        notification.style.borderRadius = '5px';
                        notification.style.backgroundColor = '#d4edda';
                        notification.style.borderColor = '#c3e6cb';
                        notification.style.color = '#155724';
                        notification.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
                        notification.innerHTML = '<i class="fas fa-check-circle"></i> Mémoire déposé avec succès! La page va se recharger...';
                        
                        document.body.appendChild(notification);
                        
                        // Reload the page after a short delay
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Une erreur est survenue lors du dépôt du fichier. Veuillez réessayer.');
                        submitBtn.innerHTML = originalBtnText;
                        submitBtn.disabled = false;
                    });
                });
            }
        });
    </script>
</body>

</html>
