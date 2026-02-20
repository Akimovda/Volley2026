$(function () {
    $('.anima').each(function() {
        // Получаем текст (без тегов)
		$(this).css('opacity','1');
        var text = $(this).text();       
        // Разбиваем на слова
        var words = text.split(/\s+/).filter(function(word) {
            return word.length > 0;
		});        
        // Оборачиваем каждое слово в span
        var wrapped = '';
        words.forEach(function(word, index) {
            wrapped += '<span ' +
			'data-aos="fade-down" ' +
			'data-aos-delay="' + (index * 100) + '" ' +
			'data-aos-duration="400">' + 
			word + '</span> ';
		});       
        // Заменяем содержимое
        $(this).html(wrapped);
	});
	
	
	initIcons();
	/* =========================
		КЭШ ЭЛЕМЕНТОВ
	========================= */
	const $header     = $('.fix-header');
	const $userBtn    = $('.fix-header-btn-user, .fix-header-user');
	const $hammBtn    = $('.fix-header-btn-hamm');
	
	const $userMenu   = $('.fix-header-menu-2');
	const $hammMenu   = $('.fix-header-menu-3');
	
	const allMenus    = $('.fix-header-menu');
	const allButtons  = $('.fix-header-btn-user, .fix-header-btn-hamm, .fix-header-user');
	
	
	/* =========================
		BREAKPOINT
	========================= */
	function isDesktop() {
		return window.matchMedia('(min-width: 768px)').matches;
	}
	
	let isDesktopState = isDesktop();
	
	
	/* =========================
		СБРОС СОСТОЯНИЯ
	========================= */
	function resetMenus() {
		// Убираем inline-стили от jQuery
		allMenus.removeAttr('style');
		
		// Убираем классы состояний
		allMenus.removeClass('open');
		allButtons.removeClass('active');
	}
	
	
	/* =========================
		ЗАКРЫТИЕ ВСЕХ МЕНЮ
	========================= */
	function closeAll() {
		allMenus.removeClass('open');
		if (!isDesktop()) {
			allMenus
			.stop(true, true)
			.slideUp(300, function () {
				// чистим display после анимации
				$(this).css('display', '');
			});
		}
		allButtons.removeClass('active');
	}
	
	
	/* =========================
		ОТКРЫТИЕ МЕНЮ
	========================= */
	function openMenu($button, $menu) {
		
		// если это меню уже активно — закрываем всё
		if ($button.hasClass('active')) {
			closeAll();
			return;
		}
		
		closeAll();
		$button.addClass('active');
		$menu.addClass('open');
		if (!isDesktop()) {
			$menu
			.stop(true, true)
			.slideDown(300);
		}	
	}
	
	
	/* =========================
		ОБРАБОТЧИКИ КНОПОК
	========================= */
	$userBtn.on('click', function (e) {
		e.stopPropagation();
		openMenu($userBtn, $userMenu);
	});
	
	$hammBtn.on('click', function (e) {
		e.stopPropagation();
		openMenu($hammBtn, $hammMenu);
	});
	
	
	/* =========================
		КЛИК ВНЕ ШАПКИ
	========================= */
	$(document).on('click', function (e) {
		if (!$(e.target).closest('.fix-header').length) {
			closeAll();
		}
	});
	
	
	/* =========================
		КЛИК ВНУТРИ МЕНЮ
	========================= */
	allMenus.on('click', function (e) {
		e.stopPropagation();
	});
	
	
	/* =========================
		ESC — ЗАКРЫТЬ
	========================= */
	$(document).on('keyup', function (e) {
		if (e.key === 'Escape') {
			closeAll();
		}
	});
	
	
	/* =========================
		КЛИК ПО ССЫЛКЕ В МЕНЮ
	========================= */
	/*
		$('.fix-header-menu a').on('click', function () {
		closeAll();
		});
	*/
	
	/* =========================
		RESIZE (КЛЮЧЕВОЙ МОМЕНТ)
	========================= */
	$(window).on('resize', function () {
		const nowDesktop = isDesktop();
		
		// сбрасываем ТОЛЬКО при смене режима
		if (nowDesktop !== isDesktopState) {
			resetMenus();
			isDesktopState = nowDesktop;
		}
	});
	
	function margin() {	
		// Проверяем видимую ширину окна (уже включает скролл)
		if (window.innerWidth >= 992) {
			$("main").css({'margin-bottom': $("footer").height()});
			} else {
			$("main").css({'margin-bottom': 0});
		}
	}				
	margin();
	
	$(window).on('resize', function() {
		setTimeout(margin, 50); // Минимальная задержка
	});
	
	$('[data-href]').on('click', function() {
		const url = $(this).data('href');
		if (url) {
			window.location.href = url;
		}
	});
	
	$('.fix-header-btn-theme').click(function(e) {
		e.preventDefault();
		
		// 1. Добавляем класс, отключающий ВСЕ анимации
		$('body').addClass('no-transitions');
		
		// 2. Переключаем тему
		$('body').toggleClass('dark');
		
		// 3. Сохраняем в localStorage
		const isDark = $('body').hasClass('dark');
		if (isDark) {
			localStorage.setItem('theme', 'dark');
			} else {
			localStorage.removeItem('theme');
		}
		
		// 4. Через минимальную задержку убираем класс (после перерисовки)
		setTimeout(() => {
			$('body').removeClass('no-transitions');
		}, 50); // 50ms обычно достаточно
		
		// 5. Эффект нажатия на кнопку (опционально)
		$(this).css('transform', 'scale(0.95)');
		setTimeout(() => {
			$(this).css('transform', '');
		}, 100);
	});
	
	// Добавляем эффект нажатия для ВСЕХ кнопок в header
	$('.fix-header-btn-user, .fix-header-btn-hamm, .fix-header-btn-theme').on('click', function() {
		const $btn = $(this);
		$btn.css({
			'transform': 'scale(0.95)',
			'transition': 'transform 0.1s ease'
		});
		
		setTimeout(() => {
			$btn.css({
				'transform': '',
				'transition': ''
			});
		}, 100);
	});	
	
	// paralax
    const $image = $('.top-section-img img');
    const $container = $('.top-section-img');
    
	
    
    // Функция обновления
	function updateSmoothParallax() {
		const containerHeight = $container.outerHeight();
		const windowHeight = $(window).height();	
		const scrollTop = $(window).scrollTop();
		const windowBottom = scrollTop + windowHeight;
		const containerBottom = $container.offset().top + containerHeight;
		
		// Если картинка еще в зоне видимости
		if (windowBottom > $container.offset().top) {
			// Вычисляем прогресс скролла относительно картинки (0-1)
			const progress = Math.min(scrollTop / containerHeight, 1);
			
			// Параллакс эффект (движение вниз)
			const parallaxValue = progress * containerHeight * 0.38;
			
			// Эффект scale (увеличение)
			const scaleValue = 1 + progress * -0.15; // Увеличиваем до 110%
			
			
			// Применяем все сразу
			$image.css({
				'transform': `translateY(${parallaxValue}px) scale(${scaleValue})`
			});
		}
	}
    
    // Оптимизация скролла
    let ticking = false;
    $(window).on('scroll', function() {
        if (!ticking) {
            requestAnimationFrame(function() {
                updateSmoothParallax();
                ticking = false;
			});
            ticking = true;
		}
	});
    
    // При ресайзе
    $(window).on('resize', function() {
        requestAnimationFrame(updateSmoothParallax);
	});
    
    // Инициализация
    updateSmoothParallax();
	
	
	
	// 1. Сначала объявляем функцию для таблиц
	function initTableScroll() {
		$('.table-scrollable').each(function() {
			var $tableWrapper = $(this);
			
			// Проверяем, нужен ли скролл вообще
			function checkIfScrollable() {
				// Если элемент не виден - пропускаем
				if (!$tableWrapper.is(':visible')) {
					$tableWrapper.removeClass('scrollable');
					return false;
				}
				var isScrollable = $tableWrapper[0].scrollWidth > $tableWrapper[0].clientWidth;
				$tableWrapper.toggleClass('scrollable', isScrollable);
				return isScrollable;
			}
			
			var isDragging = false;
			var startX, scrollLeft;
			var velocity = 0;
			var lastX = 0;
			var animationId;
			var isHorizontalScroll = false;
			
			// Универсальное получение координаты X
			function getPageX(e) {
				if (e.pageX !== undefined) return e.pageX;
				if (e.originalEvent && e.originalEvent.touches) {
					return e.originalEvent.touches[0].pageX;
				}
				return 0;
			}
			
			// Drag-скролл мышью
			function startDrag(e) {
				$tableWrapper.addClass('hide-indicator');
				if (!checkIfScrollable()) return;
				
				isDragging = true;
				$tableWrapper.addClass('dragging');
				startX = getPageX(e);
				scrollLeft = $tableWrapper.scrollLeft();
				lastX = startX;
				velocity = 0;
				isHorizontalScroll = false;
			}
			
			function doDrag(e) {
				if (!isDragging) return;
				
				var x = getPageX(e);
				if (!x) return;
				
				var deltaX = startX - x;
				
				if (!isHorizontalScroll) {
					var deltaY;
					if (e.movementY !== undefined) {
						deltaY = Math.abs(e.movementY);
						} else if (e.originalEvent && e.originalEvent.touches) {
						var touch = e.originalEvent.touches[0];
						deltaY = Math.abs(touch.pageY - startX);
						} else {
						deltaY = 0;
					}
					
					if (Math.abs(deltaX) > Math.abs(deltaY) * 1.5) {
						isHorizontalScroll = true;
						e.preventDefault();
					}
				}
				
				if (isHorizontalScroll) {
					var walk = deltaX * 1.5;
					$tableWrapper.scrollLeft(scrollLeft + walk);
					
					velocity = (x - lastX) * 16;
					lastX = x;
				}
			}
			
			function endDrag() {
				if (!isDragging) return;
				
				isDragging = false;
				$tableWrapper.removeClass('dragging');
				
				if (isHorizontalScroll && Math.abs(velocity) > 1) {
					cancelAnimationFrame(animationId);
					
					var inertiaScroll = function() {
						velocity *= 0.92;
						
						if (Math.abs(velocity) > 0.5) {
							$tableWrapper.scrollLeft($tableWrapper.scrollLeft() - velocity);
							animationId = requestAnimationFrame(inertiaScroll);
							} else {
							cancelAnimationFrame(animationId);
						}
					};
					
					animationId = requestAnimationFrame(inertiaScroll);
				}
			}
			
			// События для мыши
			$tableWrapper.on('mousedown', startDrag);
			$(document).on('mousemove', doDrag);
			$(document).on('mouseup', function() {
				endDrag();
				$(document).off('mousemove', doDrag);
			});
			
			$tableWrapper.on('mousedown', function() {
				$(document).on('mousemove', doDrag);
			});
			
			// События для touch-устройств
			$tableWrapper.on('touchstart', function(e) {
				if (e.originalEvent.touches.length === 1) {
					startDrag(e);
				}
			});
			
			$tableWrapper.on('touchmove', function(e) {
				if (!isDragging || e.originalEvent.touches.length !== 1) return;
				
				var touch = e.originalEvent.touches[0];
				var deltaX = startX - touch.pageX;
				
				if (!isHorizontalScroll) {
					var deltaY = Math.abs(touch.pageY - startX);
					
					if (Math.abs(deltaX) > Math.abs(deltaY) * 1.5) {
						isHorizontalScroll = true;
						e.preventDefault();
					}
				}
				
				if (isHorizontalScroll) {
					var walk = deltaX * 1.5;
					$tableWrapper.scrollLeft(scrollLeft + walk);
					
					velocity = (touch.pageX - lastX) * 16;
					lastX = touch.pageX;
					
					if (isHorizontalScroll) {
						e.preventDefault();
					}
				}
			});
			
			$tableWrapper.on('touchend', function() {
				endDrag();
			});
			
			// Отмена контекстного меню при drag
			$tableWrapper.on('contextmenu', function(e) {
				if (isDragging) {
					e.preventDefault();
					return false;
				}
			});
			
			// Инициализация
			setTimeout(function() {
				checkIfScrollable();
			}, 100);
			
			// Обновляем при изменении размера окна
			$(window).on('resize', function() {
				checkIfScrollable();
			});
			
			// Отменяем drag если клик был на ссылке или кнопке внутри таблицы
			$tableWrapper.on('mousedown touchstart', 'a, button, .clickable', function(e) {
				e.stopPropagation();
			});
		});
	}
	// Таблицы
	initTableScroll();
	
	// Дополнительно при ресайзе
	$(window).on('resize', function() {
		setTimeout(initTableScroll, 100);
	});	
	
// Глобальная функция для обновления подсветки
window.updateAllTabHighlights = function() {
	$('.tabs-content').each(function() {
		var $container = $(this);
		// Ищем .tabs ТОЛЬКО внутри этого контейнера
		var $tabsContainer = $container.find('.tabs');
		
		// Если нет .tabs, возможно табы лежат просто в родителе (как в твоем случае)
		if ($tabsContainer.length === 0) {
			// Ищем родителя табов (первый общий контейнер для всех .tab)
			var $firstTab = $container.find('.tab').first();
			if ($firstTab.length) {
				$tabsContainer = $firstTab.parent();
			}
		}
		
		if (!$tabsContainer || !$tabsContainer.length) return;
		
		var $highlight = $tabsContainer.find('.tab-highlight');
		var $activeTab = $tabsContainer.find('.tab.active');
		
		if ($activeTab.length) {
			var tabPosition = $activeTab.position();
			var tabWidth = $activeTab.outerWidth();
			var tabHeight = $activeTab.outerHeight();
			
			if (!$highlight.length) {
				$highlight = $('<div class="tab-highlight"></div>');
				$tabsContainer.append($highlight);
			}
			
			$highlight.css({
				width: tabWidth + 'px',
				height: tabHeight + 'px',
				transform: 'translate(' + tabPosition.left + 'px, ' + tabPosition.top + 'px)',
				opacity: 1
			});
		}
	});
};

// Функция для инициализации табов (почти без изменений)
function initTabSet($container) {
	// Ищем ТОЛЬКО непосредственных детей для табов и панелей
	// Это защищает от вложенных табов
	var $tabs = $container.children('.tabs').children('.tab');
	var $panes = $container.children('.tab-panes').children('.tab-pane');
	
	// Если нет структуры .tbs/.tab-panes, ищем глубже (для твоего случая)
	if ($tabs.length === 0) {
		$tabs = $container.find('.tab');
		// Фильтруем, чтобы не захватывать вложенные табы
		$tabs = $tabs.filter(function() {
			// Проверяем, что таб находится в том же контейнере, что и панели
			return $(this).closest('.tabs-content').is($container);
		});
	}
	
	if ($panes.length === 0) {
		$panes = $container.find('.tab-pane');
		$panes = $panes.filter(function() {
			return $(this).closest('.tabs-content').is($container);
		});
	}
	
	if ($tabs.length > 0 && $tabs.filter('.active').length === 0) {
		var $firstTab = $tabs.first();
		var firstTabId = $firstTab.data('tab');
		
		$firstTab.addClass('active');
		$panes.filter('#' + firstTabId).addClass('active');
	}
	
	$tabs.off('click').on('click', function() {
		var tabId = $(this).data('tab');
		
		$tabs.removeClass('active');
		$(this).addClass('active');
		
		$panes.removeClass('active');
		$panes.filter('#' + tabId).addClass('active');
		
		window.updateAllTabHighlights();
		
		setTimeout(initTableScroll, 100);
	});
}
	
    // Инициализация табов
    $('.tabs-content').each(function() {
        initTabSet($(this));
	});
    
    // Первичная инициализация подсветки
    window.updateAllTabHighlights();
    
    // Обновление при ресайзе
    var resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(window.updateAllTabHighlights, 250);
	});
	
	
	
	
