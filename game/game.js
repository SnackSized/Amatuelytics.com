// Opsætning af Canvas
let canvas, ctx;
let isGamePaused = false;

// Spillets tilstand
const gameState = {
    heroLevel: 1,
    gold: 0,
    wood: 0,
    baseLevel: 0,
    mathDifficulty: 1
};

// Helte-objekt (Placering og bevægelse)
const hero = {
    x: 100,
    y: 100,
    radius: 18,
    speed: 4,
    targetX: 100,
    targetY: 100,
    isMoving: false
};

// Interaktive objekter på kortet
let worldObjects = [];
let activeCollisionObject = null;
let currentCorrectAnswer = 0;

window.onload = function() {
    canvas = document.getElementById('gameCanvas');
    ctx = canvas.getContext('2d');
    
    // Sæt fast opløsning på spilverdenen (god til både iPad og mobil-skalering)
    canvas.width = 800;
    canvas.height = 600;

    // Generer basen og tilfældige ressourcer rundt omkring på kortet
    initWorld();

    // Lyt efter både klik (mus) og touch (mobil/iPad)
    canvas.addEventListener('mousedown', handleInput);
    canvas.addEventListener('touchstart', handleInput, { passive: false });

    document.getElementById('math-answer').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') checkAnswer();
    });

    // Start spillets motor (Game Loop)
    gameLoop();
};

function initWorld() {
    worldObjects = [
        { type: 'B', x: 100, y: 100, radius: 30, icon: '🏰' } // Basen
    ];

    // Spred 10 tilfældige kister, træer og monstre
    const types = [
        { type: 'C', icon: '🪙' },
        { type: 'W', icon: '🪵' },
        { type: 'E', icon: '⚔️' }
    ];

    for (let i = 0; i < 10; i++) {
        let randType = types[Math.floor(Math.random() * types.length)];
        worldObjects.push({
            type: randType.type,
            icon: randType.icon,
            x: Math.floor(Math.random() * (canvas.width - 150)) + 100,
            y: Math.floor(Math.random() * (canvas.height - 150)) + 100,
            radius: 20
        });
    }
}

// Håndter klik/touch på skærmen for at flytte helten
function handleInput(e) {
    if (isGamePaused) return;
    e.preventDefault();

    let clientX, clientY;
    if (e.touches) {
        clientX = e.touches[0].clientX;
        clientY = e.touches[0].clientY;
    } else {
        clientX = e.clientX;
        clientY = e.clientY;
    }

    // Omregn skærm-koordinater til canvas-koordinater
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;

    hero.targetX = (clientX - rect.left) * scaleX;
    hero.targetY = (clientY - rect.top) * scaleY;
    hero.isMoving = true;
}

// Spillets motor kører hele tiden
function gameLoop() {
    if (!isGamePaused) {
        updatePhysics();
        drawFrame();
    }
    requestAnimationFrame(gameLoop);
}

// Beregn bevægelse og tjek for kollisioner
function updatePhysics() {
    if (!hero.isMoving) return;

    // Beregn afstand til målet
    let dx = hero.targetX - hero.x;
    let dy = hero.targetY - hero.y;
    let distance = Math.sqrt(dx * dx + dy * dy);

    if (distance > hero.speed) {
        // Flyt helten tættere på målet trin for trin
        hero.x += (dx / distance) * hero.speed;
        hero.y += (dy / distance) * hero.speed;
    } else {
        hero.x = hero.targetX;
        hero.y = hero.targetY;
        hero.isMoving = false;
    }

    // Tjek kollision med objekter (undtagen basen 'B')
    worldObjects.forEach((obj) => {
        if (obj.type === 'B') return;

        let objDx = obj.x - hero.x;
        let objDy = obj.y - hero.y;
        let objDist = Math.sqrt(objDx * objDx + objDy * objDy);

        // Hvis helten rører objektet, udløses matematikopgaven
        if (objDist < hero.radius + obj.radius) {
            hero.isMoving = false;
            activeCollisionObject = obj;
            triggerMathModal();
        }
    });
}

