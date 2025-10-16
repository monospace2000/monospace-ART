// ============================================================
// UTILITIES
// ============================================================

function distance(a, b) {
    return Math.hypot(a.x - b.x, a.y - b.y);
}

function crossSum(a, b) {
    let sum = a + b;
    while (sum > 9) {
        sum = sum
            .toString()
            .split("")
            .reduce((acc, d) => acc + parseInt(d), 0);
    }
    return sum;
}

function applySpringPhysics(
    d,
    targetX,
    targetY,
    spring,
    damp,
    maxSpeed,
    offset = 0
) {
    // applies spring physics to a digit's dx and dy

    if (offset) {
        targetX += (Math.random() - 0.5) * offset;
        targetY += (Math.random() - 0.5) * offset;
    }

    d.dx += (targetX - d.x) * spring;
    d.dy += (targetY - d.y) * spring;
    d.dx *= damp;
    d.dy *= damp;

    const speed = Math.hypot(d.dx, d.dy);
    if (speed > maxSpeed) {
        d.dx = (d.dx / speed) * maxSpeed;
        d.dy = (d.dy / speed) * maxSpeed;
    }
}

// --- Logging ---
function log(...messages) {
    if (window.DEV) console.log(...messages);
}

// --- Log all public config params ---
function showConfig() {
    log("=== CONFIG PARAMETERS ===");
    for (const key of Object.keys(CONFIG)) {
        if (key.startsWith("_")) continue;
        const value = CONFIG[key];
        if (typeof value !== "function") log(key, ":", value);
    }
    log("========================");
}

// --- Module path helpers ---
function moduleTag(meta) {
    // Returns e.g. "modules/movement.js"
    const parts = meta.url.split("/");
    return parts.slice(-2).join("/");
}

// --- Example usage ---
// import { log, moduleTag, trace } from "../utils/utilities.js";
// log(`[${moduleTag(import.meta)}] loaded`);
// log(`${trace(import.meta, "updateDigitPosition")} started`);

log(`[${moduleTag(import.meta)}] loaded`);

// ============================================================
// STATE MODULE
// ============================================================



function createCountsObject() {
    return {
        M: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0, 6: 0, 7: 0, 8: 0, 9: 0 },
        F: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0, 6: 0, 7: 0, 8: 0, 9: 0 },
    };
}

const state = {
    digits: [],
    tick: 0,
    resetCount: 0,
    resetStartTime: Date.now(),
    allTimeTotal: 0,
    rawTotalValue: 0, // Store the raw total value of digit names
    // Simulation control
    running: false,
    paused: false,
    animationFrameId: null,
    currentCounts: createCountsObject(),
    epochCumulativeCounts: createCountsObject(),
};

log(`[${moduleTag(import.meta)}] loaded`);

// ============================================================
// CONFIG MODULE
// ============================================================

// Internal backing for FPS and POP_CAP
let _FPS = 60;
let _POP_CAP = 48;

