// ============================================================
// SIMULATION HELPER FUNCTIONS
// ============================================================

export function distance(a, b) {
    return Math.hypot(a.x - b.x, a.y - b.y);
}

export function crossSum(a, b) {
    let sum = a + b;
    while (sum > 9) {
        sum = sum
            .toString()
            .split('')
            .reduce((acc, d) => acc + parseInt(d), 0);
    }
    return sum;
}

export function sign() {
    // return +1 or -1
    return Math.random() > 0.5 ? 1 : -1;
}

export function wiggle() {
    // return random between -0.5 and 0.5
    return Math.random() - 0.5;
}

export function applySpringPhysics(
    d,
    targetX,
    targetY,
    spring,
    damp,
    maxSpeed,
    offset = 0
) {
    // applies spring physics to a digit's dx and dy

    if (offset) {
        targetX += (Math.random() - 0.5) * offset;
        targetY += (Math.random() - 0.5) * offset;
    }

    d.dx += (targetX - d.x) * spring;
    d.dy += (targetY - d.y) * spring;
    d.dx *= damp;
    d.dy *= damp;

    const speed = Math.hypot(d.dx, d.dy);
    if (speed > maxSpeed) {
        d.dx = (d.dx / speed) * maxSpeed;
        d.dy = (d.dy / speed) * maxSpeed;
    }
}