// Функция для создания кастомного селекта
function createCustomSelect($select) {
    // Создаём обёртку
    const $wrapper = $('<div>', {
        class: 'form-select-wrapper',
        'data-select-id': $select.attr('id') || 'select-' + Math.random().toString(36).substr(2, 9)
    });
    
    // Создаём кастомный элемент
    const $customSelect = $('<div>', {
        class: 'form-select-custom' + ($select.is(':disabled') ? ' form-select-custom--disabled' : '')
    });
    
    // Создаём отображаемое значение
    const $valueSpan = $('<span>', {
        class: 'form-select-value'
    });
    
    // Стрелка (SVG)
    const $arrow = $('<span>', {
        class: 'form-select-arrow',
        html: `<svg viewBox="0 0 24 24">
            <polyline points="6 9 12 15 18 9"></polyline>
        </svg>`
    });
    
    // Добавляем в кастомный селект
    $customSelect.append($valueSpan, $arrow);
    
    // Создаём выпадающий список
    const $dropdown = $('<div>', {
        class: 'form-select-dropdown'
    });
    
    // Собираем опции из оригинального селекта
    $select.find('option').each(function() {
        const $option = $(this);
        const $customOption = $('<div>', {
            class: 'form-select-option' + 
                ($option.is(':selected') ? ' form-select-option--selected' : '') +
                ($option.is(':disabled') ? ' form-select-option--disabled' : ''),
            'data-value': $option.val(),
            html: $option.text()
        });
        
        // Обработчик клика на опцию
        $customOption.on('click', function(e) {
            if ($option.is(':disabled') || $select.is(':disabled')) return;
            
            e.stopPropagation();
            
            // Обновляем значение в оригинальном селекте
            $select.val($option.val()).trigger('change');
            
            // Обновляем отображаемое значение
            updateCustomSelect($select, $wrapper);
            
            // Закрываем dropdown
            $customSelect.removeClass('form-select-custom--active');
            $dropdown.removeClass('form-select-dropdown--active');
        });
        
        $dropdown.append($customOption);
    });
    
    // Собираем всё вместе
    $wrapper.append($customSelect, $dropdown);
    
    // Вставляем перед оригинальным селектом
    $select.before($wrapper);
    
    // Обновляем отображение
    updateCustomSelect($select, $wrapper);
    
    // Обработчики событий для кастомного селекта
    $customSelect.on('click', function(e) {
        if ($select.is(':disabled')) return;
        
        e.stopPropagation();
        
        // Закрываем другие открытые селекты
        $('.form-select-custom--active').not($customSelect).removeClass('form-select-custom--active');
        $('.form-select-dropdown--active').not($dropdown).removeClass('form-select-dropdown--active');
        
        // Переключаем состояние текущего
        $customSelect.toggleClass('form-select-custom--active');
        $dropdown.toggleClass('form-select-dropdown--active');
    });
    
    // Закрытие при клике вне
    $(document).on('click', function(e) {
        if (!$wrapper.is(e.target) && $wrapper.has(e.target).length === 0) {
            $customSelect.removeClass('form-select-custom--active');
            $dropdown.removeClass('form-select-dropdown--active');
        }
    });
    
    // Слушаем изменения оригинального селекта
    $select.on('change', function() {
        updateCustomSelect($(this), $wrapper);
    });
    
    return $wrapper;
}

