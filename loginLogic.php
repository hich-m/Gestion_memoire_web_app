<?php

require_once 'config/db_config.php'; // This file must define $pdo

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Empty fields check
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = 'Please fill in all the fields.';
        $_SESSION['login_email'] = $email;
        header('Location: login.php');
        exit();
    }

    // Email format validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email address.';
        $_SESSION['login_email'] = $email;
        header('Location: login.php');
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, a.email, a.password, u.role, a.status
           FROM user u
           JOIN account a ON u.id = a.id_user
           WHERE a.email = :email;

        ");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $_SESSION['error'] = 'Incorrect email or password.';
            $_SESSION['login_email'] = $email;
            header('Location: login.php');
            exit();
        }

        if ($user['status'] !== 'approved') {
            $_SESSION['error'] = 'Your account is pending approval. Please wait for administrator verification.';
            $_SESSION['login_email'] = $email;
            header('Location: login.php');
            exit();
        }

        // Successful login - set session variables
        $_SESSION['user'] = [
            'id' => $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'role' => $user['role']
        ];

        $_SESSION['user_id'] = $user['id'];

        // If student, get matricule
        if ($user['role'] === 'student') {
            $stmt = $pdo->prepare("SELECT matricule FROM student WHERE user_id = :id");
            $stmt->bindParam(':id', $user['id'], PDO::PARAM_INT);
            $stmt->execute();
            $student = $stmt->fetch();
            $_SESSION['user']['matricule'] = $student['matricule'] ?? '';
        }

        // Redirect based on role
        switch ($user['role']) {
            case 'admin':
                header('Location: admin/dashboard.php');
                break;
            case 'professor':
                header('Location: professor/dashboard.php');
                break;
            case 'admin_library':
                header('Location: admin_library/dashboard.php');
                break;
            case 'student':
                header('Location: student/dashboard.php');
                break;
            default:
                header('Location: index.php');
        }
        exit();
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = 'An error occurred while processing your request. Please try again later.';
        header('Location: login.php');
        exit();
    }
} else {
    header('Location: login.php');
    exit();
}