const CONFIG$1 = {

    // --- Movement ---
    _speed: 3,
    get speed() {
        //        return this._speed;
        return this._speed ?? Math.min(Math.max(window.innerWidth / 500, 2), 5);
    },
    set speed(value) {
        this._speed = value;
        this.updateDerivedFrames();
    },

    // --- Lifecycle timing (seconds) ---
    _maxAgeSec: 20,
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
            Math.min(Math.max(window.innerWidth / 15, 30), 40)
        );
    },
    set digitSize(value) {
        this._digitSize = value;
        document.documentElement.style.setProperty(
            "--digit-size",
            value + "px"
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
                sorted[i].age = CONFIG$1.oldAge; // they will be killed next tick
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
        return this._matureAgeSec ?? this.maxAgeSec / 4;
    },
    set matureAgeSec(value) {
        this._matureAgeSec = value;
        this.updateDerivedFrames();
    },

    _oldAgeSec: null,
    get oldAgeSec() {
        return this._oldAgeSec ?? this.maxAgeSec - this.maxAgeSec / 5;
    },
    set oldAgeSec(value) {
        this._oldAgeSec = value;
        this.updateDerivedFrames();
    },

    velocityJitter: 1.0,
    directionJitter: 0.3,
    jitterYoung: 0.4,
    jitterOld: 0.2,

    // --- Reproduction ---
    _reproCooldownSec: null,
    get reproCooldownSec() {
        return this._reproCooldownSec ?? this.maxAgeSec / 8;
    },
    set reproCooldownSec(value) {
        this._reproCooldownSec = value;
        this.updateDerivedFrames();
    },

    _mateDistance: null,
    get mateDistance() {
        return this._mateDistance ?? 60;
    },
    set mateDistance(value) {
        this._mateDistance = value;
        this.updateDerivedFrames();
    },

    _gestationSec: null,
    get gestationSec() {
        return this._gestationSec ?? this.maxAgeSec / 20;
    },
    set gestationSec(value) {
        this._gestationSec = value;
        this.updateDerivedFrames();
    },

    bondedOffset: 50,

    // --- Newborn settings ---
    newbornSpeedFactor: 1,
    newbornOffset: 2,

    // --- Attractor interaction ---
    enableAttractor: false,
    attractorRadius: 250,
    attractorStrength: 0.2,
    showAttractorLines: true,

    // === Spring physics for bonded movement ===
    springK: 0.09, // spring stiffness
    springDamping: 0.6, // damping factor (0 = no damping, 1 = heavy damping)
    bondedJitter: 0.9, // liveliness

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
CONFIG$1.updateDerivedFrames();

// Apply digit size to CSS
document.documentElement.style.setProperty(
    "--digit-size",
    CONFIG$1.digitSize + "px"
);

// Expose for dev/debug
if (window.DEV) {
    window.CONFIG = CONFIG$1;
    window.showConfig = showConfig;
}

log(`[${moduleTag(import.meta)}] loaded`);

// ============================================================
// RENDER MODULE (Canvas-Only)
// Globe Shading + Outline + Glow + Drop Shadow + Motion Blur
// + Optional Simulation Overlays (search radius, bonds, attraction)
// ============================================================


// ============================================================
// 🔧 CONFIGURABLE APPEARANCE SETTINGS
// ============================================================

const GLOBE_SETTINGS = {
    lightAngle: Math.PI / 4,
    lightDistance: 0.3,
    highlight: 0.4,
    shadow: 0.4,
};
const OUTLINE_SETTINGS = { maxAlpha: 0.8, baseWidth: 0.5};
const GLOW_SETTINGS = { strength: 20, opacity: 0.9 };
const BLUR_SETTINGS = { maxBlur: 4 };



// ============================================================
// 🔧 SIMULATION OVERLAY SETTINGS
// ============================================================

const SEARCH_RADIUS_SETTINGS = {
    color: "rgba(0,0,255,0.15)",
    lineWidth: 1,
    fillOpacity: 0.05,
};
const BOND_LINE_SETTINGS = {
    color: "rgba(0,200,0,0.5)",
    lineWidth: 2,
};
const CHILD_BOND_LINE_SETTINGS = {
    baseColor: [255, 165, 0],
    lineWidth: 1.5,
};
const ATTRACTION_LINE_SETTINGS = {
    maleToFemale: { color: "rgba(255,255,255,0.7)", width: 1 },
};

// ============================================================
// INTERNAL STATE
// ============================================================

let canvas$2, ctx$2;

// ============================================================
// APPEARANCE LOGIC
// ============================================================

function updateDigitAppearance(d) {
    const scale = 0.3 + Math.min(d.age / CONFIG$1.matureAge, 1) * 0.7;
    const opacity =
        d.age < CONFIG$1.oldAge
            ? 1
            : 1 - (d.age - CONFIG$1.oldAge) / (CONFIG$1.maxAge - CONFIG$1.oldAge);

    const t = Math.min(d.age / CONFIG$1.matureAge, 1);
    let r, g, b;
    if (d.sex === "M") {
        r = (255 + t * (100 - 255)) | 0;
        g = (255 + t * (160 - 255)) | 0;
        b = 255;
    } else {
        r = 255;
        g = (255 + t * (100 - 255)) | 0;
        b = (255 + t * (100 - 255)) | 0;
    }

    const baseColor = `rgb(${r},${g},${b})`;
    const lighten = (c) => Math.min(255, c + 255 * GLOBE_SETTINGS.highlight);
    const darken = (c) => Math.max(0, c * (1 - GLOBE_SETTINGS.shadow));
    const highlight = `rgb(${lighten(r)},${lighten(g)},${lighten(b)})`;
    const shadow = `rgb(${darken(r)},${darken(g)},${darken(b)})`;

    const outlineColor = `rgba(0,0,0,${Math.min(opacity, OUTLINE_SETTINGS.maxAlpha)})`;
    const outlineWidth = OUTLINE_SETTINGS.baseWidth + scale * 0.5;

    const speed = Math.hypot(d.dx || 0, d.dy || 0);
    const blur = Math.min(speed / CONFIG$1.speed, 1) * BLUR_SETTINGS.maxBlur
        ;

    const lx =
        d.x -
        Math.cos(GLOBE_SETTINGS.lightAngle) *
            GLOBE_SETTINGS.lightDistance *
            CONFIG$1.digitSize *
            scale;
    const ly =
        d.y -
        Math.sin(GLOBE_SETTINGS.lightAngle) *
            GLOBE_SETTINGS.lightDistance *
            CONFIG$1.digitSize *
            scale;

    return {
        scale,
        color: baseColor,
        opacity,
        highlight,
        shadow,
        outlineColor,
        outlineWidth,
        blur,
        lx,
        ly,
    };
}

// ============================================================
// INITIALIZATION
// ============================================================

function initCanvasRenderer() {
    canvas$2 = document.getElementById("dslCanvas");
    if (!canvas$2) throw new Error("Canvas element not found: #dslCanvas");
    ctx$2 = canvas$2.getContext("2d");
    return { canvas: canvas$2, ctx: ctx$2 };
}

// ============================================================
// RENDERING FUNCTIONS
// ============================================================

function renderDigit(d, activeSet) {
    const props = updateDigitAppearance(d);
    const size = CONFIG$1.digitSize * props.scale;

    ctx$2.save();
    ctx$2.globalAlpha = props.opacity;

    // --- Reset shadow ---
    ctx$2.shadowBlur = 0;
    ctx$2.shadowOffsetX = 0;
    ctx$2.shadowOffsetY = 0;

    // --- Apply glow if enabled ---
    {
        ctx$2.shadowColor = `rgba(${props.color.match(/\d+/g).join(",")},${GLOW_SETTINGS.opacity})`;
        ctx$2.shadowBlur = GLOW_SETTINGS.strength;
    }

    // --- Main circle ---
    ctx$2.beginPath();
    {
        const g = ctx$2.createRadialGradient(
            props.lx,
            props.ly,
            size * 0.1,
            d.x,
            d.y,
            size / 2
        );
        g.addColorStop(0, props.highlight);
        g.addColorStop(0.3, props.color);
        g.addColorStop(1, props.shadow);
        ctx$2.fillStyle = g;
    }
    ctx$2.arc(d.x, d.y, size / 2, 0, Math.PI * 2);
    ctx$2.fill();
    ctx$2.closePath();

    // --- Reset shadow before outline ---
    ctx$2.shadowBlur = 0;
    ctx$2.shadowOffsetX = 0;
    ctx$2.shadowOffsetY = 0;

    // --- Outline ---
    {
        ctx$2.lineWidth = props.outlineWidth;
        ctx$2.strokeStyle = props.outlineColor;
        ctx$2.stroke();
    }

    // --- Label ---
    ctx$2.fillStyle = "#000";
    ctx$2.font = `${size * 0.6}px sans-serif`;
    ctx$2.textAlign = "center";
    ctx$2.textBaseline = "middle";
    ctx$2.fillText(d.name, d.x, d.y);
    ctx$2.restore();

    // --- Overlays ---
    if (d.sex === "M" && d.age >= CONFIG$1.matureAge) {
        ctx$2.save();
        ctx$2.fillStyle = `rgba(0,0,255,${SEARCH_RADIUS_SETTINGS.fillOpacity})`;
        ctx$2.strokeStyle = SEARCH_RADIUS_SETTINGS.color;
        ctx$2.lineWidth = SEARCH_RADIUS_SETTINGS.lineWidth;
        ctx$2.beginPath();
        ctx$2.arc(d.x, d.y, CONFIG$1.attractionRadius, 0, Math.PI * 2);
        ctx$2.fill();
        ctx$2.stroke();
        ctx$2.closePath();
        ctx$2.restore();
    }

    if (
        d.sex === "M" &&
        d.age >= CONFIG$1.matureAge &&
        d.age < CONFIG$1.oldAge &&
        d.attractionTarget
    ) {
        const target = d.attractionTarget;
        const validTarget =
            target &&
            activeSet.has(target) &&
            target.sex === "F" &&
            target.age >= CONFIG$1.matureAge &&
            target.age < CONFIG$1.oldAge &&
            target.gestationTimer === 0 &&
            !target.bondedTo;

        if (validTarget) {
            ctx$2.save();
            ctx$2.strokeStyle = ATTRACTION_LINE_SETTINGS.maleToFemale.color;
            ctx$2.lineWidth = ATTRACTION_LINE_SETTINGS.maleToFemale.width;
            ctx$2.beginPath();
            ctx$2.moveTo(d.x, d.y);
            ctx$2.lineTo(target.x, target.y);
            ctx$2.stroke();
            ctx$2.restore();
        }
    }
}


// ============================================================
// Render bonding lines safely
// ============================================================

function renderBonds(digits) {
    if (!ctx$2) return;
    ctx$2.save();
    ctx$2.lineCap = "round";

    const activeDigits = digits.filter((d) => d);
    const activeSet = new Set(activeDigits);

    for (const d of activeDigits) {
        // Adult bonds
// Adult bonds
if (d.bondedTo && activeSet.has(d.bondedTo)) {
    const other = d.bondedTo;

    // Compute fade for each digit
    const fade1 = d.age < CONFIG$1.oldAge
        ? 1
        : 1 - (d.age - CONFIG$1.oldAge) / (CONFIG$1.maxAge - CONFIG$1.oldAge);
    const fade2 = other.age < CONFIG$1.oldAge
        ? 1
        : 1 - (other.age - CONFIG$1.oldAge) / (CONFIG$1.maxAge - CONFIG$1.oldAge);

    const alpha = Math.min(fade1, fade2); // line fades if either partner is old

    ctx$2.strokeStyle = BOND_LINE_SETTINGS.color.replace(/[\d.]+\)$/g, `${alpha})`);
    ctx$2.lineWidth = BOND_LINE_SETTINGS.lineWidth;
    ctx$2.beginPath();
    ctx$2.moveTo(d.x, d.y);
    ctx$2.lineTo(other.x, other.y);
    ctx$2.stroke();
}


        // Child bonds
        if (
            d.mother &&
            activeSet.has(d.mother) &&
            d.age < CONFIG$1.adolescenceAge
        ) {
            const fade = 1 - d.age / CONFIG$1.adolescenceAge;
            ctx$2.strokeStyle = `rgba(${CHILD_BOND_LINE_SETTINGS.baseColor.join(",")},${fade})`;
            ctx$2.lineWidth = CHILD_BOND_LINE_SETTINGS.lineWidth;
            ctx$2.beginPath();
            ctx$2.moveTo(d.x, d.y);
            ctx$2.lineTo(d.mother.x, d.mother.y);
            ctx$2.stroke();
        }
    }

    ctx$2.restore();
}

