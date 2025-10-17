import { CONFIG } from '../config/config.js';
import { log, moduleTag, trace } from '../utils/utilities.js';
import {
    VISUALS,
    updateDigitAppearance,
    GLOW_SETTINGS,
    SHADOW_SETTINGS,
    AGE_INDICATOR_SETTINGS,
    SEARCH_RADIUS_SETTINGS,
    ATTRACTION_LINE_SETTINGS,
    BLUR_SETTINGS,
    CHILD_BOND_LINE_SETTINGS,
    BOND_LINE_SETTINGS,
} from './render.js';

// ============================================================
// VISUAL OVERLAY FUNCTIONS
// ============================================================

/**
 * Render glow effect around a digit
 * Creates a separate outer glow that doesn't interfere with globe transparency
 * Uses multiple layers and color boosting for enhanced visibility
 * @param {Object} d - Digit object
 * @param {number} size - Digit size
 */
export function renderGlow(d, size) {
    if (!VISUALS.glow.enabled) return;

    const props = updateDigitAppearance(d);
    const rgb = props.color.match(/\d+/g).map(Number);

    // Boost color intensity
    const boostedR = Math.min(
        255,
        Math.floor(rgb[0] * GLOW_SETTINGS.colorBoost)
    );
    const boostedG = Math.min(
        255,
        Math.floor(rgb[1] * GLOW_SETTINGS.colorBoost)
    );
    const boostedB = Math.min(
        255,
        Math.floor(rgb[2] * GLOW_SETTINGS.colorBoost)
    );

    ctx.save();

    // Draw multiple layers for increased intensity
    for (let layer = 0; layer < GLOW_SETTINGS.layers; layer++) {
        // Draw multiple rings with decreasing opacity for smooth glow
        const rings = 20;
        for (let i = 0; i < rings; i++) {
            const progress = i / rings;
            // const radius = size / 2 + GLOW_SETTINGS.strength * progress;
            const radius =
                size / 2 +
                (GLOW_SETTINGS.strength * progress * size) / CONFIG.digitSize;
            const alpha =
                (GLOW_SETTINGS.opacity * (1 - progress) * props.opacity) /
                rings;

            ctx.fillStyle = `rgba(${boostedR},${boostedG},${boostedB},${alpha})`;
            ctx.beginPath();
            ctx.arc(d.x, d.y, radius, 0, Math.PI * 2);
            ctx.fill();
        }
    }
    ctx.restore();
}

/**
 * Render drop shadow effect around a digit
 * Creates a separate outer shadow that doesn't interfere with globe transparency
 * @param {Object} d - Digit object
 * @param {number} size - Digit size
 */
export function renderDropShadow(d, size) {
    // Uncomment the VISUALS check when enabling shadows
    // if (!VISUALS.shadows.enabled) return;

    ctx.save();
    ctx.shadowColor = SHADOW_SETTINGS.color;
    ctx.shadowOffsetX = SHADOW_SETTINGS.offsetX;
    ctx.shadowOffsetY = SHADOW_SETTINGS.offsetY;
    ctx.shadowBlur = SHADOW_SETTINGS.blur;

    // Draw invisible circle that casts the shadow
    ctx.fillStyle = 'transparent';
    ctx.beginPath();
    ctx.arc(d.x, d.y, size / 2, 0, Math.PI * 2);
    ctx.fill();
    ctx.closePath();

    ctx.restore();
}

/**
 * Render outline around a digit
 * @param {Object} props - Visual properties from updateDigitAppearance
 */
export function renderOutline(props) {
    if (!VISUALS.outlines.enabled) return;

    ctx.lineWidth = props.outlineWidth;
    ctx.strokeStyle = props.outlineColor;
    ctx.stroke();
}

/**
 * Render age indicator ring around a digit
 * Shows remaining lifespan as a circular progress bar
 * @param {Object} d - Digit object
 * @param {number} size - Digit size
 * @param {Object} props - Visual properties
 */
export function renderAgeIndicator(d, size, props) {
    if (!VISUALS.ageIndicator.enabled) return;

    // Calculate ring radius (outside outline)
    const r = size / 2 + props.outlineWidth * 1.2;

    // Calculate age progression and remaining life
    const ageRatio = Math.min(d.age / CONFIG.maxAge, 1);
    const remaining = 1 - ageRatio;

    // Helper to parse RGB from rgba/rgb string
    const parseRGBA = (str) => {
        const m = str.match(/rgba?\((\d+),\s*(\d+),\s*(\d+),?\s*([\d.]*)\)/i);
        return m
            ? [parseInt(m[1]), parseInt(m[2]), parseInt(m[3])]
            : [255, 255, 255];
    };

    // Get color values for different life stages
    const [rY, gY, bY] = parseRGBA(AGE_INDICATOR_SETTINGS.colors.young);
    const [rM, gM, bM] = parseRGBA(AGE_INDICATOR_SETTINGS.colors.mature);
    const [rO, gO, bO] = parseRGBA(AGE_INDICATOR_SETTINGS.colors.old);

    // Select color based on current life stage
    let rC, gC, bC;
    if (d.age < CONFIG.matureAge) {
        [rC, gC, bC] = [rY, gY, bY];
    } else if (d.age < CONFIG.oldAge) {
        [rC, gC, bC] = [rM, gM, bM];
    } else {
        [rC, gC, bC] = [rO, gO, bO];
    }

    // Draw arc showing remaining life (counter-clockwise from top)
    ctx.save();
    ctx.beginPath();
    ctx.lineWidth = AGE_INDICATOR_SETTINGS.width;
    ctx.strokeStyle = `rgba(${rC},${gC},${bC},${props.opacity})`;
    ctx.arc(
        d.x,
        d.y,
        r,
        -Math.PI / 2,
        -Math.PI / 2 - Math.PI * 2 * remaining,
        true
    );
    ctx.stroke();
    ctx.closePath();
    ctx.restore();
}

