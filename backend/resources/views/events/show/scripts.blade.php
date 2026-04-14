
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
				loadParticipants();
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
				
				// Группируем по group_key для отображения пар
				const groupOrder = [];
				const groupMap = {};
				const rendered = new Set();

				data.forEach((p) => {
					if (p.group_key) {
						if (!groupMap[p.group_key]) {
							groupMap[p.group_key] = [];
							groupOrder.push({ type: 'group', key: p.group_key });
						}
						groupMap[p.group_key].push(p);
					} else {
						groupOrder.push({ type: 'solo', player: p });
					}
				});

				let displayIndex = 1;

				groupOrder.forEach((item) => {
					if (item.type === 'solo') {
						const p = item.player;
						const el = document.createElement('div');
						el.style.cssText = 'display:flex;align-items:center;gap:0.75rem;margin-bottom:0.5rem;';
						el.innerHTML = `
							<div class="f-13" style="width:20px;text-align:right;color:#aaa">${displayIndex++}.</div>
							<a href="${p.url || '/user/'+p.id}"><img src="${p.avatar || 'https://ui-avatars.com/api/?name=Player'}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;"></a>
							<div class="f-16" style="flex:1"><a href="${p.url || '/user/'+p.id}" class="blink">${levelIcon(p.level)} ${p.name}</a></div>
							<div class="f-13 text-muted">${p.position && p.position !== 'player' ? positionLabel(p.position) : ''}</div>
						`;
						playersList.appendChild(el);
					} else {
						const members = groupMap[item.key];
						members.forEach((p, mi) => {
							const el = document.createElement('div');
							el.style.cssText = 'display:flex;align-items:center;gap:0.75rem;margin-bottom:0.5rem;';
							const num = displayIndex++;
							const prefix = mi === 0
								? `<div class="f-13" style="width:20px;text-align:right;color:#aaa">${num}.</div>`
								: `<div class="f-13" style="width:20px;text-align:right;color:#aaa;padding-left:16px">╰ ${num}.</div>`;
							el.innerHTML = `
						${prefix}
						<a href="${p.url || '/user/'+p.id}"><img src="${p.avatar || 'https://ui-avatars.com/api/?name=Player'}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;${mi > 0 ? 'margin-left:4px;' : ''}"></a>
						<div class="f-16" style="flex:1"><a href="${p.url || '/user/'+p.id}" class="blink">${levelIcon(p.level)} ${p.name}</a></div>
						<div class="f-13 text-muted">${p.position && p.position !== 'player' ? positionLabel(p.position) : ''}</div>
					`;
							playersList.appendChild(el);
						});
					}
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
				middle: 'ЦБ',
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
				if (data.registered_total !== undefined && progress && maxPlayers > 0) {
					const percent = Math.min(100, (data.registered_total / maxPlayers) * 100);
					progress.style.transition = 'width 0.4s ease';
					progress.style.width = percent + '%';
					progress.classList.remove('bg-danger', 'bg-warning', 'bg-success');
					if (percent >= 75) progress.classList.add('bg-success');
					else if (percent >= 40) progress.classList.add('bg-warning');
					else progress.classList.add('bg-danger');
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

/*
|--------------------------------------------------------------------------
| AJAX JOIN — перехват форм записи на позицию
|--------------------------------------------------------------------------
*/
function initJoinForms() {
    const joinBlock = document.getElementById('join-registration-block');
    if (!joinBlock) return;

    joinBlock.addEventListener('submit', async function(e) {
        const form = e.target;
        if (!form.matches('form[data-ajax-join]')) return;
        e.preventDefault();

        const btn = form.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = '⏳...'; }

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
            });

            const data = await res.json();

            if (!data.ok) {
                showJoinError(data.message || 'Ошибка записи');
                if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label || 'Записаться'; }
                return;
            }

            // Обновляем блок записи
            updateJoinBlock(data);

            // Обновляем список участников
            loadParticipants();

        } catch (err) {
            console.error('Join error', err);
            showJoinError('Ошибка соединения');
            if (btn) { btn.disabled = false; }
        }
    });
}

function updateJoinBlock(data) {
    const block = document.getElementById('join-registration-block');
    if (!block) return;

    // Строим новый HTML блока записи
    let html = '';

    // Статус записи
    html += '<div class="alert alert-success">Вы уже записаны</div>';

    // Блок оплаты
    if (data.payment_status === 'link_pending') {
        html += `<div class="alert alert-warning mt-2">⏳ Ожидаем оплату — <strong>${data.amount} ₽</strong></div>`;
        if (data.payment_link) {
            html += `<a href="${data.payment_link}" target="_blank" class="btn w-100 mt-1">💳 Перейти к оплате</a>`;
        }
        if (data.payment_id) {
            html += `<form method="POST" action="/payments/${data.payment_id}/user-confirm">
                <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.content}">
                <button type="submit" class="btn btn-secondary w-100 mt-1">✅ Я оплатил</button>
            </form>`;
        }
    } else if (data.payment_status === 'yoomoney_pending') {
        html += `<div class="alert alert-warning mt-2">⏳ Место зарезервировано до ${data.payment_expires_at}</div>`;
        if (data.yoomoney_url) {
            html += `<a href="${data.yoomoney_url}" target="_blank" class="btn w-100 mt-1">🟡 Оплатить через ЮМани</a>`;
        }
    } else if (data.payment_status === 'paid') {
        html += `<div class="alert alert-success mt-2">✅ Оплачено — ${data.amount} ₽</div>`;
    }

    // Сообщение
    if (data.message) {
        html += `<div class="alert alert-info mt-2">${data.message}</div>`;
    }

    // Кнопка отмены (оставляем как есть — страница обновится при отмене)
    const cancelForm = block.querySelector('form[data-cancel-form]');
    if (cancelForm) {
        html += cancelForm.outerHTML;
    }

    block.innerHTML = html;

    // Показываем блок приглашений
    const inviteBlock = document.getElementById('invite-players-block');
    if (inviteBlock) inviteBlock.style.display = '';

    // Обновляем статус-бар
    updatePlayers();
}

function showJoinError(msg) {
    const block = document.getElementById('join-registration-block');
    if (!block) return;
    const existing = block.querySelector('.alert-danger');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.className = 'alert alert-danger mt-2';
    div.textContent = msg;
    block.prepend(div);
}
         /*
	    | INIT
		|--------------------------------------------------------------------------
	    */
		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", function() {
				if (hasOccurrence) { loadParticipants(); updatePlayers(); }
				initTournamentUx(); initJoinForms();
			});
		} else {
			if (hasOccurrence) { loadParticipants(); updatePlayers(); }
			initTournamentUx(); initJoinForms();
		}
	})();
</script>

