<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Validar sesión activa
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

// Obtener datos del usuario actual
try {
    $stmt = $pdo->prepare("SELECT nombre, email, fecha_registro FROM usuarios_gym WHERE email = ?");
    $stmt->execute([$_SESSION['email']]);
    $usuario_datos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario_datos) {
        // Si no se encuentra el usuario, usar datos por defecto
        $usuario_datos = [
            'nombre' => 'Usuario',
            'email' => $_SESSION['email'],
            'plan' => 'Básico',
            'fecha_registro' => date('Y-m-d')
        ];
    }
} catch (PDOException $e) {
    // En caso de error, usar datos por defecto
    $usuario_datos = [
        'nombre' => 'Usuario',
        'email' => $_SESSION['email'],
        'plan' => 'Básico',
        'fecha_registro' => date('Y-m-d')
    ];
}

// Obtener pagos pendientes del usuario - USANDO LA MISMA CONSULTA QUE EL ADMIN
try {
    $stmtPendientes = $pdo->prepare("SELECT nombre, monto, fecha_vencimiento FROM pagos_gym WHERE email = ? AND estatus = 'pendiente' ORDER BY fecha_vencimiento ASC");
    $stmtPendientes->execute([$_SESSION['email']]);
    $pagos_pendientes = $stmtPendientes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pagos_pendientes = [];
}

// Obtener pagos realizados del usuario
try {
    $stmtPagados = $pdo->prepare("SELECT nombre, monto, fecha_vencimiento FROM pagos_gym WHERE email = ? AND estatus = 'pagado' ORDER BY fecha_vencimiento DESC");
    $stmtPagados->execute([$_SESSION['email']]);
    $pagos_realizados = $stmtPagados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pagos_realizados = [];
}

//  Obtener TODOS los equipos (disponibles y en mantenimiento)
try {
    $stmtEquipos = $pdo->query("SELECT nombre_equipo, descripcion, en_servicio FROM equipos_mantenimiento ORDER BY en_servicio DESC");
    $equipos_todos = $stmtEquipos->fetchAll(PDO::FETCH_ASSOC);
    
    // Separar equipos disponibles y en mantenimiento
    $equipos_disponibles = [];
    $equipos_mantenimiento = [];
    
    if (!empty($equipos_todos)) {
        foreach ($equipos_todos as $equipo) {
            // Asegurar que tenemos el nombre del equipo
            if (empty($equipo['nombre_equipo'])) {
                $equipo['nombre_equipo'] = !empty($equipo['descripcion']) ? $equipo['descripcion'] : 'Equipo sin nombre';
            }
            
         
            // 0 = en mantenimiento, 1 = disponible
            if ($equipo['en_servicio'] == 0) {
                $equipos_mantenimiento[] = $equipo;
            } else {
                $equipos_disponibles[] = $equipo;
            }
        }
    }
} catch (PDOException $e) {
    $equipos_mantenimiento = [];
    $equipos_disponibles = [];
    error_log("Error al obtener equipos: " . $e->getMessage());
}

// Obtener equipos en mantenimiento (contador)
try {
    $stmtCount = $pdo->query("SELECT COUNT(*) AS total_en_mantenimiento FROM equipos_mantenimiento WHERE en_servicio = 0");
    $countResult = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $total_en_mantenimiento = $countResult['total_en_mantenimiento'] ?? 0;
} catch (PDOException $e) {
    $total_en_mantenimiento = 0;
}

// Obtener equipos disponibles 
try {
    $stmtCountDisponibles = $pdo->query("SELECT COUNT(*) AS total_disponibles FROM equipos_mantenimiento WHERE en_servicio = 1");
    $countResultDisponibles = $stmtCountDisponibles->fetch(PDO::FETCH_ASSOC);
    $total_disponibles = $countResultDisponibles['total_disponibles'] ?? 0;
} catch (PDOException $e) {
    $total_disponibles = 0;
}


$stats = [
    'pagos_pendientes' => count($pagos_pendientes),
    'pagos_realizados' => count($pagos_realizados), 
];

// Clases disponibles 
$clases_disponibles = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            Tipo_Actividad,
            hora_sesion,
            nombre_instructor
        FROM agendas_gym 
        WHERE TRY_CONVERT(DATE, fecha_sesion) = CAST(GETDATE() AS DATE)
        AND estado = 'Activa'
        ORDER BY hora_sesion
    ");
    $stmt->execute();
    $clases_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($clases_db as $clase) {
        // Contar cuántas sesiones  tiene el usuario
        $stmt_count = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM agendas_gym 
            WHERE TRY_CONVERT(DATE, fecha_sesion) = CAST(GETDATE() AS DATE)
            AND estado = 'Activa'
            AND Tipo_Actividad = ?
            AND hora_sesion = ?
            AND nombre_instructor = ?
        ");
        $stmt_count->execute([$clase['Tipo_Actividad'], $clase['hora_sesion'], $clase['nombre_instructor']]);
        $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
        
        $clases_disponibles[] = [
            'nombre' => $clase['Tipo_Actividad'],
            'hora' => date('H:i', strtotime($clase['hora_sesion'])),
            'instructor' => $clase['nombre_instructor'],
            'cupos' => $count_result['total']
        ];
    }
    
} catch (PDOException $e) {
    $clases_disponibles = [];
    error_log("Error al obtener clases de hoy: " . $e->getMessage());
}


