<?php
session_start();

// Validar sesión activa y usuario admin
if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'soporteverifiacion@gmail.com') {
    session_unset();
    session_destroy();
    header("Location: forms.html");
    exit;
}

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

// Conexión a la base de datos
try {
    $pdo = new PDO("odbc:Driver={SQL Server};Server=26.71.132.202;Database=gym;", "sa", "Uriel2004.");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$mensaje = "";
$tipo_mensaje = "";

// Función para enviar email de aprobación de pago
function enviarPagoAprobado($nombre, $email, $monto, $fecha_vencimiento, $duracion_meses) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'soporteverifiacion@gmail.com';
        $mail->Password = 'pxdrpuoozmaxvmko';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('soporteverifiacion@gmail.com', 'Sistema Gimnasio');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Pago Aprobado - Membresia Gimnasio';
        
        $fecha_formateada = date('d/m/Y', strtotime($fecha_vencimiento));
        
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa;'>
            <div style='background: linear-gradient(45deg, #141E30, #243B55); padding: 30px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>🏋️‍♂️ GIMNASIO</h1>
                <p style='color: #ffffff; margin: 10px 0 0 0; opacity: 0.9;'>pago ha sido aprobado</p>
            </div>
            
            <div style='background: white; padding: 40px; border-radius: 0 0 15px 15px;'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 10px; border: 1px solid #c3e6cb;'>
                        <h2 style='margin: 0; color: #155724;'>✅ ¡PAGO APROBADO!</h2>
                    </div>
                </div>
                
                <h3 style='color: #141E30; margin-bottom: 20px;'>Hola $nombre,</h3>
                
                <p style='color: #243B55; font-size: 16px; line-height: 1.6;'>
                    Nos complace informarte que tu pago ha sido <strong>verificado y aprobado</strong> exitosamente. 
                    Tu membresía del gimnasio ya está activa.
                </p>
                
                <div style='background: #f8f9fa; padding: 25px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #2ed573;'>
                    <h4 style='color: #141E30; margin-top: 0;'>📋 Detalles de tu Membresía:</h4>
                    <ul style='color: #243B55; list-style: none; padding: 0;'>
                        <li style='margin: 8px 0;'><strong>💰 Monto:</strong> $" . number_format($monto, 2) . "</li>
                        <li style='margin: 8px 0;'><strong>📅 Duración:</strong> $duracion_meses mes" . ($duracion_meses > 1 ? 'es' : '') . "</li>
                        <li style='margin: 8px 0;'><strong>⏰ Vence el:</strong> $fecha_formateada</li>
                        <li style='margin: 8px 0;'><strong>📧 Email:</strong> $email</li>
                    </ul>
                </div>
                
                <div style='background: #fff3cd; color: #856404; padding: 20px; border-radius: 10px; margin: 25px 0; border: 1px solid #ffeaa7;'>
                    <h4 style='margin-top: 0; color: #856404;'>📝 Información Importante:</h4>
                    <ul style='margin: 0; padding-left: 20px;'>
                        <li>Tu membresía está activa desde ahora</li>
                        <li>Recuerda traer una identificación oficial</li>
                        <li>Consulta nuestros horarios de atención</li>
                        <li>Para renovaciones, contacta con nosotros antes del vencimiento</li>
                    </ul>
                </div>
                
                <div style='text-align: center; margin-top: 30px;'>
                    <p style='color: #243B55; font-size: 16px;'>
                        ¡Gracias por confiar en nosotros! 💪<br>
                        <strong>¡Te esperamos en el gimnasio!</strong>
                    </p>
                </div>
                
                <hr style='border: none; border-top: 1px solid #e1e8ed; margin: 30px 0;'>
                
                <div style='text-align: center; color: #6c757d; font-size: 14px;'>
                    <p style='margin: 5px 0;'>📧 Sistema de Gimnasio</p>
                    <p style='margin: 5px 0;'>Este es un correo automático, no responder.</p>
                    <p style='margin: 5px 0;'>Para soporte: soporteverifiacion@gmail.com</p>
                </div>
            </div>
        </div>";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Error enviando email: " . $mail->ErrorInfo);
        return false;
    }
}

