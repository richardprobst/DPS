/**
 * Space Groomers: Invasao das Pulgas
 * Game engine - Canvas + vanilla JS, zero dependencias.
 *
 * @version 1.4.0
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
    var LEGACY_HIGHSCORE_KEY = 'dps_sg_highscore';
    var PROGRESS_KEY = 'dps_sg_progress_v1';
    var PROGRESS_VERSION = 1;
    var RUN_HISTORY_LIMIT = 8;

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

    var MISSION_POOL = [
        {
            id: 'survive_60',
            kind: 'survive_seconds',
            target: 60,
            icon: '\u23F1',
            title: 'Sobreviva 60s',
            hint: 'Jogue no seguro e preserve vidas.'
        },
        {
            id: 'collect_3_powerups',
            kind: 'collect_powerups',
            target: 3,
            icon: '\uD83E\uDDF4',
            title: 'Colete 3 power-ups',
            hint: 'Shampoo e toalha contam para a meta.'
        },
        {
            id: 'combo_9',
            kind: 'reach_combo',
            target: 9,
            icon: '\uD83D\uDD25',
            title: 'Atinga combo 9',
            hint: 'Mantenha a sequencia sem deixar tiro escapar.'
        },
        {
            id: 'defeat_6_ticks',
            kind: 'defeat_ticks',
            target: 6,
            icon: '\uD83D\uDEE1',
            title: 'Derrote 6 carrapatos',
            hint: 'Carrapatos valem mais e contam para a missao.'
        }
    ];

    var BADGE_POOL = [
        {
            id: 'first_run',
            icon: '\u2728',
            name: 'Primeiro Banho',
            desc: 'Concluiu a primeira run.',
            check: function (state) {
                return state.totals.runs >= 1;
            }
        },
        {
            id: 'combo_keeper',
            icon: '\uD83D\uDD25',
            name: 'Ritmo de Tesoura',
            desc: 'Atingiu combo 9 em alguma partida.',
            check: function (state) {
                return state.records.bestCombo >= 9;
            }
        },
        {
            id: 'mission_regular',
            icon: '\uD83C\uDFC5',
            name: 'Missao em Dia',
            desc: 'Completou 3 missoes diarias.',
            check: function (state) {
                return state.totals.totalMissionCompletions >= 3;
            }
        },
        {
            id: 'streak_3',
            icon: '\uD83D\uDCC5',
            name: 'Retorno em Serie',
            desc: 'Manteve streak de 3 dias.',
            check: function (state) {
                return state.streak.best >= 3;
            }
        },
        {
            id: 'first_victory',
            icon: '\uD83D\uDEBF',
            name: 'Banho Completo',
            desc: 'Venceu uma run completa.',
            check: function (state) {
                return state.totals.wins >= 1;
            }
        }
    ];

    function toNumber(value, fallback) {
        var parsed = Number(value);
        return isFinite(parsed) ? parsed : fallback;
    }

    function pad2(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function dateKeyFromDate(date) {
        var source = date || new Date();
        return source.getFullYear() + '-' + pad2(source.getMonth() + 1) + '-' + pad2(source.getDate());
    }

    function dayNumberFromDateKey(dateKey) {
        if (!dateKey || typeof dateKey !== 'string') {
            return 0;
        }

        var parts = dateKey.split('-');
        if (parts.length !== 3) {
            return 0;
        }

        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) - 1;
        var d = parseInt(parts[2], 10);
        if (!isFinite(y) || !isFinite(m) || !isFinite(d)) {
            return 0;
        }

        return Math.floor(new Date(y, m, d).getTime() / 86400000);
    }

    function createDefaultProgressState() {
        return {
            version: PROGRESS_VERSION,
            highscore: 0,
            totals: {
                runs: 0,
                wins: 0,
                totalScore: 0,
                totalPlayMs: 0,
                totalPowerups: 0,
                totalMissionCompletions: 0
            },
            records: {
                bestCombo: 0,
                longestRunSec: 0,
                bestWave: 1
            },
            streak: {
                current: 0,
                best: 0,
                lastDateKey: ''
            },
            mission: {
                dateKey: '',
                missionId: '',
                progress: 0,
                completed: false,
                completedAt: ''
            },
            badges: {},
            history: [],
            rewardMarkers: {},
            lastSyncedAt: ''
        };
    }

    function getSpaceGroomersConfig() {
        if (typeof window === 'undefined' || !window.dpsSpaceGroomersConfig || typeof window.dpsSpaceGroomersConfig !== 'object') {
            return {};
        }

        return window.dpsSpaceGroomersConfig;
    }

    function cloneProgressState(state) {
        try {
            return JSON.parse(JSON.stringify(state || createDefaultProgressState()));
        } catch (e) {
            return createDefaultProgressState();
        }
    }

    function hasMeaningfulProgress(state) {
        if (!state || typeof state !== 'object') {
            return false;
        }

        return !!(
            (state.highscore && state.highscore > 0) ||
            (state.totals && state.totals.runs > 0) ||
            (state.history && state.history.length > 0)
        );
    }

    function LocalProgressAdapter() {
        this.progressKey = PROGRESS_KEY;
        this.legacyHighscoreKey = LEGACY_HIGHSCORE_KEY;
        this.storageEnabled = false;

        try {
            this.storageEnabled = typeof window !== 'undefined' && !!window.localStorage;
        } catch (e) {
            this.storageEnabled = false;
        }
    }

    LocalProgressAdapter.prototype.loadState = function () {
        if (!this.storageEnabled) {
            return null;
        }

        try {
            return window.localStorage.getItem(this.progressKey);
        } catch (e) {
            return null;
        }
    };

    LocalProgressAdapter.prototype.saveState = function (state) {
        if (!this.storageEnabled) {
            return;
        }

        try {
            window.localStorage.setItem(this.progressKey, JSON.stringify(state));
        } catch (e) {
            return;
        }
    };

    LocalProgressAdapter.prototype.loadLegacyHighscore = function () {
        if (!this.storageEnabled) {
            return 0;
        }

        try {
            return Math.max(0, parseInt(window.localStorage.getItem(this.legacyHighscoreKey), 10) || 0);
        } catch (e) {
            return 0;
        }
    };

    LocalProgressAdapter.prototype.syncLegacyHighscore = function (highscore) {
        if (!this.storageEnabled) {
            return;
        }

        try {
            window.localStorage.setItem(this.legacyHighscoreKey, String(Math.max(0, Math.floor(toNumber(highscore, 0)))));
        } catch (e) {
            return;
        }
    };

    function RemoteProgressAdapter(config, localAdapter) {
        this.config = config || {};
        this.localAdapter = localAdapter;
        this.enabled = !!(
            this.config &&
            this.config.syncEnabled &&
            this.config.endpoints &&
            this.config.endpoints.progress &&
            this.config.endpoints.sync &&
            this.config.nonce
        );
    }

    RemoteProgressAdapter.prototype.request = function (url, options) {
        if (!this.enabled || !url || typeof fetch !== 'function') {
            return Promise.resolve(null);
        }

        var requestOptions = options || {};
        var headers = requestOptions.headers || {};
        headers['Accept'] = 'application/json';
        headers['X-DPS-Game-Nonce'] = this.config.nonce;
        requestOptions.headers = headers;
        requestOptions.credentials = 'same-origin';

        return fetch(url, requestOptions).then(function (response) {
            if (!response.ok) {
                throw new Error('dps_sg_remote_request_failed');
            }
            return response.json();
        });
    };

    RemoteProgressAdapter.prototype.fetchProgress = function () {
        return this.request(this.config.endpoints.progress, {
            method: 'GET'
        });
    };

    RemoteProgressAdapter.prototype.syncState = function (state, meta) {
        var payload = {
            progress: state
        };

        if (meta && meta.telemetry && typeof meta.telemetry === 'object') {
            payload.telemetry = meta.telemetry;
        }

        return this.request(this.config.endpoints.sync, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
    };

    RemoteProgressAdapter.prototype.bootstrapState = function (localState) {
        var self = this;
        if (!this.enabled) {
            return Promise.resolve(null);
        }

        return this.fetchProgress().then(function (response) {
            var remoteState = response && response.progress ? response.progress : null;

            if (!remoteState) {
                if (hasMeaningfulProgress(localState)) {
                    return self.syncState(localState);
                }
                return response;
            }

            if (
                hasMeaningfulProgress(localState) &&
                localState &&
                localState.totals && remoteState.totals && (
                    localState.totals.runs > remoteState.totals.runs ||
                    localState.highscore > remoteState.highscore ||
                    localState.totals.totalMissionCompletions > remoteState.totals.totalMissionCompletions
                )
            ) {
                return self.syncState(localState);
            }

            return response;
        });
    };

    function ProgressAdapterFactory(config) {
        var local = new LocalProgressAdapter(config);
        return {
            local: local,
            remote: new RemoteProgressAdapter(config, local)
        };
    }

    function SGProgression(options) {
        this.options = options || {};
        this.onStateChange = typeof this.options.onStateChange === 'function' ? this.options.onStateChange : function () {};
        this.onSyncResult = typeof this.options.onSyncResult === 'function' ? this.options.onSyncResult : function () {};
        this.config = getSpaceGroomersConfig();

        var adapters = ProgressAdapterFactory(this.config);
        this.localAdapter = adapters.local;
        this.remoteAdapter = adapters.remote;
        this.storageEnabled = this.localAdapter.storageEnabled;

        this.state = this.loadState();
        this.ensureDailyMission();
        this.saveState({
            skipRemoteSync: true,
            silent: true
        });
        this.bootstrapRemoteState();
    }

    SGProgression.prototype.loadState = function () {
        var fallbackState = createDefaultProgressState();
        var rawState = this.localAdapter.loadState();

        if (!rawState) {
            fallbackState.highscore = this.localAdapter.loadLegacyHighscore();
            return fallbackState;
        }

        try {
            return this.normalizeState(JSON.parse(rawState));
        } catch (e) {
            fallbackState.highscore = this.localAdapter.loadLegacyHighscore();
            return fallbackState;
        }
    };

    SGProgression.prototype.normalizeState = function (raw) {
        var state = createDefaultProgressState();
        if (!raw || typeof raw !== 'object') {
            state.highscore = this.localAdapter.loadLegacyHighscore();
            return state;
        }

        state.highscore = Math.max(0, Math.floor(toNumber(raw.highscore, this.localAdapter.loadLegacyHighscore())));

        if (raw.totals && typeof raw.totals === 'object') {
            state.totals.runs = Math.max(0, Math.floor(toNumber(raw.totals.runs, 0)));
            state.totals.wins = Math.max(0, Math.floor(toNumber(raw.totals.wins, 0)));
            state.totals.totalScore = Math.max(0, Math.floor(toNumber(raw.totals.totalScore, 0)));
            state.totals.totalPlayMs = Math.max(0, Math.floor(toNumber(raw.totals.totalPlayMs, 0)));
            state.totals.totalPowerups = Math.max(0, Math.floor(toNumber(raw.totals.totalPowerups, 0)));
            state.totals.totalMissionCompletions = Math.max(0, Math.floor(toNumber(raw.totals.totalMissionCompletions, 0)));
        }

        if (raw.records && typeof raw.records === 'object') {
            state.records.bestCombo = Math.max(0, Math.floor(toNumber(raw.records.bestCombo, 0)));
            state.records.longestRunSec = Math.max(0, Math.floor(toNumber(raw.records.longestRunSec, 0)));
            state.records.bestWave = Math.max(1, Math.floor(toNumber(raw.records.bestWave, 1)));
        }

        if (raw.streak && typeof raw.streak === 'object') {
            state.streak.current = Math.max(0, Math.floor(toNumber(raw.streak.current, 0)));
            state.streak.best = Math.max(0, Math.floor(toNumber(raw.streak.best, 0)));
            state.streak.lastDateKey = typeof raw.streak.lastDateKey === 'string' ? raw.streak.lastDateKey : '';
        }

        if (raw.mission && typeof raw.mission === 'object') {
            state.mission.dateKey = typeof raw.mission.dateKey === 'string' ? raw.mission.dateKey : '';
            state.mission.missionId = typeof raw.mission.missionId === 'string' ? raw.mission.missionId : '';
            state.mission.progress = Math.max(0, Math.floor(toNumber(raw.mission.progress, 0)));
            state.mission.completed = !!raw.mission.completed;
            state.mission.completedAt = typeof raw.mission.completedAt === 'string' ? raw.mission.completedAt : '';
        }

        if (raw.badges && typeof raw.badges === 'object') {
            state.badges = raw.badges;
        }

        if (Array.isArray(raw.history)) {
            state.history = raw.history.slice(0, RUN_HISTORY_LIMIT);
        }

        if (raw.rewardMarkers && typeof raw.rewardMarkers === 'object') {
            state.rewardMarkers = raw.rewardMarkers;
        }

        if (typeof raw.lastSyncedAt === 'string') {
            state.lastSyncedAt = raw.lastSyncedAt;
        }

        return state;
    };

    SGProgression.prototype.applyRemoteResponse = function (response) {
        if (!response || !response.progress) {
            return null;
        }

        this.state = this.normalizeState(response.progress);
        this.ensureDailyMission();
        this.localAdapter.saveState(this.state);
        this.localAdapter.syncLegacyHighscore(this.state.highscore);
        this.onStateChange(this.getSnapshot(), response);

        return response;
    };

    SGProgression.prototype.bootstrapRemoteState = function () {
        var self = this;
        if (!this.remoteAdapter.enabled) {
            return Promise.resolve(null);
        }

        return this.remoteAdapter.bootstrapState(this.getSyncPayload()).then(function (response) {
            if (response) {
                self.applyRemoteResponse(response);
                self.onSyncResult({
                    ok: true,
                    phase: 'bootstrap',
                    response: response,
                    summary: response.summary || null,
                    rewards: response.awardedRewards || []
                });
            }
            return response;
        }).catch(function (error) {
            self.onSyncResult({
                ok: false,
                phase: 'bootstrap',
                error: error
            });
            return null;
        });
    };

    SGProgression.prototype.syncRemoteState = function (meta) {
        var self = this;
        var syncMeta = meta || {};
        if (!this.remoteAdapter.enabled) {
            return Promise.resolve(null);
        }

        return this.remoteAdapter.syncState(this.getSyncPayload(), syncMeta).then(function (response) {
            self.applyRemoteResponse(response);
            self.onSyncResult({
                ok: true,
                phase: syncMeta.phase ? syncMeta.phase : 'sync',
                response: response,
                summary: response && response.summary ? response.summary : null,
                rewards: response && response.awardedRewards ? response.awardedRewards : []
            });
            return response;
        }).catch(function (error) {
            self.onSyncResult({
                ok: false,
                phase: syncMeta.phase ? syncMeta.phase : 'sync',
                error: error
            });
            return null;
        });
    };

    SGProgression.prototype.saveState = function (options) {
        var opts = options || {};
        this.localAdapter.saveState(this.state);
        this.localAdapter.syncLegacyHighscore(this.state.highscore);

        if (!opts.silent) {
            this.onStateChange(this.getSnapshot(), null);
        }

        if (opts.skipRemoteSync) {
            return Promise.resolve(null);
        }

        return this.syncRemoteState({
            phase: opts.phase || 'save',
            telemetry: opts.telemetry || null
        });
    };

    SGProgression.prototype.getMissionById = function (missionId) {
        for (var i = 0; i < MISSION_POOL.length; i++) {
            if (MISSION_POOL[i].id === missionId) {
                return MISSION_POOL[i];
            }
        }
        return MISSION_POOL[0];
    };

    SGProgression.prototype.getMissionForDate = function (dateKey) {
        var dayIndex = Math.abs(dayNumberFromDateKey(dateKey));
        return MISSION_POOL[dayIndex % MISSION_POOL.length];
    };

    SGProgression.prototype.ensureDailyMission = function () {
        var todayKey = dateKeyFromDate(new Date());
        var activeMission = this.getMissionForDate(todayKey);

        if (this.state.mission.dateKey !== todayKey || this.state.mission.missionId !== activeMission.id) {
            this.state.mission.dateKey = todayKey;
            this.state.mission.missionId = activeMission.id;
            this.state.mission.progress = 0;
            this.state.mission.completed = false;
            this.state.mission.completedAt = '';
        }
    };

    SGProgression.prototype.getMissionProgressFromRun = function (mission, baseProgress, runSummary) {
        var progress = baseProgress;
        if (!runSummary) {
            return progress;
        }

        if (mission.kind === 'survive_seconds') {
            progress = Math.max(progress, Math.floor(toNumber(runSummary.durationSec, 0)));
        } else if (mission.kind === 'collect_powerups') {
            progress += Math.floor(toNumber(runSummary.powerupsCollected, 0));
        } else if (mission.kind === 'reach_combo') {
            progress = Math.max(progress, Math.floor(toNumber(runSummary.bestCombo, 0)));
        } else if (mission.kind === 'defeat_ticks') {
            progress += Math.floor(toNumber(runSummary.tickKills, 0));
        }

        return Math.min(mission.target, Math.max(0, progress));
    };

    SGProgression.prototype.getMissionPreview = function (runSummary) {
        this.ensureDailyMission();

        var missionDef = this.getMissionById(this.state.mission.missionId);
        var baseProgress = this.state.mission.completed ? missionDef.target : this.state.mission.progress;
        var previewProgress = this.state.mission.completed ? missionDef.target : this.getMissionProgressFromRun(missionDef, baseProgress, runSummary);
        var completed = this.state.mission.completed || previewProgress >= missionDef.target;

        return {
            id: missionDef.id,
            kind: missionDef.kind,
            icon: missionDef.icon,
            title: missionDef.title,
            hint: missionDef.hint,
            target: missionDef.target,
            progress: Math.min(missionDef.target, previewProgress),
            completed: completed,
            remaining: completed ? 0 : Math.max(0, missionDef.target - previewProgress),
            dateKey: this.state.mission.dateKey
        };
    };

    SGProgression.prototype.updateStreak = function (todayKey) {
        var lastDateKey = this.state.streak.lastDateKey;

        if (!lastDateKey) {
            this.state.streak.current = 1;
        } else {
            var delta = dayNumberFromDateKey(todayKey) - dayNumberFromDateKey(lastDateKey);
            if (delta <= 0) {
                this.state.streak.current = Math.max(1, this.state.streak.current);
            } else if (delta === 1) {
                this.state.streak.current += 1;
            } else {
                this.state.streak.current = 1;
            }
        }

        this.state.streak.lastDateKey = todayKey;
        this.state.streak.best = Math.max(this.state.streak.best, this.state.streak.current);
    };

    SGProgression.prototype.getUnlockedBadges = function () {
        var badges = [];

        for (var i = 0; i < BADGE_POOL.length; i++) {
            var badgeDef = BADGE_POOL[i];
            if (this.state.badges[badgeDef.id]) {
                badges.push({
                    id: badgeDef.id,
                    icon: badgeDef.icon,
                    name: badgeDef.name,
                    desc: badgeDef.desc,
                    unlockedAt: this.state.badges[badgeDef.id].unlockedAt
                });
            }
        }

        badges.sort(function (a, b) {
            return String(b.unlockedAt).localeCompare(String(a.unlockedAt));
        });

        return badges;
    };

    SGProgression.prototype.unlockEligibleBadges = function () {
        var unlocked = [];

        for (var i = 0; i < BADGE_POOL.length; i++) {
            var badgeDef = BADGE_POOL[i];
            if (this.state.badges[badgeDef.id]) {
                continue;
            }

            if (badgeDef.check(this.state)) {
                var timestamp = new Date().toISOString();
                this.state.badges[badgeDef.id] = {
                    unlockedAt: timestamp
                };
                unlocked.push({
                    id: badgeDef.id,
                    icon: badgeDef.icon,
                    name: badgeDef.name,
                    desc: badgeDef.desc,
                    unlockedAt: timestamp
                });
            }
        }

        return unlocked;
    };

    SGProgression.prototype.getSnapshot = function () {
        var missionStatus = this.getMissionPreview(null);

        return {
            highscore: this.state.highscore,
            totals: {
                runs: this.state.totals.runs,
                wins: this.state.totals.wins,
                totalScore: this.state.totals.totalScore,
                totalPlayMs: this.state.totals.totalPlayMs,
                totalPowerups: this.state.totals.totalPowerups,
                totalMissionCompletions: this.state.totals.totalMissionCompletions
            },
            records: {
                bestCombo: this.state.records.bestCombo,
                longestRunSec: this.state.records.longestRunSec,
                bestWave: this.state.records.bestWave
            },
            streak: {
                current: this.state.streak.current,
                best: this.state.streak.best,
                lastDateKey: this.state.streak.lastDateKey
            },
            mission: missionStatus,
            badges: this.getUnlockedBadges(),
            history: this.state.history.slice(0),
            lastSyncedAt: this.state.lastSyncedAt
        };
    };

    SGProgression.prototype.getSyncPayload = function () {
        return cloneProgressState(this.state);
    };

    SGProgression.prototype.registerRun = function (runSummary) {
        this.ensureDailyMission();

        var todayKey = dateKeyFromDate(new Date());
        var missionBefore = this.getMissionPreview(null);

        this.updateStreak(todayKey);

        this.state.totals.runs += 1;
        if (runSummary.result === 'victory') {
            this.state.totals.wins += 1;
        }

        this.state.totals.totalScore += Math.max(0, Math.floor(toNumber(runSummary.score, 0)));
        this.state.totals.totalPlayMs += Math.max(0, Math.floor(toNumber(runSummary.durationSec, 0) * 1000));
        this.state.totals.totalPowerups += Math.max(0, Math.floor(toNumber(runSummary.powerupsCollected, 0)));

        this.state.records.bestCombo = Math.max(this.state.records.bestCombo, Math.floor(toNumber(runSummary.bestCombo, 0)));
        this.state.records.longestRunSec = Math.max(this.state.records.longestRunSec, Math.floor(toNumber(runSummary.durationSec, 0)));
        this.state.records.bestWave = Math.max(this.state.records.bestWave, Math.floor(toNumber(runSummary.waveReached, 1)));

        var runScore = Math.max(0, Math.floor(toNumber(runSummary.score, 0)));
        this.state.highscore = Math.max(this.state.highscore, runScore);

        var missionAfterPreview = this.getMissionPreview(runSummary);
        this.state.mission.progress = missionAfterPreview.progress;

        var missionJustCompleted = false;
        if (!this.state.mission.completed && missionAfterPreview.completed) {
            missionJustCompleted = true;
            this.state.mission.completed = true;
            this.state.mission.completedAt = new Date().toISOString();
            this.state.totals.totalMissionCompletions += 1;
        }

        this.state.history.unshift({
            dateKey: todayKey,
            score: runScore,
            result: runSummary.result,
            durationSec: Math.floor(toNumber(runSummary.durationSec, 0)),
            bestCombo: Math.floor(toNumber(runSummary.bestCombo, 0)),
            powerupsCollected: Math.floor(toNumber(runSummary.powerupsCollected, 0)),
            tickKills: Math.floor(toNumber(runSummary.tickKills, 0)),
            waveReached: Math.floor(toNumber(runSummary.waveReached, 1)),
            timestamp: new Date().toISOString()
        });

        if (this.state.history.length > RUN_HISTORY_LIMIT) {
            this.state.history = this.state.history.slice(0, RUN_HISTORY_LIMIT);
        }

        var unlockedBadges = this.unlockEligibleBadges();

        return {
            mission: {
                before: missionBefore,
                after: this.getMissionPreview(null),
                justCompleted: missionJustCompleted
            },
            unlockedBadges: unlockedBadges,
            snapshot: this.getSnapshot()
        };
    };

    SGProgression.prototype.getIntegrationPayload = function () {
        var snapshot = this.getSnapshot();

        return {
            schemaVersion: PROGRESS_VERSION,
            exportedAt: new Date().toISOString(),
            highscore: snapshot.highscore,
            mission: snapshot.mission,
            streak: snapshot.streak,
            records: snapshot.records,
            totals: snapshot.totals,
            badges: snapshot.badges,
            recentRuns: snapshot.history.slice(0, 5),
            lastSyncedAt: snapshot.lastSyncedAt,
            syncEnabled: !!this.remoteAdapter.enabled
        };
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
        this.elPauseBtn = container.querySelector('.dps-sg-btn--pause');
        this.elGoal = container.querySelector('.dps-sg-goal');
        this.elGoalTitle = container.querySelector('.dps-sg-goal__title');
        this.elGoalProgress = container.querySelector('.dps-sg-goal__progress');
        this.elGoalFill = container.querySelector('.dps-sg-goal__fill');
        this.elGoalRemaining = container.querySelector('.dps-sg-goal__remaining');
        this.elStatusLive = container.querySelector('.dps-sg-status-live');

        this.overlayStart = container.querySelector('.dps-sg-overlay--start');
        this.overlayGameover = container.querySelector('.dps-sg-overlay--gameover');
        this.overlayVictory = container.querySelector('.dps-sg-overlay--victory');
        this.overlayWave = container.querySelector('.dps-sg-overlay--wave');
        this.overlayPause = container.querySelector('.dps-sg-overlay--pause');
        this.elPauseReason = container.querySelector('.dps-sg-overlay__pause-reason');
        this.elPauseStats = container.querySelector('.dps-sg-overlay__pause-stats');
        this.elPauseResume = container.querySelector('.dps-sg-btn--resume');
        this.elPauseRetry = container.querySelector('.dps-sg-btn--retry');

        this.elStartStreak = container.querySelector('.dps-sg-start-streak-value');
        this.elStartMissionTitle = container.querySelector('.dps-sg-start-meta__mission-title');
        this.elStartMissionProgress = container.querySelector('.dps-sg-start-meta__mission-progress');
        this.elStartBadges = container.querySelector('.dps-sg-start-meta__badges');
        this.elStartStatus = container.querySelector('.dps-sg-start-meta__status');

        this.state = 'idle';
        this.rafId = null;
        this.lastTime = 0;
        this.waveTimeout = null;
        this.waveIntroDeadline = 0;
        this.waveIntroRemainingMs = BALANCE.waveIntroMs;
        this.pauseState = null;
        this.runSession = null;
        this.lastCompletedTelemetry = null;
        this.gameConfig = getSpaceGroomersConfig();

        var self = this;
        this.progression = new SGProgression({
            onStateChange: function (snapshot) {
                self.progressSnapshot = snapshot;
                self.highscore = snapshot.highscore;
                self.updateHighscoreDisplay();
                self.updateMetaUI();
            },
            onSyncResult: function (result) {
                self.handleSyncResult(result);
            }
        });
        this.progressSnapshot = this.progression.getSnapshot();
        this.lastRunMeta = null;
        this.highscore = this.progressSnapshot.highscore;

        this.updateHighscoreDisplay();
        this.bindEvents();
        this.reset();
        this.draw();
        this.updateHUD();
        this.updateMetaUI();
        this.emitTelemetry('game_loaded', {
            badgesUnlocked: this.progressSnapshot.badges.length,
            missionId: this.progressSnapshot.mission.id
        });
    }

    SpaceGroomers.prototype.handleSyncResult = function (result) {
        if (!result) {
            return;
        }

        if (result.ok && result.summary && typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
            window.dispatchEvent(new CustomEvent('dps-space-groomers-progress', {
                detail: {
                    summary: result.summary,
                    response: result.response || null,
                    rewards: result.rewards || []
                }
            }));
        }

        if (!result.ok && result.phase === 'run_complete') {
            this.emitTelemetry('sync_error', {
                phase: result.phase
            });
            this.showToast('Sync local ativo', (this.gameConfig.i18n && this.gameConfig.i18n.syncError) || 'Nao foi possivel sincronizar agora. O progresso local segue ativo.', 'warning', 1800);
            return;
        }

        if (result.ok && result.phase === 'run_complete') {
            this.emitTelemetry('sync_success', {
                phase: result.phase,
                rewardsCount: result.rewards ? result.rewards.length : 0
            });
        }

        if (result.ok && result.phase === 'run_complete' && result.rewards && result.rewards.length) {
            var points = 0;
            for (var i = 0; i < result.rewards.length; i++) {
                points += Math.max(0, Math.floor(toNumber(result.rewards[i].points, 0)));
            }

            if (points > 0) {
                this.showToast('Portal atualizado', '+' + points + ' pts no loyalty', 'success', 2000);
            }
        }
    };

    SpaceGroomers.prototype.announceStatus = function (message) {
        if (!this.elStatusLive) {
            return;
        }

        this.elStatusLive.textContent = '';
        if (!message) {
            return;
        }

        this.elStatusLive.textContent = message;
    };

    SpaceGroomers.prototype.getProgressMode = function () {
        if (this.progression && this.progression.remoteAdapter && this.progression.remoteAdapter.enabled) {
            return 'portal_sync';
        }

        if (this.progression && this.progression.storageEnabled) {
            return 'local_storage';
        }

        return 'volatile';
    };

    SpaceGroomers.prototype.emitTelemetry = function (eventName, payload) {
        if (typeof window === 'undefined' || typeof window.dispatchEvent !== 'function') {
            return;
        }

        var detail = {
            event: eventName,
            context: this.container.dataset.context || 'shortcode',
            clientId: Math.max(0, Math.floor(toNumber(this.gameConfig.clientId, 0))),
            syncEnabled: !!(this.progression && this.progression.remoteAdapter && this.progression.remoteAdapter.enabled),
            storageEnabled: !!(this.progression && this.progression.storageEnabled),
            progressMode: this.getProgressMode(),
            timestamp: new Date().toISOString(),
            sessionId: this.runSession ? this.runSession.sessionId : ''
        };

        if (payload && typeof payload === 'object') {
            for (var key in payload) {
                if (Object.prototype.hasOwnProperty.call(payload, key)) {
                    detail[key] = payload[key];
                }
            }
        }

        window.dispatchEvent(new CustomEvent('dps-space-groomers-telemetry', {
            detail: detail
        }));
        window.dispatchEvent(new CustomEvent('dps-space-groomers-' + eventName, {
            detail: detail
        }));
    };

    SpaceGroomers.prototype.beginRunSession = function (trigger, previousState) {
        var mission = this.progression.getMissionPreview(null);

        this.runSession = {
            sessionId: 'sg_' + Date.now() + '_' + Math.floor(Math.random() * 100000),
            startedAt: new Date().toISOString(),
            trigger: trigger || 'start',
            previousState: previousState || 'idle',
            retry: trigger === 'retry',
            pauseCount: 0,
            pauseReasons: {}
        };

        this.emitTelemetry('game_start', {
            trigger: this.runSession.trigger,
            previousState: this.runSession.previousState,
            retry: this.runSession.retry,
            missionId: mission.id,
            missionProgress: mission.progress
        });
    };

    SpaceGroomers.prototype.getPauseMessage = function (reason) {
        var i18n = this.gameConfig.i18n || {};

        if (reason === 'hidden') {
            return i18n.pauseHidden || 'A partida foi pausada porque a aba ficou em segundo plano.';
        }
        if (reason === 'blur') {
            return i18n.pauseBlur || 'A partida foi pausada porque a janela perdeu foco.';
        }
        if (reason === 'orientation') {
            return i18n.pauseOrientation || 'A partida foi pausada apos mudanca de orientacao da tela.';
        }

        return i18n.pauseManual || 'Partida pausada. Retome quando estiver pronto.';
    };

    SpaceGroomers.prototype.buildRunTelemetry = function (runSummary, metaResult) {
        var mission = metaResult && metaResult.mission ? metaResult.mission.after : this.progression.getMissionPreview(null);
        var telemetry = {
            event: 'run_complete',
            sessionId: this.runSession ? this.runSession.sessionId : '',
            context: this.container.dataset.context || 'shortcode',
            result: runSummary.result,
            score: Math.max(0, Math.floor(toNumber(runSummary.score, 0))),
            durationSec: Math.max(0, Math.floor(toNumber(runSummary.durationSec, 0))),
            waveReached: Math.max(1, Math.floor(toNumber(runSummary.waveReached, 1))),
            bestCombo: Math.max(0, Math.floor(toNumber(runSummary.bestCombo, 0))),
            powerupsCollected: Math.max(0, Math.floor(toNumber(runSummary.powerupsCollected, 0))),
            tickKills: Math.max(0, Math.floor(toNumber(runSummary.tickKills, 0))),
            missionCompleted: !!(metaResult && metaResult.mission && metaResult.mission.justCompleted),
            missionId: mission.id,
            retry: !!(this.runSession && this.runSession.retry),
            pauseCount: this.runSession ? this.runSession.pauseCount : 0,
            pauseReasons: this.runSession ? cloneProgressState(this.runSession.pauseReasons) : {},
            progressMode: this.getProgressMode(),
            startedAt: this.runSession ? this.runSession.startedAt : '',
            endedAt: new Date().toISOString()
        };

        return telemetry;
    };

    SpaceGroomers.prototype.updatePauseOverlay = function () {
        if (!this.overlayPause) {
            return;
        }

        if (this.elPauseReason) {
            this.elPauseReason.textContent = this.getPauseMessage(this.pauseState && this.pauseState.reason ? this.pauseState.reason : 'manual');
        }

        if (this.elPauseStats) {
            this.elPauseStats.textContent = 'Onda ' + this.wave + ' | ' + this.score.toLocaleString() + ' pts | ' + Math.max(0, Math.round(this.runTimeMs / 1000)) + 's de run';
        }
    };

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
        this.pauseState = null;
        this.runSession = null;
        this.waveIntroDeadline = 0;
        this.waveIntroRemainingMs = BALANCE.waveIntroMs;

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
        this.runPowerupsCollected = 0;
        this.runTickKills = 0;
        this.runKills = 0;
        this.missionPreviewCompleteAnnounced = false;

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

        this.updateMetaUI();
    };

    SpaceGroomers.prototype.hideAllOverlays = function () {
        if (this.waveTimeout) {
            clearTimeout(this.waveTimeout);
            this.waveTimeout = null;
        }

        this.waveIntroDeadline = 0;
        if (this.overlayStart) {
            this.overlayStart.classList.add('dps-sg-overlay--hidden');
        }
        if (this.overlayGameover) {
            this.overlayGameover.classList.add('dps-sg-overlay--hidden');
        }
        if (this.overlayVictory) {
            this.overlayVictory.classList.add('dps-sg-overlay--hidden');
        }
        if (this.overlayWave) {
            this.overlayWave.classList.add('dps-sg-overlay--hidden');
        }
        if (this.overlayPause) {
            this.overlayPause.classList.add('dps-sg-overlay--hidden');
        }
    };

    SpaceGroomers.prototype.start = function (trigger) {
        var startTrigger = trigger || 'start';
        var previousState = this.state;

        if (startTrigger === 'retry') {
            this.emitTelemetry('retry', {
                fromState: previousState,
                previousResult: this.lastCompletedTelemetry ? this.lastCompletedTelemetry.result : ''
            });
        }

        this.reset();
        this.hideAllOverlays();
        this.beginRunSession(startTrigger, previousState);
        this.state = 'waveIntro';
        this.showWaveIntro(BALANCE.waveIntroMs);
    };

    SpaceGroomers.prototype.pauseGame = function (reason) {
        if (this.state !== 'playing' && this.state !== 'waveIntro') {
            return false;
        }

        var pauseReason = reason || 'manual';
        var previousState = this.state;

        if (previousState === 'waveIntro' && this.waveTimeout) {
            this.waveIntroRemainingMs = Math.max(0, this.waveIntroDeadline - performance.now());
        }

        if (this.waveTimeout) {
            clearTimeout(this.waveTimeout);
            this.waveTimeout = null;
        }

        cancelAnimationFrame(this.rafId);
        this.state = 'paused';
        this.pauseState = {
            reason: pauseReason,
            fromState: previousState
        };

        if (this.runSession) {
            this.runSession.pauseCount += 1;
            this.runSession.pauseReasons[pauseReason] = (this.runSession.pauseReasons[pauseReason] || 0) + 1;
        }

        this.hideAllOverlays();
        this.updatePauseOverlay();
        if (this.overlayPause) {
            this.overlayPause.classList.remove('dps-sg-overlay--hidden');
        }

        this.emitTelemetry('pause', {
            reason: pauseReason,
            fromState: previousState,
            pauseCount: this.runSession ? this.runSession.pauseCount : 0,
            elapsedSec: Math.max(0, Math.round(this.runTimeMs / 1000))
        });
        this.announceStatus(this.getPauseMessage(pauseReason));
        return true;
    };

    SpaceGroomers.prototype.resumeGame = function () {
        if (this.state !== 'paused') {
            return false;
        }

        var pauseState = this.pauseState || {
            reason: 'manual',
            fromState: 'playing'
        };

        this.hideAllOverlays();
        this.pauseState = null;
        this.emitTelemetry('resume', {
            reason: pauseState.reason,
            fromState: pauseState.fromState,
            elapsedSec: Math.max(0, Math.round(this.runTimeMs / 1000))
        });
        this.announceStatus((this.gameConfig.i18n && this.gameConfig.i18n.resumeReady) || 'Tudo pronto para retomar do mesmo ponto.');

        if (pauseState.fromState === 'waveIntro') {
            this.state = 'waveIntro';
            this.showWaveIntro(this.waveIntroRemainingMs || 80);
            return true;
        }

        this.state = 'playing';
        this.lastTime = performance.now();
        this.loop(this.lastTime);
        return true;
    };

    SpaceGroomers.prototype.showWaveIntro = function (delayMs) {
        var self = this;
        var titleEl = this.container.querySelector('.dps-sg-wave-title');
        var bonusEl = this.container.querySelector('.dps-sg-wave-bonus');
        var introDelay = typeof delayMs === 'number' ? Math.max(0, Math.floor(delayMs)) : BALANCE.waveIntroMs;
        this.waveConfig = getWaveConfig(this.wave);
        titleEl.textContent = 'Onda ' + this.wave;
        if (this.wave === 1) {
            var missionStart = this.progression.getMissionPreview(this.getLiveRunSummary());
            bonusEl.textContent = 'Meta de hoje: ' + missionStart.title + ' (' + missionStart.progress + '/' + missionStart.target + ')';
        } else {
            bonusEl.textContent = '';
        }
        this.overlayWave.classList.remove('dps-sg-overlay--hidden');
        this.waveIntroRemainingMs = introDelay;
        this.waveIntroDeadline = performance.now() + introDelay;

        if (this.waveTimeout) {
            clearTimeout(this.waveTimeout);
        }

        this.waveTimeout = setTimeout(function () {
            self.overlayWave.classList.add('dps-sg-overlay--hidden');
            self.waveTimeout = null;
            self.waveIntroDeadline = 0;
            self.waveIntroRemainingMs = BALANCE.waveIntroMs;
            self.spawnWave();
            self.state = 'playing';
            self.lastTime = performance.now();
            self.loop(self.lastTime);
        }, introDelay);
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

        if (!this.missionPreviewCompleteAnnounced) {
            var missionPreview = this.progression.getMissionPreview(this.getLiveRunSummary());
            if (this.progressSnapshot && this.progressSnapshot.mission && !this.progressSnapshot.mission.completed && missionPreview.completed) {
                this.missionPreviewCompleteAnnounced = true;
                this.showToast('Missao pronta', 'Finalize a run para registrar a conclusao de hoje.', 'success', 1500);
            }
        }

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
                        this.runKills++;
                        if (enemy.type === 'tick') {
                            this.runTickKills++;
                        }
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
                this.runKills++;
                if (enemy.type === 'tick') {
                    this.runTickKills++;
                }
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
        this.runPowerupsCollected++;
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
                    this.runKills++;
                    if (enemy.type === 'tick') {
                        this.runTickKills++;
                    }
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
        this.emitTelemetry('game_over', {
            score: this.score,
            waveReached: this.wave,
            durationSec: Math.max(0, Math.round(this.runTimeMs / 1000))
        });
        this.showToast('Fim da run', 'Mais uma tentativa costuma render melhor que a anterior.', 'warning', 1800);
    };

    SpaceGroomers.prototype.endWave = function () {
        if (this.wavePerfect) {
            var perfectBonus = this.waveConfig.perfectBonus;
            this.score += perfectBonus;
            this.spawnFloatingText('Perfeito +' + perfectBonus, W / 2, H / 2, '#9ae6b4', '700 16px "Segoe UI", system-ui, sans-serif');
        }

        this.emitTelemetry('wave_complete', {
            wave: this.wave,
            perfect: this.wavePerfect,
            score: this.score
        });

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

    SpaceGroomers.prototype.getLiveRunSummary = function () {
        return {
            score: this.score,
            durationSec: Math.max(0, Math.round(this.runTimeMs / 1000)),
            bestCombo: Math.max(this.bestComboCount, this.comboCount),
            powerupsCollected: this.runPowerupsCollected,
            tickKills: this.runTickKills,
            kills: this.runKills,
            waveReached: this.wave,
            result: this.state === 'victory' ? 'victory' : 'gameover'
        };
    };

    SpaceGroomers.prototype.finalizeProgression = function (result) {
        var runSummary = this.getLiveRunSummary();
        runSummary.result = result;

        var metaResult = this.progression.registerRun(runSummary);
        var telemetry = this.buildRunTelemetry(runSummary, metaResult);
        metaResult.syncPromise = this.progression.saveState({
            phase: 'run_complete',
            telemetry: telemetry
        });

        this.lastRunMeta = metaResult;
        this.lastCompletedTelemetry = telemetry;
        this.progressSnapshot = metaResult.snapshot;
        this.highscore = this.progressSnapshot.highscore;
        this.updateHighscoreDisplay();
        this.updateMetaUI();

        if (metaResult.mission.justCompleted) {
            this.emitTelemetry('mission_completed', {
                missionId: telemetry.missionId,
                score: telemetry.score,
                durationSec: telemetry.durationSec
            });
        }

        this.emitTelemetry('run_complete', telemetry);
        this.runSession = null;

        return {
            summary: runSummary,
            meta: metaResult,
            telemetry: telemetry
        };
    };

    SpaceGroomers.prototype.renderPostRunMeta = function (overlay, finalData) {
        var missionEl = overlay.querySelector('.dps-sg-overlay__mission');
        var recordsEl = overlay.querySelector('.dps-sg-overlay__records');
        var unlockWrap = overlay.querySelector('.dps-sg-overlay__unlocks');
        var unlockList = overlay.querySelector('.dps-sg-overlay__unlocks-list');
        var mission = finalData.meta.mission.after;
        var streak = finalData.meta.snapshot.streak;
        var records = finalData.meta.snapshot.records;

        if (missionEl) {
            if (mission.completed) {
                missionEl.textContent = mission.icon + ' Missao: ' + mission.title + ' (concluida hoje).';
            } else {
                missionEl.textContent = mission.icon + ' Missao: ' + mission.title + ' (' + mission.progress + '/' + mission.target + ') - faltam ' + mission.remaining + '.';
            }
        }

        if (recordsEl) {
            recordsEl.textContent = 'Streak: ' + streak.current + ' dias (melhor ' + streak.best + ') | recorde combo: ' + records.bestCombo + ' | melhor wave: ' + records.bestWave;
        }

        if (unlockWrap && unlockList) {
            if (finalData.meta.unlockedBadges.length > 0) {
                var badgeNames = [];
                for (var i = 0; i < Math.min(3, finalData.meta.unlockedBadges.length); i++) {
                    badgeNames.push(finalData.meta.unlockedBadges[i].icon + ' ' + finalData.meta.unlockedBadges[i].name);
                }
                unlockList.textContent = badgeNames.join(' | ');
                unlockWrap.classList.remove('dps-sg-overlay__unlocks--hidden');
            } else {
                unlockList.textContent = '';
                unlockWrap.classList.add('dps-sg-overlay__unlocks--hidden');
            }
        }
    };

    SpaceGroomers.prototype.updateMetaUI = function () {
        this.progression.ensureDailyMission();
        this.progressSnapshot = this.progression.getSnapshot();

        if (this.elStartStreak) {
            this.elStartStreak.textContent = this.progressSnapshot.streak.current + ' dias';
        }

        if (this.elStartMissionTitle) {
            this.elStartMissionTitle.textContent = this.progressSnapshot.mission.icon + ' ' + this.progressSnapshot.mission.title;
        }

        if (this.elStartMissionProgress) {
            if (this.progressSnapshot.mission.completed) {
                this.elStartMissionProgress.textContent = 'Meta diaria completa. Volte amanha para a proxima.';
            } else {
                this.elStartMissionProgress.textContent = 'Progresso: ' + this.progressSnapshot.mission.progress + '/' + this.progressSnapshot.mission.target + ' (faltam ' + this.progressSnapshot.mission.remaining + ')';
            }
        }

        if (this.elStartBadges) {
            this.elStartBadges.textContent = 'Badges locais: ' + this.progressSnapshot.badges.length;
        }

        if (this.elStartStatus) {
            if (this.getProgressMode() === 'portal_sync') {
                this.elStartStatus.textContent = (this.gameConfig.i18n && this.gameConfig.i18n.syncReady) || 'Progresso sincronizado com o portal.';
            } else if (this.getProgressMode() === 'local_storage') {
                this.elStartStatus.textContent = (this.gameConfig.i18n && this.gameConfig.i18n.syncFallback) || 'Sem portal autenticado: usando progresso local neste navegador.';
            } else {
                this.elStartStatus.textContent = (this.gameConfig.i18n && this.gameConfig.i18n.syncVolatile) || 'Sem portal e sem armazenamento local: o progresso vale apenas nesta aba.';
            }
        }

        this.updateGoalHUD();
    };

    SpaceGroomers.prototype.updateGoalHUD = function () {
        if (!this.elGoal || !this.elGoalTitle || !this.elGoalProgress || !this.elGoalFill || !this.elGoalRemaining) {
            return;
        }

        var runSummary = (this.state === 'playing' || this.state === 'gameoverTransition') ? this.getLiveRunSummary() : null;
        var mission = this.progression.getMissionPreview(runSummary);

        this.elGoalTitle.textContent = mission.icon + ' ' + mission.title;
        this.elGoalProgress.textContent = mission.progress + '/' + mission.target;
        this.elGoalFill.style.width = Math.min(100, (mission.progress / mission.target) * 100) + '%';
        this.elGoal.classList.toggle('dps-sg-goal--done', mission.completed);

        if (mission.completed) {
            this.elGoalRemaining.textContent = 'Concluida hoje';
        } else {
            this.elGoalRemaining.textContent = 'Falta ' + mission.remaining;
        }
    };

    SpaceGroomers.prototype.finishGameOver = function () {
        this.state = 'gameover';
        cancelAnimationFrame(this.rafId);

        var finalData = this.finalizeProgression('gameover');
        var overlay = this.overlayGameover;

        overlay.querySelector('.dps-sg-final-score').textContent = this.score.toLocaleString();
        overlay.querySelector('.dps-sg-overlay__stats').textContent =
            this.stats.flea + ' pulgas | ' +
            this.stats.tick + ' carrapatos | ' +
            this.stats.furball + ' pelos | melhor sequencia ' + this.bestComboCount + ' | ' + finalData.summary.durationSec + 's';

        this.renderPostRunMeta(overlay, finalData);
        overlay.classList.remove('dps-sg-overlay--hidden');
        this.announceStatus('Run encerrada em game over.');
    };

    SpaceGroomers.prototype.victory = function () {
        this.state = 'victory';
        cancelAnimationFrame(this.rafId);

        var finalData = this.finalizeProgression('victory');
        var overlay = this.overlayVictory;

        overlay.querySelector('.dps-sg-final-score').textContent = this.score.toLocaleString();
        overlay.querySelector('.dps-sg-overlay__stats').textContent =
            this.stats.flea + ' pulgas | ' +
            this.stats.tick + ' carrapatos | ' +
            this.stats.furball + ' pelos | ' +
            finalData.summary.durationSec + 's de run';

        this.renderPostRunMeta(overlay, finalData);
        overlay.classList.remove('dps-sg-overlay--hidden');
        this.announceStatus('Run concluida com vitoria.');
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
        if (this.elPauseBtn) {
            this.elPauseBtn.disabled = !(this.state === 'playing' || this.state === 'waveIntro' || this.state === 'paused');
            this.elPauseBtn.classList.toggle('dps-sg-btn--pause-active', this.state === 'paused');
            this.elPauseBtn.setAttribute('aria-label', this.state === 'paused' ? 'Retomar partida' : 'Pausar partida');
        }

        this.updateGoalHUD();
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
                    return instance.state === 'playing' || instance.state === 'waveIntro';
                });

                if (event.key === 'Escape') {
                    var active = getActiveInstance();
                    if (active) {
                        event.preventDefault();
                        if (active.state === 'paused') {
                            active.resumeGame();
                        } else {
                            active.pauseGame('manual');
                        }
                    }
                }

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

        var startButtons = this.container.querySelectorAll('.dps-sg-overlay--start .dps-sg-btn--play');
        for (var i = 0; i < startButtons.length; i++) {
            startButtons[i].addEventListener('click', function () {
                ensureAudio();
                self.start('start');
            });
        }

        var retryButtons = this.container.querySelectorAll('.dps-sg-overlay--gameover .dps-sg-btn--play, .dps-sg-overlay--victory .dps-sg-btn--play, .dps-sg-btn--retry');
        for (var j = 0; j < retryButtons.length; j++) {
            retryButtons[j].addEventListener('click', function () {
                ensureAudio();
                self.start('retry');
            });
        }

        if (this.elPauseResume) {
            this.elPauseResume.addEventListener('click', function () {
                ensureAudio();
                self.resumeGame();
            });
        }

        if (this.elPauseBtn) {
            this.elPauseBtn.addEventListener('click', function () {
                ensureAudio();
                if (self.state === 'paused') {
                    self.resumeGame();
                } else {
                    self.pauseGame('manual');
                }
            });
        }

        if (this.overlayStart) {
            this.overlayStart.addEventListener('click', function (event) {
                if (event.target.closest('.dps-sg-btn--play')) {
                    return;
                }
                ensureAudio();
                self.start('start');
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

            SpaceGroomers._pauseAllPlaying = function (reason) {
                SpaceGroomers._instances.forEach(function (instance) {
                    instance.pauseGame(reason || 'manual');
                });
            };

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    SpaceGroomers._pauseAllPlaying('hidden');
                }
            });

            window.addEventListener('blur', function () {
                SpaceGroomers._pauseAllPlaying('blur');
            });

            window.addEventListener('orientationchange', function () {
                SpaceGroomers._pauseAllPlaying('orientation');
            });

            window.addEventListener('resize', function () {
                SpaceGroomers._instances.forEach(function (instance) {
                    instance.draw();
                    instance.updateHUD();
                });
            });
        }
    };

    SpaceGroomers.prototype.getTextState = function () {
        return {
            coordinateSystem: 'origin top-left, +x right, +y down',
            mode: this.state,
            progressMode: this.getProgressMode(),
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
            pauseState: this.pauseState,
            run: {
                durationSec: Math.round(this.runTimeMs / 1000),
                powerupsCollected: this.runPowerupsCollected,
                tickKills: this.runTickKills,
                kills: this.runKills
            },
            mission: this.progression.getMissionPreview(this.getLiveRunSummary()),
            streak: this.progressSnapshot ? this.progressSnapshot.streak : null,
            badgesUnlocked: this.progressSnapshot ? this.progressSnapshot.badges.length : 0,
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

    ENEMY_TYPES.flea.color = '#c97837';
    ENEMY_TYPES.flea.accent = '#ffd18a';
    ENEMY_TYPES.flea.detail = '#442314';
    ENEMY_TYPES.tick.color = '#647f3c';
    ENEMY_TYPES.tick.accent = '#dff0a4';
    ENEMY_TYPES.tick.detail = '#20351b';
    ENEMY_TYPES.furball.color = '#ecd5ae';
    ENEMY_TYPES.furball.accent = '#ff9b73';
    ENEMY_TYPES.furball.detail = '#4e3428';

    POWERUP_TYPES.shampoo.color = '#43c7ff';
    POWERUP_TYPES.shampoo.accent = '#dff8ff';
    POWERUP_TYPES.towel.color = '#ffc14f';
    POWERUP_TYPES.towel.accent = '#fff3c2';

    function roundRectPath(ctx, x, y, width, height, radius) {
        var r = Math.max(0, Math.min(radius, Math.min(width, height) / 2));
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + width - r, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + r);
        ctx.lineTo(x + width, y + height - r);
        ctx.quadraticCurveTo(x + width, y + height, x + width - r, y + height);
        ctx.lineTo(x + r, y + height);
        ctx.quadraticCurveTo(x, y + height, x, y + height - r);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
    }

    function drawBackdrop(ctx, runTimeMs, stars) {
        var sky = ctx.createLinearGradient(0, 0, 0, H);
        sky.addColorStop(0, '#041224');
        sky.addColorStop(0.56, '#0a2442');
        sky.addColorStop(1, '#15395d');
        ctx.fillStyle = sky;
        ctx.fillRect(0, 0, W, H);

        ctx.fillStyle = 'rgba(120, 194, 255, 0.11)';
        ctx.beginPath();
        ctx.ellipse(W * 0.19, 100, 86, 72, -0.18, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = 'rgba(255, 181, 120, 0.06)';
        ctx.beginPath();
        ctx.ellipse(W * 0.78, 138, 74, 54, 0.22, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = 'rgba(170, 223, 255, 0.08)';
        ctx.lineWidth = 1;
        for (var line = 0; line < 3; line++) {
            var y = H - 118 + line * 16;
            ctx.beginPath();
            ctx.moveTo(18, y + Math.sin(runTimeMs / 900 + line) * 2);
            ctx.lineTo(W - 18, y);
            ctx.stroke();
        }

        drawStars(ctx, stars, runTimeMs);

        var floor = ctx.createLinearGradient(0, H - 88, 0, H);
        floor.addColorStop(0, 'rgba(79, 195, 247, 0)');
        floor.addColorStop(1, 'rgba(79, 195, 247, 0.14)');
        ctx.fillStyle = floor;
        ctx.fillRect(0, H - 88, W, 88);
        ctx.fillStyle = 'rgba(173, 231, 255, 0.22)';
        ctx.fillRect(0, H - 34, W, 2);
    }

    function drawPlayer(ctx, game) {
        var x = game.player.x;
        var y = game.player.y + Math.sin(game.runTimeMs / 220) * 1.6;
        var hitFlash = game.playerHitTimer > 0;
        var blink = game.playerInvulnTimer > 0 && Math.floor(game.playerInvulnTimer / 70) % 2 === 0;
        var enginePulse = 0.92 + Math.sin(game.runTimeMs / 92) * 0.14;
        if (blink && !hitFlash && game.state !== 'gameoverTransition') { return; }
        ctx.save();
        ctx.translate(x, y);
        if (hitFlash) { ctx.scale(0.96, 1.08); }

        if (blink && !hitFlash && game.state !== 'gameoverTransition') {
            return;
        }

        ctx.save();
        ctx.translate(x, y);

        if (hitFlash) {
            ctx.scale(0.96, 1.08);
        }

        ctx.globalAlpha = 0.26;
        ctx.fillStyle = '#03101f';
        ctx.beginPath();
        ctx.ellipse(0, 20, 18, 6, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.globalAlpha = 1;
        ctx.fillStyle = hitFlash ? '#ffb39c' : '#ff9363';
        ctx.beginPath(); ctx.moveTo(-12, 12); ctx.lineTo(-16, 16 + 10 * enginePulse); ctx.lineTo(-8, 16); ctx.closePath(); ctx.fill();
        ctx.beginPath(); ctx.moveTo(12, 12); ctx.lineTo(16, 16 + 10 * enginePulse); ctx.lineTo(8, 16); ctx.closePath(); ctx.fill();
        ctx.fillStyle = hitFlash ? '#fff0d6' : '#ffe7a5';
        ctx.beginPath(); ctx.moveTo(-10, 13); ctx.lineTo(-12, 18 + 6 * enginePulse); ctx.lineTo(-7, 16); ctx.closePath(); ctx.fill();
        ctx.beginPath(); ctx.moveTo(10, 13); ctx.lineTo(12, 18 + 6 * enginePulse); ctx.lineTo(7, 16); ctx.closePath(); ctx.fill();
        ctx.fillStyle = hitFlash ? '#ffd8d2' : '#59d1ff';
        ctx.beginPath();
        ctx.moveTo(0, -20); ctx.lineTo(14, -7); ctx.lineTo(20, 11); ctx.lineTo(9, 11); ctx.lineTo(6, 18); ctx.lineTo(-6, 18); ctx.lineTo(-9, 11); ctx.lineTo(-20, 11); ctx.lineTo(-14, -7);
        ctx.closePath();
        ctx.fill();
        ctx.fillStyle = hitFlash ? '#ff9d82' : '#ff8058';
        ctx.beginPath(); ctx.moveTo(0, -24); ctx.lineTo(8, -10); ctx.lineTo(-8, -10); ctx.closePath(); ctx.fill();
        ctx.fillStyle = hitFlash ? '#ffd8d2' : '#1f6fb6';
        ctx.beginPath(); ctx.moveTo(-14, 1); ctx.lineTo(-24, 10); ctx.lineTo(-11, 12); ctx.closePath(); ctx.fill();
        ctx.beginPath(); ctx.moveTo(14, 1); ctx.lineTo(24, 10); ctx.lineTo(11, 12); ctx.closePath(); ctx.fill();
        ctx.fillStyle = '#f4fbff';
        ctx.beginPath(); ctx.ellipse(0, -6, 8, 10, 0, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#133d66';
        ctx.beginPath(); ctx.ellipse(0, -6, 5, 7, 0, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.34)'; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(-9, -3); ctx.lineTo(9, -3); ctx.stroke();
        ctx.strokeStyle = 'rgba(5, 19, 36, 0.52)'; ctx.beginPath(); ctx.moveTo(-18, 10); ctx.lineTo(-7, 16); ctx.lineTo(7, 16); ctx.lineTo(18, 10); ctx.stroke();

        ctx.fillStyle = hitFlash ? '#ffb39c' : '#ff9363';
        ctx.beginPath();
        ctx.moveTo(-12, 12);
        ctx.lineTo(-16, 16 + 10 * enginePulse);
        ctx.lineTo(-8, 16);
        ctx.closePath();
        ctx.fill();

        ctx.beginPath();
        ctx.moveTo(12, 12);
        ctx.lineTo(16, 16 + 10 * enginePulse);
        ctx.lineTo(8, 16);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = hitFlash ? '#fff0d6' : '#ffe7a5';
        ctx.beginPath();
        ctx.moveTo(-10, 13);
        ctx.lineTo(-12, 18 + 6 * enginePulse);
        ctx.lineTo(-7, 16);
        ctx.closePath();
        ctx.fill();

        ctx.beginPath();
        ctx.moveTo(10, 13);
        ctx.lineTo(12, 18 + 6 * enginePulse);
        ctx.lineTo(7, 16);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = hitFlash ? '#ffd8d2' : '#59d1ff';
        ctx.beginPath();
        ctx.moveTo(0, -20);
        ctx.lineTo(14, -7);
        ctx.lineTo(20, 11);
        ctx.lineTo(9, 11);
        ctx.lineTo(6, 18);
        ctx.lineTo(-6, 18);
        ctx.lineTo(-9, 11);
        ctx.lineTo(-20, 11);
        ctx.lineTo(-14, -7);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = hitFlash ? '#ff9d82' : '#ff8058';
        ctx.beginPath();
        ctx.moveTo(0, -24);
        ctx.lineTo(8, -10);
        ctx.lineTo(-8, -10);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = hitFlash ? '#ffd8d2' : '#1f6fb6';
        ctx.beginPath();
        ctx.moveTo(-14, 1);
        ctx.lineTo(-24, 10);
        ctx.lineTo(-11, 12);
        ctx.closePath();
        ctx.fill();

        ctx.beginPath();
        ctx.moveTo(14, 1);
        ctx.lineTo(24, 10);
        ctx.lineTo(11, 12);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = '#f4fbff';
        ctx.beginPath();
        ctx.ellipse(0, -6, 8, 10, 0, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = '#133d66';
        ctx.beginPath();
        ctx.ellipse(0, -6, 5, 7, 0, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = 'rgba(255, 255, 255, 0.34)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(-9, -3);
        ctx.lineTo(9, -3);
        ctx.stroke();

        ctx.strokeStyle = 'rgba(5, 19, 36, 0.52)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(-18, 10);
        ctx.lineTo(-7, 16);
        ctx.lineTo(7, 16);
        ctx.lineTo(18, 10);
        ctx.stroke();

        ctx.restore();
    }

    function drawEnemy(ctx, enemy, runTimeMs) {
        var et = ENEMY_TYPES[enemy.type];
        var hs = ENEMY_SIZE / 2;
        var bobOffset = enemy.pattern === 'dive' ? 0 : Math.sin(runTimeMs / 240 + enemy.animSeed) * 1.4;
        var hurtScale = enemy.hurtTimer > 0 ? 1.06 : 1;
        ctx.save();
        ctx.translate(enemy.x, enemy.y + bobOffset);
        ctx.scale(hurtScale, 1 / hurtScale);
        ctx.globalAlpha = 0.2;
        ctx.fillStyle = '#071321';
        ctx.beginPath(); ctx.ellipse(0, hs * 0.9, hs * 0.9, 5, 0, 0, Math.PI * 2); ctx.fill();
        ctx.globalAlpha = 1;
        if (enemy.pattern === 'dive' && enemy.telegraph > 0) {
            var ringSize = hs + 6 + (1 - enemy.telegraph / enemy.telegraphMax) * 10;
            ctx.strokeStyle = 'rgba(255, 141, 112, 0.82)'; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(0, 0, ringSize, 0, Math.PI * 2); ctx.stroke();
        }
        if (enemy.pattern === 'dive' && enemy.telegraph <= 0) { ctx.rotate(clamp(enemy.vx * 0.12, -0.28, 0.28)); }
        if (enemy.type === 'flea') {
            ctx.strokeStyle = 'rgba(68, 35, 20, 0.78)'; ctx.lineWidth = 2;
            for (var leg = -1; leg <= 1; leg += 2) { ctx.beginPath(); ctx.moveTo(leg * 5, 2); ctx.lineTo(leg * 11, 8); ctx.moveTo(leg * 4, 6); ctx.lineTo(leg * 10, 13); ctx.stroke(); }
            ctx.fillStyle = enemy.hurtTimer > 0 ? '#fff1dc' : et.color; ctx.beginPath(); ctx.ellipse(0, 3, 10, 9, 0, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = enemy.hurtTimer > 0 ? '#ffe5c0' : et.accent; ctx.beginPath(); ctx.ellipse(0, -7, 7, 6, 0, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = et.detail; ctx.beginPath(); ctx.arc(-2.3, -8, 1.6, 0, Math.PI * 2); ctx.arc(2.3, -8, 1.6, 0, Math.PI * 2); ctx.fill();
        } else if (enemy.type === 'tick') {
            ctx.fillStyle = enemy.hurtTimer > 0 ? '#edf8d0' : et.color;
            ctx.beginPath(); ctx.moveTo(0, -14); ctx.lineTo(12, -6); ctx.lineTo(12, 8); ctx.lineTo(0, 15); ctx.lineTo(-12, 8); ctx.lineTo(-12, -6); ctx.closePath(); ctx.fill();
            ctx.fillStyle = enemy.hurtTimer > 0 ? '#f9ffd9' : '#2f4726';
            ctx.beginPath(); ctx.moveTo(0, -9); ctx.lineTo(8, -3); ctx.lineTo(8, 6); ctx.lineTo(0, 11); ctx.lineTo(-8, 6); ctx.lineTo(-8, -3); ctx.closePath(); ctx.fill();
            if (enemy.hp > 1) { ctx.strokeStyle = 'rgba(223, 240, 164, 0.92)'; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(-6, -2); ctx.lineTo(6, -2); ctx.stroke(); }
            ctx.fillStyle = enemy.hurtTimer > 0 ? '#ffffff' : et.accent; ctx.beginPath(); ctx.arc(0, 1, 3.2, 0, Math.PI * 2); ctx.fill();
        var type = enemy.type;
        var et = ENEMY_TYPES[type];
        var hs = ENEMY_SIZE / 2;
        var bobOffset = enemy.pattern === 'dive' ? 0 : Math.sin(runTimeMs / 240 + enemy.animSeed) * 1.4;
        var hurtScale = enemy.hurtTimer > 0 ? 1.06 : 1;

        ctx.save();
        ctx.translate(enemy.x, enemy.y + bobOffset);
        ctx.scale(hurtScale, 1 / hurtScale);

        ctx.globalAlpha = 0.2;
        ctx.fillStyle = '#071321';
        ctx.beginPath();
        ctx.ellipse(0, hs * 0.9, hs * 0.9, 5, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.globalAlpha = 1;

        if (enemy.pattern === 'dive' && enemy.telegraph > 0) {
            var ringSize = hs + 6 + (1 - enemy.telegraph / enemy.telegraphMax) * 10;
            ctx.strokeStyle = 'rgba(255, 141, 112, 0.82)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(0, 0, ringSize, 0, Math.PI * 2);
            ctx.stroke();
        }

        if (enemy.pattern === 'dive' && enemy.telegraph <= 0) {
            ctx.rotate(clamp(enemy.vx * 0.12, -0.28, 0.28));
        }

        if (type === 'flea') {
            ctx.strokeStyle = 'rgba(68, 35, 20, 0.78)';
            ctx.lineWidth = 2;
            for (var leg = -1; leg <= 1; leg += 2) {
                ctx.beginPath();
                ctx.moveTo(leg * 5, 2);
                ctx.lineTo(leg * 11, 8);
                ctx.moveTo(leg * 4, 6);
                ctx.lineTo(leg * 10, 13);
                ctx.stroke();
            }

            ctx.fillStyle = enemy.hurtTimer > 0 ? '#fff1dc' : et.color;
            ctx.beginPath();
            ctx.ellipse(0, 3, 10, 9, 0, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = enemy.hurtTimer > 0 ? '#ffe5c0' : et.accent;
            ctx.beginPath();
            ctx.ellipse(0, -7, 7, 6, 0, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = et.detail;
            ctx.beginPath();
            ctx.arc(-2.3, -8, 1.6, 0, Math.PI * 2);
            ctx.arc(2.3, -8, 1.6, 0, Math.PI * 2);
            ctx.fill();

            ctx.strokeStyle = 'rgba(255, 255, 255, 0.28)';
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(-4, -10);
            ctx.lineTo(-7, -14);
            ctx.moveTo(4, -10);
            ctx.lineTo(7, -14);
            ctx.stroke();
        } else if (type === 'tick') {
            ctx.fillStyle = enemy.hurtTimer > 0 ? '#edf8d0' : et.color;
            ctx.beginPath();
            ctx.moveTo(0, -14);
            ctx.lineTo(12, -6);
            ctx.lineTo(12, 8);
            ctx.lineTo(0, 15);
            ctx.lineTo(-12, 8);
            ctx.lineTo(-12, -6);
            ctx.closePath();
            ctx.fill();

            ctx.fillStyle = enemy.hurtTimer > 0 ? '#f9ffd9' : '#2f4726';
            ctx.beginPath();
            ctx.moveTo(0, -9);
            ctx.lineTo(8, -3);
            ctx.lineTo(8, 6);
            ctx.lineTo(0, 11);
            ctx.lineTo(-8, 6);
            ctx.lineTo(-8, -3);
            ctx.closePath();
            ctx.fill();

            if (enemy.hp > 1) {
                ctx.strokeStyle = 'rgba(223, 240, 164, 0.92)';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.moveTo(-6, -2);
                ctx.lineTo(6, -2);
                ctx.stroke();
            }

            ctx.fillStyle = enemy.hurtTimer > 0 ? '#ffffff' : et.accent;
            ctx.beginPath();
            ctx.arc(0, 1, 3.2, 0, Math.PI * 2);
            ctx.fill();
        } else {
            for (var puff = 0; puff < 6; puff++) {
                var angle = (puff / 6) * Math.PI * 2 + enemy.animSeed * 0.25;
                ctx.fillStyle = enemy.hurtTimer > 0 ? '#fff1de' : (puff % 2 === 0 ? et.color : et.accent);
                ctx.beginPath(); ctx.arc(Math.cos(angle) * 7, Math.sin(angle) * 7, 5.2, 0, Math.PI * 2); ctx.fill();
            }
            ctx.fillStyle = enemy.hurtTimer > 0 ? '#fffaf2' : '#fff3e0'; ctx.beginPath(); ctx.arc(0, 0, 7, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = et.detail; ctx.beginPath(); ctx.arc(-2, -1, 1.4, 0, Math.PI * 2); ctx.arc(2, -1, 1.4, 0, Math.PI * 2); ctx.fill();
        }
        if (enemy.pattern === 'dive' && enemy.telegraph <= 0) { ctx.strokeStyle = 'rgba(255, 190, 146, 0.62)'; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(0, hs * 0.55); ctx.lineTo(0, hs + 12); ctx.stroke(); }
                ctx.beginPath();
                ctx.arc(Math.cos(angle) * 7, Math.sin(angle) * 7, 5.2, 0, Math.PI * 2);
                ctx.fill();
            }

            ctx.fillStyle = enemy.hurtTimer > 0 ? '#fffaf2' : '#fff3e0';
            ctx.beginPath();
            ctx.arc(0, 0, 7, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = et.detail;
            ctx.beginPath();
            ctx.arc(-2, -1, 1.4, 0, Math.PI * 2);
            ctx.arc(2, -1, 1.4, 0, Math.PI * 2);
            ctx.fill();

            ctx.strokeStyle = 'rgba(255, 255, 255, 0.28)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(-2, 3);
            ctx.lineTo(2, 3);
            ctx.stroke();
        }

        if (enemy.pattern === 'dive' && enemy.telegraph <= 0) {
            ctx.strokeStyle = 'rgba(255, 190, 146, 0.62)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(0, hs * 0.55);
            ctx.lineTo(0, hs + 12);
            ctx.stroke();
        }

        ctx.restore();
    }

    function drawBullet(ctx, bullet) {
        ctx.save();
        ctx.translate(bullet.x, bullet.y);
        ctx.fillStyle = 'rgba(103, 227, 255, 0.22)';
        roundRectPath(ctx, -4, -4, 8, 16, 4);
        ctx.fill();
        ctx.fillStyle = '#eefcff';
        roundRectPath(ctx, -2, -10, 4, 18, 3);
        ctx.fill();
        ctx.fillStyle = '#7de4ff';
        roundRectPath(ctx, -1, -4, 2, 9, 1);
        ctx.fill();

        ctx.fillStyle = 'rgba(103, 227, 255, 0.22)';
        roundRectPath(ctx, -4, -4, 8, 16, 4);
        ctx.fill();

        ctx.fillStyle = '#eefcff';
        roundRectPath(ctx, -2, -10, 4, 18, 3);
        ctx.fill();

        ctx.fillStyle = '#7de4ff';
        roundRectPath(ctx, -1, -4, 2, 9, 1);
        ctx.fill();

        ctx.restore();
    }

    function drawMud(ctx, mud) {
        ctx.save();
        ctx.translate(mud.x, mud.y);
        ctx.fillStyle = '#7d5638';
        ctx.beginPath(); ctx.arc(0, 0, MUD_SIZE, 0, Math.PI * 2); ctx.arc(-4, 2, MUD_SIZE * 0.65, 0, Math.PI * 2); ctx.arc(4, 2, MUD_SIZE * 0.55, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = 'rgba(255, 224, 193, 0.45)'; ctx.beginPath(); ctx.arc(-2, -2, 2, 0, Math.PI * 2); ctx.fill();
        ctx.beginPath();
        ctx.arc(0, 0, MUD_SIZE, 0, Math.PI * 2);
        ctx.arc(-4, 2, MUD_SIZE * 0.65, 0, Math.PI * 2);
        ctx.arc(4, 2, MUD_SIZE * 0.55, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = 'rgba(255, 224, 193, 0.45)';
        ctx.beginPath();
        ctx.arc(-2, -2, 2, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }

    function drawPowerup(ctx, powerup, runTimeMs) {
        var pt = POWERUP_TYPES[powerup.type];
        var bob = Math.sin(runTimeMs / 180 + powerup.animSeed) * 4;
        var y = powerup.y + bob;
        ctx.save();
        ctx.translate(powerup.x, y);
        ctx.globalAlpha = 0.24; ctx.fillStyle = '#051221'; ctx.beginPath(); ctx.ellipse(0, 18, 14, 5, 0, 0, Math.PI * 2); ctx.fill(); ctx.globalAlpha = 1;
        ctx.fillStyle = pt.color; ctx.beginPath(); ctx.arc(0, 0, POWERUP_SIZE / 2 + 3, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.55)'; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(0, 0, POWERUP_SIZE / 2 + 8 + Math.sin(runTimeMs / 170 + powerup.animSeed) * 2, 0, Math.PI * 2); ctx.stroke();
        if (powerup.type === 'shampoo') {
            ctx.fillStyle = pt.accent; roundRectPath(ctx, -5, -6, 10, 14, 4); ctx.fill(); ctx.fillRect(-2, -11, 4, 4); ctx.fillStyle = '#8ce6ff'; ctx.beginPath(); ctx.arc(7, -2, 2, 0, Math.PI * 2); ctx.arc(10, -7, 1.4, 0, Math.PI * 2); ctx.fill();
        } else {
            ctx.fillStyle = pt.accent; ctx.beginPath(); ctx.moveTo(0, -9); ctx.lineTo(9, 0); ctx.lineTo(0, 10); ctx.lineTo(-9, 0); ctx.closePath(); ctx.fill();
            ctx.strokeStyle = 'rgba(188, 119, 26, 0.62)'; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(-4, -1); ctx.lineTo(4, 7); ctx.stroke();
        }
        ctx.fillStyle = 'rgba(5, 20, 36, 0.82)'; roundRectPath(ctx, -34, 16, 68, 15, 7); ctx.fill();
        ctx.fillStyle = '#f8fbff'; ctx.font = '500 10px "Source Sans 3", "Segoe UI", system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(pt.shortLabel, 0, 23.5);

        ctx.save();
        ctx.translate(powerup.x, y);

        ctx.globalAlpha = 0.24;
        ctx.fillStyle = '#051221';
        ctx.beginPath();
        ctx.ellipse(0, 18, 14, 5, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.globalAlpha = 1;

        ctx.fillStyle = pt.color;
        ctx.beginPath();
        ctx.arc(0, 0, POWERUP_SIZE / 2 + 3, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = 'rgba(255,255,255,0.55)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(0, 0, POWERUP_SIZE / 2 + 8 + Math.sin(runTimeMs / 170 + powerup.animSeed) * 2, 0, Math.PI * 2);
        ctx.stroke();

        if (powerup.type === 'shampoo') {
            ctx.fillStyle = pt.accent;
            roundRectPath(ctx, -5, -6, 10, 14, 4);
            ctx.fill();
            ctx.fillRect(-2, -11, 4, 4);
            ctx.fillStyle = '#8ce6ff';
            ctx.beginPath();
            ctx.arc(7, -2, 2, 0, Math.PI * 2);
            ctx.arc(10, -7, 1.4, 0, Math.PI * 2);
            ctx.fill();
        } else {
            ctx.fillStyle = pt.accent;
            ctx.beginPath();
            ctx.moveTo(0, -9);
            ctx.lineTo(9, 0);
            ctx.lineTo(0, 10);
            ctx.lineTo(-9, 0);
            ctx.closePath();
            ctx.fill();
            ctx.strokeStyle = 'rgba(188, 119, 26, 0.62)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(-4, -1);
            ctx.lineTo(4, 7);
            ctx.stroke();
        }

        ctx.fillStyle = 'rgba(5, 20, 36, 0.82)';
        roundRectPath(ctx, -34, 16, 68, 15, 7);
        ctx.fill();
        ctx.fillStyle = '#f8fbff';
        ctx.font = '500 10px "Source Sans 3", "Segoe UI", system-ui, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(pt.shortLabel, 0, 23.5);
        ctx.restore();
    }

    function drawStars(ctx, stars, runTimeMs) {
        for (var i = 0; i < stars.length; i++) {
            var star = stars[i];
            var sparkle = 0.45 + Math.sin(runTimeMs / 280 + star.x * 0.04 + star.y * 0.03) * 0.22;
            var size = star.s > 1 ? 2.2 : 1.2;
            ctx.globalAlpha = clamp(sparkle, 0.22, 0.88);
            ctx.fillStyle = star.s > 1 ? '#e8fbff' : '#a5d4ff';
            ctx.fillRect(star.x, star.y, size, size);
            if (star.s > 1) { ctx.fillRect(star.x - 1, star.y + 0.6, size + 2, 0.8); ctx.fillRect(star.x + 0.6, star.y - 1, 0.8, size + 2); }
            if (star.s > 1) {
                ctx.fillRect(star.x - 1, star.y + 0.6, size + 2, 0.8);
                ctx.fillRect(star.x + 0.6, star.y - 1, 0.8, size + 2);
            }
        }
        ctx.globalAlpha = 1;
    }

    function drawParticles(ctx, arr) {
        for (var i = 0; i < arr.length; i++) {
            var particle = arr[i];
            ctx.globalAlpha = clamp(particle.life / particle.maxLife, 0, 1);
            ctx.fillStyle = particle.color;
            ctx.beginPath(); ctx.arc(particle.x, particle.y, particle.size / 2, 0, Math.PI * 2); ctx.fill();
            ctx.beginPath();
            ctx.arc(particle.x, particle.y, particle.size / 2, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.globalAlpha = 1;
    }

    function drawFloatingTexts(ctx, arr) {
        ctx.save(); ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.shadowBlur = 12; ctx.shadowColor = 'rgba(6, 16, 30, 0.35)';
        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.shadowBlur = 12;
        ctx.shadowColor = 'rgba(6, 16, 30, 0.35)';

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

    SpaceGroomers.prototype.updateResultOverlay = function (overlay, finalData) {
        if (!overlay || !finalData || !finalData.summary) { return; }
        var comboEl = overlay.querySelector('.dps-sg-result-combo');
        var waveEl = overlay.querySelector('.dps-sg-result-wave');
        var timeEl = overlay.querySelector('.dps-sg-result-time');
        if (comboEl) { comboEl.textContent = 'x' + Math.max(1, this.bestComboCount); }
        if (waveEl) { waveEl.textContent = Math.min(BALANCE.totalWaves, Math.max(1, this.wave)); }
        if (timeEl) { timeEl.textContent = finalData.summary.durationSec + 's'; }
        if (!overlay || !finalData || !finalData.summary) {
            return;
        }

        var comboEl = overlay.querySelector('.dps-sg-result-combo');
        var waveEl = overlay.querySelector('.dps-sg-result-wave');
        var timeEl = overlay.querySelector('.dps-sg-result-time');

        if (comboEl) {
            comboEl.textContent = 'x' + Math.max(1, this.bestComboCount);
        }

        if (waveEl) {
            waveEl.textContent = Math.min(BALANCE.totalWaves, Math.max(1, this.wave));
        }

        if (timeEl) {
            timeEl.textContent = finalData.summary.durationSec + 's';
        }
    };

    SpaceGroomers.prototype.finishGameOver = function () {
        this.state = 'gameover';
        cancelAnimationFrame(this.rafId);
        var finalData = this.finalizeProgression('gameover');
        var overlay = this.overlayGameover;
        overlay.querySelector('.dps-sg-final-score').textContent = this.score.toLocaleString();
        overlay.querySelector('.dps-sg-overlay__stats').textContent = 'Pulgas ' + this.stats.flea + ' | Carrapatos ' + this.stats.tick + ' | Pelos ' + this.stats.furball + ' | Run ' + finalData.summary.durationSec + 's';

        var finalData = this.finalizeProgression('gameover');
        var overlay = this.overlayGameover;

        overlay.querySelector('.dps-sg-final-score').textContent = this.score.toLocaleString();
        overlay.querySelector('.dps-sg-overlay__stats').textContent =
            'Pulgas ' + this.stats.flea + ' | ' +
            'Carrapatos ' + this.stats.tick + ' | ' +
            'Pelos ' + this.stats.furball + ' | ' +
            'Run ' + finalData.summary.durationSec + 's';

        this.updateResultOverlay(overlay, finalData);
        this.renderPostRunMeta(overlay, finalData);
        overlay.classList.remove('dps-sg-overlay--hidden');
        this.announceStatus('Run encerrada em game over.');
    };

    SpaceGroomers.prototype.victory = function () {
        this.state = 'victory';
        cancelAnimationFrame(this.rafId);
        var finalData = this.finalizeProgression('victory');
        var overlay = this.overlayVictory;
        overlay.querySelector('.dps-sg-final-score').textContent = this.score.toLocaleString();
        overlay.querySelector('.dps-sg-overlay__stats').textContent = 'Pulgas ' + this.stats.flea + ' | Carrapatos ' + this.stats.tick + ' | Pelos ' + this.stats.furball + ' | Run ' + finalData.summary.durationSec + 's';

        var finalData = this.finalizeProgression('victory');
        var overlay = this.overlayVictory;

        overlay.querySelector('.dps-sg-final-score').textContent = this.score.toLocaleString();
        overlay.querySelector('.dps-sg-overlay__stats').textContent =
            'Pulgas ' + this.stats.flea + ' | ' +
            'Carrapatos ' + this.stats.tick + ' | ' +
            'Pelos ' + this.stats.furball + ' | ' +
            'Run ' + finalData.summary.durationSec + 's';

        this.updateResultOverlay(overlay, finalData);
        this.renderPostRunMeta(overlay, finalData);
        overlay.classList.remove('dps-sg-overlay--hidden');
        this.announceStatus('Run concluida com vitoria.');
    };

    SpaceGroomers.prototype.draw = function () {
        var ctx = this.ctx;
        ctx.clearRect(0, 0, W, H);
        ctx.save();
        if (this.screenShakeTimer > 0 && this.screenShakeForce > 0) {
            ctx.translate((Math.random() - 0.5) * this.screenShakeForce, (Math.random() - 0.5) * this.screenShakeForce);
        }
        drawBackdrop(ctx, this.runTimeMs, this.stars);
        for (var i = 0; i < this.enemies.length; i++) { drawEnemy(ctx, this.enemies[i], this.runTimeMs); }
        for (var j = 0; j < this.bullets.length; j++) { drawBullet(ctx, this.bullets[j]); }
        for (var k = 0; k < this.muds.length; k++) { drawMud(ctx, this.muds[k]); }
        for (var l = 0; l < this.powerups.length; l++) { drawPowerup(ctx, this.powerups[l], this.runTimeMs); }
        if (this.state !== 'gameoverTransition' || this.gameOverTimer > BALANCE.gameOverDelayMs / 3) { drawPlayer(ctx, this); }
        drawParticles(ctx, this.particles);
        drawFloatingTexts(ctx, this.floatingTexts);
        if (this.playerHitTimer > 0) { ctx.fillStyle = 'rgba(255, 118, 97, 0.14)'; ctx.fillRect(0, 0, W, H); }

        if (this.screenShakeTimer > 0 && this.screenShakeForce > 0) {
            ctx.translate(
                (Math.random() - 0.5) * this.screenShakeForce,
                (Math.random() - 0.5) * this.screenShakeForce
            );
        }

        drawBackdrop(ctx, this.runTimeMs, this.stars);

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
            ctx.fillStyle = 'rgba(255, 118, 97, 0.14)';
            ctx.fillRect(0, 0, W, H);
        }

        ctx.restore();
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

    window.dps_sg_export_progress = function () {
        var instance = getActiveInstance();
        if (!instance || !instance.progression) {
            return JSON.stringify({ schemaVersion: PROGRESS_VERSION, mode: 'uninitialized' });
        }
        return JSON.stringify(instance.progression.getIntegrationPayload());
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