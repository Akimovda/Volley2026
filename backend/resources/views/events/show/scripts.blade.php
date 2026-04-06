
<script src="/assets/fas.js"></script>    

<script src="/js/pusher.min.js"></script>
<script src="/js/echo.iife.js"></script>
<script>
	
	// Инициализация Swiper для галереи

	document.addEventListener('DOMContentLoaded', function() {	
		const swiper = new Swiper('.event-swiper', {
			slidesPerView: 1,
			spaceBetween: 12,
			pagination: {
				el: '.swiper-pagination',
				clickable: true,
			},
			breakpoints: {
				768: {
					slidesPerView: 1, 
					spaceBetween: 12
				}                    
			}
		});
	});
 
	
	(function () {
		const occurrenceId = @json($occurrence->id ?? null);
		const playersCount = document.getElementById('players-count');
		const playersList = document.getElementById('players-list');
		const progress = document.getElementById('players-progress');
		const maxPlayers = @json($event->gameSettings?->max_players ?? 0);
		const hasOccurrence = occurrenceId !== null && occurrenceId !== undefined;
		
		/*
			|--------------------------------------------------------------------------
			| WEBSOCKET
			|--------------------------------------------------------------------------
		*/
		if (hasOccurrence) {
			try {
				window.Echo = new Echo({
					broadcaster: 'reverb',
					key: 'local',
					wsHost: window.location.hostname,
					wsPort: 80,
					wssPort: 443,
					forceTLS: window.location.protocol === 'https:',
					enabledTransports: ['ws', 'wss']
				});
				
				const channel = Echo.channel('occurrence.' + occurrenceId);
				
				/*
					|--------------------------------------------------------------------------
					| LIVE STATS UPDATE
					|--------------------------------------------------------------------------
				*/
				channel.listen('.stats.updated', (e) => {
					if (playersCount) {
						playersCount.textContent = e.registeredTotal;
					}
					
					if (progress && maxPlayers) {
						const percent = Math.min(
						100,
						(e.registeredTotal / maxPlayers) * 100
						);
						
						progress.style.transition = 'width 0.4s ease';
						progress.style.width = percent + '%';
						progress.classList.remove(
						'bg-danger',
						'bg-warning',
						'bg-success'
						);
						
						if (percent >= 75) {
							progress.classList.add('bg-success');
							} else if (percent >= 40) {
							progress.classList.add('bg-warning');
							} else {
							progress.classList.add('bg-danger');
						}
					}
					
					loadParticipants();
				});
				
				/*
					|--------------------------------------------------------------------------
					| PLAYER JOIN EVENT
					|--------------------------------------------------------------------------
				*/
				channel.listen('.player.joined', (e) => {
					showJoinMessage(
					e.player_name,
					e.position
					);
					
					if (playersCount) {
						const current = parseInt(playersCount.textContent || '0', 10);
						playersCount.textContent = current + 1;
					}
				});
				} catch (err) {
				console.warn('WebSocket disabled', err);
			}
		}
		
		/*
			|--------------------------------------------------------------------------
			| LOAD PARTICIPANTS
			|--------------------------------------------------------------------------
		*/
		async function loadParticipants() {
			if (!hasOccurrence || !playersList) {
				return;
			}
			
			try {
				const res = await fetch(`/api/occurrences/${occurrenceId}/participants`);
				const data = await res.json();
				
				playersList.innerHTML = '';
				
				if (data.length === 0) {
					playersList.innerHTML =
					'<div>Пока никто не записался</div>';
					return;
				}
				
				data.forEach((p, i) => {
					const el = document.createElement('div');
					el.style.display = 'flex';
					el.style.alignItems = 'center';
					el.style.gap = '1rem';
					el.style.marginBottom = '0.6rem';
					
					const level = levelIcon(p.level);
					
					el.innerHTML = `
					<div class="f-13" style="width:24px; text-align:right;">
					${i + 1}.
					</div>
					<img
					src="${p.avatar || 'https://ui-avatars.com/api/?name=Player'}"
					style="
					width:36px;
					height:36px;
					border-radius:50%;
					object-fit:cover;
					"
					>
					<div class="f-16" style="flex:1">
					${level} ${p.name}
					</div>
					<div class="f-13">
					${p.position && p.position !== 'player' ? positionLabel(p.position) : ''}
					</div>
					`;
					
					playersList.appendChild(el);
				});
				} catch (e) {
				console.error('participants load error', e);
			}
		}
		/*
			|--------------------------------------------------------------------------
			| HELPERS
			|--------------------------------------------------------------------------
		*/
		function levelIcon(level) {
			const map = {
				1: '⚪️',
				2: '🟡',
				3: '🟠',
				4: '🔵',
				5: '🟣',
				6: '🔴',
				7: '⚫️'
			};
			
			return map[level] ?? '⚪';
		}
		
		function positionLabel(pos) {
			const map = {
				setter: 'Связующий',
				outside: 'Доигровщик',
				middle: 'Центральный блокирующий',
				opposite: 'Диагональный',
				libero: 'Либеро',
				player: ''
			};
			
			return map[pos] ?? pos;
		}
		
		function showJoinMessage(name, position) {
			const el = document.createElement('div');
			el.style.position = 'fixed';
			el.style.bottom = '20px';
			el.style.right = '20px';
			el.style.background = '#111';
			el.style.color = '#fff';
			el.style.padding = '10px 14px';
			el.style.borderRadius = '8px';
			el.style.zIndex = '9999';
			el.style.boxShadow = '0 4px 20px rgba(0,0,0,0.25)';
			el.style.fontSize = '14px';
			el.innerText = `🏐 ${name} занял позицию ${positionLabel(position)}`;
			document.body.appendChild(el);
			
			setTimeout(() => {
				el.remove();
			}, 4000);
		}
		
		/*
			|--------------------------------------------------------------------------
			| FALLBACK POLLING
			|--------------------------------------------------------------------------
		*/
		async function updatePlayers() {
			if (!hasOccurrence) {
				return;
			}
			
			try {
				const res = await fetch(`/api/occurrences/${occurrenceId}/stats`, {
					headers: { 'X-Requested-With': 'XMLHttpRequest' }
				});
				const data = await res.json();
				
				if (data.registered_total !== undefined && playersCount) {
					playersCount.textContent = data.registered_total;
				}
				} catch (e) {
				console.error('stats update error', e);
			}
		}
		
		if (hasOccurrence) {
			setInterval(updatePlayers, 10000);
		}
		
		/*
			|--------------------------------------------------------------------------
			| TOURNAMENT UX
			|--------------------------------------------------------------------------
		*/
		function initTournamentUx() {
			const copyButtons = document.querySelectorAll('[data-copy-invite-code]');
			copyButtons.forEach((btn) => {
				btn.addEventListener('click', async function () {
					const code = btn.getAttribute('data-copy-invite-code') || '';
					
					if (!code) {
						return;
					}
					
					try {
						await navigator.clipboard.writeText(code);
						
						const original = btn.textContent;
						btn.textContent = 'Скопировано';
						
						setTimeout(() => {
							btn.textContent = original;
						}, 1500);
						} catch (e) {
						console.warn('copy failed', e);
					}
				});
			});
			
			const destructiveForms = document.querySelectorAll('[data-confirm-remove-member]');
			destructiveForms.forEach((form) => {
				form.addEventListener('submit', function (e) {
					const ok = window.confirm('Удалить игрока из команды?');
					if (!ok) {
						e.preventDefault();
					}
				});
			});
			
			const submitForms = document.querySelectorAll('[data-confirm-submit-team]');
			submitForms.forEach((form) => {
				form.addEventListener('submit', function (e) {
					const ok = window.confirm('Подать заявку команды на турнир?');
					if (!ok) {
						e.preventDefault();
					}
				});
			});
			
		}
		
		/*
			|--------------------------------------------------------------------------
			| INIT
			|--------------------------------------------------------------------------
		*/
		document.addEventListener('DOMContentLoaded', function () {
			if (hasOccurrence) {
				loadParticipants();
				updatePlayers();
			}
			
			initTournamentUx();
		});
	})();
</script>

