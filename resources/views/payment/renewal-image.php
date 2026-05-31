<!-- Vista simplificada: payment/renewal-image.php -->
<!-- Muestra imagen de renovación cuando el dueño hace click en renovar -->

<?php require __DIR__ . '/../layouts/header.php'; ?>

<!-- Renewal Instructions Image -->
<div class="renewal-container">
    <div class="renewal-card">
        <h2>Renovar Membresía</h2>
        
        <?php if ($membership && $isActive): ?>
            <div class="alert alert-info">
                <i class="fas fa-check-circle"></i>
                Tu membresía está <strong>ACTIVA</strong>
                <br>
                Vence el: <?= date('d/m/Y', strtotime($membership['fecha_vencimiento'])) ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-circle"></i>
                Tu membresía <strong>HA EXPIRADO</strong> o no está activa.
                <br>
                <strong>Debes renovar para acceder a tus videos.</strong>
            </div>
        <?php endif; ?>

        <!-- Imagen de renovación -->
        <div class="renewal-image-section">
            <img src="<?= $baseUrl . $renewalImagePath ?>" 
                 alt="Instrucciones de renovación" 
                 class="renewal-image"
                 onerror="this.src='<?= $baseUrl ?>/images/default-renewal.png'">
        </div>

        <!-- Instrucciones de texto -->
        <div class="renewal-instructions">
            <h3>Instrucciones de Renovación:</h3>
            <ol>
                <li>Realiza una transferencia al siguiente número de cuenta</li>
                <li>Incluye tu número de referencia: <strong><?= isset($user['id_usuario']) ? 'OWNER_' . $user['id_usuario'] : 'N/A' ?></strong></li>
                <li>Tu membresía se renovará automáticamente en 24 horas</li>
            </ol>
        </div>

        <!-- Botones -->
        <div class="renewal-actions">
            <a href="<?= $baseUrl ?>/owner/canchas.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <a href="<?= $baseUrl ?>/owner/membership.php" class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> Ver Estado
            </a>
        </div>
    </div>
</div>

<style>
.renewal-container {
    max-width: 600px;
    margin: 40px auto;
    padding: 20px;
}

.renewal-card {
    background: #fff;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.renewal-card h2 {
    text-align: center;
    margin-bottom: 20px;
    color: #333;
}

.alert {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.renewal-image-section {
    margin: 25px 0;
    text-align: center;
}

.renewal-image {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.renewal-instructions {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.renewal-instructions h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 16px;
}

.renewal-instructions ol {
    margin: 0;
    padding-left: 20px;
}

.renewal-instructions li {
    margin-bottom: 10px;
    line-height: 1.5;
}

.renewal-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 4px;
    text-decoration: none;
    cursor: pointer;
    border: none;
    font-size: 14px;
    transition: all 0.2s ease;
}

.btn-primary {
    background: #0066cc;
    color: #fff;
}

.btn-primary:hover {
    background: #0052a3;
}

.btn-secondary {
    background: #6c757d;
    color: #fff;
}

.btn-secondary:hover {
    background: #5a6268;
}

@media (max-width: 480px) {
    .renewal-container {
        padding: 10px;
    }
    
    .renewal-card {
        padding: 20px;
    }
    
    .renewal-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
