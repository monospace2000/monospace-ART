// ============================================================
// MOVEMENT FUNCTIONS (Canvas-Only, internal targets & wall clamping)
// ============================================================
// Handles all digit movement behaviors including:
// - Child following mother (orbital movement)
// - Bonded male orbiting female
// - Male seeking and pursuing females
// - Random jitter movement for unbonded digits
// - Wall collision detection and bouncing
// - Velocity constraints and physics updates
// ============================================================

import { CONFIG } from '../config/config.js';
import { distance, applySpringPhysics } from '../utils/helpers.js';
import { state } from './state.js';
import { updateDigitAppearance } from '../render/render.js';
import { log, moduleTag } from '../utils/utilities.js';
import { resolveDigitCollisions } from '../movement/collision.js';
import { validateBonds } from '../model/validation.js';

// ============================================================
// APPEARANCE CACHE
// ============================================================
// Cache appearance calculations to avoid redundant computation
// Cleared at the start of each frame

let appearanceCache = new WeakMap();

/**
 * Get cached appearance or calculate and cache if not present
 * @param {Object} d - Digit object
 * @returns {Object} Appearance properties
 */
export function getCachedAppearance(d) {
    if (!appearanceCache.has(d)) {
        appearanceCache.set(d, updateDigitAppearance(d));
    }
    return appearanceCache.get(d);
}

/**
 * Clear appearance cache at start of frame
 * Must be called once per frame before any appearance lookups
 */
export function clearAppearanceCache() {
    appearanceCache = new WeakMap(); // resets it clea
}

// ============================================================
// CONSTANTS
// ============================================================

// Orbital movement configuration
const ORBIT = {
    childSpeed: 0.01, // Angular velocity for children orbiting mother
    maleSpeed: 0.008, // Angular velocity for bonded males orbiting female
    childRadiusMin: 0.8, // Minimum radius multiplier for child orbit
    childRadiusMax: 0.2, // Maximum radius variation for child orbit
    maleRadiusMin: 0.85, // Minimum radius multiplier for male orbit
    maleRadiusMax: 0.15, // Maximum radius variation for male orbit
    jitter: 0.1, // Extra random movement for children
};

// Seeking behavior configuration
const SEEK = {
    blendDefault: 0.08, // Default angle interpolation rate
    blendAggressive: 0.15, // Faster angle change when actively pursuing
    speedDefault: 0.7, // Default speed multiplier
    speedAggressive: 1.0, // Full speed when actively pursuing
};

// Wall bounce configuration
const WALL = {
    margin: 5, // Extra space from edge in pixels
    topMargin: 40, // Extra margin at top (for UI elements)
    bounceDamping: 0.8, // Velocity multiplier on bounce (0.8 = 20% energy loss)
};

// Jitter movement configuration
const JITTER = {
    baseSpeed: 0.2, // Base speed multiplier for jittering
    maxSpeed: 1.2, // Maximum speed multiplier for jittering
};

// ============================================================
// BOUNDARY UTILITIES
// ============================================================

/**
 * Calculate viewport boundaries accounting for digit size
 * @param {number} radius - Half the digit's visual size
 * @returns {Object} Boundary coordinates {left, right, top, bottom}
 */
function calculateBounds(radius) {
    return {
        left: radius + WALL.margin,
        right: window.innerWidth - radius - WALL.margin,
        top: WALL.topMargin + radius + WALL.margin,
        bottom: window.innerHeight - radius - WALL.margin,
    };
}

/**
 * Clamp a target position to stay within viewport boundaries
 * Unused in current implementation but kept for potential future use
 * @param {Object} target - Target position {x, y}
 */
function clampTarget(target) {
    const margin = CONFIG.digitSize / 2 + WALL.margin;
    target.x = Math.max(margin, Math.min(target.x, window.innerWidth - margin));
    target.y = Math.max(
        margin,
        Math.min(target.y, window.innerHeight - margin)
    );
}

// ============================================================
// ORBITAL MOVEMENT
// ============================================================

/**
 * Initialize orbital movement properties if not already set
 * @param {Object} d - Digit object
 */
