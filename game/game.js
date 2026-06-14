// Spillets data/tilstand (State)
const gameState = {
    heroLevel: 1,
    gold: 0,
    wood: 0,
    baseLevel: 0,
    currentTileIndex: null,
    mathDifficulty: 1 // Stiger i takt med områder gennemføres
};

// Aktuel matematikopgave i gang
let currentCorrectAnswer = 0;

// Kort-layout (16 felter i et 4x4 grid)
// Typer: 'B' = Base, 'C' = Chest (Guld), 'W' = Wood, 'E' = Enemy/Hero training
const mapData = [
    { type: 'B', unlocked: true, icon: '🏰' },
    { type: 'C', unlocked: true, icon: '🪙' },
    { type: 'W', unlocked: true, icon: '🪵' },
    { type: 'C', unlocked: false, icon: '🪙' },
    
    { type: 'W', unlocked: false, icon: '🪵' },
    { type: 'E', unlocked: false, icon: '⚔️' },
    { type: 'C', unlocked: false, icon: '🪙' },
    { type: 'W', unlocked: false, icon: '🪵' },
    
    { type: 'C', unlocked: false, icon: '🪙' },
    { type: 'E', unlocked: false, icon: '⚔️' },
    { type: 'W', unlocked: false, icon: '🪵' },
    { type: 'C', unlocked: false, icon: '🪙' },
    
    { type: 'W', unlocked: false, icon: '🪵' },
    { type: 'C', unlocked: false, icon: '🪙' },
    { type: 'E', unlocked: false, icon: '⚔️' },
    { type: 'C', unlocked: false, icon: '🪙' }
];

// Initialisér spillet når siden indlæses
window.onload = function() {
    renderMap();
    updateUI();
    
    // Tillad at trykke 'Enter' på tastaturet for laptops
    document.getElementById('math-answer').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            checkAnswer();
        }
    });
};

// Tegn kortet dynamisk
function renderMap() {
    const mapContainer = document.getElementById('world-map');
    mapContainer.innerHTML = '';

    mapData.forEach((tile, index) => {
        const tileDiv = document.createElement('div');
        tileDiv.classList.add('tile');
        
        if (!tile.unlocked) {
            tileDiv.classList.add('locked');
        } else {
            tileDiv.innerText = tile.icon;
            tileDiv.onclick = () => handleTileClick(index);
        }
        
        mapContainer.appendChild(tileDiv);
    });
}

// Opdatér tallene øverst på skærmen
function updateUI() {
    document.getElementById('hero-level').innerText = gameState.heroLevel;
    document.getElementById('res-gold').innerText = gameState.gold;
    document.getElementById('res-wood').innerText = gameState.wood;
    document.getElementById('base-level').innerText = gameState.baseLevel;
}

// Håndter klik på et aktivt felt på kortet
function handleTileClick(index) {
    const tile = mapData[index];
    gameState.currentTileIndex = index;
    
    if (tile.type === 'B') {
        alert("Dette er din base. Brug knapperne i bunden til at udbygge den!");
        return;
    }
    
    // Hver gang man vil interagere med ressourcer/monstre, skal der regnes!
    triggerMathModal();
}

// Generer matematikopgave tilpasset en 10-årig (4.-5. klasse)
function triggerMathModal() {
    const modal = document.getElementById('math-modal');
    const questionText = document.getElementById('math-question');
    const inputField = document.getElementById('math-answer');
    const feedback = document.getElementById('math-feedback');
    
    inputField.value = '';
    feedback.innerText = '';
    
    let num1, num2, operator;
    const diff = gameState.mathDifficulty;

    // Progression i sværhedsgrad baseret på spillets stadie
    if (diff === 1) {
        // Nemme gangetabeller og plusstykker
        const types = ['+', 'x'];
        operator = types[Math.floor(Math.random() * types.length)];
        if (operator === 'x') {
            num1 = Math.floor(Math.random() * 10) + 2; // 2-10 tabel
            num2 = Math.floor(Math.random() * 10) + 2;
            currentCorrectAnswer = num1 * num2;
        } else {
            num1 = Math.floor(Math.random() * 50) + 10;
            num2 = Math.floor(Math.random() * 50) + 10;
            currentCorrectAnswer = num1 + num2;
        }
    } else {
        // Sværere stykker (f.eks. division eller større minusstykker)
        const types = ['-', 'x'];
        operator = types[Math.floor(Math.random() * types.length)];
        if (operator === 'x') {
            num1 = Math.floor(Math.random() * 12) + 3; 
            num2 = Math.floor(Math.random() * 12) + 2;
            currentCorrectAnswer = num1 * num2;
        } else {
            num1 = Math.floor(Math.random() * 100) + 50;
            num2 = Math.floor(Math.random() * 49) + 10;
            currentCorrectAnswer = num1 - num2;
        }
    }

    questionText.innerText = `Hvad er: ${num1} ${operator} ${num2}?`;
    modal.classList.remove('hidden');
    inputField.focus();
}

// Tjek om barnets svar er korrekt
function checkAnswer() {
    const userAnswer = parseInt(document.getElementById('math-answer').value);
    const feedback = document.getElementById('math-feedback');
    
    if (userAnswer === currentCorrectAnswer) {
        feedback.style.color = '#27ae60';
        feedback.innerText = 'Rigtigt! 🎉';
        
        setTimeout(() => {
            document.getElementById('math-modal').classList.add('hidden');
            rewardPlayer();
        }, 800);
        
    } else {
        feedback.style.color = '#c0392b';
        feedback.innerText = 'Prøv igen! Du kan godt! 💪';
    }
}

// Giv belønning og lås op for næste område (Sandbox ekspansion)
function rewardPlayer() {
    const index = gameState.currentTileIndex;
    const tile = mapData[index];
    
    if (tile.type === 'C') {
        gameState.gold += Math.floor(Math.random() * 10) + 10;
    } else if (tile.type === 'W') {
        gameState.wood += Math.floor(Math.random() * 10) + 10;
    } else if (tile.type === 'E') {
        gameState.heroLevel += 1;
        alert("Du besejrede monstret og din Hero steg i level!");
    }
    
    // Sandbox progression: Lås op for det næste tilstødende felt på kortet
    if (index + 1 < mapData.length && !mapData[index + 1].unlocked) {
        mapData[index + 1].unlocked = true;
        // Gør spillet gradvist sværere efterhånden som banen udvides
        if (index > 5) gameState.mathDifficulty = 2;
    }
    
    renderMap();
    updateUI();
}

// Opgradering af basen via ressourcer
function upgradeBase() {
    if (gameState.gold >= 10 && gameState.wood >= 10) {
        gameState.gold -= 10;
        gameState.wood -= 10;
        gameState.baseLevel += 1;
        updateUI();
        alert("Flot! Din base er nu i Level " + gameState.baseLevel);
    } else {
        alert("Du mangler ressourcer! Løs flere opgaver på kortet.");
    }
}

// Træn helten manuelt
function trainHero() {
    if (gameState.gold >= 15) {
        gameState.gold -= 15;
        gameState.heroLevel += 1;
        updateUI();
        alert("Din Hero er blevet stærkere!");
    } else {
        alert("Ikke nok guld til træning.");
    }
}