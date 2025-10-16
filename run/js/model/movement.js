// ============================================================
// MOVEMENT FUNCTIONS (Canvas-Only, internal targets & wall clamping)
// ============================================================

import { CONFIG } from "../config/config.js";
import { distance, applySpringPhysics } from "../utils/utilities.js";
import { state } from "./state.js";
import { updateDigitAppearance } from "../render/render.js"; // ensure this import exists
import { log, moduleTag, trace } from "../utils/utilities.js";


// Helper: keep a position inside the screen
function clampTarget(target) {
    const margin = CONFIG.digitSize / 2 + 5; // margin to avoid edges
    target.x = Math.max(margin, Math.min(target.x, window.innerWidth - margin));
    target.y = Math.max(margin, Math.min(target.y, window.innerHeight - margin));
}

// Update a single digit's position
export function updateDigitPosition(d) {
    let moved = false;

    // --- Juveniles follow mother ---
    if (d.mother && d.age < CONFIG.adolescenceAge) {
        if (!d.target) d.target = { x: d.x, y: d.y };
        if (!d.orbitAngle) d.orbitAngle = Math.random() * Math.PI * 2;

        d.orbitAngle += 0.01; 
        const radius = CONFIG.bondedOffset * (0.8 + Math.random() * 0.2);

        d.target.x = d.mother.x + Math.cos(d.orbitAngle) * radius;
        d.target.y = d.mother.y + Math.sin(d.orbitAngle) * radius;

        applySpringPhysics(
            d,
            d.target.x,
            d.target.y,
            CONFIG.springK,
            CONFIG.springDamping,
            CONFIG.speed * CONFIG.bondedJitter
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
        const radius = CONFIG.bondedOffset * (0.85 + Math.random() * 0.15);

        d.target.x = d.bondedTo.x + Math.cos(d.orbitAngle) * radius;
        d.target.y = d.bondedTo.y + Math.sin(d.orbitAngle) * radius;

        applySpringPhysics(
            d,
            d.target.x,
            d.target.y,
            CONFIG.springK,
            CONFIG.springDamping,
            CONFIG.speed * CONFIG.bondedJitter
        );

        moved = true;
    }

// Adult males seeking females
else if (d.sex === "M" && d.age >= CONFIG.matureAge && d.age < CONFIG.oldAge) {
    const targetFemale = nearestFemale(d);
    if (targetFemale) {
        const dist = distance(d, targetFemale);

        // Try to bond if in range
        if (dist <= CONFIG.mateDistance && !d.bondedTo && targetFemale.gestationTimer === 0) {
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

    clampVelocity(d, CONFIG.speed);
    updatePosition(d);
    bounceOffWalls(d);
}

// --- Helpers ---
export function nearestFemale(male) {
    let minDist = CONFIG.attractionRadius;
    let closest = null;

    for (const f of state.digits) {
        if (
            f.sex !== "F" ||
            f.age < CONFIG.matureAge ||
            f.age >= CONFIG.oldAge ||
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

export function seekTarget(d, target, dist) {
    const targetAngle = Math.atan2(target.y - d.y, target.x - d.x);
    const currentAngle = Math.atan2(d.dy, d.dx);

    let blend = 0.08;
    let speedFactor = 0.7;

    if (d.sex === "M" && !d.bondedTo && target.gestationTimer === 0) {
        blend = 0.15;
        speedFactor = 1.0;
    }

    const angle = currentAngle * (1 - blend) + targetAngle * blend;
    const approachFactor = Math.min(dist / CONFIG.attractionRadius, 1);
    const speed = CONFIG.speed * speedFactor * approachFactor;

    d.dx = Math.cos(angle) * speed;
    d.dy = Math.sin(angle) * speed;
}

export function bondPair(male, female) {
    male.bondedTo = female;
    female.bondedTo = male;
    female.gestationTimer = CONFIG.gestation;
}

export function applyJitter(d) {
    const ageRatio = d.age / CONFIG.maxAge;
    const jitterScale =
        ageRatio < 0.3
            ? CONFIG.jitterYoung
            : ageRatio < 0.7
            ? 1.0
            : CONFIG.jitterOld;

    const angle =
        Math.atan2(d.dy, d.dx) +
        (Math.random() - 0.5) * CONFIG.directionJitter * jitterScale;

    const jitter = (Math.random() - 0.5) * CONFIG.velocityJitter * jitterScale;
    const baseSpeed = CONFIG.speed * 0.2;
    const speed = Math.max(baseSpeed, Math.min(CONFIG.speed * (1 + jitter), CONFIG.speed * 1.2));

    d.dx = Math.cos(angle) * speed;
    d.dy = Math.sin(angle) * speed;
}

export function clampVelocity(d, maxSpeed) {
    const mag = Math.hypot(d.dx, d.dy);
    if (mag > maxSpeed) {
        d.dx = (d.dx / mag) * maxSpeed;
        d.dy = (d.dy / mag) * maxSpeed;
    }
}

// --- Only update the internal position (no DOM) ---
export function updatePosition(d) {
    d.x += d.dx;
    d.y += d.dy;
}


export function bounceOffWalls(d) {
    const margin = 5;

    // Get current visual scale (same as used for rendering)
    const { scale } = updateDigitAppearance(d);
    const radius = (CONFIG.digitSize * scale) / 2;

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