// Функция обновления отображаемого значения
function updateCustomSelect($select, $wrapper) {
    const $customSelect = $wrapper.find('.form-select-custom');
    const $valueSpan = $wrapper.find('.form-select-value');
    const $dropdown = $wrapper.find('.form-select-dropdown');
    
    const selectedValue = $select.val();
    const selectedText = $select.find('option:selected').text();
    
    // Обновляем отображаемое значение
    $valueSpan.text(selectedText);
    if (!selectedValue && $select.find('option[value=""]').length) {
        $valueSpan.addClass('form-select-placeholder');
    } else {
        $valueSpan.removeClass('form-select-placeholder');
    }
    
    // Обновляем выбранную опцию в dropdown
    $dropdown.find('.form-select-option').removeClass('form-select-option--selected');
    $dropdown.find(`.form-select-option[data-value="${selectedValue}"]`).addClass('form-select-option--selected');
}

// Инициализация всех селектов в формах
function initCustomSelects() {
    $('.form select').each(function() {
        const $select = $(this);
        
        // Проверяем, не инициализирован ли уже этот селект
        if ($select.data('custom-initialized')) return;
        
        // Создаём кастомный селект
        createCustomSelect($select);
        
        // Помечаем как инициализированный
        $select.data('custom-initialized', true);
    });
}

