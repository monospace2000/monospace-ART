export const NUMEROLOGY = {
    1: { name: "Independence", color: "#ff4d4d", speedFactor: 1.2 },
    2: { name: "Harmony", color: "#ffa64d", speedFactor: 0.9 },
    3: { name: "Expression", color: "#ffff4d", speedFactor: 1.1 },
    4: { name: "Stability", color: "#4dff4d", speedFactor: 0.8 },
    5: { name: "Freedom", color: "#4dffff", speedFactor: 1.3 },
    6: { name: "Care", color: "#4d4dff", speedFactor: 0.9 },
    7: { name: "Introspection", color: "#b84dff", speedFactor: 0.7 },
    8: { name: "Power", color: "#ff4db8", speedFactor: 1.1 },
    9: { name: "Compassion", color: "#ffffff", speedFactor: 1.0 }
};

// Apply traits on digit creation
export function applyNumerologyTraits(digit) {
    const reduced = crossSum(digit.value); // your existing cross-sum function
    const traits = NUMEROLOGY[reduced];
    digit.traits = traits;
    digit.color = traits.color;
    digit.speed *= traits.speedFactor;
}