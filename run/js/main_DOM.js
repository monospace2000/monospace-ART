// ============================================================
// MAIN ENTRY POINT
// ============================================================

import { uiReady, setupModalManager } from "./ui/ui.js";
import { initAttractor } from "./model/attractor.js";
import { startSimulation } from "./control/controls.js";
import { log } from "./utils/utilities.js"; 



// ============================================================
// DETECT MOBILE
// ============================================================
export function isMobile() {
    //return true;
    return /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
}

// ============================================================
// LOADING SCREEN HANDLERS
// ============================================================
function showLoadingScreen() {
    let loader = document.getElementById("warmup-loader");
    if (!loader) {
        loader = document.createElement("div");
        loader.id = "warmup-loader";
        Object.assign(loader.style, {
            position: "fixed",
            inset: "0",
            background: "rgba(0,0,0,1)",
            color: "#fff",
            display: "flex",
            justifyContent: "center",
            alignItems: "center",
            fontSize: "24px",
            fontFamily: "monospace", // monospace
            zIndex: 20000,
            flexDirection: "column",
        });

        // Use a fixed-width container for the text to prevent jumping
        const message = document.createElement("div");
        message.id = "warmup-loader-message";
        message.style.width = "20ch"; // enough for "Initializing... 100%"
        message.style.textAlign = "center"; // center text within container
        message.textContent = "Initializing... 0%";
        loader.appendChild(message);

        document.body.appendChild(loader);
    }
    loader.style.display = "flex";
}

function updateLoadingMessage(message) {
    const msgEl = document.getElementById("warmup-loader-message");
    if (msgEl) msgEl.textContent = message;
}

function hideLoadingScreen() {
    const loader = document.getElementById("warmup-loader");
    if (loader) loader.style.display = "none";
}

// ============================================================
// INITIALIZATION
// ============================================================
export async function init() {
    showLoadingScreen();

    const mobile = isMobile();

    if (!mobile) {
        setupModalManager();
        await uiReady;

        // UI is ready — initialize graph, stats, and parameters
       // initGraph();
        initAttractor();
        //initializeParameters();
        log("Desktop viewer mode");
    } else {
        document.body.classList.add("mobile");
        log("Mobile viewer mode");
    }

    

    // Fade out everything except loader
    const contentElements = Array.from(document.body.children).filter(
        (el) => el.id !== "warmup-loader"
    );
    contentElements.forEach((el) => (el.style.opacity = "0"));

    // Start simulation in the background
    startSimulation();

    // Warm-up animation
    let warmupTicks = 0;
    const warmupDuration = 30;

    const checkWarmup = () => {
        warmupTicks++;
        const progress = Math.floor((warmupTicks / warmupDuration) * 100);
        updateLoadingMessage(`Initializing... ${progress}%`);

        if (warmupTicks >= warmupDuration) {
            contentElements.forEach((el) => {
                el.style.transition = "opacity 0.3s";
                el.style.opacity = "1";
            });
            hideLoadingScreen();
            console.log("WELCOME TO DIGITAL SEX LIFE 2025");
        } else {
            requestAnimationFrame(checkWarmup);
        }
    };

    requestAnimationFrame(checkWarmup);
}

// ============================================================
// START UP
// ============================================================
document.addEventListener("DOMContentLoaded", async () => {
   // await init();
});
