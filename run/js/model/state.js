// ============================================================
// STATE MODULE
// ============================================================

import { log, moduleTag, trace } from '../utils/utilities.js';

export function createCountsObject() {
    return {
        M: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0, 6: 0, 7: 0, 8: 0, 9: 0 },
        F: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0, 6: 0, 7: 0, 8: 0, 9: 0 },
    };
}

export const state = {
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