// Инициализация при загрузке
$(document).ready(function() {
    initCustomSelects();
});

// Инициализация динамически добавленных селектов
$(document).on('DOMNodeInserted', '.form select', function() {
    if (!$(this).data('custom-initialized')) {
        initCustomSelects();
    }
});

// Публичные методы для использования извне
window.customSelect = {
    update: function(selectId) {
        const $select = $(`#${selectId}`);
        const $wrapper = $select.prev('.form-select-wrapper');
        if ($wrapper.length) {
            updateCustomSelect($select, $wrapper);
        }
    },
    
    destroy: function(selectId) {
        const $select = $(`#${selectId}`);
        const $wrapper = $select.prev('.form-select-wrapper');
        
        if ($wrapper.length) {
            $wrapper.remove();
            $select.removeData('custom-initialized');
        }
    },
    
    disable: function(selectId, disabled) {
        const $select = $(`#${selectId}`);
        const $wrapper = $select.prev('.form-select-wrapper');
        
        if ($wrapper.length) {
            const $customSelect = $wrapper.find('.form-select-custom');
            if (disabled) {
                $customSelect.addClass('form-select-custom--disabled');
            } else {
                $customSelect.removeClass('form-select-custom--disabled');
            }
        }
    }
};
	
	
	
	// Отслеживаем движение мыши внутри .ramka и .card-ramka
	$('.ramka, .card-ramka').on('mousemove', function(e) {
        
        // Координаты относительно текущего элемента
        const rect = this.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        // Устанавливаем CSS-переменные для этого элемента
        this.style.setProperty('--mouse-x', `${x}px`);
        this.style.setProperty('--mouse-y', `${y}px`);
	});
    
    // Опционально: убираем свечение, когда мышь уходит с родителя
    $('.ramka, .card-ramka').on('mouseleave', function() {
        // Можно оставить как есть, а можно убрать переменные
        // this.style.removeProperty('--mouse-x');
        // this.style.removeProperty('--mouse-y');
        // Оставляем как есть — свечение гаснет через transition
	});	
	
	$('.ufilter-btn').on('click', function() {
		$('.users-filter').toggleClass('open');
		$('.top-section-img').toggleClass('mhide');
	});
	
	
});

document.addEventListener('DOMContentLoaded', function() {
	AOS.init({
		duration: 800,
		once: true,
		offset: 100,
		initClassName: 'aos-init', // Класс применяется сразу
		//disableMutationObserver: true // ← Важно! Отключаем наблюдение
		//disable: 'mobile' // Отключаем на мобилках для производительности
	});
});