// ============================================================
// Render all digits + overlays
// ============================================================

function renderAll(digits) {
    if (!ctx$2) return;
    ctx$2.clearRect(0, 0, canvas$2.width, canvas$2.height);

    const activeSet = new Set(digits.filter((d) => d));

    for (const d of digits) if (d) renderDigit(d, activeSet);
    renderBonds(digits);
}

// ============================================================
// MODULE LOAD MESSAGE
// ============================================================

log(`[${moduleTag(import.meta)}] loaded (render module)`);

// ============================================================
// MOVEMENT FUNCTIONS (Canvas-Only, internal targets & wall clamping)
// ============================================================


// Update a single digit's position
function updateDigitPosition(d) {
    let moved = false;

    // --- Juveniles follow mother ---
    if (d.mother && d.age < CONFIG$1.adolescenceAge) {
        if (!d.target) d.target = { x: d.x, y: d.y };
        if (!d.orbitAngle) d.orbitAngle = Math.random() * Math.PI * 2;

        d.orbitAngle += 0.01; 
        const radius = CONFIG$1.bondedOffset * (0.8 + Math.random() * 0.2);

        d.target.x = d.mother.x + Math.cos(d.orbitAngle) * radius;
        d.target.y = d.mother.y + Math.sin(d.orbitAngle) * radius;

        applySpringPhysics(
            d,
            d.target.x,
            d.target.y,
            CONFIG$1.springK,
            CONFIG$1.springDamping,
            CONFIG$1.speed * CONFIG$1.bondedJitter
        );

        // subtle extra jitter
        d.dx += (Math.random() - 0.5) * 0.1;
        d.dy += (Math.random() - 0.5) * 0.1;

        moved = true;
    }

    // --- Adult males bonded ---
    else if (d.sex === "M" && d.bondedTo) {
        if (!d.target) d.target = { x: d.x, y: d.y };
        if (!d.orbitAngle) d.orbitAngle = Math.random() * Math.PI * 2;

        d.orbitAngle += 0.008;
        const radius = CONFIG$1.bondedOffset * (0.85 + Math.random() * 0.15);

        d.target.x = d.bondedTo.x + Math.cos(d.orbitAngle) * radius;
        d.target.y = d.bondedTo.y + Math.sin(d.orbitAngle) * radius;

        applySpringPhysics(
            d,
            d.target.x,
            d.target.y,
            CONFIG$1.springK,
            CONFIG$1.springDamping,
            CONFIG$1.speed * CONFIG$1.bondedJitter
        );

        moved = true;
    }

// Adult males seeking females
else if (d.sex === "M" && d.age >= CONFIG$1.matureAge && d.age < CONFIG$1.oldAge) {
    const targetFemale = nearestFemale(d);
    if (targetFemale) {
        const dist = distance(d, targetFemale);

        // Try to bond if in range
        if (dist <= CONFIG$1.mateDistance && !d.bondedTo && targetFemale.gestationTimer === 0) {
            bondPair(d, targetFemale);
        }

        // Move toward female's current position (for physics)
        if (!d.target) d.target = { x: d.x, y: d.y };
        d.target.x = targetFemale.x;
        d.target.y = targetFemale.y;
        seekTarget(d, d.target, dist);

        // Store reference for rendering the attraction line
        d.attractionTarget = targetFemale;
    } else {
        d.attractionTarget = null;
    }
}




    // --- Females and unbonded males ---
    if (!moved) {
        applyJitter(d);
    }

    clampVelocity(d, CONFIG$1.speed);
    updatePosition(d);
    bounceOffWalls(d);
}

// --- Helpers ---
function nearestFemale(male) {
    let minDist = CONFIG$1.attractionRadius;
    let closest = null;

    for (const f of state.digits) {
        if (
            f.sex !== "F" ||
            f.age < CONFIG$1.matureAge ||
            f.age >= CONFIG$1.oldAge ||
            f.gestationTimer > 0 ||
            f.bondedTo
        ) continue;

        const dist = distance(male, f);
        if (dist < minDist) {
            minDist = dist;
            closest = f;
        }
    }
    return closest;
}

function seekTarget(d, target, dist) {
    const targetAngle = Math.atan2(target.y - d.y, target.x - d.x);
    const currentAngle = Math.atan2(d.dy, d.dx);

    let blend = 0.08;
    let speedFactor = 0.7;

    if (d.sex === "M" && !d.bondedTo && target.gestationTimer === 0) {
        blend = 0.15;
        speedFactor = 1.0;
    }

    const angle = currentAngle * (1 - blend) + targetAngle * blend;
    const approachFactor = Math.min(dist / CONFIG$1.attractionRadius, 1);
    const speed = CONFIG$1.speed * speedFactor * approachFactor;

    d.dx = Math.cos(angle) * speed;
    d.dy = Math.sin(angle) * speed;
}

function bondPair(male, female) {
    male.bondedTo = female;
    female.bondedTo = male;
    female.gestationTimer = CONFIG$1.gestation;
}

function applyJitter(d) {
    const ageRatio = d.age / CONFIG$1.maxAge;
    const jitterScale =
        ageRatio < 0.3
            ? CONFIG$1.jitterYoung
            : ageRatio < 0.7
            ? 1.0
            : CONFIG$1.jitterOld;

    const angle =
        Math.atan2(d.dy, d.dx) +
        (Math.random() - 0.5) * CONFIG$1.directionJitter * jitterScale;

    const jitter = (Math.random() - 0.5) * CONFIG$1.velocityJitter * jitterScale;
    const baseSpeed = CONFIG$1.speed * 0.2;
    const speed = Math.max(baseSpeed, Math.min(CONFIG$1.speed * (1 + jitter), CONFIG$1.speed * 1.2));

    d.dx = Math.cos(angle) * speed;
    d.dy = Math.sin(angle) * speed;
}

