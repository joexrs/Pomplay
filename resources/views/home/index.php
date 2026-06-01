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
                        <option value="<?= htmlspecialchars((string) $loc['id_local']) ?>">
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
            <div class="search-field date-wrapper glass-select" id="datePickerTrigger">

                <span id="date-label" class="date-label">
                    <?= !empty($searchDate) ? date('d/m/Y', strtotime($searchDate)) : 'FECHA' ?>
                </span>

                <input type="date"
                    name="search_date"
                    id="search_date"
                    value="<?= htmlspecialchars($searchDate ?? '') ?>"
                    class="date-native-input" />
            </div>

            <!-- FILTRO 4: HORA (deshabilitada hasta elegir fecha) -->
            <div class="search-field">
                <select name="search_hora" id="search_hora" class="glass-select" disabled>
                    <option value="">HORA</option>
                </select>
            </div>

            <button type="submit" class="btn-search glass-btn">BUSCAR</button>
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
(function () {
    const BASE = '<?= $baseUrl ?>';

    const localSel  = document.getElementById('search_local');
    const canchaSel = document.getElementById('search_cancha');
    const fechaInp  = document.getElementById('search_date');
    const horaSel   = document.getElementById('search_hora');
    const fechaTrigger = document.getElementById('datePickerTrigger');
    const fechaLabel = document.getElementById('date-label');

    function resetSelect(sel, placeholder) {
        sel.innerHTML = `<option value="">${placeholder}</option>`;
    }

    function refreshHoras() {
        const cancha = canchaSel.value;
        const fecha  = fechaInp.value;

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

    function updateFechaLabel() {
        const v = fechaInp.value;
        if (v) {
            const [y, m, d] = v.split('-');
            fechaLabel.textContent = `${d}/${m}/${y}`;
        } else {
            fechaLabel.textContent = 'FECHA';
        }
    }

    fechaTrigger.addEventListener('click', function () {
        if (typeof fechaInp.showPicker === 'function') {
            fechaInp.showPicker();
        } else {
            fechaInp.focus();
            fechaInp.click();
        }
    });

    fechaInp.addEventListener('change', function () {
        updateFechaLabel();
        refreshHoras();
    });

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
    if (fechaInp.value) {
        horaSel.disabled = false;
        refreshHoras();
    }

})();
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
