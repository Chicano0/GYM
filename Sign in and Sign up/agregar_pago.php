<?php
session_start();

// Conexión a la base de datos
try {
    $pdo = new PDO("odbc:Driver={SQL Server};Server=26.71.132.202;Database=gym;", "sa", "Uriel2004.");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$mensaje = "";
$tipo_mensaje = "";
$pago_registrado = false;

// Procesar formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $fecha_pago = $_POST['fecha_pago'] ?? '';
    $monto = (float)($_POST['monto'] ?? 0);
    $duracion_meses = (int)($_POST['duracion_meses'] ?? 1);

    // Validaciones
    $errores = [];

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "Email válido es obligatorio";
    }

    if (empty($nombre)) {
        $errores[] = "El nombre es obligatorio";
    }

    if (empty($fecha_pago)) {
        $errores[] = "La fecha de pago es obligatoria";
    }

    if ($monto <= 0) {
        $errores[] = "El monto debe ser mayor a 0";
    }

    if ($duracion_meses < 1 || $duracion_meses > 12) {
        $errores[] = "La duración debe ser entre 1 y 12 meses";
    }

    if (empty($errores)) {
        try {
            // Calcular fecha de vencimiento
            $fecha_vencimiento = date('Y-m-d', strtotime($fecha_pago . ' + ' . $duracion_meses . ' months'));

            // Manejar subida de archivo si existe
            $foto_pago = null;
            if (isset($_FILES['foto_pago']) && $_FILES['foto_pago']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/pagos/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $archivo_extension = strtolower(pathinfo($_FILES['foto_pago']['name'], PATHINFO_EXTENSION));
                $archivo_nombre = 'pago_' . date('YmdHis') . '_' . uniqid() . '.' . $archivo_extension;
                $archivo_destino = $upload_dir . $archivo_nombre;

                // Validar tipo de archivo
                $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                if (in_array($archivo_extension, $tipos_permitidos)) {
                    if (move_uploaded_file($_FILES['foto_pago']['tmp_name'], $archivo_destino)) {
                        $foto_pago = $archivo_nombre;
                    }
                }
            }

            // Insertar en la base de datos
            $stmt = $pdo->prepare("INSERT INTO pagos_gym (email, nombre, fecha_pago, monto, duracion_meses, fecha_vencimiento, foto_pago, estatus) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')");
            $stmt->execute([$email, $nombre, $fecha_pago, $monto, $duracion_meses, $fecha_vencimiento, $foto_pago]);

            $mensaje = "¡Pago registrado exitosamente! Tu pago será verificado en un plazo de 2 días hábiles.";
            $tipo_mensaje = "success";
            $pago_registrado = true;

            // Limpiar formulario después del éxito
            $_POST = array();
        } catch (PDOException $e) {
            $mensaje = "Error al registrar el pago: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = implode("<br>", $errores);
        $tipo_mensaje = "error";
    }
}

