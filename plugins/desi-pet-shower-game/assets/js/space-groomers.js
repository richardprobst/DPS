/**
 * Space Groomers: Invasão das Pulgas
 * Game engine — Canvas + vanilla JS, zero dependências.
 *
 * @version 1.0.0
 */
(function () {
    'use strict';

    /* ═══════════════════════════════════════════
       CONSTANTS
       ═══════════════════════════════════════════ */
    var W = 480;
    var H = 640;
    var PLAYER_W = 40;
    var PLAYER_H = 28;
    var BULLET_W = 4;
    var BULLET_H = 12;
    var ENEMY_SIZE = 28;
    var POWERUP_SIZE = 22;
    var MUD_SIZE = 6;
    var TOTAL_WAVES = 10;
    var SPECIAL_COST = 500;
    var FPS = 60;
    var FRAME_TIME = 1000 / FPS;

    var ENEMY_TYPES = {
        flea:    { hp: 1, pts: 10, color: '#a0522d', speed: 1, label: 'pulgas' },
        tick:    { hp: 2, pts: 25, color: '#556b2f', speed: 0.6, label: 'carrapatos' },
        furball: { hp: 1, pts: 15, color: '#d2b48c', speed: 1.4, label: 'pelos' }
    };

    var POWERUP_TYPES = {
        shampoo: { icon: '🧴', name: 'Shampoo Turbo', duration: 8000, color: '#4fc3f7' },
        towel:   { icon: '🧹', name: 'Toalha',        duration: 0,    color: '#f7c948' }
    };

    var LS_KEY = 'dps_sg_highscore';

    /* ═══════════════════════════════════════════
       AUDIO (Web Audio API — tiny chiptune SFX)
       ═══════════════════════════════════════════ */
    var audioCtx = null;

    function ensureAudio() {
        if (!audioCtx) {
            try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { /* silent */ }
        }
    }

    function playTone(freq, dur, type, vol) {
        ensureAudio();
        if (!audioCtx) return;
        try {
            var o = audioCtx.createOscillator();
            var g = audioCtx.createGain();
            o.type = type || 'square';
            o.frequency.value = freq;
            g.gain.value = vol || 0.08;
            g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + dur);
            o.connect(g);
            g.connect(audioCtx.destination);
            o.start();
            o.stop(audioCtx.currentTime + dur);
        } catch (e) { /* silent */ }
    }

    function sfxShoot()   { playTone(880, 0.06, 'square', 0.06); }
    function sfxHit()     { playTone(220, 0.10, 'triangle', 0.08); }
    function sfxPowerup() { playTone(660, 0.08, 'sine', 0.07); playTone(990, 0.12, 'sine', 0.07); }
    function sfxLoseLife() { playTone(150, 0.25, 'sawtooth', 0.06); }
    function sfxSpecial() { playTone(440, 0.15, 'sine', 0.08); playTone(880, 0.2, 'sine', 0.06); }

    /* ═══════════════════════════════════════════
       PARTICLES
       ═══════════════════════════════════════════ */
    function createParticles(arr, x, y, color, count) {
        for (var i = 0; i < count; i++) {
            arr.push({
                x: x,
                y: y,
                vx: (Math.random() - 0.5) * 4,
                vy: (Math.random() - 0.5) * 4,
                life: 0.5 + Math.random() * 0.3,
                color: color,
                size: 2 + Math.random() * 3
            });
        }
    }

    function updateParticles(arr, dt) {
        for (var i = arr.length - 1; i >= 0; i--) {
            var p = arr[i];
            p.x += p.vx;
            p.y += p.vy;
            p.life -= dt;
            if (p.life <= 0) arr.splice(i, 1);
        }
    }

    function drawParticles(ctx, arr) {
        for (var i = 0; i < arr.length; i++) {
            var p = arr[i];
            ctx.globalAlpha = Math.max(0, p.life * 2);
            ctx.fillStyle = p.color;
            ctx.fillRect(p.x - p.size / 2, p.y - p.size / 2, p.size, p.size);
        }
        ctx.globalAlpha = 1;
    }

    /* ═══════════════════════════════════════════
       SPRITE DRAWING (pixel-art via canvas)
       ═══════════════════════════════════════════ */
    function drawPlayer(ctx, x, y) {
        // Secador turbo — simple pixel ship
        ctx.fillStyle = '#4fc3f7';
        ctx.fillRect(x - 4, y - 14, 8, 28);    // body
        ctx.fillRect(x - 16, y, 32, 10);        // wings
        ctx.fillRect(x - 20, y + 4, 8, 8);      // left thruster
        ctx.fillRect(x + 12, y + 4, 8, 8);      // right thruster
        ctx.fillStyle = '#e1f5fe';
        ctx.fillRect(x - 2, y - 14, 4, 8);      // nozzle
        ctx.fillStyle = '#0288d1';
        ctx.fillRect(x - 12, y + 2, 24, 4);     // detail
    }

    function drawEnemy(ctx, type, x, y, hp) {
        var et = ENEMY_TYPES[type];
        var s = ENEMY_SIZE;
        var hs = s / 2;

        if (type === 'flea') {
            // Pulga — round body + legs
            ctx.fillStyle = et.color;
            ctx.beginPath();
            ctx.arc(x, y, hs * 0.7, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillRect(x - hs, y + hs * 0.3, 4, 8);
            ctx.fillRect(x + hs - 4, y + hs * 0.3, 4, 8);
            ctx.fillStyle = '#fff';
            ctx.fillRect(x - 4, y - 4, 3, 3);
            ctx.fillRect(x + 2, y - 4, 3, 3);
        } else if (type === 'tick') {
            // Carrapato — larger, darker, armored
            ctx.fillStyle = hp > 1 ? et.color : '#8b4513';
            ctx.fillRect(x - hs, y - hs, s, s);
            ctx.fillStyle = '#3e5902';
            ctx.fillRect(x - hs + 3, y - hs + 3, s - 6, s - 6);
            ctx.fillStyle = '#fff';
            ctx.fillRect(x - 4, y - 3, 3, 3);
            ctx.fillRect(x + 2, y - 3, 3, 3);
            if (hp > 1) {
                ctx.fillStyle = 'rgba(255,255,255,0.3)';
                ctx.fillRect(x - hs + 2, y - hs + 2, s - 4, 3);
            }
        } else {
            // Bolota de pelo — fluffy circle
            ctx.fillStyle = et.color;
            ctx.beginPath();
            ctx.arc(x, y, hs * 0.8, 0, Math.PI * 2);
            ctx.fill();
            // fur strands
            ctx.strokeStyle = '#c4a882';
            ctx.lineWidth = 1;
            for (var i = 0; i < 6; i++) {
                var a = (i / 6) * Math.PI * 2;
                ctx.beginPath();
                ctx.moveTo(x + Math.cos(a) * hs * 0.5, y + Math.sin(a) * hs * 0.5);
                ctx.lineTo(x + Math.cos(a) * hs, y + Math.sin(a) * hs);
                ctx.stroke();
            }
        }
    }

    function drawBullet(ctx, x, y) {
        ctx.fillStyle = '#e1f5fe';
        ctx.fillRect(x - BULLET_W / 2, y - BULLET_H / 2, BULLET_W, BULLET_H);
        ctx.fillStyle = 'rgba(79,195,247,0.4)';
        ctx.fillRect(x - BULLET_W, y, BULLET_W * 2, BULLET_H / 2);
    }

    function drawMud(ctx, x, y) {
        ctx.fillStyle = '#795548';
        ctx.beginPath();
        ctx.arc(x, y, MUD_SIZE, 0, Math.PI * 2);
        ctx.fill();
    }

    function drawPowerup(ctx, type, x, y) {
        var pt = POWERUP_TYPES[type];
        ctx.fillStyle = pt.color;
        ctx.beginPath();
        ctx.arc(x, y, POWERUP_SIZE / 2 + 2, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#fff';
        ctx.font = '14px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(pt.icon, x, y);
    }

    function drawStars(ctx, stars) {
        ctx.fillStyle = 'rgba(255,255,255,0.6)';
        for (var i = 0; i < stars.length; i++) {
            ctx.fillRect(stars[i].x, stars[i].y, stars[i].s, stars[i].s);
        }
    }

    /* ═══════════════════════════════════════════
       GAME CLASS
       ═══════════════════════════════════════════ */
    function SpaceGroomers(container) {
        this.container = container;
        this.canvas = container.querySelector('.dps-sg-canvas');
        this.ctx = this.canvas.getContext('2d');

        // UI refs
        this.elScore = container.querySelector('.dps-sg-score');
        this.elWave = container.querySelector('.dps-sg-wave');
        this.elLives = container.querySelector('.dps-sg-lives');
        this.elCombo = container.querySelector('.dps-sg-combo');
        this.elComboText = container.querySelector('.dps-sg-combo__text');
        this.elPowerup = container.querySelector('.dps-sg-powerup-indicator');
        this.elPowerupIcon = container.querySelector('.dps-sg-powerup-indicator__icon');
        this.elPowerupName = container.querySelector('.dps-sg-powerup-indicator__name');
        this.elPowerupFill = container.querySelector('.dps-sg-powerup-indicator__fill');
        this.elSpecialFill = container.querySelector('.dps-sg-special-bar__fill');
        this.elSpecialBtn = container.querySelector('.dps-sg-btn--special');

        this.overlayStart = container.querySelector('.dps-sg-overlay--start');
        this.overlayGameover = container.querySelector('.dps-sg-overlay--gameover');
        this.overlayVictory = container.querySelector('.dps-sg-overlay--victory');
        this.overlayWave = container.querySelector('.dps-sg-overlay--wave');

        this.state = 'idle'; // idle | playing | paused | gameover | victory | waveIntro
        this.rafId = null;
        this.lastTime = 0;

        this.highscore = parseInt(localStorage.getItem(LS_KEY), 10) || 0;
        this.updateHighscoreDisplay();

        this.bindEvents();
    }

    SpaceGroomers.prototype.reset = function () {
        this.score = 0;
        this.wave = 1;
        this.lives = 3;
        this.comboCount = 0;
        this.comboMultiplier = 1;
        this.comboTimer = 0;
        this.specialCharge = 0;
        this.activePowerup = null;
        this.powerupTimer = 0;
        this.waveTimer = 0;
        this.wavePerfect = true;

        this.stats = { flea: 0, tick: 0, furball: 0 };

        // Player
        this.player = { x: W / 2, y: H - 60, speed: 5 };

        // Collections
        this.bullets = [];
        this.enemies = [];
        this.muds = [];
        this.powerups = [];
        this.particles = [];

        // Stars (background)
        this.stars = [];
        for (var i = 0; i < 60; i++) {
            this.stars.push({
                x: Math.random() * W,
                y: Math.random() * H,
                s: Math.random() < 0.3 ? 2 : 1,
                vy: 0.2 + Math.random() * 0.3
            });
        }

        // Input
        this.keys = {};
        this.shootCooldown = 0;
        this.touchMoving = 0; // -1 left, 0 none, 1 right
        this.touchFiring = false;

        this.enemyDir = 1;
        this.enemyDropTimer = 0;
        this.mudCooldown = 0;
    };

    /* ─── Wave spawning ─── */
    SpaceGroomers.prototype.spawnWave = function () {
        var w = this.wave;
        var cols = Math.min(6 + Math.floor(w / 3), 10);
        var rows = Math.min(2 + Math.floor(w / 2), 5);
        var types = ['flea'];
        if (w >= 2) types.push('furball');
        if (w >= 3) types.push('tick');

        this.enemies = [];
        var startX = (W - cols * 44) / 2 + 22;

        for (var r = 0; r < rows; r++) {
            for (var c = 0; c < cols; c++) {
                var t;
                if (w >= 3 && r === 0 && Math.random() < 0.15 + w * 0.03) {
                    t = 'tick';
                } else if (w >= 2 && Math.random() < 0.25) {
                    t = 'furball';
                } else {
                    t = 'flea';
                }
                var et = ENEMY_TYPES[t];
                this.enemies.push({
                    type: t,
                    x: startX + c * 44,
                    y: 60 + r * 40,
                    hp: et.hp,
                    baseSpeed: et.speed * (1 + w * 0.08)
                });
            }
        }

        this.enemyDir = 1;
        this.mudCooldown = 2;
        this.wavePerfect = true;
    };

    /* ─── Start / Restart ─── */
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
        titleEl.textContent = 'Wave ' + this.wave;
        bonusEl.textContent = '';
        this.overlayWave.classList.remove('dps-sg-overlay--hidden');

        setTimeout(function () {
            self.overlayWave.classList.add('dps-sg-overlay--hidden');
            self.spawnWave();
            self.state = 'playing';
            self.lastTime = performance.now();
            self.loop(self.lastTime);
        }, 1200);
    };

    /* ─── Main Loop ─── */
    SpaceGroomers.prototype.loop = function (now) {
        if (this.state !== 'playing') return;

        var dt = Math.min((now - this.lastTime) / 1000, 0.05);
        this.lastTime = now;

        this.update(dt);
        this.draw();
        this.updateHUD();

        var self = this;
        this.rafId = requestAnimationFrame(function (t) { self.loop(t); });
    };

    /* ─── Update ─── */
    SpaceGroomers.prototype.update = function (dt) {
        var self = this;

        // Stars scroll
        for (var si = 0; si < this.stars.length; si++) {
            this.stars[si].y += this.stars[si].vy;
            if (this.stars[si].y > H) {
                this.stars[si].y = 0;
                this.stars[si].x = Math.random() * W;
            }
        }

        // Player movement
        var moveDir = 0;
        if (this.keys['ArrowLeft'] || this.keys['a'] || this.touchMoving < 0) moveDir = -1;
        if (this.keys['ArrowRight'] || this.keys['d'] || this.touchMoving > 0) moveDir = 1;
        this.player.x += moveDir * this.player.speed;
        this.player.x = Math.max(PLAYER_W / 2 + 4, Math.min(W - PLAYER_W / 2 - 4, this.player.x));

        // Shooting
        this.shootCooldown -= dt;
        if ((this.keys[' '] || this.keys['Space'] || this.touchFiring) && this.shootCooldown <= 0) {
            this.shoot();
            this.shootCooldown = 0.18;
        }

        // Special
        if (this.keys['Shift'] || this.keys['Control']) {
            this.fireSpecial();
        }

        // Bullets
        for (var bi = this.bullets.length - 1; bi >= 0; bi--) {
            this.bullets[bi].y -= 8;
            if (this.bullets[bi].y < -10) {
                this.bullets.splice(bi, 1);
                this.resetCombo();
            }
        }

        // Enemies movement (invaders pattern)
        var minX = W, maxX = 0;
        for (var ei = 0; ei < this.enemies.length; ei++) {
            var e = this.enemies[ei];
            if (e.x < minX) minX = e.x;
            if (e.x > maxX) maxX = e.x;
        }
        var edgeHit = (this.enemyDir > 0 && maxX > W - 30) || (this.enemyDir < 0 && minX < 30);
        if (edgeHit) {
            this.enemyDir *= -1;
            for (var ej = 0; ej < this.enemies.length; ej++) {
                this.enemies[ej].y += 12;
            }
        }
        for (var ek = 0; ek < this.enemies.length; ek++) {
            this.enemies[ek].x += this.enemyDir * this.enemies[ek].baseSpeed;
        }

        // Enemy passed line
        for (var el = this.enemies.length - 1; el >= 0; el--) {
            if (this.enemies[el].y > H - 50) {
                this.enemies.splice(el, 1);
                this.loseLife();
                this.wavePerfect = false;
            }
        }

        // Mud drops
        this.mudCooldown -= dt;
        if (this.mudCooldown <= 0 && this.enemies.length > 0 && this.wave >= 2) {
            var mudInterval = Math.max(0.8, 2.5 - this.wave * 0.15);
            this.mudCooldown = mudInterval;
            var src = this.enemies[Math.floor(Math.random() * this.enemies.length)];
            this.muds.push({ x: src.x, y: src.y + ENEMY_SIZE / 2 });
        }
        for (var mi = this.muds.length - 1; mi >= 0; mi--) {
            this.muds[mi].y += 3;
            if (this.muds[mi].y > H + 10) {
                this.muds.splice(mi, 1);
                continue;
            }
            // Hit player?
            if (Math.abs(this.muds[mi].x - this.player.x) < PLAYER_W / 2 &&
                Math.abs(this.muds[mi].y - this.player.y) < PLAYER_H / 2) {
                createParticles(this.particles, this.muds[mi].x, this.muds[mi].y, '#795548', 6);
                this.muds.splice(mi, 1);
                this.loseLife();
            }
        }

        // Bullet–enemy collision
        for (var bj = this.bullets.length - 1; bj >= 0; bj--) {
            var b = this.bullets[bj];
            for (var em = this.enemies.length - 1; em >= 0; em--) {
                var en = this.enemies[em];
                var hitbox = this.activePowerup === 'shampoo' ? ENEMY_SIZE * 0.8 : ENEMY_SIZE / 2;
                if (Math.abs(b.x - en.x) < hitbox && Math.abs(b.y - en.y) < hitbox) {
                    en.hp--;
                    this.bullets.splice(bj, 1);
                    sfxHit();
                    createParticles(this.particles, en.x, en.y, '#e1f5fe', 5);
                    if (en.hp <= 0) {
                        var pts = ENEMY_TYPES[en.type].pts * this.comboMultiplier;
                        this.score += pts;
                        this.specialCharge = Math.min(SPECIAL_COST, this.specialCharge + pts);
                        this.stats[en.type]++;
                        this.advanceCombo();
                        createParticles(this.particles, en.x, en.y, ENEMY_TYPES[en.type].color, 10);
                        this.enemies.splice(em, 1);
                    }
                    break;
                }
            }
        }

        // Powerups spawning
        if (Math.random() < 0.002 && this.enemies.length > 0) {
            var ptypes = Object.keys(POWERUP_TYPES);
            var ptype = ptypes[Math.floor(Math.random() * ptypes.length)];
            this.powerups.push({
                type: ptype,
                x: 40 + Math.random() * (W - 80),
                y: -20
            });
        }

        // Powerups falling + collection
        for (var pi = this.powerups.length - 1; pi >= 0; pi--) {
            this.powerups[pi].y += 1.5;
            if (this.powerups[pi].y > H + 20) {
                this.powerups.splice(pi, 1);
                continue;
            }
            if (Math.abs(this.powerups[pi].x - this.player.x) < 30 &&
                Math.abs(this.powerups[pi].y - this.player.y) < 30) {
                this.collectPowerup(this.powerups[pi].type);
                this.powerups.splice(pi, 1);
            }
        }

        // Powerup timer
        if (this.activePowerup && this.powerupTimer > 0) {
            this.powerupTimer -= dt * 1000;
            if (this.powerupTimer <= 0) {
                this.activePowerup = null;
                this.powerupTimer = 0;
            }
        }

        // Combo timer
        if (this.comboTimer > 0) {
            this.comboTimer -= dt;
            if (this.comboTimer <= 0) {
                this.comboMultiplier = 1;
                this.comboCount = 0;
            }
        }

        // Particles
        updateParticles(this.particles, dt);

        // Wave complete?
        if (this.enemies.length === 0 && this.state === 'playing') {
            this.endWave();
        }
    };

    /* ─── Shooting ─── */
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

    /* ─── Special ─── */
    SpaceGroomers.prototype.fireSpecial = function () {
        if (this.specialCharge < SPECIAL_COST) return;
        this.specialCharge = 0;
        sfxSpecial();

        // Clear bottom half enemies
        for (var i = this.enemies.length - 1; i >= 0; i--) {
            if (this.enemies[i].y > H / 2) {
                var en = this.enemies[i];
                this.score += ENEMY_TYPES[en.type].pts;
                this.stats[en.type]++;
                createParticles(this.particles, en.x, en.y, '#e1f5fe', 8);
                this.enemies.splice(i, 1);
            }
        }

        // Big foam effect
        for (var j = 0; j < 30; j++) {
            createParticles(this.particles, Math.random() * W, H / 2 + Math.random() * (H / 2), '#e1f5fe', 1);
        }
    };

    /* ─── Power-ups ─── */
    SpaceGroomers.prototype.collectPowerup = function (type) {
        sfxPowerup();
        if (type === 'towel') {
            // Screen clear: remove all enemies in a row (highest Y row)
            var maxY = 0;
            for (var i = 0; i < this.enemies.length; i++) {
                if (this.enemies[i].y > maxY) maxY = this.enemies[i].y;
            }
            for (var j = this.enemies.length - 1; j >= 0; j--) {
                if (Math.abs(this.enemies[j].y - maxY) < 20) {
                    var en = this.enemies[j];
                    this.score += ENEMY_TYPES[en.type].pts;
                    this.stats[en.type]++;
                    createParticles(this.particles, en.x, en.y, '#f7c948', 8);
                    this.enemies.splice(j, 1);
                }
            }
        } else {
            this.activePowerup = type;
            this.powerupTimer = POWERUP_TYPES[type].duration;
        }
    };

    /* ─── Combo ─── */
    SpaceGroomers.prototype.advanceCombo = function () {
        this.comboCount++;
        if (this.comboCount >= 20) {
            this.comboMultiplier = 3;
            this.comboTimer = 5;
        } else if (this.comboCount >= 10) {
            this.comboMultiplier = 2;
            this.comboTimer = 5;
        }
    };

    SpaceGroomers.prototype.resetCombo = function () {
        this.comboCount = 0;
        this.comboMultiplier = 1;
        this.comboTimer = 0;
    };

    /* ─── Lives ─── */
    SpaceGroomers.prototype.loseLife = function () {
        this.lives--;
        sfxLoseLife();
        if (this.lives <= 0) {
            this.gameOver();
        }
    };

    /* ─── Wave end ─── */
    SpaceGroomers.prototype.endWave = function () {
        // Bonus
        if (this.wavePerfect) {
            this.score += 200;
        }

        if (this.wave >= TOTAL_WAVES) {
            this.victory();
            return;
        }

        this.wave++;
        this.state = 'waveIntro';
        cancelAnimationFrame(this.rafId);

        var bonusEl = this.container.querySelector('.dps-sg-wave-bonus');
        bonusEl.textContent = this.wavePerfect ? 'Perfeito! +200' : '';
        this.showWaveIntro();
    };

    /* ─── Game Over ─── */
    SpaceGroomers.prototype.gameOver = function () {
        this.state = 'gameover';
        cancelAnimationFrame(this.rafId);
        this.saveHighscore();

        var el = this.overlayGameover;
        el.querySelector('.dps-sg-final-score').textContent = this.score.toLocaleString();
        el.querySelector('.dps-sg-overlay__stats').textContent =
            this.stats.flea + ' pulgas · ' + this.stats.tick + ' carrapatos · ' + this.stats.furball + ' pelos';
        this.updateHighscoreDisplay();
        el.classList.remove('dps-sg-overlay--hidden');
    };

    /* ─── Victory ─── */
    SpaceGroomers.prototype.victory = function () {
        this.state = 'victory';
        cancelAnimationFrame(this.rafId);
        this.saveHighscore();

        var el = this.overlayVictory;
        el.querySelector('.dps-sg-final-score').textContent = this.score.toLocaleString();
        el.querySelector('.dps-sg-overlay__stats').textContent =
            this.stats.flea + ' pulgas · ' + this.stats.tick + ' carrapatos · ' + this.stats.furball + ' pelos';
        this.updateHighscoreDisplay();
        el.classList.remove('dps-sg-overlay--hidden');
    };

    /* ─── Highscore ─── */
    SpaceGroomers.prototype.saveHighscore = function () {
        if (this.score > this.highscore) {
            this.highscore = this.score;
            try { localStorage.setItem(LS_KEY, String(this.highscore)); } catch (e) { /* quota */ }
        }
    };

    SpaceGroomers.prototype.updateHighscoreDisplay = function () {
        var els = this.container.querySelectorAll('.dps-sg-highscore-value');
        for (var i = 0; i < els.length; i++) {
            els[i].textContent = this.highscore.toLocaleString();
        }
    };

    /* ─── Draw ─── */
    SpaceGroomers.prototype.draw = function () {
        var ctx = this.ctx;
        ctx.clearRect(0, 0, W, H);

        // Background
        ctx.fillStyle = '#0a0e27';
        ctx.fillRect(0, 0, W, H);

        drawStars(ctx, this.stars);

        // Pet-planeta (decorative bottom line)
        ctx.fillStyle = 'rgba(79,195,247,0.08)';
        ctx.fillRect(0, H - 40, W, 40);
        ctx.fillStyle = 'rgba(79,195,247,0.2)';
        ctx.fillRect(0, H - 40, W, 2);

        // Enemies
        for (var i = 0; i < this.enemies.length; i++) {
            var e = this.enemies[i];
            drawEnemy(ctx, e.type, e.x, e.y, e.hp);
        }

        // Bullets
        for (var j = 0; j < this.bullets.length; j++) {
            drawBullet(ctx, this.bullets[j].x, this.bullets[j].y);
        }

        // Muds
        for (var k = 0; k < this.muds.length; k++) {
            drawMud(ctx, this.muds[k].x, this.muds[k].y);
        }

        // Powerups
        for (var l = 0; l < this.powerups.length; l++) {
            drawPowerup(ctx, this.powerups[l].type, this.powerups[l].x, this.powerups[l].y);
        }

        // Player
        drawPlayer(ctx, this.player.x, this.player.y);

        // Particles
        drawParticles(ctx, this.particles);
    };

    /* ─── HUD Update ─── */
    SpaceGroomers.prototype.updateHUD = function () {
        this.elScore.textContent = this.score.toLocaleString();
        this.elWave.textContent = this.wave;

        var hearts = '';
        for (var i = 0; i < this.lives; i++) hearts += '❤️';
        for (var j = this.lives; j < 3; j++) hearts += '🖤';
        this.elLives.textContent = hearts;

        // Combo
        if (this.comboMultiplier > 1) {
            this.elCombo.classList.remove('dps-sg-combo--hidden');
            this.elComboText.textContent = 'x' + this.comboMultiplier;
        } else {
            this.elCombo.classList.add('dps-sg-combo--hidden');
        }

        // Power-up indicator
        if (this.activePowerup) {
            var pt = POWERUP_TYPES[this.activePowerup];
            this.elPowerup.classList.remove('dps-sg-powerup-indicator--hidden');
            this.elPowerupIcon.textContent = pt.icon;
            this.elPowerupName.textContent = pt.name;
            this.elPowerupFill.style.width = (this.powerupTimer / pt.duration * 100) + '%';
        } else {
            this.elPowerup.classList.add('dps-sg-powerup-indicator--hidden');
        }

        // Special bar
        var pct = Math.min(100, (this.specialCharge / SPECIAL_COST) * 100);
        this.elSpecialFill.style.width = pct + '%';
        if (this.elSpecialBtn) {
            this.elSpecialBtn.disabled = this.specialCharge < SPECIAL_COST;
        }
    };

    /* ─── Overlays ─── */
    SpaceGroomers.prototype.hideAllOverlays = function () {
        this.overlayStart.classList.add('dps-sg-overlay--hidden');
        this.overlayGameover.classList.add('dps-sg-overlay--hidden');
        this.overlayVictory.classList.add('dps-sg-overlay--hidden');
        this.overlayWave.classList.add('dps-sg-overlay--hidden');
    };

    /* ─── Input Binding ─── */
    SpaceGroomers.prototype.bindEvents = function () {
        var self = this;

        // Keyboard
        document.addEventListener('keydown', function (e) {
            self.keys[e.key] = true;
            if (e.key === ' ' && self.state === 'playing') {
                e.preventDefault();
            }
        });
        document.addEventListener('keyup', function (e) {
            self.keys[e.key] = false;
        });

        // Play buttons
        var playBtns = this.container.querySelectorAll('.dps-sg-btn--play');
        for (var i = 0; i < playBtns.length; i++) {
            playBtns[i].addEventListener('click', function () {
                ensureAudio();
                self.start();
            });
        }

        // Mobile controls
        var btnLeft = this.container.querySelector('.dps-sg-btn--left');
        var btnRight = this.container.querySelector('.dps-sg-btn--right');
        var btnFire = this.container.querySelector('.dps-sg-btn--fire');
        var btnSpecial = this.container.querySelector('.dps-sg-btn--special');

        if (btnLeft) {
            btnLeft.addEventListener('touchstart', function (e) { e.preventDefault(); self.touchMoving = -1; });
            btnLeft.addEventListener('touchend', function () { self.touchMoving = 0; });
            btnLeft.addEventListener('touchcancel', function () { self.touchMoving = 0; });
        }
        if (btnRight) {
            btnRight.addEventListener('touchstart', function (e) { e.preventDefault(); self.touchMoving = 1; });
            btnRight.addEventListener('touchend', function () { self.touchMoving = 0; });
            btnRight.addEventListener('touchcancel', function () { self.touchMoving = 0; });
        }
        if (btnFire) {
            btnFire.addEventListener('touchstart', function (e) { e.preventDefault(); self.touchFiring = true; });
            btnFire.addEventListener('touchend', function () { self.touchFiring = false; });
            btnFire.addEventListener('touchcancel', function () { self.touchFiring = false; });
        }
        if (btnSpecial) {
            btnSpecial.addEventListener('click', function () { self.fireSpecial(); });
        }
    };

    /* ═══════════════════════════════════════════
       AUTO-INIT: find all game containers
       ═══════════════════════════════════════════ */
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
