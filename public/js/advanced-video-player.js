/**
 * Advanced Video Player Controls
 * Proporciona controles avanzados: zoom, velocidad, captura, etc.
 */

class AdvancedVideoPlayer {
    constructor(videoElementId) {
        this.video = document.getElementById(videoElementId);
        this.container = this.video.closest('.video-player-wrap') || this.video.parentElement;
        this.currentZoom = 1;
        this.minZoom = 1;
        this.maxZoom = 3;
        this.zoomStep = 0.1;
        this.skipAmount = 10; // segundos para saltar adelante/atrás

        this.init();
    }

    init() {
        this.createCustomControls();
        this.setupEventListeners();
        this.updateControlsDisplay();
    }

    /**
     * Crear controles personalizados
     */
    createCustomControls() {
        // Crear contenedor de controles
        const controlsContainer = document.createElement('div');
        controlsContainer.className = 'advanced-video-controls';
        controlsContainer.innerHTML = `
            <div class="controls-row">
                <!-- Fila 1: Play/Pause, Skip, Time -->
                <div class="controls-group">
                    <button class="control-btn" id="playPauseBtn" title="Reproducir/Pausar">
                        <i class="fas fa-play"></i>
                    </button>
                    <button class="control-btn" id="skipBackBtn" title="Retroceder 10s">
                        <i class="fas fa-backward"></i> 10s
                    </button>
                    <button class="control-btn" id="skipForwardBtn" title="Adelantar 10s">
                        10s <i class="fas fa-forward"></i>
                    </button>
                    <span class="time-display">
                        <span id="currentTime">00:00</span> / <span id="duration">00:00</span>
                    </span>
                </div>
            </div>

            <div class="controls-row">
                <!-- Fila 2: Zoom, Speed, Volume -->
                <div class="controls-group">
                    <button class="control-btn" id="zoomOutBtn" title="Disminuir zoom">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <span class="zoom-level" id="zoomLevel">100%</span>
                    <button class="control-btn" id="zoomInBtn" title="Aumentar zoom">
                        <i class="fas fa-search-plus"></i>
                    </button>
                </div>

                <div class="controls-group">
                    <label for="speedControl" class="control-label">Velocidad:</label>
                    <select id="speedControl" class="control-select" title="Velocidad de reproducción">
                        <option value="0.5">0.5x</option>
                        <option value="1" selected>1x</option>
                        <option value="1.25">1.25x</option>
                        <option value="1.5">1.5x</option>
                        <option value="2">2x</option>
                    </select>
                </div>

                <div class="controls-group">
                    <label for="volumeControl" class="control-label">Volumen:</label>
                    <input type="range" id="volumeControl" class="volume-slider" min="0" max="100" value="100" title="Volumen">
                    <span class="volume-label" id="volumeLabel">100%</span>
                </div>
            </div>

            <div class="controls-row">
                <!-- Fila 3: Fullscreen, Picture in Picture, Snapshot -->
                <div class="controls-group">
                    <button class="control-btn" id="pipBtn" title="Picture in Picture">
                        <i class="fas fa-expand-alt"></i> PiP
                    </button>
                    <button class="control-btn" id="fullscreenBtn" title="Pantalla completa">
                        <i class="fas fa-expand"></i>
                    </button>
                    <button class="control-btn" id="snapshotBtn" title="Capturar fotograma">
                        <i class="fas fa-camera"></i>
                    </button>
                    <button class="control-btn" id="downloadBtn" title="Descargar video">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>

            <div class="progress-container">
                <input type="range" id="progressBar" class="progress-bar" min="0" max="100" value="0" title="Barra de progreso">
            </div>

            <!-- Indicador de zoom si está activo -->
            <div class="zoom-indicator" id="zoomIndicator" style="display: none;">
                Zoom: <span id="zoomPercentage">100</span>%
            </div>
        `;

        // Insertar controles después del video
        this.container.insertAdjacentElement('afterend', controlsContainer);
        this.controlsContainer = controlsContainer;
    }

    /**
     * Configurar event listeners
     */
    setupEventListeners() {
        // Play/Pause
        document.getElementById('playPauseBtn').addEventListener('click', () => this.togglePlayPause());
        
        // Skip buttons
        document.getElementById('skipBackBtn').addEventListener('click', () => this.skipTime(-this.skipAmount));
        document.getElementById('skipForwardBtn').addEventListener('click', () => this.skipTime(this.skipAmount));

        // Zoom controls
        document.getElementById('zoomInBtn').addEventListener('click', () => this.zoomIn());
        document.getElementById('zoomOutBtn').addEventListener('click', () => this.zoomOut());

        // Speed control
        document.getElementById('speedControl').addEventListener('change', (e) => this.setPlaybackSpeed(e.target.value));

        // Volume control
        document.getElementById('volumeControl').addEventListener('input', (e) => this.setVolume(e.target.value));

        // Fullscreen & PiP
        document.getElementById('fullscreenBtn').addEventListener('click', () => this.toggleFullscreen());
        document.getElementById('pipBtn').addEventListener('click', () => this.togglePictureInPicture());

        // Snapshot
        document.getElementById('snapshotBtn').addEventListener('click', () => this.captureSnapshot());

        // Download
        document.getElementById('downloadBtn').addEventListener('click', () => this.downloadVideo());

        // Progress bar
        document.getElementById('progressBar').addEventListener('change', (e) => this.seek(e.target.value));
        document.getElementById('progressBar').addEventListener('input', (e) => this.seek(e.target.value));

        // Actualizar display durante reproducción
        this.video.addEventListener('timeupdate', () => this.updateDisplay());
        this.video.addEventListener('loadedmetadata', () => this.updateDisplay());
        this.video.addEventListener('play', () => this.updatePlayPauseButton());
        this.video.addEventListener('pause', () => this.updatePlayPauseButton());

        // Zoom con rueda del mouse
        this.container.addEventListener('wheel', (e) => {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                e.deltaY > 0 ? this.zoomOut() : this.zoomIn();
            }
        });

