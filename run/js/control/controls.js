// ============================================================
// SIMULATION CONTROL (Canvas-Ready)
// ============================================================
// Main simulation loop and control functions including:
// - Canvas initialization and setup
// - Starting digit creation and initialization
// - Simulation reset and state management
// - Main tick loop with frame timing
// - Digit lifecycle management (aging, death, reproduction)
// - Simulation control (start, stop, pause)
// ============================================================

import { state } from '../model/state.js';
import { createDigit, killDigit } from '../model/digit.js';
import { CONFIG } from '../config/config.js';
import { updateAllDigits } from '../model/movement.js';
import { createCountsObject } from '../model/state.js';
import { reproduce } from '../model/reproduction.js';
import { updateStats } from '../ui/stats.js';
import { applyAttractor } from '../model/attractor.js';
import { attractorDebugCtx } from '../model/attractor.js';
import { log, moduleTag } from '../utils/utilities.js';
import { renderAll } from '../render/render.js';
import { clearAppearanceCache } from '../model/movement.js';
// ============================================================
// MODULE STATE
// ============================================================

// Canvas references for rendering
let canvas = null;
let ctx = null;

// Frame timing for delta time calculations
let lastFrameTime = performance.now();
// ADD THESE:
// Active digits cache (recalculated once per frame)
let frameActiveDigits = [];
let frameActiveSet = null;
// ============================================================
// CONSTANTS
// ============================================================

// Initial digit positioning
const INITIAL_SETUP = {
    spacing: 30, // Distance between starting male and female
    minSpeed: 0.5, // Minimum initial movement speed
};

// ============================================================
// CANVAS INITIALIZATION
// ============================================================

/**
 * Initialize canvas element and 2D rendering context
 * Must be called before simulation can start
 * @returns {Object} Object containing canvas and context references
 * @throws {Error} If canvas element is not found in DOM
 */
export function initSimulationCanvas() {
    canvas = document.getElementById('dslCanvas');
    if (!canvas) {
        throw new Error('Canvas element not found: #dslCanvas');
    }

    ctx = canvas.getContext('2d');

    return { canvas, ctx };
}

// ============================================================
// DIGIT INITIALIZATION
// ============================================================

/**
 * Create a digit with initial velocity
 * Applies random direction and appropriate speed
 * @param {string} name - Digit identifier
 * @param {string} sex - Digit sex ('M' or 'F')
 * @param {number} x - Initial x position
 * @param {number} y - Initial y position
 * @returns {Object} Created and initialized digit
 */
function createInitialDigit(name, sex, x, y) {
    const digit = createDigit(name, sex, x, y);

    // Set random initial direction
    const angle = Math.random() * Math.PI * 2;
    const speed = Math.max(CONFIG.speed, INITIAL_SETUP.minSpeed);

    digit.dx = Math.cos(angle) * speed;
    digit.dy = Math.sin(angle) * speed;
    digit.followTimer = 0; // Start moving immediately

    return digit;
}

/**
 * Initialize simulation with starting male and female digits
 * Places them near center of viewport
 */
function initStartingDigits() {
    // Calculate center position
    const centerX = window.innerWidth / 2;
    const centerY = window.innerHeight / 2;

    // Create initial pair slightly offset from center
    const male = createInitialDigit(
        '1',
        'M',
        centerX - INITIAL_SETUP.spacing,
        centerY
    );
    const female = createInitialDigit(
        '1',
        'F',
        centerX + INITIAL_SETUP.spacing,
        centerY
    );

    // Start simulation after digits are created
    requestAnimationFrame(() => startSimulation());
}

// ============================================================
// SIMULATION RESET
// ============================================================

/**
 * Reset simulation to initial state
 * Clears all existing digits and creates new starting pair
 * Updates reset counter and timestamps for statistics
 */
export function resetSimulation() {
    // Clear all existing digits
    state.digits = [];

    // Create new starting pair
    initStartingDigits();

    // Update reset tracking (skip counter increment on first run)
    if (state.tick > 0) {
        state.resetCount++;
    }

    // Reset timing and statistics
    state.resetStartTime = Date.now();
    state.epochCumulativeCounts = createCountsObject();
}

// ============================================================
// FRAME UTILITIES
// ============================================================

/**
 * Calculate frame increment for time-independent animation
 * Converts elapsed time to frame units based on target FPS
 * @param {number} currentTime - Current timestamp from performance.now()
 * @returns {number} Frame increment multiplier
 */
function calculateFrameIncrement(currentTime) {
    const deltaSeconds = (currentTime - lastFrameTime) / 1000;
    lastFrameTime = currentTime;
    return deltaSeconds * CONFIG.FPS;
}

