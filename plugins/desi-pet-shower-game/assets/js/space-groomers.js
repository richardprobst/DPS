/**
 * Space Groomers: Invasao das Pulgas
 * Game engine - Canvas + vanilla JS, zero dependencias.
 *
 * @version 1.2.0
 */
(function () {
    'use strict';

    var W = 480;
    var H = 640;
    var PLAYER_W = 40;
    var PLAYER_H = 28;
    var BULLET_W = 4;
    var BULLET_H = 12;
    var ENEMY_SIZE = 28;
    var POWERUP_SIZE = 22;
    var MUD_SIZE = 6;
    var FPS = 60;
    var FRAME_TIME = 1000 / FPS;
    var LS_KEY = 'dps_sg_highscore';

    var BALANCE = {
        totalWaves: 8,
        waveIntroMs: 420,
        perfectBonusBase: 120,
        perfectBonusStep: 20,
        autoFireInterval: 0.22,
        pickupRadius: 34,
        playerSpeed: 5.2,
        playerInvulnMs: 850,
        comboTier2: 4,
        comboTier3: 9,
        comboWindow: 3.8,
        specialCost: 420,
        powerupBaseChance: 0.00125,
        mudStartWave: 3,
        mudBaseInterval: 3.1,
        mudMinInterval: 1.05,
        diveStartWave: 4,
        diveBaseInterval: 7.5,
        diveMinInterval: 3.4,
        particleCap: 96,
        floatingTextCap: 10,
        hitFreezeMs: 22,
        killFreezeMs: 34,
        shakeHit: 2,
        shakeDamage: 7,
        shakeSpecial: 8,
        shakeGameOver: 10,
        gameOverDelayMs: 620
    };

    var ENEMY_TYPES = {
        flea: {
            hp: 1,
            pts: 10,
            color: '#a0522d',
            speed: 1,
            label: 'pulgas'
        },
        tick: {
            hp: 2,
            pts: 24,
            color: '#556b2f',
            speed: 0.62,
            label: 'carrapatos'
        },
        furball: {
            hp: 1,
            pts: 16,
            color: '#d2b48c',
            speed: 1.3,
            label: 'pelos'
        }
    };

    var POWERUP_TYPES = {
        shampoo: {
            icon: '\uD83E\uDDF4',
            name: 'Shampoo Turbo',
            shortLabel: '3 jatos',
            desc: '3 tiros por disparo',
            duration: 8000,
            color: '#4fc3f7'
        },
        towel: {
            icon: '\uD83E\uDDF9',
            name: 'Toalha Giratoria',
            shortLabel: 'limpa fileira',
            desc: 'remove a fileira mais baixa',
            duration: 0,
            color: '#f7c948'
        }
    };

    var audioCtx = null;

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function ensureAudio() {
        if (!audioCtx) {
            try {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            } catch (e) {
                audioCtx = null;
            }
        }
    }

    function playTone(freq, dur, type, vol) {
        ensureAudio();
        if (!audioCtx) {
            return;
        }

        try {
            var oscillator = audioCtx.createOscillator();
            var gain = audioCtx.createGain();
            oscillator.type = type || 'square';
            oscillator.frequency.value = freq;
            gain.gain.value = vol || 0.08;
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + dur);
            oscillator.connect(gain);
            gain.connect(audioCtx.destination);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + dur);
        } catch (e) {
            return;
        }
    }

    function sfxShoot() {
        playTone(880, 0.05, 'square', 0.05);
    }

    function sfxHit() {
        playTone(240, 0.08, 'triangle', 0.07);
    }

    function sfxKill() {
        playTone(410, 0.07, 'triangle', 0.06);
        playTone(610, 0.08, 'triangle', 0.04);
    }

    function sfxPowerup() {
        playTone(660, 0.08, 'sine', 0.07);
        playTone(990, 0.10, 'sine', 0.06);
    }

    function sfxLoseLife() {
        playTone(150, 0.20, 'sawtooth', 0.06);
    }

    function sfxSpecial() {
        playTone(440, 0.15, 'sine', 0.08);
        playTone(880, 0.20, 'sine', 0.06);
    }

    function sfxComboTier() {
        playTone(540, 0.08, 'sine', 0.06);
        playTone(720, 0.08, 'sine', 0.05);
    }

    function sfxReady() {
        playTone(520, 0.06, 'triangle', 0.05);
        playTone(780, 0.08, 'triangle', 0.04);
    }

    function getWaveConfig(wave) {
        return {
            cols: Math.min(5 + Math.floor((wave - 1) / 2), 7),
            rows: Math.min(2 + Math.floor((wave - 1) / 3), 4),
            tickChance: wave >= 4 ? Math.min(0.12 + (wave - 4) * 0.05, 0.28) : 0,
            furballChance: wave >= 2 ? Math.min(0.18 + wave * 0.025, 0.38) : 0,
            speedMultiplier: 0.92 + wave * 0.06,
            mudInterval: wave < BALANCE.mudStartWave ? 999 : Math.max(BALANCE.mudMinInterval, BALANCE.mudBaseInterval - wave * 0.18),
            powerupChance: BALANCE.powerupBaseChance + wave * 0.00008,
            diveInterval: wave < BALANCE.diveStartWave ? 999 : Math.max(BALANCE.diveMinInterval, BALANCE.diveBaseInterval - wave * 0.45),
            perfectBonus: BALANCE.perfectBonusBase + (wave - 1) * BALANCE.perfectBonusStep
        };
    }

    function drawPlayer(ctx, game) {
        var x = game.player.x;
        var y = game.player.y + Math.sin(game.runTimeMs / 180) * 1.5;
        var hitFlash = game.playerHitTimer > 0;
        var blink = game.playerInvulnTimer > 0 && Math.floor(game.playerInvulnTimer / 70) % 2 === 0;
        var enginePulse = 0.9 + Math.sin(game.runTimeMs / 75) * 0.1;

        if (blink && !hitFlash && game.state !== 'gameoverTransition') {
            return;
        }

        ctx.save();
        ctx.translate(x, y);
        if (hitFlash) {
            ctx.scale(0.95, 1.08);
        }

        ctx.fillStyle = hitFlash ? '#ffd7d7' : '#4fc3f7';
        ctx.fillRect(-4, -14, 8, 28);
        ctx.fillRect(-16, 0, 32, 10);
        ctx.fillRect(-20, 4, 8, 8);
        ctx.fillRect(12, 4, 8, 8);

        ctx.fillStyle = '#e1f5fe';
        ctx.fillRect(-2, -14, 4, 8);

        ctx.fillStyle = hitFlash ? '#ff8a80' : '#0288d1';
        ctx.fillRect(-12, 2, 24, 4);

        ctx.fillStyle = hitFlash ? '#ff7043' : 'rgba(255, 183, 77, 0.9)';
        ctx.fillRect(-18, 12, 6, 8 * enginePulse);
        ctx.fillRect(12, 12, 6, 8 * enginePulse);

        ctx.restore();
    }

    function drawEnemy(ctx, enemy, runTimeMs) {
        var type = enemy.type;
        var et = ENEMY_TYPES[type];
        var s = ENEMY_SIZE;
        var hs = s / 2;
        var bobOffset = enemy.pattern === 'dive' ? 0 : Math.sin(runTimeMs / 220 + enemy.animSeed) * 1.2;

        ctx.save();
        ctx.translate(enemy.x, enemy.y + bobOffset);

        if (enemy.pattern === 'dive' && enemy.telegraph > 0) {
            var ringSize = hs + 4 + (1 - enemy.telegraph / enemy.telegraphMax) * 8;
            ctx.strokeStyle = 'rgba(255, 120, 120, 0.75)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(0, 0, ringSize, 0, Math.PI * 2);
            ctx.stroke();
        }

        if (enemy.pattern === 'dive' && enemy.telegraph <= 0) {
            ctx.rotate(clamp(enemy.vx * 0.12, -0.25, 0.25));
        }

        if (enemy.hurtTimer > 0) {
            ctx.globalAlpha = 0.88;
        }

        if (type === 'flea') {
            ctx.fillStyle = enemy.hurtTimer > 0 ? '#fff1e8' : et.color;
            ctx.beginPath();
            ctx.arc(0, 0, hs * 0.7, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillRect(-hs, hs * 0.3, 4, 8);
            ctx.fillRect(hs - 4, hs * 0.3, 4, 8);
            ctx.fillStyle = '#fff';
            ctx.fillRect(-4, -4, 3, 3);
            ctx.fillRect(2, -4, 3, 3);
        } else if (type === 'tick') {
            ctx.fillStyle = enemy.hurtTimer > 0 ? '#f3f6d8' : (enemy.hp > 1 ? et.color : '#8b4513');
            ctx.fillRect(-hs, -hs, s, s);
            ctx.fillStyle = '#3e5902';
            ctx.fillRect(-hs + 3, -hs + 3, s - 6, s - 6);
            ctx.fillStyle = '#fff';
            ctx.fillRect(-4, -3, 3, 3);
            ctx.fillRect(2, -3, 3, 3);
            if (enemy.hp > 1) {
                ctx.fillStyle = 'rgba(255,255,255,0.28)';
                ctx.fillRect(-hs + 2, -hs + 2, s - 4, 3);
            }
        } else {
            ctx.fillStyle = enemy.hurtTimer > 0 ? '#fff7ea' : et.color;
            ctx.beginPath();
            ctx.arc(0, 0, hs * 0.8, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = '#c4a882';
            ctx.lineWidth = 1;
            for (var i = 0; i < 6; i++) {
                var angle = (i / 6) * Math.PI * 2;
                ctx.beginPath();
                ctx.moveTo(Math.cos(angle) * hs * 0.5, Math.sin(angle) * hs * 0.5);
                ctx.lineTo(Math.cos(angle) * hs, Math.sin(angle) * hs);
                ctx.stroke();
            }
        }

        if (enemy.pattern === 'dive' && enemy.telegraph <= 0) {
            ctx.strokeStyle = 'rgba(255, 191, 128, 0.55)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(0, hs * 0.5);
            ctx.lineTo(0, hs + 10);
            ctx.stroke();
        }

        ctx.restore();
    }

    function drawBullet(ctx, bullet) {
        ctx.fillStyle = '#e1f5fe';
        ctx.fillRect(bullet.x - BULLET_W / 2, bullet.y - BULLET_H / 2, BULLET_W, BULLET_H);
        ctx.fillStyle = 'rgba(79,195,247,0.4)';
        ctx.fillRect(bullet.x - BULLET_W, bullet.y, BULLET_W * 2, BULLET_H / 2);
    }

    function drawMud(ctx, mud) {
        ctx.fillStyle = '#795548';
        ctx.beginPath();
        ctx.arc(mud.x, mud.y, MUD_SIZE, 0, Math.PI * 2);
        ctx.fill();
    }

    function drawPowerup(ctx, powerup, runTimeMs) {
        var pt = POWERUP_TYPES[powerup.type];
        var bob = Math.sin(runTimeMs / 180 + powerup.animSeed) * 4;
        var y = powerup.y + bob;

        ctx.save();
        ctx.translate(powerup.x, y);

        ctx.fillStyle = pt.color;
        ctx.beginPath();
        ctx.arc(0, 0, POWERUP_SIZE / 2 + 2, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = 'rgba(255,255,255,0.45)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(0, 0, POWERUP_SIZE / 2 + 7 + Math.sin(runTimeMs / 170 + powerup.animSeed) * 2, 0, Math.PI * 2);
        ctx.stroke();

        ctx.fillStyle = '#fff';
        ctx.font = '14px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(pt.icon, 0, 0);

        ctx.fillStyle = 'rgba(8, 14, 39, 0.85)';
        ctx.fillRect(-38, -28, 76, 16);

        ctx.fillStyle = '#fff';
        ctx.font = '600 10px "Segoe UI", system-ui, sans-serif';
        ctx.fillText(pt.shortLabel, 0, -20);
        ctx.restore();
    }

    function drawStars(ctx, stars) {
        ctx.fillStyle = 'rgba(255,255,255,0.6)';
        for (var i = 0; i < stars.length; i++) {
            ctx.fillRect(stars[i].x, stars[i].y, stars[i].s, stars[i].s);
        }
    }

    function drawParticles(ctx, arr) {
        for (var i = 0; i < arr.length; i++) {
            var particle = arr[i];
            ctx.globalAlpha = clamp(particle.life / particle.maxLife, 0, 1);
            ctx.fillStyle = particle.color;
            ctx.fillRect(particle.x - particle.size / 2, particle.y - particle.size / 2, particle.size, particle.size);
        }
        ctx.globalAlpha = 1;
    }

    function drawFloatingTexts(ctx, arr) {
        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        for (var i = 0; i < arr.length; i++) {
            var item = arr[i];
            ctx.globalAlpha = clamp(item.life / item.maxLife, 0, 1);
            ctx.fillStyle = item.color;
            ctx.font = item.font;
            ctx.fillText(item.text, item.x, item.y);
        }

        ctx.restore();
        ctx.globalAlpha = 1;
    }

    function SpaceGroomers(container) {
        this.container = container;
        this.canvas = container.querySelector('.dps-sg-canvas');
        this.ctx = this.canvas.getContext('2d');

        this.elScore = container.querySelector('.dps-sg-score');
        this.elWave = container.querySelector('.dps-sg-wave');
        this.elLives = container.querySelector('.dps-sg-lives');
        this.elCombo = container.querySelector('.dps-sg-combo');
        this.elComboText = container.querySelector('.dps-sg-combo__text');
        this.elComboHint = container.querySelector('.dps-sg-combo__hint');
        this.elComboFill = container.querySelector('.dps-sg-combo__fill');
        this.elToast = container.querySelector('.dps-sg-toast');
        this.elToastTitle = container.querySelector('.dps-sg-toast__title');
        this.elToastDesc = container.querySelector('.dps-sg-toast__desc');
        this.elPowerup = container.querySelector('.dps-sg-powerup-indicator');
        this.elPowerupIcon = container.querySelector('.dps-sg-powerup-indicator__icon');
        this.elPowerupName = container.querySelector('.dps-sg-powerup-indicator__name');
        this.elPowerupDesc = container.querySelector('.dps-sg-powerup-indicator__desc');
        this.elPowerupFill = container.querySelector('.dps-sg-powerup-indicator__fill');
        this.elSpecialBar = container.querySelector('.dps-sg-special-bar');
        this.elSpecialFill = container.querySelector('.dps-sg-special-bar__fill');
        this.elSpecialBtn = container.querySelector('.dps-sg-btn--special');

        this.overlayStart = container.querySelector('.dps-sg-overlay--start');
        this.overlayGameover = container.querySelector('.dps-sg-overlay--gameover');
        this.overlayVictory = container.querySelector('.dps-sg-overlay--victory');
        this.overlayWave = container.querySelector('.dps-sg-overlay--wave');

        this.state = 'idle';
        this.rafId = null;
        this.lastTime = 0;
        this.waveTimeout = null;

        var storedHighscore = 0;
        try {
            storedHighscore = parseInt(localStorage.getItem(LS_KEY), 10) || 0;
        } catch (e) {
            storedHighscore = 0;
        }

        this.highscore = storedHighscore;
        this.updateHighscoreDisplay();
        this.bindEvents();
        this.reset();
        this.draw();
        this.updateHUD();
    }

    SpaceGroomers.prototype.reset = function () {
        this.score = 0;
        this.wave = 1;
        this.waveConfig = getWaveConfig(this.wave);
        this.lives = 3;
        this.comboCount = 0;
        this.comboMultiplier = 1;
        this.comboTimer = 0;
        this.bestComboCount = 0;
        this.specialCharge = 0;
        this.activePowerup = null;
        this.powerupTimer = 0;
        this.wavePerfect = true;
        this.runTimeMs = 0;
        this.playerHitTimer = 0;
        this.playerInvulnTimer = 0;
        this.screenShakeTimer = 0;
        this.screenShakeForce = 0;
        this.freezeTimer = 0;
        this.toastTimer = 0;
        this.toastTone = 'neutral';
        this.gameOverTimer = 0;
        this.specialReadyPlayed = false;
        this.specialReadyPulse = 0;
        this.comboPulse = 0;
        this.firstDiveAnnounced = false;

        this.stats = {
            flea: 0,
            tick: 0,
            furball: 0
        };

        this.player = {
            x: W / 2,
            y: H - 60,
            speed: BALANCE.playerSpeed
        };

        this.bullets = [];
        this.enemies = [];
        this.muds = [];
        this.powerups = [];
        this.particles = [];
        this.floatingTexts = [];
        this.stars = [];

        for (var i = 0; i < 60; i++) {
            this.stars.push({
                x: Math.random() * W,
                y: Math.random() * H,
                s: Math.random() < 0.25 ? 2 : 1,
                vy: 0.2 + Math.random() * 0.35
            });
        }

        this.keys = {};
        this.shootCooldown = 0;
        this.isPointerDown = false;
        this.pointerId = null;
        this.pointerOffsetX = 0;
        this.mobileDragActive = false;
        this.enemyDir = 1;
        this.mudCooldown = this.waveConfig.mudInterval;
        this.diveCooldown = this.waveConfig.diveInterval;

        this.clearToast();
        this.hideAllOverlays();
        if (this.overlayStart) {
            this.overlayStart.classList.remove('dps-sg-overlay--hidden');
        }
    };

    SpaceGroomers.prototype.hideAllOverlays = function () {
        if (this.waveTimeout) {
            clearTimeout(this.waveTimeout);
            this.waveTimeout = null;
        }

        this.overlayStart.classList.add('dps-sg-overlay--hidden');
        this.overlayGameover.classList.add('dps-sg-overlay--hidden');
        this.overlayVictory.classList.add('dps-sg-overlay--hidden');
        this.overlayWave.classList.add('dps-sg-overlay--hidden');
    };

    SpaceGroomers.prototype.start = function () {
        this.reset();
        this.hideAllOverlays();
        this.state = 'waveIntro';
        this.showWaveIntro();
    };

    SpaceGroomers.prototype.showWaveIntro = function () {
        var self = this;
        var titleEl = this.container.querySelector('.dps-sg-wave-title');
        var bonusEl = this.container.querySelector('.dps-sg-wave-bonus');
        this.waveConfig = getWaveConfig(this.wave);
        titleEl.textContent = 'Onda ' + this.wave;
        bonusEl.textContent = this.wave === 1 ? 'Comeco mais leve, combo rapido e power-ups mais claros.' : '';
        this.overlayWave.classList.remove('dps-sg-overlay--hidden');

        this.waveTimeout = setTimeout(function () {
            self.overlayWave.classList.add('dps-sg-overlay--hidden');
            self.spawnWave();
            self.state = 'playing';
            self.lastTime = performance.now();
            self.loop(self.lastTime);
        }, BALANCE.waveIntroMs);
    };

    SpaceGroomers.prototype.spawnWave = function () {
        var wave = this.wave;
        var config = this.waveConfig;
        var cols = config.cols;
        var rows = config.rows;
        var startX = (W - cols * 44) / 2 + 22;
        this.enemies = [];

        for (var row = 0; row < rows; row++) {
            for (var col = 0; col < cols; col++) {
                var type = 'flea';
                if (wave >= 4 && row === 0 && Math.random() < config.tickChance) {
                    type = 'tick';
                } else if (wave >= 2 && Math.random() < config.furballChance) {
                    type = 'furball';
                }

                var enemyType = ENEMY_TYPES[type];
                this.enemies.push({
                    type: type,
                    x: startX + col * 44,
                    y: 64 + row * 40,
                    hp: enemyType.hp,
                    baseSpeed: enemyType.speed * config.speedMultiplier,
                    pattern: 'formation',
                    telegraph: 0,
                    telegraphMax: 0.36,
                    vx: 0,
                    vy: 0,
                    hurtTimer: 0,
                    animSeed: Math.random() * Math.PI * 2
                });
            }
        }

        this.enemyDir = 1;
        this.mudCooldown = config.mudInterval;
        this.diveCooldown = config.diveInterval;
        this.wavePerfect = true;
        this.showToast('Onda ' + wave, wave >= BALANCE.diveStartWave ? 'Pulgas em mergulho podem aparecer.' : 'Foque em manter a sequencia viva.', wave >= BALANCE.diveStartWave ? 'warning' : 'neutral', 1600);
    };

    SpaceGroomers.prototype.loop = function (now) {
        if (this.state !== 'playing' && this.state !== 'gameoverTransition') {
            return;
        }

        var elapsed = Math.min(now - this.lastTime, 50);
        this.lastTime = now;

        if (this.freezeTimer > 0) {
            this.freezeTimer = Math.max(0, this.freezeTimer - elapsed);
            this.draw();
            this.updateHUD();
        } else {
            this.update(elapsed / 1000);
            this.draw();
            this.updateHUD();
        }

        var self = this;
        this.rafId = requestAnimationFrame(function (time) {
            self.loop(time);
        });
    };

    SpaceGroomers.prototype.update = function (dt) {
        var dtMs = dt * 1000;
        this.updateAmbient(dt, dtMs);

        if (this.state === 'gameoverTransition') {
            this.gameOverTimer -= dtMs;
            if (this.gameOverTimer <= 0) {
                this.finishGameOver();
            }
            return;
        }

        if (this.state !== 'playing') {
            return;
        }

        this.runTimeMs += dtMs;

        var moveDir = 0;
        if (!this.mobileDragActive) {
            if (this.keys.ArrowLeft || this.keys.a) {
                moveDir = -1;
            }
            if (this.keys.ArrowRight || this.keys.d) {
                moveDir = 1;
            }
            this.player.x += moveDir * this.player.speed;
        }
        this.player.x = clamp(this.player.x, PLAYER_W / 2 + 4, W - PLAYER_W / 2 - 4);

        this.shootCooldown -= dt;
        if (this.shootCooldown <= 0) {
            this.shoot();
            this.shootCooldown = BALANCE.autoFireInterval;
        }

        if (this.keys.Shift || this.keys.Control) {
            this.fireSpecial();
        }

        this.updateBullets();
        this.updateEnemies(dt);
        this.updateMud(dt);
        this.updateBulletEnemyCollisions();
        this.updatePowerups();
        this.updatePowerupTimer(dtMs);
        this.updateComboTimer(dt);
        this.updateSpecialReadyState();

        if (this.enemies.length === 0 && this.state === 'playing') {
            this.endWave();
        }
    };

    SpaceGroomers.prototype.updateAmbient = function (dt, dtMs) {
        for (var i = 0; i < this.stars.length; i++) {
            this.stars[i].y += this.stars[i].vy;
            if (this.stars[i].y > H) {
                this.stars[i].y = 0;
                this.stars[i].x = Math.random() * W;
            }
        }

        if (this.playerHitTimer > 0) {
            this.playerHitTimer = Math.max(0, this.playerHitTimer - dtMs);
        }
        if (this.playerInvulnTimer > 0) {
            this.playerInvulnTimer = Math.max(0, this.playerInvulnTimer - dtMs);
        }
        if (this.screenShakeTimer > 0) {
            this.screenShakeTimer = Math.max(0, this.screenShakeTimer - dtMs);
            if (this.screenShakeTimer === 0) {
                this.screenShakeForce = 0;
            }
        }
        if (this.specialReadyPulse > 0) {
            this.specialReadyPulse = Math.max(0, this.specialReadyPulse - dtMs);
        }
        if (this.comboPulse > 0) {
            this.comboPulse = Math.max(0, this.comboPulse - dtMs);
        }
        if (this.toastTimer > 0) {
            this.toastTimer = Math.max(0, this.toastTimer - dtMs);
            if (this.toastTimer === 0) {
                this.clearToast();
            }
        }

        for (var p = this.particles.length - 1; p >= 0; p--) {
            var particle = this.particles[p];
            particle.x += particle.vx;
            particle.y += particle.vy;
            particle.life -= dtMs;
            if (particle.life <= 0) {
                this.particles.splice(p, 1);
            }
        }

        for (var t = this.floatingTexts.length - 1; t >= 0; t--) {
            var text = this.floatingTexts[t];
            text.x += text.vx;
            text.y += text.vy;
            text.life -= dtMs;
            if (text.life <= 0) {
                this.floatingTexts.splice(t, 1);
            }
        }

        for (var e = 0; e < this.enemies.length; e++) {
            if (this.enemies[e].hurtTimer > 0) {
                this.enemies[e].hurtTimer = Math.max(0, this.enemies[e].hurtTimer - dtMs);
            }
        }
    };

    SpaceGroomers.prototype.updateBullets = function () {
        for (var i = this.bullets.length - 1; i >= 0; i--) {
            this.bullets[i].y -= 8;
            if (this.bullets[i].y < -10) {
                this.bullets.splice(i, 1);
                this.resetCombo();
            }
        }
    };

    SpaceGroomers.prototype.updateEnemies = function (dt) {
        var minX = W;
        var maxX = 0;
        var hasFormationEnemies = false;

        for (var i = 0; i < this.enemies.length; i++) {
            var enemy = this.enemies[i];
            if (enemy.pattern === 'formation') {
                hasFormationEnemies = true;
                if (enemy.x < minX) {
                    minX = enemy.x;
                }
                if (enemy.x > maxX) {
                    maxX = enemy.x;
                }
            }
        }

        var edgeHit = hasFormationEnemies && (
            (this.enemyDir > 0 && maxX > W - 30) ||
            (this.enemyDir < 0 && minX < 30)
        );

        if (edgeHit) {
            this.enemyDir *= -1;
            for (var j = 0; j < this.enemies.length; j++) {
                if (this.enemies[j].pattern === 'formation') {
                    this.enemies[j].y += 10;
                }
            }
        }

        this.diveCooldown -= dt;
        if (this.diveCooldown <= 0) {
            this.triggerDiveAttack();
            this.diveCooldown = this.waveConfig.diveInterval;
        }

        for (var k = this.enemies.length - 1; k >= 0; k--) {
            var current = this.enemies[k];
            if (current.pattern === 'dive') {
                if (current.telegraph > 0) {
                    current.telegraph -= dt;
                } else {
                    current.x += current.vx;
                    current.y += current.vy;
                    current.vy = Math.min(current.vy + 0.02, 4.4);
                }
            } else {
                current.x += this.enemyDir * current.baseSpeed;
            }

            if (current.y > H - 50) {
                this.enemies.splice(k, 1);
                this.loseLife(current.x, current.y);
                this.wavePerfect = false;
            }
        }
    };

    SpaceGroomers.prototype.triggerDiveAttack = function () {
        if (this.wave < BALANCE.diveStartWave) {
            return;
        }

        var candidates = [];
        for (var i = 0; i < this.enemies.length; i++) {
            if (this.enemies[i].pattern === 'formation' && this.enemies[i].type !== 'tick') {
                candidates.push(this.enemies[i]);
            }
        }

        if (!candidates.length) {
            return;
        }

        var diver = candidates[Math.floor(Math.random() * candidates.length)];
        diver.pattern = 'dive';
        diver.telegraph = diver.telegraphMax;
        diver.vx = clamp((this.player.x - diver.x) * 0.015, -2.4, 2.4);
        diver.vy = 2.1 + this.wave * 0.12;

        if (!this.firstDiveAnnounced) {
            this.firstDiveAnnounced = true;
            this.showToast('Novo padrao', 'Algumas pulgas fazem mergulho rapido. Reaja para os lados.', 'warning', 1800);
        }
    };

    SpaceGroomers.prototype.updateMud = function (dt) {
        this.mudCooldown -= dt;
        if (this.mudCooldown <= 0 && this.enemies.length > 0 && this.wave >= BALANCE.mudStartWave) {
            this.mudCooldown = this.waveConfig.mudInterval;
            var source = this.enemies[Math.floor(Math.random() * this.enemies.length)];
            this.muds.push({
                x: source.x,
                y: source.y + ENEMY_SIZE / 2
            });
        }

        for (var i = this.muds.length - 1; i >= 0; i--) {
            this.muds[i].y += 3;
            if (this.muds[i].y > H + 10) {
                this.muds.splice(i, 1);
                continue;
            }

            if (
                Math.abs(this.muds[i].x - this.player.x) < PLAYER_W / 2 &&
                Math.abs(this.muds[i].y - this.player.y) < PLAYER_H / 2
            ) {
                this.emitParticles(this.muds[i].x, this.muds[i].y, '#795548', 6, 4);
                this.muds.splice(i, 1);
                this.loseLife(this.player.x, this.player.y);
            }
        }
    };

    SpaceGroomers.prototype.updateBulletEnemyCollisions = function () {
        for (var bulletIndex = this.bullets.length - 1; bulletIndex >= 0; bulletIndex--) {
            var bullet = this.bullets[bulletIndex];

            for (var enemyIndex = this.enemies.length - 1; enemyIndex >= 0; enemyIndex--) {
                var enemy = this.enemies[enemyIndex];
                var hitbox = this.activePowerup === 'shampoo' ? ENEMY_SIZE * 0.8 : ENEMY_SIZE / 2;

                if (Math.abs(bullet.x - enemy.x) < hitbox && Math.abs(bullet.y - enemy.y) < hitbox) {
                    enemy.hp--;
                    enemy.hurtTimer = 90;
                    this.bullets.splice(bulletIndex, 1);
                    sfxHit();
                    this.freezeFor(enemy.hp <= 0 ? BALANCE.killFreezeMs : BALANCE.hitFreezeMs);
                    this.shakeScreen(enemy.hp <= 0 ? BALANCE.shakeHit + 1 : BALANCE.shakeHit, 70);
                    this.emitParticles(enemy.x, enemy.y, '#e1f5fe', enemy.hp <= 0 ? 7 : 4, 4);

                    if (enemy.hp <= 0) {
                        var pts = ENEMY_TYPES[enemy.type].pts * this.comboMultiplier;
                        this.score += pts;
                        this.specialCharge = Math.min(BALANCE.specialCost, this.specialCharge + pts);
                        this.stats[enemy.type]++;
                        this.advanceCombo();
                        this.bestComboCount = Math.max(this.bestComboCount, this.comboCount);
                        this.spawnFloatingText('+' + pts, enemy.x, enemy.y - 12, this.comboMultiplier > 1 ? '#ffd166' : '#ffffff', '700 14px "Segoe UI", system-ui, sans-serif');
                        this.emitParticles(enemy.x, enemy.y, ENEMY_TYPES[enemy.type].color, 9, 5);
                        sfxKill();
                        this.enemies.splice(enemyIndex, 1);
                    }

                    break;
                }
            }
        }
    };

    SpaceGroomers.prototype.updatePowerups = function () {
        if (Math.random() < this.waveConfig.powerupChance && this.enemies.length > 0 && this.powerups.length < 2) {
            var powerupKeys = Object.keys(POWERUP_TYPES);
            var selectedKey = powerupKeys[Math.floor(Math.random() * powerupKeys.length)];
            this.powerups.push({
                type: selectedKey,
                x: 40 + Math.random() * (W - 80),
                y: -20,
                animSeed: Math.random() * Math.PI * 2
            });
        }

        for (var i = this.powerups.length - 1; i >= 0; i--) {
            this.powerups[i].y += 1.5;
            if (this.powerups[i].y > H + 20) {
                this.powerups.splice(i, 1);
                continue;
            }

            if (
                Math.abs(this.powerups[i].x - this.player.x) < BALANCE.pickupRadius &&
                Math.abs(this.powerups[i].y - this.player.y) < BALANCE.pickupRadius
            ) {
                this.collectPowerup(this.powerups[i].type);
                this.powerups.splice(i, 1);
            }
        }
    };

    SpaceGroomers.prototype.updatePowerupTimer = function (dtMs) {
        if (this.activePowerup && this.powerupTimer > 0) {
            this.powerupTimer -= dtMs;
            if (this.powerupTimer <= 0) {
                this.activePowerup = null;
                this.powerupTimer = 0;
                this.showToast('Fim do boost', 'O shampoo turbo acabou. Volte para a linha segura.', 'neutral', 1200);
            }
        }
    };

    SpaceGroomers.prototype.updateComboTimer = function (dt) {
        if (this.comboTimer > 0) {
            this.comboTimer -= dt;
            if (this.comboTimer <= 0) {
                this.resetCombo();
            }
        }
    };

    SpaceGroomers.prototype.updateSpecialReadyState = function () {
        if (this.specialCharge >= BALANCE.specialCost) {
            this.specialReadyPulse = 650;
            if (!this.specialReadyPlayed) {
                this.specialReadyPlayed = true;
                sfxReady();
                this.showToast('Especial pronto', 'Toque no raio para limpar a metade de baixo.', 'success', 1500);
            }
        } else {
            this.specialReadyPlayed = false;
        }
    };

    SpaceGroomers.prototype.shoot = function () {
        sfxShoot();
        var px = this.player.x;
        var py = this.player.y - PLAYER_H / 2;

        if (this.activePowerup === 'shampoo') {
            this.bullets.push({ x: px, y: py });
            this.bullets.push({ x: px - 14, y: py + 4 });
            this.bullets.push({ x: px + 14, y: py + 4 });
        } else {
            this.bullets.push({ x: px, y: py });
        }
    };

    SpaceGroomers.prototype.fireSpecial = function () {
        if (this.specialCharge < BALANCE.specialCost || this.state !== 'playing') {
            return;
        }

        this.specialCharge = 0;
        this.specialReadyPlayed = false;
        this.specialReadyPulse = 700;
        sfxSpecial();
        this.shakeScreen(BALANCE.shakeSpecial, 180);
        this.freezeFor(46);

        for (var i = this.enemies.length - 1; i >= 0; i--) {
            if (this.enemies[i].y > H / 2) {
                var enemy = this.enemies[i];
                var pts = ENEMY_TYPES[enemy.type].pts;
                this.score += pts;
                this.stats[enemy.type]++;
                this.emitParticles(enemy.x, enemy.y, '#e1f5fe', 8, 5);
                this.spawnFloatingText('+' + pts, enemy.x, enemy.y - 8, '#b3e5fc', '700 13px "Segoe UI", system-ui, sans-serif');
                this.enemies.splice(i, 1);
            }
        }

        for (var j = 0; j < 28; j++) {
            this.emitParticles(Math.random() * W, H / 2 + Math.random() * (H / 2), '#e1f5fe', 1, 5);
        }

        this.showToast('Banho de espuma', 'A metade inferior foi limpa. Aproveite para respirar.', 'success', 1300);
    };

    SpaceGroomers.prototype.collectPowerup = function (type) {
        var powerup = POWERUP_TYPES[type];
        sfxPowerup();
        this.emitParticles(this.player.x, this.player.y - 10, powerup.color, 8, 5);
        this.shakeScreen(3, 90);

        if (type === 'towel') {
            var maxY = 0;
            for (var i = 0; i < this.enemies.length; i++) {
                if (this.enemies[i].y > maxY) {
                    maxY = this.enemies[i].y;
                }
            }

            for (var j = this.enemies.length - 1; j >= 0; j--) {
                if (Math.abs(this.enemies[j].y - maxY) < 20) {
                    var enemy = this.enemies[j];
                    this.score += ENEMY_TYPES[enemy.type].pts;
                    this.stats[enemy.type]++;
                    this.emitParticles(enemy.x, enemy.y, '#f7c948', 8, 5);
                    this.spawnFloatingText('limpo', enemy.x, enemy.y - 10, '#f7c948', '700 12px "Segoe UI", system-ui, sans-serif');
                    this.enemies.splice(j, 1);
                }
            }
        } else {
            this.activePowerup = type;
            this.powerupTimer = powerup.duration;
        }

        this.showToast(powerup.name, powerup.desc, 'success', 1700);
    };

    SpaceGroomers.prototype.advanceCombo = function () {
        var previousMultiplier = this.comboMultiplier;
        this.comboCount++;
        this.comboTimer = BALANCE.comboWindow;

        if (this.comboCount >= BALANCE.comboTier3) {
            this.comboMultiplier = 3;
        } else if (this.comboCount >= BALANCE.comboTier2) {
            this.comboMultiplier = 2;
        } else {
            this.comboMultiplier = 1;
        }

        if (this.comboMultiplier !== previousMultiplier) {
            this.comboPulse = 700;
            sfxComboTier();
            this.emitParticles(this.player.x, this.player.y - 24, this.comboMultiplier === 3 ? '#ffb703' : '#ff8f3f', 10, 4);
            this.spawnFloatingText(this.comboMultiplier === 3 ? 'NO EMBALO' : 'BOA SEQUENCIA', this.player.x, this.player.y - 52, '#ffffff', '700 12px "Segoe UI", system-ui, sans-serif');
            this.showToast(
                this.comboMultiplier === 3 ? 'Combo x3' : 'Combo x2',
                this.comboMultiplier === 3 ? 'Pontuacao alta enquanto a sequencia durar.' : 'Mantenha o ritmo para chegar ao x3.',
                'success',
                1300
            );
        }
    };

    SpaceGroomers.prototype.resetCombo = function () {
        this.comboCount = 0;
        this.comboMultiplier = 1;
        this.comboTimer = 0;
    };

    SpaceGroomers.prototype.loseLife = function (sourceX, sourceY) {
        if (this.playerInvulnTimer > 0 || (this.state !== 'playing' && this.state !== 'gameoverTransition')) {
            return;
        }

        this.lives--;
        this.playerHitTimer = 220;
        this.playerInvulnTimer = this.lives > 0 ? BALANCE.playerInvulnMs : 0;
        this.wavePerfect = false;
        this.resetCombo();
        sfxLoseLife();
        this.freezeFor(40);
        this.shakeScreen(this.lives > 0 ? BALANCE.shakeDamage : BALANCE.shakeGameOver, 160);
        this.emitParticles(sourceX || this.player.x, sourceY || this.player.y, '#ff6b6b', 12, 5);

        if (this.lives <= 0) {
            this.startGameOverSequence();
        } else if (this.lives === 1) {
            this.showToast('Ultima vida', 'Segure o centro e guarde o especial para escapar.', 'warning', 1600);
        } else {
            this.showToast('Dano recebido', 'Respire, reposicione e retome a sequencia.', 'warning', 1200);
        }
    };

    SpaceGroomers.prototype.startGameOverSequence = function () {
        this.state = 'gameoverTransition';
        this.gameOverTimer = BALANCE.gameOverDelayMs;
        this.showToast('Fim da run', 'Mais uma tentativa costuma render melhor que a anterior.', 'warning', 1800);
    };

    SpaceGroomers.prototype.endWave = function () {
        if (this.wavePerfect) {
            var perfectBonus = this.waveConfig.perfectBonus;
            this.score += perfectBonus;
            this.spawnFloatingText('Perfeito +' + perfectBonus, W / 2, H / 2, '#9ae6b4', '700 16px "Segoe UI", system-ui, sans-serif');
        }

        if (this.wave >= BALANCE.totalWaves) {
            this.victory();
            return;
        }

        this.wave++;
        this.state = 'waveIntro';
        cancelAnimationFrame(this.rafId);

        var bonusEl = this.container.querySelector('.dps-sg-wave-bonus');
        bonusEl.textContent = this.wavePerfect ? 'Perfeito! Bonus garantido.' : 'Agora os inimigos apertam um pouco mais.';
        this.showWaveIntro();
    };

    SpaceGroomers.prototype.finishGameOver = function () {
        this.state = 'gameover';
        cancelAnimationFrame(this.rafId);
        this.saveHighscore();

        var overlay = this.overlayGameover;
        overlay.querySelector('.dps-sg-final-score').textContent = this.score.toLocaleString();
        overlay.querySelector('.dps-sg-overlay__stats').textContent =
            this.stats.flea + ' pulgas | ' +
            this.stats.tick + ' carrapatos | ' +
            this.stats.furball + ' pelos | melhor sequencia ' + this.bestComboCount + ' | ' + Math.round(this.runTimeMs / 1000) + 's';
        this.updateHighscoreDisplay();
        overlay.classList.remove('dps-sg-overlay--hidden');
    };

    SpaceGroomers.prototype.victory = function () {
        this.state = 'victory';
        cancelAnimationFrame(this.rafId);
        this.saveHighscore();

        var overlay = this.overlayVictory;
        overlay.querySelector('.dps-sg-final-score').textContent = this.score.toLocaleString();
        overlay.querySelector('.dps-sg-overlay__stats').textContent =
            this.stats.flea + ' pulgas | ' +
            this.stats.tick + ' carrapatos | ' +
            this.stats.furball + ' pelos | ' +
            Math.round(this.runTimeMs / 1000) + 's de run';
        this.updateHighscoreDisplay();
        overlay.classList.remove('dps-sg-overlay--hidden');
    };

    SpaceGroomers.prototype.saveHighscore = function () {
        if (this.score > this.highscore) {
            this.highscore = this.score;
            try {
                localStorage.setItem(LS_KEY, String(this.highscore));
            } catch (e) {
                return;
            }
        }
    };

    SpaceGroomers.prototype.updateHighscoreDisplay = function () {
        var els = this.container.querySelectorAll('.dps-sg-highscore-value');
        for (var i = 0; i < els.length; i++) {
            els[i].textContent = this.highscore.toLocaleString();
        }
    };

    SpaceGroomers.prototype.draw = function () {
        var ctx = this.ctx;
        ctx.clearRect(0, 0, W, H);
        ctx.save();

        if (this.screenShakeTimer > 0 && this.screenShakeForce > 0) {
            ctx.translate(
                (Math.random() - 0.5) * this.screenShakeForce,
                (Math.random() - 0.5) * this.screenShakeForce
            );
        }

        var background = ctx.createLinearGradient(0, 0, 0, H);
        background.addColorStop(0, '#081226');
        background.addColorStop(1, '#132347');
        ctx.fillStyle = background;
        ctx.fillRect(0, 0, W, H);

        ctx.fillStyle = 'rgba(111, 177, 255, 0.12)';
        ctx.beginPath();
        ctx.arc(W * 0.18, 90, 70, 0, Math.PI * 2);
        ctx.fill();

        drawStars(ctx, this.stars);

        ctx.fillStyle = 'rgba(79,195,247,0.08)';
        ctx.fillRect(0, H - 40, W, 40);
        ctx.fillStyle = 'rgba(79,195,247,0.2)';
        ctx.fillRect(0, H - 40, W, 2);

        for (var i = 0; i < this.enemies.length; i++) {
            drawEnemy(ctx, this.enemies[i], this.runTimeMs);
        }

        for (var j = 0; j < this.bullets.length; j++) {
            drawBullet(ctx, this.bullets[j]);
        }

        for (var k = 0; k < this.muds.length; k++) {
            drawMud(ctx, this.muds[k]);
        }

        for (var l = 0; l < this.powerups.length; l++) {
            drawPowerup(ctx, this.powerups[l], this.runTimeMs);
        }

        if (this.state !== 'gameoverTransition' || this.gameOverTimer > BALANCE.gameOverDelayMs / 3) {
            drawPlayer(ctx, this);
        }

        drawParticles(ctx, this.particles);
        drawFloatingTexts(ctx, this.floatingTexts);

        if (this.playerHitTimer > 0) {
            ctx.fillStyle = 'rgba(255, 107, 107, 0.12)';
            ctx.fillRect(0, 0, W, H);
        }

        ctx.restore();
    };

    SpaceGroomers.prototype.updateHUD = function () {
        this.elScore.textContent = this.score.toLocaleString();
        this.elWave.textContent = this.wave;

        var hearts = '';
        for (var i = 0; i < this.lives; i++) {
            hearts += '\u2764\uFE0F';
        }
        for (var j = this.lives; j < 3; j++) {
            hearts += '\uD83D\uDDA4';
        }
        this.elLives.textContent = hearts;

        if (this.comboCount > 1 || this.comboMultiplier > 1) {
            var comboProgress = this.getComboProgressPercent();
            this.elCombo.classList.remove('dps-sg-combo--hidden');
            this.elCombo.classList.toggle('dps-sg-combo--pulse', this.comboPulse > 0);
            this.elComboText.textContent = 'x' + this.comboMultiplier;
            this.elComboHint.textContent = this.getComboHint();
            this.elComboFill.style.width = comboProgress + '%';
        } else {
            this.elCombo.classList.add('dps-sg-combo--hidden');
            this.elCombo.classList.remove('dps-sg-combo--pulse');
            this.elComboFill.style.width = '0%';
        }

        if (this.activePowerup) {
            var powerup = POWERUP_TYPES[this.activePowerup];
            this.elPowerup.classList.remove('dps-sg-powerup-indicator--hidden');
            this.elPowerupIcon.textContent = powerup.icon;
            this.elPowerupName.textContent = powerup.name;
            this.elPowerupDesc.textContent = powerup.desc;
            this.elPowerupFill.style.width = (this.powerupTimer / powerup.duration * 100) + '%';
        } else {
            this.elPowerup.classList.add('dps-sg-powerup-indicator--hidden');
            this.elPowerupFill.style.width = '0%';
        }

        var pct = Math.min(100, (this.specialCharge / BALANCE.specialCost) * 100);
        this.elSpecialFill.style.width = pct + '%';
        this.elSpecialBar.classList.toggle('dps-sg-special-bar--ready', this.specialCharge >= BALANCE.specialCost || this.specialReadyPulse > 0);
        if (this.elSpecialBtn) {
            this.elSpecialBtn.disabled = this.specialCharge < BALANCE.specialCost;
            this.elSpecialBtn.classList.toggle('dps-sg-btn--charged', this.specialCharge >= BALANCE.specialCost || this.specialReadyPulse > 0);
        }
    };

    SpaceGroomers.prototype.getComboProgressPercent = function () {
        if (this.comboMultiplier >= 3) {
            return clamp((this.comboTimer / BALANCE.comboWindow) * 100, 0, 100);
        }
        if (this.comboCount < BALANCE.comboTier2) {
            return clamp((this.comboCount / BALANCE.comboTier2) * 100, 0, 100);
        }
        return clamp(((this.comboCount - BALANCE.comboTier2) / (BALANCE.comboTier3 - BALANCE.comboTier2)) * 100, 0, 100);
    };

    SpaceGroomers.prototype.getComboHint = function () {
        if (this.comboMultiplier >= 3) {
            return 'voce esta voando';
        }
        if (this.comboMultiplier === 2) {
            return 'mais ' + (BALANCE.comboTier3 - this.comboCount) + ' para x3';
        }
        return this.comboCount + ' acertos seguidos';
    };

    SpaceGroomers.prototype.showToast = function (title, desc, tone, durationMs) {
        if (!this.elToast) {
            return;
        }
        this.toastTimer = durationMs || 1200;
        this.toastTone = tone || 'neutral';
        this.elToastTitle.textContent = title;
        this.elToastDesc.textContent = desc || '';
        this.elToast.classList.remove('dps-sg-toast--hidden', 'dps-sg-toast--warning', 'dps-sg-toast--success');
        if (this.toastTone === 'warning') {
            this.elToast.classList.add('dps-sg-toast--warning');
        } else if (this.toastTone === 'success') {
            this.elToast.classList.add('dps-sg-toast--success');
        }
    };

    SpaceGroomers.prototype.clearToast = function () {
        if (!this.elToast) {
            return;
        }
        this.elToast.classList.add('dps-sg-toast--hidden');
        this.elToast.classList.remove('dps-sg-toast--warning', 'dps-sg-toast--success');
    };

    SpaceGroomers.prototype.emitParticles = function (x, y, color, count, speed) {
        var total = Math.min(count, BALANCE.particleCap);
        while (this.particles.length > BALANCE.particleCap - total) {
            this.particles.shift();
        }

        for (var i = 0; i < total; i++) {
            this.particles.push({
                x: x,
                y: y,
                vx: (Math.random() - 0.5) * (speed || 4),
                vy: (Math.random() - 0.5) * (speed || 4),
                life: 260 + Math.random() * 220,
                maxLife: 420,
                color: color,
                size: 2 + Math.random() * 3
            });
        }
    };

    SpaceGroomers.prototype.spawnFloatingText = function (text, x, y, color, font) {
        while (this.floatingTexts.length >= BALANCE.floatingTextCap) {
            this.floatingTexts.shift();
        }

        this.floatingTexts.push({
            text: text,
            x: x,
            y: y,
            vx: 0,
            vy: -0.35,
            life: 720,
            maxLife: 720,
            color: color || '#fff',
            font: font || '700 14px "Segoe UI", system-ui, sans-serif'
        });
    };

    SpaceGroomers.prototype.shakeScreen = function (force, durationMs) {
        this.screenShakeForce = Math.max(this.screenShakeForce, force);
        this.screenShakeTimer = Math.max(this.screenShakeTimer, durationMs || 100);
    };

    SpaceGroomers.prototype.freezeFor = function (durationMs) {
        this.freezeTimer = Math.max(this.freezeTimer, durationMs || 20);
    };

    SpaceGroomers.prototype.bindEvents = function () {
        var self = this;

        if (!SpaceGroomers._keyboardBound) {
            SpaceGroomers._keyboardBound = true;
            SpaceGroomers._instances = [];

            document.addEventListener('keydown', function (event) {
                SpaceGroomers._instances.forEach(function (instance) {
                    instance.keys[event.key] = true;
                });

                var playing = SpaceGroomers._instances.some(function (instance) {
                    return instance.state === 'playing';
                });

                if (playing && (event.key === ' ' || event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
                    event.preventDefault();
                }
            });

            document.addEventListener('keyup', function (event) {
                SpaceGroomers._instances.forEach(function (instance) {
                    instance.keys[event.key] = false;
                });
            });
        }

        SpaceGroomers._instances.push(this);

        var playButtons = this.container.querySelectorAll('.dps-sg-btn--play');
        for (var i = 0; i < playButtons.length; i++) {
            playButtons[i].addEventListener('click', function () {
                ensureAudio();
                self.start();
            });
        }

        if (this.overlayStart) {
            this.overlayStart.addEventListener('click', function (event) {
                if (event.target.closest('.dps-sg-btn--play')) {
                    return;
                }
                ensureAudio();
                self.start();
            });
        }

        var specialButton = this.container.querySelector('.dps-sg-btn--special');
        var pointerSurface = this.container.querySelector('.dps-sg-wrapper');

        if (specialButton) {
            specialButton.addEventListener('click', function () {
                self.fireSpecial();
            });
        }

        if (pointerSurface) {
            pointerSurface.style.touchAction = 'none';

            var updatePointerX = function (clientX) {
                var rect = self.canvas.getBoundingClientRect();
                if (!rect.width) {
                    return;
                }

                var canvasX = (clientX - rect.left) * (W / rect.width);
                self.player.x = canvasX - self.pointerOffsetX;
                self.player.x = clamp(self.player.x, PLAYER_W / 2 + 4, W - PLAYER_W / 2 - 4);
            };

            pointerSurface.addEventListener('pointerdown', function (event) {
                if (self.state !== 'playing') {
                    return;
                }
                if (self.pointerId !== null && self.pointerId !== event.pointerId) {
                    return;
                }

                self.pointerId = event.pointerId;
                self.mobileDragActive = true;
                self.isPointerDown = true;

                var rect = self.canvas.getBoundingClientRect();
                var currentPlayerX = rect.left + (self.player.x * rect.width / W);
                self.pointerOffsetX = (event.clientX - currentPlayerX) * (W / rect.width);
                updatePointerX(event.clientX);

                if (pointerSurface.setPointerCapture) {
                    try {
                        pointerSurface.setPointerCapture(event.pointerId);
                    } catch (e) {
                        return;
                    }
                }
            }, { passive: true });

            pointerSurface.addEventListener('pointermove', function (event) {
                if (!self.mobileDragActive || self.pointerId !== event.pointerId || self.state !== 'playing') {
                    return;
                }
                updatePointerX(event.clientX);
            }, { passive: true });

            var releasePointer = function (event) {
                if (self.pointerId !== event.pointerId) {
                    return;
                }
                self.isPointerDown = false;
                self.mobileDragActive = false;
                self.pointerId = null;
                self.pointerOffsetX = 0;
            };

            pointerSurface.addEventListener('pointerup', releasePointer, { passive: true });
            pointerSurface.addEventListener('pointercancel', releasePointer, { passive: true });
            pointerSurface.addEventListener('pointerleave', function (event) {
                if (event.pointerType === 'mouse') {
                    return;
                }
                releasePointer(event);
            }, { passive: true });
        }

        if (!SpaceGroomers._focusBound) {
            SpaceGroomers._focusBound = true;

            SpaceGroomers._pauseAllPlaying = function () {
                SpaceGroomers._instances.forEach(function (instance) {
                    if (instance.state === 'playing') {
                        instance.state = 'paused';
                        cancelAnimationFrame(instance.rafId);
                    }
                });
            };

            SpaceGroomers._resumeAllPaused = function () {
                SpaceGroomers._instances.forEach(function (instance) {
                    if (instance.state === 'paused') {
                        instance.state = 'playing';
                        instance.lastTime = performance.now();
                        instance.loop(instance.lastTime);
                    }
                });
            };

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    SpaceGroomers._pauseAllPlaying();
                } else {
                    SpaceGroomers._resumeAllPaused();
                }
            });

            window.addEventListener('blur', function () {
                SpaceGroomers._pauseAllPlaying();
            });

            window.addEventListener('focus', function () {
                if (!document.hidden) {
                    SpaceGroomers._resumeAllPaused();
                }
            });
        }
    };

    SpaceGroomers.prototype.getTextState = function () {
        return {
            coordinateSystem: 'origin top-left, +x right, +y down',
            mode: this.state,
            wave: this.wave,
            score: this.score,
            lives: this.lives,
            comboCount: this.comboCount,
            comboMultiplier: this.comboMultiplier,
            comboTimer: Number(this.comboTimer.toFixed(2)),
            specialCharge: this.specialCharge,
            specialReady: this.specialCharge >= BALANCE.specialCost,
            activePowerup: this.activePowerup,
            powerupTimer: Math.round(this.powerupTimer),
            player: {
                x: Math.round(this.player.x),
                y: Math.round(this.player.y),
                invulnMs: Math.round(this.playerInvulnTimer)
            },
            enemies: this.enemies.map(function (enemy) {
                return {
                    type: enemy.type,
                    x: Math.round(enemy.x),
                    y: Math.round(enemy.y),
                    hp: enemy.hp,
                    pattern: enemy.pattern,
                    telegraph: Number(Math.max(0, enemy.telegraph).toFixed(2))
                };
            }),
            muds: this.muds.map(function (mud) {
                return { x: Math.round(mud.x), y: Math.round(mud.y) };
            }),
            powerups: this.powerups.map(function (powerup) {
                return { type: powerup.type, x: Math.round(powerup.x), y: Math.round(powerup.y) };
            }),
            toast: this.elToast && !this.elToast.classList.contains('dps-sg-toast--hidden') ? {
                title: this.elToastTitle.textContent,
                desc: this.elToastDesc.textContent
            } : null
        };
    };

    function getActiveInstance() {
        if (!SpaceGroomers._instances || !SpaceGroomers._instances.length) {
            return null;
        }

        for (var i = 0; i < SpaceGroomers._instances.length; i++) {
            if (SpaceGroomers._instances[i].state === 'playing' || SpaceGroomers._instances[i].state === 'gameoverTransition') {
                return SpaceGroomers._instances[i];
            }
        }

        return SpaceGroomers._instances[0];
    }

    window.render_game_to_text = function () {
        var instance = getActiveInstance();
        return JSON.stringify(instance ? instance.getTextState() : { mode: 'uninitialized' });
    };

    window.advanceTime = function (ms) {
        var instance = getActiveInstance();
        if (!instance) {
            return;
        }

        var steps = Math.max(1, Math.round(ms / FRAME_TIME));
        var dt = FRAME_TIME / 1000;

        for (var i = 0; i < steps; i++) {
            if (instance.state === 'playing' || instance.state === 'gameoverTransition') {
                if (instance.freezeTimer > 0) {
                    instance.freezeTimer = Math.max(0, instance.freezeTimer - FRAME_TIME);
                    instance.updateAmbient(dt, FRAME_TIME);
                } else {
                    instance.update(dt);
                }
            }
        }

        instance.draw();
        instance.updateHUD();
    };

    function initAll() {
        var containers = document.querySelectorAll('.dps-space-groomers');
        for (var i = 0; i < containers.length; i++) {
            if (!containers[i].dataset.init) {
                containers[i].dataset.init = '1';
                new SpaceGroomers(containers[i]);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
