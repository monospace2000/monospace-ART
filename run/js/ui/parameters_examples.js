

// ============================================================
// EXAMPLE USAGE OF PARAMETERS MODULE
// ============================================================


// ============================================================
// INITIALIZATION
// ============================================================

// Call this during your app initialization (e.g., in main.js)
export function initializeParameters() {
    // Setup the modal
    setupParametersModal();

    // Add your simulation parameters as sliders
    // Example 1: Simple numeric parameter
    parameterManager.addSlider({
        id: "speed",
        label: "Speed",
        target: CONFIG,
        property: "simulationSpeed",
        min: 0,
        max: 100,
        step: 1,
        onChange: (value) => {
            console.log(`Speed changed to: ${value}`);
        }
    });

    
    // Example 2: Parameter with decimal steps and custom formatting
    parameterManager.addSlider({
        id: "gravity",
        label: "Gravity",
        target: CONFIG,
        property: "gravity",
        min: 0,
        max: 2,
        step: 0.1,
        format: (v) => v.toFixed(1),
        onChange: (value) => {
            console.log(`Gravity changed to: ${value}`);
        }
    });

    // Example 3: Percentage parameter
    parameterManager.addSlider({
        id: "friction",
        label: "Friction",
        target: CONFIG,
        property: "friction",
        min: 0,
        max: 1,
        step: 0.01,
        format: (v) => `${(v * 100).toFixed(0)}%`,
        onChange: (value) => {
            console.log(`Friction changed to: ${value}`);
        }
    });

    

    // Example 4: Population size
    parameterManager.addSlider({
        id: "population",
        label: "Population",
        target: CONFIG,
        property: "maxPopulation",
        min: 10,
        max: 500,
        step: 10
    });

    // Example 5: Temperature with custom formatting
    parameterManager.addSlider({
        id: "temperature",
        label: "Temperature",
        target: CONFIG,
        property: "temperature",
        min: -50,
        max: 150,
        step: 5,
        format: (v) => `${v}°C`
    });
}



// ============================================================
// PROGRAMMATIC CONTROL EXAMPLES
// ============================================================

// Update a slider value programmatically
export function resetToDefaults() {
    parameterManager.updateValue("speed", 50);
    parameterManager.updateValue("gravity", 1.0);
    parameterManager.updateValue("friction", 0.5);
}

// Get current value
export function getCurrentSpeed() {
    return parameterManager.getValue("speed");
}

// Remove a specific slider
export function removeSpeedControl() {
    parameterManager.removeSlider("speed");
}

// Add a slider dynamically based on user action
export function addDynamicSlider(id, label, targetObj, prop, min, max) {
    parameterManager.addSlider({
        id,
        label,
        target: targetObj,
        property: prop,
        min,
        max,
        step: (max - min) / 100
    });
}

// Clear all sliders
export function clearAllControls() {
    parameterManager.clearAll();
}




// ============================================================
// INTEGRATION WITH YOUR SIMULATION
// ============================================================

// Example: Add sliders for digit-specific parameters
export function addDigitControls(state) {
    for (let i = 1; i <= 9; i++) {
        parameterManager.addSlider({
            id: `digit${i}Weight`,
            label: `Digit ${i}`,
            target: CONFIG,
            property: `digit${i}Weight`,
            min: 0,
            max: 10,
            step: 0.1,
            format: (v) => v.toFixed(1)
        });
    }
}


// Example: Add/remove sliders based on simulation state
export function updateControlsForMode(mode) {
    if (mode === "advanced") {
        parameterManager.addSlider({
            id: "advancedParam",
            label: "Advanced",
            target: CONFIG,
            property: "advancedSetting",
            min: 0,
            max: 100,
            step: 1
        });
    } else {
        parameterManager.removeSlider("advancedParam");
    }
}