        // Teclas de teclado
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
    }

    /**
     * Toggle play/pause
     */
    togglePlayPause() {
        if (this.video.paused) {
            this.video.play();
        } else {
            this.video.pause();
        }
    }

    /**
     * Skip time
     */
    skipTime(seconds) {
        this.video.currentTime = Math.max(0, Math.min(this.video.duration, this.video.currentTime + seconds));
    }

    /**
     * Zoom In
     */
    zoomIn() {
        this.setZoom(Math.min(this.maxZoom, this.currentZoom + this.zoomStep));
    }

    /**
     * Zoom Out
     */
    zoomOut() {
        this.setZoom(Math.max(this.minZoom, this.currentZoom - this.zoomStep));
    }

    /**
     * Set zoom level
     */
    setZoom(level) {
        this.currentZoom = level;
        this.video.style.transform = `scale(${level})`;
        this.video.style.transformOrigin = 'center center';
        
        // Mostrar indicador de zoom
        const zoomPercentage = Math.round(level * 100);
        document.getElementById('zoomPercentage').textContent = zoomPercentage;
        document.getElementById('zoomLevel').textContent = `${zoomPercentage}%`;
        
        // Mostrar/ocultar indicador
        const indicator = document.getElementById('zoomIndicator');
        if (level !== this.minZoom) {
            indicator.style.display = 'block';
        } else {
            indicator.style.display = 'none';
        }
    }

    /**
     * Set playback speed
     */
    setPlaybackSpeed(speed) {
        this.video.playbackRate = parseFloat(speed);
    }

    /**
     * Set volume
     */
    setVolume(value) {
        const volume = parseInt(value) / 100;
        this.video.volume = volume;
        document.getElementById('volumeLabel').textContent = value + '%';
    }

    /**
     * Toggle fullscreen
     */
    toggleFullscreen() {
        const elem = this.container;
        
        if (elem.requestFullscreen) {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                elem.requestFullscreen();
            }
        }
    }

    /**
     * Toggle Picture in Picture
     */
    async togglePictureInPicture() {
        try {
            if (document.pictureInPictureElement) {
                await document.exitPictureInPicture();
            } else if (document.pictureInPictureEnabled) {
                await this.video.requestPictureInPicture();
            }
        } catch (error) {
            console.error('Picture in Picture error:', error);
        }
    }

    /**
     * Capture screenshot
     */
    captureSnapshot() {
        const canvas = document.createElement('canvas');
        canvas.width = this.video.videoWidth;
        canvas.height = this.video.videoHeight;
        
        const ctx = canvas.getContext('2d');
        ctx.drawImage(this.video, 0, 0);
        
        // Descargar como imagen
        canvas.toBlob((blob) => {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `screenshot-${Date.now()}.png`;
            a.click();
            URL.revokeObjectURL(url);
        });
    }

    /**
     * Download video
     */
    downloadVideo() {
        const videoUrl = this.video.src;
        if (videoUrl) {
            const a = document.createElement('a');
            a.href = videoUrl;
            a.download = videoUrl.split('/').pop() || 'video.mp4';
            a.click();
        }
    }

    /**
     * Seek to time
     */
    seek(percentage) {
        const time = (percentage / 100) * this.video.duration;
        this.video.currentTime = time;
    }

    /**
     * Actualizar display de tiempo
     */
    updateDisplay() {
        const current = this.formatTime(this.video.currentTime);
        const duration = this.formatTime(this.video.duration);
        
        document.getElementById('currentTime').textContent = current;
        document.getElementById('duration').textContent = duration;
        
        const percentage = (this.video.currentTime / this.video.duration) * 100 || 0;
        document.getElementById('progressBar').value = percentage;
    }

    /**
     * Actualizar botón play/pause
     */
    updatePlayPauseButton() {
        const btn = document.getElementById('playPauseBtn');
        if (this.video.paused) {
            btn.innerHTML = '<i class="fas fa-play"></i>';
            btn.title = 'Reproducir';
        } else {
            btn.innerHTML = '<i class="fas fa-pause"></i>';
            btn.title = 'Pausar';
        }
    }

    /**
     * Actualizar controles
     */
    updateControlsDisplay() {
        this.updatePlayPauseButton();
        this.updateDisplay();
    }

    /**
     * Manejo de teclado
     */
    handleKeyboard(e) {
        // Solo si el foco está en el video o en los controles
        if (document.activeElement !== this.video && !this.controlsContainer.contains(document.activeElement)) {
            return;
        }

        switch(e.key) {
            case ' ':
                e.preventDefault();
                this.togglePlayPause();
                break;
            case 'ArrowRight':
                e.preventDefault();
                this.skipTime(this.skipAmount);
                break;
            case 'ArrowLeft':
                e.preventDefault();
                this.skipTime(-this.skipAmount);
                break;
            case '+':
            case '=':
                e.preventDefault();
                this.zoomIn();
                break;
            case '-':
                e.preventDefault();
                this.zoomOut();
                break;
            case 'f':
                this.toggleFullscreen();
                break;
            case 'p':
                this.togglePictureInPicture();
                break;
        }
    }

    /**
     * Format time (mm:ss)
     */
    formatTime(seconds) {
        if (isNaN(seconds)) return '00:00';
        
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);
        
        if (hours > 0) {
            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
        return `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
}

// Inicializar cuando DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    const videoElement = document.querySelector('video');
    if (videoElement) {
        new AdvancedVideoPlayer(videoElement.id || 'videoPlayer');
    }
});
