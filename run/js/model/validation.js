import { state } from '../model/state.js';
import { log, moduleTag, trace, showConfig } from '../utils/utilities.js';
/**
 * Check if a digit is still active in the global state array
 * @param {Object} d
 * @returns {boolean}
 */
export function isAlive(d) {
    return d && state.digits.includes(d);
}

/**
 * Detect and optionally clean up invalid relationships.
 * @param {Object} d - Digit to validate.
 * @param {boolean} fix - Whether to fix broken links automatically.
 */
export function validateBonds(d, fix = true) {
    let invalid = false;

    if (d.bondedTo && !isAlive(d.bondedTo)) {
        invalid = true;
        if (fix) d.bondedTo = null;
    }

    if (d.mother && !isAlive(d.mother)) {
        invalid = true;
        if (fix) d.mother = null;
    }

    if (d.children && Array.isArray(d.children)) {
        d.children = d.children.filter(isAlive);
    }

    if (invalid) {
        console.warn('💀 Ghost bond cleared for digit:', d.id || d);
    }
}

log(`[${moduleTag(import.meta)}] loaded`);