function initializeOrbit(d) {
    if (!d.target) d.target = { x: d.x, y: d.y };
    if (!d.orbitAngle) d.orbitAngle = Math.random() * Math.PI * 2;
}

/**
 * Update orbital position around a center point
 * @param {Object} d - Digit object
 * @param {Object} center - Center point to orbit {x, y}
 * @param {number} angularSpeed - How fast to orbit (radians per frame)
 * @param {number} radiusMin - Minimum radius multiplier
 * @param {number} radiusMax - Random radius variation
 * @param {number} speedMultiplier - Movement speed multiplier
 * @param {boolean} addJitter - Whether to add extra random movement
 */
function updateOrbit(
    d,
    center,
    angularSpeed,
    radiusMin,
    radiusMax,
    speedMultiplier,
    addJitter = false
) {
    // Increment orbital angle
    d.orbitAngle += angularSpeed;

    // Calculate orbital radius with random variation
    const radius =
        CONFIG.bondedOffset * (radiusMin + Math.random() * radiusMax);

    // Calculate target position on orbit
    d.target.x = center.x + Math.cos(d.orbitAngle) * radius;
    d.target.y = center.y + Math.sin(d.orbitAngle) * radius;

    // Apply spring physics to smoothly move toward target
    applySpringPhysics(
        d,
        d.target.x,
        d.target.y,
        CONFIG.springK * 2.0, // double stiffness
        CONFIG.springDamping * 0.7, // reduce damping slightly
        CONFIG.speed * 1.5 // boost movement speed
    );

    // Add extra jitter if requested (for children)
    if (addJitter) {
        d.dx += (Math.random() - 0.5) * ORBIT.jitter;
        d.dy += (Math.random() - 0.5) * ORBIT.jitter;
    }
}

/**
 * Handle child following mother behavior
 * Children orbit their mother until adolescence
 * @param {Object} d - Digit object (child)
 * @returns {boolean} True if movement was handled
 */
function handleChildMovement(d) {
    if (!d.mother || d.age >= CONFIG.adolescenceAge) return false;

    initializeOrbit(d);
    updateOrbit(
        d,
        d.mother,
        ORBIT.childSpeed,
        ORBIT.childRadiusMin,
        ORBIT.childRadiusMax,
        CONFIG.bondedJitter,
        true // Add extra jitter
    );

    return true;
}

/**
 * Handle bonded male orbiting female behavior
 * Bonded males orbit their mate
 * @param {Object} d - Digit object (male)
 * @returns {boolean} True if movement was handled
 */
function handleBondedMaleMovement(d) {
    if (d.sex !== 'M' || !d.bondedTo) return false;

    initializeOrbit(d);
    updateOrbit(
        d,
        d.bondedTo,
        ORBIT.maleSpeed,
        ORBIT.maleRadiusMin,
        ORBIT.maleRadiusMax,
        CONFIG.bondedJitter,
        false // No extra jitter
    );

    return true;
}

// ============================================================
// SEEKING AND MATING
// ============================================================

/**
 * Check if a female is a valid mating target
 * @param {Object} female - Female digit to check
 * @returns {boolean} True if female is available for mating
 */
function isValidMatingTarget(female) {
    return (
        female.sex === 'F' &&
        female.age >= CONFIG.matureAge &&
        female.age < CONFIG.oldAge &&
        female.gestationTimer === 0 &&
        !female.bondedTo
    );
}

/**
 * Find the nearest available female within attraction radius
 * @param {Object} male - Male digit searching for mate
 * @returns {Object|null} Nearest female or null if none found
 */
function findNearestFemale(male) {
    let minDistance = CONFIG.attractionRadius;
    let nearest = null;

    // Search through all digits for valid females
    for (const female of state.digits) {
        if (!isValidMatingTarget(female)) continue;

        const dist = distance(male, female);
        if (dist < minDistance) {
            minDistance = dist;
            nearest = female;
        }
    }

    return nearest;
}

/**
 * Create a bonded pair between male and female
 * Initiates gestation period for female
 * @param {Object} male - Male digit
 * @param {Object} female - Female digit
 */
