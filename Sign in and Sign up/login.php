<?php
session_start();

try {
    $pdo = new PDO("odbc:Driver={SQL Server};Server=26.71.132.202;Database=gym;", "sa", "Uriel2004.");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        header("Location: forms.html?error=empty");
        exit;
    }

    $stmt = $pdo->prepare("SELECT password FROM USUARIOS_GYM WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        header("Location: forms.html?error=notfound");
        exit;
    }

    if (password_verify($password, $usuario['password'])) {
        $_SESSION['email'] = $email;
        
        // Verificar si es admin
        if ($email === 'soporteverifiacion@gmail.com') {
            header("Location: admin.php");
            exit;
        } else {
            // Usuario normal
            header("Location: inicio.php");
            exit;
        }
    } else {
        header("Location: forms.html?error=wrongpass");
        exit;
    }

} catch (PDOException $e) {
    // Opcional: redirigir con error general
    header("Location: forms.html?error=dberror");
    exit;
}
?>