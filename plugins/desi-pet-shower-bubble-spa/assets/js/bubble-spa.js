/**
 * Bubble Spa Deluxe — Zuma-like game engine.
 *
 * Vanilla JS, zero dependencies, Canvas 2D, Web Audio API.
 * Auto-initialises on every `.dps-bubble-spa` container.
 *
 * @version 1.0.0
 */
(function () {
    'use strict';

    /* ======================================================================
       CONSTANTS
       ====================================================================== */
    var W = 480, H = 720;
    var BR = 14;                    // bubble radius
    var SPACING = BR * 2 + 2;      // spacing between chain bubbles
    var SHOOTER_Y = 670;            // cannon Y
    var SHOOTER_X = W / 2;          // cannon X
    var BULLET_SPEED = 9;           // fired bubble speed
    var TOTAL_LEVELS = 10;
    var SAVE_KEY = 'dps_bs_save';

    /* ======================================================================
       BUBBLE TYPES
       ====================================================================== */
    var BTYPES = [
        { id: 'foam',    color: '#90caf9', fill: '#e3f2fd', stroke: '#64b5f6', icon: '🫧', pat: 'circle'  },
        { id: 'perfume', color: '#ce93d8', fill: '#f3e5f5', stroke: '#ba68c8', icon: '💜', pat: 'ring'    },
        { id: 'shampoo', color: '#fff176', fill: '#fffde7', stroke: '#fdd835', icon: '🟡', pat: 'stripe'  },
        { id: 'aloe',    color: '#81c784', fill: '#e8f5e9', stroke: '#66bb6a', icon: '🟢', pat: 'diamond' },
        { id: 'mud',     color: '#a1887f', fill: '#efebe9', stroke: '#8d6e63', icon: '🟤', pat: 'cross'   }
    ];

    /* ======================================================================
       LEVEL DEFINITIONS
       ====================================================================== */
    var LEVELS = [
        { name: 'Banho Básico',    desc: 'Aqueça os motores!',           speed: 0.12, colors: 3, total: 30, spawn: 3.0, stars: [500, 1200, 2500] },
        { name: 'Espuma & Sabão',  desc: 'A espuma está subindo!',       speed: 0.14, colors: 3, total: 35, spawn: 2.8, stars: [800, 1800, 3500] },
        { name: 'Banho Completo',  desc: 'Mais cores, mais diversão!',   speed: 0.16, colors: 4, total: 38, spawn: 2.5, stars: [1000, 2200, 4500] },
        { name: 'Tosa Leve',       desc: 'Cuidado com os nós!',          speed: 0.18, colors: 4, total: 40, spawn: 2.2, stars: [1200, 2800, 5500] },
        { name: 'Hidratação',      desc: 'O tratamento ficou sério!',    speed: 0.20, colors: 4, total: 42, spawn: 2.0, stars: [1500, 3200, 6500] },
        { name: 'Banho Pesado',    desc: 'Velocidade máxima!',           speed: 0.22, colors: 4, total: 45, spawn: 1.8, stars: [2000, 4000, 8000] },
        { name: 'Lama & Limpeza',  desc: 'A lama chegou!',               speed: 0.24, colors: 5, total: 45, spawn: 1.6, stars: [2500, 5000, 9500] },
        { name: 'Tosa Perfeita',   desc: 'Bolhas travadas no caminho!',  speed: 0.26, colors: 5, total: 48, spawn: 1.5, stars: [3000, 6000, 11000] },
        { name: 'SPA Premium',     desc: 'Só os melhores passam!',       speed: 0.28, colors: 5, total: 50, spawn: 1.3, stars: [3500, 7000, 13000] },
        { name: 'Bubble Spa Deluxe', desc: 'O desafio final!',           speed: 0.30, colors: 5, total: 55, spawn: 1.2, stars: [4000, 8500, 16000] }
    ];

    /* ======================================================================
       POWER-UP DEFINITIONS
       ====================================================================== */
    var POWERUPS = {
        stop:  { icon: '🧴', name: 'Toalha',          dur: 4000 },
        bomb:  { icon: '💥', name: 'Banho de Espuma',  dur: 0    },
        brush: { icon: '🪥', name: 'Escova Mágica',    dur: 0    },
        blow:  { icon: '💨', name: 'Secador Turbo',    dur: 3000 }
    };

    /* ======================================================================
       PATH GENERATION  —  10 unique curved paths
       ====================================================================== */
    function lerp(a, b, t) { return a + (b - a) * t; }

    function catmullRom(p0, p1, p2, p3, t) {
        var t2 = t * t, t3 = t2 * t;
        return 0.5 * (
            (2 * p1) +
            (-p0 + p2) * t +
            (2 * p0 - 5 * p1 + 4 * p2 - p3) * t2 +
            (-p0 + 3 * p1 - 3 * p2 + p3) * t3
        );
    }

    function buildPathFromWaypoints(pts) {
        var path = [];
        for (var i = 0; i < pts.length - 1; i++) {
            var p0 = pts[Math.max(0, i - 1)];
            var p1 = pts[i];
            var p2 = pts[Math.min(pts.length - 1, i + 1)];
            var p3 = pts[Math.min(pts.length - 1, i + 2)];
            var steps = 60;
            for (var s = 0; s < steps; s++) {
                var t = s / steps;
                path.push({
                    x: catmullRom(p0.x, p1.x, p2.x, p3.x, t),
                    y: catmullRom(p0.y, p1.y, p2.y, p3.y, t)
                });
            }
        }
        // add last point
        path.push({ x: pts[pts.length - 1].x, y: pts[pts.length - 1].y });
        return resamplePath(path, 2);
    }

    function resamplePath(raw, step) {
        var out = [raw[0]];
        var acc = 0;
        for (var i = 1; i < raw.length; i++) {
            var dx = raw[i].x - raw[i - 1].x;
            var dy = raw[i].y - raw[i - 1].y;
            acc += Math.sqrt(dx * dx + dy * dy);
            while (acc >= step) {
                acc -= step;
                var t = 1 - acc / Math.sqrt(dx * dx + dy * dy + 0.0001);
                out.push({
                    x: lerp(raw[i - 1].x, raw[i].x, Math.max(0, Math.min(1, t))),
                    y: lerp(raw[i - 1].y, raw[i].y, Math.max(0, Math.min(1, t)))
                });
            }
        }
        return out;
    }

    function getPathWaypoints(level) {
        var m = 40; // margin
        switch (level) {
            case 0: // gentle S-curve
                return [
                    {x: m, y: m+40}, {x: W*0.75, y: 100}, {x: W*0.25, y: 200},
                    {x: W*0.8, y: 300}, {x: W*0.2, y: 400}, {x: W*0.7, y: 500},
                    {x: W/2, y: 600}
                ];
            case 1: // wider S
                return [
                    {x: m, y: m+30}, {x: W-m, y: 90}, {x: m, y: 170}, {x: W-m, y: 250},
                    {x: m, y: 340}, {x: W-m, y: 420}, {x: W/2, y: 520}, {x: W/2, y: 600}
                ];
            case 2: // spiral inward
                return [
                    {x: m, y: m+40}, {x: W-m, y: m+80}, {x: W-m, y: 280},
                    {x: m, y: 280}, {x: m, y: 420}, {x: W-m, y: 420},
                    {x: W*0.65, y: 520}, {x: W*0.35, y: 520}, {x: W/2, y: 590}
                ];
            case 3: // figure-8
                return [
                    {x: W/2, y: m+40}, {x: W-m, y: 120}, {x: W/2, y: 220},
                    {x: m, y: 320}, {x: W/2, y: 420}, {x: W-m, y: 320},
                    {x: W/2, y: 220}, {x: m, y: 120}, {x: m, y: 420},
                    {x: W/2, y: 560}, {x: W/2, y: 600}
                ];
            case 4: // zigzag
                return [
                    {x: m, y: m+40}, {x: W-m, y: 100}, {x: m, y: 160},
                    {x: W-m, y: 220}, {x: m, y: 280}, {x: W-m, y: 340},
                    {x: m, y: 400}, {x: W-m, y: 460}, {x: m, y: 520},
                    {x: W/2, y: 600}
                ];
            case 5: // double loop
                return [
                    {x: m, y: m+40}, {x: W*0.7, y: 80}, {x: W-m, y: 180},
                    {x: W*0.5, y: 260}, {x: m, y: 180}, {x: m, y: 320},
                    {x: W*0.5, y: 380}, {x: W-m, y: 460}, {x: W*0.5, y: 540},
                    {x: m, y: 460}, {x: W/2, y: 600}
                ];
            case 6: // serpentine long
                return [
                    {x: W-m, y: m+30}, {x: m, y: 80}, {x: W-m, y: 140},
                    {x: m, y: 200}, {x: W-m, y: 260}, {x: m, y: 320},
                    {x: W-m, y: 380}, {x: m, y: 440}, {x: W-m, y: 500},
                    {x: m, y: 560}, {x: W/2, y: 610}
                ];
            case 7: // looping
                return [
                    {x: m, y: m+40}, {x: W/2, y: 60}, {x: W-m, y: 140},
                    {x: W/2, y: 220}, {x: m, y: 140}, {x: W/4, y: 300},
                    {x: W*3/4, y: 360}, {x: W-m, y: 460}, {x: W/2, y: 540},
                    {x: m, y: 460}, {x: W/2, y: 600}
                ];
            case 8: // triple S
                return [
                    {x: m, y: m+30}, {x: W*0.8, y: 80}, {x: W*0.2, y: 150},
                    {x: W*0.8, y: 210}, {x: W*0.2, y: 280}, {x: W*0.8, y: 340},
                    {x: W*0.2, y: 400}, {x: W*0.8, y: 460}, {x: W*0.2, y: 530},
                    {x: W*0.7, y: 570}, {x: W/2, y: 610}
                ];
            case 9: // grand spiral
                return [
                    {x: W/2, y: m+30}, {x: W-m, y: m+80}, {x: W-m, y: 200},
                    {x: W/2, y: 260}, {x: m, y: 200}, {x: m, y: 100},
                    {x: m+80, y: 340}, {x: W-m-80, y: 340}, {x: W-m, y: 440},
                    {x: W/2, y: 500}, {x: m, y: 440}, {x: W/4, y: 560},
                    {x: W/2, y: 610}
                ];
            default:
                return getPathWaypoints(0);
        }
    }

    /* ======================================================================
       SOUND  —  Web Audio API procedural
       ====================================================================== */
    var audioCtx = null;
    function ensureAudio() {
        if (!audioCtx) {
            try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { /* silent */ }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
    }

    function tone(freq, dur, type, vol) {
        if (!audioCtx) return;
        try {
            var o = audioCtx.createOscillator();
            var g = audioCtx.createGain();
            o.type = type || 'square';
            o.frequency.value = freq;
            g.gain.value = vol || 0.06;
            g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + dur);
            o.connect(g); g.connect(audioCtx.destination);
            o.start(); o.stop(audioCtx.currentTime + dur);
        } catch (e) { /* silent */ }
    }

    function sfxShoot()   { tone(440, 0.08, 'square', 0.05); }
    function sfxPop()     { tone(300, 0.12, 'sine', 0.07); tone(180, 0.15, 'triangle', 0.04); }
    function sfxChain()   { tone(500, 0.15, 'sine', 0.06); tone(700, 0.2, 'sine', 0.05); }
    function sfxCombo()   { tone(400, 0.15, 'sine', 0.06); tone(550, 0.15, 'sine', 0.05); tone(700, 0.2, 'sine', 0.04); }
    function sfxPowerup() { tone(800, 0.1, 'sine', 0.06); tone(1100, 0.15, 'sine', 0.05); }
    function sfxSwap()    { tone(600, 0.05, 'square', 0.04); }
    function sfxGameover(){ tone(300, 0.3, 'sawtooth', 0.06); tone(200, 0.4, 'sawtooth', 0.05); }
    function sfxVictory() { tone(523, 0.15, 'sine', 0.06); setTimeout(function(){ tone(659, 0.15, 'sine', 0.06); }, 150); setTimeout(function(){ tone(784, 0.3, 'sine', 0.07); }, 300); }

    /* ======================================================================
       PARTICLES
       ====================================================================== */
    function createParticles(arr, x, y, color, count, spread) {
        for (var i = 0; i < count; i++) {
            var angle = Math.random() * Math.PI * 2;
            var speed = 1 + Math.random() * (spread || 3);
            arr.push({
                x: x, y: y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                life: 1, decay: 0.02 + Math.random() * 0.02,
                color: color,
                size: 2 + Math.random() * 3
            });
        }
    }

    function updateParticles(arr) {
        for (var i = arr.length - 1; i >= 0; i--) {
            var p = arr[i];
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.05; // gravity
            p.life -= p.decay;
            if (p.life <= 0) arr.splice(i, 1);
        }
    }

    function drawParticles(ctx, arr) {
        for (var i = 0; i < arr.length; i++) {
            var p = arr[i];
            ctx.globalAlpha = Math.max(0, p.life);
            ctx.fillStyle = p.color;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.size * p.life, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.globalAlpha = 1;
    }

    /* ======================================================================
       SAVE / LOAD
       ====================================================================== */
    function loadSave() {
        try {
            var d = JSON.parse(localStorage.getItem(SAVE_KEY));
            if (d && d.levels) return d;
        } catch (e) { /* ignore */ }
        return { highscore: 0, levels: {} };
    }

    function writeSave(save) {
        try { localStorage.setItem(SAVE_KEY, JSON.stringify(save)); } catch (e) { /* ignore */ }
    }

    /* ======================================================================
       MAIN GAME CLASS
       ====================================================================== */
    function BubbleSpa(container) {
        this.ct = container;
        this.canvas = container.querySelector('.dps-bs-canvas');
        this.ctx = this.canvas.getContext('2d');

        // DOM refs
        this.elScore = container.querySelector('.dps-bs-score');
        this.elLevel = container.querySelector('.dps-bs-level');
        this.elStars = container.querySelectorAll('.dps-bs-star');
        this.elProgress = container.querySelector('.dps-bs-progress__fill');
        this.elCombo = container.querySelector('.dps-bs-combo');
        this.elComboText = container.querySelector('.dps-bs-combo__text');
        this.elPowerInd = container.querySelector('.dps-bs-powerup-ind');
        this.elPowerIcon = container.querySelector('.dps-bs-powerup-ind__icon');
        this.elPowerName = container.querySelector('.dps-bs-powerup-ind__name');
        this.elSwap = container.querySelector('.dps-bs-swap');
        this.elSwapCanvas = container.querySelector('.dps-bs-swap__next');
        this.swapCtx = this.elSwapCanvas.getContext('2d');
        this.elPauseBtn = container.querySelector('.dps-bs-pause-btn');
        this.elHsVal = container.querySelectorAll('.dps-bs-hs-val');

        // overlays
        this.ovStart   = container.querySelector('.dps-bs-overlay--start');
        this.ovLevels  = container.querySelector('.dps-bs-overlay--levels');
        this.ovIntro   = container.querySelector('.dps-bs-overlay--intro');
        this.ovGameover= container.querySelector('.dps-bs-overlay--gameover');
        this.ovVictory = container.querySelector('.dps-bs-overlay--victory');
        this.ovPause   = container.querySelector('.dps-bs-overlay--pause');

        this.state = 'menu'; // menu | levelSelect | levelIntro | playing | paused | victory | gameover
        this.save = loadSave();
        this.rafId = null;
        this.lastTime = 0;

        this.updateHighscoreDisplay();
        this.bindEvents();
    }

    /* ------------------------------------------------------------------
       EVENTS
       ------------------------------------------------------------------ */
    BubbleSpa.prototype.bindEvents = function () {
        var self = this;

        // Play button (start → level select)
        self.ct.querySelector('.dps-bs-btn--play').addEventListener('click', function () {
            ensureAudio();
            self.showLevelSelect();
        });

        // Back from level select
        self.ct.querySelector('.dps-bs-btn--back').addEventListener('click', function () {
            self.hideAll(); self.show(self.ovStart); self.state = 'menu';
        });

        // Level intro → start
        self.ct.querySelector('.dps-bs-btn--go').addEventListener('click', function () {
            self.startLevel(self.pendingLevel);
        });

        // Retry
        self.ct.querySelector('.dps-bs-btn--retry').addEventListener('click', function () {
            self.startLevel(self.currentLevel);
        });

        // Next level
        self.ct.querySelector('.dps-bs-btn--next').addEventListener('click', function () {
            var next = self.currentLevel + 1;
            if (next < TOTAL_LEVELS) {
                self.showLevelIntro(next);
            } else {
                self.showLevelSelect();
            }
        });

        // Menu buttons
        var menuBtns = self.ct.querySelectorAll('.dps-bs-btn--menu');
        for (var i = 0; i < menuBtns.length; i++) {
            menuBtns[i].addEventListener('click', function () {
                self.stopLoop();
                self.showLevelSelect();
            });
        }

        // Resume
        self.ct.querySelector('.dps-bs-btn--resume').addEventListener('click', function () {
            self.resume();
        });

        // Pause
        self.elPauseBtn.addEventListener('click', function () {
            if (self.state === 'playing') self.pause();
        });

        // Swap bubble
        self.elSwap.addEventListener('click', function () {
            if (self.state === 'playing') self.swapBubble();
        });

        // Canvas pointer events
        var onDown = function (e) {
            if (self.state !== 'playing') return;
            ensureAudio();
            e.preventDefault();
            var pos = self.getPointerPos(e);
            self.aimAt(pos.x, pos.y);
            self.fire();
        };

        var onMove = function (e) {
            if (self.state !== 'playing') return;
            e.preventDefault();
            var pos = self.getPointerPos(e);
            self.aimAt(pos.x, pos.y);
        };

        self.canvas.addEventListener('pointerdown', onDown);
        self.canvas.addEventListener('pointermove', onMove);

        // Keyboard
        self.ct.addEventListener('keydown', function (e) {
            if (self.state === 'playing') {
                if (e.key === 's' || e.key === 'S') { self.swapBubble(); e.preventDefault(); }
                if (e.key === 'Escape' || e.key === 'p' || e.key === 'P') { self.pause(); e.preventDefault(); }
            } else if (self.state === 'paused') {
                if (e.key === 'Escape' || e.key === 'p' || e.key === 'P') { self.resume(); e.preventDefault(); }
            }
        });

        // Make container focusable for keyboard
        self.ct.setAttribute('tabindex', '0');
    };

    BubbleSpa.prototype.getPointerPos = function (e) {
        var rect = this.canvas.getBoundingClientRect();
        var scaleX = W / rect.width;
        var scaleY = H / rect.height;
        var cx = (e.clientX - rect.left) * scaleX;
        var cy = (e.clientY - rect.top) * scaleY;
        return { x: cx, y: cy };
    };

    /* ------------------------------------------------------------------
       OVERLAY HELPERS
       ------------------------------------------------------------------ */
    BubbleSpa.prototype.hideAll = function () {
        var ovs = this.ct.querySelectorAll('.dps-bs-overlay');
        for (var i = 0; i < ovs.length; i++) ovs[i].classList.add('dps-bs-overlay--hidden');
        this.elPauseBtn.classList.add('dps-bs-pause-btn--hidden');
    };

    BubbleSpa.prototype.show = function (el) {
        el.classList.remove('dps-bs-overlay--hidden');
    };

    /* ------------------------------------------------------------------
       LEVEL SELECT
       ------------------------------------------------------------------ */
    BubbleSpa.prototype.showLevelSelect = function () {
        var self = this;
        this.stopLoop();
        this.hideAll();
        this.state = 'levelSelect';

        var grid = this.ct.querySelector('.dps-bs-level-grid');
        grid.innerHTML = '';

        for (var i = 0; i < TOTAL_LEVELS; i++) {
            var info = this.save.levels[i];
            var unlocked = i === 0 || (this.save.levels[i - 1] && this.save.levels[i - 1].stars > 0);
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dps-bs-level-btn' + (unlocked ? '' : ' dps-bs-level-btn--locked');
            btn.innerHTML = '<span>' + (i + 1) + '</span>';
            if (info && info.stars > 0) {
                var s = '';
                for (var j = 0; j < 3; j++) s += j < info.stars ? '★' : '☆';
                btn.innerHTML += '<span class="dps-bs-level-btn__stars">' + s + '</span>';
            }
            if (unlocked) {
                (function (idx) {
                    btn.addEventListener('click', function () {
                        self.showLevelIntro(idx);
                    });
                })(i);
            }
            grid.appendChild(btn);
        }

        var self = this;
        this.show(this.ovLevels);
    };

    BubbleSpa.prototype.showLevelIntro = function (idx) {
        this.hideAll();
        this.state = 'levelIntro';
        this.pendingLevel = idx;
        var def = LEVELS[idx];
        this.ct.querySelector('.dps-bs-intro-title').textContent = 'Fase ' + (idx + 1) + ': ' + def.name;
        this.ct.querySelector('.dps-bs-intro-desc').textContent = def.desc;
        this.show(this.ovIntro);
    };

    /* ------------------------------------------------------------------
       START / STOP
       ------------------------------------------------------------------ */
    BubbleSpa.prototype.startLevel = function (idx) {
        this.hideAll();
        this.state = 'playing';
        this.currentLevel = idx;
        this.elPauseBtn.classList.remove('dps-bs-pause-btn--hidden');

        var def = LEVELS[idx];
        this.path = buildPathFromWaypoints(getPathWaypoints(idx));
        this.chainSpeed = def.speed;
        this.numColors = def.colors;
        this.totalToSpawn = def.total;
        this.spawnInterval = def.spawn;
        this.starThresholds = def.stars;

        // game state
        this.chain = [];
        this.bullet = null;  // flying bubble
        this.particles = [];
        this.score = 0;
        this.combo = 0;
        this.totalSpawned = 0;
        this.totalPopped = 0;
        this.shotsFired = 0;
        this.shotsHit = 0;
        this.spawnTimer = 0;
        this.headIndex = 0; // path index of chain head (first bubble spawn point)
        this.aimAngle = -Math.PI / 2;
        this.comboTimer = 0;
        this.activePower = null;
        this.powerTimer = 0;
        this.chainFrozen = false;
        this.chainReversed = false;
        this.matchAnimating = false;
        this.matchAnimTimer = 0;
        this.removingBubbles = [];

        // shooter bubbles
        this.currentBubble = this.randomBubbleType();
        this.nextBubble = this.randomBubbleType();

        // spawn initial chain
        this.spawnInitialChain(12 + idx * 2);

        // update HUD
        this.elLevel.textContent = idx + 1;
        this.elScore.textContent = '0';
        this.updateStarsHUD(0);
        this.drawSwapBubble();

        this.ct.focus();
        this.startLoop();
    };

    BubbleSpa.prototype.spawnInitialChain = function (count) {
        var spacing = Math.round(SPACING / 2);
        var headStart = count * spacing;
        if (headStart >= this.path.length) headStart = this.path.length - spacing;
        for (var i = 0; i < count; i++) {
            var pIdx = headStart - i * spacing;
            if (pIdx < 0) pIdx = 0;
            var pi = Math.min(Math.round(pIdx), this.path.length - 1);
            this.chain.push({
                type: this.randomBubbleType(),
                pathIdx: pIdx,
                x: this.path[pi].x,
                y: this.path[pi].y,
                special: null,
                popping: false
            });
        }
        this.totalSpawned = this.chain.length;
    };

    BubbleSpa.prototype.randomBubbleType = function () {
        return Math.floor(Math.random() * this.numColors);
    };

    BubbleSpa.prototype.pause = function () {
        if (this.state !== 'playing') return;
        this.state = 'paused';
        this.show(this.ovPause);
    };

    BubbleSpa.prototype.resume = function () {
        if (this.state !== 'paused') return;
        this.hideAll();
        this.state = 'playing';
        this.elPauseBtn.classList.remove('dps-bs-pause-btn--hidden');
        this.lastTime = performance.now();
    };

    /* ------------------------------------------------------------------
       GAME LOOP
       ------------------------------------------------------------------ */
    BubbleSpa.prototype.startLoop = function () {
        var self = this;
        self.lastTime = performance.now();
        function tick(now) {
            var dt = Math.min((now - self.lastTime) / 1000, 0.05); // cap at 50ms
            self.lastTime = now;
            if (self.state === 'playing') {
                self.update(dt);
            }
            self.render();
            self.rafId = requestAnimationFrame(tick);
        }
        self.rafId = requestAnimationFrame(tick);
    };

    BubbleSpa.prototype.stopLoop = function () {
        if (this.rafId) {
            cancelAnimationFrame(this.rafId);
            this.rafId = null;
        }
    };

    /* ------------------------------------------------------------------
       UPDATE
       ------------------------------------------------------------------ */
    BubbleSpa.prototype.update = function (dt) {
        // combo timer
        if (this.comboTimer > 0) {
            this.comboTimer -= dt;
            if (this.comboTimer <= 0) {
                this.elCombo.classList.add('dps-bs-combo--hidden');
                this.combo = 0;
            }
        }

        // power-up timer
        if (this.activePower) {
            this.powerTimer -= dt * 1000;
            if (this.powerTimer <= 0) {
                this.deactivatePower();
            }
        }

        // match animation
        if (this.matchAnimating) {
            this.matchAnimTimer -= dt;
            if (this.matchAnimTimer <= 0) {
                this.finishMatchAnim();
            }
            updateParticles(this.particles);
            return; // pause chain/spawn during anim
        }

        // advance chain (dt-based for consistent speed)
        if (!this.chainFrozen) {
            var speed = this.chainReversed ? -this.chainSpeed * 2 : this.chainSpeed;
            this.advanceChain(speed * dt * 60);
        }

        // spawn new bubbles
        if (this.totalSpawned < this.totalToSpawn) {
            this.spawnTimer -= dt;
            if (this.spawnTimer <= 0) {
                this.spawnTimer = this.spawnInterval;
                this.spawnBubble();
            }
        }

        // update bullet (dt-based)
        if (this.bullet) {
            var bdt = dt * 60;
            this.bullet.x += this.bullet.vx * bdt;
            this.bullet.y += this.bullet.vy * bdt;

            // boundary check
            if (this.bullet.x < -BR || this.bullet.x > W + BR || this.bullet.y < -BR) {
                this.bullet = null;
            } else {
                // check collision with chain
                this.checkBulletCollision();
            }
        }

        // check win/lose
        this.checkGameEnd();

        // update particles
        updateParticles(this.particles);

        // update progress bar
        this.updateProgress();
    };

    /* ------------------------------------------------------------------
       CHAIN MOVEMENT
       ------------------------------------------------------------------ */
    BubbleSpa.prototype.advanceChain = function (speed) {
        if (this.chain.length === 0) return;

        // move head bubble along path
        this.chain[0].pathIdx += speed;
        if (this.chain[0].pathIdx < 0) this.chain[0].pathIdx = 0;
        this.chain[0].pathIdx = Math.min(this.chain[0].pathIdx, this.path.length - 1);

        // position head
        var hi = Math.round(this.chain[0].pathIdx);
        hi = Math.max(0, Math.min(hi, this.path.length - 1));
        this.chain[0].x = this.path[hi].x;
        this.chain[0].y = this.path[hi].y;

        // position rest following head with proper spacing
        var spacing = Math.round(SPACING / 2);
        for (var i = 1; i < this.chain.length; i++) {
            var targetIdx = this.chain[i - 1].pathIdx - spacing;
            if (targetIdx < 0) targetIdx = 0;
            // Smoothly close gaps but don't push bubbles backward
            if (this.chain[i].pathIdx < targetIdx) {
                this.chain[i].pathIdx += Math.min(Math.abs(speed) * 3, targetIdx - this.chain[i].pathIdx);
            } else {
                this.chain[i].pathIdx = targetIdx;
            }
            var pi = Math.round(this.chain[i].pathIdx);
            pi = Math.max(0, Math.min(pi, this.path.length - 1));
            this.chain[i].x = this.path[pi].x;
            this.chain[i].y = this.path[pi].y;
        }
    };

    BubbleSpa.prototype.spawnBubble = function () {
        if (this.totalSpawned >= this.totalToSpawn) return;

        var type = this.randomBubbleType();
        // special bubbles in later levels
        var special = null;
        if (this.currentLevel >= 5 && Math.random() < 0.08) {
            special = 'gift'; // brinde
        }

        // insert at tail (beginning of path)
        var tailIdx = 0;
        if (this.chain.length > 0) {
            tailIdx = this.chain[this.chain.length - 1].pathIdx - Math.round(SPACING / 2);
            if (tailIdx < 0) tailIdx = 0;
        }

        this.chain.push({
            type: type,
            pathIdx: tailIdx,
            x: this.path[Math.max(0, Math.round(tailIdx))].x,
            y: this.path[Math.max(0, Math.round(tailIdx))].y,
            special: special,
            popping: false
        });
        this.totalSpawned++;
    };

    /* ------------------------------------------------------------------
       AIMING & FIRING
       ------------------------------------------------------------------ */
    BubbleSpa.prototype.aimAt = function (px, py) {
        var dx = px - SHOOTER_X;
        var dy = py - SHOOTER_Y;
        this.aimAngle = Math.atan2(dy, dx);
        // clamp to upper half
        if (this.aimAngle > -0.15) this.aimAngle = -0.15;
        if (this.aimAngle < -Math.PI + 0.15) this.aimAngle = -Math.PI + 0.15;
    };

    BubbleSpa.prototype.fire = function () {
        if (this.bullet) return; // one at a time
        sfxShoot();
        this.shotsFired++;
        this.bullet = {
            type: this.currentBubble,
            x: SHOOTER_X,
            y: SHOOTER_Y - 20,
            vx: Math.cos(this.aimAngle) * BULLET_SPEED,
            vy: Math.sin(this.aimAngle) * BULLET_SPEED
        };
        this.currentBubble = this.nextBubble;
        this.nextBubble = this.randomBubbleType();
        this.drawSwapBubble();
    };

    BubbleSpa.prototype.swapBubble = function () {
        sfxSwap();
        var tmp = this.currentBubble;
        this.currentBubble = this.nextBubble;
        this.nextBubble = tmp;
        this.drawSwapBubble();
    };

    /* ------------------------------------------------------------------
       COLLISION & MATCHING
       ------------------------------------------------------------------ */
    BubbleSpa.prototype.checkBulletCollision = function () {
        if (!this.bullet || this.chain.length === 0) return;

        var bx = this.bullet.x, by = this.bullet.y;
        var hitDist = BR * 2.2;
        var bestIdx = -1, bestDist = Infinity;

        for (var i = 0; i < this.chain.length; i++) {
            var c = this.chain[i];
            var dx = bx - c.x, dy = by - c.y;
            var d = Math.sqrt(dx * dx + dy * dy);
            if (d < hitDist && d < bestDist) {
                bestDist = d;
                bestIdx = i;
            }
        }

        if (bestIdx >= 0) {
            this.shotsHit++;
            this.insertBubbleAt(bestIdx);
            this.bullet = null;
        }
    };

    BubbleSpa.prototype.insertBubbleAt = function (hitIdx) {
        var hit = this.chain[hitIdx];
        var bx = this.bullet.x, by = this.bullet.y;

        // Determine insert position: before or after hit
        var insertIdx;
        if (hitIdx === 0) {
            // Check if bullet is closer to the head side
            insertIdx = 0;
        } else {
            var prev = this.chain[hitIdx - 1];
            var dPrev = Math.sqrt((bx - prev.x) * (bx - prev.x) + (by - prev.y) * (by - prev.y));
            var dHit  = Math.sqrt((bx - hit.x) * (bx - hit.x) + (by - hit.y) * (by - hit.y));

            // insert after hitIdx if closer to head side, before if closer to tail
            if (hit.pathIdx > (prev ? prev.pathIdx : 0)) {
                // normal order: chain[0] = head (highest pathIdx)
                insertIdx = dPrev < dHit ? hitIdx : hitIdx + 1;
            } else {
                insertIdx = hitIdx;
            }
        }

        // Create new bubble at insertion point
        var newPathIdx;
        if (insertIdx < this.chain.length) {
            newPathIdx = this.chain[insertIdx].pathIdx;
        } else if (this.chain.length > 0) {
            newPathIdx = this.chain[this.chain.length - 1].pathIdx - Math.round(SPACING / 2);
        } else {
            newPathIdx = 0;
        }

        var np = Math.max(0, Math.min(Math.round(newPathIdx), this.path.length - 1));
        var newBubble = {
            type: this.bullet.type,
            pathIdx: newPathIdx,
            x: this.path[np].x,
            y: this.path[np].y,
            special: null,
            popping: false
        };

        this.chain.splice(insertIdx, 0, newBubble);

        // Push chain to make room
        for (var i = insertIdx + 1; i < this.chain.length; i++) {
            this.chain[i].pathIdx -= Math.round(SPACING / 2);
            if (this.chain[i].pathIdx < 0) this.chain[i].pathIdx = 0;
            var pi = Math.max(0, Math.min(Math.round(this.chain[i].pathIdx), this.path.length - 1));
            this.chain[i].x = this.path[pi].x;
            this.chain[i].y = this.path[pi].y;
        }

        // Check for matches
        this.checkMatches(insertIdx);
    };

    BubbleSpa.prototype.checkMatches = function (fromIdx) {
        if (this.chain.length === 0) return;
        fromIdx = Math.max(0, Math.min(fromIdx, this.chain.length - 1));

        var type = this.chain[fromIdx].type;
        var left = fromIdx, right = fromIdx;

        while (left > 0 && this.chain[left - 1].type === type && !this.chain[left - 1].popping) left--;
        while (right < this.chain.length - 1 && this.chain[right + 1].type === type && !this.chain[right + 1].popping) right++;

        var count = right - left + 1;
        if (count >= 3) {
            this.combo++;
            var basePoints;
            if (count === 3) basePoints = 100;
            else if (count === 4) basePoints = 250;
            else basePoints = 500;

            var multiplier = Math.min(this.combo, 8);
            var points = basePoints * multiplier;

            // gift bonus
            for (var g = left; g <= right; g++) {
                if (this.chain[g].special === 'gift') {
                    points += 300;
                    // chance for power-up
                    if (Math.random() < 0.4) {
                        this.activateRandomPower();
                    }
                }
            }

            this.score += points;
            this.elScore.textContent = this.score;
            this.updateStarsHUD(this.score);

            // Show combo
            this.showCombo(count, multiplier);

            if (this.combo >= 2) sfxChain();
            else sfxPop();

            // Mark for removal animation
            this.removingBubbles = [];
            for (var r = left; r <= right; r++) {
                this.chain[r].popping = true;
                this.removingBubbles.push(r);
                createParticles(this.particles, this.chain[r].x, this.chain[r].y, BTYPES[this.chain[r].type].color, 6, 3);
            }

            this.totalPopped += count;
            this.matchAnimating = true;
            this.matchAnimTimer = 0.25;
            this.pendingMatchLeft = left;
            this.pendingMatchRight = right;
        }
    };

    BubbleSpa.prototype.finishMatchAnim = function () {
        this.matchAnimating = false;

        // Remove popping bubbles
        var newChain = [];
        for (var i = 0; i < this.chain.length; i++) {
            if (!this.chain[i].popping) newChain.push(this.chain[i]);
        }
        this.chain = newChain;

        // Close gap - pull head part toward tail
        if (this.chain.length > 1) {
            // Recalculate positions ensuring proper spacing
            for (var j = 1; j < this.chain.length; j++) {
                var target = this.chain[j - 1].pathIdx - Math.round(SPACING / 2);
                if (target < 0) target = 0;
                if (this.chain[j].pathIdx > target) {
                    this.chain[j].pathIdx = target;
                }
                var pi = Math.max(0, Math.min(Math.round(this.chain[j].pathIdx), this.path.length - 1));
                this.chain[j].x = this.path[pi].x;
                this.chain[j].y = this.path[pi].y;
            }
        }

        // Check for chain reactions at the gap closure point
        if (this.chain.length >= 3) {
            // Find where the gap was and check for new matches
            var checkIdx = Math.min(this.pendingMatchLeft, this.chain.length - 1);
            if (checkIdx >= 0 && checkIdx < this.chain.length) {
                var type = this.chain[checkIdx].type;
                var left = checkIdx, right = checkIdx;
                while (left > 0 && this.chain[left - 1].type === type) left--;
                while (right < this.chain.length - 1 && this.chain[right + 1].type === type) right++;
                if (right - left + 1 >= 3) {
                    sfxCombo();
                    this.checkMatches(checkIdx);
                    return;
                }
            }
        }

        this.combo = 0;

        // Check for power-up drop (5% base chance on any match)
        if (Math.random() < 0.05 + (this.combo > 2 ? 0.05 : 0)) {
            this.activateRandomPower();
        }
    };

    /* ------------------------------------------------------------------
       POWER-UPS
       ------------------------------------------------------------------ */
    BubbleSpa.prototype.activateRandomPower = function () {
        var keys = Object.keys(POWERUPS);
        var key = keys[Math.floor(Math.random() * keys.length)];
        this.activatePower(key);
    };

    BubbleSpa.prototype.activatePower = function (key) {
        sfxPowerup();
        var pw = POWERUPS[key];
        this.elPowerIcon.textContent = pw.icon;
        this.elPowerName.textContent = pw.name;
        this.elPowerInd.classList.remove('dps-bs-powerup-ind--hidden');

        switch (key) {
            case 'stop':
                this.activePower = 'stop';
                this.powerTimer = pw.dur;
                this.chainFrozen = true;
                break;
            case 'bomb':
                this.doBomb();
                this.activePower = null;
                setTimeout(function () { this.elPowerInd.classList.add('dps-bs-powerup-ind--hidden'); }.bind(this), 1500);
                break;
            case 'brush':
                this.doBrush();
                this.activePower = null;
                setTimeout(function () { this.elPowerInd.classList.add('dps-bs-powerup-ind--hidden'); }.bind(this), 1500);
                break;
            case 'blow':
                this.activePower = 'blow';
                this.powerTimer = pw.dur;
                this.chainReversed = true;
                break;
        }
    };

    BubbleSpa.prototype.deactivatePower = function () {
        this.chainFrozen = false;
        this.chainReversed = false;
        this.activePower = null;
        this.elPowerInd.classList.add('dps-bs-powerup-ind--hidden');
    };

    BubbleSpa.prototype.doBomb = function () {
        // Explode bubbles near the head
        if (this.chain.length === 0) return;
        var cx = this.chain[0].x, cy = this.chain[0].y;
        var radius = BR * 6;
        var removed = 0;
        for (var i = this.chain.length - 1; i >= 0; i--) {
            var dx = this.chain[i].x - cx, dy = this.chain[i].y - cy;
            if (Math.sqrt(dx * dx + dy * dy) < radius) {
                createParticles(this.particles, this.chain[i].x, this.chain[i].y, BTYPES[this.chain[i].type].color, 8, 4);
                this.chain.splice(i, 1);
                removed++;
            }
        }
        this.totalPopped += removed;
        this.score += removed * 30;
        this.elScore.textContent = this.score;
        sfxPop();
    };

    BubbleSpa.prototype.doBrush = function () {
        // Remove all bubbles of the most common type
        if (this.chain.length === 0) return;
        var counts = {};
        for (var i = 0; i < this.chain.length; i++) {
            counts[this.chain[i].type] = (counts[this.chain[i].type] || 0) + 1;
        }
        var maxType = 0, maxCount = 0;
        for (var t in counts) {
            if (counts[t] > maxCount) { maxCount = counts[t]; maxType = parseInt(t); }
        }
        var removed = 0;
        for (var j = this.chain.length - 1; j >= 0; j--) {
            if (this.chain[j].type === maxType) {
                createParticles(this.particles, this.chain[j].x, this.chain[j].y, BTYPES[this.chain[j].type].color, 5, 3);
                this.chain.splice(j, 1);
                removed++;
            }
        }
        this.totalPopped += removed;
        this.score += removed * 25;
        this.elScore.textContent = this.score;
        sfxPop();
    };

    /* ------------------------------------------------------------------
       COMBO DISPLAY
       ------------------------------------------------------------------ */
    BubbleSpa.prototype.showCombo = function (count, multi) {
        var text = '';
        if (multi > 1) text = 'Chain x' + multi + '!';
        else if (count >= 5) text = 'Incrível!';
        else if (count >= 4) text = 'Ótimo!';
        else text = 'Nice!';

        this.elComboText.textContent = text;
        this.elCombo.classList.remove('dps-bs-combo--hidden');
        this.comboTimer = 1.2;

        // Re-trigger animation
        this.elComboText.style.animation = 'none';
        void this.elComboText.offsetHeight;
        this.elComboText.style.animation = '';
    };

    /* ------------------------------------------------------------------
       GAME END
       ------------------------------------------------------------------ */
    BubbleSpa.prototype.checkGameEnd = function () {
        // Lose: head reached end of path
        if (this.chain.length > 0 && this.chain[0].pathIdx >= this.path.length - 5) {
            this.gameOver();
            return;
        }

        // Win: all bubbles spawned and chain empty
        if (this.totalSpawned >= this.totalToSpawn && this.chain.length === 0 && !this.bullet) {
            this.victory();
        }
    };

    BubbleSpa.prototype.gameOver = function () {
        this.state = 'gameover';
        sfxGameover();
        this.hideAll();

        var finalEl = this.ovGameover.querySelector('.dps-bs-final-score');
        if (finalEl) finalEl.textContent = this.score;

        this.show(this.ovGameover);
        this.saveProgress(0);
    };

    BubbleSpa.prototype.victory = function () {
        this.state = 'victory';
        sfxVictory();
        this.hideAll();

        // Calculate bonuses
        var accuracy = this.shotsFired > 0 ? this.shotsHit / this.shotsFired : 1;
        var accBonus = Math.round(accuracy * 500);
        this.score += accBonus;

        // Stars
        var stars = 0;
        for (var s = 0; s < 3; s++) {
            if (this.score >= this.starThresholds[s]) stars = s + 1;
        }

        // Show stars
        var starsDiv = this.ovVictory.querySelector('.dps-bs-victory-stars');
        starsDiv.innerHTML = '';
        for (var i = 0; i < 3; i++) {
            var span = document.createElement('span');
            span.textContent = i < stars ? '⭐' : '☆';
            starsDiv.appendChild(span);
        }

        // Show bonuses
        var bonusDiv = this.ovVictory.querySelector('.dps-bs-victory-bonuses');
        bonusDiv.innerHTML = 'Precisão: ' + Math.round(accuracy * 100) + '% (+' + accBonus + ')';

        var finalEl = this.ovVictory.querySelector('.dps-bs-final-score');
        if (finalEl) finalEl.textContent = this.score;

        this.show(this.ovVictory);
        this.saveProgress(stars);

        // Confetti
        for (var c = 0; c < 40; c++) {
            var colors = ['#ffd54f', '#4fc3f7', '#81c784', '#f48fb1', '#ffab91'];
            createParticles(this.particles, W / 2 + (Math.random() - 0.5) * W, -10, colors[c % colors.length], 1, 4);
        }
    };

    BubbleSpa.prototype.saveProgress = function (stars) {
        if (!this.save.levels[this.currentLevel] || this.save.levels[this.currentLevel].stars < stars) {
            this.save.levels[this.currentLevel] = { stars: stars, score: this.score };
        }
        if (this.score > this.save.highscore) this.save.highscore = this.score;
        writeSave(this.save);
        this.updateHighscoreDisplay();
    };

    BubbleSpa.prototype.updateHighscoreDisplay = function () {
        for (var i = 0; i < this.elHsVal.length; i++) {
            this.elHsVal[i].textContent = this.save.highscore;
        }
    };

    /* ------------------------------------------------------------------
       HUD UPDATES
       ------------------------------------------------------------------ */
    BubbleSpa.prototype.updateStarsHUD = function (score) {
        for (var i = 0; i < this.elStars.length; i++) {
            var threshold = this.starThresholds ? this.starThresholds[i] : Infinity;
            if (score >= threshold) {
                this.elStars[i].textContent = '★';
                this.elStars[i].classList.add('dps-bs-star--active');
            } else {
                this.elStars[i].textContent = '☆';
                this.elStars[i].classList.remove('dps-bs-star--active');
            }
        }
    };

    BubbleSpa.prototype.updateProgress = function () {
        if (this.chain.length === 0) {
            this.elProgress.style.width = '0%';
            return;
        }
        var pct = (this.chain[0].pathIdx / this.path.length) * 100;
        this.elProgress.style.width = Math.min(100, pct).toFixed(1) + '%';
    };

    BubbleSpa.prototype.drawSwapBubble = function () {
        var ctx = this.swapCtx;
        ctx.clearRect(0, 0, 40, 40);
        var bt = BTYPES[this.nextBubble];
        ctx.beginPath();
        ctx.arc(20, 20, 14, 0, Math.PI * 2);
        ctx.fillStyle = bt.fill;
        ctx.fill();
        ctx.strokeStyle = bt.stroke;
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.fillStyle = bt.color;
        ctx.font = '12px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(bt.icon, 20, 20);
    };

    /* ------------------------------------------------------------------
       RENDER
       ------------------------------------------------------------------ */
    BubbleSpa.prototype.render = function () {
        var ctx = this.ctx;
        ctx.clearRect(0, 0, W, H);

        // Background gradient
        var bg = ctx.createLinearGradient(0, 0, 0, H);
        bg.addColorStop(0, '#e0f7fa');
        bg.addColorStop(0.3, '#b2ebf2');
        bg.addColorStop(0.6, '#80deea');
        bg.addColorStop(1, '#4dd0e1');
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, W, H);

        // Draw path (subtle dotted line)
        this.drawPath(ctx);

        // Draw drain at path end
        this.drawDrain(ctx);

        // Draw chain bubbles
        for (var i = this.chain.length - 1; i >= 0; i--) {
            this.drawBubble(ctx, this.chain[i].x, this.chain[i].y, this.chain[i].type, this.chain[i].special, this.chain[i].popping);
        }

        // Draw bullet
        if (this.bullet) {
            this.drawBubble(ctx, this.bullet.x, this.bullet.y, this.bullet.type, null, false);
        }

        // Draw shooter
        this.drawShooter(ctx);

        // Draw aim line
        if (this.state === 'playing') {
            this.drawAimLine(ctx);
        }

        // Draw particles
        drawParticles(ctx, this.particles);
    };

    BubbleSpa.prototype.drawPath = function (ctx) {
        ctx.save();
        ctx.strokeStyle = 'rgba(0, 77, 64, 0.15)';
        ctx.lineWidth = BR * 2 + 4;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();
        ctx.moveTo(this.path[0].x, this.path[0].y);
        for (var i = 1; i < this.path.length; i += 3) {
            ctx.lineTo(this.path[i].x, this.path[i].y);
        }
        ctx.stroke();
        ctx.restore();
    };

    BubbleSpa.prototype.drawDrain = function (ctx) {
        var end = this.path[this.path.length - 1];
        ctx.save();

        // Drain circle
        ctx.beginPath();
        ctx.arc(end.x, end.y, 22, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(0, 0, 0, 0.3)';
        ctx.fill();

        // Spiral pattern
        ctx.strokeStyle = 'rgba(0, 0, 0, 0.4)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        for (var a = 0; a < Math.PI * 4; a += 0.2) {
            var r = a * 2;
            var px = end.x + Math.cos(a) * r;
            var py = end.y + Math.sin(a) * r;
            if (a === 0) ctx.moveTo(px, py);
            else ctx.lineTo(px, py);
        }
        ctx.stroke();

        // Label
        ctx.fillStyle = 'rgba(255,255,255,0.7)';
        ctx.font = '10px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Ralo', end.x, end.y + 32);
        ctx.restore();
    };

    BubbleSpa.prototype.drawBubble = function (ctx, x, y, typeIdx, special, popping) {
        var bt = BTYPES[typeIdx];
        var r = BR;
        if (popping) r *= 1.3;

        ctx.save();
        if (popping) ctx.globalAlpha = 0.6;

        // Main circle with gradient
        var grad = ctx.createRadialGradient(x - r * 0.3, y - r * 0.3, r * 0.1, x, y, r);
        grad.addColorStop(0, '#fff');
        grad.addColorStop(0.4, bt.fill);
        grad.addColorStop(1, bt.color);
        ctx.beginPath();
        ctx.arc(x, y, r, 0, Math.PI * 2);
        ctx.fillStyle = grad;
        ctx.fill();

        // Stroke
        ctx.strokeStyle = bt.stroke;
        ctx.lineWidth = 1.5;
        ctx.stroke();

        // Shine highlight
        ctx.beginPath();
        ctx.arc(x - r * 0.25, y - r * 0.3, r * 0.35, -Math.PI * 0.8, -Math.PI * 0.2);
        ctx.strokeStyle = 'rgba(255,255,255,0.6)';
        ctx.lineWidth = 1.5;
        ctx.stroke();

        // Icon
        ctx.fillStyle = bt.stroke;
        ctx.font = (r * 0.9) + 'px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(bt.icon, x, y + 1);

        // Special overlay
        if (special === 'gift') {
            ctx.strokeStyle = '#ffd54f';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(x, y, r + 2, 0, Math.PI * 2);
            ctx.stroke();
            ctx.fillStyle = '#ffd54f';
            ctx.font = '8px sans-serif';
            ctx.fillText('🎁', x, y - r - 4);
        }

        ctx.restore();
    };

    BubbleSpa.prototype.drawShooter = function (ctx) {
        ctx.save();
        ctx.translate(SHOOTER_X, SHOOTER_Y);
        ctx.rotate(this.aimAngle + Math.PI / 2);

        // Cannon body
        ctx.fillStyle = '#37474f';
        ctx.beginPath();
        ctx.moveTo(-12, 10);
        ctx.lineTo(12, 10);
        ctx.lineTo(6, -25);
        ctx.lineTo(-6, -25);
        ctx.closePath();
        ctx.fill();

        // Cannon tip
        ctx.fillStyle = '#546e7a';
        ctx.beginPath();
        ctx.arc(0, -25, 8, 0, Math.PI * 2);
        ctx.fill();

        ctx.restore();

        // Current bubble on cannon
        if (this.state === 'playing' && !this.bullet) {
            var bx = SHOOTER_X + Math.cos(this.aimAngle) * 25;
            var by = SHOOTER_Y + Math.sin(this.aimAngle) * 25;
            this.drawBubble(ctx, bx, by, this.currentBubble, null, false);
        }

        // Base platform
        ctx.fillStyle = '#455a64';
        ctx.beginPath();
        ctx.arc(SHOOTER_X, SHOOTER_Y + 5, 18, 0, Math.PI * 2);
        ctx.fill();
    };

    BubbleSpa.prototype.drawAimLine = function (ctx) {
        if (this.bullet) return;
        ctx.save();
        ctx.strokeStyle = 'rgba(255,255,255,0.3)';
        ctx.lineWidth = 1;
        ctx.setLineDash([6, 6]);
        ctx.beginPath();
        var sx = SHOOTER_X, sy = SHOOTER_Y - 20;
        ctx.moveTo(sx, sy);
        var len = 200;
        ctx.lineTo(
            sx + Math.cos(this.aimAngle) * len,
            sy + Math.sin(this.aimAngle) * len
        );
        ctx.stroke();
        ctx.restore();
    };

    /* ======================================================================
       AUTO-INITIALISE
       ====================================================================== */
    function initAll() {
        var containers = document.querySelectorAll('.dps-bubble-spa');
        for (var i = 0; i < containers.length; i++) {
            if (!containers[i].dataset.init) {
                containers[i].dataset.init = '1';
                var game = new BubbleSpa(containers[i]);
                containers[i]._game = game;
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
