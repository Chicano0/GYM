<?php
session_start();

$dsn = "odbc:Driver={SQL Server};Server=26.71.132.202;Database=gym;";
$user = "sa";
$password = "Uriel2004.";

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error en conexion a BD: " . $e->getMessage());
}

$tokenValido = false;
$tokenExpirado = false;
$tokenUsado = false;
$tokenNoExiste = false;
$email = '';
$nombreUsuario = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verificar token en la base de datos
    $stmt = $pdo->prepare("SELECT tr.email, tr.expira, tr.usado, u.nombre 
                          FROM TOKENS_RECUPERACION tr 
                          INNER JOIN USUARIOS_GYM u ON tr.email = u.email 
                          WHERE tr.token = ?");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch();
    
    if (!$tokenData) {
        $tokenNoExiste = true;
    } elseif ($tokenData['usado'] == 1) {
        $tokenUsado = true;
    } elseif (time() > $tokenData['expira']) {
        $tokenExpirado = true;
    } else {
        $tokenValido = true;
        $email = $tokenData['email'];
        $nombreUsuario = $tokenData['nombre'];
    }
} else {
    $tokenNoExiste = true;
}

// Procesar actualización de contraseña
if (isset($_POST['nueva_password']) && $tokenValido) {
    $nuevaPassword = trim($_POST['nueva_password']);
    $confirmarPassword = trim($_POST['confirmar_password']);
    
    if (strlen($nuevaPassword) < 6) {
        $errorPassword = "La contraseña debe tener al menos 6 caracteres";
    } elseif ($nuevaPassword !== $confirmarPassword) {
        $errorPassword = "Las contraseñas no coinciden";
    } else {
        try {
            // Actualizar contraseña
            $passwordHash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
            $stmtUpdate = $pdo->prepare("UPDATE USUARIOS_GYM SET password = ? WHERE email = ?");
            
            if ($stmtUpdate->execute([$passwordHash, $email])) {
                $stmtToken = $pdo->prepare("UPDATE TOKENS_RECUPERACION SET usado = 1 WHERE token = ?");
                $stmtToken->execute([$token]);
                
                $passwordActualizada = true;
            } else {
                $errorPassword = "Error al actualizar la contraseña";
            }
        } catch (PDOException $e) {
            $errorPassword = "Error en la base de datos";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualizar Contraseña</title>
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

        .header-section.error {
            background: linear-gradient(to right, #dc3545, #c82333);
        }

        .header-section.success {
            background: linear-gradient(to right, #28a745, #20c997);
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

        .icon {
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

        .error-section, .success-section {
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
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--grad-clr1);
            font-weight: 600;
            font-size: 0.9rem;
        }

        input[type="password"] {
            width: 100%;
            padding: 15px 45px 15px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        input[type="password"]:focus {
            outline: none;
            border-color: var(--grad-clr1);
            background: white;
            box-shadow: 0 0 0 3px rgba(20, 30, 48, 0.1);
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 1.1rem;
            margin-top: 12px;
        }

        .toggle-password:hover {
            color: var(--grad-clr1);
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

        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .password-requirements {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: #1565c0;
        }

        .password-requirements ul {
            margin: 10px 0 0 20px;
        }

        .welcome-user {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
            color: var(--grad-clr1);
            font-weight: 500;
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

            .form-section, .error-section, .success-section {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($tokenNoExiste): ?>
            <!-- Token no existe -->
            <div class="header-section error">
                <div class="icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1>Enlace Inválido</h1>
                <p>El enlace que has usado no es válido o ha sido modificado</p>
            </div>
            <div class="error-section">
                <div class="info-text">
                    <p>Este enlace no existe en nuestro sistema. Esto puede ocurrir si:</p>
                    <ul style="text-align: left; margin: 15px 0;">
                        <li>El enlace fue copiado incorrectamente</li>
                        <li>El enlace fue modificado</li>
                        <li>El enlace es muy antiguo</li>
                    </ul>
                </div>
                <div class="back-link">
                    <a href="recuperar_password.php">
                        <i class="fas fa-redo"></i> Solicitar nuevo enlace
                    </a>
                </div>
            </div>

        <?php elseif ($tokenUsado): ?>
            <!-- Token ya usado -->
            <div class="header-section error">
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>Enlace Ya Utilizado</h1>
                <p>Este enlace ya fue usado anteriormente</p>
            </div>
            <div class="error-section">
                <div class="info-text">
                    <p>Este enlace ya fue utilizado para cambiar la contraseña y no puede usarse nuevamente.</p>
                    <p>Si necesitas cambiar tu contraseña otra vez, solicita un nuevo enlace.</p>
                </div>
                <div class="back-link">
                    <a href="recuperar_password.php">
                        <i class="fas fa-redo"></i> Solicitar nuevo enlace
                    </a>
                </div>
            </div>

        <?php elseif (isset($passwordActualizada)): ?>
            <!-- Contraseña actualizada exitosamente -->
            <div class="header-section success">
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>¡Contraseña Actualizada!</h1>
                <p>Tu contraseña ha sido cambiada exitosamente</p>
            </div>
            <div class="success-section">
                <div class="info-text">
                    <p>Tu contraseña ha sido actualizada correctamente.</p>
                    <p>Ya puedes iniciar sesión con tu nueva contraseña.</p>
                </div>
                <div class="back-link">
                    <a href="forms.html">
                        <i class="fas fa-sign-in-alt"></i> Ir al Login
                    </a>
                </div>
            </div>

        <?php elseif ($tokenValido): ?>
            <!-- Formulario para nueva contraseña -->
            <div class="header-section">
                <div class="icon">
                    <i class="fas fa-key"></i>
                </div>
                <h1>Nueva Contraseña</h1>
                <p>Crea una contraseña segura para tu cuenta</p>
            </div>
            
            <div class="form-section">
                <div class="welcome-user">
                    <i class="fas fa-user-circle"></i> 
                    Hola <strong><?php echo htmlspecialchars($nombreUsuario); ?></strong>
                </div>

                <?php if (isset($errorPassword)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $errorPassword; ?>
                </div>
                <?php endif; ?>

                <div class="password-requirements">
                    <strong><i class="fas fa-info-circle"></i> Requisitos de la contraseña:</strong>
                    <ul>
                        <li>Mínimo 6 caracteres</li>
                        <li>Se recomienda usar letras, números y símbolos</li>
                        <li>Evita usar información personal</li>
                    </ul>
                </div>
                
                <form method="POST" id="passwordForm">
                    <div class="form-group">
                        <label for="nueva_password">
                            <i class="fas fa-lock"></i> Nueva Contraseña
                        </label>
                        <input 
                            type="password" 
                            id="nueva_password" 
                            name="nueva_password" 
                            placeholder="Ingresa tu nueva contraseña" 
                            required
                            minlength="6"
                        >
                        <span class="toggle-password" onclick="togglePassword('nueva_password')">
                            <i class="fas fa-eye" id="toggleIcon1"></i>
                        </span>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmar_password">
                            <i class="fas fa-lock"></i> Confirmar Contraseña
                        </label>
                        <input 
                            type="password" 
                            id="confirmar_password" 
                            name="confirmar_password" 
                            placeholder="Confirma tu nueva contraseña" 
                            required
                            minlength="6"
                        >
                        <span class="toggle-password" onclick="togglePassword('confirmar_password')">
                            <i class="fas fa-eye" id="toggleIcon2"></i>
                        </span>
                    </div>
                    
                    <button type="submit">
                        <i class="fas fa-save"></i> Actualizar Contraseña
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
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId === 'nueva_password' ? 'toggleIcon1' : 'toggleIcon2');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('nueva_password').value;
            const confirmPassword = document.getElementById('confirmar_password').value;
            
            if (newPassword.length < 6) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Contraseña muy corta',
                    text: 'La contraseña debe tener al menos 6 caracteres',
                    confirmButtonColor: '#141E30'
                });
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Contraseñas no coinciden',
                    text: 'Las contraseñas ingresadas no son iguales',
                    confirmButtonColor: '#141E30'
                });
                return;
            }
        });

        document.getElementById('nueva_password')?.addEventListener('input', function() {
            const password = this.value;
            const strength = getPasswordStrength(password);
            
            const existingIndicator = document.querySelector('.password-strength');
            if (existingIndicator) {
                existingIndicator.remove();
            }
            
            if (password.length > 0) {
                const indicator = document.createElement('div');
                indicator.className = 'password-strength';
                indicator.style.cssText = `
                    margin-top: 8px;
                    padding: 8px 12px;
                    border-radius: 4px;
                    font-size: 0.8rem;
                    font-weight: 500;
                `;
                
                if (strength.score < 2) {
                    indicator.style.background = '#ffebee';
                    indicator.style.color = '#c62828';
                    indicator.innerHTML = '<i class="fas fa-times-circle"></i> Débil';
                } else if (strength.score < 4) {
                    indicator.style.background = '#fff3e0';
                    indicator.style.color = '#ef6c00';
                    indicator.innerHTML = '<i class="fas fa-exclamation-circle"></i> Regular';
                } else {
                    indicator.style.background = '#e8f5e8';
                    indicator.style.color = '#2e7d32';
                    indicator.innerHTML = '<i class="fas fa-check-circle"></i> Fuerte';
                }
                
                this.parentNode.appendChild(indicator);
            }
        });

        function getPasswordStrength(password) {
            let score = 0;
            
            if (password.length >= 6) score++;
            if (password.length >= 8) score++;
            
            if (/[A-Z]/.test(password)) score++;
            
            if (/[0-9]/.test(password)) score++;
            
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            return { score };
        }
    </script>
</body>
</html>