function createBond(male, female) {
    male.bondedTo = female;
    female.bondedTo = male;
    female.gestationTimer = CONFIG.gestation;
}

/**
 * Move digit toward a target position using angle interpolation
 * @param {Object} d - Digit to move
 * @param {Object} target - Target position {x, y}
 * @param {number} dist - Distance to target
 */
function moveTowardTarget(d, target, dist) {
    // Calculate angles
    const targetAngle = Math.atan2(target.y - d.y, target.x - d.x);
    const currentAngle = Math.atan2(d.dy, d.dx);

    // Determine behavior based on digit state
    const isAggressive =
        d.sex === 'M' && !d.bondedTo && target.gestationTimer === 0;
    const blend = isAggressive ? SEEK.blendAggressive : SEEK.blendDefault;
    const speedFactor = isAggressive ? SEEK.speedAggressive : SEEK.speedDefault;

    // Interpolate angle for smooth turning
    const angle = currentAngle * (1 - blend) + targetAngle * blend;

    // Scale speed based on distance (slower when closer)
    //const approachFactor = Math.min(dist / CONFIG.attractionRadius, 1);
    const approachFactor = 1;
    const speed = CONFIG.speed * speedFactor * approachFactor;

    // Set velocity components
    d.dx = Math.cos(angle) * speed;
    d.dy = Math.sin(angle) * speed;
}

/**
 * Handle mature male seeking female behavior
 * Males actively pursue nearby females
 * @param {Object} d - Digit object (male)
 * @returns {boolean} True if movement was handled
 */
function handleSeekingMaleMovement(d) {
    if (d.sex !== 'M' || d.age < CONFIG.matureAge || d.age >= CONFIG.oldAge) {
        return false;
    }

    // Find nearest available female
    const targetFemale = findNearestFemale(d);

    if (targetFemale) {
        const dist = distance(d, targetFemale);

        // Attempt to bond if close enough
        if (
            dist <= CONFIG.mateDistance &&
            !d.bondedTo &&
            targetFemale.gestationTimer === 0
        ) {
            createBond(d, targetFemale);
        }

        // Move toward female
        if (!d.target) d.target = { x: d.x, y: d.y };
        d.target.x = targetFemale.x;
        d.target.y = targetFemale.y;
        moveTowardTarget(d, d.target, dist);

        // Store reference for rendering attraction line
        d.attractionTarget = targetFemale;
    } else {
        // No target found
        d.attractionTarget = null;
    }

    return true;
}

// ============================================================
// JITTER MOVEMENT
// ============================================================

/**
 * Apply random wandering movement to digit
 * Movement intensity varies with age
 * @param {Object} d - Digit object
 */
function applyJitterMovement(d) {
    // Calculate age-based jitter scale
    const ageRatio = d.age / d.maxAge;
    const jitterScale =
        ageRatio < 0.3
            ? CONFIG.jitterYoung
            : ageRatio < 0.7
            ? 1.0
            : CONFIG.jitterOld;

    // Add random angle change to current direction
    const currentAngle = Math.atan2(d.dy, d.dx);
    const angle =
        currentAngle +
        (Math.random() - 0.5) * CONFIG.directionJitter * jitterScale;

    // Add random speed variation
    const speedVariation =
        (Math.random() - 0.5) * CONFIG.velocityJitter * jitterScale;
    const baseSpeed = CONFIG.speed * JITTER.baseSpeed;
    const speed = Math.max(
        baseSpeed,
        Math.min(
            CONFIG.speed * (1 + speedVariation),
            CONFIG.speed * JITTER.maxSpeed
        )
    );

    // Set new velocity
    d.dx = Math.cos(angle) * speed;
    d.dy = Math.sin(angle) * speed;
}

// ============================================================
// VELOCITY CONSTRAINTS
// ============================================================

/**
 * Limit digit velocity to maximum speed
 * Prevents digits from moving too fast
 * @param {Object} d - Digit object
 * @param {number} maxSpeed - Maximum allowed speed
 */