/**
 * Render search radius circle for mature males
 * Shows the range in which males can detect potential mates
 * @param {Object} d - Digit object
 */
export function renderSearchRadius(d) {
    if (!VISUALS.searchRadius.enabled) return;
    if (d.sex !== 'M' || d.age < CONFIG.matureAge) return;

    ctx.save();
    ctx.fillStyle = `rgba(0,0,255,${SEARCH_RADIUS_SETTINGS.fillOpacity})`;
    ctx.strokeStyle = SEARCH_RADIUS_SETTINGS.color;
    ctx.lineWidth = SEARCH_RADIUS_SETTINGS.lineWidth;
    ctx.beginPath();
    ctx.arc(d.x, d.y, CONFIG.attractionRadius, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.closePath();
    ctx.restore();
}

/**
 * Render attraction line from male to potential mate
 * Shows active pursuit behavior
 * @param {Object} d - Digit object (male)
 * @param {Set} activeSet - Set of all active digits for validation
 */
export function renderAttractionLine(d, activeSet) {
    if (!VISUALS.attractionLines.enabled) return;
    if (d.sex !== 'M') return;
    if (d.age < CONFIG.matureAge || d.age >= CONFIG.oldAge) return;
    if (!d.attractionTarget) return;

    const target = d.attractionTarget;

    // Validate target is still viable
    const validTarget =
        target &&
        activeSet.has(target) &&
        target.sex === 'F' &&
        target.age >= CONFIG.matureAge &&
        target.age < CONFIG.oldAge &&
        target.gestationTimer === 0 &&
        !target.bondedTo;

    if (!validTarget) return;

    // Draw line from male to female
    ctx.save();
    ctx.strokeStyle = ATTRACTION_LINE_SETTINGS.maleToFemale.color;
    ctx.lineWidth = ATTRACTION_LINE_SETTINGS.maleToFemale.width;
    ctx.beginPath();
    ctx.moveTo(d.x, d.y);
    ctx.lineTo(target.x, target.y);
    ctx.stroke();
    ctx.restore();
}

// ============================================================
// BOND RENDERING
// ============================================================

/**
 * Render all bond lines between digits
 * Includes mate bonds (green) and child-mother bonds (orange)
 * @param {Array} digits - Array of all digits
 */
export function renderBonds(digits) {
    if (!ctx) return;

    ctx.save();
    ctx.lineCap = 'round';

    const activeDigits = digits.filter((d) => d);
    const activeSet = new Set(activeDigits);

    for (const d of activeDigits) {
        // Render mate bonds
        if (VISUALS.bonds.enabled && d.bondedTo && activeSet.has(d.bondedTo)) {
            const other = d.bondedTo;
            const fade1 =
                d.age < CONFIG.oldAge
                    ? 1
                    : 1 -
                      (d.age - CONFIG.oldAge) / (CONFIG.maxAge - CONFIG.oldAge);
            const fade2 =
                other.age < CONFIG.oldAge
                    ? 1
                    : 1 -
                      (other.age - CONFIG.oldAge) /
                          (CONFIG.maxAge - CONFIG.oldAge);
            const alpha = Math.min(fade1, fade2);

            ctx.strokeStyle = BOND_LINE_SETTINGS.color.replace(
                /[\d.]+\)$/g,
                `${alpha})`
            );
            ctx.lineWidth = BOND_LINE_SETTINGS.lineWidth;
            ctx.beginPath();
            ctx.moveTo(d.x, d.y);
            ctx.lineTo(other.x, other.y);
            ctx.stroke();
        }

        // Render child-mother bonds
        if (
            VISUALS.childBonds.enabled &&
            d.mother &&
            activeSet.has(d.mother) &&
            d.age < CONFIG.adolescenceAge
        ) {
            const fade = 1 - d.age / CONFIG.adolescenceAge;

            // Replace alpha in existing rgba color string
            ctx.strokeStyle = CHILD_BOND_LINE_SETTINGS.color.replace(
                /[\d.]+\)$/g,
                `${fade})`
            );

            ctx.lineWidth = CHILD_BOND_LINE_SETTINGS.lineWidth;
            ctx.beginPath();
            ctx.moveTo(d.x, d.y);
            ctx.lineTo(d.mother.x, d.mother.y);
            ctx.stroke();
        }
    }

    ctx.restore();
}

log(`[${moduleTag(import.meta)}] loaded`);
