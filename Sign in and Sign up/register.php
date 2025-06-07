<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

// Conexion BD SQL Server
$dsn = "odbc:Driver={SQL Server};Server=192.168.1.18;Database=gym;";
$user = "sa";
$password = "Uriel2004.";

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error en conexion a BD: " . $e->getMessage());
}

function enviarCodigo($nombre, $email, $codigo) {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'soporteverifiacion@gmail.com';
    $mail->Password = 'pxdrpuoozmaxvmko';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('soporteverifiacion@gmail.com', 'Registro Sistema');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Codigo de verificacion';
    $mail->Body = "<div style='font-family: sans-serif; text-align: center;'>
        <img src='https://cdn-icons-png.flaticon.com/512/3079/3079296.png' width='100' alt='Verificacion' />
        <h2>Hola $nombre,</h2>
        <p>Tu codigo de verificacion es: <strong>$codigo</strong></p>
        <p>Este codigo expirara en 60 segundos.</p>
    </div>";
    $mail->send();
}

function enviarBienvenida($nombre, $email) {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'soporteverifiacion@gmail.com';
    $mail->Password = 'pxdrpuoozmaxvmko';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('soporteverifiacion@gmail.com', 'Registro Sistema');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Bienvenido a nuestra plataforma';
    $mail->Body = "<div style='font-family: sans-serif; text-align: center;'>
        <img src='https://cdn-icons-png.flaticon.com/512/876/876019.png' width='120' alt='Bienvenido' />
        <h2>Felicidades $nombre!</h2>
        <p>Tu cuenta ha sido creada correctamente.</p>
        <p>Gracias por registrarte. Bienvenido a nuestra comunidad.</p>
    </div>";
    $mail->send();
}

// REGISTRO y envío de código
if (isset($_POST['nombre'], $_POST['email'], $_POST['password'])) {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM USUARIOS_GYM WHERE email = ?");
    $stmtCheck->execute([$email]);
    $emailExiste = $stmtCheck->fetchColumn();

    if ($emailExiste) {
        echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />
</head>
<body>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Correo ya registrado',
        text: 'El correo $email ya esta registrado.',
        confirmButtonText: 'Volver'
    }).then(() => {
        window.location.href = 'forms.html';
    });
</script>
</body>
</html>
HTML;
        exit;
    }

    $codigo = random_int(100000, 999999);
    $_SESSION['registro'] = [
        'nombre' => $nombre,
        'email' => $email,
        'password' => $password_hash,
        'codigo' => $codigo,
        'expira' => time() + 60
    ];

    enviarCodigo($nombre, $email, $codigo);

    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Verificacion</title>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />
    <style>
        body {
            background: #000;
            color: white;
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background: rgba(255,255,255,0.1);
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Codigo de Verificacion</h2>
        <p>Revisa tu correo y escribe el codigo para verificar tu cuenta.</p>
    </div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let tiempo = 60;
    let timer = setInterval(() => {
        tiempo--;
        if (tiempo <= 0) {
            clearInterval(timer);
            Swal.fire({
                title: 'Codigo vencido',
                text: 'El codigo ha expirado. Puedes reenviarlo.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Reenviar codigo',
                cancelButtonText: 'Cancelar'
            }).then(result => {
                if (result.isConfirmed) location.reload();
            });
        }
    }, 1000);

    Swal.fire({
        title: 'Codigo de verificacion',
        input: 'text',
        inputLabel: 'Ingresa el codigo de 6 digitos',
        confirmButtonText: 'Verificar',
        inputValidator: (value) => {
            if (!/^\d{6}$/.test(value)) return 'Debe ser un codigo de 6 digitos';
        },
        preConfirm: (codigoUsuario) => {
            return fetch('register.php?verificar=1&codigo=' + codigoUsuario)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message);
                    return data;
                }).catch(error => {
                    Swal.showValidationMessage(error.message);
                });
        }
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Registro completado',
                text: 'Bienvenido',
                confirmButtonText: 'Continuar'
            }).then(() => {
                window.location.href = 'forms.html';
            });
        }
    });
</script>
</body>
</html>
HTML;
    exit;
}

// VERIFICACION DE CODIGO
if (isset($_GET['verificar'], $_GET['codigo'])) {
    header('Content-Type: application/json');

    $codigoIngresado = $_GET['codigo'];

    // Si no hay sesion, asumimos que ya fue validado
    if (!isset($_SESSION['registro'])) {
        echo json_encode(['success' => true]);
        exit;
    }

    $registro = $_SESSION['registro'];

    if (time() > $registro['expira']) {
        echo json_encode(['success' => false, 'message' => 'El codigo ha expirado']);
        exit;
    }

    if (!preg_match('/^\d{6}$/', $codigoIngresado)) {
        echo json_encode(['success' => false, 'message' => 'Codigo invalido']);
        exit;
    }

    if ($codigoIngresado == $registro['codigo']) {
        try {
            $stmt = $pdo->prepare("INSERT INTO USUARIOS_GYM (nombre, email, password) VALUES (?, ?, ?)");
            if ($stmt->execute([$registro['nombre'], $registro['email'], $registro['password']])) {
                enviarBienvenida($registro['nombre'], $registro['email']);
                unset($_SESSION['registro']);
                echo json_encode(['success' => true]);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al guardar en BD']);
                exit;
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Codigo incorrecto']);
        exit;
    }
}

// Si no entra en nada, redirigir
header("Location: forms.html");
exit;
?>
