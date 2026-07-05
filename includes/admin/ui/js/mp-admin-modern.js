(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function addSummaryForOrderList() {
		var body = document.body;
		if (!body.classList.contains('post-type-mp_order') || !body.classList.contains('edit-php')) {
			return;
		}

		var table = document.querySelector('.wp-list-table.widefat tbody');
		if (!table) {
			return;
		}

		var badges = table.querySelectorAll('.mp-order-status-badge');
		if (!badges.length) {
			return;
		}

		var counts = {
			pending: 0,
			paid: 0,
			shipping: 0,
			closed: 0
		};

		badges.forEach(function (badge) {
			var text = (badge.textContent || '').toLowerCase();
			if (text.indexOf('aussteh') !== -1) {
				counts.pending += 1;
			} else if (text.indexOf('bezahlt') !== -1) {
				counts.paid += 1;
			} else if (text.indexOf('versand') !== -1) {
				counts.shipping += 1;
			} else if (text.indexOf('abgeschlossen') !== -1) {
				counts.closed += 1;
			}
		});

		var headerAnchor = document.querySelector('.wrap .wp-header-end');
		if (!headerAnchor) {
			return;
		}

		var oldSummary = document.querySelector('.mp-admin-summary');
		if (oldSummary) {
			oldSummary.remove();
		}

		var summary = document.createElement('div');
		summary.className = 'mp-admin-summary';
		summary.innerHTML = '' +
			'<div class="mp-admin-summary-item"><strong>' + counts.pending + '</strong><span>Ausstehend</span></div>' +
			'<div class="mp-admin-summary-item"><strong>' + counts.paid + '</strong><span>Bezahlt</span></div>' +
			'<div class="mp-admin-summary-item"><strong>' + counts.shipping + '</strong><span>Versand</span></div>' +
			'<div class="mp-admin-summary-item"><strong>' + counts.closed + '</strong><span>Abgeschlossen</span></div>';

		headerAnchor.parentNode.insertBefore(summary, headerAnchor.nextSibling);
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

	function buildKanbanForOrders() {
		var body = document.body;
		if (!body.classList.contains('post-type-mp_order') || !body.classList.contains('edit-php')) {
			return;
		}

		var wrap = document.querySelector('.wrap');
		var postsFilter = document.getElementById('posts-filter');
		var listTable = document.querySelector('.wp-list-table.widefat');
		if (!wrap || !listTable || !postsFilter) {
			return;
		}

		var oldBoard = document.querySelector('.mp-orders-kanban');
		if (oldBoard) {
			oldBoard.remove();
		}

		var toolbar = document.querySelector('.mp-orders-view-toggle');
		if (!toolbar) {
			toolbar = document.createElement('div');
			toolbar.className = 'mp-orders-view-toggle';
			toolbar.innerHTML = '' +
				'<button type="button" class="button" data-view="table">Tabelle</button>' +
				'<button type="button" class="button" data-view="kanban">Kanban</button>';
		}

		var topNav = postsFilter.querySelector('.tablenav.top');
		if (topNav) {
			topNav.parentNode.insertBefore(toolbar, topNav.nextSibling);
		} else if (toolbar.parentNode !== listTable.parentNode) {
			listTable.parentNode.insertBefore(toolbar, listTable);
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
			var statusCard = row.querySelector('.column-mp_orders_status .mp-order-status-card');
			var idLink = row.querySelector('.column-mp_orders_id .row-title');
			if (!statusCard || !idLink) {
				return;
			}

			var targetStatus = getStatusFromCard(statusCard);
			var targetColumn = board.querySelector('.mp-kanban-col[data-status="' + targetStatus + '"] .mp-kanban-cards');
			if (!targetColumn) {
				return;
			}

			var customer = row.querySelector('.column-mp_orders_name a');
			var total = row.querySelector('.column-mp_orders_total');
			var flow = statusCard.querySelector('.mp-order-flow-badge');
			var settlement = statusCard.querySelector('.mp-order-settlement-badge');
			var actions = statusCard.querySelector('.mp-order-status-actions');

			var card = document.createElement('article');
			card.className = 'mp-kanban-card';
			card.innerHTML = '' +
				'<a class="mp-kanban-order-link" href="' + idLink.getAttribute('href') + '">' + (idLink.textContent || '').trim() + '</a>' +
				'<p class="mp-kanban-customer">' + ((customer ? customer.textContent : '') || '').trim() + '</p>' +
				'<p class="mp-kanban-total">' + ((total ? total.textContent : '') || '').trim() + '</p>' +
				'<div class="mp-kanban-meta"></div>' +
				'<div class="mp-kanban-actions"></div>';

			var meta = card.querySelector('.mp-kanban-meta');
			if (flow) {
				meta.appendChild(flow.cloneNode(true));
			}
			if (settlement) {
				meta.appendChild(settlement.cloneNode(true));
			}

			if (actions) {
				card.querySelector('.mp-kanban-actions').appendChild(actions.cloneNode(true));
			}

			targetColumn.appendChild(card);
		});

		listTable.parentNode.insertBefore(board, listTable);

		var view = window.localStorage.getItem('mpOrdersView') || 'kanban';
		setOrdersView(view, board, listTable, toolbar);
	}

	function setOrdersView(view, board, listTable, toolbar) {
		var showKanban = view === 'kanban';
		board.style.display = showKanban ? 'grid' : 'none';
		listTable.style.display = showKanban ? 'none' : '';

		toolbar.querySelectorAll('button[data-view]').forEach(function (btn) {
			if (btn.getAttribute('data-view') === view) {
				btn.classList.add('button-primary');
			} else {
				btn.classList.remove('button-primary');
			}
		});

		window.localStorage.setItem('mpOrdersView', view);
	}

	function bindKanbanToggle() {
		document.addEventListener('click', function (event) {
			var btn = event.target.closest('.mp-orders-view-toggle button[data-view]');
			if (!btn) {
				return;
			}

			var board = document.querySelector('.mp-orders-kanban');
			var table = document.querySelector('.wp-list-table.widefat');
			var toolbar = document.querySelector('.mp-orders-view-toggle');
			if (!board || !table || !toolbar) {
				return;
			}

			event.preventDefault();
			setOrdersView(btn.getAttribute('data-view'), board, table, toolbar);
		});
	}

	ready(function () {
		document.body.classList.add('mp-modern-admin');
		addSummaryForOrderList();
		buildKanbanForOrders();
		bindKanbanToggle();
		document.addEventListener('mp:orderStatusUpdated', function () {
			addSummaryForOrderList();
			buildKanbanForOrders();
		});
	});
})();
