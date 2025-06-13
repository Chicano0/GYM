<?php
session_start();

// Validar sesión activa (cualquier usuario logueado)
if (!isset($_SESSION['email'])) {
    header("Location: forms.html");
    exit;
}

// Conexión a la base de datos
try {
    $pdo = new PDO("odbc:Driver={SQL Server};Server=26.71.132.202;Database=gym;", "sa", "Uriel2004.");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$mensaje = "";
$tipo_mensaje = "";
$instructores = [];
$sesion_generada = null;

// Función corregida para limpiar sesiones vencidas automáticamente
function limpiarSesionesVencidas($pdo)
{
    try {
        // 1. Eliminar registros donde la fecha_vencimiento ya pasó
        $stmt = $pdo->prepare("
            DELETE FROM agendas_gym 
            WHERE TRY_CONVERT(DATE, fecha_vencimiento) < CAST(GETDATE() AS DATE)
        ");
        $stmt->execute();

        // 2. Eliminar sesiones de días anteriores (más seguro que calcular minutos exactos)
        $stmt = $pdo->prepare("
            DELETE FROM agendas_gym 
            WHERE TRY_CONVERT(DATE, fecha_sesion) < CAST(GETDATE() AS DATE)
            AND estado = 'Activa'
        ");
        $stmt->execute();

        // 3. Para mayor precisión, eliminar sesiones del día actual que ya terminaron
        // (solo si la hora actual es mayor que hora_sesion + 2 horas como margen de seguridad)
        $stmt = $pdo->prepare("
            DELETE FROM agendas_gym 
            WHERE TRY_CONVERT(DATE, fecha_sesion) = CAST(GETDATE() AS DATE)
            AND estado = 'Activa'
            AND DATEPART(HOUR, GETDATE()) > (DATEPART(HOUR, TRY_CONVERT(TIME, hora_sesion)) + 2)
        ");
        $stmt->execute();

        return true;
    } catch (PDOException $e) {
        error_log("Error al limpiar sesiones vencidas: " . $e->getMessage());
        return false;
    }
}

// Ejecutar limpieza automática al cargar la página
limpiarSesionesVencidas($pdo);

// Obtener información del usuario logueado
$usuario_info = [];
try {
    // Buscar en la tabla de usuarios (ajusta el nombre de tu tabla de usuarios)
    $stmt = $pdo->prepare("SELECT nombre, email FROM usuarios_gym WHERE email = ?");
    $stmt->execute([$_SESSION['email']]);
    $usuario_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si no se encuentra en usuarios, usar solo el email
    if (!$usuario_info) {
        $usuario_info = [
            'nombre' => explode('@', $_SESSION['email'])[0], // Usar parte antes del @
            'email' => $_SESSION['email']
        ];
    }
} catch (PDOException $e) {
    $usuario_info = [
        'nombre' => explode('@', $_SESSION['email'])[0],
        'email' => $_SESSION['email']
    ];
}

// Obtener lista de instructores con manejo de diferentes nombres de columna
try {
    $stmt = $pdo->query("SELECT * FROM Instructores ORDER BY Nombre_instructor");
    $instructores_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalizar los datos para asegurar que tengan la clave 'Minutos'
    $instructores = [];
    foreach ($instructores_raw as $instructor) {
        // Crear una copia normalizada del instructor
        $instructor_normalizado = $instructor;

        // Buscar la columna de minutos con diferentes posibles nombres
        if (!isset($instructor['Minutos'])) {
            // Posibles nombres de columna para minutos
            $posibles_minutos = ['minutos', 'duracion', 'tiempo', 'minutos_recomendados', 'duracion_minutos'];

            foreach ($posibles_minutos as $campo) {
                if (isset($instructor[$campo])) {
                    $instructor_normalizado['Minutos'] = $instructor[$campo];
                    break;
                }
            }

            // Si no se encuentra ninguna columna de minutos, asignar un valor por defecto
            if (!isset($instructor_normalizado['Minutos'])) {
                $instructor_normalizado['Minutos'] = 60; // Valor por defecto
            }
        }

        $instructores[] = $instructor_normalizado;
    }
} catch (PDOException $e) {
    $mensaje = "Error al cargar instructores: " . $e->getMessage();
    $tipo_mensaje = "error";
}

// Procesar formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $instructores_seleccionados = isset($_POST['instructor_ids']) ? $_POST['instructor_ids'] : [];
    $fecha_sesion = trim($_POST['fecha_sesion'] ?? '');
    $hora_sesion = trim($_POST['hora_sesion'] ?? '');
    $minutos_personalizados = isset($_POST['minutos_personalizados']) ? (int)$_POST['minutos_personalizados'] : 0;
    $fecha_vencimiento = trim($_POST['fecha_vencimiento'] ?? '');

    // Validaciones
    if (empty($instructores_seleccionados) || empty($fecha_sesion) || empty($hora_sesion) || $minutos_personalizados <= 0 || empty($fecha_vencimiento)) {
        $mensaje = "Todos los campos son obligatorios. Debe seleccionar al menos un instructor, definir los minutos y la fecha de vencimiento.";
        $tipo_mensaje = "error";
    } else {
        // Validar que la fecha no sea en el pasado
        $fecha_actual = new DateTime();
        $fecha_seleccionada = new DateTime($fecha_sesion . ' ' . $hora_sesion);
        $fecha_venc = new DateTime($fecha_vencimiento);

        if ($fecha_seleccionada <= $fecha_actual) {
            $mensaje = "La fecha y hora de la sesión debe ser futura.";
            $tipo_mensaje = "error";
        } elseif ($fecha_venc < new DateTime($fecha_sesion)) {
            $mensaje = "La fecha de vencimiento no puede ser anterior a la fecha de la sesión.";
            $tipo_mensaje = "error";
        } else {
            try {
                // Obtener información de los instructores seleccionados
                $placeholders = str_repeat('?,', count($instructores_seleccionados) - 1) . '?';
                $stmt = $pdo->prepare("SELECT * FROM Instructores WHERE Id IN ($placeholders)");
                $stmt->execute($instructores_seleccionados);
                $instructores_info_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Normalizar los datos de instructores seleccionados
                $instructores_info = [];
                foreach ($instructores_info_raw as $instructor) {
                    $instructor_normalizado = $instructor;

                    // Asegurar que tenga la clave 'Minutos'
                    if (!isset($instructor['Minutos'])) {
                        $posibles_minutos = ['minutos', 'duracion', 'tiempo', 'minutos_recomendados', 'duracion_minutos'];

                        foreach ($posibles_minutos as $campo) {
                            if (isset($instructor[$campo])) {
                                $instructor_normalizado['Minutos'] = $instructor[$campo];
                                break;
                            }
                        }

                        if (!isset($instructor_normalizado['Minutos'])) {
                            $instructor_normalizado['Minutos'] = 60; // Valor por defecto
                        }
                    }

                    $instructores_info[] = $instructor_normalizado;
                }

                if (count($instructores_info) === count($instructores_seleccionados)) {
                    // Insertar en la tabla agendas_gym para cada instructor seleccionado
                    $pdo->beginTransaction();

                    foreach ($instructores_info as $instructor) {
                        $stmt = $pdo->prepare("
                            INSERT INTO agendas_gym (
                                usuario_email, 
                                usuario_nombre, 
                                nombre_instructor, 
                                Area_Instructor, 
                                Minutos, 
                                Tipo_Actividad,
                                fecha_sesion,
                                hora_sesion,
                                minutos_asignados,
                                fecha_vencimiento,
                                fecha_creacion,
                                estado
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        $stmt->execute([
                            $usuario_info['email'],
                            $usuario_info['nombre'],
                            $instructor['nombre_instructor'],
                            $instructor['Area_Instructor'],
                            $instructor['Minutos'], // Ahora está garantizado que existe
                            $instructor['Tipo_Actividad'],
                            $fecha_sesion,
                            $hora_sesion,
                            $minutos_personalizados,
                            $fecha_vencimiento,
                            date('Y-m-d H:i:s'),
                            'Activa'
                        ]);
                    }

                    $pdo->commit();

                    // Crear la sesión generada para mostrar
                    $sesion_generada = [
                        'instructores' => $instructores_info,
                        'fecha' => $fecha_sesion,
                        'hora' => $hora_sesion,
                        'minutos_asignados' => $minutos_personalizados,
                        'fecha_vencimiento' => $fecha_vencimiento,
                        'usuario' => $usuario_info,
                        'fecha_generacion' => date('Y-m-d H:i:s'),
                        'estado' => 'Activa'
                    ];

                    $mensaje = "¡Sesión generada exitosamente! Tu cita ha sido programada con " . count($instructores_seleccionados) . " instructor(es) y guardada en el sistema.";
                    $tipo_mensaje = "success";

                    // Limpiar formulario
                    $_POST = array();
                } else {
                    $mensaje = "Algunos instructores seleccionados no fueron encontrados.";
                    $tipo_mensaje = "error";
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensaje = "Error al generar la sesión: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
}

// Obtener las sesiones activas del usuario actual para mostrar
$sesiones_activas = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM agendas_gym 
        WHERE usuario_email = ? AND estado = 'Activa' 
        ORDER BY fecha_sesion, hora_sesion
    ");
    $stmt->execute([$_SESSION['email']]);
    $sesiones_activas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Error silencioso para no afectar la funcionalidad principal
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Sesión con Instructor - Gym</title>

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

        .user-info {
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
            grid-template-columns: 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .form-section {
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
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .instructor-card {
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .instructor-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .instructor-card.selected {
            border-color: var(--success-color);
            background: #f0fff4;
        }

        .instructor-card .checkbox {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 20px;
            height: 20px;
        }

        .instructor-info h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 5px;
            margin-right: 40px;
        }

        .instructor-info p {
            color: var(--secondary-color);
            font-size: 0.9rem;
            margin-bottom: 3px;
        }

        .selected-count {
            background: var(--success-color);
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 15px;
        }

        .minutes-input {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .minutes-input label {
            color: white !important;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .minutes-input input {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            font-size: 1.2rem;
            font-weight: 600;
            text-align: center;
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

        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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

        .session-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-top: 20px;
            box-shadow: var(--card-shadow);
        }

        .session-summary h3 {
            margin-bottom: 20px;
            font-size: 1.4rem;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-weight: 500;
        }

        .summary-value {
            font-weight: 700;
        }

        .instructors-list {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
        }

        .instructor-item {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .instructor-item:last-child {
            border-bottom: none;
        }

        .datetime-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .vencimiento-group {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .vencimiento-group label {
            color: #856404 !important;
            font-weight: 600;
        }

        .no-instructors {
            text-align: center;
            color: var(--secondary-color);
            font-style: italic;
            padding: 40px 20px;
        }

        .active-sessions {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            margin-top: 30px;
        }

        .session-card {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            border-left: 4px solid var(--success-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .session-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .session-instructor {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .session-status {
            background: var(--success-color);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .session-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            color: var(--secondary-color);
            font-size: 0.9rem;
        }

        .auto-clean-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: #1565c0;
            font-size: 0.9rem;
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

            .datetime-group {
                grid-template-columns: 1fr;
            }

            .session-details {
                grid-template-columns: 1fr;
            }
        }
    </style>

</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🏋️ Generar Sesión con Instructor</h1>
            <div>
                <a href="inicio.php" class="back-btn" style="display: inline-block; margin: 0;">
                    Inicio
                </a>
                <span class="user-info">👤 <?php echo htmlspecialchars($usuario_info['nombre']); ?> (<?php echo htmlspecialchars($_SESSION['email']); ?>)</span>
            </div>
        </div>

        <div class="auto-clean-info">
            ℹ️ <strong>Sistema de Auto-limpieza:</strong> Las sesiones se eliminan automáticamente cuando pasan los minutos asignados o cuando vence la fecha de vencimiento.
        </div>

        <?php if ($mensaje): ?>
            <div class="message <?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Contenido Principal -->
        <div class="main-content">
            <?php if (!$sesion_generada): ?>
                <!-- Formulario para Generar Sesión -->
                <div class="form-section">
                    <h2 class="section-title">
                        📅 Programar Nueva Sesión
                    </h2>

                    <?php if (empty($instructores)): ?>
                        <div class="no-instructors">
                            No hay instructores disponibles en este momento.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label>Seleccionar Instructores * (Puedes seleccionar varios)</label>
                                <div id="selectedCount" class="selected-count" style="display: none;">
                                    0 instructores seleccionados
                                </div>

                                <?php foreach ($instructores as $instructor): ?>
                                    <div class="instructor-card" onclick="toggleInstructor(<?php echo $instructor['Id']; ?>)">
                                        <input type="checkbox" name="instructor_ids[]" value="<?php echo $instructor['Id']; ?>"
                                            id="instructor_<?php echo $instructor['Id']; ?>" class="checkbox">
                                        <div class="instructor-info">
                                            <h4><?php echo htmlspecialchars($instructor['nombre_instructor']); ?></h4>
                                            <p><strong>Área:</strong> <?php echo htmlspecialchars($instructor['Area_Instructor']); ?></p>
                                            <p><strong>Tipo de Actividad:</strong> <?php echo htmlspecialchars($instructor['Tipo_Actividad']); ?></p>
                                            <p><strong>Duración recomendada:</strong> <?php echo htmlspecialchars((string)$instructor['Minutos']); ?> minutos</p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="minutes-input">
                                <label for="minutos_personalizados">⏱️ Minutos a Asignar *</label>
                                <input type="number" id="minutos_personalizados" name="minutos_personalizados"
                                    min="15" max="300" step="15" placeholder="Ej: 60"
                                    value="<?php echo htmlspecialchars($_POST['minutos_personalizados'] ?? ''); ?>" required>
                                <small style="color: rgba(255,255,255,0.8); display: block; margin-top: 5px;">
                                    Mínimo 15 minutos, máximo 300 minutos (5 horas)
                                </small>
                            </div>

                            <div class="datetime-group">
                                <div class="form-group">
                                    <label for="fecha_sesion">Fecha de la Sesión *</label>
                                    <input type="date" id="fecha_sesion" name="fecha_sesion"
                                        min="<?php echo date('Y-m-d'); ?>"
                                        value="<?php echo htmlspecialchars($_POST['fecha_sesion'] ?? ''); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="hora_sesion">Hora de la Sesión *</label>
                                    <input type="time" id="hora_sesion" name="hora_sesion"
                                        value="<?php echo htmlspecialchars($_POST['hora_sesion'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="vencimiento-group">
                                <div class="form-group">
                                    <label for="fecha_vencimiento">⚠️ Fecha de Vencimiento de los Minutos *</label>
                                    <input type="date" id="fecha_vencimiento" name="fecha_vencimiento"
                                        min="<?php echo date('Y-m-d'); ?>"
                                        value="<?php echo htmlspecialchars($_POST['fecha_vencimiento'] ?? date('Y-m-d')); ?>" required>
                                    <small style="color: #856404; display: block; margin-top: 5px;">
                                        Los minutos asignados vencerán en esta fecha y el registro se eliminará automáticamente.
                                    </small>
                                </div>
                            </div>

                            <button type="submit" class="submit-btn" id="submitBtn" disabled>
                                🎯 Generar y Guardar Sesión
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Resumen de la Sesión Generada -->
                <div class="session-summary">
                    <h3>✅ ¡Sesión Programada y Guardada Exitosamente!</h3>

                    <div class="summary-item">
                        <span class="summary-label">👨‍🏫 Instructores Asignados:</span>
                        <span class="summary-value"><?php echo count($sesion_generada['instructores']); ?> instructor(es)</span>
                    </div>

                    <div class="instructors-list">
                        <?php foreach ($sesion_generada['instructores'] as $instructor): ?>
                            <div class="instructor-item">
                                <strong><?php echo htmlspecialchars($instructor['nombre_instructor']); ?></strong> -
                                <?php echo htmlspecialchars($instructor['Area_Instructor']); ?>
                                (<?php echo htmlspecialchars($instructor['Tipo_Actividad']); ?>)
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label">⏱️ Minutos Asignados:</span>
                        <span class="summary-value"><?php echo $sesion_generada['minutos_asignados']; ?> minutos</span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label">📅 Fecha de Sesión:</span>
                        <span class="summary-value"><?php echo date('d/m/Y', strtotime($sesion_generada['fecha'])); ?></span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label">🕐 Hora:</span>
                        <span class="summary-value"><?php echo date('H:i', strtotime($sesion_generada['hora'])); ?></span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label">⚠️ Vencimiento de Minutos:</span>
                        <span class="summary-value"><?php echo date('d/m/Y', strtotime($sesion_generada['fecha_vencimiento'])); ?></span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label">👤 Usuario:</span>
                        <span class="summary-value"><?php echo htmlspecialchars($sesion_generada['usuario']['nombre']); ?></span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label">📊 Estado:</span>
                        <span class="summary-value"><?php echo $sesion_generada['estado']; ?></span>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 20px;">
                    <a href="Agendar_Entranamiento.php" class="back-btn" style="display: inline-block; margin: 0;">
                        🔄 Programar Nueva Sesión
                    </a>
                    <a href="inicio.php" class="back-btn" style="display: inline-block; margin: 0;">
                        Inicio
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Mostrar Sesiones Activas -->
        <?php if (!empty($sesiones_activas)): ?>
            <div class="active-sessions">
                <h2 class="section-title">
                    📋 Tus Sesiones Activas (<?php echo count($sesiones_activas); ?>)
                </h2>

                <?php foreach ($sesiones_activas as $sesion): ?>
                    <div class="session-card">
                        <div class="session-header">
                            <div class="session-instructor">
                                🏋️ <?php echo htmlspecialchars($sesion['nombre_instructor']); ?>
                            </div>
                            <div class="session-status">
                                <?php echo htmlspecialchars($sesion['estado']); ?>
                            </div>
                        </div>
                        <div class="session-details">
                            <div><strong>📅 Fecha:</strong> <?php echo date('d/m/Y', strtotime($sesion['fecha_sesion'])); ?></div>
                            <div><strong>🕐 Hora:</strong> <?php echo date('H:i', strtotime($sesion['hora_sesion'])); ?></div>
                            <div><strong>⏱️ Minutos:</strong> <?php echo htmlspecialchars($sesion['minutos_asignados']); ?> min</div>
                            <div><strong>🎯 Área:</strong> <?php echo htmlspecialchars($sesion['Area_Instructor']); ?></div>
                            <div><strong>🏃 Actividad:</strong> <?php echo htmlspecialchars($sesion['Tipo_Actividad']); ?></div>
                            <div><strong>⚠️ Vence:</strong> <?php echo date('d/m/Y', strtotime($sesion['fecha_vencimiento'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleInstructor(instructorId) {
            const checkbox = document.querySelector(`#instructor_${instructorId}`);
            const card = checkbox.closest('.instructor-card');

            // Toggle checkbox
            checkbox.checked = !checkbox.checked;

            // Toggle visual selection
            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }

            // Update counter and form validity
            updateSelectedCount();
            checkFormValidity();
        }

        function updateSelectedCount() {
            const selected = document.querySelectorAll('input[name="instructor_ids[]"]:checked');
            const counter = document.getElementById('selectedCount');

            if (selected.length > 0) {
                counter.style.display = 'inline-block';
                counter.textContent = `${selected.length} instructor${selected.length > 1 ? 'es' : ''} seleccionado${selected.length > 1 ? 's' : ''}`;
            } else {
                counter.style.display = 'none';
            }
        }

        function checkFormValidity() {
            const instructoresSelected = document.querySelectorAll('input[name="instructor_ids[]"]:checked');
            const fecha = document.getElementById('fecha_sesion').value;
            const hora = document.getElementById('hora_sesion').value;
            const minutos = document.getElementById('minutos_personalizados').value;
            const fechaVencimiento = document.getElementById('fecha_vencimiento').value;
            const submitBtn = document.getElementById('submitBtn');

            if (instructoresSelected.length > 0 && fecha && hora && minutos && parseInt(minutos) >= 15 && fechaVencimiento) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }

        // Event listeners para todos los campos
        document.getElementById('fecha_sesion').addEventListener('change', function() {
            // Auto-ajustar fecha de vencimiento al día de la sesión si está vacía
            const fechaVencimiento = document.getElementById('fecha_vencimiento');
            if (!fechaVencimiento.value || fechaVencimiento.value < this.value) {
                fechaVencimiento.value = this.value;
            }
            checkFormValidity();
        });

        document.getElementById('hora_sesion').addEventListener('change', checkFormValidity);
        document.getElementById('minutos_personalizados').addEventListener('input', checkFormValidity);
        document.getElementById('fecha_vencimiento').addEventListener('change', checkFormValidity);

        // Auto-hide mensajes después de 5 segundos
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(message) {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.5s ease';
                setTimeout(function() {
                    message.style.display = 'none';
                }, 500);
            });
        }, 5000);

        // Establecer hora mínima si la fecha es hoy
        document.getElementById('fecha_sesion').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            const horaInput = document.getElementById('hora_sesion');

            if (selectedDate.toDateString() === today.toDateString()) {
                const currentTime = today.getHours().toString().padStart(2, '0') + ':' +
                    today.getMinutes().toString().padStart(2, '0');
                horaInput.min = currentTime;
            } else {
                horaInput.removeAttribute('min');
            }
        });

        // Auto-completar fecha de vencimiento por defecto al día actual
        document.addEventListener('DOMContentLoaded', function() {
            const fechaVencimiento = document.getElementById('fecha_vencimiento');
            if (!fechaVencimiento.value) {
                fechaVencimiento.value = '<?php echo date('Y-m-d'); ?>';
            }
        });

        // Auto-refresh para verificar sesiones vencidas cada 5 minutos
        setInterval(function() {
            // Solo refrescar si no hay formulario siendo llenado
            const form = document.querySelector('form');
            if (form) {
                const formData = new FormData(form);
                let hasData = false;
                for (let [key, value] of formData.entries()) {
                    if (value && key !== 'fecha_vencimiento') {
                        hasData = true;
                        break;
                    }
                }
                if (!hasData) {
                    location.reload();
                }
            } else {
                location.reload();
            }
        }, 300000); // 5 minutos
    </script>
</body>

</html>