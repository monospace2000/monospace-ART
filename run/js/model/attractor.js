// attractor.js
import { CONFIG } from '../config/config.js';
import { state } from './state.js'; // for children lookup
import { log, moduleTag } from '../utils/utilities.js';

// --- Debug canvas for lines ---
let debugCtx = null;
if (CONFIG.showAttractorOverlay) {
    const c = document.createElement('canvas');
    c.style.position = 'fixed';
    c.style.left = '0';
    c.style.top = '0';
    c.style.pointerEvents = 'none';
    c.width = window.innerWidth;
    c.height = window.innerHeight;
    c.style.zIndex = 9999;
    document.body.appendChild(c);
    debugCtx = c.getContext('2d');

    window.addEventListener('resize', () => {
        c.width = window.innerWidth;
        c.height = window.innerHeight;
    });
}

// --- Attractor state ---
let attractor = {
    active: false,
    x: 0,
    y: 0,
};

export function getAttractor() {
    return attractor.active ? attractor : null;
}
// --- Draw attractor visual indicator ---
// --- Draw attractor visual indicator with radial gradient ---
let pulsePhase = 0;

export function drawAttractorDebug() {
    if (!debugCtx) return;
    if (!attractor.active) return;

    // Increment pulse animation
    pulsePhase += 0.1;
    const pulse = Math.sin(pulsePhase) * 0.5 + 0.5; // Oscillates 0-1

    debugCtx.save();

    // Draw outer radius circle with radial gradient
    if (CONFIG.attractorRadius && CONFIG.attractorRadius > 0) {
        const radiusWithPulse = CONFIG.attractorRadius + pulse * 10;

        // Create radial gradient from center to edge
        const gradient = debugCtx.createRadialGradient(
            attractor.x,
            attractor.y,
            0, // Inner circle (center)
            attractor.x,
            attractor.y,
            radiusWithPulse // Outer circle (edge)
        );

        // Center is more opaque, edges fade to transparent
        gradient.addColorStop(0, `rgba(255, 0, 0, ${0.3 + pulse * 0.1})`); // Center: 30-40% opacity
        gradient.addColorStop(0.5, `rgba(255, 0, 0, ${0.15 + pulse * 0.05})`); // Middle: 15-20% opacity
        gradient.addColorStop(0.85, `rgba(255, 0, 0, ${0.05 + pulse * 0.02})`); // Near edge: 5-7% opacity
        gradient.addColorStop(1, 'rgba(255, 0, 0, 0)'); // Edge: fully transparent

        // Fill with gradient
        debugCtx.beginPath();
        debugCtx.arc(attractor.x, attractor.y, radiusWithPulse, 0, Math.PI * 2);
        debugCtx.fillStyle = gradient;
        debugCtx.fill();

        // Optional: subtle border at the edge
        debugCtx.strokeStyle = `rgba(255, 0, 0, ${0.15 + pulse * 0.1})`;
        debugCtx.lineWidth = 1;
        //debugCtx.stroke();
    }

    /*  // Draw center point with its own gradient
    const centerSize = 10 + pulse * 3;
    const centerGradient = debugCtx.createRadialGradient(
        attractor.x,
        attractor.y,
        0,
        attractor.x,
        attractor.y,
        centerSize
    );
    centerGradient.addColorStop(0, `rgba(255, 50, 50, ${0.8 + pulse * 0.2})`); // Bright center
    centerGradient.addColorStop(0.7, `rgba(255, 0, 0, ${0.5 + pulse * 0.2})`); // Mid
    centerGradient.addColorStop(1, `rgba(255, 0, 0, ${0.2 + pulse * 0.1})`); // Edge fades

    debugCtx.beginPath();
    debugCtx.arc(attractor.x, attractor.y, centerSize, 0, Math.PI * 2);
    debugCtx.fillStyle = centerGradient;
    debugCtx.fill();

    // Crosshair for precision
    debugCtx.strokeStyle = `rgba(255, 0, 0, ${0.6 + pulse * 0.2})`;
    debugCtx.lineWidth = 1.5;

    // Horizontal line
    debugCtx.beginPath();
    debugCtx.moveTo(attractor.x - 15, attractor.y);
    debugCtx.lineTo(attractor.x + 15, attractor.y);
    debugCtx.stroke();

    // Vertical line
    debugCtx.beginPath();
    debugCtx.moveTo(attractor.x, attractor.y - 15);
    debugCtx.lineTo(attractor.x, attractor.y + 15);
    debugCtx.stroke(); */

    debugCtx.restore();
}
// --- True single check ---
function isTrueSingle(digit) {
    if (digit.bondedTo) return false;
    if (digit.mother || digit.father) return false;
    if (digit.children && digit.children.length > 0) return false;
    return true;
}