function clampVelocity(d, maxSpeed) {
    const mag = Math.hypot(d.dx, d.dy);
    if (mag > maxSpeed) {
        d.dx = (d.dx / mag) * maxSpeed;
        d.dy = (d.dy / mag) * maxSpeed;
    }
}

// --- Only update the internal position (no DOM) ---
function updatePosition(d) {
    d.x += d.dx;
    d.y += d.dy;
}


function bounceOffWalls(d) {
    const margin = 5;

    // Get current visual scale (same as used for rendering)
    const { scale } = updateDigitAppearance(d);
    const radius = (CONFIG$1.digitSize * scale) / 2;

    const bounds = {
        left: radius + margin,
        right: window.innerWidth - radius - margin,
        top: 40 + radius + margin,
        bottom: window.innerHeight - radius - margin,
    };

    // Reflect velocity when hitting walls
    if (d.x < bounds.left) {
        d.x = bounds.left;
        d.dx *= -0.8;
    } else if (d.x > bounds.right) {
        d.x = bounds.right;
        d.dx *= -0.8;
    }

    if (d.y < bounds.top) {
        d.y = bounds.top;
        d.dy *= -0.8;
    } else if (d.y > bounds.bottom) {
        d.y = bounds.bottom;
        d.dy *= -0.8;
    }
}


log(`[${moduleTag(import.meta)}] loaded`);

// ================================
// DIGIT MODULE (Canvas-Only)
// ================================


console.log("CONFIG in digit.js");

function createDigit(name, sex, x, y, speedFactor = 1, mother, father) {

    const angle = Math.random() * 2 * Math.PI;
    const digit = {
        name,
        sex,
        x,
        y,
        dx: mother ? 0 : Math.cos(angle) * CONFIG$1.speed * speedFactor,
        dy: mother ? 0 : Math.sin(angle) * CONFIG$1.speed * speedFactor,
        age: 0,
        lastRepro: -Infinity,
        element: null,           // <-- NO DOM element
        bondedTo: null,
        gestationTimer: 0,
        mother,
        father,
        attractedZero: false,
    };

    // Apply numerology traits if needed
    // applyNumerologyTraits(digit);

    // Initialize position and appearance (for internal state only)
    updateDigitPosition(digit);
    updateDigitAppearance(digit);

    state.digits.push(digit);

    state.currentCounts[sex][name]++;
    state.epochCumulativeCounts[sex][name]++;
    state.allTimeTotal += parseInt(name);

    return digit;
}

function killDigit(d) {
    state.currentCounts[d.sex][d.name] = Math.max(
        0,
        state.currentCounts[d.sex][d.name] - 1
    );

    // Only remove element if it exists (DOM mode fallback)
    if (d.element) d.element.remove();

    state.digits = state.digits.filter((x) => x !== d);
}

log(`[${moduleTag(import.meta)}] loaded`);

// ============================================================
// REPRODUCTION
// ============================================================



function reproduce() {
    if (state.digits.length >= CONFIG$1.POP_CAP) return;

    for (const f of state.digits) {
        if (f.sex === "F" && f.gestationTimer > 0) {
        
        f.gestationTimer--;

            if (f.gestationTimer === 0 && f.bondedTo) {

                const m = f.bondedTo;
                const name = crossSum(
                    parseInt(f.name),
                    parseInt(m.name)
                ).toString();
                const sex = Math.random() < 0.5 ? "M" : "F";
                const x =
                    (f.x + m.x) / 2 +
                    (Math.random() - 0.5) * CONFIG$1.newbornOffset;
                const y =
                    (f.y + m.y) / 2 +
                    (Math.random() - 0.5) * CONFIG$1.newbornOffset;

                createDigit(name, sex, x, y, CONFIG$1.newbornSpeedFactor, f, m);

                f.lastRepro = state.tick;
                m.lastRepro = state.tick;
                f.bondedTo = null;
                m.bondedTo = null;
                //break;
            }
        }
    }
}

log(`[${moduleTag(import.meta)}] loaded`);

// ============================================================
// STATS & UI (Safe Version)
// ============================================================

// ============================================================
// CONFIG: Which stats to display
// Each entry defines a label and a value-producing function
// ============================================================
const STATS_CONFIG = [
    {
        id: "epoch",
        label: "Epoch",
        value: () => state.resetCount + 1,
    },
    {
        id: "elapsed",
        label: "Elapsed Time",
        value: () =>
            ((Date.now() - state.resetStartTime) / 1000).toFixed(1) + "s",
    },
    {
        id: "current",
        label: "Current Value",
        value: () => {
            let total = 0;
            for (const d of state.digits)
                total = crossSum(total, parseInt(d.name));
            return total;
        },
    },
    {
        id: "epochTotal",
        label: "Epoch Total Value",
        value: () => {
            let epochTotal = 0;
            for (let n = 1; n <= 9; n++) {
                const digit = n.toString();
                const count =
                    (state.epochCumulativeCounts.M[digit] || 0) +
                    (state.epochCumulativeCounts.F[digit] || 0);
                for (let i = 0; i < count; i++) {
                    epochTotal = crossSum(epochTotal, parseInt(digit));
                }
            }
            return epochTotal;
        },
    },
];

// ============================================================
// INIT STATS BAR
// ============================================================
function initStatsBar() {
    const container = document.getElementById("statsBarContent");
    if (!container) return;

    // Clear any existing stats
    container.innerHTML = "";

    // Dynamically build stat items
    STATS_CONFIG.forEach(({ id, label }) => {
        const item = document.createElement("div");
        item.className = "statsbar-item";
        item.id = `stat-${id}`;
        item.innerHTML = `
      <div class="statsbar-item-label">${label}</div>
      <div class="statsbar-item-value">–</div>
    `;
        container.appendChild(item);
    });
}
// ============================================================
// UPDATE STATS BAR
// ============================================================
function updateStatsBar() {
    STATS_CONFIG.forEach(({ id, value }) => {
        const valueEl = document.querySelector(
            `#stat-${id} .statsbar-item-value`
        );
        if (!valueEl) return;

        try {
            const newValue = value();
            valueEl.textContent = newValue ?? "–";
        } catch (err) {
            console.warn(`⚠️ Error updating stat "${id}":`, err);
            valueEl.textContent = "–";
        }
    });
}

