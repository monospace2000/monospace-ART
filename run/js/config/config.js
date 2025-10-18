// ============================================================
// CONFIG MODULE
// ============================================================
import { log, moduleTag, trace, showConfig } from '../utils/utilities.js';
import { state } from '../model/state.js'; // holds current digits
import { setAppearance } from '../render/render.js';

setAppearance('flat'); // flat, ball, or bubble

// Internal backing for FPS and POP_CAP
let _FPS = 60;
let _POP_CAP = 60;

export const CONFIG = {
    // --- Movement ---
    _speed: 5,
    get speed() {
        //        return this._speed;
        return this._speed ?? Math.min(Math.max(window.innerWidth / 500, 2), 5);
    },
    set speed(value) {
        this._speed = value;
        this.updateDerivedFrames();
    },

    // --- Lifecycle timing (seconds) ---
    _maxAgeSec: 15, // 10-15 is ideal
    get maxAgeSec() {
        return this._maxAgeSec;
    },
    set maxAgeSec(value) {
        this._maxAgeSec = value;
        this.updateDerivedFrames();
    },

    _attractionRadius: null,
    get attractionRadius() {
        return this._attractionRadius ?? 300;
    },
    set attractionRadius(value) {
        this._attractionRadius = value;
        this.updateDerivedFrames();
    },

    // --- Display ---
    _digitSize: null,
    get digitSize() {
        return (
            this._digitSize ??
            Math.min(Math.max(window.innerWidth / 15, 30), 36)
        );
    },
    set digitSize(value) {
        this._digitSize = value;
        document.documentElement.style.setProperty(
            '--digit-size',
            value + 'px'
        );
    },

    // --- Population Cap ---
    get POP_CAP() {
        return _POP_CAP;
    },
    set POP_CAP(value) {
        const oldCap = _POP_CAP;
        _POP_CAP = value;

        if (value < oldCap && state.digits.length > value) {
            const excess = state.digits.length - value;
            log(`POP_CAP decreased: aging ${excess} extra digits`);

            // Sort digits by age ascending (youngest first)
            const sorted = [...state.digits].sort((a, b) => a.age - b.age);

            for (let i = 0; i < excess; i++) {
                sorted[i].age = CONFIG.oldAge; // they will be killed next tick
            }
        }

        log(`POP_CAP updated to ${value}`);
    },

    // --- FPS ---
    get FPS() {
        return _FPS;
    },
    set FPS(value) {
        _FPS = value;
        this.updateDerivedFrames();
    },

    _juvenileAgeSec: null,
    get juvenileAgeSec() {
        return this._juvenileAgeSec ?? this.maxAgeSec / 8;
    },
    set juvenileAgeSec(value) {
        this._juvenileAgeSec = value;
        this.updateDerivedFrames();
    },

    _adolescenceAgeSec: null,
    get adolescenceAgeSec() {
        return this._adolescenceAgeSec ?? this.maxAgeSec / 5;
    },
    set adolescenceAgeSec(value) {
        this._adolescenceAgeSec = value;
        this.updateDerivedFrames();
    },

    _matureAgeSec: null,
    get matureAgeSec() {
        return this._matureAgeSec ?? this.maxAgeSec / 6;
    },
    set matureAgeSec(value) {
        this._matureAgeSec = value;
        this.updateDerivedFrames();
    },

    _oldAgeSec: null,
    get oldAgeSec() {
        return this._oldAgeSec ?? this.maxAgeSec - this.maxAgeSec / 7;
    },
    set oldAgeSec(value) {
        this._oldAgeSec = value;
        this.updateDerivedFrames();
    },
    // --- Reproduction ---
    _reproCooldownSec: null,
    get reproCooldownSec() {
        return this._reproCooldownSec ?? this.maxAgeSec / 12;
    },
    set reproCooldownSec(value) {
        this._reproCooldownSec = value;
        this.updateDerivedFrames();
    },

    _mateDistance: null,
    get mateDistance() {
        return this._mateDistance ?? 40;
    },
    set mateDistance(value) {
        this._mateDistance = value;
        this.updateDerivedFrames();
    },

    _gestationSec: null,
    get gestationSec() {
        return this._gestationSec ?? this.maxAgeSec / 30;
    },
    set gestationSec(value) {
        this._gestationSec = value;
        this.updateDerivedFrames();
    },

    //
    maxAgeVariation: 0.2, // 20%
    //
    velocityJitter: 0.6,
    directionJitter: 0.2,
    jitterYoung: 0.2,
    jitterOld: 0.2,
    //

    bondedOffset: 25,

    // --- Newborn settings ---
    newbornSpeedFactor: 1,
    newbornOffset: 2,

    // --- Attractor interaction ---
    enableAttractor: true,
    attractorRadius: 250,
    attractorStrength: 0.1,
    showAttractorOverlay: true,

    // === Spring physics for bonded movement ===
    springK: 0.3, // spring stiffness
    springDamping: 0.2, // damping factor (0 = no damping, 1 = heavy damping)
    bondedJitter: 0.2, // liveliness

    // --- Derived frame counts ---
    updateDerivedFrames() {
        this.maxAge = this.maxAgeSec * this.FPS;
        this.juvenileAge = this.juvenileAgeSec * this.FPS;
        this.adolescenceAge = this.adolescenceAgeSec * this.FPS;
        this.matureAge = this.matureAgeSec * this.FPS;
        this.oldAge = this.oldAgeSec * this.FPS;
        this.reproCooldown = this.reproCooldownSec * this.FPS;
        this.gestation = this.gestationSec * this.FPS;
    },
};

// Initial calculation
CONFIG.updateDerivedFrames();

// Apply digit size to CSS
document.documentElement.style.setProperty(
    '--digit-size',
    CONFIG.digitSize + 'px'
);

// Expose for dev/debug
if (window.DEV) {
    window.CONFIG = CONFIG;
    window.showConfig = showConfig;
}

log(`[${moduleTag(import.meta)}] loaded`);