// Procesar actualización de estatus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_estatus'])) {
    $id_pago = (int)$_POST['id_pago'];
    $nuevo_estatus = $_POST['estatus'];
    
    // Validar que el estatus sea válido
    if (in_array($nuevo_estatus, ['pendiente', 'pagado'])) {
        try {
            // Obtener información del pago antes de actualizarlo
            $stmt_info = $pdo->prepare("SELECT * FROM pagos_gym WHERE id = ?");
            $stmt_info->execute([$id_pago]);
            $info_pago = $stmt_info->fetch(PDO::FETCH_ASSOC);
            
            if ($info_pago) {
                $estatus_anterior = $info_pago['estatus'];
                
                // Actualizar el estatus
                $stmt = $pdo->prepare("UPDATE pagos_gym SET estatus = ? WHERE id = ?");
                $stmt->execute([$nuevo_estatus, $id_pago]);
                
                // Si cambió de 'pendiente' a 'pagado', enviar email de confirmación
                if ($estatus_anterior === 'pendiente' && $nuevo_estatus === 'pagado') {
                    $email_enviado = enviarPagoAprobado(
                        $info_pago['nombre'],
                        $info_pago['email'],
                        $info_pago['monto'],
                        $info_pago['fecha_vencimiento'],
                        $info_pago['duracion_meses']
                        
                    );
                    
                    if ($email_enviado) {
                        $mensaje = "Estatus actualizado exitosamente y se ha enviado un email de confirmacion a " . $info_pago['email'];
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "Estatus actualizado exitosamente, pero hubo un error al enviar el email de confirmacion.";
                        $tipo_mensaje = "warning";
                    }
                } else {
                    $mensaje = "Estatus del pago actualizado exitosamente.";
                    $tipo_mensaje = "success";
                }
            } else {
                $mensaje = "No se encontro el pago especificado.";
                $tipo_mensaje = "error";
            }
            
        } catch (PDOException $e) {
            $mensaje = "Error al actualizar el estatus: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "Estatus inválido.";
        $tipo_mensaje = "error";
    }
}