// Obtener pagos del usuario actual si hay email en sesión
$mis_pagos = [];
if (isset($_POST['email']) && !$pago_registrado) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM pagos_gym WHERE email = ? ORDER BY fecha_pago DESC");
        $stmt->execute([$_POST['email']]);
        $mis_pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignorar errores silenciosamente
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Pagos - Gym</title>
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
            max-width: 1200px;
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

        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .form-section,
        .list-section {
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--primary-color);
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            transform: scale(1.2);
        }

        .submit-btn {
            background: linear-gradient(45deg, var(--success-color), #1e90ff);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(46, 213, 115, 0.3);
        }

        .submit-btn.update {
            background: linear-gradient(45deg, var(--warning-color), #ff6348);
        }

        .cancel-btn {
            background: var(--secondary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
            margin-top: 10px;
            text-align: center;
        }

        .cancel-btn:hover {
            background: var(--primary-color);
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

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .equipment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #e1e8ed;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .equipment-item:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .equipment-info {
            flex: 1;
        }

        .equipment-info h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 5px;
        }

        .equipment-info p {
            color: var(--secondary-color);
            font-size: 0.9rem;
            margin-bottom: 3px;
        }

        .equipment-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.maintenance {
            background: #fff3cd;
            color: #856404;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-edit,
        .btn-delete {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-edit {
            background: #17a2b8;
            color: white;
        }

        .btn-edit:hover {
            background: #138496;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: var(--error-color);
            color: white;
        }

        .btn-delete:hover {
            background: #e63946;
            transform: translateY(-1px);
        }

        .no-equipment {
            text-align: center;
            color: var(--secondary-color);
            font-style: italic;
            padding: 40px 20px;
        }

        .editing-notice {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }

        /* Estilos adicionales para la página de pagos */
        .info-box {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .info-box h3 {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .pricing-option {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .pricing-option:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .pricing-option.popular {
            border-color: var(--success-color);
            background: linear-gradient(45deg, #e8f5e8, #d4edda);
        }

        .pricing-option h4 {
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .pricing-option .price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-color);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 15px;
            border: 2px dashed #e1e8ed;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
            color: var(--secondary-color);
            font-weight: 500;
        }

        .file-input-label:hover {
            border-color: var(--primary-color);
            background: #f0f4ff;
        }

        .verification-notice {
            background: #fff3cd;
            color: #856404;
            padding: 20px;
            border-radius: 15px;
            margin-top: 25px;
            text-align: center;
            border: 1px solid #ffeaa7;
        }

        .verification-notice h3 {
            margin-bottom: 10px;
            font-size: 1.3rem;
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .header h1 {
                font-size: 1.8rem;
            }

            .equipment-item {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .equipment-actions {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .pricing-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>💳 Registro de Pagos</h1>
            <div>
                <a href="inicio.php" class="back-btn">← Volver al Inicio</a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="message <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <?php if ($pago_registrado): ?>
            <div class="verification-notice">
                <h3>⏰ Proceso de Verificación</h3>
                <p>Tu pago ha sido registrado correctamente. Nuestro equipo verificará tu pago en un plazo de <strong>2 días hábiles</strong>.</p>
                <p>Recibirás una confirmación una vez que el pago sea aprobado.</p>
            </div>
        <?php else: ?>
            <!-- Información de Precios -->
            <div class="info-box">
                <h3>📋 Planes de Membresía Disponibles</h3>
                <div class="pricing-grid">
                    <div class="pricing-option">
                        <h4>Mensual</h4>
                        <div class="price">$400 MXN</div>
                        <small>1 mes</small>
                    </div>
                    <div class="pricing-option popular">
                        <h4>Trimestral</h4>
                        <div class="price">$1,080 MXN</div>
                        <small>3 meses (10% desc.)</small>
                    </div>
                    <div class="pricing-option">
                        <h4>Semestral</h4>
                        <div class="price">$2,040 MXN</div>
                        <small>6 meses (15% desc.)</small>
                    </div>
                    <div class="pricing-option">
                        <h4>Anual</h4>
                        <div class="price">$3,600 MXN</div>
                        <small>12 meses (25% desc.)</small>
                    </div>
                </div>
            </div>

            <center>
                <!-- Formulario de Registro de Pago -->
                <div class="main-content">
                    <div class="form-section">
                        <h2>💰 Registrar Nuevo Pago</h2>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email">Email *</label>
                                    <input type="email" id="email" name="email"
                                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                        required>
                                </div>

                                <div class="form-group">
                                    <label for="nombre">Nombre Completo *</label>
                                    <input type="text" id="nombre" name="nombre"
                                        value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>"
                                        required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="fecha_pago">Fecha de Pago *</label>
                                    <input type="date" id="fecha_pago" name="fecha_pago"
                                        value="<?php echo htmlspecialchars($_POST['fecha_pago'] ?? date('Y-m-d')); ?>"
                                        required>
                                </div>

                                <div class="form-group">
                                    <label for="monto">Monto Pagado (MXN) *</label>
                                    <input type="number" id="monto" name="monto" step="0.01" min="0"
                                        value="<?php echo htmlspecialchars($_POST['monto'] ?? ''); ?>"
                                        placeholder="400.00" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="duracion_meses">Duración de la Membresía *</label>
                                <select id="duracion_meses" name="duracion_meses" required>
                                    <option value="">Selecciona la duración</option>
                                    <option value="1" <?php echo (isset($_POST['duracion_meses']) && $_POST['duracion_meses'] == '1') ? 'selected' : ''; ?>>1 mes - $400 MXN</option>
                                    <option value="3" <?php echo (isset($_POST['duracion_meses']) && $_POST['duracion_meses'] == '3') ? 'selected' : ''; ?>>3 meses - $1,080 MXN</option>
                                    <option value="6" <?php echo (isset($_POST['duracion_meses']) && $_POST['duracion_meses'] == '6') ? 'selected' : ''; ?>>6 meses - $2,040 MXN</option>
                                    <option value="12" <?php echo (isset($_POST['duracion_meses']) && $_POST['duracion_meses'] == '12') ? 'selected' : ''; ?>>12 meses - $3,600 MXN</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="foto_pago"> Transferir 834230123120912 BBVA</label>

                                <label for="foto_pago">Comprobante de Pago (obligatoria Enviar A Gymtrinity@gmail.com)</label>

                                
                            <button type="submit" class="submit-btn">
                                💾 Registrar Pago
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>


    </div>
    </center>
    <script>
        // Auto-hide mensajes después de 7 segundos
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(message) {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.5s ease';
                setTimeout(function() {
                    message.style.display = 'none';
                }, 500);
            });
        }, 7000);

        // Actualizar precio según duración seleccionada
        document.getElementById('duracion_meses').addEventListener('change', function() {
            const duracion = this.value;
            const montoInput = document.getElementById('monto');

            const precios = {
                '1': 400,
                '3': 1080,
                '6': 2040,
                '12': 3600
            };

            if (precios[duracion]) {
                montoInput.value = precios[duracion];
            }
        });

        // Mostrar nombre del archivo seleccionado
        document.getElementById('foto_pago').addEventListener('change', function() {
            const label = document.querySelector('.file-input-label');
            if (this.files && this.files[0]) {
                label.innerHTML = `📎 ${this.files[0].name}`;
            } else {
                label.innerHTML = '📎 Subir comprobante de pago <small>(JPG, PNG, PDF - Máx. 5MB)</small>';
            }
        });

        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const nombre = document.getElementById('nombre').value;
            const monto = parseFloat(document.getElementById('monto').value);
            const duracion = parseInt(document.getElementById('duracion_meses').value);

            if (!email || !nombre || !monto || !duracion) {
                e.preventDefault();
                alert('Por favor completa todos los campos obligatorios.');
                return false;
            }

            if (monto <= 0) {
                e.preventDefault();
                alert('El monto debe ser mayor a 0.');
                return false;
            }

            // Confirmación antes de enviar
            if (!confirm('¿Confirmas que los datos son correctos? Una vez enviado, tu pago será revisado por nuestro equipo.')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>

</html>