// ---------------------------
// STATS TABLE
// ---------------------------
function updateStatsTable() {
    const tbody = document.querySelector("#statsTable tbody");
    if (!tbody) return; // Table not yet in DOM

    tbody.innerHTML = "";

    const rows = [];
    let maxCurrent = 0,
        maxCumulative = 0;
    const totals = { mCur: 0, fCur: 0, mCum: 0, fCum: 0 };

    for (let n = 1; n <= 9; n++) {
        const digit = n.toString();
        const mCur = state.currentCounts.M[digit] || 0;
        const fCur = state.currentCounts.F[digit] || 0;
        const mCum = state.epochCumulativeCounts.M[digit] || 0;
        const fCum = state.epochCumulativeCounts.F[digit] || 0;

        rows.push({ digit, mCur, fCur, mCum, fCum });

        totals.mCur += mCur;
        totals.fCur += fCur;
        totals.mCum += mCum;
        totals.fCum += fCum;

        maxCurrent = Math.max(maxCurrent, mCur, fCur);
        maxCumulative = Math.max(maxCumulative, mCum, fCum);
    }

    const getOpacity = (value, max) =>
        value && max ? 0.1 + (value / max) * 0.8 : 0.1;

    for (const row of rows) {
        const mCurOpacity = getOpacity(row.mCur, maxCurrent);
        const fCurOpacity = getOpacity(row.fCur, maxCurrent);
        const mCumOpacity = getOpacity(row.mCum, maxCumulative);
        const fCumOpacity = getOpacity(row.fCum, maxCumulative);

        tbody.innerHTML += `
      <tr>
        <td>${row.digit}</td>
        <td style="background-color: rgba(100, 160, 255, ${mCurOpacity});">${
            row.mCur || ""
        }</td>
        <td style="background-color: rgba(255, 100, 100, ${fCurOpacity});">${
            row.fCur || ""
        }</td>
        <td><strong>${row.mCur + row.fCur || ""}</strong></td>
        <td style="background-color: rgba(100, 160, 255, ${mCumOpacity});">${
            row.mCum || ""
        }</td>
        <td style="background-color: rgba(255, 100, 100, ${fCumOpacity});">${
            row.fCum || ""
        }</td>
        <td><strong>${row.mCum + row.fCum || ""}</strong></td>
      </tr>
    `;
    }

    // TOTAL row
    tbody.innerHTML += `
    <tr style="font-weight: bold;">
      <td style="background-color: rgba(200, 200, 200, 0.3);">TOTAL</td>
      <td style="background-color: rgba(100, 160, 255, 0.5);">${
          totals.mCur
      }</td>
      <td style="background-color: rgba(255, 100, 100, 0.5);">${
          totals.fCur
      }</td>
      <td style="background-color: rgba(220, 220, 220, 0.4); font-size: 1.3em;"><strong>${
          totals.mCur + totals.fCur
      }</strong></td>
      <td style="background-color: rgba(100, 160, 255, 0.5);">${
          totals.mCum
      }</td>
      <td style="background-color: rgba(255, 100, 100, 0.5);">${
          totals.fCum
      }</td>
      <td style="background-color: rgba(220, 220, 220, 0.4); font-size: 1.3em;"><strong>${
          totals.mCum + totals.fCum
      }</strong></td>
    </tr>
  `;
}

// ---------------------------
// GRAPH
// ---------------------------


let graph = null;

function graphValue() {
    let rawTotalValue = 0;
    state.digits.length;

    for (const d of state.digits) {
        const value = parseInt(d.name) || 0; // safely parse
        rawTotalValue += value;
    }

    state.rawTotalValue = rawTotalValue;
    return rawTotalValue;
}

function initGraph() {
    const canvas = document.getElementById("graphCanvas");
    if (!canvas) {
        log("Graph canvas not yet available — will initialize later.");
        return false;
    }
    graph = {
        data: [],
        maxPoints: 200,
        updateCounter: 0,
        updateInterval: 5,
        canvas,
        ctx: canvas.getContext("2d"),
    };
    return true;
}

function updateGraph() {
    if (!graph) {
        if (!initGraph()) return; // try initializing if canvas exists
    }

    let valueToGraph = graphValue();
    graph.updateCounter++;
    if (graph.updateCounter >= graph.updateInterval) {
        graph.data.push(valueToGraph);
        if (graph.data.length > graph.maxPoints) graph.data.shift();
        graph.updateCounter = 0;
    }

    drawGraph();
}

function drawGraph() {
    if (!graph) return;
    const { canvas, ctx, data } = graph;
    const { width, height } = canvas;

    ctx.clearRect(0, 0, width, height);
    if (data.length < 2) return;

    const maxValue = Math.max(...data, 10);
    const range = maxValue;

    // Grid lines
    ctx.strokeStyle = "#e0e0e0";
    ctx.lineWidth = 1;
    for (let i = 0; i <= 5; i++) {
        const y = (height - 20) * (i / 5) + 10;
        ctx.beginPath();
        ctx.moveTo(30, y);
        ctx.lineTo(width - 10, y);
        ctx.stroke();
    }

    // Labels
    ctx.fillStyle = "black";
    ctx.font = "10px sans-serif";
    ctx.textAlign = "right";
    for (let i = 0; i <= 5; i++) {
        const y = (height - 20) * (i / 5) + 10;
        const value = Math.round(maxValue - (range * i) / 5);
        ctx.fillText(value, 25, y + 3);
    }

    // Graph line
    ctx.strokeStyle = "#2196F3";
    ctx.lineWidth = 2;
    ctx.beginPath();

    const pointSpacing = (width - 40) / (graph.maxPoints - 1);
    for (let i = 0; i < data.length; i++) {
        const x = 30 + i * pointSpacing;
        const y = height - 10 - (data[i] / range) * (height - 20);
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    }
    ctx.stroke();

    if (data.length > 0) {
        ctx.fillStyle = "black";
        ctx.font = "bold 12px sans-serif";
        ctx.textAlign = "left";
        ctx.fillText(`Current: ${data[data.length - 1]}`, 35, 20);
    }
}

initGraph();
initStatsBar();

function updateStats() {
    updateStatsBar?.();
    updateStatsTable?.();
    updateGraph?.();
}

log(`[${moduleTag(import.meta)}] loaded`);

// attractor.js

// create canvas for attractor debug lines
let attractorDebugCtx;
if (CONFIG$1.showAttractorLines) {
    const c = document.createElement("canvas");
    c.style.position = "fixed";
    c.style.left = 0;
    c.style.top = 0;
    c.style.pointerEvents = "none";
    c.width = window.innerWidth;
    c.height = window.innerHeight;
    c.style.zIndex = 9999;
    attractorDebugCtx = c.getContext("2d");
    document.body.appendChild(c);

    // keep canvas in sync with window
    window.addEventListener("resize", () => {
        c.width = window.innerWidth;
        c.height = window.innerHeight;
    });
}

let attractor = null;
let attractorEl = null;

/**
 * Initialize the attractor interaction
 */
