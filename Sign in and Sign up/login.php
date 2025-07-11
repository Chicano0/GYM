<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

// Configurar conexión
try {
    $pdo = new PDO("odbc:Driver={SQL Server};Server=26.71.132.202;Database=gym;", "sa", "Uriel2004.");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Captura inputs
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        header("Location: forms.html?error=empty");
        exit;
    }

    // Buscar usuario
    $stmt = $pdo->prepare("SELECT nombre, password FROM USUARIOS_GYM WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        header("Location: forms.html?error=notfound");
        exit;
    }

    // Verificar contraseña
    if (password_verify($password, $usuario['password'])) {
        $_SESSION['email'] = $email;

        // Generar código
        $codigo = rand(100000, 999999);
        $_SESSION['codigo_verificacion'] = $codigo;
        $_SESSION['codigo_expiracion'] = time() + 60;

        // Enviar el código con PHPMailer
        enviarCodigo($usuario['nombre'] ?? 'Usuario', $email, $codigo);

        // Redirigir a página para ingresar el código
        header("Location: verificar_codigo.php");
        exit;
    } else {
        header("Location: forms.html?error=wrongpass");
        exit;
    }

} catch (PDOException $e) {
    header("Location: forms.html?error=dberror");
    exit;
}


// Función para enviar código de verificación
function enviarCodigo($nombre, $email, $codigo) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'soporteverifiacion@gmail.com';
        $mail->Password = 'pxdrpuoozmaxvmko'; // Usa una App Password real aquí
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('soporteverifiacion@gmail.com', 'Codigo Para Autenticarse');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Codigo de verificacion';

        $mail->Body = "<div style='font-family: sans-serif; text-align: center;'>
            <img src='https://cdn-icons-png.flaticon.com/512/3079/3079296.png' width='100' alt='Verificacion' />
            <h2>Hola $nombre,</h2>
            <p>Tu código de verificación es: <strong>$codigo</strong></p>
            <p>Este código expirará en 60 segundos.</p>
        </div>";

        $mail->send();
    } catch (Exception $e) {
    
    }
}
?>
