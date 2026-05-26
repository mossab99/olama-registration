/**
 * os-hub.js — Customer Hub Panel Manager
 *
 * Phase 1: PanelManager + keyboard shortcuts + type card interactions
 * Phase 2: SearchModule, CountsLoader, TileManager AJAX, IdentityHeader
 * Phase 3: ProfileEditor (inline edit + activate/deactivate)
 * Phase 4: YearSelector, QuickActions, TileShortcuts, ChildrenInlineAdd
 *
 * Pattern: IIFE module (matches existing olama-reg.js style)
 * Data: JSON hydration block via #os-hub-data (per OLAMASKILL.md §4.1)
 * NO wp_localize_script usage here.
 */
/* global jQuery */
(function ($) {
    'use strict';

    // ── Bootstrap: Read JSON hydration block ──────────────────────────────────
    var hubDataEl = document.getElementById('os-hub-data');
    if (!hubDataEl) {
        // Not on hub page — do nothing
        return;
    }

    var HUB_DATA  = JSON.parse(hubDataEl.textContent);
    var AJAX_URL  = HUB_DATA.ajaxUrl;
    var NONCE     = HUB_DATA.nonce;
    var USER_ID   = HUB_DATA.currentUserId;
    var I18N      = HUB_DATA.i18n;

    // localStorage key scoped per user (shared workstation safety)
    var RECENT_KEY = 'os_hub_recent_' + USER_ID;

    // ── State ─────────────────────────────────────────────────────────────────
    var state = {
        currentType:     null,   // 'family' | 'external'
        currentCustomer: null,   // { uid, name, type, phone, count }
        currentPanel:    'type', // 'type' | 'lookup' | 'hub'
        openTile:        null,   // tile id currently expanded
        currentYearId:   HUB_DATA.currentYearId || 0,  // active academic year filter
    };

    // ══════════════════════════════════════════════════════════════════════════
    // PanelManager — controls which of the 3 stage panels is visible
    // ══════════════════════════════════════════════════════════════════════════
    var PanelManager = {

        panels: {
            type:   '#os-hub-panel-type',
            lookup: '#os-hub-panel-lookup',
            hub:    '#os-hub-panel-hub',
        },

        /**
         * Show the specified panel, hide all others.
         * @param {string} panelId  'type' | 'lookup' | 'hub'
         */
        show: function (panelId) {
            var self = this;
            Object.keys(self.panels).forEach(function (id) {
                var $el = $(self.panels[id]);
                if (id === panelId) {
                    $el.addClass('os-hub-panel--active')
                       .removeAttr('aria-hidden');
                    // Restore aria-hidden = false explicitly
                    $el.attr('aria-hidden', 'false');
                } else {
                    $el.removeClass('os-hub-panel--active')
                       .attr('aria-hidden', 'true');
                }
            });
            state.currentPanel = panelId;

            // Focus management: move focus to first focusable element in panel
            var $panel = $(self.panels[panelId]);
            var $focusable = $panel.find('input, button, [tabindex="0"]').filter(':visible').first();
            if ($focusable.length) {
                setTimeout(function () { $focusable.focus(); }, 100);
            }
        },
    };

    // ══════════════════════════════════════════════════════════════════════════
    // TypeSelector — Stage 1: Family vs External card interactions
    // ══════════════════════════════════════════════════════════════════════════
    var TypeSelector = {

        init: function () {
            var self = this;

            // Click on type cards
            $(document).on('click', '.os-hub-type-card', function () {
                var type = $(this).data('type');
                self.select(type);
            });

            // Keyboard: Enter / Space activates the card
            $(document).on('keydown', '.os-hub-type-card', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    var type = $(this).data('type');
                    self.select(type);
                }
            });

            // Global keyboard shortcuts (only when on type panel)
            $(document).on('keydown', function (e) {
                // Only fire if no input/textarea is focused and we're on type panel
                if ($(e.target).is('input, textarea, select, [contenteditable]')) return;
                if (state.currentPanel !== 'type') return;

                if (e.key === 'f' || e.key === 'F') {
                    e.preventDefault();
                    self.select('family');
                } else if (e.key === 'e' || e.key === 'E') {
                    e.preventDefault();
                    self.select('external');
                }
            });
        },

        select: function (type) {
            state.currentType = type;

            // Update aria-pressed on cards
            $('.os-hub-type-card').attr('aria-pressed', 'false');
            $('.os-hub-type-card[data-type="' + type + '"]').attr('aria-pressed', 'true');

            // Update type badge in lookup panel
            TypeBadge.update(type);

            // Show/Hide Add New Customer button based on currentType
            if (type === 'external') {
                $('#cust_btn_add_new').show();
            } else {
                $('#cust_btn_add_new').hide();
            }

            // Transition to lookup panel
            PanelManager.show('lookup');

            // Phase 2: SearchModule will init here
            SearchModule.reset();
            RecentLookups.render(type);
        },
    };

    // ══════════════════════════════════════════════════════════════════════════
    // TypeBadge — small pill showing current type in lookup panel
    // ══════════════════════════════════════════════════════════════════════════
    var TypeBadge = {
        update: function (type) {
            var $badge = $('#os-hub-current-type-badge');
            var label  = (type === 'family') ? I18N.family : I18N.external;
            var cls    = (type === 'family') ? '' : 'os-hub-type-badge--external';

            $badge
                .text(label)
                .removeClass('os-hub-type-badge--external')
                .addClass(cls)
                .prepend(
                    $('<span class="dashicons" aria-hidden="true">').addClass(
                        type === 'family' ? 'dashicons-groups' : 'dashicons-admin-users'
                    )
                );
        },
    };

    // ══════════════════════════════════════════════════════════════════════════
    // RecentLookups — localStorage-based recent customer list
    // ══════════════════════════════════════════════════════════════════════════
    var RecentLookups = {

        get: function () {
            try {
                return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
            } catch (e) {
                return [];
            }
        },

        save: function (customer) {
            var recent   = this.get();
            var filtered = recent.filter(function (r) { return r.uid !== customer.uid; });
            filtered.unshift({
                uid:       customer.uid,
                name:      customer.name,
                type:      customer.type,
                phone:     customer.phone || '',
                timestamp: Date.now(),
            });
            try {
                localStorage.setItem(RECENT_KEY, JSON.stringify(filtered.slice(0, 10)));
            } catch (e) { /* storage full — ignore */ }
        },

        render: function (filterType) {
            var recent  = this.get();
            var $list   = $('#os-hub-recent-list');
            var $section = $('#os-hub-recent-section');

            // Filter by current type
            var filtered = recent.filter(function (r) { return r.type === filterType; });

            if (!filtered.length) {
                $section.hide();
                return;
            }

            $section.show();
            $list.empty();

            filtered.forEach(function (r) {
                var $item = $('<li class="os-hub-recent__item" role="option" tabindex="0">')
                    .data('customer', r)
                    .attr('aria-label', r.name);

                $item.append(
                    $('<span class="dashicons dashicons-backup" aria-hidden="true">'),
                    $('<span class="os-hub-recent__item-name">').text(r.name),
                    $('<span class="os-hub-recent__item-type">').text(
                        r.type === 'family' ? I18N.family : I18N.external
                    )
                );

                $list.append($item);
            });
        },

        init: function () {
            // Click on recent item
            $(document).on('click keydown', '#os-hub-recent-list .os-hub-recent__item', function (e) {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
                var customer = $(this).data('customer');
                CustomerHub.loadCustomer(customer);
            });
        },
    };

    // ══════════════════════════════════════════════════════════════════════════
    // SearchModule — Phase 2 placeholder (wired up next phase)
    // ══════════════════════════════════════════════════════════════════════════
    var SearchModule = {

        _timer: null,

        reset: function () {
            $('#os-hub-search-input').val('');
            $('#os-hub-results-list').empty();
            $('#os-hub-search-status').empty();
        },

        init: function () {
            // Debounced search on input
            $(document).on('input', '#os-hub-search-input', function () {
                var query = $(this).val().trim();
                clearTimeout(SearchModule._timer);
                if (query.length < 2) {
                    $('#os-hub-results-list').empty();
                    $('#os-hub-search-status').empty();
                    return;
                }
                SearchModule._timer = setTimeout(function () {
                    SearchModule.run(query);
                }, 200);
            });

            // Search button click
            $(document).on('click', '#os-hub-search-btn', function () {
                var query = $('#os-hub-search-input').val().trim();
                if (query.length >= 2) {
                    SearchModule.run(query);
                }
            });

            // Enter key in search input
            $(document).on('keydown', '#os-hub-search-input', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var query = $(this).val().trim();
                    if (query.length >= 2) {
                        SearchModule.run(query);
                    } else {
                        // If results showing, select first
                        var $first = $('#os-hub-results-list li:first');
                        if ($first.length) $first.trigger('click');
                    }
                }
                // ↓ Arrow down: move to first result
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    $('#os-hub-results-list li:first').focus();
                }
            });

            // Keyboard navigation inside results list
            $(document).on('keydown', '#os-hub-results-list li', function (e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    $(this).next('li').focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    var $prev = $(this).prev('li');
                    if ($prev.length) {
                        $prev.focus();
                    } else {
                        $('#os-hub-search-input').focus();
                    }
                } else if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });

            // Click result
            $(document).on('click', '#os-hub-results-list .os-hub-result-item', function () {
                var customer = $(this).data('customer');
                CustomerHub.loadCustomer(customer);
            });

            // Click add new customer/family button
            $(document).on('click', '#os-hub-add-new-btn', function () {
                if (state.currentType === 'family') {
                    window.location.href = ADMIN_URL + 'admin.php?page=olama-registration&view=families&action=new';
                } else {
                    window.location.href = ADMIN_URL + 'admin.php?page=olama-registration&view=customers#add-new';
                }
            });
        },

        run: function (query) {
            var $status  = $('#os-hub-search-status');
            var $results = $('#os-hub-results-list');

            $status.html(
                '<span class="os-hub-spinner" aria-hidden="true"></span>' +
                '<span>' + I18N.loading + '</span>'
            );
            $results.empty();

            $.ajax({
                url:    AJAX_URL,
                method: 'POST',
                data: {
                    action: 'os_hub_search',
                    nonce:  NONCE,
                    q:      query,
                    type:   state.currentType,
                },
                success: function (response) {
                    $status.empty();
                    if (!response.success) {
                        SearchModule.showError(I18N.errorGeneric);
                        return;
                    }
                    var results = response.data.results || [];
                    if (!results.length) {
                        SearchModule.showEmpty(query);
                        return;
                    }
                    SearchModule.renderResults(results);
                },
                error: function () {
                    $status.empty();
                    SearchModule.showError(I18N.networkError);
                },
            });
        },

        renderResults: function (results) {
            var $list = $('#os-hub-results-list');
            $list.empty();

            results.forEach(function (r) {
                var isFamily   = (state.currentType === 'family');
                var countLabel = isFamily
                    ? (r.student_count + ' ' + I18N.students)
                    : (r.child_count   + ' ' + I18N.children);

                var $item = $('<li>')
                    .addClass('os-hub-result-item')
                    .addClass(isFamily ? '' : 'os-hub-result-item--external')
                    .attr({ role: 'option', tabindex: '0', 'aria-selected': 'false' })
                    .data('customer', {
                        uid:         r.uid,
                        name:        r.name,
                        type:        state.currentType,
                        phone:       r.phone || '',
                        is_active:   (r.is_active !== undefined) ? parseInt(r.is_active, 10) : 1,
                        internal_id: r.internal_id || '',
                    });

                $item.append(
                    $('<span class="os-hub-result-item__icon" aria-hidden="true">').append(
                        $('<span class="dashicons">').addClass(
                            isFamily ? 'dashicons-groups' : 'dashicons-admin-users'
                        )
                    ),
                    $('<div class="os-hub-result-item__body">').append(
                        $('<span class="os-hub-result-item__name">').text(r.name),
                        $('<span class="os-hub-result-item__meta">').text(r.phone + ' · ' + countLabel)
                    ),
                    $('<span class="os-hub-result-item__uid os-hub-ltr">').text(r.uid)
                );

                $list.append($item);
            });
        },

        showEmpty: function (query) {
            $('#os-hub-results-list').html(
                '<li class="os-hub-notice" style="padding:20px 16px;">' +
                '<span class="dashicons dashicons-warning" aria-hidden="true"></span>' +
                '<span class="os-hub-notice__title">' + I18N.noResults + '</span>' +
                '<span class="os-hub-notice__body">' + escHtml(query) + '</span>' +
                '<div class="os-hub-notice__actions">' +
                '<button type="button" class="button" id="os-hub-add-new-btn">' +
                I18N.addNewCustomer + '</button>' +
                '</div></li>'
            );
        },

        showError: function (msg) {
            $('#os-hub-search-status').html(
                '<span class="dashicons dashicons-warning" style="color:#d63638;" aria-hidden="true"></span>' +
                '<span style="color:#d63638;">' + escHtml(msg) + '</span>'
            );
        },
    };

    // ══════════════════════════════════════════════════════════════════════════
    // TileManager — accordion expand/collapse + AJAX content loading
    // ══════════════════════════════════════════════════════════════════════════
    var TileManager = {

        init: function () {
            // Tile button click → toggle accordion
            $(document).on('click', '.os-hub-tile', function () {
                var tileId  = $(this).data('tile');
                var $btn    = $(this);
                var $panel  = $('#os-hub-tile-panel-' + tileId);
                var isOpen  = $btn.attr('aria-expanded') === 'true';

                // Close all tiles first
                TileManager.collapseAll();

                if (!isOpen) {
                    TileManager.open(tileId, $btn, $panel);
                }
            });

            // Keyboard on tile button
            $(document).on('keydown', '.os-hub-tile', function (e) {
                if (e.key === 'Escape') {
                    TileManager.collapseAll();
                    $(this).focus();
                }
            });
        },

        open: function (tileId, $btn, $panel) {
            $btn.attr('aria-expanded', 'true');
            $panel.attr('aria-hidden', 'false').show();
            state.openTile = tileId;

            var $content = $panel.find('.os-hub-tile-panel__content');
            var $loading = $panel.find('.os-hub-tile-panel__loading');

            // Only load if not already loaded for this customer
            if ($content.data('loaded')) {
                $loading.hide();
                $content.show();
                // Still fire the event so TileReloader can add its button
                $(document).trigger('os-hub:tile-opened', [{ tileId: tileId, $panel: $panel }]);
                return;
            }

            $loading.show();
            $content.hide().empty();

            if (!state.currentCustomer) return;

            $.ajax({
                url:    AJAX_URL,
                method: 'POST',
                data: {
                    action: 'os_hub_tile',
                    nonce:  NONCE,
                    tile:   tileId,
                    uid:    state.currentCustomer.uid,
                    type:   state.currentCustomer.type,
                    year:   state.currentYearId,
                },
                success: function (response) {
                    $loading.hide();
                    $content.show();

                    if (!response.success) {
                        $content.html(
                            '<div class="os-hub-notice os-hub-notice--error">'
                            + '<span class="dashicons dashicons-warning" aria-hidden="true"></span>'
                            + '<p class="os-hub-notice__title">' + escHtml(I18N.errorGeneric) + '</p>'
                            + '</div>'
                        );
                        return;
                    }

                    $content.html(response.data.html);
                    $content.data('loaded', true);

                    // Fire event so TileReloader injects its button
                    $(document).trigger('os-hub:tile-opened', [{ tileId: tileId, $panel: $panel }]);
                },
                error: function () {
                    $loading.hide();
                    $content.show().html(
                        '<div class="os-hub-notice os-hub-notice--error">'
                        + '<span class="dashicons dashicons-warning" aria-hidden="true"></span>'
                        + '<p class="os-hub-notice__title">' + escHtml(I18N.networkError) + '</p>'
                        + '<div class="os-hub-notice__actions">'
                        + '<button type="button" class="button os-hub-tile-retry" data-tile="' + tileId + '">' + escHtml(I18N.retry) + '</button>'
                        + '</div></div>'
                    );
                },
            });
        },

        collapseAll: function () {
            $('.os-hub-tile').attr('aria-expanded', 'false');
            $('.os-hub-tile-panel').attr('aria-hidden', 'true').hide();
            state.openTile = null;
        },
    };

    // ══════════════════════════════════════════════════════════════════════════
    // CountsLoader — loads badge counts for all tiles in one AJAX request
    // ══════════════════════════════════════════════════════════════════════════
    var CountsLoader = {

        load: function (uid, type) {
            $.ajax({
                url:    AJAX_URL,
                method: 'POST',
                data: {
                    action: 'os_hub_counts',
                    nonce:  NONCE,
                    uid:    uid,
                    type:   type,
                    year:   state.currentYearId,
                },
                success: function (response) {
                    if (!response.success) return;

                    var counts = response.data.counts || {};
                    var fin    = response.data.financial_mini || null;

                    // Update tile badges
                    Object.keys(counts).forEach(function (tile) {
                        var count  = counts[tile];
                        var $badge = $('#os-hub-tile-badge-' + tile);
                        if (count !== null && count !== undefined && count > 0) {
                            $badge.text(count).show();
                        } else {
                            $badge.hide();
                        }
                    });

                    // Update identity header with financial mini-stats (families only)
                    if (fin && type === 'family') {
                        IdentityHeader.renderFinStats(fin);
                    }
                },
                error: function () {
                    // Silently fail — badge counts are non-critical
                },
            });
        },
    };

    // ══════════════════════════════════════════════════════════════════════════
    // CustomerHub — top-level: loads customer into the hub (Stage 3)
    // ══════════════════════════════════════════════════════════════════════════
    var CustomerHub = {

        loadCustomer: function (customer) {
            state.currentCustomer = customer;

            // Save to recent lookups
            RecentLookups.save(customer);

            // Update identity header
            IdentityHeader.render(customer);

            // Settlement tile visibility (families only)
            if (customer.type === 'family') {
                $('.os-hub-tile-item--family-only').removeClass('os-hub-tile-item--hidden');
            } else {
                $('.os-hub-tile-item--family-only').addClass('os-hub-tile-item--hidden');
            }

            // Reset tiles (clear loaded content for fresh customer)
            TileManager.collapseAll();
            $('.os-hub-tile-panel__content').removeData('loaded').empty();

            // Show hub panel
            PanelManager.show('hub');

            // Load badge counts via AJAX
            CountsLoader.load(customer.uid, customer.type);

            // Phase 4: show quick actions + year selector
            QuickActions.show(customer);
            YearSelector.showForCustomer();
        },
    };

    // ══════════════════════════════════════════════════════════════════════════
    // IdentityHeader — renders the sticky customer info bar in Stage 3
    // ══════════════════════════════════════════════════════════════════════════
    var IdentityHeader = {
        render: function (customer) {
            $('#os-hub-identity-name').text(customer.name);

            // Status dot
            var isActive  = (customer.is_active === undefined) ? true : (customer.is_active == 1);
            var statusDot = isActive
                ? '<span class="os-hub-status-dot os-hub-status-dot--active" title="' + escHtml(I18N.active || 'نشط') + '" aria-label="' + escHtml(I18N.active || 'نشط') + '"></span>'
                : '<span class="os-hub-status-dot os-hub-status-dot--inactive" title="' + escHtml(I18N.inactive || 'غير نشط') + '" aria-label="' + escHtml(I18N.inactive || 'غير نشط') + '"></span>';

            var meta = statusDot;
            meta += '<span class="os-hub-uid-copy" title="نسخ" data-uid="' + escHtml(customer.uid) + '">'
                  + '<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>'
                  + escHtml(customer.uid) + '</span>';
            if (customer.phone) meta += '<span>·</span><span>' + escHtml(customer.phone) + '</span>';
            var typeLbl = customer.type === 'family' ? I18N.family : I18N.external;
            meta += '<span class="os-hub-type-badge' + (customer.type === 'external' ? ' os-hub-type-badge--external' : '') + '">' + escHtml(typeLbl) + '</span>';
            $('#os-hub-identity-meta').html(meta);

            // Avatar icon
            var $avatar = $('#os-hub-identity .os-hub-identity__avatar');
            $avatar.find('.dashicons')
                   .removeClass('dashicons-admin-users dashicons-groups')
                   .addClass(customer.type === 'family' ? 'dashicons-groups' : 'dashicons-admin-users');

            // Clear previous fin-stats
            $('#os-hub-identity-fin-stats').remove();
        },

        renderFinStats: function (fin) {
            var $existing = $('#os-hub-identity-fin-stats');
            if ($existing.length) $existing.remove();

            if (!fin) return;

            var fmt = function (n) { return parseFloat(n).toLocaleString('ar', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

            var html = '<div class="os-hub-identity__fin-stats" id="os-hub-identity-fin-stats">';
            html += '<span class="os-hub-identity__fin-stat">'
                 + '<span>مفوتر: </span><strong dir="ltr">' + fmt(fin.total_billed) + '</strong></span>';
            html += '<span class="os-hub-identity__fin-stat os-hub-identity__fin-stat--paid">'
                 + '<span>مدفوع: </span><strong dir="ltr">' + fmt(fin.total_paid) + '</strong></span>';
            if (parseFloat(fin.total_balance) > 0) {
                html += '<span class="os-hub-identity__fin-stat os-hub-identity__fin-stat--balance">'
                     + '<span>رصيد: </span><strong dir="ltr">' + fmt(fin.total_balance) + '</strong></span>';
            }
            html += '</div>';

            $('#os-hub-identity-meta').after(html);
        },
    };

    // ══════════════════════════════════════════════════════════════════════════
    // Navigation — Back buttons
    // ══════════════════════════════════════════════════════════════════════════
    var Navigation = {
        init: function () {
            // Back to type selection
            $(document).on('click', '#os-hub-back-to-type', function () {
                state.currentType     = null;
                state.currentCustomer = null;
                $('#cust_btn_add_new').hide();
                PanelManager.show('type');
            });

            // Back to search
            $(document).on('click', '#os-hub-back-to-search', function () {
                TileManager.collapseAll();
                if (state.currentType === 'external') {
                    $('#cust_btn_add_new').show();
                } else {
                    $('#cust_btn_add_new').hide();
                }
                PanelManager.show('lookup');
                // Re-render recent for current type
                RecentLookups.render(state.currentType);
            });

            // Tile retry button
            $(document).on('click', '.os-hub-tile-retry', function () {
                var tileId = $(this).data('tile');
                var $btn   = $('#os-hub-tile-btn-' + tileId);
                var $panel = $('#os-hub-tile-panel-' + tileId);
                $panel.find('.os-hub-tile-panel__content').removeData('loaded').empty();
                TileManager.open(tileId, $btn, $panel);
            });

            // Global Escape key
            $(document).on('keydown', function (e) {
                if ($(e.target).is('input, textarea, select')) return;
                if (e.key !== 'Escape') return;

                if (state.currentPanel === 'hub') {
                    if (state.openTile) {
                        TileManager.collapseAll();
                    } else {
                        PanelManager.show('lookup');
                        RecentLookups.render(state.currentType);
                    }
                } else if (state.currentPanel === 'lookup') {
                    state.currentType = null;
                    PanelManager.show('type');
                }
            });

            // Ctrl+K: focus search input
            $(document).on('keydown', function (e) {
                if (e.ctrlKey && (e.key === 'k' || e.key === 'K')) {
                    e.preventDefault();
                    if (state.currentPanel === 'lookup') {
                        $('#os-hub-search-input').focus();
                    }
                }
            });
        },
    };

    // ══════════════════════════════════════════════════════════════════════════
    // PHASE 5 MODULES
    // ══════════════════════════════════════════════════════════════════════════

    // ── TileReloader: ↺ button inside each open tile panel ──────────────────
    var TileReloader = {
        init: function () {
            // Inject reload button when a tile opens
            $(document).on('os-hub:tile-opened', function (e, data) {
                var $panel = data.$panel;
                var tileId = data.tileId;
                // Remove any existing reload button first
                $panel.find('.os-hub-tile-reload-btn').remove();

                var $btn = $('<button type="button">')
                    .addClass('os-hub-tile-reload-btn')
                    .attr({
                        'aria-label': 'تحديث',
                        'title':       'تحديث البيانات',
                        'data-tile':   tileId,
                    })
                    .html('<span class="dashicons dashicons-update" aria-hidden="true"></span>');

                $panel.find('.os-hub-tile-panel__inner').prepend($btn);
            });

            // Click reload button
            $(document).on('click', '.os-hub-tile-reload-btn', function () {
                var tileId = $(this).data('tile');
                var $btn   = $('#os-hub-tile-btn-' + tileId);
                var $panel = $('#os-hub-tile-panel-' + tileId);
                $panel.find('.os-hub-tile-panel__content').removeData('loaded').empty();
                TileManager.open(tileId, $btn, $panel);
            });
        },
    };

    // ── UidCopy: copy UID to clipboard from identity meta bar ─────────────
    var UidCopy = {
        init: function () {
            $(document).on('click', '.os-hub-uid-copy', function () {
                var uid  = $(this).data('uid');
                var self = this;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(uid).then(function () {
                        UidCopy.flash(self, '✓ تم النسخ');
                    });
                } else {
                    // Fallback
                    var $t = $('<textarea>').val(uid).appendTo('body').select();
                    document.execCommand('copy');
                    $t.remove();
                    UidCopy.flash(self, '✓ تم النسخ');
                }
            });
        },

        flash: function (el, msg) {
            var $el  = $(el);
            var orig = $el.html();
            $el.html('<span class="dashicons dashicons-yes" aria-hidden="true"></span>' + msg);
            setTimeout(function () { $el.html(orig); }, 1500);
        },
    };

    // ══════════════════════════════════════════════════════════════════════════
    // PHASE 4 MODULES
    // ══════════════════════════════════════════════════════════════════════════

    // ── YearSelector: populate & respond to academic year dropdown ─────────
    var YearSelector = {

        _populated: false,

        init: function () {
            var self = this;

            // Populate the <select> once from HUB_DATA
            if (!self._populated && HUB_DATA.academicYears && HUB_DATA.academicYears.length) {
                var $sel = $('#os-hub-year-select');
                // "All years" option
                $sel.append($('<option>').val('0').text(I18N.yearAll || 'جميع السنوات'));
                HUB_DATA.academicYears.forEach(function (y) {
                    var $opt = $('<option>').val(y.id).text(y.name);
                    if (y.id === HUB_DATA.currentYearId) $opt.prop('selected', true);
                    $sel.append($opt);
                });
                self._populated = true;
            }

            // On year change: update state, bust tile caches, reload counts
            $(document).on('change', '#os-hub-year-select', function () {
                state.currentYearId = parseInt($(this).val(), 10) || 0;
                // Bust all tile caches for this customer
                $('.os-hub-tile-panel__content').removeData('loaded').empty();
                TileManager.collapseAll();
                if (state.currentCustomer) {
                    CountsLoader.load(state.currentCustomer.uid, state.currentCustomer.type);
                }
            });
        },

        showForCustomer: function () {
            if (HUB_DATA.academicYears && HUB_DATA.academicYears.length) {
                $('#os-hub-year-wrap').show();
            }
        },

        hide: function () {
            $('#os-hub-year-wrap').hide();
        },
    };

    // ── QuickActions: quick-link bar in identity header ───────────────────
    var ADMIN_URL = HUB_DATA.adminUrl || HUB_DATA.ajaxUrl.replace('admin-ajax.php', '');

    var QuickActions = {

        show: function (customer) {
            var $bar = $('#os-hub-quick-actions');

            // Build URLs
            var invoiceUrl       = ADMIN_URL + 'admin.php?page=olama-registration-invoices&action=new';
            var agreementUrl     = ADMIN_URL + 'admin.php?page=olama-registration-agreements&action=new';
            var paymentUrl       = ADMIN_URL + 'admin.php?page=olama-registration-payments&action=new';
            var settlementUrl    = ADMIN_URL + 'admin.php?page=olama-registration-settlements';
            var customPaymentUrl = ADMIN_URL + 'admin.php?page=olama-registration-custom-payments';

            if (customer.type === 'family') {
                invoiceUrl       += '&family_uid='  + encodeURIComponent(customer.uid);
                agreementUrl     += '&payer_type=family&payer_uid=' + encodeURIComponent(customer.uid);
                paymentUrl       += '&family_uid='  + encodeURIComponent(customer.uid);
                settlementUrl    += '&family_uid='  + encodeURIComponent(customer.uid);
                customPaymentUrl += '&family_uid='  + encodeURIComponent(customer.uid);
                $bar.find('.os-hub-qaction--family-only').show();
            } else {
                agreementUrl     += '&payer_type=customer&payer_uid=' + encodeURIComponent(customer.uid);
                customPaymentUrl += '&ext_customer_id=' + encodeURIComponent(customer.internal_id || customer.id || '');
                $bar.find('.os-hub-qaction--family-only').hide();
            }

            $('#os-hub-qaction-invoice').attr('href', invoiceUrl);
            $('#os-hub-qaction-agreement').attr('href', agreementUrl);
            $('#os-hub-qaction-payment').attr('href', paymentUrl);
            $('#os-hub-qaction-settlement').attr('href', settlementUrl);
            $('#os-hub-qaction-custom-payment').attr('href', customPaymentUrl);

            $bar.show();
        },

        hide: function () {
            $('#os-hub-quick-actions').hide();
        },
    };

    // ── TileShortcuts: keyboard 1–8 to open tile panels ──────────────────
    var TileShortcuts = {

        tileIds: ['profile','agreements','invoices','payments','children','financial','history','settlements'],

        init: function () {
            var self = this;
            // Show legend when hub panel is reached
            $(document).on('keydown', function (e) {
                if ($(e.target).is('input, textarea, select, [contenteditable]')) return;
                if (state.currentPanel !== 'hub') return;

                var idx = parseInt(e.key, 10) - 1;
                if (isNaN(idx) || idx < 0 || idx >= self.tileIds.length) return;

                e.preventDefault();
                var tileId = self.tileIds[idx];
                var $btn   = $('#os-hub-tile-btn-' + tileId);
                var $panel = $('#os-hub-tile-panel-' + tileId);

                if (!$btn.length || $btn.closest('.os-hub-tile-item').hasClass('os-hub-tile-item--hidden')) return;

                var isOpen = $btn.attr('aria-expanded') === 'true';
                TileManager.collapseAll();
                if (!isOpen) {
                    TileManager.open(tileId, $btn, $panel);
                }
                $btn[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        },
    };

    // ── ChildrenInlineAdd: add child form in children tile ─────────────
    var ChildrenInlineAdd = {

        init: function () {
            // Show add-child form
            $(document).on('click', '#os-hub-add-child-btn', function () {
                var $wrap = $(this).closest('.os-hub-add-child-wrap');
                $wrap.find('#os-hub-add-child-form').slideDown(200);
                $wrap.find('#os-hub-add-child-btn').hide();
                $wrap.find('#os-hub-child-name-input').focus();
            });

            // Cancel
            $(document).on('click', '.os-hub-add-child-cancel', function () {
                var $wrap = $(this).closest('.os-hub-add-child-wrap');
                $wrap.find('#os-hub-add-child-form').slideUp(160);
                $wrap.find('#os-hub-add-child-btn').show();
                $wrap.find('#os-hub-add-child-form input').val('');
            });

            // Submit
            $(document).on('submit', '#os-hub-add-child-form', function (e) {
                e.preventDefault();
                ChildrenInlineAdd.submit($(this));
            });
        },

        submit: function ($form) {
            var childName = $form.find('[name="child_name"]').val().trim();
            if (!childName) {
                alert(I18N.childNameRequired || 'اسم الابن مطلوب.');
                $form.find('[name="child_name"]').focus();
                return;
            }

            var $submitBtn = $form.find('.os-hub-add-child-submit');
            $submitBtn.prop('disabled', true)
                      .html('<span class="os-hub-spinner" style="width:14px;height:14px;border-width:2px;" aria-hidden="true"></span> جاري...');

            $.ajax({
                url:    AJAX_URL,
                method: 'POST',
                data: {
                    action:     'os_hub_add_child',
                    nonce:      NONCE,
                    uid:        state.currentCustomer.uid,
                    child_name: childName,
                    grade:      $form.find('[name="grade"]').val().trim(),
                },
                success: function (response) {
                    $submitBtn.prop('disabled', false)
                              .html('<span class="dashicons dashicons-saved" aria-hidden="true"></span> حفظ');

                    if (!response.success) {
                        ChildrenInlineAdd.toast($form, response.data.message || 'حدث خطأ.', 'error');
                        return;
                    }

                    // Show toast
                    ChildrenInlineAdd.toast($form, response.data.message, 'success');

                    // Reload the children tile
                    var $panel   = $('#os-hub-tile-panel-children');
                    var $content = $panel.find('.os-hub-tile-panel__content');
                    $content.removeData('loaded').empty();
                    TileManager.collapseAll();
                    TileManager.open('children', $('#os-hub-tile-btn-children'), $panel);

                    // Refresh counts badge
                    CountsLoader.load(state.currentCustomer.uid, state.currentCustomer.type);
                },
                error: function () {
                    $submitBtn.prop('disabled', false)
                              .html('<span class="dashicons dashicons-saved" aria-hidden="true"></span> حفظ');
                    ChildrenInlineAdd.toast($form, 'خطأ في الاتصال.', 'error');
                },
            });
        },

        toast: function ($form, message, type) {
            var $toast = $form.closest('.os-hub-add-child-wrap').find('.os-hub-add-child-toast');
            var cls    = type === 'success' ? 'os-hub-toast--success' : 'os-hub-toast--error';
            $toast
                .removeClass('os-hub-toast--success os-hub-toast--error')
                .addClass(cls).text(message).stop(true).css({opacity:1})
                .fadeIn(200).delay(3000).fadeOut(400, function () { $(this).empty().removeClass(cls); });
        },
    };

    // ══════════════════════════════════════════════════════════════════════════
    // ProfileEditor — Phase 3: inline edit + activate/deactivate on profile tile
    // ══════════════════════════════════════════════════════════════════════════
    var ProfileEditor = {

        init: function () {
            // ── Edit button: toggle between view and form ──────────────────────
            $(document).on('click', '.os-hub-edit-btn', function () {
                var $wrap  = $(this).closest('.os-hub-profile-wrap');
                ProfileEditor.showForm($wrap);
            });

            // ── Cancel button ──────────────────────────────────────────────
            $(document).on('click', '.os-hub-form-cancel', function () {
                var $wrap = $(this).closest('.os-hub-profile-wrap');
                ProfileEditor.showView($wrap);
            });

            // ── Form submit (save profile) ──────────────────────────────────
            $(document).on('submit', '.os-hub-profile-form', function (e) {
                e.preventDefault();
                var $form = $(this);
                var $wrap = $form.closest('.os-hub-profile-wrap');
                ProfileEditor.saveProfile($form, $wrap);
            });

            // ── Toggle active/inactive ─────────────────────────────────────
            $(document).on('click', '.os-hub-toggle-active-btn', function () {
                var $btn   = $(this);
                var $wrap  = $btn.closest('.os-hub-profile-wrap');
                var uid    = $wrap.data('uid');
                var type   = $wrap.data('type');
                var active = $btn.data('active'); // current state (1=active, 0=inactive)
                var label  = active == 1
                    ? 'هل تريد تعطيل هذا الملف؟'
                    : 'هل تريد تفعيل هذا الملف؟';

                if (!window.confirm(label)) return;

                ProfileEditor.toggleActive($btn, $wrap, uid, type, active);
            });
        },

        // Switch to inline form
        showForm: function ($wrap) {
            $wrap.find('.os-hub-profile-view').slideUp(160);
            $wrap.find('.os-hub-profile-form').slideDown(200);
            $wrap.find('.os-hub-edit-btn').addClass('button-primary');
            $wrap.find('.os-hub-profile-form input:first').focus();
        },

        // Switch back to read-only view
        showView: function ($wrap) {
            $wrap.find('.os-hub-profile-form').slideUp(160);
            $wrap.find('.os-hub-profile-view').slideDown(200);
            $wrap.find('.os-hub-edit-btn').removeClass('button-primary');
            $wrap.find('.os-hub-profile-toast').empty();
        },

        // AJAX: save profile edits
        saveProfile: function ($form, $wrap) {
            var $submitBtn = $form.find('.os-hub-form-save');
            $submitBtn.prop('disabled', true)
                      .html('<span class="os-hub-spinner" aria-hidden="true"></span> جاري...');

            var formData = $form.serializeArray();
            formData.push({ name: 'action', value: 'os_hub_save_profile' });
            formData.push({ name: 'nonce',  value: NONCE });

            $.ajax({
                url:    AJAX_URL,
                method: 'POST',
                data:   formData,
                success: function (response) {
                    $submitBtn.prop('disabled', false)
                              .html('<span class="dashicons dashicons-saved" aria-hidden="true"></span> حفظ التغييرات');

                    if (!response.success) {
                        ProfileEditor.toast($wrap, response.data.message || 'حدث خطأ.', 'error');
                        return;
                    }

                    // Update the customer name in the identity header
                    if (response.data.name) {
                        $('#os-hub-identity-name').text(response.data.name);
                        if (state.currentCustomer) {
                            state.currentCustomer.name = response.data.name;
                            RecentLookups.save(state.currentCustomer);
                        }
                    }

                    ProfileEditor.toast($wrap, response.data.message, 'success');
                    ProfileEditor.showView($wrap);

                    // Bust the tile cache so it reloads next open
                    var $content = $wrap.closest('.os-hub-tile-panel__content');
                    $content.removeData('loaded');
                },
                error: function () {
                    $submitBtn.prop('disabled', false)
                              .html('<span class="dashicons dashicons-saved" aria-hidden="true"></span> حفظ التغييرات');
                    ProfileEditor.toast($wrap, 'خطأ في الاتصال. حاول مجدداً.', 'error');
                },
            });
        },

        // AJAX: toggle active status
        toggleActive: function ($btn, $wrap, uid, type, active) {
            $btn.prop('disabled', true);

            $.ajax({
                url:    AJAX_URL,
                method: 'POST',
                data: {
                    action: 'os_hub_toggle_active',
                    nonce:  NONCE,
                    uid:    uid,
                    type:   type,
                    active: active,
                },
                success: function (response) {
                    $btn.prop('disabled', false);

                    if (!response.success) {
                        ProfileEditor.toast($wrap, response.data.message || 'حدث خطأ.', 'error');
                        return;
                    }

                    var isNowActive = response.data.is_active === 1;

                    // Update button state
                    $btn.data('active', isNowActive ? '1' : '0');
                    $btn.find('.dashicons')
                        .removeClass('dashicons-lock dashicons-unlock')
                        .addClass(isNowActive ? 'dashicons-lock' : 'dashicons-unlock');
                    $btn.contents().filter(function () {
                        return this.nodeType === 3; // text nodes
                    }).last().replaceWith(isNowActive ? 'تعطيل' : 'تفعيل');

                    // Update status badge in the read-only view table
                    var $statusRow = $wrap.find('.os-hub-profile-view tbody tr').last();
                    $statusRow.find('td').html(
                        isNowActive
                            ? '<span class="os-hub-badge os-hub-badge--green">نشط</span>'
                            : '<span class="os-hub-badge os-hub-badge--gray">غير نشط</span>'
                    );

                    ProfileEditor.toast($wrap, response.data.message, 'success');

                    // Bust tile cache
                    $wrap.closest('.os-hub-tile-panel__content').removeData('loaded');
                },
                error: function () {
                    $btn.prop('disabled', false);
                    ProfileEditor.toast($wrap, 'خطأ في الاتصال.', 'error');
                },
            });
        },

        // Show inline toast notification
        toast: function ($wrap, message, type) {
            var $toast = $wrap.find('.os-hub-profile-toast');
            var cls    = type === 'success' ? 'os-hub-toast--success' : 'os-hub-toast--error';
            $toast
                .removeClass('os-hub-toast--success os-hub-toast--error')
                .addClass(cls)
                .text(message)
                .stop(true)
                .css({ opacity: 1 })
                .fadeIn(200)
                .delay(3000)
                .fadeOut(400, function () { $(this).empty().removeClass(cls); });
        },
    };

    // ══════════════════════════════════════════════════════════════════════════
    // Utility
    // ══════════════════════════════════════════════════════════════════════════
    function escHtml(str) {
        return $('<div>').text(str).html();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Init — boot all modules when DOM is ready
    // ══════════════════════════════════════════════════════════════════════════
    $(function () {
        TypeSelector.init();
        RecentLookups.init();
        SearchModule.init();
        TileManager.init();
        Navigation.init();
        ProfileEditor.init();
        // Phase 4
        YearSelector.init();
        TileShortcuts.init();
        ChildrenInlineAdd.init();
        // Phase 5
        TileReloader.init();
        UidCopy.init();

        // Intercept Invoice Quick Action click to open modal inline on customer hub page
        $(document).on('click', '#os-hub-qaction-invoice', function (e) {
            e.preventDefault();
            var customer = state.currentCustomer;
            if (!customer || customer.type !== 'family') return;

            var $familySelect = $('#inv_family_uid');
            if ($familySelect.length) {
                // Pre-select the customer
                $familySelect.empty().append(new Option(customer.name + ' (' + customer.uid + ')', customer.uid, true, true));
                $familySelect.prop('disabled', true);

                // Add hidden input if not exists to ensure it submits when disabled
                var $form = $('#olama-reg-invoice-form');
                var $hidden = $form.find('input[name="family_uid"]');
                if (!$hidden.length) {
                    $hidden = $('<input type="hidden" name="family_uid">');
                    $form.append($hidden);
                }
                $hidden.val(customer.uid);

                // Trigger select2 init
                if (typeof $.fn.select2 !== 'undefined' && !$familySelect.hasClass('select2-hidden-accessible')) {
                    $familySelect.select2({
                        dir: 'rtl',
                        width: '100%',
                        dropdownParent: $('#olama-reg-invoice-modal'),
                        placeholder: 'ابحث عن عائلة باستخدام رقم الملف أو الاسم...',
                        minimumInputLength: 2
                    });
                }

                // Open the modal
                $('#olama-reg-invoice-modal').fadeIn(200);

                // Trigger change so student list loads
                $familySelect.trigger('change');
            }
        });

        // Intercept Agreement Quick Action click to open modal inline on customer hub page
        $(document).on('click', '#os-hub-qaction-agreement', function (e) {
            e.preventDefault();
            var customer = state.currentCustomer;
            if (!customer) return;

            var $payerSelect = $('#os-agr-payer-modal');
            if ($payerSelect.length) {
                var payerType = (customer.type === 'family') ? 'family' : 'customer';
                var payerId = (customer.type === 'family') ? customer.uid : (customer.internal_id || customer.id || '');

                // Reset tabs to default (first tab active, others disabled)
                $('#modal-tab-link-header').trigger('click');
                $('#modal-tab-link-fees, #modal-tab-link-clauses').addClass('os-disabled');

                // Prefill dummy radio buttons
                $('input[name="payer_type_dummy"][value="' + payerType + '"]').prop('checked', true);

                // Pre-select the customer name in select
                $payerSelect.empty().append(new Option(customer.name + ' (' + customer.uid + ')', payerId, true, true));
                $payerSelect.prop('disabled', true);

                var $form = $('#os-form-agreement-header');
                
                // Reset IDs
                $form.find('input[name="id"]').val(0);
                $('#os-agr-fees-table').data('agr-id', 0).attr('data-agr-id', 0);
                $('#os-agr-add-clause').data('agr-id', 0).attr('data-agr-id', 0);
                $form.find('input[name="template_id"]').val(0);

                // Clear dynamic content
                $('#os-agr-fees-table tbody').empty();
                $('#os-agr-clauses-list').empty();
                $('#os-agr-total-label').text('0.000');
                $('#os-agr-new-clause').val('');
                $('#os-btn-save-header').html('<span class="dashicons dashicons-saved"></span> ' + 'حفظ البيانات');

                // Set hidden fields
                $form.find('input[name="payer_type"]').val(payerType);
                $form.find('input[name="payer_id"]').val(payerId);
                $form.find('#os-agr-customer-uid-hidden').val(customer.uid);

                // Fetch participants to populate window.payerChildren
                window.payerChildren = [];
                if (typeof olamaReg !== 'undefined' && olamaReg.ajaxurl) {
                    $.post(olamaReg.ajaxurl, {
                        action: 'olama_reg_agr_get_participants',
                        nonce: olamaReg.nonce,
                        payer_type: payerType,
                        payer_id: payerId
                    }).done(function (res) {
                        if (res.success && res.data.results) {
                            window.payerChildren = res.data.results;
                        }
                    });
                }

                // Initialize datepickers
                if (typeof $.fn.datepicker !== 'undefined') {
                    $('#olama-reg-agreement-modal').find('.olama-reg-datepicker').each(function () {
                        if (!$(this).hasClass('hasDatepicker')) {
                            $(this).datepicker({ dateFormat: 'yy-mm-dd', changeYear: true, changeMonth: true, yearRange: '1970:2050' });
                        }
                    });
                }

                // Initialize select2 on activity nature dropdown if select2 is available
                var $activitySelect = $('#os-agr-activity-modal');
                if (typeof $.fn.select2 !== 'undefined' && !$activitySelect.hasClass('select2-hidden-accessible')) {
                    $activitySelect.select2({
                        dir: 'rtl',
                        width: '100%',
                        dropdownParent: $('#olama-reg-agreement-modal')
                    });
                }

                // Open the modal
                $('#olama-reg-agreement-modal').fadeIn(200);
            }
        });

        // Intercept Payment Quick Action click to open modal inline on customer hub page
        $(document).on('click', '#os-hub-qaction-payment', function (e) {
            e.preventDefault();
            var customer = state.currentCustomer;
            if (!customer || customer.type !== 'family') return;

            var $searchFamily = $('#pay_search_family');
            if ($searchFamily.length) {
                // Pre-populate Select2 with the active customer/family details
                $searchFamily.empty().append(new Option(customer.name + ' (' + customer.uid + ')', customer.uid, true, true));
                $searchFamily.prop('disabled', true);

                // Set family UID in hidden input
                $('#pay_family_uid').val(customer.uid);

                // Trigger change to load family invoices
                $searchFamily.trigger('change');

                // Set date to today
                var today = new Date().toISOString().split('T')[0];
                $('#pay_date').val(today);

                // Show family search wrapper (which contains both family select and invoice select)
                $('#pay_family_search_wrap').show();
                $('#pay_invoice_select_wrap').show();
                $('#pay_invoice_display_wrap').hide();

                // Open the modal
                $('#olama-reg-payment-modal').fadeIn(200);

                // Initialize datepicker
                if (typeof $.fn.datepicker !== 'undefined') {
                    $('#olama-reg-payment-modal').find('.olama-reg-datepicker, .os-datepicker').each(function () {
                        if (!$(this).hasClass('hasDatepicker')) {
                            $(this).datepicker({ dateFormat: 'yy-mm-dd', changeYear: true, changeMonth: true, yearRange: '1970:2050' });
                        }
                    });
                }
            }
        });

        // Intercept Settlement Quick Action click to open modal inline on customer hub page
        $(document).on('click', '#os-hub-qaction-settlement', function (e) {
            e.preventDefault();
            var customer = state.currentCustomer;
            if (!customer || customer.type !== 'family') return;

            var $familySelect = $('#settlement_family_search');
            if ($familySelect.length) {
                // Pre-select the customer
                $familySelect.empty().append(new Option(customer.name + ' (' + customer.uid + ')', customer.uid, true, true));
                $familySelect.prop('disabled', true);

                // Set family UID in hidden input
                $('#settlement_family_id').val(customer.uid);

                // Initialize Select2 if not done
                if (typeof $.fn.select2 !== 'undefined' && !$familySelect.hasClass('select2-hidden-accessible')) {
                    $familySelect.select2({
                        dir: 'rtl',
                        width: '100%',
                        dropdownParent: $('#olama-reg-settlement-modal'),
                        placeholder: 'ابحث بالاسم أو رقم العائلة...',
                        minimumInputLength: 2
                    });
                }

                // Open the modal
                $('#olama-reg-settlement-modal').fadeIn(200);
            }
        });

        // Intercept Custom Payment Quick Action click to open modal inline on customer hub page
        $(document).on('click', '#os-hub-qaction-custom-payment', function (e) {
            e.preventDefault();
            var customer = state.currentCustomer;
            if (!customer) return;

            var isFamily = (customer.type === 'family');
            
            // Clear lists, loading, and messages
            $('#cp_students_list').empty();
            $('#cp_ext_students_list').empty();
            $('#cp_total_display').text('0.00 د.أ');
            $('#cp_amount').val('');
            $('#cp_discount').val('0.00');
            $('#cp_service_type').val('');
            $('#cp_fee_template').val('');
            $('#cp_response_msg').hide();
            $('#cp_loading').hide();

            // Set inputs
            if (isFamily) {
                $('#cp_type_internal').prop('checked', true);
                $('#cp_internal_customer_wrap').show();
                $('#cp_external_customer_wrap').hide();

                // Prefill and lock select
                var $familySelect = $('#cp_family_search');
                $familySelect.empty().append(new Option(customer.name + ' (' + customer.uid + ')', customer.uid, true, true));
                $familySelect.prop('disabled', true);
                $('#cp_family_uid').val(customer.uid);
                $('#cp_customer_uid').val('');

                // Trigger change to load internal students!
                $familySelect.trigger('change');
            } else {
                $('#cp_type_external').prop('checked', true);
                $('#cp_internal_customer_wrap').hide();
                $('#cp_external_customer_wrap').show();

                // Prefill and lock select
                var $extSelect = $('#cp_ext_customer_search');
                var payerId = customer.internal_id || customer.id || '';
                $extSelect.empty().append(new Option(customer.name + ' (' + customer.uid + ')', payerId, true, true));
                $extSelect.prop('disabled', true);
                $('#cp_ext_customer_id').val(payerId);
                $('#cp_customer_uid').val(customer.uid);

                // Trigger change to load external children!
                $extSelect.trigger('change');
            }

            // Open the modal!
            $('#olama-reg-custom-payment-modal').fadeIn(200);
        });

        // Intercept Custom Payment form submit to reload dashboard page contextually on success
        $(document).on('submit', '#olama-reg-custom-payment-form', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation(); // Prevents olama-reg.js submit handler from running!
            
            const isExternal = $('input[name="customer_type"]:checked').val() === 'external';
            const isDirect = $('#cp_ext_pay_customer_direct').is(':checked');
            const internalCount = $('.cp-student-check:checked').length;
            const externalCount = isDirect ? 1 : $('.cp-ext-student-check:checked').length;

            if (!isExternal && internalCount === 0) { $('#cp_students_error').show(); return; }
            if (isExternal && externalCount === 0) { alert('يجب اختيار ابن واحد على الأقل، أو تفعيل "دفعة مباشرة للعميل".'); return; }

            const $form = $(this);
            const $btn = $('#cp_submit_btn');
            const $loading = $('#cp_loading');
            const $msg = $('#cp_response_msg');

            $btn.prop('disabled', true); $loading.show(); $msg.hide();

            let formData = $form.serialize();

            if (isExternal) {
                const extCustomerId = parseInt($('#cp_ext_customer_id').val()) || 0;
                if (!extCustomerId) {
                    $msg.html('<div class="notice notice-error inline" style="padding:10px;"><p>يرجى تحديد عميل خارجي.</p></div>').fadeIn();
                    $btn.prop('disabled', false); $loading.hide(); return;
                }
                formData += '&is_external_customer=1';
                if (isDirect) {
                    formData = formData.replace(/child_ids%5B%5D=[^&]*/g, '').replace(/child_ids%5B%5D/g, '');
                }
            }

            $.post(olamaReg.ajaxurl, 'action=olama_reg_save_custom_payment&nonce=' + olamaReg.nonce + '&' + formData)
                .done(res => {
                    if (res.success) {
                        $msg.html('<div class="notice notice-success inline" style="padding:10px;"><p>' + res.data.message + '</p></div>').fadeIn();
                        
                        // Open receipt in new tab
                        const invoiceId = res.data.payment_id || (res.data.invoice_ids && res.data.invoice_ids[0]);
                        if (invoiceId) {
                            const url = new URL(window.location.href);
                            url.searchParams.set('page', 'olama-registration-payments');
                            url.searchParams.set('action', 'print_receipt');
                            url.searchParams.set('id', invoiceId);
                            window.open(url.toString(), '_blank');
                        }

                        // Reload dashboard contextually
                        setTimeout(() => {
                            const familyUid = $('#cp_family_uid').val();
                            const customerUid = $('#cp_customer_uid').val();
                            let targetUrl = window.location.pathname + '?page=olama-registration';
                            if (familyUid) {
                                targetUrl += '&family_uid=' + encodeURIComponent(familyUid);
                            } else if (customerUid) {
                                targetUrl += '&customer_uid=' + encodeURIComponent(customerUid);
                            }
                            window.location.href = targetUrl;
                        }, 1000);
                    } else {
                        $msg.html('<div class="notice notice-error inline" style="padding:10px;"><p>' + (res.data?.message || 'حدث خطأ.') + '</p></div>').fadeIn();
                    }
                })
                .always(() => { $btn.prop('disabled', false); $loading.hide(); });
        });

        // Phase 6: Preload customer/family if passed in URL
        if (HUB_DATA.preloadCustomer) {
            state.currentType = HUB_DATA.preloadCustomer.type;
            TypeBadge.update(state.currentType);
            SearchModule.reset();
            RecentLookups.render(state.currentType);
            CustomerHub.loadCustomer(HUB_DATA.preloadCustomer);
        }
    });

}(jQuery));