function initAttractor() {
    log("Initializing attractor...");
    if (!CONFIG$1.enableAttractor) return;

    // Create visual element (finger/mouse)
    attractorEl = document.createElement("div");
    Object.assign(attractorEl.style, {
        position: "fixed",
        width: CONFIG$1.digitSize + "px",
        height: CONFIG$1.digitSize + "px",
        borderRadius: "50%",
        color: "white",
        fontSize: CONFIG$1.digitSize + "px",
        fontWeight: "bold",
        textAlign: "center",
        lineHeight: CONFIG$1.digitSize + "px",
        pointerEvents: "none",
        display: "none",

        // Prevent selection / highlighting
        userSelect: "none",
        WebkitUserSelect: "none",
        MozUserSelect: "none",
        msUserSelect: "none",
        touchAction: "none",
    });
    attractorEl.innerText = "0";
    document.body.appendChild(attractorEl);

    // Prevent right-click / long-press context menu
    attractorEl.addEventListener("contextmenu", (e) => e.preventDefault());

    // Helper to update attractor position and visual
    const updateAttractor = (x, y) => {
        attractor = { x, y };
        attractorEl.style.display = "block";
        attractorEl.style.left = x - CONFIG$1.digitSize / 2 + "px";
        attractorEl.style.top = y - CONFIG$1.digitSize / 2 + "px";
    };

    // --- Mouse Events ---
    document.addEventListener("mousedown", (e) => {
        updateAttractor(e.clientX, e.clientY);
        //log('Attractor set at:', attractor);
    });

    document.addEventListener(
        "mousemove",
        (e) => attractor && updateAttractor(e.clientX, e.clientY)
    );
    document.addEventListener("mouseup", () => {
        attractor = null;
        attractorEl.style.display = "none";
    });

    // --- Touch Events ---
    document.addEventListener("touchstart", (e) => {
        const touch = e.touches[0];
        updateAttractor(touch.clientX, touch.clientY);
    });
    document.addEventListener("touchmove", (e) => {
        const touch = e.touches[0];
        if (attractor) updateAttractor(touch.clientX, touch.clientY);
    });
    document.addEventListener("touchend", () => {
        attractor = null;
        attractorEl.style.display = "none";
    });
}

/**
 * Apply attractor effect to a digit
 */
function applyAttractor(digit) {
    if (!CONFIG$1.enableAttractor || !attractor) return;
    
    // Stop attracting digits that have already become zero
    if (digit.attractedZero) return;


    const dx = attractor.x - digit.x;
    const dy = attractor.y - digit.y;
    const dist = Math.sqrt(dx * dx + dy * dy);
    const radius = CONFIG$1.attractionRadius || 150;
    const strength = CONFIG$1.attractorStrength || 0.05;

    if (dist < radius) {
        const force = (radius - dist) / radius;

        // use dx/dy (always defined)
        digit.dx += dx * force * strength;
        digit.dy += dy * force * strength;

        if (CONFIG$1.showAttractorLines && attractorDebugCtx) {
            attractorDebugCtx.beginPath();
            attractorDebugCtx.moveTo(digit.x, digit.y);
            attractorDebugCtx.lineTo(attractor.x, attractor.y);
            attractorDebugCtx.strokeStyle = "rgba(0,255,0,0.1)";
            attractorDebugCtx.stroke();
        }

        if (dist < 20) {
            digit.name = "0";
            digit.attractedZero = true; // mark that this digit became zero via attractor
        }
    }
}

log(`[${moduleTag(import.meta)}] loaded`);

// ============================================================
// SIMULATION CONTROL (Canvas-Ready)
// ============================================================


// ------------------------------------------------------------
// Canvas setup
// ------------------------------------------------------------
let canvas$1, ctx$1;

function initSimulationCanvas() {
    canvas$1 = document.getElementById("dslCanvas");
    if (!canvas$1) throw new Error("Canvas element not found: #dslCanvas");
    ctx$1 = canvas$1.getContext("2d");
    return { canvas: canvas$1, ctx: ctx$1 };
}



// ------------------------------------------------------------
// Initialize starting digits
// ------------------------------------------------------------
function initStartingDigits() {
    const cx = window.innerWidth / 2;
    const cy = window.innerHeight / 2;

    const d1 = createDigit("1", "M", cx - 30, cy);
    const d2 = createDigit("1", "F", cx + 30, cy);

    [d1, d2].forEach((d) => {
        const angle = Math.random() * 2 * Math.PI;
        const speed = Math.max(CONFIG$1.speed, 0.5);
        d.dx = Math.cos(angle) * speed;
        d.dy = Math.sin(angle) * speed;
        d.followTimer = 0; // start moving immediately
    });

    requestAnimationFrame(() => startSimulation());
}

// ------------------------------------------------------------
// Reset simulation
// ------------------------------------------------------------
function resetSimulation() {
    state.digits = []; // clear all digits
    initStartingDigits();
    state.resetStartTime = Date.now();
    state.epochCumulativeCounts = createCountsObject();
}

// ------------------------------------------------------------
// Main simulation loop
// ------------------------------------------------------------
let lastTime = performance.now();

function tickSimulation(now = performance.now()) {
    if (!state.paused) {
        const deltaTime = (now - lastTime) / 1000;
        lastTime = now;

        const frameIncrement = deltaTime * CONFIG$1.FPS;

        // Clear attractor debug if needed
        if (CONFIG$1.showAttractorLines && attractorDebugCtx) {
            attractorDebugCtx.clearRect(
                0,
                0,
                attractorDebugCtx.canvas.width,
                attractorDebugCtx.canvas.height
            );
        }

        // Update all digits
        for (const d of [...state.digits]) {
            d.age += frameIncrement;

            updateDigitPosition(d);

            if (CONFIG$1.enableAttractor) {
                applyAttractor(d);
            }

            if (d.age > CONFIG$1.maxAge) killDigit(d);
        }

        // Handle reproduction
        reproduce();

        // Reset if no digits remain
        if (state.digits.length === 0) resetSimulation();

        // Update stats UI (DOM)
        updateStats();

        // Render all digits on canvas
        renderAll(state.digits);
    }

    // Continue animation loop
    if (state.running) {
        state.animationFrameId = requestAnimationFrame(tickSimulation);
    }
}

// ------------------------------------------------------------
// Simulation control functions
// ------------------------------------------------------------
function startSimulation() {
    if (!state.running) {
        state.running = true;
        state.paused = false;

        if (state.digits.length === 0) resetSimulation();

        tickSimulation();
    }
}

function stopSimulation() {
    state.running = false;
    state.paused = false;
    if (state.animationFrameId) {
        cancelAnimationFrame(state.animationFrameId);
        state.animationFrameId = null;
    }
}

log(`[${moduleTag(import.meta)}] loaded`);

// ============================================================
// PARAMETERS MODULE
// ============================================================


// ============================================================
// PARAMETER SLIDER MANAGER
// ============================================================

class ParameterManager {
    constructor() {
        this.sliders = new Map();
        this.container = null;
        this.initialized = false;
    }

    /**
     * Initialize the parameter manager
     * Call this once after the DOM is ready
     */
    init() {
        if (this.initialized) return;

        this.container = document.getElementById("paramSlidersContainer");
        if (!this.container) {
            console.warn("Parameter sliders container not found yet, retrying...");
            return false; // signal that init failed
        }

        this.initialized = true;
        log(`[${moduleTag(import.meta)}] loaded`);        
        return true;
    }

