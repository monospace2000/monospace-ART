// ============================================================
// MOVEMENT FUNCTIONS
// ============================================================

import { CONFIG } from "./config.js";
import { distance, applySpringPhysics, log } from "./utilities.js";
import { state } from "./state.js";

export function updateDigitPosition(d) {
    let moved = false;

    // -------------------------
    // Juveniles follow mother
    // -------------------------
    if (d.mother && d.age < CONFIG.adolescenceAge) {
        // Dynamic orbit around mother
        const angle = Math.random() * Math.PI * 2; 
        const radius = CONFIG.bondedOffset * (0.6 + Math.random() * 0.8);
        const targetX = d.mother.x + Math.cos(angle) * radius;
        const targetY = d.mother.y + Math.sin(angle) * radius;

        applySpringPhysics(
            d,
            targetX,
            targetY,
            CONFIG.springK,
            CONFIG.springDamping,
            CONFIG.speed * CONFIG.bondedJitter
        );

        // Subtle extra jitter for liveliness
        d.dx += (Math.random() - 0.5) * 0.2;
        d.dy += (Math.random() - 0.5) * 0.2;

        moved = true;
    }

    // -------------------------
    // Adult males bonded
    // -------------------------
    else if (d.sex === "M" && d.bondedTo) {
        // Dynamic orbit around partner
        const angle = Math.random() * Math.PI * 2;
        const radius = CONFIG.bondedOffset * (0.8 + Math.random() * 0.4);
        const targetX = d.bondedTo.x + Math.cos(angle) * radius;
        const targetY = d.bondedTo.y + Math.sin(angle) * radius;

        applySpringPhysics(
            d,
            targetX,
            targetY,
            CONFIG.springK,
            CONFIG.springDamping,
            CONFIG.speed * CONFIG.bondedJitter
        );
        moved = true;
    }

    // -------------------------
    // Adult males seeking females
    // -------------------------
    else if (
        d.sex === "M" &&
        d.age >= CONFIG.matureAge &&
        d.age < CONFIG.oldAge
    ) {
        const target = nearestFemale(d);
        if (target) {
            const dist = distance(d, target);

            // Bond if close enough
            if (
                dist <= CONFIG.mateDistance &&
                !d.bondedTo &&
                target.gestationTimer === 0
            ) {
                bondPair(d, target);
                moved = true;
            } else {
                seekTarget(d, target, dist);
                moved = true;
            }
        }
    }

    // -------------------------
    // Unbonded digits and females
    // -------------------------
    if (!moved) {
        // Apply normal jitter
        applyJitter(d);

        // Minimum motion: ensure they never completely stop
        const minSpeed = CONFIG.speed * 0.3;
        const mag = Math.hypot(d.dx, d.dy);
        if (mag < minSpeed) {
            const angle = Math.random() * Math.PI * 2;
            d.dx += Math.cos(angle) * (minSpeed * 0.5);
            d.dy += Math.sin(angle) * (minSpeed * 0.5);
        }

        // Subtle drift toward screen center to prevent "stuck" digits
        const centerX = window.innerWidth / 2;
        const centerY = window.innerHeight / 2;
        const driftStrength = 0.02;
        d.dx += (centerX - d.x) * driftStrength * 0.01;
        d.dy += (centerY - d.y) * driftStrength * 0.01;

        // Occasional random temporary wander targets
        if (!d.tempTarget && Math.random() < 0.01) {
            d.tempTarget = {
                x: Math.random() * window.innerWidth,
                y: Math.random() * window.innerHeight
            };
        }

        if (d.tempTarget) {
            applySpringPhysics(
                d,
                d.tempTarget.x,
                d.tempTarget.y,
                CONFIG.springK,
                CONFIG.springDamping,
                CONFIG.speed * 0.5
            );
            if (distance(d, d.tempTarget) < 5) d.tempTarget = null;
        }
    }

    // -------------------------
    // Clamp speed and update
    // -------------------------
    clampVelocity(d, CONFIG.speed);
    updatePosition(d);
    bounceOffWalls(d);
}

// -------------------------
// Nearest female finder
// -------------------------
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
        )
            continue;

        const dist = distance(male, f);
        if (dist < minDist) {
            minDist = dist;
            closest = f;
        }
    }
    return closest;
}

// -------------------------
// Males seeking females
// -------------------------
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

// -------------------------
// Bonding
// -------------------------
export function bondPair(male, female) {
    male.bondedTo = female;
    female.bondedTo = male;
    female.gestationTimer = CONFIG.gestation;
}

// -------------------------
// Jitter / natural motion
// -------------------------
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
    const baseSpeed = CONFIG.speed * 0.2; // minimum floor
    const speed = Math.max(baseSpeed, Math.min(CONFIG.speed * (1 + jitter), CONFIG.speed * 1.2));

    d.dx = Math.cos(angle) * speed;
    d.dy = Math.sin(angle) * speed;
}

// -------------------------
// Clamp velocity
// -------------------------
export function clampVelocity(d, maxSpeed) {
    const mag = Math.hypot(d.dx, d.dy);
    if (mag > maxSpeed) {
        d.dx = (d.dx / mag) * maxSpeed;
        d.dy = (d.dy / mag) * maxSpeed;
    }
}

// -------------------------
// Update position
// -------------------------
export function updatePosition(d) {
    d.x += d.dx;
    d.y += d.dy;
    d.element.style.left = d.x.toFixed(2) + "px";
    d.element.style.top = d.y.toFixed(2) + "px";
}

// -------------------------
// Bounce off walls
// -------------------------
export function bounceOffWalls(d) {
    const margin = 5;
    const bounds = {
        left: margin,
        right: window.innerWidth - CONFIG.digitSize - margin,
        top: 40 + margin,
        bottom: window.innerHeight - CONFIG.digitSize - margin
    };

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

    d.element.style.left = d.x.toFixed(2) + "px";
    d.element.style.top = d.y.toFixed(2) + "px";
}

// -------------------------
// Module load log
// -------------------------
log("Movement module loaded");
