<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

// Conexion BD SQL Server
$dsn = "odbc:Driver={SQL Server};Server=26.71.132.202;Database=gym;";
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
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de Registro</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css"
        integrity="sha512-KfkfwYDsLkIlwQp6LFnl8zNdLGxu9YAA1QvwINks4PhcElQSvqcyVLLD9aMhXd13uQjoXtEKNosOWaZqXgel0g=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap');

        * {
            padding: 0px;
            margin: 0px;
            box-sizing: border-box;
        }

        :root {
            --linear-grad: linear-gradient(to right, #141E30, #243B55);
            --grad-clr1: #141E30;
            --grad-clr2: #243B55;
        }

        body {
            min-height: 100vh;
            background: #f6f5f7;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Poppins', sans-serif;
            padding: 20px;
        }

        .container {
            position: relative;
            width: 400px;
            height: 300px;
            background-color: #fff;
            box-shadow: 25px 30px 55px #5557;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 40px;
        }

        .error-icon {
            font-size: 4rem;
            color: #e74c3c;
            margin-bottom: 20px;
        }

        h1 {
            color: var(--grad-clr1);
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        button {
            border-radius: 20px;
            border: 1px solid var(--grad-clr1);
            background-color: var(--grad-clr1);
            color: #FFFFFF;
            font-size: 12px;
            font-weight: bold;
            padding: 12px 45px;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            cursor: pointer;
            font-family: inherit;
        }

        button:hover {
            background-color: transparent;
            color: var(--grad-clr1);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .container {
                width: 90%;
                max-width: 350px;
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h1>Correo ya registrado</h1>
        <p>El correo $email ya está registrado en nuestro sistema.</p>
        <button onclick="window.location.href='forms.html'">Volver al registro</button>
    </div>
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
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Código</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css"
        integrity="sha512-KfkfwYDsLkIlwQp6LFnl8zNdLGxu9YAA1QvwINks4PhcElQSvqcyVLLD9aMhXd13uQjoXtEKNosOWaZqXgel0g=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap');

        * {
            padding: 0px;
            margin: 0px;
            box-sizing: border-box;
        }

        :root {
            --linear-grad: linear-gradient(to right, #141E30, #243B55);
            --grad-clr1: #141E30;
            --grad-clr2: #243B55;
        }

        body {
            min-height: 100vh;
            background: #f6f5f7;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Poppins', sans-serif;
            padding: 20px;
        }

        .container {
            position: relative;
            width: 500px;
            min-height: 400px;
            background-color: #fff;
            box-shadow: 25px 30px 55px #5557;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .header-section {
            background: var(--linear-grad);
            color: white;
            padding: 40px 30px;
            text-align: center;
            flex-shrink: 0;
        }

        .header-section h1 {
            color: white;
            font-size: 1.8rem;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .header-section p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .verification-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: white;
        }

        .content-section {
            padding: 40px 30px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .timer-display {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            font-size: 1.1rem;
            color: var(--grad-clr1);
            font-weight: 600;
        }

        .info-text {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        button {
            border-radius: 20px;
            border: 1px solid var(--grad-clr1);
            background-color: var(--grad-clr1);
            color: #FFFFFF;
            font-size: 12px;
            font-weight: bold;
            padding: 12px 30px;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            cursor: pointer;
            font-family: inherit;
            margin: 5px;
        }

        button:hover {
            background-color: transparent;
            color: var(--grad-clr1);
            transform: translateY(-2px);
        }

        button.ghost {
            background-color: transparent;
            border-color: #666;
            color: #666;
        }

        button.ghost:hover {
            background-color: #666;
            color: white;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                width: 100%;
                max-width: 400px;
                margin: 10px;
            }

            .header-section {
                padding: 30px 20px;
            }

            .header-section h1 {
                font-size: 1.5rem;
            }

            .content-section {
                padding: 30px 20px;
            }

            .button-group {
                flex-direction: column;
                width: 100%;
            }

            button {
                width: 100%;
                margin: 5px 0;
            }
        }

        @media (max-width: 375px) {
            .container {
                margin: 5px;
            }

            .header-section {
                padding: 25px 15px;
            }

            .content-section {
                padding: 25px 15px;
            }

            .header-section h1 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <div class="verification-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1>Verificación de Código</h1>
            <p>Hemos enviado un código de verificación a tu correo electrónico</p>
        </div>
        
        <div class="content-section">
            <p class="info-text">
                Revisa tu bandeja de entrada (y spam) y ingresa el código de 6 dígitos para completar tu registro.
            </p>
            
            <div class="timer-display" id="timerDisplay">
                <i class="fas fa-clock"></i> Tiempo restante: <span id="countdown">60</span> segundos
            </div>
            
            <div class="button-group">
                <button id="verifyBtn">Ingresar Código</button>
                <button class="ghost" onclick="window.location.href='forms.html'">Cancelar</button>
            </div>
        </div>
    </div>

    <script>
        let tiempo = 60;
        const countdownEl = document.getElementById('countdown');
        const timerDisplayEl = document.getElementById('timerDisplay');
        
        let timer = setInterval(() => {
            tiempo--;
            countdownEl.textContent = tiempo;
            
            if (tiempo <= 10) {
                timerDisplayEl.style.background = '#fff5f5';
                timerDisplayEl.style.borderColor = '#feb2b2';
                timerDisplayEl.style.color = '#e53e3e';
            } else if (tiempo <= 30) {
                timerDisplayEl.style.background = '#fffbf0';
                timerDisplayEl.style.borderColor = '#fed7aa';
                timerDisplayEl.style.color = '#dd6b20';
            }
            
            if (tiempo <= 0) {
                clearInterval(timer);
                timerDisplayEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Código expirado';
                
                Swal.fire({
                    title: 'Código vencido',
                    text: 'El código ha expirado. Puedes reenviarlo o cancelar el proceso.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Reenviar código',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#141E30',
                    cancelButtonColor: '#666'
                }).then(result => {
                    if (result.isConfirmed) {
                        location.reload();
                    } else {
                        window.location.href = 'forms.html';
                    }
                });
            }
        }, 1000);

        document.getElementById('verifyBtn').addEventListener('click', function() {
            Swal.fire({
                title: 'Código de verificación',
                input: 'text',
                inputLabel: 'Ingresa el código de 6 dígitos',
                inputPlaceholder: '000000',
                confirmButtonText: 'Verificar',
                showCancelButton: true,
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#141E30',
                cancelButtonColor: '#666',
                inputValidator: (value) => {
                    if (!/^\d{6}$/.test(value)) return 'Debe ser un código de 6 dígitos';
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
                    clearInterval(timer);
                    Swal.fire({
                        icon: 'success',
                        title: 'Registro completado',
                        text: 'Bienvenido a Trinity Gym',
                        confirmButtonText: 'Continuar',
                        confirmButtonColor: '#141E30'
                    }).then(() => {
                        window.location.href = 'forms.html';
                    });
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    window.location.href = 'forms.html';
                }
            });
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
        echo json_encode(['success' => false, 'message' => 'El código ha expirado']);
        exit;
    }

    if (!preg_match('/^\d{6}$/', $codigoIngresado)) {
        echo json_encode(['success' => false, 'message' => 'Código inválido']);
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
        echo json_encode(['success' => false, 'message' => 'Código incorrecto']);
        exit;
    }
}

// Si no entra en nada, redirigir
header("Location: forms.html");
exit;
?>