/**
 * Shortcode Arcade Crypto Idle Game - Game Logic
 * Version: 0.4.6
 *
 * Public window.* globals (intentional for gameplay):
 * - window.sacigMine() - Mining click handler
 * - window.sacigBuyUpgrade(id) - Upgrade purchase handler
 * - window.sacigPrestige() - Prestige/Hard Fork handler
 * - window.sacigSelfReset() - Self-reset (new run) handler
 * - window.sacigShowModal() - Info modal display
 * - window.sacigHideModal() - Info modal close
 *
 * LocalStorage usage:
 * - sacigCryptoMinerSave - Game state persistence
 * - sacigLastSaveTime - Offline progress calculation
 * - sacigCryptoMinerVisited - First-time user detection
 */

(function() {
    'use strict';
    
    // Game State
    let gameState = {
        satoshis: 0,
        clickPower: 1,
        passiveIncome: 0,
        rating: 1000,
        prestigeLevel: 0,
        prestigeMultiplier: 1,
        upgrades: {},
        difficulty: 'medium',
        version: '0.4.6'
    };

    // Cloud save settings (passed from WordPress)
    const cloudSavesEnabled = typeof sacigSettings !== 'undefined' && sacigSettings.cloudSavesEnabled;
    const isUserLoggedIn = typeof sacigSettings !== 'undefined' && sacigSettings.isUserLoggedIn;
    const useCloudSaves = cloudSavesEnabled && isUserLoggedIn;

    // Decoy mine buttons created for anti-bot button modes.
    let decoyButtons = [];

    /**
     * Admin gameplay settings exposed via wp_localize_script (window.sacigGameplay).
     */
    function getGameplay() {
        const gp = (typeof sacigGameplay !== 'undefined') ? sacigGameplay : {};
        return {
            difficulty: gp.difficulty || 'medium',
            difficultyIntensity: (typeof gp.difficultyIntensity === 'number') ? gp.difficultyIntensity : 0.8,
            allowPlayerDifficulty: !!gp.allowPlayerDifficulty,
            buttonMode: Math.min(3, Math.max(1, parseInt(gp.buttonMode, 10) || 1)),
            movementTrigger: gp.movementTrigger || 'none',
            enableSelfReset: gp.enableSelfReset !== false
        };
    }

    /**
     * Upgrade cost scale relative to medium (0.8 intensity):
     * easy is cheaper, hard is pricier.
     */
    function getDifficultyCostScale() {
        return getGameplay().difficultyIntensity / 0.8;
    }

    // Branding (passed from WordPress). Falls back to the default currency name.
    const sacigCurrency = (typeof sacigSettings !== 'undefined' && sacigSettings.currencyName) ? sacigSettings.currencyName : 'Satoshis';
    const sacigCurrencyLc = sacigCurrency.toLowerCase();

    // Upgrade Definitions with Elo-based balancing
    // NOTE: Prestige multiplier is applied at EARN-TIME, not purchase-time
    // This ensures idempotent progression and prevents balance issues
    const upgradeDefinitions = [
        {
            id: 'betterClicker',
            name: 'Better Pickaxe',
            baseEffect: 1,
            baseDescription: 'Increases click power',
            baseCost: 10,
            rating: 1000,
            type: 'click',
            costMultiplier: 1.15
        },
        {
            id: 'cpuMiner',
            name: 'CPU Miner',
            baseEffect: 0.1,
            baseDescription: `Generates ${sacigCurrencyLc}/sec`,
            baseCost: 50,
            rating: 1050,
            type: 'passive',
            costMultiplier: 1.2
        },
        {
            id: 'powerfulClicker',
            name: 'Diamond Pickaxe',
            baseEffect: 5,
            baseDescription: 'Increases click power',
            baseCost: 100,
            rating: 1100,
            type: 'click',
            costMultiplier: 1.15
        },
        {
            id: 'gpuRig',
            name: 'GPU Mining Rig',
            baseEffect: 1,
            baseDescription: `Generates ${sacigCurrencyLc}/sec`,
            baseCost: 500,
            rating: 1200,
            type: 'passive',
            costMultiplier: 1.25
        },
        {
            id: 'megaClicker',
            name: 'Quantum Pickaxe',
            baseEffect: 25,
            baseDescription: 'Increases click power',
            baseCost: 1000,
            rating: 1300,
            type: 'click',
            costMultiplier: 1.15
        },
        {
            id: 'asicMiner',
            name: 'ASIC Miner',
            baseEffect: 10,
            baseDescription: `Generates ${sacigCurrencyLc}/sec`,
            baseCost: 5000,
            rating: 1400,
            type: 'passive',
            costMultiplier: 1.3
        },
        {
            id: 'ultraClicker',
            name: 'Neutron Star Drill',
            baseEffect: 100,
            baseDescription: 'Increases click power',
            baseCost: 10000,
            rating: 1500,
            type: 'click',
            costMultiplier: 1.15
        },
        {
            id: 'miningFarm',
            name: 'Mining Farm',
            baseEffect: 50,
            baseDescription: `Generates ${sacigCurrencyLc}/sec`,
            baseCost: 50000,
            rating: 1600,
            type: 'passive',
            costMultiplier: 1.35
        },
        {
            id: 'godClicker',
            name: 'Black Hole Extractor',
            baseEffect: 500,
            baseDescription: 'Increases click power',
            baseCost: 100000,
            rating: 1700,
            type: 'click',
            costMultiplier: 1.15
        },
        {
            id: 'datacenter',
            name: 'Data Center',
            baseEffect: 250,
            baseDescription: `Generates ${sacigCurrencyLc}/sec`,
            baseCost: 500000,
            rating: 1800,
            type: 'passive',
            costMultiplier: 1.4
        }
    ];

    /**
     * Calculate diminishing returns multiplier
     * 1st purchase: 100% (1.0)
     * 2nd purchase: 80% (0.8)
     * 3rd purchase: 60% (0.6)
     * 4th purchase: 40% (0.4)
     * 5th+ purchase: 20% (0.2)
     */
    function getDiminishingReturnsMultiplier(ownedCount) {
        if (ownedCount === 0) return 1.0;  // First purchase: 100%
        if (ownedCount === 1) return 0.8;  // Second purchase: 80%
        if (ownedCount === 2) return 0.6;  // Third purchase: 60%
        if (ownedCount === 3) return 0.4;  // Fourth purchase: 40%
        return 0.2;                         // Fifth+ purchase: 20%
    }

    /**
     * Calculate prestige cost with exponential scaling
     * Level 0→1: 1,000,000
     * Level 1→2: 5,000,000 (5x)
     * Level 2→3: 25,000,000 (5x)
     * Level 3→4: 125,000,000 (5x)
     */
    function getPrestigeCost() {
        const basePrestigeCost = 1000000;
        const prestigeMultiplier = 5;
        
        if (gameState.prestigeLevel === 0) {
            return basePrestigeCost;
        }
        
        return basePrestigeCost * Math.pow(prestigeMultiplier, gameState.prestigeLevel);
    }

    /**
     * Calculate total effect for an upgrade with diminishing returns
     */
    function calculateTotalEffect(upgrade) {
        const owned = gameState.upgrades[upgrade.id] || 0;
        if (owned === 0) return 0;
        
        let totalEffect = 0;
        
        // Calculate effect for each owned upgrade with diminishing returns
        for (let i = 0; i < owned; i++) {
            const multiplier = getDiminishingReturnsMultiplier(i);
            totalEffect += upgrade.baseEffect * multiplier;
        }
        
        return totalEffect;
    }

    /**
     * Recalculate production stats with diminishing returns
     */
    function recalculateProduction() {
        let totalClickPower = 1; // Base click power
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

    /**
     * Calculate upgrade cost using Elo-based formula
     */
    function getUpgradeCost(upgrade) {
        const owned = gameState.upgrades[upgrade.id] || 0;
        const ratingDiff = upgrade.rating - gameState.rating;
        const eloMultiplier = Math.max(0.5, 1 + (ratingDiff / 400));
        const ownedMultiplier = Math.pow(upgrade.costMultiplier, owned);
        return Math.ceil(upgrade.baseCost * eloMultiplier * ownedMultiplier * getDifficultyCostScale());
    }

    /**
     * Mining click function
     * Prestige multiplier is applied HERE at earn-time, not at upgrade purchase time
     */
    window.sacigMine = function() {
        const earnedAmount = gameState.clickPower * gameState.prestigeMultiplier;
        gameState.satoshis += earnedAmount;
        gameState.satoshis = Number(gameState.satoshis.toFixed(6)); // Prevent floating point drift
        
        // Create floating particle effect
        const button = document.getElementById('sacig-mineButton');
        if (button) {
            const rect = button.getBoundingClientRect();
            const particle = document.createElement('div');
            particle.className = 'sacig-click-particle';
            particle.textContent = '+' + formatNumber(earnedAmount);
            particle.style.left = (rect.left + rect.width / 2 - 30) + 'px';
            particle.style.top = (rect.top + rect.height / 2) + 'px';
            document.body.appendChild(particle);
            
            setTimeout(() => particle.remove(), 1000);
        }
        
        updateUI();
    };

    /**
     * Buy upgrade function
     */
    window.sacigBuyUpgrade = function(upgradeId) {
        const upgrade = upgradeDefinitions.find(u => u.id === upgradeId);
        if (!upgrade) return;
        
        const cost = getUpgradeCost(upgrade);
        
        if (gameState.satoshis >= cost) {
            gameState.satoshis -= cost;

            // Detect the first-ever purchase of this tier before incrementing.
            const wasFirstPurchase = !gameState.upgrades[upgradeId];

            // Track owned count
            gameState.upgrades[upgradeId] = (gameState.upgrades[upgradeId] || 0) + 1;

            // Recalculate production with diminishing returns
            recalculateProduction();

            // Increase rating based on upgrade tier
            gameState.rating += 10;

            updateUI();
            saveGame();

            // AI Storyline popup fires only on the first purchase of each tier.
            if (wasFirstPurchase) {
                maybeTriggerStoryline('upgrade', upgrade.id, upgrade.name, 0);
            }
        }
    };

    /**
     * Prestige function with exponential cost scaling
     */
    window.sacigPrestige = function() {
        const prestigeCost = getPrestigeCost();
        
        if (gameState.satoshis < prestigeCost) {
            alert(`You need ${formatNumber(prestigeCost)} ${sacigCurrencyLc} to perform a Hard Fork!`);
            return;
        }
        
        const currentPrestige = gameState.prestigeLevel;
        const nextPrestigeCost = prestigeCost * 5;
        
        const confirmed = confirm(
            `Perform Hard Fork?\n\n` +
            `Current Level: ${currentPrestige}\n` +
            `New Level: ${currentPrestige + 1}\n` +
            `Current Bonus: +${currentPrestige * 10}%\n` +
            `New Bonus: +${(currentPrestige + 1) * 10}%\n\n` +
            `Cost: ${formatNumber(prestigeCost)} ${sacigCurrency}\n` +
            `Next Fork Cost: ${formatNumber(nextPrestigeCost)} ${sacigCurrency}\n\n` +
            `This will reset your progress but give you a permanent +10% production bonus!\n` +
            `Diminishing returns will also reset.`
        );
        
        if (!confirmed) return;
        
        gameState.prestigeLevel++;
        gameState.prestigeMultiplier = 1 + (gameState.prestigeLevel * 0.1);
        gameState.satoshis = 0;
        gameState.clickPower = 1;
        gameState.passiveIncome = 0;
        gameState.rating = 1000;
        gameState.upgrades = {};

        updateUI();
        saveGame();

        // AI Storyline popup fires on each completed Hard Fork.
        maybeTriggerStoryline('prestige', '', '', gameState.prestigeLevel);
    };

    /**
     * Format large numbers
     */
    function formatNumber(num) {
        if (num >= 1000000000) return (num / 1000000000).toFixed(2) + 'B';
        if (num >= 1000000) return (num / 1000000).toFixed(2) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(2) + 'K';
        return num.toFixed(2);
    }

    /**
     * Update UI
     */
    function updateUI() {
        // Calculate effective values (base * prestige multiplier)
        const effectiveClickPower = gameState.clickPower * gameState.prestigeMultiplier;
        const effectivePassiveIncome = gameState.passiveIncome * gameState.prestigeMultiplier;
        
        // Update stats
        const satoshisEl = document.getElementById('sacig-satoshis');
        const clickPowerEl = document.getElementById('sacig-clickPower');
        const passiveIncomeEl = document.getElementById('sacig-passiveIncome');
        const ratingEl = document.getElementById('sacig-rating');
        
        if (satoshisEl) satoshisEl.textContent = formatNumber(gameState.satoshis);
        if (clickPowerEl) clickPowerEl.textContent = formatNumber(effectiveClickPower);
        if (passiveIncomeEl) passiveIncomeEl.textContent = formatNumber(effectivePassiveIncome);
        if (ratingEl) ratingEl.textContent = Math.floor(gameState.rating);
        
        // Update upgrades list
        const upgradesList = document.getElementById('sacig-upgradesList');
        if (upgradesList) {
            upgradesList.innerHTML = '';
            
            upgradeDefinitions.forEach(upgrade => {
                const cost = getUpgradeCost(upgrade);
                const owned = gameState.upgrades[upgrade.id] || 0;
                const canAfford = gameState.satoshis >= cost;
                const totalEffect = calculateTotalEffect(upgrade);
                
                // Calculate next purchase effect with diminishing returns
                const nextMultiplier = getDiminishingReturnsMultiplier(owned);
                const nextEffect = upgrade.baseEffect * nextMultiplier;
                
                const upgradeDiv = document.createElement('div');
                upgradeDiv.className = 'sacig-upgrade-item' + (canAfford ? '' : ' sacig-disabled');
                upgradeDiv.onclick = () => window.sacigBuyUpgrade(upgrade.id);
                
                // Build effect text
                let effectText = '';
                if (upgrade.type === 'click') {
                    effectText = `+${formatNumber(nextEffect)} per click`;
                } else {
                    effectText = `+${formatNumber(nextEffect)}/sec`;
                }
                
                // Show diminishing returns info
                let diminishingText = '';
                if (owned > 0 && nextMultiplier < 1.0) {
                    diminishingText = ` (${Math.round(nextMultiplier * 100)}% effectiveness)`;
                }
                
                upgradeDiv.innerHTML = `
                    <div class="sacig-upgrade-header">
                        <div class="sacig-upgrade-name">${upgrade.name}</div>
                        <div class="sacig-upgrade-cost">${formatNumber(cost)}</div>
                    </div>
                    <div class="sacig-upgrade-description">${effectText}${diminishingText}</div>
                    <div class="sacig-upgrade-owned">Owned: ${owned}${owned > 0 ? ` | Total: ${formatNumber(totalEffect * gameState.prestigeMultiplier)}` : ''}</div>
                `;
                
                upgradesList.appendChild(upgradeDiv);
            });
        }
        
        // Update prestige button
        const prestigeCost = getPrestigeCost();
        const prestigeButton = document.getElementById('sacig-prestigeButton');
        if (prestigeButton) {
            prestigeButton.disabled = gameState.satoshis < prestigeCost;
            
            // Update button text with current level
            if (gameState.prestigeLevel > 0) {
                prestigeButton.textContent = `HARD FORK (Level ${gameState.prestigeLevel})`;
            }
        }
        
        // Update prestige info
        const prestigeInfo = document.querySelector('.sacig-prestige-info');
        if (prestigeInfo) {
            const nextPrestigeCost = getPrestigeCost();
            const currentBonus = gameState.prestigeLevel * 10;
            
            prestigeInfo.innerHTML = `
                Hard Fork available at ${formatNumber(nextPrestigeCost)} ${sacigCurrencyLc}<br>
                <span style="font-size: 0.9rem; opacity: 0.7;">
                    ${gameState.prestigeLevel > 0 ? `Current Level: ${gameState.prestigeLevel} (+${currentBonus}% bonus)<br>` : ''}
                    Reset with permanent +10% bonus to all production
                </span>
            `;
        }
    }

    /**
     * Passive income loop
     * Prestige multiplier is applied HERE at earn-time, not at upgrade purchase time
     */
    function passiveIncomeLoop() {
        const earned = (gameState.passiveIncome * gameState.prestigeMultiplier) / 10;
        gameState.satoshis += earned; // Update 10 times per second
        gameState.satoshis = Number(gameState.satoshis.toFixed(6)); // Prevent floating point drift
        updateUI();
    }

    /**
     * Calculate offline progress
     */
    function calculateOfflineProgress() {
        const lastSaveTime = localStorage.getItem('sacigLastSaveTime');
        if (!lastSaveTime) return;
        
        const now = Date.now();
        const secondsAway = Math.min((now - lastSaveTime) / 1000, 86400); // Cap at 24 hours
        
        // Only calculate if away for more than 60 seconds
        if (secondsAway < 60) return;
        
        const offlineEarned = secondsAway * gameState.passiveIncome * gameState.prestigeMultiplier;
        
        if (offlineEarned > 0) {
            gameState.satoshis += offlineEarned;
            gameState.satoshis = Number(gameState.satoshis.toFixed(6));
            
            // Show notification to player
            const hours = Math.floor(secondsAway / 3600);
            const minutes = Math.floor((secondsAway % 3600) / 60);
            let timeAway = '';
            if (hours > 0) timeAway = `${hours}h ${minutes}m`;
            else timeAway = `${minutes}m`;
            
            setTimeout(() => {
                alert(`Welcome back! You were away for ${timeAway} and earned ${formatNumber(offlineEarned)} ${sacigCurrencyLc}!`);
            }, 500);
        }
    }

    /**
     * Save game (local or cloud)
     */
    function saveGame() {
        // Always save timestamp for offline progress
        localStorage.setItem('sacigLastSaveTime', Date.now().toString());
        
        if (useCloudSaves) {
            saveToCloud();
        } else {
            saveToLocalStorage();
        }
    }

    /**
     * Save to localStorage
     */
    function saveToLocalStorage() {
        localStorage.setItem('sacigCryptoMinerSave', JSON.stringify(gameState));
        showSaveIndicator();
    }

    /**
     * Save to cloud
     */
    async function saveToCloud() {
        try {
            const response = await fetch(sacigSettings.restUrl + 'save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': sacigSettings.nonce
                },
                body: JSON.stringify({
                    save_data: gameState
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Also save to localStorage as backup
                saveToLocalStorage();
                showSaveIndicator('☁️ Cloud Saved');
            } else {
                console.error('Cloud save failed:', data.message);
                // Fallback to localStorage
                saveToLocalStorage();
            }
        } catch (error) {
            console.error('Cloud save error:', error);
            // Fallback to localStorage
            saveToLocalStorage();
        }
    }

    /**
     * Load game (local or cloud)
     */
    async function loadGame() {
        if (useCloudSaves) {
            await loadFromCloud();
        } else {
            loadFromLocalStorage();
        }
        
        // After loading, recalculate production to apply diminishing returns
        recalculateProduction();
    }

    /**
     * Load from localStorage
     */
    function loadFromLocalStorage() {
        const saved = localStorage.getItem('sacigCryptoMinerSave');
        if (saved) {
            try {
                const loadedState = JSON.parse(saved);
                
                // Merge loaded state with defaults (for new fields)
                gameState = Object.assign({}, gameState, loadedState);
                gameState.difficulty = gameState.difficulty || 'medium';

                // Update version
                gameState.version = '0.4.6';

                updateUI();
                syncDifficultySelector();
            } catch (e) {
                console.error('Failed to load saved game:', e);
            }
        }
    }

    /**
     * Load from cloud
     */
    async function loadFromCloud() {
        try {
            const response = await fetch(sacigSettings.restUrl + 'load', {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': sacigSettings.nonce
                }
            });
            
            const data = await response.json();
            
            if (data.success && data.data) {
                // Cloud save exists, use it
                gameState = Object.assign({}, gameState, data.data);

                // Server is authoritative for difficulty (enforces the global setting).
                gameState.difficulty = data.difficulty || gameState.difficulty || 'medium';

                // Update version
                gameState.version = '0.4.6';

                updateUI();
                syncDifficultySelector();
                console.log('Loaded from cloud');
            } else {
                // No cloud save, try localStorage
                loadFromLocalStorage();
            }
        } catch (error) {
            console.error('Cloud load error:', error);
            // Fallback to localStorage
            loadFromLocalStorage();
        }
    }

    /**
     * Show save indicator
     */
    function showSaveIndicator(message = 'Game Saved') {
        const indicator = document.getElementById('sacig-saveIndicator');
        if (indicator) {
            indicator.textContent = message;
            indicator.classList.add('sacig-show');
            setTimeout(() => indicator.classList.remove('sacig-show'), 2000);
        }
    }

    /**
     * Modal functions
     */
    window.sacigShowModal = function() {
        const modal = document.getElementById('sacig-infoModal');
        if (modal) {
            modal.classList.add('sacig-show');
        }
    };

    window.sacigHideModal = function() {
        const modal = document.getElementById('sacig-infoModal');
        if (modal) {
            modal.classList.remove('sacig-show');
        }
    };

    /**
     * AI Storyline: persistent set of upgrade tiers that have already shown a
     * popup, so a given tier only narrates once (even across Hard Forks).
     */
    function getStorylineSeen() {
        try {
            return JSON.parse(localStorage.getItem('sacigStorylineSeenUpgrades')) || [];
        } catch (e) {
            return [];
        }
    }

    function markStorylineSeen(upgradeId) {
        const seen = getStorylineSeen();
        if (seen.indexOf(upgradeId) === -1) {
            seen.push(upgradeId);
            localStorage.setItem('sacigStorylineSeenUpgrades', JSON.stringify(seen));
        }
    }

    /**
     * Decide whether to request an AI story for this milestone, then fetch it.
     */
    function maybeTriggerStoryline(eventType, upgradeId, upgradeName, prestigeLevel) {
        if (typeof sacigAI === 'undefined' || !sacigAI.enabled) {
            return;
        }

        if (eventType === 'upgrade') {
            if (getStorylineSeen().indexOf(upgradeId) !== -1) {
                return;
            }
            markStorylineSeen(upgradeId);
        }

        triggerAIStoryline(eventType, upgradeId, upgradeName, prestigeLevel);
    }

    /**
     * Resolve the prestige media URL for a given level, with per-level overrides.
     */
    function getPrestigeMedia(prestigeLevel) {
        if (typeof sacigAI === 'undefined' || !sacigAI.media) {
            return '';
        }
        if (prestigeLevel === 10 && sacigAI.media.level10) {
            return sacigAI.media.level10;
        }
        if (prestigeLevel === 5 && sacigAI.media.level5) {
            return sacigAI.media.level5;
        }
        return sacigAI.media.general || '';
    }

    /**
     * Call the storyline REST endpoint and show the popup on success.
     * Fails silently — players never see an error.
     */
    function triggerAIStoryline(eventType, upgradeId, upgradeName, prestigeLevel) {
        if (typeof sacigAI === 'undefined' || !sacigAI.enabled) {
            return;
        }

        const mediaUrl = eventType === 'prestige' ? getPrestigeMedia(prestigeLevel) : '';

        fetch(sacigAI.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': sacigAI.nonce
            },
            body: JSON.stringify({
                event_type: eventType,
                upgrade_id: upgradeId || '',
                upgrade_name: upgradeName || '',
                prestige_level: prestigeLevel || 0
            })
        })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data && data.story) {
                    showAIPopup(data.story, mediaUrl);
                }
            })
            .catch(function() {
                // Silent fail — never interrupt gameplay.
            });
    }

    /**
     * Display the AI storyline popup with an optional media element.
     */
    function showAIPopup(message, mediaUrl) {
        // Only one popup at a time.
        const existing = document.querySelector('.sacig-ai-popup-overlay');
        if (existing) {
            existing.remove();
        }

        const overlay = document.createElement('div');
        overlay.className = 'sacig-ai-popup-overlay';

        const box = document.createElement('div');
        box.className = 'sacig-ai-popup-box';

        if (mediaUrl) {
            let media;
            if (/\.mp4($|\?)/i.test(mediaUrl)) {
                media = document.createElement('video');
                media.src = mediaUrl;
                media.autoplay = true;
                media.muted = true;
                media.loop = true;
                media.playsInline = true;
            } else {
                media = document.createElement('img');
                media.src = mediaUrl;
                media.alt = '';
            }
            media.className = 'sacig-ai-popup-media';
            box.appendChild(media);
        }

        const text = document.createElement('p');
        text.className = 'sacig-ai-popup-message';
        text.textContent = message;
        box.appendChild(text);

        const close = document.createElement('button');
        close.className = 'sacig-ai-popup-close';
        close.textContent = 'Continue Mining';
        box.appendChild(close);

        overlay.appendChild(box);
        document.body.appendChild(overlay);

        // Animate in on the next tick.
        setTimeout(function() {
            overlay.classList.add('sacig-ai-popup-active');
        }, 10);

        let dismissed = false;
        function dismiss() {
            if (dismissed) {
                return;
            }
            dismissed = true;
            overlay.classList.remove('sacig-ai-popup-active');
            setTimeout(function() {
                overlay.remove();
            }, 300);
        }

        close.addEventListener('click', dismiss);
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                dismiss();
            }
        });

        // Auto-dismiss after 8 seconds.
        setTimeout(dismiss, 8000);
    }

    /**
     * Apply custom branding theme
     */
    function applyBrandingTheme() {
        if (!sacigSettings || !sacigSettings.branding || !sacigSettings.branding.enabled) {
            return;
        }
        
        const branding = sacigSettings.branding;
        const container = document.querySelector('.sacig-container');
        
        if (!container) return;
        
        // Apply custom colors
        if (branding.colors) {
            container.style.setProperty('--sacig-neon-cyan', branding.colors.primary);
            container.style.setProperty('--sacig-neon-magenta', branding.colors.secondary);
            container.style.setProperty('--sacig-neon-yellow', branding.colors.accent);
        }
    }

    /**
     * Initialize game when DOM is ready
     */
    async function initGame() {
        // Apply custom branding theme
        applyBrandingTheme();

        // Apply admin gameplay settings (anti-bot buttons, self-reset, difficulty).
        setupGameplayFeatures();

        // Load game state
        await loadGame();
        
        // Calculate offline progress
        calculateOfflineProgress();
        
        // Update UI
        updateUI();
        
        // Start passive income loop
        setInterval(passiveIncomeLoop, 100); // 10 times per second
        
        // Auto-save every 10 seconds
        setInterval(saveGame, 10000);
        
        // Show info modal on first visit
        if (!localStorage.getItem('sacigCryptoMinerVisited')) {
            setTimeout(() => window.sacigShowModal(), 500);
            localStorage.setItem('sacigCryptoMinerVisited', 'true');
        }
    }

    /**
     * Apply admin gameplay settings to the rendered game.
     */
    function setupGameplayFeatures() {
        const gp = getGameplay();
        setupButtonMode(gp);
        setupSelfReset(gp);
        setupDifficultySelector(gp);
    }

    /**
     * Anti-bot button mode: render (buttonMode - 1) identical decoys alongside
     * the real mine button. Only the real button mines; decoys give harmless
     * visual feedback. Optionally reshuffle positions on click/timer.
     */
    function setupButtonMode(gp) {
        const real = document.getElementById('sacig-mineButton');
        if (!real || gp.buttonMode < 2) {
            return;
        }
        const parent = real.parentNode;
        if (!parent) {
            return;
        }

        const decoysNeeded = gp.buttonMode - 1;
        for (let i = 0; i < decoysNeeded; i++) {
            const decoy = real.cloneNode(true);
            decoy.id = 'sacig-mineDecoy-' + i;
            decoy.removeAttribute('onclick');
            decoy.classList.add('sacig-mine-decoy');
            decoy.addEventListener('click', function(e) {
                e.stopPropagation();
                decoy.classList.add('sacig-clicked');
                setTimeout(function() { decoy.classList.remove('sacig-clicked'); }, 100);
            });
            parent.appendChild(decoy);
            decoyButtons.push(decoy);
        }

        shuffleButtons(parent);

        if (gp.movementTrigger === 'timer' || gp.movementTrigger === 'both') {
            setInterval(function() { shuffleButtons(parent); }, 3000);
        }
        if (gp.movementTrigger === 'click' || gp.movementTrigger === 'both') {
            real.addEventListener('click', function() { shuffleButtons(parent); });
        }
    }

    /**
     * Re-append the real + decoy buttons in random order to swap their positions.
     */
    function shuffleButtons(parent) {
        const real = document.getElementById('sacig-mineButton');
        const buttons = [real].concat(decoyButtons).filter(Boolean);
        buttons.sort(function() { return Math.random() - 0.5; })
            .forEach(function(b) { parent.appendChild(b); });
    }

    /**
     * Self-reset: add a NEW RUN control that resets the run while keeping
     * prestige level, multiplier and (server-side) best scores.
     */
    function setupSelfReset(gp) {
        if (!gp.enableSelfReset) {
            return;
        }
        const section = document.querySelector('.sacig-prestige-section');
        if (!section || document.getElementById('sacig-selfResetButton')) {
            return;
        }
        const btn = document.createElement('button');
        btn.id = 'sacig-selfResetButton';
        btn.type = 'button';
        btn.className = 'sacig-prestige-button sacig-self-reset-button';
        btn.textContent = 'NEW RUN';
        btn.title = 'Start a new run, keeping your prestige level and best score';
        btn.addEventListener('click', window.sacigSelfReset);
        section.appendChild(btn);
    }

    window.sacigSelfReset = function() {
        if (!window.confirm('Start a new run? You keep your prestige level and best score.')) {
            return;
        }
        resetRunKeepingPrestige();
        saveGame();
    };

    /**
     * Reset the active run but preserve prestige progression and difficulty.
     */
    function resetRunKeepingPrestige() {
        gameState.satoshis = 0;
        gameState.clickPower = 1;
        gameState.passiveIncome = 0;
        gameState.rating = 1000;
        gameState.upgrades = {};
        recalculateProduction();
        updateUI();
    }

    /**
     * Player-facing difficulty selector (only when admin allows it).
     */
    function setupDifficultySelector(gp) {
        if (!gp.allowPlayerDifficulty) {
            return;
        }
        const section = document.querySelector('.sacig-prestige-section');
        if (!section || document.getElementById('sacig-difficultySelect')) {
            return;
        }
        const wrap = document.createElement('div');
        wrap.className = 'sacig-difficulty-selector';

        const label = document.createElement('label');
        label.setAttribute('for', 'sacig-difficultySelect');
        label.textContent = 'Difficulty: ';

        const select = document.createElement('select');
        select.id = 'sacig-difficultySelect';
        ['easy', 'medium', 'hard'].forEach(function(d) {
            const opt = document.createElement('option');
            opt.value = d;
            opt.textContent = d.charAt(0).toUpperCase() + d.slice(1);
            select.appendChild(opt);
        });
        select.value = gameState.difficulty || gp.difficulty || 'medium';
        select.addEventListener('change', function() { changeDifficulty(select.value); });

        wrap.appendChild(label);
        wrap.appendChild(select);
        section.appendChild(wrap);
    }

    function syncDifficultySelector() {
        const select = document.getElementById('sacig-difficultySelect');
        if (select) {
            select.value = gameState.difficulty || 'medium';
        }
    }

    /**
     * Change difficulty. With cloud saves the server resets the run while
     * preserving prestige/best scores; otherwise reset locally.
     */
    function changeDifficulty(difficulty) {
        if (!window.confirm('Changing difficulty starts a new run on the selected difficulty. Continue?')) {
            syncDifficultySelector();
            return;
        }

        gameState.difficulty = difficulty;

        if (useCloudSaves) {
            fetch(sacigSettings.restUrl + 'change-difficulty', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': sacigSettings.nonce
                },
                body: JSON.stringify({ difficulty: difficulty })
            })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        loadGame();
                    } else {
                        syncDifficultySelector();
                    }
                })
                .catch(function() { syncDifficultySelector(); });
        } else {
            resetRunKeepingPrestige();
            saveGame();
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGame);
    } else {
        initGame();
    }
})();
