<?php
session_start();

// Evitar cache para que el botón atrás no muestre contenido protegido
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Validar sesión activa y usuario admin
if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'soporteverifiacion@gmail.com') {
   header("Location: forms.html");
   exit;
}

// Datos del administrador
$admin_datos = [
    'nombre' => 'Administrador',
    'email' => $_SESSION['email']
];

// Conexión a la base de datos
try {
    $pdo = new PDO("odbc:Driver={SQL Server};Server=26.71.132.202;Database=gym;", "sa", "Uriel2004.");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener total de miembros
try {
    $stmtCount = $pdo->query("SELECT COUNT(*) AS total FROM usuarios_gym");
    $row = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $stats['total_miembros'] = $row['total'] ?? 0;
} catch (PDOException $e) {
    die("Error al contar miembros: " . $e->getMessage());
}

// Obtener miembros recientes (solo nombre)
try {
    $stmt = $pdo->query("SELECT nombre FROM usuarios_gym ORDER BY fecha_registro DESC");
    $miembros_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}

// Obtener pagos pendientes
try {
    $stmtPendientes = $pdo->prepare("SELECT nombre, monto, fecha_vencimiento FROM pagos_gym WHERE estatus = 'pendiente' ORDER BY fecha_vencimiento ASC");
    $stmtPendientes->execute();
    $pagos_pendientes = $stmtPendientes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener pagos pendientes: " . $e->getMessage());
}

// Obtener pagos pagados
try {
    $stmtPagados = $pdo->prepare("SELECT nombre, monto, fecha_vencimiento FROM pagos_gym WHERE estatus = 'pagado' ORDER BY fecha_vencimiento DESC");
    $stmtPagados->execute();
    $pagos_pagados = $stmtPagados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener pagos pagados: " . $e->getMessage());
}

// Obtener equipos en mantenimiento
try {
    $stmtCount = $pdo->query("SELECT COUNT(*) AS total_en_mantenimiento FROM equipos_mantenimiento WHERE en_servicio = 0");
    $countResult = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $total_en_mantenimiento = $countResult['total_en_mantenimiento'] ?? 0;
} catch (PDOException $e) {
    $total_en_mantenimiento = 0;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Estilos específicos para el dashboard admin usando la misma base del CSS */
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
        }

        .user-name {
            background: var(--linear-grad);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 500;
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

        .member-item, .payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .member-item:last-child, .payment-item:last-child {
            border-bottom: none;
        }

        .member-info h4, .payment-info h4 {
            color: var(--grad-clr1);
            font-weight: 600;
            margin-bottom: 5px;
        }

        .member-info p, .payment-info p {
            color: var(--grad-clr2);
            font-size: 0.9rem;
        }

        .plan-badge {
            background: var(--linear-grad);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .amount {
            font-weight: 700;
            color: #ff4757;
            font-size: 1.1rem;
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

        .action-btn.danger {
            background: linear-gradient(45deg, #ff4757, #ff3742);
        }

        .action-btn.success {
            background: linear-gradient(45deg, #2ed573, #1e90ff);
        }

        .action-btn.warning {
            background: linear-gradient(45deg, #ffa502, #ff6348);
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
                <h1>🏋️ FitCenter Admin</h1>
                <h2>¡Bienvenido, <?php echo $admin_datos['nombre']; ?>!</h2>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo $admin_datos['nombre']; ?></span>
                <form method="post" action="logout.php" style="display:inline;">
                    <button type="submit" class="logout-btn">Cerrar Sesión</button>
                </form>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo $stats['total_miembros']; ?></div>
                <div class="stat-label">Total Miembros</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-number"><?php echo $total_en_mantenimiento; ?></div>
                <div class="stat-label">Equipos en Mantenimiento</div>
            </div>
        </div>

        <!-- Contenido Principal -->
        <div class="main-content">
            <!-- Pagos Pendientes -->
            <div class="section">
                <h3>💳 Pagos Pendientes</h3>
                <?php if (count($pagos_pendientes) === 0): ?>
                    <p>No hay pagos pendientes.</p>
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
                <?php if (count($pagos_pagados) === 0): ?>
                    <p>No hay pagos realizados.</p>
                <?php else: ?>
                    <?php foreach ($pagos_pagados as $pago): ?>
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
        </div>

        <!-- Acciones Rápidas -->
        <div class="section">
            <h3>⚡ Acciones Rápidas</h3>
            <div class="actions-grid">
                <a href="add_instructores.php" class="action-btn success">
                    👤 Agregar Instrucores
                </a>
                <a href="gestionar_pagos.php" class="action-btn">
                    💰 Gestionar Pagos
                </a>
                <a href="InstructoresClases.php" class="action-btn">
                    🕒 Horarios y Clases
                </a>
                <a href="equipos.php" class="action-btn warning">
                    🏋️ Gestión de Equipos
                </a>
             
            </div>
        </div>
    </div>
</body>
</html>