function constrainVelocity(d, maxSpeed) {
    const magnitude = Math.hypot(d.dx, d.dy);

    // Only clamp if exceeding max speed
    if (magnitude > maxSpeed) {
        const scale = maxSpeed / magnitude;
        d.dx *= scale;
        d.dy *= scale;
    }
}

// ============================================================
// POSITION UPDATES
// ============================================================

/**
 * Update digit position based on current velocity
 * @param {Object} d - Digit object
 */
function updatePosition(d) {
    d.x += d.dx;
    d.y += d.dy;
}

/**
 * Handle wall collisions with velocity reflection
 * Keeps digits within viewport boundaries
 * @param {Object} d - Digit object
 */
function bounceOffWalls(d) {
    // Get current visual scale for accurate collision detection
    const { scale } = getCachedAppearance(d);
    const radius = (CONFIG.digitSize * scale) / 2;

    // Calculate viewport boundaries
    const bounds = calculateBounds(radius);

    // Handle horizontal walls
    if (d.x < bounds.left) {
        d.x = bounds.left;
        d.dx *= -WALL.bounceDamping;
    } else if (d.x > bounds.right) {
        d.x = bounds.right;
        d.dx *= -WALL.bounceDamping;
    }

    // Handle vertical walls
    if (d.y < bounds.top) {
        d.y = bounds.top;
        d.dy *= -WALL.bounceDamping;
    } else if (d.y > bounds.bottom) {
        d.y = bounds.bottom;
        d.dy *= -WALL.bounceDamping;
    }
}

// ============================================================
// MAIN MOVEMENT FUNCTIONS
// ============================================================

/**
 * Update a single digit's movement behavior
 * Delegates to appropriate movement handler based on digit state
 * @param {Object} d - Digit object to update
 */
export function updateDigitPosition(d) {
    // Try each movement behavior in priority order
    // Each returns true if it handled the movement
    const movementHandled =
        handleChildMovement(d) ||
        handleBondedMaleMovement(d) ||
        handleSeekingMaleMovement(d);

    // If no specialized movement applied, use random jitter
    if (!movementHandled) {
        applyJitterMovement(d);
    }

    // Ensure velocity stays within limits
    constrainVelocity(d, CONFIG.speed);
}

/**
 * Update all digits in the simulation
 * Handles movement, collision resolution, and wall bouncing
 */
export function updateAllDigits() {
    const digits = state.digits;

    // Validate relationships first (catch ghost bonds early)
    for (const d of digits) {
        if (d) validateBonds(d, true); // true = auto-fix mode
    }

    // Update individual movement behaviors
    for (const d of digits) {
        if (d) updateDigitPosition(d);
    }

    // Resolve collisions between digits
    resolveDigitCollisions(digits, {
        bounceFactor: 0.9, // Energy retention on collision
        minSeparation: 2, // Minimum space between digits
        maxIterations: 3, // Collision resolution passes
        applyVelocity: true, // Apply velocity changes
    });

    // Update positions and handle wall collisions
    for (const d of digits) {
        if (d) {
            updatePosition(d);
            bounceOffWalls(d);
        }
    }
}

// ============================================================
// EXPORTED LEGACY FUNCTIONS
// ============================================================
// These maintain backward compatibility with existing code

/**
 * @deprecated Use findNearestFemale instead
 */
export function nearestFemale(male) {
    return findNearestFemale(male);
}

/**
 * @deprecated Use moveTowardTarget instead
 */
export function seekTarget(d, target, dist) {
    moveTowardTarget(d, target, dist);
}

/**
 * @deprecated Use createBond instead
 */
export function bondPair(male, female) {
    createBond(male, female);
}

/**
 * @deprecated Use applyJitterMovement instead
 */
export function applyJitter(d) {
    applyJitterMovement(d);
}

/**
 * @deprecated Use constrainVelocity instead
 */
export function clampVelocity(d, maxSpeed) {
    constrainVelocity(d, maxSpeed);
}

// ============================================================
// MODULE LOAD MESSAGE
// ============================================================

log(`[${moduleTag(import.meta)}] loaded`);
