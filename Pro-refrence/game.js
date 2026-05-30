/**
 * Crypto Miner Tycoon - Game Logic
 * Version: 1.0.0 - AI Storyline Edition
 * 
 * Changes in 0.9.0:
 * - Player-selectable difficulty (Easy/Medium/Hard)
 * - Difficulty change with run reset (preserves prestige)
 * - Per-difficulty intensity scaling
 * - Difficulty stored in save data
 * 
 * Changes in 0.8.1:
 * - Miners auto-stop after 48 hours of inactivity
 * - Activity tracking on clicks and upgrades
 * - Miner status indicator with restart button
 * - Offline earnings capped at 48 hours
 */

(function() {
    'use strict';
    
    // ============================================
    // GAME STATE
    // ============================================
    let gameState = {
        satoshis: 0,
        clickPower: 1,
        passiveIncome: 0,
        rating: 1000,
        prestigeLevel: 0,
        prestigeMultiplier: 1,
        upgrades: {},
        bestRankScore: 0,
        lastActiveTime: Date.now(),
        minersActive: true,
        difficulty: 'medium', // New in 0.9.0
        version: '1.0.0'
    };
    
    // Pending difficulty change (for confirmation modal)
    let pendingDifficultyChange = null;

    // ============================================
    // AI STORYLINE UNLOCK LOG (New in 1.0.0)
    // ============================================
    // Tracks upgrades and prestige events already triggered locally so the
    // popup doesn't re-fire on page reload. Entries older than 24h are purged
    // to keep localStorage from accumulating forever.
    const AI_STORY_STORAGE_KEY = 'cmt_ai_unlocked';

    function getAiUnlockLog() {
        try {
            const raw = localStorage.getItem(AI_STORY_STORAGE_KEY);
            if (!raw) return {};
            const log = JSON.parse(raw);
            const cutoff = Date.now() - (24 * 60 * 60 * 1000);
            Object.keys(log).forEach(k => { if (log[k] < cutoff) delete log[k]; });
            return log;
        } catch (e) {
            return {};
        }
    }

    function markAiUnlocked(key) {
        try {
            const log = getAiUnlockLog();
            log[key] = Date.now();
            localStorage.setItem(AI_STORY_STORAGE_KEY, JSON.stringify(log));
        } catch (e) {}
    }

    function hasBeenAiUnlocked(key) {
        return !!getAiUnlockLog()[key];
    }

    // Miner timeout constant (48 hours in milliseconds)
    const MINER_TIMEOUT_MS = 48 * 60 * 60 * 1000;
    
    // Difficulty intensity map
    const DIFFICULTY_INTENSITY = {
        'easy': 0.6,
        'medium': 0.8,
        'hard': 1.0
    };
    
    // ============================================
    // SETTINGS FROM WORDPRESS
    // ============================================
    const cloudSavesEnabled = typeof cmtSettings !== 'undefined' && cmtSettings.cloudSavesEnabled;
    const isUserLoggedIn = typeof cmtSettings !== 'undefined' && cmtSettings.isUserLoggedIn;
    const useCloudSaves = cloudSavesEnabled && isUserLoggedIn;
    
    const gameplay = (typeof cmtSettings !== 'undefined' && cmtSettings.gameplay) 
        ? cmtSettings.gameplay 
        : {
            difficulty: 'medium',
            intensity: 0.8,
            buttonMode: 1,
            movementTrigger: 'click',
            enableSelfReset: true,
            leaderboardLimit: 10,
            allowPlayerDifficulty: false
        };
    
    const upgradeNames = (typeof cmtSettings !== 'undefined' && 
                         cmtSettings.branding && 
                         cmtSettings.branding.enabled && 
                         cmtSettings.branding.upgradeNames) 
        ? cmtSettings.branding.upgradeNames 
        : [
            'Better Pickaxe',
            'CPU Miner',
            'Diamond Pickaxe',
            'GPU Mining Rig',
            'Quantum Pickaxe',
            'ASIC Miner',
            'Neutron Star Drill',
            'Mining Farm',
            'Black Hole Extractor',
            'Data Center'
        ];
    
    const currencyLabel = (typeof cmtSettings !== 'undefined' &&
                          cmtSettings.branding &&
                          cmtSettings.branding.enabled &&
                          cmtSettings.branding.currencyLabel)
        ? cmtSettings.branding.currencyLabel
        : 'satoshis';

    // ============================================
    // UI LABEL ACCESSOR
    // ============================================
    const cmtLabels = ( typeof cmtSettings !== 'undefined' && cmtSettings.labels ) ? cmtSettings.labels : {};

    function getLabel( key, fallback ) {
        const label = cmtLabels[ key ];
        return ( label === undefined || label === null || label === '' ) ? fallback : label;
    }

    // ============================================
    // DIFFICULTY SYSTEM (New in 0.9.0)
    // ============================================
    
    /**
     * Get current difficulty intensity
     * Uses player-selected difficulty if enabled, otherwise admin setting
     */
    function getCurrentIntensity() {
        if (gameplay.allowPlayerDifficulty) {
            return DIFFICULTY_INTENSITY[gameState.difficulty] || 0.8;
        }
        return gameplay.intensity;
    }
    
    /**
     * Initialize difficulty selector UI
     */
    function initDifficultySelector() {
        if (!gameplay.allowPlayerDifficulty) return;
        
        const selector = document.getElementById('cmt-difficultySelector');
        if (!selector) return;
        
        const buttons = selector.querySelectorAll('.cmt-difficulty-btn');
        
        // Set initial active state
        updateDifficultyButtonState();
        
        // Add click handlers
        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                const newDifficulty = this.dataset.difficulty;
                
                // Don't do anything if already on this difficulty
                if (newDifficulty === gameState.difficulty) return;
                
                // Show confirmation modal
                showDifficultyChangeModal(newDifficulty);
            });
        });
    }
    
    /**
     * Update difficulty button active state
     */
    function updateDifficultyButtonState() {
        const buttons = document.querySelectorAll('.cmt-difficulty-btn');
        buttons.forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.difficulty === gameState.difficulty) {
                btn.classList.add('active');
            }
        });
    }
    
    /**
     * Show difficulty change confirmation modal
     */
    function showDifficultyChangeModal(newDifficulty) {
        pendingDifficultyChange = newDifficulty;
        
        const modal = document.getElementById('cmt-difficultyModal');
        const label = document.getElementById('cmt-newDifficultyLabel');
        const leaderboardLabel = document.getElementById('cmt-newDifficultyLeaderboard');
        
        if (modal && label) {
            const diffLabelText = getLabel(newDifficulty, newDifficulty.charAt(0).toUpperCase() + newDifficulty.slice(1));
            label.textContent = diffLabelText;
            if (leaderboardLabel) {
                leaderboardLabel.textContent = diffLabelText;
            }
            modal.classList.add('cmt-show');
        }
    }
    
    /**
     * Hide difficulty change modal
     */
    window.cmtHideDifficultyModal = function() {
        const modal = document.getElementById('cmt-difficultyModal');
        if (modal) {
            modal.classList.remove('cmt-show');
        }
        pendingDifficultyChange = null;
    };
    
    /**
     * Confirm difficulty change
     */
    window.cmtConfirmDifficultyChange = async function() {
        if (!pendingDifficultyChange) return;
        
        const newDifficulty = pendingDifficultyChange;
        
        // Hide modal first
        cmtHideDifficultyModal();
        
        // If using cloud saves, use the API endpoint
        if (useCloudSaves) {
            try {
                const response = await fetch(cmtSettings.restUrl + 'change-difficulty', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': cmtSettings.nonce
                    },
                    body: JSON.stringify({ difficulty: newDifficulty })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Apply the reset state from server
                    if (data.new_save_data) {
                        gameState = Object.assign({}, gameState, data.new_save_data);
                    } else {
                        // Manual reset if server didn't return new state
                        performLocalDifficultyReset(newDifficulty);
                    }
                    
                    recalculateProduction();
                    updateUI();
                    updateDifficultyButtonState();
                    updateMinerStatusUI();
                    
                    showSaveIndicator('🎮 Difficulty changed to ' + newDifficulty.charAt(0).toUpperCase() + newDifficulty.slice(1) + '!');
                } else {
                    console.error('Difficulty change failed:', data.message);
                    showSaveIndicator('❌ Failed to change difficulty');
                }
            } catch (error) {
                console.error('Difficulty change error:', error);
                showSaveIndicator('❌ Error changing difficulty');
            }
        } else {
            // Local save mode - just reset locally
            performLocalDifficultyReset(newDifficulty);
            recalculateProduction();
            updateUI();
            updateDifficultyButtonState();
            saveGame();
            
            showSaveIndicator('🎮 Difficulty changed to ' + newDifficulty.charAt(0).toUpperCase() + newDifficulty.slice(1) + '!');
        }
    };
    
    /**
     * Perform local difficulty reset (for localStorage mode)
     */
    function performLocalDifficultyReset(newDifficulty) {
        // Preserve prestige and best score
        const preservedPrestige = gameState.prestigeLevel;
        const preservedMultiplier = gameState.prestigeMultiplier;
        const preservedBestScore = gameState.bestRankScore;
        
        // Reset run
        gameState.satoshis = 0;
        gameState.clickPower = 1;
        gameState.passiveIncome = 0;
        gameState.rating = 1000;
        gameState.upgrades = {};
        gameState.lastActiveTime = Date.now();
        gameState.minersActive = true;
        
        // Apply new difficulty
        gameState.difficulty = newDifficulty;
        
        // Restore preserved values
        gameState.prestigeLevel = preservedPrestige;
        gameState.prestigeMultiplier = preservedMultiplier;
        gameState.bestRankScore = preservedBestScore;
    }

    // ============================================
    // MULTI-BUTTON SYSTEM (From 0.8.0)
    // ============================================
    let buttonSystem = {
        mode: gameplay.buttonMode,
        movementTrigger: gameplay.movementTrigger,
        timerInterval: null,
        lastMoveTime: 0,
        moveCount: 0
    };
    
    function getChaosLevel() {
        if (buttonSystem.mode < 3) {
            return 0.3;
        }
        
        const effectiveLevel = Math.min(gameState.prestigeLevel, 10);
        const baseIntensity = getCurrentIntensity(); // Updated to use player difficulty
        const levelScale = 0.2 + (effectiveLevel * 0.08);
        
        return Math.min(1.0, levelScale * baseIntensity);
    }
    
    function getMovementSpeed() {
        const chaos = getChaosLevel();
        return Math.floor(800 - (chaos * 400));
    }
    
    function getTimerInterval() {
        const chaos = getChaosLevel();
        return Math.floor(3000 - (chaos * 1500));
    }
    
    function initButtonSystem() {
        const clickArea = document.getElementById('cmt-clickArea');
        if (!clickArea) return;
        
        const allButtons = clickArea.querySelectorAll('.cmt-mine-button');
        allButtons.forEach(button => {
            button.addEventListener('click', handleButtonClick);
        });
        
        if (buttonSystem.mode > 1) {
            randomizeButtonPositions();
        }
        
        if (buttonSystem.movementTrigger === 'timer' || buttonSystem.movementTrigger === 'both') {
            startMovementTimer();
        }
    }
    
    function handleButtonClick(event) {
        const button = event.currentTarget;
        const isReal = button.dataset.real === 'true';
        
        if (isReal) {
            performMine(button);
            
            if (buttonSystem.mode > 1 && 
                (buttonSystem.movementTrigger === 'click' || buttonSystem.movementTrigger === 'both')) {
                setTimeout(() => {
                    randomizeButtonPositions();
                }, 100);
            }
        } else {
            showDecoyFeedback(button);
        }
    }
    
    function performMine(button) {
        recordActivity();
        
        const earnedAmount = gameState.clickPower * gameState.prestigeMultiplier;
        gameState.satoshis += earnedAmount;
        gameState.satoshis = Number(gameState.satoshis.toFixed(6));
        
        if (button) {
            const rect = button.getBoundingClientRect();
            const particle = document.createElement('div');
            particle.className = 'cmt-click-particle';
            particle.textContent = '+' + formatNumber(earnedAmount);
            particle.style.left = (rect.left + rect.width / 2 - 30) + 'px';
            particle.style.top = (rect.top + rect.height / 2) + 'px';
            document.body.appendChild(particle);
            
            setTimeout(() => particle.remove(), 1000);
        }
        
        if (button) {
            button.classList.add('cmt-clicked');
            setTimeout(() => button.classList.remove('cmt-clicked'), 100);
        }
        
        updateUI();
    }
    
    function showDecoyFeedback(button) {
        button.classList.add('cmt-decoy-shake');
        setTimeout(() => button.classList.remove('cmt-decoy-shake'), 300);
        
        const rect = button.getBoundingClientRect();
        const indicator = document.createElement('div');
        indicator.className = 'cmt-decoy-indicator';
        indicator.textContent = '✗';
        indicator.style.left = (rect.left + rect.width / 2 - 15) + 'px';
        indicator.style.top = (rect.top + rect.height / 2 - 15) + 'px';
        document.body.appendChild(indicator);
        
        setTimeout(() => indicator.remove(), 500);
    }
    
    function getSlotPositions(buttonCount) {
        if (buttonCount === 2) {
            return [
                { x: 25, y: 50 },
                { x: 75, y: 50 }
            ];
        } else if (buttonCount === 3) {
            return [
                { x: 25, y: 35 },
                { x: 75, y: 35 },
                { x: 50, y: 75 }
            ];
        }
        return [{ x: 50, y: 50 }];
    }
    
    function shuffleArray(array) {
        const shuffled = [...array];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    }
    
    function randomizeButtonPositions() {
        const clickArea = document.getElementById('cmt-clickArea');
        if (!clickArea) return;
        
        const buttons = Array.from(clickArea.querySelectorAll('.cmt-mine-button'));
        if (buttons.length <= 1) return;
        
        const buttonCount = buttons.length;
        const slots = getSlotPositions(buttonCount);
        const chaos = getChaosLevel();
        
        const shuffledSlots = shuffleArray(slots);
        
        const areaWidth = clickArea.offsetWidth;
        const areaHeight = clickArea.offsetHeight;
        const buttonSize = 160;
        
        buttons.forEach((button, index) => {
            const slot = shuffledSlots[index];
            
            let x = (slot.x / 100) * areaWidth - (buttonSize / 2);
            let y = (slot.y / 100) * areaHeight - (buttonSize / 2);
            
            if (chaos > 0.3) {
                const jitterRange = 30 * chaos;
                x += (Math.random() - 0.5) * jitterRange * 2;
                y += (Math.random() - 0.5) * jitterRange * 2;
            }
            
            x = Math.max(10, Math.min(areaWidth - buttonSize - 10, x));
            y = Math.max(10, Math.min(areaHeight - buttonSize - 10, y));
            
            const speed = getMovementSpeed();
            button.style.transition = `left ${speed}ms ease-out, top ${speed}ms ease-out`;
            button.style.position = 'absolute';
            button.style.transform = 'none';
            button.style.left = x + 'px';
            button.style.top = y + 'px';
        });
        
        buttonSystem.moveCount++;
    }
    
    function startMovementTimer() {
        if (buttonSystem.timerInterval) {
            clearInterval(buttonSystem.timerInterval);
        }
        
        const interval = getTimerInterval();
        buttonSystem.timerInterval = setInterval(() => {
            if (buttonSystem.mode > 1) {
                randomizeButtonPositions();
            }
        }, interval);
    }
    
    function stopMovementTimer() {
        if (buttonSystem.timerInterval) {
            clearInterval(buttonSystem.timerInterval);
            buttonSystem.timerInterval = null;
        }
    }

    // ============================================
    // UPGRADE DEFINITIONS
    // ============================================
    const upgradeDefinitions = [
        { id: 'betterClicker', name: upgradeNames[0], baseEffect: 1, baseDescription: 'Increases click power', baseCost: 10, rating: 1000, type: 'click', costMultiplier: 1.15 },
        { id: 'cpuMiner', name: upgradeNames[1], baseEffect: 0.1, baseDescription: `Generates ${currencyLabel}/sec`, baseCost: 50, rating: 1050, type: 'passive', costMultiplier: 1.2 },
        { id: 'powerfulClicker', name: upgradeNames[2], baseEffect: 5, baseDescription: 'Increases click power', baseCost: 100, rating: 1100, type: 'click', costMultiplier: 1.15 },
        { id: 'gpuRig', name: upgradeNames[3], baseEffect: 1, baseDescription: `Generates ${currencyLabel}/sec`, baseCost: 500, rating: 1200, type: 'passive', costMultiplier: 1.25 },
        { id: 'megaClicker', name: upgradeNames[4], baseEffect: 25, baseDescription: 'Increases click power', baseCost: 1000, rating: 1300, type: 'click', costMultiplier: 1.15 },
        { id: 'asicMiner', name: upgradeNames[5], baseEffect: 10, baseDescription: `Generates ${currencyLabel}/sec`, baseCost: 5000, rating: 1400, type: 'passive', costMultiplier: 1.3 },
        { id: 'ultraClicker', name: upgradeNames[6], baseEffect: 100, baseDescription: 'Increases click power', baseCost: 10000, rating: 1500, type: 'click', costMultiplier: 1.15 },
        { id: 'miningFarm', name: upgradeNames[7], baseEffect: 50, baseDescription: `Generates ${currencyLabel}/sec`, baseCost: 50000, rating: 1600, type: 'passive', costMultiplier: 1.35 },
        { id: 'godClicker', name: upgradeNames[8], baseEffect: 500, baseDescription: 'Increases click power', baseCost: 100000, rating: 1700, type: 'click', costMultiplier: 1.15 },
        { id: 'datacenter', name: upgradeNames[9], baseEffect: 250, baseDescription: `Generates ${currencyLabel}/sec`, baseCost: 500000, rating: 1800, type: 'passive', costMultiplier: 1.4 }
    ];

    // ============================================
    // GAME MECHANICS
    // ============================================
    
    function getDiminishingReturnsMultiplier(ownedCount) {
        if (ownedCount === 0) return 1.0;
        if (ownedCount === 1) return 0.8;
        if (ownedCount === 2) return 0.6;
        if (ownedCount === 3) return 0.4;
        return 0.2;
    }

    function getPrestigeCost() {
        const basePrestigeCost = 1000000;
        const prestigeMultiplier = 5;
        
        if (gameState.prestigeLevel === 0) {
            return basePrestigeCost;
        }
        
        return basePrestigeCost * Math.pow(prestigeMultiplier, gameState.prestigeLevel);
    }

    function calculateTotalEffect(upgrade) {
        const owned = gameState.upgrades[upgrade.id] || 0;
        if (owned === 0) return 0;
        
        let totalEffect = 0;
        for (let i = 0; i < owned; i++) {
            const multiplier = getDiminishingReturnsMultiplier(i);
            totalEffect += upgrade.baseEffect * multiplier;
        }
        
        return totalEffect;
    }

    function recalculateProduction() {
        let totalClickPower = 1;
        let totalPassiveIncome = 0;
        
        upgradeDefinitions.forEach(upgrade => {
            const effect = calculateTotalEffect(upgrade);
            
            if (upgrade.type === 'click') {
                totalClickPower += effect;
            } else if (upgrade.type === 'passive') {
                totalPassiveIncome += effect;
            }
        });
        
        gameState.clickPower = totalClickPower;
        gameState.passiveIncome = totalPassiveIncome;
    }

    function getUpgradeCost(upgrade) {
        const owned = gameState.upgrades[upgrade.id] || 0;
        const ratingDiff = upgrade.rating - gameState.rating;
        const eloMultiplier = Math.max(0.5, 1 + (ratingDiff / 400));
        const ownedMultiplier = Math.pow(upgrade.costMultiplier, owned);
        
        // Use current intensity (player difficulty or admin setting)
        const difficultyModifier = 0.8 + (getCurrentIntensity() * 0.4);
        
        return Math.ceil(upgrade.baseCost * eloMultiplier * ownedMultiplier * difficultyModifier);
    }
    
    function calculateRankScore() {
        const baseScore = Math.log10(gameState.satoshis + 1) * 1000;
        const prestigeBonus = gameState.prestigeLevel * 10000;
        return baseScore + prestigeBonus;
    }

    // ============================================
    // GLOBAL FUNCTIONS
    // ============================================
    
    window.cmtMine = function() {
        const realButton = document.querySelector('.cmt-real-button');
        if (realButton) {
            performMine(realButton);
        }
    };

    window.cmtBuyUpgrade = function(upgradeId) {
        recordActivity();
        
        const upgrade = upgradeDefinitions.find(u => u.id === upgradeId);
        if (!upgrade) return;
        
        const cost = getUpgradeCost(upgrade);
        
        if (gameState.satoshis >= cost) {
            gameState.satoshis -= cost;
            gameState.upgrades[upgradeId] = (gameState.upgrades[upgradeId] || 0) + 1;

            // Trigger AI storyline on first unlock of this upgrade.
            const newCount = gameState.upgrades[upgradeId];
            if (newCount === 1
                && typeof cmtSettings !== 'undefined'
                && cmtSettings.aiStorylineEnabled
                && !hasBeenAiUnlocked('upgrade_' + upgradeId)
            ) {
                markAiUnlocked('upgrade_' + upgradeId);
                fetchAndShowAiStory('upgrade', {
                    upgrade_id:   upgradeId,
                    upgrade_name: upgrade.name,
                });
            }

            recalculateProduction();
            gameState.rating += 10;
            
            const currentScore = calculateRankScore();
            if (currentScore > gameState.bestRankScore) {
                gameState.bestRankScore = currentScore;
            }
            
            updateUI();
            saveGame();
        }
    };

    window.cmtPrestige = function() {
        const prestigeCost = getPrestigeCost();
        
        if (gameState.satoshis < prestigeCost) {
            alert(`You need ${formatNumber(prestigeCost)} ${currencyLabel} to perform a ${getLabel('hardFork', 'Hard Fork')}!`);
            return;
        }
        
        const currentPrestige = gameState.prestigeLevel;
        const nextPrestigeCost = prestigeCost * 5;
        
        const confirmed = confirm(
            `Perform ${getLabel('hardFork', 'Hard Fork')}?\n\n` +
            `Current Level: ${currentPrestige}\n` +
            `New Level: ${currentPrestige + 1}\n` +
            `Current Bonus: +${currentPrestige * 10}%\n` +
            `New Bonus: +${(currentPrestige + 1) * 10}%\n\n` +
            `Cost: ${formatNumber(prestigeCost)} ${currencyLabel}\n` +
            `Next ${getLabel('hardFork', 'Hard Fork')} Cost: ${formatNumber(nextPrestigeCost)} ${currencyLabel}\n\n` +
            `This will reset your progress but give you a permanent +10% production bonus!\n` +
            `Diminishing returns will also reset.`
        );
        
        if (!confirmed) return;
        
        const currentScore = calculateRankScore();
        if (currentScore > gameState.bestRankScore) {
            gameState.bestRankScore = currentScore;
        }
        
        gameState.prestigeLevel++;
        gameState.prestigeMultiplier = 1 + (gameState.prestigeLevel * 0.1);
        gameState.satoshis = 0;
        gameState.clickPower = 1;
        gameState.passiveIncome = 0;
        gameState.rating = 1000;
        gameState.upgrades = {};
        
        updateUI();
        saveGame();

        // Trigger AI storyline on Hard Fork.
        if (typeof cmtSettings !== 'undefined' && cmtSettings.aiStorylineEnabled) {
            fetchAndShowAiStory('prestige', {
                prestige_level: gameState.prestigeLevel,
            });
        }

        if (buttonSystem.mode > 1) {
            randomizeButtonPositions();

            if (buttonSystem.movementTrigger === 'timer' || buttonSystem.movementTrigger === 'both') {
                startMovementTimer();
            }
        }
    };

    window.cmtSelfReset = function() {
        const modal = document.getElementById('cmt-resetModal');
        if (modal) {
            modal.classList.add('cmt-show');
        }
    };
    
    window.cmtHideResetModal = function() {
        const modal = document.getElementById('cmt-resetModal');
        if (modal) {
            modal.classList.remove('cmt-show');
        }
    };
    
    window.cmtConfirmReset = function() {
        const currentScore = calculateRankScore();
        if (currentScore > gameState.bestRankScore) {
            gameState.bestRankScore = currentScore;
        }
        const preservedBestScore = gameState.bestRankScore;
        const preservedPrestige = gameState.prestigeLevel;
        const preservedMultiplier = gameState.prestigeMultiplier;
        const preservedDifficulty = gameState.difficulty; // Preserve difficulty too
        
        gameState.satoshis = 0;
        gameState.clickPower = 1;
        gameState.passiveIncome = 0;
        gameState.rating = 1000;
        gameState.upgrades = {};
        gameState.bestRankScore = preservedBestScore;
        gameState.prestigeLevel = preservedPrestige;
        gameState.prestigeMultiplier = preservedMultiplier;
        gameState.difficulty = preservedDifficulty;
        
        cmtHideResetModal();
        
        saveGame();
        updateUI();
        
        showSaveIndicator('🔄 New Run Started!');
    };

    function formatNumber(num) {
        if (num >= 1000000000) return (num / 1000000000).toFixed(2) + 'B';
        if (num >= 1000000) return (num / 1000000).toFixed(2) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(2) + 'K';
        return num.toFixed(2);
    }

    function updateUI() {
        const effectiveClickPower = gameState.clickPower * gameState.prestigeMultiplier;
        const effectivePassiveIncome = gameState.passiveIncome * gameState.prestigeMultiplier;
        
        const satoshisEl = document.getElementById('cmt-satoshis');
        const clickPowerEl = document.getElementById('cmt-clickPower');
        const passiveIncomeEl = document.getElementById('cmt-passiveIncome');
        const ratingEl = document.getElementById('cmt-rating');
        
        if (satoshisEl) satoshisEl.textContent = formatNumber(gameState.satoshis);
        if (clickPowerEl) clickPowerEl.textContent = formatNumber(effectiveClickPower);
        if (passiveIncomeEl) passiveIncomeEl.textContent = formatNumber(effectivePassiveIncome);
        if (ratingEl) ratingEl.textContent = Math.floor(gameState.rating);
        
        const upgradesList = document.getElementById('cmt-upgradesList');
        if (upgradesList) {
            upgradesList.innerHTML = '';
            
            upgradeDefinitions.forEach(upgrade => {
                const cost = getUpgradeCost(upgrade);
                const owned = gameState.upgrades[upgrade.id] || 0;
                const canAfford = gameState.satoshis >= cost;
                const totalEffect = calculateTotalEffect(upgrade);
                
                const nextMultiplier = getDiminishingReturnsMultiplier(owned);
                const nextEffect = upgrade.baseEffect * nextMultiplier;
                
                const upgradeDiv = document.createElement('div');
                upgradeDiv.className = 'cmt-upgrade-item' + (canAfford ? '' : ' cmt-disabled');
                upgradeDiv.onclick = () => window.cmtBuyUpgrade(upgrade.id);
                
                let effectText = '';
                if (upgrade.type === 'click') {
                    effectText = `+${formatNumber(nextEffect)} per click`;
                } else {
                    effectText = `+${formatNumber(nextEffect)}/sec`;
                }
                
                let diminishingText = '';
                if (owned > 0 && nextMultiplier < 1.0) {
                    diminishingText = ` (${Math.round(nextMultiplier * 100)}% effectiveness)`;
                }
                
                upgradeDiv.innerHTML = `
                    <div class="cmt-upgrade-header">
                        <div class="cmt-upgrade-name">${upgrade.name}</div>
                        <div class="cmt-upgrade-cost">${formatNumber(cost)}</div>
                    </div>
                    <div class="cmt-upgrade-description">${effectText}${diminishingText}</div>
                    <div class="cmt-upgrade-owned">Owned: ${owned}${owned > 0 ? ` | Total: ${formatNumber(totalEffect * gameState.prestigeMultiplier)}` : ''}</div>
                `;
                
                upgradesList.appendChild(upgradeDiv);
            });
        }
        
        const prestigeCost = getPrestigeCost();
        const prestigeButton = document.getElementById('cmt-prestigeButton');
        if (prestigeButton) {
            prestigeButton.disabled = gameState.satoshis < prestigeCost;
            const hardForkLabel = getLabel('hardFork', 'Hard Fork').toUpperCase();
            prestigeButton.textContent = gameState.prestigeLevel > 0
                ? `${hardForkLabel} (Level ${gameState.prestigeLevel})`
                : hardForkLabel;
        }
        
        const prestigeInfo = document.querySelector('.cmt-prestige-info');
        if (prestigeInfo) {
            const nextPrestigeCost = getPrestigeCost();
            const currentBonus = gameState.prestigeLevel * 10;
            
            let chaosInfo = '';
            if (buttonSystem.mode >= 3 && gameState.prestigeLevel < 10) {
                chaosInfo = `<br><span style="font-size: 0.8rem; color: var(--cmt-neon-magenta);">&#x26A1; ${getLabel('chaosLevel', 'Chaos Level')}: ${Math.min(gameState.prestigeLevel + 1, 10)}/10</span>`;
            }

            // Show difficulty info if player selection enabled
            let difficultyInfo = '';
            if (gameplay.allowPlayerDifficulty) {
                const intensity = getCurrentIntensity();
                const diffName = getLabel(gameState.difficulty, gameState.difficulty.charAt(0).toUpperCase() + gameState.difficulty.slice(1));
                difficultyInfo = `<br><span style="font-size: 0.8rem; color: var(--cmt-neon-cyan);">&#x26A1; ${getLabel('difficultyLabel', 'Difficulty')}: ${diffName} (${intensity}x)</span>`;
            }

            prestigeInfo.innerHTML = `
                ${getLabel('hardFork', 'Hard Fork')} available at ${formatNumber(nextPrestigeCost)} ${currencyLabel}<br>
                <span style="font-size: 0.9rem; opacity: 0.7;">
                    ${gameState.prestigeLevel > 0 ? `Current Level: ${gameState.prestigeLevel} (+${currentBonus}% bonus)<br>` : ''}
                    ${getLabel('hardForkDesc', 'Reset with permanent +10% bonus to all production')}
                </span>
                ${difficultyInfo}
                ${chaosInfo}
            `;
        }
    }

    // ============================================
    // MINER TIMEOUT SYSTEM (From 0.8.1)
    // ============================================
    
    function passiveIncomeLoop() {
        if (!gameState.minersActive) {
            return;
        }
        
        const timeSinceActive = Date.now() - gameState.lastActiveTime;
        if (timeSinceActive >= MINER_TIMEOUT_MS) {
            gameState.minersActive = false;
            showMinerTimeoutNotification();
            updateMinerStatusUI();
            return;
        }
        
        const earned = (gameState.passiveIncome * gameState.prestigeMultiplier) / 10;
        gameState.satoshis += earned;
        gameState.satoshis = Number(gameState.satoshis.toFixed(6));
        updateUI();
    }
    
    function showMinerTimeoutNotification() {
        showSaveIndicator('⚠️ Miners Stopped - Click to restart!');
    }
    
    function updateMinerStatusUI() {
        const statusIndicator = document.getElementById('cmt-minerStatus');
        if (!statusIndicator) return;
        
        if (gameState.minersActive) {
            statusIndicator.innerHTML = `<span class="cmt-miner-active">&#x26A1; ${getLabel('minersActive', 'Miners Active')}</span>`;
            statusIndicator.classList.remove('cmt-miners-stopped');
            statusIndicator.classList.add('cmt-miners-running');
        } else {
            statusIndicator.innerHTML = `<span class="cmt-miner-stopped">💤 ${getLabel('minersStopped', 'Miners Stopped')}</span><button class="cmt-restart-miners-btn" onclick="cmtRestartMiners()">${getLabel('minersRestart', 'Restart')}</button>`;
            statusIndicator.classList.remove('cmt-miners-running');
            statusIndicator.classList.add('cmt-miners-stopped');
        }
    }
    
    window.cmtRestartMiners = function() {
        gameState.minersActive = true;
        gameState.lastActiveTime = Date.now();
        updateMinerStatusUI();
        showSaveIndicator('⚡ Miners Restarted!');
        saveGame();
    };
    
    function recordActivity() {
        gameState.lastActiveTime = Date.now();
        
        if (!gameState.minersActive) {
            gameState.minersActive = true;
            updateMinerStatusUI();
            showSaveIndicator('⚡ Miners Restarted!');
        }
    }

    function calculateOfflineProgress() {
        const lastSaveTime = localStorage.getItem('cmtLastSaveTime');
        if (!lastSaveTime) return;
        
        const now = Date.now();
        const msAway = now - parseInt(lastSaveTime);
        
        if (msAway >= MINER_TIMEOUT_MS) {
            gameState.minersActive = false;
            
            const cappedSeconds = MINER_TIMEOUT_MS / 1000;
            const offlineEarned = cappedSeconds * gameState.passiveIncome * gameState.prestigeMultiplier;
            
            if (offlineEarned > 0) {
                gameState.satoshis += offlineEarned;
                gameState.satoshis = Number(gameState.satoshis.toFixed(6));
            }
            
            setTimeout(() => {
                alert(`Welcome back! You were away for over 48 hours.\n\nYour miners earned ${formatNumber(offlineEarned)} ${currencyLabel} before stopping.\n\n⚠️ Miners auto-stop after 48 hours of inactivity.\nClick anywhere to restart them!`);
            }, 500);
            
            return;
        }
        
        const secondsAway = msAway / 1000;
        
        if (secondsAway < 60) return;
        
        const offlineEarned = secondsAway * gameState.passiveIncome * gameState.prestigeMultiplier;
        
        if (offlineEarned > 0) {
            gameState.satoshis += offlineEarned;
            gameState.satoshis = Number(gameState.satoshis.toFixed(6));
            
            const hours = Math.floor(secondsAway / 3600);
            const minutes = Math.floor((secondsAway % 3600) / 60);
            let timeAway = '';
            if (hours > 0) timeAway = `${hours}h ${minutes}m`;
            else timeAway = `${minutes}m`;
            
            setTimeout(() => {
                alert(`Welcome back! You were away for ${timeAway} and earned ${formatNumber(offlineEarned)} ${currencyLabel}!`);
            }, 500);
        }
    }

    // ============================================
    // SAVE/LOAD FUNCTIONS
    // ============================================
    
    function saveGame() {
        localStorage.setItem('cmtLastSaveTime', Date.now().toString());
        
        if (useCloudSaves) {
            saveToCloud();
        } else {
            saveToLocalStorage();
        }
    }

    function saveToLocalStorage() {
        localStorage.setItem('cmtCryptoMinerSave', JSON.stringify(gameState));
        showSaveIndicator();
    }

    async function saveToCloud() {
        try {
            const response = await fetch(cmtSettings.restUrl + 'save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': cmtSettings.nonce
                },
                body: JSON.stringify({
                    save_data: gameState
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                saveToLocalStorage();
                showSaveIndicator('☁️ Cloud Saved');
            } else {
                console.error('Cloud save failed:', data.message);
                saveToLocalStorage();
            }
        } catch (error) {
            console.error('Cloud save error:', error);
            saveToLocalStorage();
        }
    }

    async function loadGame() {
        if (useCloudSaves) {
            await loadFromCloud();
        } else {
            loadFromLocalStorage();
        }
        
        recalculateProduction();
    }

    function loadFromLocalStorage() {
        const saved = localStorage.getItem('cmtCryptoMinerSave');
        if (saved) {
            try {
                const loadedState = JSON.parse(saved);
                gameState = Object.assign({}, gameState, loadedState);
                gameState.version = '1.0.0';
                
                if (!gameState.bestRankScore) {
                    gameState.bestRankScore = calculateRankScore();
                }
                
                // Migration for 0.8.1: Add miner timeout fields if missing
                if (typeof gameState.lastActiveTime === 'undefined') {
                    gameState.lastActiveTime = Date.now();
                }
                if (typeof gameState.minersActive === 'undefined') {
                    gameState.minersActive = true;
                }
                
                // Migration for 0.9.0: Add difficulty if missing
                if (typeof gameState.difficulty === 'undefined') {
                    gameState.difficulty = gameplay.difficulty; // Use admin default
                }
                
                updateUI();
            } catch (e) {
                console.error('Failed to load saved game:', e);
            }
        } else {
            // New game - set default difficulty from admin setting
            gameState.difficulty = gameplay.difficulty;
        }
    }

    async function loadFromCloud() {
        try {
            const response = await fetch(cmtSettings.restUrl + 'load', {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': cmtSettings.nonce
                }
            });
            
            const data = await response.json();
            
            if (data.success && data.data) {
                gameState = Object.assign({}, gameState, data.data);
                gameState.version = '1.0.0';
                
                if (!gameState.bestRankScore) {
                    gameState.bestRankScore = calculateRankScore();
                }
                
                // Migration for 0.8.1
                if (typeof gameState.lastActiveTime === 'undefined') {
                    gameState.lastActiveTime = Date.now();
                }
                if (typeof gameState.minersActive === 'undefined') {
                    gameState.minersActive = true;
                }
                
                // Migration for 0.9.0
                if (typeof gameState.difficulty === 'undefined') {
                    gameState.difficulty = data.difficulty || gameplay.difficulty;
                }
                
                updateUI();
                console.log('Loaded from cloud');
            } else {
                // No cloud save - use default difficulty
                gameState.difficulty = data.difficulty || gameplay.difficulty;
                loadFromLocalStorage();
            }
        } catch (error) {
            console.error('Cloud load error:', error);
            loadFromLocalStorage();
        }
    }

    function showSaveIndicator(message = 'Game Saved') {
        const indicator = document.getElementById('cmt-saveIndicator');
        if (indicator) {
            indicator.textContent = message;
            indicator.classList.add('cmt-show');
            setTimeout(() => indicator.classList.remove('cmt-show'), 2000);
        }
    }

    // ============================================
    // MODAL FUNCTIONS
    // ============================================
    
    window.cmtShowModal = function() {
        const modal = document.getElementById('cmt-infoModal');
        if (modal) {
            modal.classList.add('cmt-show');
        }
    };

    window.cmtHideModal = function() {
        const modal = document.getElementById('cmt-infoModal');
        if (modal) {
            modal.classList.remove('cmt-show');
        }
    };

    // ============================================
    // BRANDING
    // ============================================
    
    function applyBrandingTheme() {
        if (!cmtSettings || !cmtSettings.branding || !cmtSettings.branding.enabled) {
            return;
        }
        
        const branding = cmtSettings.branding;
        const container = document.querySelector('.cmt-container');
        
        if (!container) return;
        
        if (branding.colors) {
            container.style.setProperty('--cmt-neon-cyan', branding.colors.primary);
            container.style.setProperty('--cmt-neon-magenta', branding.colors.secondary);
            container.style.setProperty('--cmt-neon-yellow', branding.colors.accent);
        }
    }

    // ============================================
    // INITIALIZATION
    // ============================================
    
    async function initGame() {
        applyBrandingTheme();
        await loadGame();
        initButtonSystem();
        initDifficultySelector(); // New in 0.9.0
        calculateOfflineProgress();
        updateUI();
        updateDifficultyButtonState(); // New in 0.9.0
        updateMinerStatusUI();
        
        recordActivity();
        
        setInterval(passiveIncomeLoop, 100);
        setInterval(saveGame, 10000);
        
        if (!localStorage.getItem('cmtCryptoMinerVisited')) {
            setTimeout(() => window.cmtShowModal(), 500);
            localStorage.setItem('cmtCryptoMinerVisited', 'true');
        }
        
        const clickArea = document.getElementById('cmt-clickArea');
        if (clickArea) {
            clickArea.addEventListener('click', function hideHint() {
                const hint = document.getElementById('cmt-clickHint');
                if (hint) {
                    hint.style.display = 'none';
                }
                clickArea.removeEventListener('click', hideHint);
            }, { once: true });
        }

        const cmtGameContainer = document.querySelector( '.cmt-container' );

        if ( cmtGameContainer ) {
            let lastTapTime = 0;
            let lastTapX    = null;
            let lastTapY    = null;

            cmtGameContainer.addEventListener( 'touchend', function( e ) {
                const now      = Date.now();
                const timeDiff = now - lastTapTime;
                const touch    = e.changedTouches && e.changedTouches[ 0 ];
                const tapX     = touch ? touch.clientX : null;
                const tapY     = touch ? touch.clientY : null;
                const sameSpot = (
                    tapX !== null && tapY !== null &&
                    lastTapX !== null && lastTapY !== null &&
                    Math.abs( tapX - lastTapX ) <= 25 &&
                    Math.abs( tapY - lastTapY ) <= 25
                );

                if ( timeDiff < 300 && timeDiff > 0 && sameSpot ) {
                    // Skip interactive elements — touch-action: manipulation on those
                    // already prevents double-tap zoom via CSS, and preventDefault()
                    // here would suppress their synthetic click events.
                    if ( !e.target.closest(
                        'button, a, input, select, textarea, label, summary, ' +
                        '[role="button"], [tabindex], .cmt-upgrade-item'
                    ) ) {
                        e.preventDefault();
                    }
                }

                lastTapTime = now;
                lastTapX    = tapX;
                lastTapY    = tapY;
            }, { passive: false } );
            // passive: false is required to allow preventDefault().
            // This only fires on the game container, not the whole page.
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGame);
    } else {
        initGame();
    }

    // ============================================
    // AI STORYLINE POPUP (New in 1.0.0)
    // ============================================

    /**
     * Resolve a media URL for an event from the localized settings.
     */
    function getMediaUrl(eventType, upgradeId, prestigeLevel) {
        if (!cmtSettings.aiMedia) return '';

        if (eventType === 'upgrade') {
            return cmtSettings.aiMedia.upgrades[upgradeId] || '';
        }

        if (eventType === 'prestige') {
            const p = cmtSettings.aiMedia.prestige;
            if (prestigeLevel === 5  && p.milestone5)  return p.milestone5;
            if (prestigeLevel === 10 && p.milestone10) return p.milestone10;
            return p.general || '';
        }

        return '';
    }

    /**
     * Detect media type from URL extension. Returns 'video', 'image', or null.
     */
    function getMediaType(url) {
        if (!url) return null;
        const clean = url.split('?')[0].toLowerCase();
        if (clean.endsWith('.mp4')) return 'video';
        const imgExts = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];
        if (imgExts.some(ext => clean.endsWith(ext))) return 'image';
        return null;
    }

    /**
     * Fetch an AI storyline message and display it in a popup.
     * Non-blocking — the game state is never paused.
     *
     * Flow:
     *   Resolve mediaUrl from cmtSettings.aiMedia (synchronous).
     *   Fetch AI message from REST (async).
     *     On failure → fallback text is used.
     *   Media (if any) plays first, then text phase.
     *   If AI is disabled: show media-only (no text). If neither, nothing.
     *
     * @param {string} eventType  'upgrade' or 'prestige'
     * @param {object} context    Event-specific context fields.
     */
    async function fetchAndShowAiStory(eventType, context) {
        if (typeof cmtSettings === 'undefined') return;
        if (cmtSettings.inContest) return;

        const mediaUrl = getMediaUrl(
            eventType,
            context.upgrade_id || '',
            typeof context.prestige_level === 'number' ? context.prestige_level : null
        );

        const fallbackMessage = eventType === 'prestige'
            ? getLabel('hardFork', 'Hard Fork') + ' complete. Level ' + (context.prestige_level || '') + ' achieved.'
            : 'You just unlocked: ' + (context.upgrade_name || 'a new upgrade') + '.';

        if (!cmtSettings.aiStorylineEnabled) {
            if (mediaUrl) {
                showAiStoryPopup('', eventType, mediaUrl);
            }
            return;
        }

        try {
            const payload = Object.assign({ event_type: eventType }, context);

            const response = await fetch(cmtSettings.restUrl + 'storyline', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload),
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const data = await response.json();
            const message = (data.success && data.message) ? data.message : fallbackMessage;

            showAiStoryPopup(message, eventType, mediaUrl);
        } catch (e) {
            console.debug('[CMT AI] Storyline fetch failed:', e);
            showAiStoryPopup(fallbackMessage, eventType, mediaUrl);
        }
    }

    /**
     * Display the AI storyline popup.
     * If mediaUrl is provided and valid, shows media first then transitions
     * to the AI text. Clicking anywhere (or Skip) advances past media immediately.
     *
     * @param {string} message    AI-generated text (may be empty for media-only)
     * @param {string} eventType  'upgrade' or 'prestige'
     * @param {string} mediaUrl   Optional media URL (MP4 or image)
     */
    function showAiStoryPopup(message, eventType, mediaUrl) {
        const existing = document.getElementById('cmt-ai-story-popup');
        if (existing) existing.remove();

        const icon        = eventType === 'prestige' ? '⚡' : '🔓';
        const accentColor = eventType === 'prestige'
            ? 'var(--cmt-neon-magenta)'
            : 'var(--cmt-neon-cyan)';

        const popup = document.createElement('div');
        popup.id = 'cmt-ai-story-popup';
        popup.setAttribute('role', 'dialog');
        popup.setAttribute('aria-modal', 'true');
        popup.style.setProperty('--cmt-story-accent', accentColor);

        document.body.appendChild(popup);

        const mediaType = getMediaType(mediaUrl);

        if (mediaType) {
            renderMediaPhase(popup, mediaUrl, mediaType, () => {
                renderTextPhase(popup, message, icon);
            });
        } else {
            renderTextPhase(popup, message, icon);
        }

        requestAnimationFrame(() => popup.classList.add('cmt-ai-story-visible'));
    }

    /**
     * Render the media phase (video or image) inside the popup.
     * Calls onComplete when media finishes, errors, or is skipped.
     */
    function renderMediaPhase(popup, mediaUrl, mediaType, onComplete) {
        popup.classList.add('cmt-ai-story-has-media');

        let mediaEl;
        let timer;
        let finished = false;

        const complete = () => {
            if (finished) return;
            finished = true;
            clearTimeout(timer);
            if (mediaEl && mediaType === 'video') {
                try { mediaEl.pause(); } catch (e) { /* ignore */ }
            }
            onComplete();
        };

        if (mediaType === 'video') {
            mediaEl = document.createElement('video');
            mediaEl.src         = mediaUrl;
            mediaEl.autoplay    = true;
            mediaEl.muted       = false;
            mediaEl.playsInline = true;
            mediaEl.controls    = false;
            mediaEl.className   = 'cmt-ai-story-media cmt-ai-story-video';
            mediaEl.addEventListener('ended', complete, { once: true });
            mediaEl.addEventListener('error', complete, { once: true });
        } else {
            mediaEl = document.createElement('img');
            mediaEl.src       = mediaUrl;
            mediaEl.alt       = '';
            mediaEl.className = 'cmt-ai-story-media cmt-ai-story-image';
            mediaEl.addEventListener('error', complete, { once: true });
            timer = setTimeout(complete, 5000);
        }

        const skipBtn = document.createElement('button');
        skipBtn.type        = 'button';
        skipBtn.className   = 'cmt-ai-story-skip';
        skipBtn.textContent = 'Skip ›';
        skipBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            complete();
        });

        const wrapper = document.createElement('div');
        wrapper.className = 'cmt-ai-story-media-wrapper';
        wrapper.appendChild(mediaEl);
        wrapper.appendChild(skipBtn);

        popup.innerHTML = '';
        popup.appendChild(wrapper);

        popup.addEventListener('click', complete, { once: true });
    }

    /**
     * Render the text phase. If message is empty (media-only mode with AI
     * disabled), dismiss the popup instead of showing an empty text box.
     */
    function renderTextPhase(popup, message, icon) {
        if (!message) {
            dismissAiStoryPopup(popup);
            return;
        }

        popup.classList.remove('cmt-ai-story-has-media');

        const renderInner = () => {
            popup.innerHTML =
                '<div class="cmt-ai-story-inner">' +
                    '<span class="cmt-ai-story-icon">' + icon + '</span>' +
                    '<p class="cmt-ai-story-text">' + escapeHtml(message) + '</p>' +
                    '<span class="cmt-ai-story-dismiss" role="button" aria-label="Dismiss">✕</span>' +
                '</div>';
            popup.style.opacity    = '';
            popup.style.transition = '';

            const timer = setTimeout(() => dismissAiStoryPopup(popup), 7000);
            popup.addEventListener('click', () => {
                clearTimeout(timer);
                dismissAiStoryPopup(popup);
            });
        };

        if (popup.childNodes.length > 0) {
            popup.style.transition = 'opacity 0.3s';
            popup.style.opacity    = '0';
            setTimeout(renderInner, 300);
        } else {
            renderInner();
        }
    }

    function dismissAiStoryPopup(popup) {
        popup.classList.remove('cmt-ai-story-visible');
        popup.classList.add('cmt-ai-story-hiding');
        popup.addEventListener('transitionend', () => popup.remove(), { once: true });
    }

    /** Basic HTML escape to safely insert AI text into innerHTML. */
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#039;');
    }
})();