if (empty($clases_disponibles)) {
    $clases_disponibles = [
        ['nombre' => 'No hay clases programadas', 'hora' => '--:--', 'instructor' => 'N/A', 'cupos' => 0]
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Dashboard - FitCenter</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --linear-grad: linear-gradient(to right, #141E30, #243B55);
            --grad-clr1: #141E30;
            --grad-clr2: #243B55;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f6f5f7;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: #fff;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 25px 30px 55px #5557;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: var(--grad-clr1);
            font-size: 2rem;
            font-weight: 700;
        }

        .welcome-section h2 {
            color: var(--grad-clr2);
            font-size: 1.2rem;
            font-weight: 500;
            margin-top: 5px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-direction: column;
            text-align: right;
        }

        .user-name {
            background: var(--linear-grad);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 500;
        }

        .user-email {
            color: var(--grad-clr2);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .logout-btn {
            background: #ff4757;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #ff3742;
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            box-shadow: 25px 30px 55px #5557;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--grad-clr1);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--grad-clr2);
            font-weight: 500;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .section {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 25px 30px 55px #5557;
        }

        .section h3 {
            color: var(--grad-clr1);
            margin-bottom: 20px;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .payment-item, .equipment-item, .class-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .payment-item:last-child, .equipment-item:last-child, .class-item:last-child {
            border-bottom: none;
        }

        .payment-info h4, .equipment-info h4, .class-info h4 {
            color: var(--grad-clr1);
            font-weight: 600;
            margin-bottom: 5px;
        }

        .payment-info p, .equipment-info p, .class-info p {
            color: var(--grad-clr2);
            font-size: 0.9rem;
        }

        .amount {
            font-weight: 700;
            color: #ff4757;
            font-size: 1.1rem;
        }

        .amount.paid {
            color: #2ed573;
        }

        .status-badge {
            background: #ffa502;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-badge.available {
            background: #2ed573;
        }

        .class-cupos {
            background: var(--linear-grad);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .class-cupos.low {
            background: #ff4757;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .action-btn {
            background: var(--linear-grad);
            color: white;
            padding: 20px;
            border: none;
            border-radius: 15px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: block;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(20, 30, 48, 0.3);
        }

        .action-btn.success {
            background: linear-gradient(45deg, #2ed573, #1e90ff);
        }

        .action-btn.warning {
            background: linear-gradient(45deg, #ffa502, #ff6348);
        }

        .action-btn.info {
            background: linear-gradient(45deg, #3742fa, #2f3542);
        }

        .no-data {
            text-align: center;
            color: var(--grad-clr2);
            font-style: italic;
            padding: 20px;
        }

        /* CORREGIDO: Agregar estilos para tabs de equipos */
        .equipment-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
        }

        .equipment-tab {
            padding: 10px 20px;
            background: none;
            border: none;
            color: var(--grad-clr2);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .equipment-tab.active {
            color: var(--grad-clr1);
            border-bottom: 2px solid var(--grad-clr1);
        }

        .equipment-content {
            display: none;
        }

        .equipment-content.active {
            display: block;
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
            
            .user-info {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="welcome-section">
                <h1>🏋️ Mi LIFEGYMTRINITY</h1>
                <h2>¡Bienvenido, <?php echo htmlspecialchars($usuario_datos['nombre']); ?>!</h2>
            </div>
            <div class="user-info">
                <div class="user-email"><?php echo htmlspecialchars($usuario_datos['email']); ?></div>
                <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-number"><?php echo $stats['pagos_pendientes']; ?></div>
                <div class="stat-label">Pagos Pendientes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $stats['pagos_realizados']; ?></div>
                <div class="stat-label">Pagos Realizados</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-number"><?php echo $total_en_mantenimiento; ?></div>
                <div class="stat-label">Equipos en Mantenimiento</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $total_disponibles; ?></div>
                <div class="stat-label">Equipos Disponibles</div>
            </div>
        </div>

        <!-- Contenido Principal -->
        <div class="main-content">
            <!-- Pagos Pendientes -->
            <div class="section">
                <h3>💳 Mis Pagos Pendientes</h3>
                <?php if (count($pagos_pendientes) === 0): ?>
                    <div class="no-data">¡Excelente! No tienes pagos pendientes.</div>
                <?php else: ?>
                    <?php foreach ($pagos_pendientes as $pago): ?>
                    <div class="payment-item">
                        <div class="payment-info">
                            <h4><?php echo htmlspecialchars($pago['nombre']); ?></h4>
                            <p>Vence: <?php echo htmlspecialchars($pago['fecha_vencimiento']); ?></p>
                        </div>
                        <span class="amount">$<?php echo number_format($pago['monto'], 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagos Realizados -->
            <div class="section">
                <h3>✅ Pagos Realizados</h3>
                <?php if (count($pagos_realizados) === 0): ?>
                    <div class="no-data">No hay pagos realizados.</div>
                <?php else: ?>
                    <?php foreach ($pagos_realizados as $pago): ?>
                    <div class="payment-item">
                        <div class="payment-info">
                            <h4><?php echo htmlspecialchars($pago['nombre']); ?></h4>
                            <p>Pagado: <?php echo htmlspecialchars($pago['fecha_vencimiento']); ?></p>
                        </div>
                        <span class="amount paid">$<?php echo number_format($pago['monto'], 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Segunda Fila -->
        <div class="main-content">
            <!-- CORREGIDO: Equipos con tabs para disponibles y mantenimiento -->
            <div class="section">
                <h3>🛠️ Estado de Equipos</h3>
                
                <!-- DEBUG: Mostrar información de depuración temporalmente -->
                <?php if (isset($_GET['debug'])): ?>
                <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px; font-size: 12px;">
                    <strong>DEBUG INFO:</strong><br>
                    Total equipos obtenidos: <?php echo count($equipos_todos ?? []); ?><br>
                    Equipos disponibles: <?php echo count($equipos_disponibles); ?><br>
                    Equipos en mantenimiento: <?php echo count($equipos_mantenimiento); ?><br>
                    <?php if (!empty($equipos_todos)): ?>
                        <strong>Datos de equipos:</strong><br>
                        <?php foreach ($equipos_todos as $eq): ?>
                            - <?php echo htmlspecialchars($eq['nombre_equipo'] ?? 'Sin nombre'); ?> 
                            (en_servicio: <?php echo $eq['en_servicio']; ?>)<br>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Tabs para equipos -->
                <div class="equipment-tabs">
                    <button class="equipment-tab active" onclick="showEquipmentTab('disponibles')">
                        Disponibles (<?php echo count($equipos_disponibles); ?>)
                    </button>
                    <button class="equipment-tab" onclick="showEquipmentTab('mantenimiento')">
                        Mantenimiento (<?php echo count($equipos_mantenimiento); ?>)
                    </button>
                </div>

                <!-- Contenido de equipos disponibles -->
                <div id="equipos-disponibles" class="equipment-content active">
                    <?php if (count($equipos_disponibles) === 0): ?>
                        <div class="no-data">No hay equipos disponibles.</div>
                    <?php else: ?>
                        <?php foreach ($equipos_disponibles as $equipo): ?>
                        <div class="equipment-item">
                            <div class="equipment-info">
                                <h4><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></h4>
                                <p>Estado: Disponible para uso</p>
                                <?php if (!empty($equipo['descripcion'])): ?>
                                <p><small><?php echo htmlspecialchars($equipo['descripcion']); ?></small></p>
                                <?php endif; ?>
                            </div>
                            <span class="status-badge available">Disponible</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Contenido de equipos en mantenimiento -->
                <div id="equipos-mantenimiento" class="equipment-content">
                    <?php if (count($equipos_mantenimiento) === 0): ?>
                        <div class="no-data">Todos los equipos están disponibles.</div>
                    <?php else: ?>
                        <?php foreach ($equipos_mantenimiento as $equipo): ?>
                        <div class="equipment-item">
                            <div class="equipment-info">
                                <h4><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></h4>
                                <p>Estado: En mantenimiento</p>
                                <?php if (!empty($equipo['descripcion'])): ?>
                                <p><small><?php echo htmlspecialchars($equipo['descripcion']); ?></small></p>
                                <?php endif; ?>
                            </div>
                            <span class="status-badge">Mantenimiento</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Clases Disponibles -->
            <div class="section">
                <h3>🕒 Clases de Hoy</h3>
                <?php if (!empty($clases_disponibles) && $clases_disponibles[0]['nombre'] !== 'No hay clases programadas'): ?>
                    <?php foreach($clases_disponibles as $clase): ?>
                    <div class="class-item">
                        <div class="class-info">
                            <h4><?php echo htmlspecialchars($clase['nombre']); ?></h4>
                            <p><?php echo htmlspecialchars($clase['hora']); ?> - <?php echo htmlspecialchars($clase['instructor']); ?></p>
                        </div>
                        <span class="class-cupos <?php echo $clase['cupos'] <= 3 ? 'low' : ''; ?>">
                            <?php echo $clase['cupos']; ?> sesiones activas
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="class-item">
                        <div class="class-info">
                            <h4>No hay clases programadas para hoy</h4>
                            <p>Programa una nueva sesión para ver las clases disponibles</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="section">
            <h3>⚡ Acciones Rápidas</h3>
            <div class="actions-grid">
                <a href="agregar_pago.php" class="action-btn success">
                    💰 Agregar Pago
                </a>
                <a href="agendar_entrenamiento.php" class="action-btn">
                    🗓️ Agendar Sesión
                </a>   
            </div>
        </div>
    </div>

    <script>
        function showEquipmentTab(tabName) {

            const contents = document.querySelectorAll('.equipment-content');
            contents.forEach(content => content.classList.remove('active'));
            
            const tabs = document.querySelectorAll('.equipment-tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            document.getElementById('equipos-' + tabName).classList.add('active');
            
            event.target.classList.add('active');
        }
    </script>
</body>
</html>