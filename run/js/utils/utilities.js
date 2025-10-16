// ============================================================
// UTILITIES
// ============================================================

// --- Logging ---
export function log(...messages) {
    if (window.DEV) console.log(...messages);
}

// --- Log all public config params ---
export function showConfig() {
    log('=== CONFIG PARAMETERS ===');
    for (const key of Object.keys(CONFIG)) {
        if (key.startsWith('_')) continue;
        const value = CONFIG[key];
        if (typeof value !== 'function') log(key, ':', value);
    }
    log('========================');
}

// --- Module path helpers ---
export function moduleTag(meta) {
    // Returns e.g. "modules/movement.js"
    const parts = meta.url.split('/');
    return parts.slice(-2).join('/');
}

export function trace(meta, fnName) {
    // Returns e.g. "[modules/movement.js → updateDigitPosition]"
    const file = moduleTag(meta);
    return `[${file}${fnName ? ` → ${fnName}` : ''}]`;
}

// --- Example usage ---
// import { log, moduleTag, trace } from "../utils/utilities.js";
// log(`[${moduleTag(import.meta)}] loaded`);
// log(`${trace(import.meta, "updateDigitPosition")} started`);

log(`[${moduleTag(import.meta)}] loaded`);
