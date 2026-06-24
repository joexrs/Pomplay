<?php require __DIR__ . '/../layouts/header.php'; ?>
<section class="hero-section">
    <div class="hero-bg" id="heroBg"></div>
    <div class="hero-content">
        <h1 class="hero-title">COMPARTE TU PASIÓN<br><span class="accent-bold">POR EL DEPORTE</span></h1>

        <form class="search-card transparent-search" method="GET" action="<?= $baseUrl ?>/index.php" id="filterForm">

            <!-- FILTRO 1: LOCAL -->
            <div class="search-field">
                <select id="search_local" class="glass-select">
                    <option value="">LOCAL</option>
                    <?php foreach ($locales as $loc): ?>
                        <option value="<?= htmlspecialchars((string) $loc['id_local']) ?>" data-es-privado="<?= (int) ($loc['es_privado'] ?? 0) ?>">
                            <?= htmlspecialchars(mb_strtoupper($loc['nombre_local'], 'UTF-8')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- FILTRO 2: CANCHA (deshabilitada hasta elegir local) -->
            <div class="search-field">
                <select name="search_cancha" id="search_cancha" class="glass-select" disabled>
                    <option value="">CANCHA</option>
                </select>
            </div>

            <!-- FILTRO 3: FECHA -->
            
            <div class="search-field date-wrapper glass-select" id="datePickerTrigger" style="position:relative; cursor:pointer;">
                <span id="date-label" class="date-label">
                    <?= !empty($searchDate) ? date('d/m/Y', strtotime($searchDate)) : 'FECHA' ?>
                </span>
                <input type="date"
                    id="search_date_real"
                    value="<?= htmlspecialchars($searchDate ?? '') ?>"
                    style="position:absolute; inset:0; width:100%; height:100%; opacity:0; cursor:pointer;" />
            </div>

            <input type="hidden" id="search_date" name="search_date" value="<?= htmlspecialchars($searchDate ?? '') ?>" />

            <!-- FILTRO 4: HORA (deshabilitada hasta elegir fecha) -->
            <div class="search-field">
                <select name="search_hora" id="search_hora" class="glass-select" disabled>
                    <option value="">HORA</option>
                </select>
            </div>

            <button type="submit" class="btn-search glass-btn">BUSCAR</button>
            <input type="hidden" name="access_code" id="access_code" value="" />
        </form>
    </div>
</section>

<?php if (isset($errorMessage)): ?>
<div class="section">
    <div class="empty-state reveal">
        <div class="empty-state-icon"><i class="fas fa-search"></i></div>
        <h3>Resultado de búsqueda</h3>
        <p><?= htmlspecialchars($errorMessage) ?></p>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    (function () {
    const BASE = '<?= $baseUrl ?>';

    const localSel       = document.getElementById('search_local');
    const canchaSel      = document.getElementById('search_cancha');
    const fechaHiddenInp = document.getElementById('search_date');
    const fechaRealInp   = document.getElementById('search_date_real');
    const horaSel        = document.getElementById('search_hora');
    const fechaLabel     = document.getElementById('date-label');

    /* ----------------------------------------------------------
       Helpers
    ---------------------------------------------------------- */
    function resetSelect(sel, placeholder) {
        sel.innerHTML = `<option value="">${placeholder}</option>`;
    }

    function updateFechaLabel() {
        const v = fechaHiddenInp.value;
        if (v) {
            const [y, m, d] = v.split('-');
            fechaLabel.textContent = `${d}/${m}/${y}`;
        } else {
            fechaLabel.textContent = 'FECHA';
        }
    }

    function setFechaValue(value) {
        fechaHiddenInp.value = value || '';
        if (fechaRealInp) fechaRealInp.value = value || '';
        updateFechaLabel();
    }

    function refreshHoras() {
        const cancha = canchaSel.value;
        const fecha  = fechaHiddenInp.value;

        if (!fecha) {
            horaSel.disabled = true;
            resetSelect(horaSel, 'HORA');
            return;
        }

        const params = new URLSearchParams();
        if (cancha) params.set('cancha', cancha);
        params.set('fecha', fecha);

        resetSelect(horaSel, 'Cargando...');
        horaSel.disabled = true;

        fetch(`${BASE}/api/horas?${params}`)
            .then(r => { if (!r.ok) throw new Error(); return r.json(); })
            .then(data => {
                resetSelect(horaSel, 'HORA');
                (data || []).forEach(h => {
                    const opt = document.createElement('option');
                    opt.value = h.hora;
                    opt.textContent = h.hora_rango;
                    horaSel.appendChild(opt);
                });
                horaSel.disabled = false;
            })
            .catch(() => {
                resetSelect(horaSel, 'HORA');
                horaSel.disabled = false;
            });
    }

    /* ----------------------------------------------------------
       Cambio de fecha — un solo listener para Desktop, iOS y Android
    ---------------------------------------------------------- */
    if (fechaRealInp) {
        fechaRealInp.addEventListener('change', function () {
            setFechaValue(this.value);
            refreshHoras();
        });
    }

    /* ----------------------------------------------------------
       Cambio de local → cargar canchas
    ---------------------------------------------------------- */
    localSel.addEventListener('change', function () {
        const idLocal = this.value;

        canchaSel.disabled = true;
        resetSelect(canchaSel, 'CANCHA');
        horaSel.disabled = true;
        resetSelect(horaSel, 'HORA');

        if (!idLocal) return;

        resetSelect(canchaSel, 'Cargando...');

        fetch(`${BASE}/api/canchas-por-local?local=${idLocal}`)
            .then(r => { if (!r.ok) throw new Error(); return r.json(); })
            .then(data => {
                resetSelect(canchaSel, 'CANCHA');
                (data || []).forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.codigo_cancha;
                    opt.textContent = (c.descripcion || '').toUpperCase();
                    canchaSel.appendChild(opt);
                });
                canchaSel.disabled = false;
            })
            .catch(() => {
                resetSelect(canchaSel, 'CANCHA');
                canchaSel.disabled = false;
            });
    });

    canchaSel.addEventListener('change', refreshHoras);

    // Si la página cargó con fecha ya puesta (vuelta de búsqueda)
    if (fechaHiddenInp.value) {
        updateFechaLabel();
        horaSel.disabled = false;
        refreshHoras();
    }

    /* ----------------------------------------------------------
       Manejo del envío: si el local es privado, mostrar modal
    ---------------------------------------------------------- */
    const form = document.getElementById('filterForm');
    const modal = document.getElementById('privateLocalModal');
    const modalInput = document.getElementById('private_code_input');
    const privateConfirm = document.getElementById('private_confirm');
    const privateCancel = document.getElementById('private_cancel');
    const accessCodeInput = document.getElementById('access_code');

    form.addEventListener('submit', function (e) {
        const opt = localSel.options[localSel.selectedIndex];
        const esPrivado = opt ? (opt.dataset.esPrivado === '1' || opt.dataset.esPrivado === 'true') : false;

        if (esPrivado) {
            e.preventDefault();
            modal.style.display = 'flex';
            modalInput.value = '';
            setTimeout(() => modalInput.focus(), 50);
        } else {
            // limpiar cualquier código previo
            accessCodeInput.value = '';
        }
    });

    privateCancel.addEventListener('click', function () {
        modal.style.display = 'none';
    });

    privateConfirm.addEventListener('click', function () {
        const val = modalInput.value.trim();
        if (val === '') {
            modalInput.focus();
            return;
        }
        accessCodeInput.value = val;
        modal.style.display = 'none';
        form.submit();
    });

    modalInput.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            privateConfirm.click();
        } else if (ev.key === 'Escape') {
            modal.style.display = 'none';
        }
    });

    })();
});
</script>
<!-- Modal para ingresar código de acceso a locales privados -->
<div id="privateLocalModal" class="modal" style="display:none;position:fixed;inset:0;z-index:1200;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);">
    <div class="modal-content" style="background:#fff;border-radius:8px;padding:20px;max-width:400px;width:90%;box-shadow:0 8px 24px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0;margin-bottom:12px;font-size:18px;">Este local es privado</h3>
        <p style="margin:0 0 12px;color:#444;">Ingresa el código que te proporcionó el propietario para acceder.</p>
        <input id="private_code_input" type="text" inputmode="numeric" pattern="\d*" placeholder="Código de acceso" style="width:100%;padding:10px;margin-bottom:12px;border:1px solid #ddd;border-radius:4px;font-size:16px" />
        <div style="display:flex;gap:8px;justify-content:flex-end;">
            <button id="private_cancel" class="glass-btn" style="background:transparent;border:1px solid #ccc;padding:8px 12px;border-radius:4px;">Cancelar</button>
            <button id="private_confirm" class="glass-btn" style="padding:8px 12px;border-radius:4px;background:#2d7cf3;color:#fff;border:0;">Confirmar</button>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>