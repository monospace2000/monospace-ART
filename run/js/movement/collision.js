// ============================================================
// DIGIT COLLISION RESOLUTION
// ==================================

import { CONFIG } from '../config/config.js';
import { log, moduleTag } from '../utils/utilities.js';

/**
 * Resolve collisions between digits so they visually bounce off each other.
 * @param {Array} digits - Array of digit objects (each must have x, y, dx, dy)
 * @param {Object} options - Configurable parameters
 */
export function resolveDigitCollisions(digits, options = {}) {
    const {
        bounceFactor = 0.8, // 0 = inelastic, 1 = fully elastic
        minSeparation = 0, // extra space between digits
        maxIterations = 2, // how many passes to avoid tunneling
        applyVelocity = true, // whether to adjust dx/dy for bounce
    } = options;

    const n = digits.length;
    for (let iter = 0; iter < maxIterations; iter++) {
        for (let i = 0; i < n; i++) {
            const A = digits[i];
            if (!A) continue;
            const radiusA = (CONFIG.digitSize * (A.scale || 1)) / 2;

            for (let j = i + 1; j < n; j++) {
                const B = digits[j];
                if (!B) continue;
                const radiusB = (CONFIG.digitSize * (B.scale || 1)) / 2;

                const dx = B.x - A.x;
                const dy = B.y - A.y;
                const dist = Math.hypot(dx, dy);
                const minDist = radiusA + radiusB + minSeparation;

                if (dist < minDist && dist > 0) {
                    // --- Push them apart ---
                    const overlap = minDist - dist;
                    const nx = dx / dist;
                    const ny = dy / dist;

                    // Move both digits away equally
                    A.x -= (overlap / 2) * nx;
                    A.y -= (overlap / 2) * ny;
                    B.x += (overlap / 2) * nx;
                    B.y += (overlap / 2) * ny;

                    // --- Optionally adjust velocities for bounce ---
                    if (applyVelocity) {
                        const relVelX = B.dx - A.dx;
                        const relVelY = B.dy - A.dy;
                        const dot = relVelX * nx + relVelY * ny;

                        const impulse = dot * bounceFactor;
                        A.dx += impulse * nx;
                        A.dy += impulse * ny;
                        B.dx -= impulse * nx;
                        B.dy -= impulse * ny;
                    }
                }
            }
        }
    }
}

// ============================================================
// SOFT REPULSION DIGIT COLLISION
// ============================================================

/**
 * Apply soft repulsion between digits to avoid visual overlap.
 * Uses a spring-like force for smooth separation.
 * @param {Array} digits - Array of digit objects (must have x, y, dx, dy)
 * @param {Object} options - Configurable parameters
 */
export function resolveDigitCollisionsSoft(digits, options = {}) {
    const {
        minSeparation = 2, // minimum spacing between digits
        stiffness = 0.05, // spring strength (0 = soft, 1 = stiff)
        damping = 0.85, // reduces velocity oscillation
        maxForce = 5, // cap on separation per frame
    } = options;

    const n = digits.length;

    for (let i = 0; i < n; i++) {
        const A = digits[i];
        if (!A) continue;
        const radiusA = (CONFIG.digitSize * (A.scale || 1)) / 2;

        for (let j = i + 1; j < n; j++) {
            const B = digits[j];
            if (!B) continue;
            const radiusB = (CONFIG.digitSize * (B.scale || 1)) / 2;

            const dx = B.x - A.x;
            const dy = B.y - A.y;
            const dist = Math.hypot(dx, dy);
            const minDist = radiusA + radiusB + minSeparation;

            if (dist < minDist && dist > 0) {
                const overlap = minDist - dist;

                // --- Calculate spring force ---
                const force = Math.min(overlap * stiffness, maxForce);

                const nx = dx / dist;
                const ny = dy / dist;

                // Apply velocities proportionally
                const fx = force * nx;
                const fy = force * ny;

                A.dx -= fx * 0.5;
                A.dy -= fy * 0.5;
                B.dx += fx * 0.5;
                B.dy += fy * 0.5;

                // Apply damping to reduce oscillation
                A.dx *= damping;
                A.dy *= damping;
                B.dx *= damping;
                B.dy *= damping;
            }
        }
    }
}

log(`[${moduleTag(import.meta)}] loaded`);