// Tegn alt grafik på canvas
function drawFrame() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Tegn objekter (Base, kister osv.)
    worldObjects.forEach((obj) => {
        ctx.font = `${obj.radius * 1.5}px Arial`;
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(obj.icon, obj.x, obj.y);
    });

    // Tegn helten 🧙‍♂️
    ctx.font = `${hero.radius * 1.8}px Arial`;
    ctx.fillText('🧙‍♂️', hero.x, hero.y);
}

function triggerMathModal() {
    isGamePaused = true; // Stop spillet
    
    const modal = document.getElementById('math-modal');
    const questionText = document.getElementById('math-question');
    const inputField = document.getElementById('math-answer');
    document.getElementById('math-feedback').innerText = '';
    inputField.value = '';

    // Generer matematik (10 år / 4. klasse niveau)
    let num1, num2, operator;
    const types = ['+', '-', 'x'];
    operator = types[Math.floor(Math.random() * types.length)];

    if (operator === 'x') {
        num1 = Math.floor(Math.random() * 9) + 2; // 2-10 tabel
        num2 = Math.floor(Math.random() * 10) + 1;
        currentCorrectAnswer = num1 * num2;
    } else if (operator === '+') {
        num1 = Math.floor(Math.random() * 60) + 20;
        num2 = Math.floor(Math.random() * 60) + 20;
        currentCorrectAnswer = num1 + num2;
    } else {
        num1 = Math.floor(Math.random() * 100) + 30;
        num2 = Math.floor(Math.random() * 25) + 5;
        currentCorrectAnswer = num1 - num2;
    }

    questionText.innerText = `Løs opgaven for at indsamle ressourcen: \n ${num1} ${operator} ${num2}`;
    modal.classList.remove('hidden');
    inputField.focus();
}

function checkAnswer() {
    const userAnswer = parseInt(document.getElementById('math-answer').value);
    const feedback = document.getElementById('math-feedback');

    if (userAnswer === currentCorrectAnswer) {
        feedback.style.color = '#27ae60';
        feedback.innerText = 'Rigtigt! 🎉';

        setTimeout(() => {
            document.getElementById('math-modal').classList.add('hidden');
            rewardPlayer();
            isGamePaused = false; // Start spillet igen
        }, 800);
    } else {
        feedback.style.color = '#c0392b';
        feedback.innerText = 'Prøv igen! Du kan godt!';
    }
}

function rewardPlayer() {
    if (!activeCollisionObject) return;

    if (activeCollisionObject.type === 'C') {
        gameState.gold += Math.floor(Math.random() * 15) + 10;
    } else if (activeCollisionObject.type === 'W') {
        gameState.wood += Math.floor(Math.random() * 15) + 10;
    } else if (activeCollisionObject.type === 'E') {
        gameState.heroLevel += 1;
    }

    // Fjern det indsamlede objekt fra verdenen
    worldObjects = worldObjects.filter(obj => obj !== activeCollisionObject);
    activeCollisionObject = null;

    // Hvis alle ressourcer er væk, kan du generere nye (Sandbox udvidelse)
    if (worldObjects.length <= 1) {
        initWorld();
        gameState.mathDifficulty++; // Gør næste bølge af matematik sværere
    }

    updateUI();
}

function updateUI() {
    document.getElementById('hero-level').innerText = gameState.heroLevel;
    document.getElementById('res-gold').innerText = gameState.gold;
    document.getElementById('res-wood').innerText = gameState.wood;
    document.getElementById('base-level').innerText = gameState.baseLevel;
}

function upgradeBase() {
    if (gameState.gold >= 10 && gameState.wood >= 10) {
        gameState.gold -= 10;
        gameState.wood -= 10;
        gameState.baseLevel += 1;
        updateUI();
        alert("🏰 Din base er opgraderet!");
    } else {
        alert("Mangler ressourcer!");
    }
}

function trainHero() {
    if (gameState.gold >= 15) {
        gameState.gold -= 15;
        gameState.heroLevel += 1;
        updateUI();
        alert("🧙‍♂️ Din helt er trænet!");
    } else {
        alert("Mangler guld!");
    }
}