// --- Initialize attractor events ---
// --- Initialize attractor events ---
export function initAttractor(canvas) {
    if (!CONFIG.enableAttractor) return;

    const updatePosition = (clientX, clientY) => {
        // Get canvas position
        const rect = canvas.getBoundingClientRect();
        attractor.x = clientX - rect.left;
        attractor.y = clientY - rect.top;
    };

    const isOverModal = (clientX, clientY) => {
        // Check if the pointer is over any modal window
        const modals = document.querySelectorAll('.modal-window');
        for (const modal of modals) {
            if (
                modal.style.display !== 'flex' &&
                modal.style.display !== 'block'
            )
                continue;
            const rect = modal.getBoundingClientRect();
            if (
                clientX >= rect.left &&
                clientX <= rect.right &&
                clientY >= rect.top &&
                clientY <= rect.bottom
            ) {
                return true;
            }
        }
        return false;
    };

    // Listen on DOCUMENT to work through modal overlays
    // Mouse events
    document.addEventListener('mousedown', (e) => {
        // Don't activate if clicking on a modal
        if (isOverModal(e.clientX, e.clientY)) {
            return;
        }

        // Check if click is within canvas bounds
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        // Only activate if click is on canvas area
        if (x >= 0 && x <= rect.width && y >= 0 && y <= rect.height) {
            attractor.active = true;
            updatePosition(e.clientX, e.clientY);
        }
    });

    document.addEventListener('mousemove', (e) => {
        if (attractor.active) {
            // Deactivate if mouse moves over a modal
            if (isOverModal(e.clientX, e.clientY)) {
                attractor.active = false;
                return;
            }
            updatePosition(e.clientX, e.clientY);
        }
    });

    document.addEventListener('mouseup', () => {
        attractor.active = false;
    });

    // Touch events
    document.addEventListener(
        'touchstart',
        (e) => {
            const touch = e.touches[0];

            // Don't activate if touching a modal
            if (isOverModal(touch.clientX, touch.clientY)) {
                return;
            }

            // Check if touch is within canvas bounds
            const rect = canvas.getBoundingClientRect();
            const x = touch.clientX - rect.left;
            const y = touch.clientY - rect.top;

            // Only activate if touch is on canvas area
            if (x >= 0 && x <= rect.width && y >= 0 && y <= rect.height) {
                attractor.active = true;
                updatePosition(touch.clientX, touch.clientY);
                e.preventDefault(); // Prevent scrolling
            }
        },
        { passive: false }
    );

    document.addEventListener(
        'touchmove',
        (e) => {
            if (attractor.active) {
                const touch = e.touches[0];

                // Deactivate if touch moves over a modal
                if (isOverModal(touch.clientX, touch.clientY)) {
                    attractor.active = false;
                    return;
                }

                updatePosition(touch.clientX, touch.clientY);
                e.preventDefault(); // Prevent scrolling
            }
        },
        { passive: false }
    );

    document.addEventListener('touchend', () => {
        attractor.active = false;
    });
}

// --- Clear debug overlay ---
export function clearAttractorDebug() {
    if (debugCtx)
        debugCtx.clearRect(0, 0, window.innerWidth, window.innerHeight);
}

// --- Apply attractor to a digit ---
// --- Apply attractor to a digit ---
export function applyAttractor(digit) {
    if (!digit) return;
    if (!CONFIG.enableAttractor || !attractor.active) return;

    // Calculate distance to attractor
    const dx = attractor.x - digit.x;
    const dy = attractor.y - digit.y;
    const distance = Math.sqrt(dx * dx + dy * dy);

    // Skip if distance is zero (already at attractor position)
    if (distance < 0.1) return;

    // Check radius limit if configured (0 or undefined means unlimited)
    if (
        CONFIG.attractorRadius &&
        CONFIG.attractorRadius > 0 &&
        distance > CONFIG.attractorRadius
    ) {
        return;
    }

    // Calculate distance factor for falloff
    const effectiveRadius =
        CONFIG.attractorRadius > 0 ? CONFIG.attractorRadius : 1000;
    const normalizedDist = distance / effectiveRadius;
    const distanceFactor = 1 - normalizedDist * normalizedDist; // Quadratic falloff

    const spring = CONFIG.attractorStrength || 0.09;

    // Store original velocity to preserve natural movement
    const originalDx = digit.dx || 0;
    const originalDy = digit.dy || 0;
    const originalSpeed = Math.sqrt(
        originalDx * originalDx + originalDy * originalDy
    );

    // Apply spring pull (additive, not replacing)
    digit.dx = originalDx + dx * spring * distanceFactor;
    digit.dy = originalDy + dy * spring * distanceFactor;

    // Small jitter for perpetual wobble
    const jitter = 0.01;
    digit.dx += (Math.random() - 0.5) * jitter;
    digit.dy += (Math.random() - 0.5) * jitter;

    // Apply gentle damping (less aggressive)
    const damping = 0.96; // Reduced from 0.98 - less velocity loss
    digit.dx *= damping;
    digit.dy *= damping;

    // Ensure minimum velocity to prevent digits from getting "stuck"
    const currentSpeed = Math.sqrt(digit.dx * digit.dx + digit.dy * digit.dy);
    const minSpeed = CONFIG.speed * 0.1; // 10% of normal speed minimum

    if (currentSpeed < minSpeed && originalSpeed > 0) {
        // Restore some of the original direction if we've slowed too much
        const restoreFactor = 0.3; // Restore 30% of original velocity
        digit.dx += originalDx * restoreFactor;
        digit.dy += originalDy * restoreFactor;
    }

    // Max speed limiter
    const maxSpeed = CONFIG.speed * 1.5; // Allow 150% of normal speed
    const finalSpeed = Math.sqrt(digit.dx * digit.dx + digit.dy * digit.dy);
    if (finalSpeed > maxSpeed) {
        digit.dx = (digit.dx / finalSpeed) * maxSpeed;
        digit.dy = (digit.dy / finalSpeed) * maxSpeed;
    }

    // Final safety check: never let velocity reach absolute zero
    if (Math.abs(digit.dx) < 0.01 && Math.abs(digit.dy) < 0.01) {
        // Give a tiny random velocity
        const angle = Math.random() * Math.PI * 2;
        digit.dx = Math.cos(angle) * minSpeed;
        digit.dy = Math.sin(angle) * minSpeed;
    }
}

log(`[${moduleTag(import.meta)}] loaded`);