/**
 * Clear attractor debug visualization if enabled
 * Must be called before drawing new attractor lines
 */
function clearAttractorDebug() {
    if (CONFIG.showAttractorLines && attractorDebugCtx) {
        attractorDebugCtx.clearRect(
            0,
            0,
            attractorDebugCtx.canvas.width,
            attractorDebugCtx.canvas.height
        );
    }
}

// ============================================================
// DIGIT LIFECYCLE
// ============================================================

/**
 * Update a single digit for one frame
 * Handles aging, attractor effects, and death
 * @param {Object} digit - Digit to update
 * @param {number} frameIncrement - Time-based frame multiplier
 * @returns {boolean} True if digit was killed this frame
 */
function updateDigitLifecycle(digit, frameIncrement) {
    // Age digit based on frame time
    digit.age += frameIncrement;

    // Apply attractor force if enabled
    if (CONFIG.enableAttractor) {
        applyAttractor(digit);
    }

    // Check for death from old age
    if (digit.age > digit.maxAge) {
        killDigit(digit);
        return true; // Digit was killed
    }

    return false; // Digit still alive
}

/**
 * Update all digits in the simulation
 * Handles lifecycle, reproduction, and rendering
 * @param {number} frameIncrement - Time-based frame multiplier
 */
function updateAllDigitsLifecycle(frameIncrement) {
    // Create copy of array to safely iterate while modifying
    const digitsSnapshot = [...state.digits];

    // Update each digit's lifecycle
    for (const digit of digitsSnapshot) {
        updateDigitLifecycle(digit, frameIncrement);
    }

    // Handle reproduction for eligible pairs
    reproduce();

    // Reset simulation if all digits died
    if (state.digits.length === 0) {
        resetSimulation();
    }
}
/////////////////////////////////////////////////////////
/**
 * Prepare active digit data for current frame
 * Filters out null/dead digits and creates lookup set
 */
export function prepareFrameData() {
    frameActiveDigits = state.digits.filter((d) => d);
    frameActiveSet = new Set(frameActiveDigits);
}

/**
 * Get active digits for current frame
 * @returns {Array} Filtered array of active digits
 */
export function getActiveDigits() {
    return frameActiveDigits;
}

/**
 * Get active digit set for current frame
 * @returns {Set} Set of active digits for O(1) lookup
 */
export function getActiveSet() {
    return frameActiveSet;
}
// ============================================================
// MAIN SIMULATION LOOP
// ============================================================

/**
 * Main simulation tick - executes once per frame
 * Handles timing, updates, rendering, and loop continuation
 * @param {number} currentTime - Current timestamp from requestAnimationFrame
 */
export function tickSimulation(currentTime = performance.now()) {
    // Only update if simulation is running and not paused
    if (!state.paused) {
        state.tick++;

        // Calculate time-based frame increment
        const frameIncrement = calculateFrameIncrement(currentTime);

        // Clear caches at start of frame
        clearAppearanceCache(); // ADD THIS LINE
        // Clear debug overlays
        clearAttractorDebug();

        // ADD THIS LINE - Prepare active digits for the frame
        prepareFrameData(); // ← THIS IS MISSING!

        // Update digit lifecycles (aging, death, reproduction)
        updateAllDigitsLifecycle(frameIncrement);

        // Update digit positions and physics
        updateAllDigits();

        // Update statistics UI
        updateStats();

        // Render all digits to canvas
        //        renderAll(state.digits);
        renderAll(frameActiveDigits, frameActiveSet); // CHANGE THIS LINE
    }

    // Continue animation loop if simulation is running
    if (state.running) {
        state.animationFrameId = requestAnimationFrame(tickSimulation);
    }
}

// ============================================================
// SIMULATION CONTROL
// ============================================================

/**
 * Start the simulation
 * Initializes digits if needed and begins animation loop
 */
export function startSimulation() {
    // Only start if not already running
    if (!state.running) {
        state.running = true;
        state.paused = false;

        // Create initial digits if none exist
        if (state.digits.length === 0) {
            resetSimulation();
        }

        // Start animation loop
        tickSimulation();
    }
}

/**
 * Stop the simulation
 * Cancels animation loop and resets running state
 */
export function stopSimulation() {
    state.running = false;
    state.paused = false;

    // Cancel pending animation frame
    if (state.animationFrameId) {
        cancelAnimationFrame(state.animationFrameId);
        state.animationFrameId = null;
    }
}

// ============================================================
// MODULE LOAD MESSAGE
// ============================================================

log(`[${moduleTag(import.meta)}] loaded`);
