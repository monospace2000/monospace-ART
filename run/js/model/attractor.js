// attractor.js
import { CONFIG } from "../config/config.js";
import { log, moduleTag, trace } from "../utils/utilities.js";

// create canvas for attractor debug lines
export let attractorDebugCtx;
if (CONFIG.showAttractorLines) {
    const c = document.createElement("canvas");
    c.style.position = "fixed";
    c.style.left = 0;
    c.style.top = 0;
    c.style.pointerEvents = "none";
    c.width = window.innerWidth;
    c.height = window.innerHeight;
    c.style.zIndex = 9999;
    attractorDebugCtx = c.getContext("2d");
    document.body.appendChild(c);

    // keep canvas in sync with window
    window.addEventListener("resize", () => {
        c.width = window.innerWidth;
        c.height = window.innerHeight;
    });
}

let attractor = null;
let attractorEl = null;

/**
 * Expose the current attractor position
 */
export function getAttractor() {
    return attractor;
}

/**
 * Initialize the attractor interaction
 */
export function initAttractor() {
    log("Initializing attractor...");
    if (!CONFIG.enableAttractor) return;

    // Create visual element (finger/mouse)
    attractorEl = document.createElement("div");
    Object.assign(attractorEl.style, {
        position: "fixed",
        width: CONFIG.digitSize + "px",
        height: CONFIG.digitSize + "px",
        borderRadius: "50%",
        color: "white",
        fontSize: CONFIG.digitSize + "px",
        fontWeight: "bold",
        textAlign: "center",
        lineHeight: CONFIG.digitSize + "px",
        pointerEvents: "none",
        display: "none",

        // Prevent selection / highlighting
        userSelect: "none",
        WebkitUserSelect: "none",
        MozUserSelect: "none",
        msUserSelect: "none",
        touchAction: "none",
    });
    attractorEl.innerText = "0";
    document.body.appendChild(attractorEl);

    // Prevent right-click / long-press context menu
    attractorEl.addEventListener("contextmenu", (e) => e.preventDefault());

    // Helper to update attractor position and visual
    const updateAttractor = (x, y) => {
        attractor = { x, y };
        attractorEl.style.display = "block";
        attractorEl.style.left = x - CONFIG.digitSize / 2 + "px";
        attractorEl.style.top = y - CONFIG.digitSize / 2 + "px";
    };

    // --- Mouse Events ---
    document.addEventListener("mousedown", (e) => {
        updateAttractor(e.clientX, e.clientY);
        //log('Attractor set at:', attractor);
    });

    document.addEventListener(
        "mousemove",
        (e) => attractor && updateAttractor(e.clientX, e.clientY)
    );
    document.addEventListener("mouseup", () => {
        attractor = null;
        attractorEl.style.display = "none";
    });

    // --- Touch Events ---
    document.addEventListener("touchstart", (e) => {
        const touch = e.touches[0];
        updateAttractor(touch.clientX, touch.clientY);
    });
    document.addEventListener("touchmove", (e) => {
        const touch = e.touches[0];
        if (attractor) updateAttractor(touch.clientX, touch.clientY);
    });
    document.addEventListener("touchend", () => {
        attractor = null;
        attractorEl.style.display = "none";
    });
}

/**
 * Apply attractor effect to a digit
 */
export function applyAttractor(digit) {
    if (!CONFIG.enableAttractor || !attractor) return;
    
    // Stop attracting digits that have already become zero
    if (digit.attractedZero) return;


    const dx = attractor.x - digit.x;
    const dy = attractor.y - digit.y;
    const dist = Math.sqrt(dx * dx + dy * dy);
    const radius = CONFIG.attractionRadius || 150;
    const strength = CONFIG.attractorStrength || 0.05;

    if (dist < radius) {
        const force = (radius - dist) / radius;

        // use dx/dy (always defined)
        digit.dx += dx * force * strength;
        digit.dy += dy * force * strength;

        if (CONFIG.showAttractorLines && attractorDebugCtx) {
            attractorDebugCtx.beginPath();
            attractorDebugCtx.moveTo(digit.x, digit.y);
            attractorDebugCtx.lineTo(attractor.x, attractor.y);
            attractorDebugCtx.strokeStyle = "rgba(0,255,0,0.1)";
            attractorDebugCtx.stroke();
        }

        if (dist < 20) {
            digit.name = "0";
            digit.attractedZero = true; // mark that this digit became zero via attractor
        }
    }
}

log(`[${moduleTag(import.meta)}] loaded`);
