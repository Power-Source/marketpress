(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function getDashboardData() {
		if (window.mp_admin_orders && window.mp_admin_orders.dashboard) {
			return window.mp_admin_orders.dashboard;
		}

		return {};
	}

	function getStatusFromCard(card) {
		if (!card) {
			return '';
		}

		if (card.classList.contains('status-order_received')) {
			return 'order_received';
		}
		if (card.classList.contains('status-order_paid')) {
			return 'order_paid';
		}
		if (card.classList.contains('status-order_shipped')) {
			return 'order_shipped';
		}
		if (card.classList.contains('status-order_closed')) {
			return 'order_closed';
		}

		return '';
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function setViewButtons(view, toolbar) {
		if (!toolbar) {
			return;
		}

		toolbar.querySelectorAll('button[data-view]').forEach(function (btn) {
			btn.classList.toggle('button-primary', btn.getAttribute('data-view') === view);
		});
	}

	function setOrdersView(view, board, listTable, toolbar) {
		var showKanban = view === 'kanban';

		if (board) {
			board.style.display = showKanban ? 'grid' : 'none';
		}
		if (listTable) {
			listTable.style.display = showKanban ? 'none' : '';
		}

		document.documentElement.classList.remove('mp-orders-pref-table', 'mp-orders-pref-kanban');
		document.documentElement.classList.add('mp-orders-pref-' + view);
		setViewButtons(view, toolbar);
		window.localStorage.setItem('mpOrdersView', view);
	}

	function buildKanbanBoard(listTable) {
		var oldBoard = document.querySelector('.mp-orders-kanban');
		if (oldBoard) {
			oldBoard.remove();
		}

		var board = document.createElement('section');
		board.className = 'mp-orders-kanban';
		board.innerHTML = '' +
			'<div class="mp-kanban-col" data-status="order_received"><h3>Ausstehend</h3><div class="mp-kanban-cards"></div></div>' +
			'<div class="mp-kanban-col" data-status="order_paid"><h3>Bezahlt</h3><div class="mp-kanban-cards"></div></div>' +
			'<div class="mp-kanban-col" data-status="order_shipped"><h3>Versand</h3><div class="mp-kanban-cards"></div></div>' +
			'<div class="mp-kanban-col" data-status="order_closed"><h3>Abgeschlossen</h3><div class="mp-kanban-cards"></div></div>';

		var rows = listTable.querySelectorAll('tbody tr');
		rows.forEach(function (row) {
			upsertKanbanCardFromRow(row, board);
		});

		listTable.parentNode.insertBefore(board, listTable);

		return board;
	}

	function upsertKanbanCardFromRow(row, board) {
		var statusCard = row.querySelector('.column-mp_orders_status .mp-order-status-card');
		var idLink = row.querySelector('.column-mp_orders_id .row-title');
		if (!statusCard || !idLink || !board) {
			return;
		}

		var postId = parseInt(statusCard.getAttribute('data-order-id'), 10) || 0;
		var targetStatus = getStatusFromCard(statusCard);
		var targetColumn = board.querySelector('.mp-kanban-col[data-status="' + targetStatus + '"] .mp-kanban-cards');
		if (!targetColumn) {
			return;
		}

		var existing = postId ? board.querySelector('.mp-kanban-card[data-order-id="' + postId + '"]') : null;
		if (existing) {
			existing.remove();
		}

		var customer = row.querySelector('.column-mp_orders_name a');
		var total = row.querySelector('.column-mp_orders_total');
		var flow = statusCard.querySelector('.mp-order-flow-badge');
		var settlement = statusCard.querySelector('.mp-order-settlement-badge');
		var withdrawal = statusCard.querySelector('.mp-order-withdrawal-badge');
		var actions = statusCard.querySelector('.mp-order-status-actions');

		var card = document.createElement('article');
		card.className = 'mp-kanban-card';
		if (postId) {
			card.setAttribute('data-order-id', String(postId));
		}
		card.innerHTML = '' +
			'<a class="mp-kanban-order-link" href="' + idLink.getAttribute('href') + '">' + escapeHtml((idLink.textContent || '').trim()) + '</a>' +
			'<p class="mp-kanban-customer">' + escapeHtml(((customer ? customer.textContent : '') || '').trim()) + '</p>' +
			'<p class="mp-kanban-total">' + escapeHtml(((total ? total.textContent : '') || '').trim()) + '</p>' +
			'<div class="mp-kanban-meta"></div>' +
			'<div class="mp-kanban-actions"></div>';

		var meta = card.querySelector('.mp-kanban-meta');
		if (flow) {
			meta.appendChild(flow.cloneNode(true));
		}
		if (settlement) {
			meta.appendChild(settlement.cloneNode(true));
		}
		if (withdrawal) {
			meta.appendChild(withdrawal.cloneNode(true));
		}

		if (actions) {
			card.querySelector('.mp-kanban-actions').appendChild(actions.cloneNode(true));
		}

		targetColumn.appendChild(card);
	}

	function ensureHeaderToolbar(wrap, postsFilter) {
		var toolbar = postsFilter.querySelector('.mp-orders-toolbar');
		if (!toolbar || !wrap) {
			return toolbar;
		}

		var topNav = postsFilter.querySelector('.tablenav.top');
		var statusLinks = postsFilter.querySelector('.subsubsub');
		var bulkActions = topNav ? topNav.querySelector('.actions.bulkactions') : null;
		var dateActions = topNav ? topNav.querySelector('.actions:not(.bulkactions)') : null;
		var searchBox = topNav ? topNav.querySelector('.search-box') : null;
		var displaying = topNav ? topNav.querySelector('.tablenav-pages .displaying-num') : null;

		var topbar = wrap.querySelector('.mp-orders-topbar');
		if (!topbar) {
			topbar = document.createElement('div');
			topbar.className = 'mp-orders-topbar';
			topbar.innerHTML = '<div class="mp-orders-topbar-main"></div><div class="mp-orders-topbar-right"></div>';

			var heading = wrap.querySelector('h1.wp-heading-inline');
			if (heading && heading.nextSibling) {
				wrap.insertBefore(topbar, heading.nextSibling);
			} else {
				wrap.insertBefore(topbar, wrap.firstChild);
			}
		}

		var topMain = topbar.querySelector('.mp-orders-topbar-main');
		if (statusLinks && statusLinks.parentNode !== topMain) {
			topMain.appendChild(statusLinks);
		}
		if (toolbar.parentNode !== topMain) {
			topMain.appendChild(toolbar);
		}
		if (bulkActions && bulkActions.parentNode !== topMain) {
			topMain.appendChild(bulkActions);
		}
		if (dateActions && dateActions.parentNode !== topMain) {
			topMain.appendChild(dateActions);
		}

		var topRight = topbar.querySelector('.mp-orders-topbar-right');
		if (searchBox && searchBox.parentNode !== topRight) {
			topRight.appendChild(searchBox);
		}
		if (displaying && displaying.parentNode !== topRight) {
			topRight.appendChild(displaying);
		}

		if (topNav) {
			topNav.classList.add('mp-orders-tablenav-hidden');
		}

		return toolbar;
	}

	function ensureDashboardShell(wrap, postsFilter) {
		var shell = wrap.querySelector('.mp-orders-dashboard-shell');
		if (!shell) {
			shell = document.createElement('div');
			shell.className = 'mp-orders-dashboard-shell';
			shell.innerHTML = '<div class="mp-orders-main"></div><aside class="mp-orders-sidebar"></aside>';
			wrap.appendChild(shell);
		}

		var main = shell.querySelector('.mp-orders-main');
		if (postsFilter.parentNode !== main) {
			main.appendChild(postsFilter);
		}

		return {
			shell: shell,
			main: main,
			sidebar: shell.querySelector('.mp-orders-sidebar')
		};
	}

	function metric(label, value) {
		return '<div class="mp-widget-metric" data-label="' + escapeHtml(label) + '"><span class="label">' + escapeHtml(label) + '</span><strong class="value">' + escapeHtml(String(value)) + '</strong></div>';
	}

	function info(label, value) {
		return '<div class="mp-widget-info"><span class="label">' + escapeHtml(label) + '</span><strong class="value">' + escapeHtml(String(value)) + '</strong></div>';
	}

	function card(title, lines) {
		return '<section class="mp-dashboard-widget"><h3>' + escapeHtml(title) + '</h3>' + lines.join('') + '</section>';
	}

	function getFlowCountsFromRows() {
		var counts = {
			local: 0,
			hybrid_subshop: 0,
			network_global: 0,
			network_multi: 0
		};

		document.querySelectorAll('.wp-list-table.widefat tbody tr.type-mp_order').forEach(function (row) {
			var badge = row.querySelector('.column-mp_orders_status .mp-order-flow-badge');
			if (!badge) {
				return;
			}

			if (badge.classList.contains('mode-local')) {
				counts.local += 1;
			} else if (badge.classList.contains('mode-hybrid_subshop')) {
				counts.hybrid_subshop += 1;
			} else if (badge.classList.contains('mode-network_global')) {
				counts.network_global += 1;
			} else if (badge.classList.contains('mode-network_multi')) {
				counts.network_multi += 1;
			}
		});

		return counts;
	}

	function renderSidebar(sidebar) {
		if (!sidebar) {
			return;
		}

		var data = getDashboardData();
		var stats = data.stats || {};
		var network = data.network || {};
		var withdrawals = data.withdrawals || {};
		var reviews = data.reviews || {};
		var isMultisite = data.mode === 'multisite';

		var cards = [];
		cards.push(card('Shop Snapshot', [
			metric('Ausstehend', stats.received || 0),
			metric('Bezahlt', stats.paid || 0),
			metric('Versand', stats.shipped || 0),
			metric('Abgeschlossen', stats.closed || 0)
		]));

		if (isMultisite && network.global_cart) {
			cards.push(card('Sync mit Mainshop', [
				info('Global Cart', 'Aktiv'),
				info('Hybrid Routing', network.hybrid_routing ? 'Aktiv' : 'Inaktiv'),
				info('Settlement', network.settlement_enabled ? 'Aktiv' : 'Inaktiv'),
				info('Auto Release', network.auto_release ? 'Aktiv' : 'Inaktiv')
			]));
		}

		if (isMultisite && network.shop_performance_page) {
			var flowCounts = getFlowCountsFromRows();
			var totalFlow = flowCounts.local + flowCounts.hybrid_subshop + flowCounts.network_global + flowCounts.network_multi;
			var dominantFlow = 'network_global';
			var dominantCount = -1;

			Object.keys(flowCounts).forEach(function (flowKey) {
				if (flowCounts[flowKey] > dominantCount) {
					dominantCount = flowCounts[flowKey];
					dominantFlow = flowKey;
				}
			});

			var perfUrl = String(network.shop_performance_page || '');
			var perfUrlWithFlow = perfUrl;
			if (perfUrl) {
				perfUrlWithFlow += (perfUrl.indexOf('?') === -1 ? '?' : '&') + 'flow=' + encodeURIComponent(dominantFlow);
			}

			cards.push(card('Shopuser Performance', [
				'<p class="mp-widget-hint">Konsolidierte Netzwerkdaten plus aktueller Flow-Mix aus deiner Bestellliste.</p>',
				'<div class="mp-widget-flow-grid">' +
					'<span class="mp-flow-pill">Lokal: <strong>' + flowCounts.local + '</strong></span>' +
					'<span class="mp-flow-pill">Hybrid: <strong>' + flowCounts.hybrid_subshop + '</strong></span>' +
					'<span class="mp-flow-pill">Mainshop: <strong>' + flowCounts.network_global + '</strong></span>' +
					'<span class="mp-flow-pill">Multi-Shop: <strong>' + flowCounts.network_multi + '</strong></span>' +
				'</div>',
				info('Flow-Abdeckung', totalFlow > 0 ? (dominantFlow + ' dominiert') : 'Keine Daten im Filter'),
				info('Hold-Tage', network.hold_days || 14),
				'<p><a class="button button-secondary" href="' + escapeHtml(perfUrlWithFlow || perfUrl) + '" target="_blank" rel="noopener">Detailseite (Flow-Kontext)</a> <a class="button button-secondary" href="' + escapeHtml(perfUrl) + '" target="_blank" rel="noopener">Ohne Filter</a></p>'
			]));
		}

		cards.push(card('Widerruf Status', [
			metric('Offen gesamt', withdrawals.open_total || 0),
			metric('Beantragt', withdrawals.open || 0),
			metric('In Pruefung', withdrawals.in_progress || 0),
			metric('Genehmigt', withdrawals.approved || 0),
			metric('Abgelehnt', withdrawals.rejected || 0),
			metric('Erstattet', withdrawals.refunded || 0),
			metric('Geschlossen', withdrawals.closed || 0)
		]));

		if (reviews.active) {
			var audience = 'Registrierte & Gäste';
			if (Array.isArray(reviews.allowed_users)) {
				if (reviews.allowed_users.length === 1 && reviews.allowed_users[0] === 'registered') {
					audience = 'Nur registrierte';
				} else if (reviews.allowed_users.length === 1 && reviews.allowed_users[0] === 'guests') {
					audience = 'Nur Gäste';
				}
			}

			var averageLabel = (parseFloat(reviews.average_rating || 0).toFixed(1)) + ' / 5';
			var actionLabel = (reviews.pending_count || 0) > 0 ? ('Freigaben prüfen (' + (reviews.pending_count || 0) + ')') : 'Bewertungen prüfen';

			cards.push(card('Neue Bewertungen', [
				metric('Freigegeben', reviews.approved_count || 0),
				metric('Wartend', reviews.pending_count || 0),
				metric('Neu (7 Tage)', reviews.recent_7d_count || 0),
				info('Ø Sterne', averageLabel),
				info('Bewerten dürfen', audience),
				info('Nur Käufer', reviews.require_purchase ? 'Ja' : 'Nein'),
				info('Hilfreich-Button', reviews.enable_helpful ? 'Aktiv' : 'Inaktiv'),
				'<p><a class="button button-secondary mp-widget-nav-link" href="' + escapeHtml(reviews.moderation_url || 'edit-comments.php?comment_status=moderated') + '" data-open="same">' + escapeHtml(actionLabel) + '</a> <a class="button button-secondary mp-widget-nav-link" href="' + escapeHtml(reviews.all_url || 'edit-comments.php') + '" data-open="same">Alle anzeigen</a></p>'
			]));
		} else {
			cards.push(card('Neue Bewertungen', [
				'<p class="mp-widget-hint">Das Addon Produktbewertungen erlauben ist derzeit nicht aktiv.</p>',
				'<p><a class="button button-secondary mp-widget-nav-link" href="' + escapeHtml(reviews.settings_url || 'admin.php?page=store-settings-addons&addon=MP_MARKETPRESS_COMMENTS_Addon') + '" data-open="same">Addon öffnen</a></p>'
			]));
		}

		sidebar.innerHTML = cards.join('');
	}

	function refreshSidebarMetrics() {
		var rows = document.querySelectorAll('.wp-list-table.widefat tbody tr.type-mp_order');
		if (!rows.length) {
			return;
		}

		var counts = {
			order_received: 0,
			order_paid: 0,
			order_shipped: 0,
			order_closed: 0
		};

		rows.forEach(function (row) {
			var cardNode = row.querySelector('.column-mp_orders_status .mp-order-status-card');
			var status = getStatusFromCard(cardNode);
			if (Object.prototype.hasOwnProperty.call(counts, status)) {
				counts[status] += 1;
			}
		});

		setMetricValue('Ausstehend', counts.order_received);
		setMetricValue('Bezahlt', counts.order_paid);
		setMetricValue('Versand', counts.order_shipped);
		setMetricValue('Abgeschlossen', counts.order_closed);
	}

	function setMetricValue(label, value) {
		var node = document.querySelector('.mp-widget-metric[data-label="' + label + '"] .value');
		if (node) {
			node.textContent = String(value);
		}
	}

	function initOrderDashboard() {
		var body = document.body;
		if (!body.classList.contains('post-type-mp_order') || !body.classList.contains('edit-php')) {
			return;
		}

		body.classList.add('mp-modern-admin');

		var wrap = document.querySelector('.wrap');
		var postsFilter = document.getElementById('posts-filter');
		var listTable = document.querySelector('.wp-list-table.widefat');
		if (!wrap || !postsFilter || !listTable) {
			return;
		}

		var toolbar = ensureHeaderToolbar(wrap, postsFilter);
		var layout = ensureDashboardShell(wrap, postsFilter);
		var board = buildKanbanBoard(listTable);

		renderSidebar(layout.sidebar);

		var preferredView = window.localStorage.getItem('mpOrdersView') || 'kanban';
		setOrdersView(preferredView, board, listTable, toolbar);
		refreshSidebarMetrics();

		document.addEventListener('click', function (event) {
			var btn = event.target.closest('.mp-orders-view-toggle button[data-view]');
			if (!btn) {
				var navLink = event.target.closest('.mp-widget-nav-link');
				if (!navLink) {
					return;
				}

				event.preventDefault();
				var href = navLink.getAttribute('href');
				if (!href) {
					return;
				}

				window.location.href = href;
				return;
			}

			event.preventDefault();
			setOrdersView(btn.getAttribute('data-view'), board, listTable, toolbar);
		});

		document.addEventListener('mp:orderStatusUpdated', function (event) {
			var detail = event && event.detail ? event.detail : {};
			var postId = parseInt(detail.postId, 10) || 0;

			if (postId && board && board.style.display !== 'none') {
				var row = listTable.querySelector('tr#post-' + postId);
				if (row) {
					upsertKanbanCardFromRow(row, board);
				} else {
					var staleCard = board.querySelector('.mp-kanban-card[data-order-id="' + postId + '"]');
					if (staleCard) {
						staleCard.remove();
					}
				}
			} else {
				board = buildKanbanBoard(listTable);
				setOrdersView(window.localStorage.getItem('mpOrdersView') || 'kanban', board, listTable, toolbar);
			}

			refreshSidebarMetrics();
		});

		body.classList.add('mp-orders-ready');
	}

	ready(initOrderDashboard);
})();