    /**
     * Add a new parameter slider
     */
    addSlider(SLIDERCONFIG) {
        if (!this.initialized) {
            console.error("Parameter manager not initialized. Call init() after DOM ready.");
            return;
        }

        const { id, label, target, property, min, max, step = 1, onChange = null, format = null } = SLIDERCONFIG;

        if (this.sliders.has(id)) {
            console.warn(`Slider with id "${id}" already exists`);
            return;
        }

        const currentValue = target[property];
        const formatValue = format || ((v) => v);

        // Create slider wrapper
        const sliderWrapper = document.createElement("div");
        sliderWrapper.className = "param-slider-wrapper";
        sliderWrapper.dataset.sliderId = id;

        sliderWrapper.innerHTML = `
            <div class="param-slider-label">${label}</div>
            <div class="param-slider-value" id="paramValue-${id}">${formatValue(currentValue)}</div>
            <div class="param-slider-track">
                <input 
                    type="range" 
                    id="paramSlider-${id}"
                    class="param-slider"
                    min="${min}"
                    max="${max}"
                    step="${step}"
                    value="${currentValue}"
                />
            </div>
            <div class="param-slider-range">${min} - ${max}</div>
        `;

        this.container.appendChild(sliderWrapper);

        const slider = document.getElementById(`paramSlider-${id}`);
        const valueDisplay = document.getElementById(`paramValue-${id}`);

        this.sliders.set(id, { element: slider, valueDisplay, wrapper: sliderWrapper, target, property, format: formatValue, onChange });

        slider.addEventListener("input", (e) => {
            const value = parseFloat(e.target.value);
            target[property] = value;
            valueDisplay.textContent = formatValue(value);
            if (onChange) onChange(value);
        });

        log(`Added slider: ${id}`);
    }

    updateValue(id, value) {
        const sliderData = this.sliders.get(id);
        if (!sliderData) return;
        sliderData.element.value = value;
        sliderData.target[sliderData.property] = value;
        sliderData.valueDisplay.textContent = sliderData.format(value);
    }

    getValue(id) {
        const sliderData = this.sliders.get(id);
        return sliderData ? parseFloat(sliderData.element.value) : null;
    }
}

// ============================================================
// SINGLETON INSTANCE
// ============================================================

const parameterManager = new ParameterManager();

// ============================================================
// SAFE INITIALIZATION FUNCTION
// ============================================================

/**
 * Call this after DOMContentLoaded and after modals are loaded
 */
function initializeParameters() {
    // Retry init if container not yet available
    if (!parameterManager.init()) {
        // Retry on next animation frame
        requestAnimationFrame(initializeParameters);
        return;
    }

    // --- Speed ---
    parameterManager.addSlider({
        id: "speed",
        label: "Speed",
        target: CONFIG$1,
        property: "speed",
        min: 1,
        max: 10,
        step: 0.1,
        //onChange: (v) => log(`Speed changed to ${v}s`),
    });

    // --- Max Age ---
    parameterManager.addSlider({
        id: "maxAgeSec",
        label: "Max Age",
        target: CONFIG$1,
        property: "maxAgeSec",
        min: 5,
        max: 50,
        step: 1,
        //onChange: (v) => log(`Max age changed to ${v}s`),
    });

    // --- Search Radius ---
    parameterManager.addSlider({
        id: "searchRadius",
        label: "Search Radius",
        target: CONFIG$1,
        property: "attractionRadius",
        min: 100,
        max: 500,
        step: 10,
        //onChange: (v) => log(`attractionRadius changed to ${v}`),
    });

    // --- Digit Size ---
    parameterManager.addSlider({
        id: "digitSize",
        label: "Digit Size",
        target: CONFIG$1,
        property: "digitSize",
        min: 20,
        max: 80,
        step: 1,
        //onChange: (v) => log(`Digit size changed to ${v}`),
    });

    // --- Population Cap ---
    parameterManager.addSlider({
        id: "POP_CAP",
        label: "Population Cap",
        target: CONFIG$1,
        property: "POP_CAP",
        min: 12,
        max: 144,
        step: 12,
        //onChange: (v) => log(`Population cap set to ${v}`),
    });
}

// ============================================================
// UI MODULE
// ============================================================








// ============================================================
// MODALS LOADER
// ============================================================
function loadModals() {
    return new Promise((resolve) => {
        const modalsContainer = document.getElementById("modalsContainer");
        if (!modalsContainer) return resolve();

        fetch("/run/modals.html")
            .then((res) => {
                if (!res.ok) throw new Error("Failed to load modals");
                return res.text();
            })
            .then((html) => {
                modalsContainer.innerHTML = html;
                resolve();
            })
            .catch((err) => {
                console.error(err);
                modalsContainer.innerHTML = `<p>Error loading modals.</p>`;
                resolve();
            });
    });
}






// ============================================================
// ABOUT TEXT LOADER
// ============================================================
function loadAboutText() {
    return new Promise((resolve) => {
        const aboutContainer = document.getElementById("aboutContent");
        if (!aboutContainer) return resolve();

        fetch("/about.html")
            .then((res) => {
                if (!res.ok) throw new Error("Failed to load about text");
                return res.text();
            })
            .then((html) => {
                aboutContainer.innerHTML = html;
                resolve();
            })
            .catch((err) => {
                console.error(err);
                aboutContainer.innerHTML = `<p>Error loading about text.</p>`;
                resolve();
            });
    });
}











// ============================================================
// MODAL FUNCTIONS
// ============================================================
let topZIndex = 10000;

function bringToFront(modal) {
    modal.style.zIndex = ++topZIndex;
}

function toggleModal(id, show) {
    const modal = document.getElementById(id);
    const overlay = document.getElementById(id.replace("Modal", "Overlay"));
    if (modal) {
        modal.style.display = show ? "flex" : "none";
        if (show) bringToFront(modal); // bring frontmost when opened
    }
    if (overlay) overlay.style.display = show ? "block" : "none";
}

function makeDraggable(modal, handle) {
    let offsetX = 0,
        offsetY = 0;
    modal.draggingHeader = false;

    handle.addEventListener("mousedown", (e) => {
        if (e.target.classList.contains("modal-close")) return;
        modal.draggingHeader = true;
        const rect = modal.getBoundingClientRect();
        offsetX = e.clientX - rect.left;
        offsetY = e.clientY - rect.top;
        document.addEventListener("mousemove", drag);
        document.addEventListener("mouseup", stopDrag);
        bringToFront(modal); // frontmost when dragging
    });

    function drag(e) {
        if (!modal.draggingHeader) return;
        modal.style.left = e.clientX - offsetX + "px";
        modal.style.top = e.clientY - offsetY + "px";
    }

    function stopDrag() {
        modal.draggingHeader = false;
        document.removeEventListener("mousemove", drag);
        document.removeEventListener("mouseup", stopDrag);
    }
}










// ============================================================
// BLOCK CLICKS INSIDE MODALS
// ============================================================
function blockModalClicks() {
    document.querySelectorAll(".modal-window").forEach((modal) => {
        modal.addEventListener("click", (e) => {
            if (!modal.draggingHeader) {
                bringToFront(modal); // frontmost on click
                e.stopPropagation();
            }
        });
        modal.addEventListener("mousedown", (e) => {
            if (!modal.draggingHeader) e.stopPropagation();
        });
        modal.addEventListener("mouseup", (e) => {
            if (!modal.draggingHeader) e.stopPropagation();
        });
    });
}













