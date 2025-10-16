// ================================
// DIGIT MODULE (Canvas-Only)
// ================================

import { log, moduleTag, trace } from '../utils/utilities.js';
import { state } from './state.js';
import { updateDigitPosition } from './movement.js';
import { updateDigitAppearance } from '../render/render.js';
import { NUMEROLOGY, applyNumerologyTraits } from './numerology.js';
import { CONFIG } from '../config/config.js';

console.log('CONFIG in digit.js');

export function createDigit(name, sex, x, y, speedFactor = 1, mother, father) {
    const angle = Math.random() * 2 * Math.PI;
    const digit = {
        name,
        sex,
        x,
        y,
        dx: mother ? 0 : Math.cos(angle) * CONFIG.speed * speedFactor,
        dy: mother ? 0 : Math.sin(angle) * CONFIG.speed * speedFactor,
        age: 0,
        lastRepro: -Infinity,
        element: null, // <-- NO DOM element
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

export function killDigit(d) {
    state.currentCounts[d.sex][d.name] = Math.max(
        0,
        state.currentCounts[d.sex][d.name] - 1
    );

    // Only remove element if it exists (DOM mode fallback)
    if (d.element) d.element.remove();

    state.digits = state.digits.filter((x) => x !== d);
}

log(`[${moduleTag(import.meta)}] loaded`);
