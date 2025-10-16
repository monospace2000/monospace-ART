// ============================================================
// PARAMETERS MODULE
// ============================================================

import { CONFIG } from "../config/config.js";
import { log, moduleTag, trace } from "../utils/utilities.js";

// ============================================================
// PARAMETER SLIDER MANAGER
// ============================================================

class ParameterManager {
    constructor() {
        this.sliders = new Map();
        this.container = null;
        this.initialized = false;
    }

    /**
     * Initialize the parameter manager
     * Call this once after the DOM is ready
     */
    init() {
        if (this.initialized) return;

        this.container = document.getElementById("paramSlidersContainer");
        if (!this.container) {
            console.warn("Parameter sliders container not found yet, retrying...");
            return false; // signal that init failed
        }

        this.initialized = true;
        log(`[${moduleTag(import.meta)}] loaded`);        
        return true;
    }

    /**
     * Add a new parameter slider
     */
    addSlider(SLIDERCONFIG) {
        if (!this.initialized) {
            console.error("Parameter manager not initialized. Call init() after DOM ready.");
            return;
        }

        const { id, label, target, property, min, max, step = 1, onChange = null, format = null } = SLIDERCONFIG;

        if (this.sliders.has(id)) {
            console.warn(`Slider with id "${id}" already exists`);
            return;
        }

        const currentValue = target[property];
        const formatValue = format || ((v) => v);

        // Create slider wrapper
        const sliderWrapper = document.createElement("div");
        sliderWrapper.className = "param-slider-wrapper";
        sliderWrapper.dataset.sliderId = id;

        sliderWrapper.innerHTML = `
            <div class="param-slider-label">${label}</div>
            <div class="param-slider-value" id="paramValue-${id}">${formatValue(currentValue)}</div>
            <div class="param-slider-track">
                <input 
                    type="range" 
                    id="paramSlider-${id}"
                    class="param-slider"
                    min="${min}"
                    max="${max}"
                    step="${step}"
                    value="${currentValue}"
                />
            </div>
            <div class="param-slider-range">${min} - ${max}</div>
        `;

        this.container.appendChild(sliderWrapper);

        const slider = document.getElementById(`paramSlider-${id}`);
        const valueDisplay = document.getElementById(`paramValue-${id}`);

        this.sliders.set(id, { element: slider, valueDisplay, wrapper: sliderWrapper, target, property, format: formatValue, onChange });

        slider.addEventListener("input", (e) => {
            const value = parseFloat(e.target.value);
            target[property] = value;
            valueDisplay.textContent = formatValue(value);
            if (onChange) onChange(value);
        });

        log(`Added slider: ${id}`);
    }

    updateValue(id, value) {
        const sliderData = this.sliders.get(id);
        if (!sliderData) return;
        sliderData.element.value = value;
        sliderData.target[sliderData.property] = value;
        sliderData.valueDisplay.textContent = sliderData.format(value);
    }

    getValue(id) {
        const sliderData = this.sliders.get(id);
        return sliderData ? parseFloat(sliderData.element.value) : null;
    }
}

// ============================================================
// SINGLETON INSTANCE
// ============================================================

export const parameterManager = new ParameterManager();

// ============================================================
// SAFE INITIALIZATION FUNCTION
// ============================================================

/**
 * Call this after DOMContentLoaded and after modals are loaded
 */
export function initializeParameters() {
    // Retry init if container not yet available
    if (!parameterManager.init()) {
        // Retry on next animation frame
        requestAnimationFrame(initializeParameters);
        return;
    }

    // --- Speed ---
    parameterManager.addSlider({
        id: "speed",
        label: "Speed",
        target: CONFIG,
        property: "speed",
        min: 1,
        max: 10,
        step: 0.1,
        //onChange: (v) => log(`Speed changed to ${v}s`),
    });

    // --- Max Age ---
    parameterManager.addSlider({
        id: "maxAgeSec",
        label: "Max Age",
        target: CONFIG,
        property: "maxAgeSec",
        min: 5,
        max: 50,
        step: 1,
        //onChange: (v) => log(`Max age changed to ${v}s`),
    });

    // --- Search Radius ---
    parameterManager.addSlider({
        id: "searchRadius",
        label: "Search Radius",
        target: CONFIG,
        property: "attractionRadius",
        min: 100,
        max: 500,
        step: 10,
        //onChange: (v) => log(`attractionRadius changed to ${v}`),
    });

    // --- Digit Size ---
    parameterManager.addSlider({
        id: "digitSize",
        label: "Digit Size",
        target: CONFIG,
        property: "digitSize",
        min: 20,
        max: 80,
        step: 1,
        //onChange: (v) => log(`Digit size changed to ${v}`),
    });

    // --- Population Cap ---
    parameterManager.addSlider({
        id: "POP_CAP",
        label: "Population Cap",
        target: CONFIG,
        property: "POP_CAP",
        min: 12,
        max: 144,
        step: 12,
        //onChange: (v) => log(`Population cap set to ${v}`),
    });
}