// Obtener lista de pagos ordenados por fecha más reciente
try {
    $stmt = $pdo->query("SELECT * FROM pagos_gym ORDER BY fecha_pago DESC");
    $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pagos = [];
    $mensaje = "Error al cargar los pagos: " . $e->getMessage();
    $tipo_mensaje = "error";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pagos - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #141E30;
            --secondary-color: #243B55;
            --accent-color: #ff4757;
            --success-color: #2ed573;
            --warning-color: #ffa502;
            --error-color: #ff4757;
            --bg-color: #f6f5f7;
            --card-shadow: 25px 30px 55px #5557;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-color);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: var(--primary-color);
            font-size: 2.2rem;
            font-weight: 700;
        }

        .admin-badge {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .back-btn {
            background: var(--secondary-color);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-left: 15px;
        }

        .back-btn:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .table-section {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--card-shadow);
        }

        .section-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .stat-card {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            text-align: center;
            flex: 1;
            min-width: 150px;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid #e1e8ed;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #e1e8ed;
        }

        th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            font-size: 0.95rem;
            color: var(--secondary-color);
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-badge.pagado {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.pendiente {
            background: #fff3cd;
            color: #856404;
        }

        .status-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-select {
            padding: 6px 10px;
            border: 2px solid #e1e8ed;
            border-radius: 6px;
            font-size: 0.85rem;
            background: white;
        }

        .status-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .update-btn {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .update-btn:hover {
            background: #26d069;
            transform: translateY(-1px);
        }

        .photo-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .photo-link:hover {
            color: var(--accent-color);
        }

        .no-payments {
            text-align: center;
            color: var(--secondary-color);
            font-style: italic;
            padding: 40px 20px;
        }

        .amount {
            font-weight: 600;
            color: var(--success-color);
        }

        .expired {
            color: var(--error-color);
            font-weight: 500;
        }

        .expires-soon {
            color: var(--warning-color);
            font-weight: 500;
        }

        /* Modal para mostrar fotos */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            position: relative;
            margin: 2% auto;
            padding: 20px;
            width: 90%;
            max-width: 800px;
            background: white;
            border-radius: 15px;
            text-align: center;
            animation: slideIn 0.3s ease;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e1e8ed;
        }

        .modal-title {
            color: var(--primary-color);
            font-size: 1.3rem;
            font-weight: 600;
        }

        .close-btn {
            background: var(--error-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: #e63946;
            transform: scale(1.1);
        }

        .modal-image {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .image-error {
            color: var(--error-color);
            font-size: 1.1rem;
            padding: 40px;
            background: #f8d7da;
            border-radius: 10px;
            border: 1px solid #f5c6cb;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(-50px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }

            .stats-row {
                flex-direction: column;
            }

            th, td {
                padding: 10px 8px;
                font-size: 0.85rem;
            }

            .status-form {
                flex-direction: column;
                gap: 5px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }

            .table-section {
                padding: 15px;
            }

            th, td {
                padding: 8px 5px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>💳 Gestión de Pagos</h1>
            <div>
                <span class="admin-badge">👑 ADMINISTRADOR</span>
                <a href="admin.php" class="back-btn">← Volver al Dashboard</a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="message <?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Tabla de Pagos -->
        <div class="table-section">
            <h2 class="section-title">
                📊 Pagos Registrados
            </h2>
            
            <?php
            // Calcular estadísticas
            $total_pagos = count($pagos);
            $pagos_pagados = array_filter($pagos, function($p) { return $p['estatus'] === 'pagado'; });
            $pagos_pendientes = array_filter($pagos, function($p) { return $p['estatus'] === 'pendiente'; });
            $total_monto_pagado = array_sum(array_column($pagos_pagados, 'monto'));
            ?>

            <div class="stats-row">
                <div class="stat-card">
                    <span class="stat-number"><?php echo $total_pagos; ?></span>
                    <span class="stat-label">Total Pagos</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo count($pagos_pagados); ?></span>
                    <span class="stat-label">Pagados</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo count($pagos_pendientes); ?></span>
                    <span class="stat-label">Pendientes</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number">$<?php echo number_format($total_monto_pagado, 2); ?></span>
                    <span class="stat-label">Total Recaudado</span>
                </div>
            </div>
            
            <?php if (empty($pagos)): ?>
                <div class="no-payments">
                    No hay pagos registrados aún.
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Nombre</th>
                                <th>Fecha Pago</th>
                                <th>Monto</th>
                                <th>Duración</th>
                                <th>Vencimiento</th>
                               
                                <th>Estatus</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagos as $pago): ?>
                                <?php
                                // Calcular si está vencido o próximo a vencer
                                $fecha_vencimiento = new DateTime($pago['fecha_vencimiento']);
                                $hoy = new DateTime();
                                $dias_restantes = $hoy->diff($fecha_vencimiento)->days;
                                $vencido = $fecha_vencimiento < $hoy;
                                $pronto_vencer = !$vencido && $dias_restantes <= 7;
                                ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($pago['id']); ?></td>
                                    <td><?php echo htmlspecialchars($pago['email']); ?></td>
                                    <td><?php echo htmlspecialchars($pago['nombre']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?></td>
                                    <td class="amount">$<?php echo number_format($pago['monto'], 2); ?></td>
                                    <td><?php echo $pago['duracion_meses']; ?> mes<?php echo $pago['duracion_meses'] > 1 ? 'es' : ''; ?></td>
                                    <td class="<?php echo $vencido ? 'expired' : ($pronto_vencer ? 'expires-soon' : ''); ?>">
                                        <?php echo date('d/m/Y', strtotime($pago['fecha_vencimiento'])); ?>
                                        <?php if ($vencido): ?>
                                            <br><small>(Vencido)</small>
                                        <?php elseif ($pronto_vencer): ?>
                                            <br><small>(Vence pronto)</small>
                                        <?php endif; ?>
                                    </td>
                                  
                                    <td>
                                        <span class="status-badge <?php echo $pago['estatus']; ?>">
                                            <?php echo $pago['estatus'] === 'pagado' ? '✅ Pagado' : '⏳ Pendiente'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" class="status-form">
                                            <input type="hidden" name="id_pago" value="<?php echo $pago['id']; ?>">
                                            <select name="estatus" class="status-select">
                                                <option value="pendiente" <?php echo $pago['estatus'] === 'pendiente' ? 'selected' : ''; ?>>
                                                    Pendiente
                                                </option>
                                                <option value="pagado" <?php echo $pago['estatus'] === 'pagado' ? 'selected' : ''; ?>>
                                                    Pagado
                                                </option>
                                            </select>
                                            <button type="submit" name="actualizar_estatus" class="update-btn">
                                                💾 Actualizar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para mostrar fotos -->
    <div id="fotoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Comprobante de Pago</h3>
                <button class="close-btn" onclick="cerrarModal()">&times;</button>
            </div>
            <div id="modalBody">
                <img id="modalImage" class="modal-image" src="" alt="Comprobante de pago">
            </div>
        </div>
    </div>

    <script>
        // Función para mostrar foto en modal
    function mostrarFoto(rutaFoto, nombreCliente) {
    const modal = document.getElementById('fotoModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    
    modalTitle.textContent = 'Comprobante de Pago - ' + nombreCliente;
    
    // Construir la ruta completa correctamente
    const rutaCompleta = 'uploads/pagos/' + rutaFoto;
    
    // Crear nueva imagen para verificar si carga
    const img = new Image();
    img.onload = function() {
        modalBody.innerHTML = '<img class="modal-image" src="' + rutaCompleta + '" alt="Comprobante de pago">';
    };
    img.onerror = function() {
        modalBody.innerHTML = '<div class="image-error">❌ Error al cargar la imagen<br><small>Ruta: ' + rutaCompleta + '</small></div>';
    };
    img.src = rutaCompleta;
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

        // Función para cerrar modal
        function cerrarModal() {
            const modal = document.getElementById('fotoModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide mensajes después de 8 segundos (más tiempo para leer el mensaje del email)
            setTimeout(function() {
                const messages = document.querySelectorAll('.message');
                messages.forEach(function(message) {
                    message.style.opacity = '0';
                    message.style.transition = 'opacity 0.5s ease';
                    setTimeout(function() {
                        message.style.display = 'none';
                    }, 500);
                });
            }, 8000);

            document.querySelectorAll('.status-form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    const select = form.querySelector('select');
                    const originalValue = select.dataset.original || select.value;
                    
                    if (select.value !== originalValue) {
                        let mensaje = '¿Estás seguro de que deseas cambiar el estatus de este pago?';
                        
                        if (originalValue === 'pendiente' && select.value === 'pagado') {
                            mensaje = '¿Confirmas que deseas APROBAR este pago?\n\nSe enviará un email de confirmación al cliente.';
                        }
                        
                        if (!confirm(mensaje)) {
                            e.preventDefault();
                        }
                    }
                });
            });

            document.querySelectorAll('.status-select').forEach(function(select) {
                select.dataset.original = select.value;
            });

            document.getElementById('fotoModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    cerrarModal();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    cerrarModal();
                }
            });
        });
    </script>
</body>
</html>