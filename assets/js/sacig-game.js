/**
 * Shortcode Arcade Crypto Idle Game - Game Logic
 * Version: 2.1.0
 *
 * Public window.* globals (intentional for gameplay):
 * - window.sacigMine() - Mining click handler
 * - window.sacigBuyUpgrade(id) - Upgrade purchase handler
 * - window.sacigPrestige() - Prestige/Hard Fork handler
 * - window.sacigSelfReset() - Self-reset (new run) handler
 * - window.sacigShowModal() - Info modal display
 * - window.sacigHideModal() - Info modal close
 *
 * Gameplay settings (window.sacigGameplay, localized by PHP) drive:
 * - Difficulty intensity (cost + chaos scaling) and Hard 2x speed
 * - Anti-bot button modes (1 real + N decoys) and movement triggers
 * - Player-selectable difficulty and self-reset controls
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
		difficulty: (typeof sacigGameplay !== 'undefined' && sacigGameplay.difficulty) ? sacigGameplay.difficulty : 'medium',
		bestRankScore: 0,
		bestRankScoreEasy: 0,
		bestRankScoreMedium: 0,
		bestRankScoreHard: 0,
		lastActiveTime: Date.now(),
		minersActive: true,
		version: '0.4.6'
	};

	// Cloud save settings (passed from WordPress)
	const cloudSavesEnabled = typeof sacigSettings !== 'undefined' && sacigSettings.cloudSavesEnabled;
	const isUserLoggedIn = typeof sacigSettings !== 'undefined' && sacigSettings.isUserLoggedIn;
	const useCloudSaves = cloudSavesEnabled && isUserLoggedIn;

	// Branding (passed from WordPress). Falls back to the default currency name.
	const sacigCurrency = (typeof sacigSettings !== 'undefined' && sacigSettings.currencyName) ? sacigSettings.currencyName : 'Satoshis';
	const sacigCurrencyLc = sacigCurrency.toLowerCase();

	// Currency symbol from branding (may arrive as an HTML entity, e.g. &#x20BF;).
	// Decode once so it can be rendered with textContent safely.
	const sacigCurrencySymbol = (function () {
		const raw = (typeof sacigSettings !== 'undefined' && sacigSettings.currencySymbol)
			? sacigSettings.currencySymbol : '₿';
		const ta = document.createElement('textarea');
		ta.innerHTML = raw;
		return ta.value || '₿';
	})();

	// Custom upgrade names from branding settings. Index matches upgradeDefinitions
	// order; an empty string means "use the hardcoded default for that tier".
	const sacigUpgradeNames = (
		typeof sacigSettings !== 'undefined' &&
		Array.isArray(sacigSettings.upgradeNames)
	) ? sacigSettings.upgradeNames : [];

	function getUpgradeName(index, defaultName) {
		const custom = sacigUpgradeNames[index];
		return (typeof custom === 'string' && custom.trim()) ? custom.trim() : defaultName;
	}

	// UI label accessor — reads from sacigSettings.labels with a safe fallback.
	const sacigLabels = (
		typeof sacigSettings !== 'undefined' && sacigSettings.labels
	) ? sacigSettings.labels : {};

	function getLabel(key, fallback) {
		const label = sacigLabels[key];
		return (label === undefined || label === null || label === '') ? fallback : label;
	}

	// Passive miners stop after this much inactivity (48 hours) to discourage idle
	// AFK farming. The player restarts them manually via sacigRestartMiners().
	const MINER_TIMEOUT_MS = 48 * 60 * 60 * 1000;

	// ===== DIFFICULTY SYSTEM =====

	/**
	 * Difficulty intensity values. Drive upgrade cost scaling and chaos level.
	 * Button movement *speed* (Hard = 2x faster) is handled separately in
	 * getMovementSpeed() / getTimerInterval().
	 */
	const DIFFICULTY_INTENSITY = {
		easy:   0.6,
		medium: 0.8,
		hard:   1.0
	};

	/**
	 * Resolve the active difficulty intensity.
	 *
	 * When players may pick their own difficulty, use the per-run gameState
	 * difficulty; otherwise use the admin's global difficultyIntensity.
	 *
	 * @return {number} Intensity in the range 0.6 - 1.0 (default 0.8).
	 */
	function getCurrentIntensity() {
		if (typeof sacigGameplay !== 'undefined' && sacigGameplay.allowPlayerDifficulty) {
			return DIFFICULTY_INTENSITY[gameState.difficulty] || 0.8;
		}
		return (typeof sacigGameplay !== 'undefined' && sacigGameplay.difficultyIntensity)
			? sacigGameplay.difficultyIntensity : 0.8;
	}

	/**
	 * Read the gameplay settings localized by PHP with safe defaults.
	 *
	 * @return {{difficulty:string, difficultyIntensity:number, allowPlayerDifficulty:boolean,
	 *           buttonMode:number, movementTrigger:string, enableSelfReset:boolean}}
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

	// ===== BUTTON MODE / MOVEMENT STATE =====

	let buttonSystem = {
		mode:            (typeof sacigGameplay !== 'undefined') ? Math.min(3, Math.max(1, parseInt(sacigGameplay.buttonMode, 10) || 1)) : 1,
		movementTrigger: (typeof sacigGameplay !== 'undefined' && sacigGameplay.movementTrigger) ? sacigGameplay.movementTrigger : 'none',
		timerInterval:   null,
		lastMoveTime:    0,
		moveCount:       0
	};

	// Upgrade Definitions with Elo-based balancing
	// NOTE: Prestige multiplier is applied at EARN-TIME, not purchase-time
	// This ensures idempotent progression and prevents balance issues
	const upgradeDefinitions = [
		{
			id: 'betterClicker',
			name: getUpgradeName(0, 'Better Pickaxe'),
			baseEffect: 1,
			baseDescription: 'Increases click power',
			baseCost: 10,
			rating: 1000,
			type: 'click',
			costMultiplier: 1.15
		},
		{
			id: 'cpuMiner',
			name: getUpgradeName(1, 'CPU Miner'),
			baseEffect: 0.1,
			baseDescription: `Generates ${sacigCurrencyLc}/sec`,
			baseCost: 50,
			rating: 1050,
			type: 'passive',
			costMultiplier: 1.2
		},
		{
			id: 'powerfulClicker',
			name: getUpgradeName(2, 'Diamond Pickaxe'),
			baseEffect: 5,
			baseDescription: 'Increases click power',
			baseCost: 100,
			rating: 1100,
			type: 'click',
			costMultiplier: 1.15
		},
		{
			id: 'gpuRig',
			name: getUpgradeName(3, 'GPU Mining Rig'),
			baseEffect: 1,
			baseDescription: `Generates ${sacigCurrencyLc}/sec`,
			baseCost: 500,
			rating: 1200,
			type: 'passive',
			costMultiplier: 1.25
		},
		{
			id: 'megaClicker',
			name: getUpgradeName(4, 'Quantum Pickaxe'),
			baseEffect: 25,
			baseDescription: 'Increases click power',
			baseCost: 1000,
			rating: 1300,
			type: 'click',
			costMultiplier: 1.15
		},
		{
			id: 'asicMiner',
			name: getUpgradeName(5, 'ASIC Miner'),
			baseEffect: 10,
			baseDescription: `Generates ${sacigCurrencyLc}/sec`,
			baseCost: 5000,
			rating: 1400,
			type: 'passive',
			costMultiplier: 1.3
		},
		{
			id: 'ultraClicker',
			name: getUpgradeName(6, 'Neutron Star Drill'),
			baseEffect: 100,
			baseDescription: 'Increases click power',
			baseCost: 10000,
			rating: 1500,
			type: 'click',
			costMultiplier: 1.15
		},
		{
			id: 'miningFarm',
			name: getUpgradeName(7, 'Mining Farm'),
			baseEffect: 50,
			baseDescription: `Generates ${sacigCurrencyLc}/sec`,
			baseCost: 50000,
			rating: 1600,
			type: 'passive',
			costMultiplier: 1.35
		},
		{
			id: 'godClicker',
			name: getUpgradeName(8, 'Black Hole Extractor'),
			baseEffect: 500,
			baseDescription: 'Increases click power',
			baseCost: 100000,
			rating: 1700,
			type: 'click',
			costMultiplier: 1.15
		},
		{
			id: 'datacenter',
			name: getUpgradeName(9, 'Data Center'),
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
	 * Calculate upgrade cost using the Elo-based formula, scaled by difficulty.
	 *
	 * Difficulty math:
	 *   intensity drives difficultyModifier via 0.8 + (intensity * 0.4)
	 *   - easy   (0.6) → 1.04x cost
	 *   - medium (0.8) → 1.12x cost
	 *   - hard   (1.0) → 1.20x cost
	 * Speed effects (Hard = 2x faster button movement/swap) are handled
	 * separately in getMovementSpeed() and getTimerInterval().
	 *
	 * @param {object} upgrade Upgrade definition.
	 * @return {number} Cost in currency, rounded up.
	 */
	function getUpgradeCost(upgrade) {
		const owned         = gameState.upgrades[upgrade.id] || 0;
		const ratingDiff    = upgrade.rating - gameState.rating;
		const eloMultiplier = Math.max(0.5, 1 + (ratingDiff / 400));
		const ownedMultiplier = Math.pow(upgrade.costMultiplier, owned);

		// Difficulty scales the Elo cost formula.
		// intensity: easy=0.6, medium=0.8, hard=1.0
		// Resulting cost modifier: easy=1.04x, medium=1.12x, hard=1.20x
		const intensity          = getCurrentIntensity();
		const difficultyModifier = 0.8 + (intensity * 0.4);

		return Math.ceil(upgrade.baseCost * eloMultiplier * ownedMultiplier * difficultyModifier);
	}

	/**
	 * Compute the current rank score, mirroring the server's
	 * SACIG_Cloud_Save::calculate_rank_score (logarithmic + prestige weight).
	 *
	 * @return {number} Rank score for the current run.
	 */
	function getCurrentRankScore() {
		const baseScore = Math.log10(gameState.satoshis + 1) * 1000;
		const prestigeBonus = gameState.prestigeLevel * 10000;
		return baseScore + prestigeBonus;
	}

	/**
	 * Track best rank scores overall and per difficulty.
	 *
	 * Best scores never decrease; the overall best is the max across all
	 * per-difficulty bests. Mirrors the server's per-difficulty columns so the
	 * leaderboard standing survives a self-reset or difficulty change.
	 */
	function updateBestScores() {
		const currentScore = getCurrentRankScore();
		const diffKey = 'bestRankScore' +
			gameState.difficulty.charAt(0).toUpperCase() +
			gameState.difficulty.slice(1);

		if (currentScore > (gameState[diffKey] || 0)) {
			gameState[diffKey] = currentScore;
		}

		gameState.bestRankScore = Math.max(
			gameState.bestRankScore || 0,
			gameState.bestRankScoreEasy || 0,
			gameState.bestRankScoreMedium || 0,
			gameState.bestRankScoreHard || 0
		);
	}

	/**
	 * Mining click function
	 * Prestige multiplier is applied HERE at earn-time, not at upgrade purchase time
	 */
	window.sacigMine = function() {
		recordActivity();

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

		updateBestScores();
		updateUI();

		// Movement trigger: reposition buttons after a real mine click.
		if (buttonSystem.mode >= 2 &&
			(buttonSystem.movementTrigger === 'click' || buttonSystem.movementTrigger === 'both')) {
			randomizeButtonPositions();
		}
	};

	/**
	 * Buy upgrade function
	 */
	window.sacigBuyUpgrade = function(upgradeId) {
		recordActivity();

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

			updateBestScores();
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

		// Snapshot best scores before the run resets.
		updateBestScores();

		gameState.prestigeLevel++;
		gameState.prestigeMultiplier = 1 + (gameState.prestigeLevel * 0.1);
		gameState.satoshis = 0;
		gameState.clickPower = 1;
		gameState.passiveIncome = 0;
		gameState.rating = 1000;
		gameState.upgrades = {};

		updateUI();
		saveGame();

		// Chaos scales with prestige level in button mode 3, so refresh the timer.
		startMovementTimer();

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

		if (satoshisEl) satoshisEl.textContent = sacigCurrencySymbol + ' ' + formatNumber(gameState.satoshis);
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
					${getLabel('hardForkDesc', 'Reset with permanent +10% bonus to all production')}
				</span>
			`;
		}
	}

	/**
	 * Passive income loop
	 * Prestige multiplier is applied HERE at earn-time, not at upgrade purchase time
	 */
	function passiveIncomeLoop() {
		// Stop passive miners after prolonged inactivity (anti-AFK farming).
		if (gameState.minersActive) {
			const timeSinceActive = Date.now() - (gameState.lastActiveTime || Date.now());
			if (timeSinceActive >= MINER_TIMEOUT_MS) {
				gameState.minersActive = false;
				updateMinerStatusUI();
			}
		}

		// Passive income only accrues while the miners are active. Skip the UI
		// refresh entirely when nothing is earned to avoid 10x/sec DOM thrashing.
		if (gameState.minersActive && gameState.passiveIncome > 0) {
			const earned = (gameState.passiveIncome * gameState.prestigeMultiplier) / 10;
			gameState.satoshis += earned; // Update 10 times per second
			gameState.satoshis = Number(gameState.satoshis.toFixed(6)); // Prevent floating point drift
			updateUI();
		}
	}

	/**
	 * Record player activity to keep the passive miners alive. Called on every
	 * manual mine and upgrade purchase.
	 */
	function recordActivity() {
		gameState.lastActiveTime = Date.now();
	}

	/**
	 * Restart the passive miners after an inactivity timeout. Exposed globally so
	 * the in-game "Restart" button can call it.
	 */
	window.sacigRestartMiners = function() {
		gameState.minersActive = true;
		gameState.lastActiveTime = Date.now();
		updateMinerStatusUI();
		updateUI();
		saveGame();
	};

	/**
	 * Reflect the miner active/stopped state in the status indicator and toggle
	 * the restart button.
	 */
	function updateMinerStatusUI() {
		const statusEl = document.getElementById('sacig-minerStatus');
		const restartBtn = document.getElementById('sacig-restartMinersButton');

		if (statusEl) {
			if (gameState.minersActive) {
				statusEl.textContent = getLabel('minersActive', 'Miners Active');
				statusEl.classList.remove('sacig-miners-stopped');
				statusEl.classList.add('sacig-miners-active');
			} else {
				statusEl.textContent = getLabel('minersStopped', 'Miners Stopped');
				statusEl.classList.remove('sacig-miners-active');
				statusEl.classList.add('sacig-miners-stopped');
			}
		}

		if (restartBtn) {
			restartBtn.textContent = getLabel('minersRestart', 'Restart');
			restartBtn.style.display = gameState.minersActive ? 'none' : '';
		}
	}

	/**
	 * Calculate offline progress
	 */
	function calculateOfflineProgress() {
		const lastSaveRaw = localStorage.getItem('sacigLastSaveTime');
		if (!lastSaveRaw) return;

		const lastSaveTime = Number(lastSaveRaw);
		const now = Date.now();

		// Miners that had already stopped before the player left earn nothing
		// offline — they must be restarted manually.
		if (gameState.minersActive === false) {
			setTimeout(() => {
				alert('Your miners had stopped before you left. Hit Restart to resume passive mining.');
			}, 500);
			return;
		}

		// Passive miners keep running offline only until the inactivity timeout
		// (48h). Offline earnings are capped at the MINER_TIMEOUT_MS window; if the
		// player was away longer, the miners stop and earnings are capped.
		const msAway = Math.max(0, now - lastSaveTime);
		let secondsAway;
		let timedOut = false;

		if (msAway >= MINER_TIMEOUT_MS) {
			secondsAway = MINER_TIMEOUT_MS / 1000;
			timedOut    = true;
		} else {
			secondsAway = msAway / 1000;
		}

		// Only calculate if the miners ran offline for more than 60 seconds.
		if (secondsAway >= 60) {
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

				const suffix = timedOut ? ' Your miners have since stopped — hit Restart to resume.' : '';
				setTimeout(() => {
					alert(`Welcome back! You were away for ${timeAway} and earned ${formatNumber(offlineEarned)} ${sacigCurrencyLc}!${suffix}`);
				}, 500);
			}
		}

		// If the inactivity window elapsed while the player was away, the miners
		// are now stopped (UI is refreshed by the updateMinerStatusUI() call that
		// follows this in initGame()).
		if (timedOut) {
			gameState.minersActive = false;
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
				startMovementTimer();
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

		const mediaUrl = (eventType === 'prestige')
			? getPrestigeMedia(prestigeLevel)
			: getUpgradeMedia(upgradeId);

		triggerAIStoryline(eventType, upgradeId, upgradeName, prestigeLevel, mediaUrl);
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
	 * Resolve the per-upgrade media URL configured by the admin.
	 *
	 * @param {string} upgradeId Upgrade definition id.
	 * @return {string} Media URL or empty string.
	 */
	function getUpgradeMedia(upgradeId) {
		if (typeof sacigAI === 'undefined' || !sacigAI.upgradeMedia) {
			return '';
		}
		return sacigAI.upgradeMedia[upgradeId] || '';
	}

	/**
	 * Call the storyline REST endpoint and show the popup on success.
	 * Fails silently — players never see an error.
	 */
	function triggerAIStoryline(eventType, upgradeId, upgradeName, prestigeLevel, mediaUrl) {
		if (typeof sacigAI === 'undefined' || !sacigAI.enabled) {
			return;
		}

		if (typeof mediaUrl === 'undefined') {
			mediaUrl = (eventType === 'prestige')
				? getPrestigeMedia(prestigeLevel)
				: getUpgradeMedia(upgradeId);
		}

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
	 * Detect media type from a URL extension.
	 *
	 * @param {string} url Media URL.
	 * @return {string|null} 'video', 'image', or null.
	 */
	function getMediaType(url) {
		if (!url) return null;
		const clean = url.split('?')[0].toLowerCase();
		if (clean.endsWith('.mp4')) return 'video';
		const imgs = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];
		for (let i = 0; i < imgs.length; i++) {
			if (clean.endsWith(imgs[i])) return 'image';
		}
		return null;
	}

	/**
	 * Display the AI storyline popup. When media is present it plays first
	 * (with a Skip button), then transitions to the AI text phase.
	 *
	 * @param {string} message  AI-generated text (may be empty for media-only).
	 * @param {string} mediaUrl Optional MP4/image URL.
	 */
	function showAIPopup(message, mediaUrl) {
		// Only one popup at a time.
		const existing = document.querySelector('.sacig-ai-popup-overlay');
		if (existing) {
			existing.remove();
		}

		const mediaType = getMediaType(mediaUrl);

		const overlay = document.createElement('div');
		overlay.className = 'sacig-ai-popup-overlay';
		document.body.appendChild(overlay);

		if (mediaType) {
			renderMediaPhase(overlay, mediaUrl, mediaType, function() {
				if (message) {
					renderTextPhase(overlay, message);
				} else {
					overlay.remove();
				}
			});
		} else {
			if (!message) {
				overlay.remove();
				return;
			}
			renderTextPhase(overlay, message);
		}

		// Animate in on the next tick.
		setTimeout(function() { overlay.classList.add('sacig-ai-popup-active'); }, 10);
	}

	/**
	 * Render the media phase (video or image) with a Skip button. Calls
	 * onComplete when the media ends, errors, times out, or is skipped/clicked.
	 *
	 * @param {HTMLElement} overlay   Popup overlay element.
	 * @param {string}      mediaUrl  Media URL.
	 * @param {string}      mediaType 'video' or 'image'.
	 * @param {Function}    onComplete Callback to advance to the text phase.
	 */
	function renderMediaPhase(overlay, mediaUrl, mediaType, onComplete) {
		overlay.classList.add('sacig-ai-popup-has-media');

		let finished = false;
		let timer;
		let mediaEl;

		function complete() {
			if (finished) return;
			finished = true;
			clearTimeout(timer);
			if (mediaEl && mediaType === 'video') {
				try { mediaEl.pause(); } catch (e) { /* ignore */ }
			}
			overlay.innerHTML = '';
			onComplete();
		}

		if (mediaType === 'video') {
			mediaEl = document.createElement('video');
			mediaEl.src         = mediaUrl;
			mediaEl.autoplay    = true;
			mediaEl.muted       = false;
			mediaEl.playsInline = true;
			mediaEl.controls    = false;
			mediaEl.className   = 'sacig-ai-popup-media sacig-ai-popup-video';
			mediaEl.addEventListener('ended', complete, { once: true });
			mediaEl.addEventListener('error', complete, { once: true });
		} else {
			mediaEl = document.createElement('img');
			mediaEl.src       = mediaUrl;
			mediaEl.alt       = '';
			mediaEl.className = 'sacig-ai-popup-media sacig-ai-popup-image';
			mediaEl.addEventListener('error', complete, { once: true });
			timer = setTimeout(complete, 5000);
		}

		const skipBtn = document.createElement('button');
		skipBtn.type        = 'button';
		skipBtn.className   = 'sacig-ai-popup-skip';
		skipBtn.textContent = 'Skip ›';
		skipBtn.addEventListener('click', function(e) {
			e.stopPropagation();
			complete();
		});

		const wrapper = document.createElement('div');
		wrapper.className = 'sacig-ai-popup-media-wrapper';
		wrapper.appendChild(mediaEl);
		wrapper.appendChild(skipBtn);
		overlay.appendChild(wrapper);
		overlay.addEventListener('click', complete, { once: true });
	}

	/**
	 * Render the AI text phase with a Continue button and auto-dismiss.
	 *
	 * @param {HTMLElement} overlay Popup overlay element.
	 * @param {string}      message AI-generated text.
	 */
	function renderTextPhase(overlay, message) {
		const box = document.createElement('div');
		box.className = 'sacig-ai-popup-box';

		const text = document.createElement('p');
		text.className = 'sacig-ai-popup-message';
		text.textContent = message;

		const close = document.createElement('button');
		close.type = 'button';
		close.className = 'sacig-ai-popup-close';
		close.textContent = 'Continue Mining';

		box.appendChild(text);
		box.appendChild(close);
		overlay.appendChild(box);

		let dismissed = false;
		function dismiss() {
			if (dismissed) return;
			dismissed = true;
			overlay.classList.remove('sacig-ai-popup-active');
			setTimeout(function() { overlay.remove(); }, 300);
		}

		close.addEventListener('click', dismiss);
		overlay.addEventListener('click', function(e) {
			if (e.target === overlay) dismiss();
		});
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

	// ===== BUTTON MODE SYSTEM =====

	/**
	 * Percentage-based slot positions for N buttons within the click area.
	 * Mode 2: side-by-side. Mode 3: triangle.
	 *
	 * @param {number} buttonCount Number of buttons (1-3).
	 * @return {Array<{x:number,y:number}>}
	 */
	function getSlotPositions(buttonCount) {
		if (buttonCount === 2) {
			return [
				{ x: 25, y: 50 },
				{ x: 75, y: 50 }
			];
		}
		if (buttonCount === 3) {
			return [
				{ x: 25, y: 35 },
				{ x: 75, y: 35 },
				{ x: 50, y: 75 }
			];
		}
		return [{ x: 50, y: 50 }];
	}

	/**
	 * Build the anti-bot button layout for mode 2/3.
	 *
	 * The real mine button stays in the click area; (mode - 1) decoy clones are
	 * appended alongside it and all buttons are absolutely positioned into random
	 * slots. Only the real button mines; clicking a decoy plays a shake + ✗ and
	 * earns nothing.
	 *
	 * No-op for mode 1 (single button keeps its original markup/behaviour).
	 *
	 * @return {void}
	 */
	function renderButtonSlots() {
		if (buttonSystem.mode < 2) return;

		const clickArea = document.getElementById('sacig-clickArea');
		if (!clickArea) return;

		const real = document.getElementById('sacig-mineButton');
		if (!real) return;

		// The real button mines; mark it for brighter styling.
		real.classList.add('sacig-real-button');

		const decoysNeeded = buttonSystem.mode - 1;
		for (let i = 0; i < decoysNeeded; i++) {
			const decoy = real.cloneNode(true);
			decoy.id = 'sacig-decoyButton-' + i;
			decoy.removeAttribute('onclick');
			decoy.classList.remove('sacig-real-button');
			decoy.classList.add('sacig-decoy-button');
			(function (d) {
				d.addEventListener('click', function (e) {
					e.stopPropagation();
					sacigDecoyClick(d);
				});
			})(decoy);
			clickArea.appendChild(decoy);
		}

		randomizeButtonPositions();
	}

	/**
	 * Handle a decoy button click: shake + brief "Wrong button!" flash. No earn.
	 *
	 * @param {HTMLElement} btn The decoy button element.
	 * @return {void}
	 */
	function sacigDecoyClick(btn) {
		btn.classList.add('sacig-shake');
		setTimeout(function() { btn.classList.remove('sacig-shake'); }, 300);

		const rect = btn.getBoundingClientRect();

		// Floating ✗ indicator over the clicked decoy.
		const indicator = document.createElement('div');
		indicator.className = 'sacig-decoy-indicator';
		indicator.textContent = '✗'; // ✗
		indicator.style.left = (rect.left + rect.width / 2 - 15) + 'px';
		indicator.style.top  = (rect.top  + rect.height / 2 - 15) + 'px';
		document.body.appendChild(indicator);
		setTimeout(function() { indicator.remove(); }, 500);

		// "Wrong button!" flash (retained from prior behavior).
		const flash = document.createElement('div');
		flash.className = 'sacig-click-particle sacig-wrong-flash';
		flash.textContent = getLabel('wrongButton', 'Wrong button!');
		flash.style.left = (rect.left + rect.width / 2 - 40) + 'px';
		flash.style.top  = (rect.top + rect.height / 2 + 20) + 'px';
		document.body.appendChild(flash);
		setTimeout(function() { flash.remove(); }, 600);
	}

	/**
	 * Render the "find the brighter coin" hint below the click area (mode 2/3).
	 * Hidden on the first real mine click.
	 *
	 * @return {void}
	 */
	function renderClickHint() {
		if (buttonSystem.mode < 2) return;
		const section = document.querySelector('.sacig-game-area');
		if (!section || document.getElementById('sacig-clickHint')) return;

		const hint = document.createElement('div');
		hint.id = 'sacig-clickHint';
		hint.className = 'sacig-click-hint';
		hint.textContent = getLabel('findCoinPrompt', 'Find the brighter coin to mine!');
		section.appendChild(hint);

		const real = document.getElementById('sacig-mineButton');
		if (real) {
			real.addEventListener('click', function hideHint() {
				hint.style.display = 'none';
				real.removeEventListener('click', hideHint);
			}, { once: true });
		}
	}

	// ===== MOVEMENT TRIGGER SYSTEM =====

	/**
	 * Chaos level (0..1) drives button movement/swap speed in mode 3, scaling
	 * with prestige level and difficulty intensity. Modes 1/2 use a flat 0.3.
	 *
	 * @return {number} Chaos level between 0 and 1.
	 */
	function getChaosLevel() {
		if (buttonSystem.mode < 3) return 0.3;
		const effectiveLevel = Math.min(gameState.prestigeLevel, 10);
		const baseIntensity = getCurrentIntensity();
		return Math.min(1.0, (0.2 + effectiveLevel * 0.08) * baseIntensity);
	}

	/**
	 * Position-swap animation duration (ms). Hard difficulty halves it (2x faster).
	 *
	 * @return {number} Duration in milliseconds (min 150).
	 */
	function getMovementSpeed() {
		const chaos = getChaosLevel();
		const baseSpeed = Math.floor(800 - (chaos * 400));
		const difficulty = gameState.difficulty || (typeof sacigGameplay !== 'undefined' ? sacigGameplay.difficulty : 'medium');
		const speedMultiplier = (difficulty === 'hard') ? 0.5 : 1.0;
		return Math.max(150, Math.floor(baseSpeed * speedMultiplier));
	}

	/**
	 * Timer-driven swap interval (ms). Hard difficulty halves it (2x faster).
	 *
	 * @return {number} Interval in milliseconds (min 500).
	 */
	function getTimerInterval() {
		const chaos = getChaosLevel();
		const baseInterval = Math.floor(3000 - (chaos * 1500));
		const difficulty = gameState.difficulty || (typeof sacigGameplay !== 'undefined' ? sacigGameplay.difficulty : 'medium');
		const speedMultiplier = (difficulty === 'hard') ? 0.5 : 1.0;
		return Math.max(500, Math.floor(baseInterval * speedMultiplier));
	}

	/**
	 * Animate buttons to randomized absolute slot positions with chaos jitter.
	 * No-op outside button mode 2/3.
	 *
	 * @return {void}
	 */
	function randomizeButtonPositions() {
		const clickArea = document.getElementById('sacig-clickArea');
		if (!clickArea || buttonSystem.mode < 2) return;

		const buttons = Array.prototype.slice.call(
			clickArea.querySelectorAll('.sacig-mine-button')
		);
		if (buttons.length <= 1) return;

		const slots = getSlotPositions(buttons.length);
		const chaos = getChaosLevel();

		// Fisher-Yates shuffle of the slot list.
		const shuffled = slots.slice();
		for (let i = shuffled.length - 1; i > 0; i--) {
			const j = Math.floor(Math.random() * (i + 1));
			const tmp = shuffled[i];
			shuffled[i] = shuffled[j];
			shuffled[j] = tmp;
		}

		const areaWidth  = clickArea.offsetWidth;
		const areaHeight = clickArea.offsetHeight;
		const buttonSize = buttons[0].offsetWidth;
		const speed = getMovementSpeed();

		buttons.forEach(function (btn, idx) {
			const slot = shuffled[idx];
			let x = (slot.x / 100) * areaWidth  - (buttonSize / 2);
			let y = (slot.y / 100) * areaHeight - (buttonSize / 2);

			if (chaos > 0.3) {
				const jitter = 30 * chaos;
				x += (Math.random() - 0.5) * jitter * 2;
				y += (Math.random() - 0.5) * jitter * 2;
			}

			x = Math.max(10, Math.min(areaWidth  - buttonSize - 10, x));
			y = Math.max(10, Math.min(areaHeight - buttonSize - 10, y));

			btn.style.transition = 'left ' + speed + 'ms ease-out, top ' + speed + 'ms ease-out';
			btn.style.position   = 'absolute';
			btn.style.transform  = 'none';
			btn.style.left       = x + 'px';
			btn.style.top        = y + 'px';
		});

		buttonSystem.lastMoveTime = Date.now();
		buttonSystem.moveCount++;
	}

	/**
	 * (Re)start the timer-based movement loop at the current difficulty speed.
	 * Clears any existing timer first so difficulty/prestige changes take effect.
	 *
	 * @return {void}
	 */
	function startMovementTimer() {
		if (buttonSystem.timerInterval) {
			clearInterval(buttonSystem.timerInterval);
			buttonSystem.timerInterval = null;
		}
		if (buttonSystem.mode < 2) return;
		if (buttonSystem.movementTrigger === 'timer' || buttonSystem.movementTrigger === 'both') {
			buttonSystem.timerInterval = setInterval(randomizeButtonPositions, getTimerInterval());
		}
	}

	// ===== SELF-RESET SYSTEM =====

	/**
	 * Render the self-reset (NEW RUN) control when enabled by the admin.
	 *
	 * @return {void}
	 */
	function setupSelfReset() {
		if (typeof sacigGameplay === 'undefined' || sacigGameplay.enableSelfReset === false) {
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

	/**
	 * Self-reset handler. Preserves prestige progression and best scores while
	 * resetting the active run.
	 *
	 * @return {void}
	 */
	window.sacigSelfReset = function() {
		const modal = document.getElementById('sacig-resetModal');

		// Fallback to a native confirm if the modal markup is absent.
		if (!modal) {
			if (!window.confirm('Start a new run? You keep your prestige level and best score.')) {
				return;
			}
			updateBestScores();
			performRunReset();
			saveGame();
			updateUI();
			return;
		}

		const confirmBtn = document.getElementById('sacig-confirmResetBtn');
		if (confirmBtn) {
			confirmBtn.onclick = function() {
				modal.style.display = 'none';
				updateBestScores();
				performRunReset();
				saveGame();
				updateUI();
			};
		}

		modal.style.display = 'flex';
	};

	/**
	 * Reset the active run fields, preserving prestige and best scores.
	 *
	 * @return {void}
	 */
	function performRunReset() {
		gameState.satoshis = 0;
		gameState.clickPower = 1;
		gameState.passiveIncome = 0;
		gameState.rating = 1000;
		gameState.upgrades = {};
		recalculateProduction();
	}

	// ===== DIFFICULTY SELECTOR UI =====

	/**
	 * Render the player difficulty selector (Easy/Medium/Hard) when the admin
	 * allows player-selectable difficulty.
	 *
	 * @return {void}
	 */
	function setupDifficultySelector() {
		if (typeof sacigGameplay === 'undefined' || !sacigGameplay.allowPlayerDifficulty) {
			return;
		}
		const section = document.querySelector('.sacig-prestige-section');
		if (!section || document.getElementById('sacig-difficultySelector')) {
			return;
		}

		const wrap = document.createElement('div');
		wrap.id = 'sacig-difficultySelector';
		wrap.className = 'sacig-difficulty-selector';

		const label = document.createElement('span');
		label.className = 'sacig-difficulty-label';
		label.textContent = getLabel('difficultyLabel', 'Difficulty') + ':';
		wrap.appendChild(label);

		['easy', 'medium', 'hard'].forEach(function(d) {
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'sacig-difficulty-button' + (gameState.difficulty === d ? ' sacig-active' : '');
			btn.dataset.difficulty = d;
			btn.textContent = d.charAt(0).toUpperCase() + d.slice(1);
			btn.addEventListener('click', function() { confirmDifficultyChange(d); });
			wrap.appendChild(btn);
		});

		section.appendChild(wrap);
	}

	/**
	 * Reflect the active difficulty in the selector button highlight + dropdown.
	 *
	 * @return {void}
	 */
	function syncDifficultySelector() {
		const buttons = document.querySelectorAll('.sacig-difficulty-button');
		buttons.forEach(function(btn) {
			if (btn.dataset.difficulty === gameState.difficulty) {
				btn.classList.add('sacig-active');
			} else {
				btn.classList.remove('sacig-active');
			}
		});
	}

	/**
	 * Confirm a difficulty change with the player before resetting the run.
	 *
	 * @param {string} newDifficulty One of 'easy' | 'medium' | 'hard'.
	 * @return {void}
	 */
	function confirmDifficultyChange(newDifficulty) {
		if (newDifficulty === gameState.difficulty) return;

		const label = newDifficulty.charAt(0).toUpperCase() + newDifficulty.slice(1);
		const modal = document.getElementById('sacig-difficultyModal');

		// Fallback to a native confirm if the modal markup is absent.
		if (!modal) {
			const ok = window.confirm(
				'Changing difficulty resets your current run but keeps prestige and best scores.\n\n' +
				'Switch to ' + label + '?'
			);
			if (!ok) {
				syncDifficultySelector();
				return;
			}
			changeDifficulty(newDifficulty);
			return;
		}

		const info = document.getElementById('sacig-difficultyChangeInfo');
		if (info) {
			info.textContent = getLabel('difficultyLabel', 'Difficulty') + ': ' + label;
		}

		const confirmBtn = document.getElementById('sacig-confirmDifficultyBtn');
		if (confirmBtn) {
			confirmBtn.onclick = function() {
				modal.style.display = 'none';
				changeDifficulty(newDifficulty);
			};
		}

		modal.style.display = 'flex';
	}

	/**
	 * Change difficulty. Snapshots best scores, then resets the run via the
	 * server (cloud saves) or locally. Falls back to a local reset on any error.
	 *
	 * @param {string} newDifficulty One of 'easy' | 'medium' | 'hard'.
	 * @return {void}
	 */
	function changeDifficulty(newDifficulty) {
		if (newDifficulty === gameState.difficulty) return;

		updateBestScores(); // snapshot before reset

		if (useCloudSaves) {
			fetch(sacigSettings.restUrl + 'change-difficulty', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': sacigSettings.nonce
				},
				body: JSON.stringify({ difficulty: newDifficulty })
			})
				.then(function(response) { return response.json(); })
				.then(function(data) {
					if (data && data.success) {
						// Reload the server-authoritative state for the new difficulty.
						loadGame();
					} else {
						// Silent fail: apply locally so the player is not stuck.
						performLocalDifficultyReset(newDifficulty);
					}
				})
				.catch(function() {
					performLocalDifficultyReset(newDifficulty);
				});
		} else {
			performLocalDifficultyReset(newDifficulty);
		}
	}

	/**
	 * Apply a difficulty change locally: preserve prestige + best scores, reset
	 * the run, restart movement at the new speed, then persist and refresh UI.
	 *
	 * @param {string} newDifficulty One of 'easy' | 'medium' | 'hard'.
	 * @return {void}
	 */
	function performLocalDifficultyReset(newDifficulty) {
		performRunReset();
		gameState.difficulty = newDifficulty;
		startMovementTimer(); // restart at the new difficulty speed
		saveGame();
		updateUI();
		syncDifficultySelector();
	}

	/**
	 * Apply all admin gameplay settings to the rendered game.
	 *
	 * @return {void}
	 */
	function setupGameplayFeatures() {
		renderButtonSlots();
		renderClickHint();
		startMovementTimer();
		setupSelfReset();
		setupDifficultySelector();
	}

	/**
	 * Initialize game when DOM is ready
	 */
	async function initGame() {
		// Apply custom branding theme
		applyBrandingTheme();

		// Load game state (sets difficulty before building difficulty-aware UI).
		await loadGame();

		// Apply admin gameplay settings (anti-bot buttons, movement, self-reset, difficulty).
		setupGameplayFeatures();

		// Calculate offline progress
		calculateOfflineProgress();

		// Reflect miner active/stopped state from the loaded save.
		updateMinerStatusUI();

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

		// Prevent double-tap zoom on the game container (mobile).
		// touch-action: manipulation on buttons handles most cases;
		// this catches taps on the container background.
		var sacigContainer = document.querySelector('.sacig-container');
		if (sacigContainer) {
			var lastTapTime = 0;
			var lastTapX = null;
			var lastTapY = null;

			sacigContainer.addEventListener('touchend', function(e) {
				var target = e.target && e.target.nodeType === 1
					? e.target : e.target && e.target.parentElement;

				// Skip mine buttons — touch-action:manipulation handles them.
				if (target && target.closest(
					'button, a, input, select, .sacig-mine-button, ' +
					'.sacig-upgrade-item, [role="button"]'
				)) { return; }

				var now   = Date.now();
				var diff  = now - lastTapTime;
				var touch = e.changedTouches && e.changedTouches[0];
				var tapX  = touch ? touch.clientX : null;
				var tapY  = touch ? touch.clientY : null;
				var sameSpot = (
					tapX !== null && tapY !== null &&
					lastTapX !== null && lastTapY !== null &&
					Math.abs(tapX - lastTapX) <= 25 &&
					Math.abs(tapY - lastTapY) <= 25
				);

				if (diff < 300 && diff > 0 && sameSpot) {
					e.preventDefault();
				}

				lastTapTime = now;
				lastTapX = tapX;
				lastTapY = tapY;
			}, { passive: false });
			// passive:false required to allow preventDefault().
			// Scoped to game container only — not the whole page.
		}
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initGame);
	} else {
		initGame();
	}
})();
