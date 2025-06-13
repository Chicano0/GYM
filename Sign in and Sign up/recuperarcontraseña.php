<?php
session_start();

if (isset($_GET['verificar'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

$dsn = "odbc:Driver={SQL Server};Server=26.71.132.202;Database=gym;";
$user = "sa";
$password = "Uriel2004.";

function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - Password Recovery Error: " . $message . PHP_EOL, 3, "recovery_errors.log");
}

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("Database connection failed: " . $e->getMessage());
    if (isset($_GET['verificar'])) {
        echo json_encode(['success' => false, 'message' => 'Error de conexión a BD']);
        exit;
    }
    die("Error en conexion a BD. Por favor, intenta más tarde.");
}

function enviarEnlaceRecuperacion($nombre, $email, $token) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'soporteverifiacion@gmail.com';
        $mail->Password = 'pxdrpuoozmaxvmko';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('soporteverifiacion@gmail.com', 'Recuperación de Contraseña');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Enlace de recuperación de contraseña';
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $currentDir = dirname($_SERVER['REQUEST_URI']);
        $enlaceRecuperacion = $protocol . "://" . $host . $currentDir . "/update.php?token=" . urlencode($token);
        
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px;'>
            <div style='background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <img src='https://cdn-icons-png.flaticon.com/512/3064/3064197.png' width='80' alt='Recuperacion' />
                    <h2 style='color: #333; margin: 20px 0 10px 0;'>Hola " . htmlspecialchars($nombre) . ",</h2>
                    <p style='color: #666; font-size: 16px;'>Solicitud de recuperación de contraseña</p>
                </div>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <p style='color: #333; margin-bottom: 20px; text-align: center;'>
                        Haz clic en el siguiente botón para crear tu nueva contraseña:
                    </p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . $enlaceRecuperacion . "' 
                           style='background: linear-gradient(135deg, #141E30, #243B55); 
                                  color: white; 
                                  padding: 15px 30px; 
                                  text-decoration: none; 
                                  border-radius: 25px; 
                                  font-weight: bold; 
                                  display: inline-block;
                                  font-size: 16px;'>
                            🔓 Cambiar Contraseña
                        </a>
                    </div>
                </div>
                
                <div style='border-top: 1px solid #eee; padding-top: 20px; color: #666; font-size: 14px;'>
                    <p><strong>⚠️ Importante:</strong></p>
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li>Este enlace expirará en <strong>15 minutos</strong></li>
                        <li>Solo puede ser usado una vez</li>
                        <li>Si no solicitaste este cambio, ignora este mensaje</li>
                    </ul>
                    
                    <p style='margin-top: 20px; font-size: 12px; color: #999;'>
                        Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                        <a href='" . $enlaceRecuperacion . "' style='color: #007bff; word-break: break-all;'>" . $enlaceRecuperacion . "</a>
                    </p>
                </div>
            </div>
        </div>";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        logError("Email sending failed: " . $e->getMessage());
        return false;
    }
}

function crearTablaTokens($pdo) {
    try {
        $sql = "
        IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='TOKENS_RECUPERACION' AND xtype='U')
        CREATE TABLE TOKENS_RECUPERACION (
            id INT IDENTITY(1,1) PRIMARY KEY,
            email NVARCHAR(255) NOT NULL,
            token NVARCHAR(128) NOT NULL,
            expira BIGINT NOT NULL,
            usado BIT DEFAULT 0,
            fecha_creacion DATETIME DEFAULT GETDATE()
        )";
        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        logError("Error creating tokens table: " . $e->getMessage());
        return false;
    }
}

crearTablaTokens($pdo);

