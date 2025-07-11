<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

if (!isset($_SESSION['email'])) {
    header("Location: forms.html");
    exit;
}

$mensajeError = "";
$email = $_SESSION['email'];
$nombre = $_SESSION['nombre'] ?? 'Usuario';
$expiraEn = $_SESSION['codigo_expiracion'] ?? 0;

if (isset($_POST['verificar'])) {
    $codigoIngresado = trim($_POST['codigo'] ?? '');

    if (empty($codigoIngresado)) {
        $mensajeError = "Por favor ingresa el código.";
    } elseif (time() > $_SESSION['codigo_expiracion']) {
        $mensajeError = "El código ha expirado. Puedes reenviarlo.";
    } elseif ($codigoIngresado == $_SESSION['codigo_verificacion']) {
        // ✅ Código correcto, marcar al usuario como verificado
        $_SESSION['verificado'] = true;
        unset($_SESSION['codigo_verificacion'], $_SESSION['codigo_expiracion']);

        if ($email === 'soporteverifiacion@gmail.com') {
            header("Location: admin.php");
        } else {
            header("Location: inicio.php");
        }
        exit;
    } else {
        $mensajeError = "Código incorrecto. Intenta nuevamente.";
    }
}

if (isset($_POST['reenviar'])) {
    $codigo = rand(100000, 999999);
    $_SESSION['codigo_verificacion'] = $codigo;
    $_SESSION['codigo_expiracion'] = time() + 60;
    $expiraEn = $_SESSION['codigo_expiracion'];
    enviarCodigo($nombre, $email, $codigo);
    $mensajeError = "Nuevo código enviado a tu correo.";
}

function enviarCodigo($nombre, $email, $codigo) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'soporteverifiacion@gmail.com';
        $mail->Password = 'pxdrpuoozmaxvmko';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('soporteverifiacion@gmail.com', 'Codigo Autenticarse');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Tu codigo de verificacion';

        $mail->Body = "
        <div style='font-family: sans-serif; text-align: center;'>
            <h2>Hola $nombre,</h2>
            <p>Tu código de verificación es:</p>
            <h1 style='color: #243B55;'>$codigo</h1>
            <p>Este código expirará en 60 segundos.</p>
        </div>";
        $mail->send();
    } catch (Exception $e) {
        // Error silenciado por seguridad
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificar Código</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f6f5f7;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 420px;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        .container h2 {
            margin-bottom: 10px;
            color: #141E30;
        }
        .container p {
            color: #333;
            margin-bottom: 25px;
        }
        input[type="text"] {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            margin-bottom: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: 0.3s ease;
        }
        input[type="text"]:focus {
            border-color: #243B55;
            outline: none;
            box-shadow: 0 0 5px rgba(36,59,85,0.3);
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #243B55;
            color: white;
            border: none;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }
        .btn:hover {
            background: #141E30;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
        }
        .resend-btn {
            background: transparent;
            border: none;
            color: #243B55;
            font-size: 0.9rem;
            margin-top: 10px;
            cursor: pointer;
        }
        .resend-timer {
            font-size: 0.9rem;
            color: #888;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2><i class="fas fa-shield-alt"></i> Verificación</h2>
    <p>Ingresa el código de 6 dígitos que enviamos a <strong><?= htmlspecialchars($email) ?></strong></p>

    <?php if (!empty($mensajeError)): ?>
        <div class="error-message"><?= htmlspecialchars($mensajeError) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="codigo" placeholder="Código de verificación" maxlength="6" required>
        <button class="btn" type="submit" name="verificar"><i class="fas fa-check-circle"></i> Verificar</button>
    </form>

    <form method="POST">
        <button class="resend-btn" type="submit" name="reenviar" id="resendButton" disabled>
            <i class="fas fa-sync-alt"></i> Reenviar código
        </button>
    </form>

    <div class="resend-timer" id="countdownText">Espera <span id="secondsLeft">60</span> segundos para reenviar.</div>

    <div style="margin-top: 20px;">
        <a href="forms.html" style="font-size: 0.9rem; color: #666;"><i class="fas fa-arrow-left"></i> Volver al login</a>
    </div>
</div>

<script>
    const resendButton = document.getElementById("resendButton");
    const countdownText = document.getElementById("countdownText");
    const secondsLeft = document.getElementById("secondsLeft");

    const expirationTimestamp = <?= $expiraEn ?>;
    const now = Math.floor(Date.now() / 1000);
    let remaining = expirationTimestamp - now;

    if (remaining > 0) {
        resendButton.disabled = true;
        countdownText.style.display = "block";

        const timer = setInterval(() => {
            remaining--;
            secondsLeft.textContent = remaining;

            if (remaining <= 0) {
                clearInterval(timer);
                resendButton.disabled = false;
                countdownText.style.display = "none";
            }
        }, 1000);
    } else {
        resendButton.disabled = false;
        countdownText.style.display = "none";
    }
</script>

</body>
</html>