// ============================================================
// MODAL MANAGER
// ============================================================
function setupModalManager() {
    // Open modal buttons inside modals
    document.querySelectorAll('[data-action="openModal"]').forEach((btn) => {
        btn.addEventListener("click", () => {
            toggleModal(btn.dataset.target, true); // frontmost handled in toggleModal
        });
    });

    // Close buttons
    document.querySelectorAll(".modal-close").forEach((btn) => {
        btn.addEventListener("click", (e) => {
            toggleModal(btn.dataset.modal, false);
            e.stopPropagation();
        });
    });

    // Stats bar toggle
    const statsToggle = document.querySelector(
        '[data-action="toggleStatsBar"]'
    );
    if (statsToggle) {
        statsToggle.addEventListener("click", () => {
            const bar = document.getElementById("statsBar");
            const indicator = document.getElementById("statsBarIndicator");
            const visible = bar.style.display === "flex";
            bar.style.display = visible ? "none" : "flex";
            indicator.textContent = visible ? "OFF" : "ON";
            indicator.style.color = visible ? "#666" : "#4CAF50";
        });
    }

    // Make modals draggable
    document.querySelectorAll(".modal-window").forEach((modal) => {
        const header = modal.querySelector(".modal-header");
        if (header) makeDraggable(modal, header);
    });

    // Top-level menu buttons
    const menuBtn = document.getElementById("menuBtn");
    const aboutBtn = document.getElementById("aboutBtn");
    const pauseBtn = document.getElementById("pauseBtn");
    const restartBtn = document.getElementById("restartBtn");

    if (menuBtn)
        menuBtn.addEventListener("click", () => toggleModal("menuModal", true));
    if (aboutBtn)
        aboutBtn.addEventListener("click", () =>
            toggleModal("aboutModal", true)
        );

    if (pauseBtn) {
        pauseBtn.addEventListener("click", function () {
            state.paused = !state.paused;
            this.innerHTML = state.paused ? "<span class='icon'>▶</span><span class='label'> Resume": "<span class='icon'>⏸</span><span class='label'> Pause";
            this.classList.toggle("paused", state.paused);
        });
    }

    if (restartBtn) {
        restartBtn.addEventListener("click", () => {
            stopSimulation();
            resetSimulation();
            updateStats();
            startSimulation();
            if (pauseBtn) {
                pauseBtn.textContent = "⏸ Pause";
                pauseBtn.classList.remove("paused");
            }
        });
    }
    if (isMobile()) {
        // Hide non-essential buttons on mobile
        const menuDivider = document.getElementById("desktopMenuDivider");
        if (menuDivider) menuDivider.style.display = "none";
        if (menuBtn) menuBtn.style.display = "none";
        if (aboutBtn) aboutBtn.style.display = "none";
        const projectTitle = document.getElementById("projectTitle");
        if (projectTitle)
            projectTitle.style.cursor = "pointer"; 
            projectTitle.addEventListener("click", () =>
                toggleModal("aboutModal", true)
            );
    }
}









// ============================================================
// READY PROMISE
// ============================================================
const uiReady = new Promise(async (resolve) => {
    if (document.readyState === "loading") {
        // DOM not yet ready — wait for it
        document.addEventListener("DOMContentLoaded", async () => {
            await loadModals();
            setupModalManager();
            initGraph();
            initStatsBar();
            initializeParameters();
            blockModalClicks();
            await loadAboutText();
            log("UI fully initialized");
            resolve();
        });
    } else {
        // DOM already ready — just run immediately
        await loadModals();
        setupModalManager();
        initGraph();
        initStatsBar();
        initializeParameters();
        blockModalClicks();
        await loadAboutText();
        log("UI fully initialized");
        resolve();
    }
});


log(`[${moduleTag(import.meta)}] loaded`);

// ============================================================
// MAIN ENTRY POINT (Canvas-Ready)
// ============================================================


// ============================================================
// DETECT MOBILE
// ============================================================
function isMobile() {
    return /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
}

// ============================================================
// LOADING SCREEN HANDLERS
// ============================================================
function showLoadingScreen() {
    let loader = document.getElementById("warmup-loader");
    if (!loader) {
        loader = document.createElement("div");
        loader.id = "warmup-loader";
        Object.assign(loader.style, {
            position: "fixed",
            inset: "0",
            background: "rgba(0,0,0,1)",
            color: "#fff",
            display: "flex",
            justifyContent: "center",
            alignItems: "center",
            fontSize: "24px",
            fontFamily: "monospace",
            zIndex: 20000,
            flexDirection: "column",
        });

        const message = document.createElement("div");
        message.id = "warmup-loader-message";
        message.style.width = "20ch";
        message.style.textAlign = "center";
        message.textContent = "Initializing... 0%";
        loader.appendChild(message);

        document.body.appendChild(loader);
    }
    loader.style.display = "flex";
}

function updateLoadingMessage(message) {
    const msgEl = document.getElementById("warmup-loader-message");
    if (msgEl) msgEl.textContent = message;
}

function hideLoadingScreen() {
    const loader = document.getElementById("warmup-loader");
    if (loader) loader.style.display = "none";
}

// ============================================================
// CANVAS INITIALIZATION
// ============================================================
let canvas, ctx;

function initCanvas() {
    canvas = document.getElementById("dslCanvas");
    if (!canvas) {
        canvas = document.createElement("canvas");
        canvas.id = "dslCanvas";
        canvas.style.position = "fixed";
        canvas.style.top = "0";
        canvas.style.left = "0";
        canvas.style.zIndex = "1"; // behind UI overlays
        document.body.appendChild(canvas);
    }
    ctx = canvas.getContext("2d");

    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    window.addEventListener("resize", resizeCanvas);
    resizeCanvas();

    return { canvas, ctx };
}

// ============================================================
// INITIALIZATION
// ============================================================
async function init() {
    showLoadingScreen();

    const mobile = isMobile();

    if (!mobile) {
        setupModalManager();
        await uiReady;
        initAttractor();
        log("Desktop viewer mode");
    } else {
        document.body.classList.add("mobile");
        log("Mobile viewer mode");
    }

    // Fade out everything except loader
    const contentElements = Array.from(document.body.children).filter(
        (el) => el.id !== "warmup-loader"
    );
    contentElements.forEach((el) => (el.style.opacity = "0"));

    // --- CANVAS & RENDERER INITIALIZATION ---
    initCanvas();           // create/resizes <canvas>
    initCanvasRenderer();   // sets ctx for canvasRenderer
    initSimulationCanvas(); // sets ctx for controls

    // --- START SIMULATION ---
    startSimulation();

    // Warm-up animation
    let warmupTicks = 0;
    const warmupDuration = 30;

    const checkWarmup = () => {
        warmupTicks++;
        const progress = Math.floor((warmupTicks / warmupDuration) * 100);
        updateLoadingMessage(`Initializing... ${progress}%`);

        if (warmupTicks >= warmupDuration) {
            contentElements.forEach((el) => {
                el.style.transition = "opacity 0.3s";
                el.style.opacity = "1";
            });
            hideLoadingScreen();
            console.log("WELCOME TO DIGITAL SEX LIFE 2025");
        } else {
            requestAnimationFrame(checkWarmup);
        }
    };

    requestAnimationFrame(checkWarmup);
}

// ============================================================
// START UP
// ============================================================
document.addEventListener("DOMContentLoaded", async () => {
    await init();
});

export { init, isMobile };
//# sourceMappingURL=dsl.bundle.js.map