if (isset($_POST['email_recovery'])) {
    $email = trim($_POST['email_recovery']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorEmail = "Formato de email inválido";
    } else {
        try {
            $stmtCheck = $pdo->prepare("SELECT nombre FROM USUARIOS_GYM WHERE email = ?");
            $stmtCheck->execute([$email]);
            $usuario = $stmtCheck->fetch();

            if (!$usuario) {
                $emailNoEncontrado = true;
            } else {
                $token = bin2hex(random_bytes(32)); 
                $expiracion = time() + (15 * 60); 
                
                try {
                    $pdo->beginTransaction();
                    
                    $stmtClean = $pdo->prepare("DELETE FROM TOKENS_RECUPERACION WHERE email = ? OR expira < ?");
                    $stmtClean->execute([$email, time()]);
                    
                    $stmtToken = $pdo->prepare("INSERT INTO TOKENS_RECUPERACION (email, token, expira, usado) VALUES (?, ?, ?, 0)");
                    $stmtToken->execute([$email, $token, $expiracion]);
                    
                    $pdo->commit();
                    
                    if (enviarEnlaceRecuperacion($usuario['nombre'], $email, $token)) {
                        $enlaceEnviado = true;
                    } else {
                        $errorEnvio = true;
                    }
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    logError("Database error during token creation: " . $e->getMessage());
                    $errorBD = true;
                }
            }
        } catch (PDOException $e) {
            logError("Database error during user check: " . $e->getMessage());
            $errorBD = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap');

        * {
            padding: 0;
            margin: 0;
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
            width: 450px;
            min-height: 500px;
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

        .recovery-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: white;
        }

        .form-section {
            padding: 40px 30px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .success-section {
            padding: 40px 30px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--grad-clr1);
            font-weight: 600;
            font-size: 0.9rem;
        }

        input[type="email"] {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: var(--grad-clr1);
            background: white;
            box-shadow: 0 0 0 3px rgba(20, 30, 48, 0.1);
        }

        input[type="email"].error {
            border-color: #dc3545;
            background: #fff5f5;
        }

        button {
            width: 100%;
            border-radius: 20px;
            border: 1px solid var(--grad-clr1);
            background-color: var(--grad-clr1);
            color: #FFFFFF;
            font-size: 14px;
            font-weight: bold;
            padding: 15px;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            cursor: pointer;
            font-family: inherit;
            margin-bottom: 15px;
        }

        button:hover {
            background-color: transparent;
            color: var(--grad-clr1);
            transform: translateY(-2px);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: var(--grad-clr1);
        }

        .info-text {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
            text-align: center;
        }

        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success-message h3 {
            margin-bottom: 10px;
            color: #155724;
        }

        .success-message .icon {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 15px;
        }

        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .hidden {
            display: none;
        }

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

            .form-section, .success-section {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <div class="recovery-icon">
                <i class="fas fa-unlock-alt"></i>
            </div>
            <h1 id="headerTitle">Recuperar Contraseña</h1>
            <p id="headerDescription">Ingresa tu email para recibir el enlace de recuperación</p>
        </div>
        
        <?php if (isset($enlaceEnviado)): ?>
        <!-- Mensaje de éxito -->
        <div class="success-section">
            <div class="success-message">
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3>¡Enlace enviado!</h3>
                <p>Hemos enviado un enlace de recuperación a tu correo:</p>
                <strong><?php echo htmlspecialchars($email); ?></strong>
            </div>
            
            <div class="info-text">
                <p><strong>Instrucciones:</strong></p>
                <ul style="text-align: left; margin: 15px 0;">
                    <li>Revisa tu bandeja de entrada (y spam)</li>
                    <li>Haz clic en el enlace del correo</li>
                    <li>El enlace expira en 15 minutos</li>
                    <li>Solo puede usarse una vez</li>
                </ul>
            </div>
            
            <div class="back-link">
                <a href="forms.html">
                    <i class="fas fa-arrow-left"></i> Volver al login
                </a>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Formulario inicial para solicitar enlace -->
        <div class="form-section" id="emailForm">
            <p class="info-text">
                Te enviaremos un enlace seguro a tu correo electrónico para que puedas crear una nueva contraseña.
            </p>
            
            <?php if (isset($errorEmail)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $errorEmail; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="recoveryForm">
                <div class="form-group">
                    <label for="email_recovery">
                        <i class="fas fa-envelope"></i> Correo Electrónico
                    </label>
                    <input 
                        type="email" 
                        id="email_recovery" 
                        name="email_recovery" 
                        placeholder="ejemplo@correo.com" 
                        required
                        value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                        <?php echo isset($errorEmail) ? 'class="error"' : ''; ?>
                    >
                </div>
                
                <button type="submit" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Enviar Enlace
                </button>
            </form>
            
            <div class="back-link">
                <a href="forms.html">
                    <i class="fas fa-arrow-left"></i> Volver al login
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('recoveryForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Enlace';
            }, 3000);
        });

        <?php if (isset($emailNoEncontrado)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Email no encontrado',
            text: 'El correo <?php echo htmlspecialchars($email); ?> no está registrado en nuestro sistema.',
            confirmButtonText: 'Intentar de nuevo',
            confirmButtonColor: '#141E30'
        }).then(() => {
            document.getElementById('email_recovery').focus();
        });
        <?php endif; ?>

        <?php if (isset($errorEnvio)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error al enviar',
            text: 'No se pudo enviar el correo. Verifica tu conexión a internet e intenta de nuevo.',
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#141E30'
        });
        <?php endif; ?>

        <?php if (isset($errorBD)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error del sistema',
            text: 'Ocurrió un error interno. Por favor, intenta de nuevo más tarde.',
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#141E30'
        });
        <?php endif; ?>
    </script>
</body>
</html>