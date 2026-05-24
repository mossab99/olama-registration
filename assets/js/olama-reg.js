/* global olamaReg */
(function ($) {
    'use strict';

    const R = olamaReg; // config injected by wp_localize_script

    // ── Utilities ────────────────────────────────────────────────────────────

    function showNotice(msg, isError = false) {
        const $n = $('#olama-reg-notice');
        $n.removeClass('olama-reg-notice--error')
          .text(msg)
          .show()
          .css('display', 'block');
        if (isError) $n.addClass('olama-reg-notice--error');
        clearTimeout(window._regNoticeTimer);
        window._regNoticeTimer = setTimeout(() => $n.fadeOut(), 6000);
    }

    function setLoading($btn, loading) {
        if (loading) {
            $btn.prop('disabled', true).addClass('olama-reg-saving');
            $btn.data('orig-text', $btn.text());
            $btn.text(R.strings.saving);
        } else {
            $btn.prop('disabled', false).removeClass('olama-reg-saving');
            if ($btn.data('orig-text')) $btn.text($btn.data('orig-text'));
        }
    }

    function ajax(action, data, $btn) {
        if ($btn) setLoading($btn, true);
        return $.post(R.ajaxurl, { action, nonce: R.nonce, ...data })
            .always(() => { if ($btn) setLoading($btn, false); });
    }

    // ── Tab Navigation ────────────────────────────────────────────────────────

    $(document).on('click', '.olama-reg-tab', function () {
        if ($(this).prop('disabled')) return;
        const tab = $(this).data('tab');
        $('.olama-reg-tab').removeClass('active');
        $(this).addClass('active');
        $('.olama-reg-tab-pane').removeClass('active').hide();
        $('#tab-' + tab).addClass('active').show();

        if (tab === 'financial' && typeof loadFamilyBilling === 'function') {
            loadFamilyBilling();
        }

        // Update URL hash
        if (history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            history.replaceState(null, '', url.toString());
        }
    });

    // Show correct pane on load
    (function () {
        const $active = $('.olama-reg-tab.active');
        if ($active.length) {
            const tab = $active.data('tab');
            $('.olama-reg-tab-pane').hide();
            $('#tab-' + tab).show().addClass('active');
        }
    })();

    // ── Sub-tabs ──────────────────────────────────────────────────────────────

    $(document).on('click', '.olama-reg-sub-tab', function () {
        const subtab = $(this).data('subtab');
        const $container = $(this).closest('.olama-reg-student-body');
        $container.find('.olama-reg-sub-tab').removeClass('active');
        $(this).addClass('active');
        $container.find('.olama-reg-sub-pane').hide();
        $('#' + subtab).show();
    });

    // ── Save Family ───────────────────────────────────────────────────────────

    $(document).on('click', '#olama-reg-save-family', function () {
        const $btn = $(this);
        const data = { family_uid: $('#olama-reg-family-uid').val() };

        // Collect all family form fields
        $('[name]', '#tab-family').each(function () {
            const el = this;
            const name = $(el).attr('name');
            if (el.type === 'checkbox') {
                if (el.checked) data[name] = $(el).val();
            } else {
                data[name] = $(el).val();
            }
        });

        ajax('olama_reg_save_family', data, $btn)
            .done(res => {
                if (res.success) {
                    showNotice(R.strings.saved);
                    if (!data.family_uid && res.data.family_uid) {
                        // Redirect to the newly created family
                        const newUid = res.data.family_uid;
                        showNotice(R.strings.familyCreated + newUid);
                        setTimeout(() => {
                            window.location.href = `${window.location.pathname}?page=olama-registration&action=edit&family_uid=${newUid}`;
                        }, 1200);
                    }
                } else {
                    showNotice(res.data?.message || R.strings.error, true);
                }
            })
            .fail(() => showNotice(R.strings.error, true));
    });

    // ── Student Accordion Toggle ──────────────────────────────────────────────

    $(document).on('click', '.olama-reg-student-row-header', function () {
        const $row = $(this).closest('.olama-reg-student-row');
        const $body = $row.find('.olama-reg-student-body');
        if ($row.hasClass('open')) {
            $row.removeClass('open');
            $body.slideUp(200);
            $(this).find('.olama-reg-student-toggle').attr('aria-expanded', 'false');
        } else {
            $row.addClass('open');
            $body.slideDown(200);
            $(this).find('.olama-reg-student-toggle').attr('aria-expanded', 'true');
            initDatepickers($body);
            initSelect2($body);
        }
    });

    // Prevent toggle firing when clicking buttons inside header
    $(document).on('click', '.olama-reg-student-row-header button', function (e) {
        e.stopPropagation();
    });

    // ── Add Student ───────────────────────────────────────────────────────────

    $(document).on('click', '#olama-reg-add-student', function () {
        const familyUid = $(this).data('family-uid');
        const $btn      = $(this);

        ajax('olama_reg_save_student', { family_uid: familyUid }, $btn)
            .done(res => {
                if (res.success) {
                    showNotice(R.strings.studentAdded);
                    // Redirect to show the new student expanded
                    const uid      = res.data.student_uid;
                    const url      = new URL(window.location.href);
                    url.searchParams.set('tab', 'students');
                    url.searchParams.set('student_uid', uid);
                    window.location.href = url.toString();
                } else {
                    showNotice(res.data?.message || R.strings.error, true);
                }
            })
            .fail(() => showNotice(R.strings.error, true));
    });

    // ── Save Student ──────────────────────────────────────────────────────────

    $(document).on('click', '.olama-reg-save-student', function () {
        const $btn       = $(this);
        const studentUid = $btn.data('student-uid');
        const familyUid  = $btn.data('family-uid');
        const $row       = $('#student-' + studentUid);

        const data = { student_uid: studentUid, family_uid: familyUid };

        // Collect student basic fields
        $row.find('.s-field').each(function () {
            const el   = this;
            const name = $(el).data('field') || $(el).attr('name');
            if (!name) return;
            if (el.type === 'radio') {
                if (el.checked) data[name] = el.value;
            } else if (el.type === 'checkbox') {
                if (el.checked) data[name] = el.value;
            } else {
                data[name] = $(el).val();
            }
        });

        ajax('olama_reg_save_student', data, $btn)
            .done(res => {
                if (res.success) {
                    showNotice(R.strings.saved);
                    // Update displayed name
                    const s = res.data.student;
                    if (s && s.student_name) {
                        $row.find('.olama-reg-student-name').text(s.student_name);
                    }
                } else {
                    showNotice(res.data?.message || R.strings.error, true);
                }
            })
            .fail(() => showNotice(R.strings.error, true));
    });

    // ── Save Transport ────────────────────────────────────────────────────────

    $(document).on('click', '.olama-reg-save-transport', function () {
        const $btn       = $(this);
        const studentUid = $btn.data('student-uid');
        const $pane      = $btn.closest('[id^="transport-"]');
        const data       = { student_uid: studentUid };

        $pane.find('.st-field').each(function () {
            const el   = this;
            const name = $(el).attr('name');
            if (!name) return;
            if (el.type === 'checkbox') {
                if (el.checked) data[name] = 1;
            } else {
                data[name] = $(el).val();
            }
        });

        ajax('olama_reg_save_transport', data, $btn)
            .done(res => {
                if (res.success) showNotice(R.strings.saved);
                else showNotice(res.data?.message || R.strings.error, true);
            })
            .fail(() => showNotice(R.strings.error, true));
    });

    // ── Blacklist Checkbox Toggle ─────────────────────────────────────────────

    $(document).on('change', '[name="blacklist"]', function () {
        const $reason = $(this).closest('.olama-reg-field').find('.olama-reg-blacklist-reason');
        if ($(this).is(':checked')) $reason.show();
        else $reason.hide();
    });

    // ── Photo Upload ──────────────────────────────────────────────────────────

    let mediaUploader;
    $(document).on('click', '.olama-reg-upload-photo', function (e) {
        e.preventDefault();
        const $btn      = $(this);
        const $wrap     = $btn.closest('.olama-reg-photo-upload');
        const $preview  = $wrap.find('.olama-reg-photo-preview');
        const $input    = $wrap.find('[name="photo_attachment_id"]');

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: R.strings.selectPhoto,
            button: { text: R.strings.selectPhoto },
            multiple: false,
            library: { type: 'image' },
        });

        mediaUploader.on('select', function () {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $preview.attr('src', attachment.url);
            $input.val(attachment.id);
        });

        mediaUploader.open();
    });

    // ── Deactivate Family Button ──────────────────────────────────────────────

    $(document).on('click', '.olama-reg-deactivate', function () {
        if (!confirm(R.strings.confirmDeact)) return;
        const uid  = $(this).data('uid');
        const $btn = $(this);
        ajax('olama_reg_soft_delete_family', { family_uid: uid }, $btn)
            .done(res => {
                if (res.success) {
                    $btn.closest('tr').find('.olama-reg-badge--active')
                        .removeClass('olama-reg-badge--active')
                        .addClass('olama-reg-badge--inactive')
                        .text($btn.data('label-inactive') || 'غير نشط');
                    showNotice(res.data.message);
                } else {
                    showNotice(res.data?.message || R.strings.error, true);
                }
            });
    });

    // ── Financial Tab ─────────────────────────────────────────────────────────

    // Reload financial data when year changes
    $(document).on('change', '#olama-reg-fin-year', function () {
        const familyUid = $(this).data('family-uid');
        const yearId    = $(this).val();
        ajax('olama_reg_get_financial', { family_uid: familyUid, academic_year_id: yearId })
            .done(res => {
                if (res.success) {
                    renderFinTable(res.data.rows, res.data.totals, familyUid, yearId);
                }
            });
    });

    function renderFinTable(rows, totals, familyUid, yearId) {
        const $body = $('#olama-reg-fin-body');
        $body.empty();
        rows.forEach(row => $body.append(buildFinRow(row)));
        updateTotals(totals);
        initDatepickers($body);
    }

    function buildFinRow(row) {
        const bal = ((parseFloat(row.amount_due) || 0) - (parseFloat(row.amount_paid) || 0) + (parseFloat(row.payments_revolving) || 0)).toFixed(2);
        return `
        <tr data-fin-id="${row.id}" class="olama-reg-fin-row">
            <td><input type="text" name="entitlement_date" value="${row.entitlement_date || ''}" class="olama-reg-inline-input olama-reg-datepicker"></td>
            <td><input type="text" name="calculation_method" value="${row.calculation_method || ''}" class="olama-reg-inline-input"></td>
            <td><input type="number" name="percentage" value="${row.percentage || ''}" class="olama-reg-inline-input" step="0.01"></td>
            <td><input type="number" name="amount_due" value="${row.amount_due || '0.00'}" class="olama-reg-inline-input olama-reg-fin-calc" step="0.01"></td>
            <td><input type="number" name="amount_paid" value="${row.amount_paid || '0.00'}" class="olama-reg-inline-input olama-reg-fin-calc" step="0.01"></td>
            <td><input type="number" name="payments_revolving" value="${row.payments_revolving || '0.00'}" class="olama-reg-inline-input olama-reg-fin-calc" step="0.01"></td>
            <td class="olama-reg-fin-balance olama-reg-balance-cell">${bal}</td>
            <td><input type="text" name="payment_reference" value="${row.payment_reference || ''}" class="olama-reg-inline-input"></td>
            <td><input type="text" name="fin_notes" value="${row.fin_notes || ''}" class="olama-reg-inline-input"></td>
            <td><button type="button" class="button button-small olama-reg-save-fin-row">حفظ</button></td>
            <td><button type="button" class="button button-small olama-reg-delete-fin-row" style="color:#c0392b">حذف</button></td>
        </tr>`;
    }

    // Add empty financial row
    $(document).on('click', '#olama-reg-add-fin-row', function () {
        const emptyRow = { id: 0, entitlement_date: '', calculation_method: '', percentage: '', amount_due: '0.00', amount_paid: '0.00', payments_revolving: '0.00', payment_reference: '', fin_notes: '' };
        const $row = $(buildFinRow(emptyRow));
        $('#olama-reg-fin-body').append($row);
        initDatepickers($row);
        $row[0].scrollIntoView({ behavior: 'smooth' });
    });

    // Live balance calculation
    $(document).on('input', '.olama-reg-fin-calc', function () {
        const $tr      = $(this).closest('tr');
        const due      = parseFloat($tr.find('[name="amount_due"]').val()) || 0;
        const paid     = parseFloat($tr.find('[name="amount_paid"]').val()) || 0;
        const revolving= parseFloat($tr.find('[name="payments_revolving"]').val()) || 0;
        $tr.find('.olama-reg-balance-cell').text((due - paid + revolving).toFixed(2));
    });

    // Save financial row
    $(document).on('click', '.olama-reg-save-fin-row', function () {
        const $btn      = $(this);
        const $tr       = $btn.closest('tr');
        const familyUid = $('#olama-reg-family-uid').val();
        const yearId    = $('#olama-reg-fin-year').val() || 0;
        const id        = parseInt($tr.data('fin-id')) || 0;

        const data = {
            family_uid:         familyUid,
            academic_year_id:   yearId,
            id,
            entitlement_date:   $tr.find('[name="entitlement_date"]').val(),
            calculation_method: $tr.find('[name="calculation_method"]').val(),
            percentage:         $tr.find('[name="percentage"]').val(),
            amount_due:         $tr.find('[name="amount_due"]').val(),
            amount_paid:        $tr.find('[name="amount_paid"]').val(),
            payments_revolving: $tr.find('[name="payments_revolving"]').val(),
            payment_reference:  $tr.find('[name="payment_reference"]').val(),
            fin_notes:          $tr.find('[name="fin_notes"]').val(),
        };

        ajax('olama_reg_save_financial_row', data, $btn)
            .done(res => {
                if (res.success) {
                    if (!id && res.data.id) $tr.attr('data-fin-id', res.data.id);
                    showNotice(R.strings.saved);
                    if (res.data.totals) updateTotals(res.data.totals);
                } else {
                    showNotice(res.data?.message || R.strings.error, true);
                }
            });
    });

    // Delete financial row
    $(document).on('click', '.olama-reg-delete-fin-row', function () {
        if (!confirm(R.strings.confirmDelete)) return;
        const $tr       = $(this).closest('tr');
        const id        = parseInt($tr.data('fin-id')) || 0;
        const familyUid = $('#olama-reg-family-uid').val();
        const yearId    = $('#olama-reg-fin-year').val() || 0;

        if (!id) { $tr.remove(); return; }

        ajax('olama_reg_delete_financial_row', { id, family_uid: familyUid, academic_year_id: yearId })
            .done(res => {
                if (res.success) {
                    $tr.remove();
                    showNotice(R.strings.saved);
                    if (res.data.totals) updateTotals(res.data.totals);
                }
            });
    });

    function updateTotals(t) {
        $('#total-due').text(parseFloat(t.total_due || 0).toFixed(2));
        $('#total-paid').text(parseFloat(t.total_paid || 0).toFixed(2));
        $('#total-revolving').text(parseFloat(t.total_revolving || 0).toFixed(2));
        $('#total-balance').text(parseFloat(t.total_balance || 0).toFixed(2));
    }

    // ── Academic History ──────────────────────────────────────────────────────

    $(document).on('click', '.olama-reg-add-hist-row', function () {
        const studentUid = $(this).data('student-uid');
        const $tbody = $(`.olama-reg-history-body[data-student-uid="${studentUid}"]`);
        $tbody.append(`
        <tr data-hist-id="0">
            <td><input type="text" name="academic_year" class="olama-reg-inline-input"></td>
            <td><input type="text" name="school_name" class="olama-reg-inline-input"></td>
            <td><input type="text" name="grade" class="olama-reg-inline-input"></td>
            <td><input type="text" name="branch" class="olama-reg-inline-input"></td>
            <td><input type="text" name="section" class="olama-reg-inline-input"></td>
            <td><input type="text" name="registration_date" class="olama-reg-inline-input olama-reg-datepicker"></td>
            <td><input type="text" name="withdrawal_date" class="olama-reg-inline-input olama-reg-datepicker"></td>
            <td><input type="text" name="student_status" class="olama-reg-inline-input"></td>
            <td><input type="checkbox" name="is_current" value="1"></td>
            <td><button type="button" class="button button-small olama-reg-save-hist-row">حفظ</button></td>
            <td><button type="button" class="button button-small olama-reg-delete-hist-row" style="color:red">حذف</button></td>
        </tr>`);
        initDatepickers($tbody);
    });

    $(document).on('click', '.olama-reg-save-hist-row', function () {
        const $btn  = $(this);
        const $tr   = $btn.closest('tr');
        const $tbody= $tr.closest('.olama-reg-history-body');
        const studentUid = $tbody.data('student-uid');
        const id    = parseInt($tr.data('hist-id')) || 0;

        const data = {
            student_uid: studentUid,
            id,
            academic_year:     $tr.find('[name="academic_year"]').val(),
            school_name:       $tr.find('[name="school_name"]').val(),
            grade:             $tr.find('[name="grade"]').val(),
            branch:            $tr.find('[name="branch"]').val(),
            section:           $tr.find('[name="section"]').val(),
            registration_date: $tr.find('[name="registration_date"]').val(),
            withdrawal_date:   $tr.find('[name="withdrawal_date"]').val(),
            student_status:    $tr.find('[name="student_status"]').val(),
            is_current:        $tr.find('[name="is_current"]').is(':checked') ? 1 : 0,
        };

        ajax('olama_reg_save_academic_history', data, $btn)
            .done(res => {
                if (res.success) {
                    if (!id && res.data.id) $tr.attr('data-hist-id', res.data.id);
                    showNotice(R.strings.saved);
                } else showNotice(res.data?.message || R.strings.error, true);
            });
    });

    $(document).on('click', '.olama-reg-delete-hist-row', function () {
        if (!confirm(R.strings.confirmDelete)) return;
        const $tr = $(this).closest('tr');
        const id  = parseInt($tr.data('hist-id')) || 0;
        if (!id) { $tr.remove(); return; }
        ajax('olama_reg_delete_history_row', { id })
            .done(res => { if (res.success) $tr.remove(); });
    });
    // ── Billing - Fee Templates ──────────────────────────────────────────────

    function calculateFeeTotals() {
        let total = 0;
        $('.olama-reg-fee-amount-input').each(function () {
            total += parseFloat($(this).val()) || 0;
        });
        $('#olama-reg-fee-total-label').text(total.toFixed(2));
    }

    function reindexFeeItems() {
        const $tbody = $('#olama-reg-fee-items-table tbody');
        const $rows = $tbody.find('tr');
        $rows.each(function (idx) {
            $(this).find('input[name*="[description]"]').attr('name', `items[${idx}][description]`);
            $(this).find('input[name*="[amount]"]').attr('name', `items[${idx}][amount]`);
        });
        calculateFeeTotals();
    }

    $(document).on('click', '#olama-reg-add-fee-row-btn', function () {
        const $tbody = $('#olama-reg-fee-items-table tbody');
        const newRow = `
        <tr>
            <td>
                <input type="text" name="items[temp][description]" class="olama-reg-inline-input" required placeholder="مثال: رسوم التسجيل، رسوم الباص...">
            </td>
            <td>
                <input type="number" step="0.01" name="items[temp][amount]" value="0.00" class="olama-reg-inline-input olama-reg-fee-amount-input" required>
            </td>
            <td>
                <button type="button" class="button button-small olama-reg-remove-fee-row-btn" style="color:#c62828;">x</button>
            </td>
        </tr>`;
        $tbody.append(newRow);
        reindexFeeItems();
    });

    $(document).on('click', '.olama-reg-remove-fee-row-btn', function () {
        const $tbody = $('#olama-reg-fee-items-table tbody');
        if ($tbody.find('tr').length > 1) {
            $(this).closest('tr').remove();
            reindexFeeItems();
        } else {
            alert('يجب أن يحتوي نموذج الرسوم على بند واحد على الأقل.');
        }
    });

    $(document).on('input change', '.olama-reg-fee-amount-input', function () {
        calculateFeeTotals();
    });

    $(document).on('submit', '#olama-reg-fee-template-form', function (e) {
        e.preventDefault();
        const $btn = $('#olama-reg-save-fee-template-btn');
        const formData = {};
        $(this).find('[name]').each(function () {
            formData[this.name] = $(this).val();
        });

        ajax('olama_reg_save_fee_template', formData, $btn)
            .done(res => {
                if (res.success) {
                    showNotice(R.strings.saved);
                    setTimeout(() => {
                        window.location.href = R.ajaxurl.replace('admin-ajax.php', 'admin.php') + '?page=olama-registration-fees';
                    }, 1000);
                } else {
                    showNotice(res.data?.message || R.strings.error, true);
                }
            })
            .fail(() => showNotice(R.strings.error, true));
    });

    $(document).on('click', '.olama-reg-delete-fee-template-btn', function () {
        if (!confirm(R.strings.confirmDelete)) return;
        const $btn = $(this);
        const id = $btn.data('id');
        const $tr = $btn.closest('tr');
        ajax('olama_reg_delete_fee_template', { id }, $btn)
            .done(res => {
                if (res.success) {
                    $tr.fadeOut(300, function () { $(this).remove(); });
                    showNotice(R.strings.saved);
                } else {
                    showNotice(res.data?.message || R.strings.error, true);
                }
            });
    });

    // ── Billing - Invoices & Modal ───────────────────────────────────────────

    function calculateInvoiceTotals() {
        let subtotal = 0;
        $('#olama-reg-invoice-items-table tbody tr').not('.olama-reg-empty-items-row').each(function () {
            const qty = parseFloat($(this).find('.inv-item-qty').val()) || 0;
            const price = parseFloat($(this).find('.inv-item-price').val()) || 0;
            const lineTotal = qty * price;
            $(this).find('.inv-item-line-total').text(lineTotal.toFixed(2));
            subtotal += lineTotal;
        });

        const discount = parseFloat($('#inv-discount-input').val()) || 0;
        const grandTotal = Math.max(0, subtotal - discount);

        $('#inv-subtotal-label').text(subtotal.toFixed(2));
        $('#inv-grand-total-label').text(grandTotal.toFixed(2));
    }

    function reindexInvoiceItems() {
        const $tbody = $('#olama-reg-invoice-items-table tbody');
        const $rows = $tbody.find('tr').not('.olama-reg-empty-items-row');
        if ($rows.length === 0) {
            $tbody.find('.olama-reg-empty-items-row').show();
        } else {
            $tbody.find('.olama-reg-empty-items-row').hide();
            $rows.each(function (idx) {
                $(this).find('.inv-item-desc').attr('name', `items[${idx}][description]`);
                $(this).find('.inv-item-qty').attr('name', `items[${idx}][quantity]`);
                $(this).find('.inv-item-price').attr('name', `items[${idx}][unit_price]`);
            });
        }
        calculateInvoiceTotals();
    }

    $(document).on('click', '#olama-reg-open-invoice-modal-btn', function () {
        $('#olama-reg-invoice-modal').fadeIn(200);

        // Select2 for family select with AJAX search
        if (typeof $.fn.select2 !== 'undefined' && $('#inv_family_uid').length && !$('#inv_family_uid').hasClass('select2-hidden-accessible')) {
            $('#inv_family_uid').select2({
                dir: 'rtl',
                width: '100%',
                dropdownParent: $('#olama-reg-invoice-modal'),
                ajax: {
                    url: R.ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    type: 'POST',
                    data: function (params) {
                        return {
                            action: 'olama_reg_search',
                            nonce: R.nonce,
                            q: params.term
                        };
                    },
                    processResults: function (data) {
                        if (data.success && data.data.families) {
                            return {
                                results: data.data.families.map(f => {
                                    const name1 = f.father_first_name || '';
                                    const name2 = f.father_family_name || '';
                                    const fullName = (name1 + ' ' + name2).trim() || 'بدون اسم';
                                    return {
                                        id: f.family_uid,
                                        text: `${fullName} (${f.family_uid})`
                                    };
                                })
                            };
                        }
                        return { results: [] };
                    },
                    cache: true
                },
                placeholder: 'ابحث عن عائلة باستخدام رقم الملف أو الاسم...',
                minimumInputLength: 2
            });
        }
    });

    $(document).on('click', '.olama-reg-modal-close', function () {
        $('#olama-reg-invoice-modal').fadeOut(200);
    });

    $(document).on('change', '#inv_family_uid', function () {
        const familyUid = $(this).val();
        const $studentSelect = $('#inv_student_uid');
        $studentSelect.empty().append('<option value="">فاتورة عامة للعائلة</option>');
        if (!familyUid) return;

        ajax('olama_reg_get_family', { family_uid: familyUid })
            .done(res => {
                if (res.success && res.data.students) {
                    res.data.students.forEach(s => {
                        $studentSelect.append(`<option value="${s.student_uid}">${s.student_name} (${s.student_uid})</option>`);
                    });
                }
            });
    });

    $(document).on('change', '#inv_fee_template_id', function () {
        const $opt = $(this).find('option:selected');
        const inst = parseInt($opt.data('inst')) || 1;
        const itemsRaw = $opt.data('items');

        $('#inv_installments').val(inst);

        const $tbody = $('#olama-reg-invoice-items-table tbody');
        $tbody.find('tr').not('.olama-reg-empty-items-row').remove();

        let items = [];
        if (typeof itemsRaw === 'string') {
            try { items = JSON.parse(itemsRaw); } catch(e) {}
        } else if (Array.isArray(itemsRaw)) {
            items = itemsRaw;
        }

        if (items.length) {
            items.forEach((item, idx) => {
                const row = `
                <tr>
                    <td>
                        <input type="text" name="items[${idx}][description]" value="${item.description}" class="olama-reg-inline-input inv-item-desc" required>
                    </td>
                    <td>
                        <input type="number" name="items[${idx}][quantity]" value="1" min="1" class="olama-reg-inline-input inv-item-qty" style="width:70px; text-align:center;" required>
                    </td>
                    <td>
                        <input type="number" step="0.01" name="items[${idx}][unit_price]" value="${parseFloat(item.amount).toFixed(2)}" class="olama-reg-inline-input inv-item-price" style="width:110px;" required>
                    </td>
                    <td>
                        <span class="inv-item-line-total" style="font-weight:700;">${parseFloat(item.amount).toFixed(2)}</span>
                    </td>
                    <td>
                        <button type="button" class="button button-small inv-remove-item-row-btn" style="color:#c62828;">x</button>
                    </td>
                </tr>`;
                $tbody.append(row);
            });
        }

        reindexInvoiceItems();
    });

    $(document).on('click', '#inv-add-item-row-btn', function () {
        const $tbody = $('#olama-reg-invoice-items-table tbody');
        const idx = $tbody.find('tr').not('.olama-reg-empty-items-row').length;
        const row = `
        <tr>
            <td>
                <input type="text" name="items[${idx}][description]" class="olama-reg-inline-input inv-item-desc" placeholder="بند رسوم مخصص..." required>
            </td>
            <td>
                <input type="number" name="items[${idx}][quantity]" value="1" min="1" class="olama-reg-inline-input inv-item-qty" style="width:70px; text-align:center;" required>
            </td>
            <td>
                <input type="number" step="0.01" name="items[${idx}][unit_price]" value="0.00" class="olama-reg-inline-input inv-item-price" style="width:110px;" required>
            </td>
            <td>
                <span class="inv-item-line-total" style="font-weight:700;">0.00</span>
            </td>
            <td>
                <button type="button" class="button button-small inv-remove-item-row-btn" style="color:#c62828;">x</button>
            </td>
        </tr>`;
        $tbody.append(row);
        reindexInvoiceItems();
    });

    $(document).on('click', '.inv-remove-item-row-btn', function () {
        $(this).closest('tr').remove();
        reindexInvoiceItems();
    });

    $(document).on('input change', '.inv-item-qty, .inv-item-price', function () {
        calculateInvoiceTotals();
    });

    $(document).on('input change', '#inv-discount-input', function () {
        calculateInvoiceTotals();
    });

    $(document).on('submit', '#olama-reg-invoice-form', function (e) {
        e.preventDefault();
        const $tbody = $('#olama-reg-invoice-items-table tbody');
        if ($tbody.find('tr').not('.olama-reg-empty-items-row').length === 0) {
            alert('يرجى إضافة بند واحد على الأقل للفاتورة.');
            return;
        }

        const $btn = $('#olama-reg-save-invoice-btn');
        const formData = {};
        $(this).find('[name]').each(function () {
            formData[this.name] = $(this).val();
        });

        ajax('olama_reg_create_invoice', formData, $btn)
            .done(res => {
                if (res.success) {
                    showNotice(R.strings.saved);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotice(res.data?.message || R.strings.error, true);
                }
            })
            .fail(() => showNotice(R.strings.error, true));
    });

    // ── Billing - Cancel & Reverse ──────────────────────────────────────────

    $(document).on('click', '.olama-reg-cancel-invoice-btn', function (e) {
        e.preventDefault();
        if (!confirm('هل أنت متأكد من إلغاء هذه الفاتورة؟ لا يمكن التراجع عن هذا الإجراء.')) return;

        const id = $(this).data('id');
        const $btn = $(this);

        ajax('olama_reg_cancel_invoice', { id }, $btn)
            .done(res => {
                if (res.success) {
                    showNotice(res.data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotice(res.data?.message || R.strings.error, true);
                }
            })
            .fail(() => showNotice(R.strings.error, true));
    });

    $(document).on('click', '.olama-reg-reverse-payment-btn', function (e) {
        e.preventDefault();
        const notes = prompt('الرجاء إدخال سبب عكس السند (اختياري):', 'عكس سند بالخطأ');
        if (notes === null) return; // User cancelled prompt

        const id = $(this).data('id');
        const $btn = $(this);

        ajax('olama_reg_reverse_payment', { id, notes }, $btn)
            .done(res => {
                if (res.success) {
                    showNotice(res.data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotice(res.data?.message || R.strings.error, true);
                }
            })
            .fail(() => showNotice(R.strings.error, true));
    });

    // ── Billing - Invoice Details Drawer ─────────────────────────────────────

    $(document).on('click', '.olama-reg-view-invoice-btn', function () {
        const id = $(this).data('id');
        const $btn = $(this);

        ajax('olama_reg_get_invoice', { id }, $btn)
            .done(res => {
                if (res.success && res.data.invoice) {
                    const inv = res.data.invoice;

                    $('#drawer-invoice-number').text(inv.invoice_number);
                    $('#drawer-total-val').text(parseFloat(inv.total).toFixed(2) + ' د.أ');
                    $('#drawer-discount-val').text(parseFloat(inv.discount || 0).toFixed(2) + ' د.أ');
                    $('#drawer-paid-val').text(parseFloat(inv.amount_paid).toFixed(2) + ' د.أ');
                    $('#drawer-balance-val').text(parseFloat(inv.balance).toFixed(2) + ' د.أ');
                    $('#drawer-family-uid').text(inv.family_uid);
                    $('#drawer-issue-date').text(inv.issue_date);
                    $('#drawer-due-date').text(inv.due_date || '—');

                    let statusClass = 'olama-reg-badge--inactive';
                    let statusLabel = 'مسودة';
                    switch (inv.status) {
                        case 'issued':
                            statusClass = 'olama-reg-badge--active';
                            statusLabel = 'صادرة';
                            break;
                        case 'partial':
                            statusClass = 'olama-reg-badge--active';
                            statusLabel = 'جزئية';
                            break;
                        case 'paid':
                            statusClass = 'olama-reg-badge--active';
                            statusLabel = 'مدفوعة';
                            break;
                        case 'overdue':
                            statusClass = 'olama-reg-badge--blacklist';
                            statusLabel = 'متأخرة';
                            break;
                        case 'cancelled':
                            statusClass = 'olama-reg-badge--inactive';
                            statusLabel = 'ملغاة';
                            break;
                    }
                    $('#drawer-status-badge').html(`<span class="olama-reg-badge ${statusClass}">${statusLabel}</span>`);

                    // Populate items
                    const $itemsBody = $('#drawer-items-table tbody');
                    $itemsBody.empty();
                    if (inv.items && inv.items.length) {
                        inv.items.forEach(item => {
                            $itemsBody.append(`
                            <tr>
                                <td>${item.description}</td>
                                <td style="text-align:center;">${parseFloat(item.quantity).toFixed(0)}</td>
                                <td>${parseFloat(item.unit_price).toFixed(2)}</td>
                                <td style="font-weight:700;">${parseFloat(item.line_total).toFixed(2)}</td>
                            </tr>`);
                        });
                    }

                    // Populate installments
                    const $instBody = $('#drawer-installments-table tbody');
                    $instBody.empty();
                    if (inv.installments && inv.installments.length) {
                        inv.installments.forEach(inst => {
                            let instStatusClass = 'olama-reg-badge--inactive';
                            let instStatusLabel = 'معلق';
                            switch (inst.status) {
                                case 'paid':
                                    instStatusClass = 'olama-reg-badge--active';
                                    instStatusLabel = 'مسدد';
                                    break;
                                case 'partial':
                                    instStatusClass = 'olama-reg-badge--active';
                                    instStatusLabel = 'جزئي';
                                    break;
                                case 'overdue':
                                    instStatusClass = 'olama-reg-badge--blacklist';
                                    instStatusLabel = 'متأخر';
                                    break;
                            }

                            $instBody.append(`
                            <tr>
                                <td>${inst.installment_no}</td>
                                <td>${inst.due_date}</td>
                                <td style="font-weight:700;">${parseFloat(inst.amount_due).toFixed(2)}</td>
                                <td>${parseFloat(inst.amount_paid).toFixed(2)}</td>
                                <td><span class="olama-reg-badge ${instStatusClass}">${instStatusLabel}</span></td>
                            </tr>`);
                        });
                    }

                    // Payment record trigger
                    const $drawerActions = $('#olama-reg-invoice-drawer').find('.olama-reg-form-actions');
                    $drawerActions.find('.olama-reg-pay-invoice-trigger').remove();
                    if (parseFloat(inv.balance) > 0 && inv.status !== 'cancelled' && inv.status !== 'draft') {
                        $drawerActions.prepend(`
                            <button type="button" class="olama-reg-btn olama-reg-btn--primary olama-reg-pay-invoice-trigger" 
                                    data-id="${inv.id}" data-no="${inv.invoice_number}" data-bal="${inv.balance}" data-family="${inv.family_uid}">
                                تسجيل دفعة ماليّة
                            </button>
                        `);
                    }

                    $('#olama-reg-invoice-drawer').fadeIn(200);
                } else {
                    showNotice(res.data?.message || R.strings.error, true);
                }
            });
    });

    $(document).on('click', '.olama-reg-drawer-close', function () {
        $('#olama-reg-invoice-drawer').fadeOut(200);
    });

    // ── Billing - Payments & Receipts ────────────────────────────────────────

    const paymentModalHtml = `
    <div id="olama-reg-payment-modal" class="olama-reg-modal" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(26,26,46,0.4); backdrop-filter:blur(4px);">
        <div class="olama-reg-wrap" style="margin:0 auto !important; padding:40px 10px !important; max-width:600px !important; width:100% !important;">
            <div class="olama-reg-modal-dialog" style="margin:0 !important; max-width:none !important;">
                <div class="olama-reg-modal-header">
                    <h2 class="olama-reg-modal-title">
                        <span class="dashicons dashicons-money-alt"></span>
                        تسجيل دفعة جديدة
                    </h2>
                    <button type="button" class="olama-reg-pay-modal-close olama-reg-modal-close" style="border:none;background:none;color:#fff;font-size:24px;cursor:pointer;">&times;</button>
                </div>
                
                <form id="olama-reg-payment-form" style="margin:0;">
                    <div class="olama-reg-modal-body">
                        <input type="hidden" name="invoice_id" id="pay_invoice_id">
                        <input type="hidden" name="family_uid" id="pay_family_uid">
                        
                        <div class="olama-reg-section" id="pay_family_search_wrap" style="display:none;">
                            <h3 class="olama-reg-section-title">البحث والربط (دفعة عامة)</h3>
                            <div class="olama-reg-grid">
                                <div class="olama-reg-field">
                                    <label for="pay_search_family">بحث عن العائلة:</label>
                                    <select id="pay_search_family" style="width:100%;"></select>
                                </div>
                                <div class="olama-reg-field" id="pay_invoice_select_wrap" style="display:none;">
                                    <label for="pay_select_invoice">الفاتورة المستهدفة:</label>
                                    <select id="pay_select_invoice" style="width:100%;">
                                        <option value="">-- اختر الفاتورة --</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="olama-reg-section" id="pay_invoice_display_wrap">
                            <h3 class="olama-reg-section-title">معلومات الفاتورة المستهدفة</h3>
                            <div class="olama-reg-grid">
                                <div class="olama-reg-field" style="display:flex; flex-direction:column;">
                                    <label>رقم الفاتورة:</label>
                                    <span id="pay_invoice_no_lbl" style="font-weight:800; color:#1a1a2e; font-size:16px; margin-top:4px;"></span>
                                </div>
                                <div class="olama-reg-field" style="display:flex; flex-direction:column;">
                                    <label>المتبقي غير المدفوع:</label>
                                    <span id="pay_invoice_bal_lbl" style="font-weight:800; color:#E8920A; font-size:16px; margin-top:4px;">0.00 د.أ</span>
                                </div>
                            </div>
                        </div>

                        <div class="olama-reg-section">
                            <h3 class="olama-reg-section-title">تفاصيل الدفعة المالية</h3>
                            <div class="olama-reg-grid">
                                <div class="olama-reg-field olama-reg-field--required">
                                    <label for="pay_amount">قيمة الدفعة المقبوضة (د.أ)</label>
                                    <input type="number" step="0.01" id="pay_amount" name="amount" required>
                                </div>
                                <div class="olama-reg-field olama-reg-field--required">
                                    <label for="pay_method">طريقة الدفع</label>
                                    <select id="pay_method" name="method" required>
                                        <option value="cash">نقدي (كاش)</option>
                                        <option value="bank_transfer">تحويل بنكي</option>
                                        <option value="cheque">شيك بنكي</option>
                                        <option value="online">دفع إلكتروني</option>
                                    </select>
                                </div>
                                <div class="olama-reg-field" id="pay_reference_wrap">
                                    <label for="pay_reference">رقم المرجع / الشيك</label>
                                    <input type="text" id="pay_reference" name="reference" placeholder="رقم المعاملة أو رقم الشيك...">
                                </div>
                                <div class="olama-reg-field olama-reg-field--required">
                                    <label for="pay_date">تاريخ القبض</label>
                                    <input type="text" id="pay_date" name="payment_date" class="olama-reg-datepicker" required>
                                </div>
                            </div>
                        </div>

                        <div class="olama-reg-section">
                            <h3 class="olama-reg-section-title">ملاحظات إضافية</h3>
                            <div style="padding:14px;">
                                <textarea id="pay_notes" name="notes" rows="3" style="width:100%; border:1.5px solid #E0C090; border-radius:6px; padding:8px; font-family:inherit;"></textarea>
                            </div>
                        </div>

                    </div>
                    
                    <div class="olama-reg-form-actions">
                        <button type="submit" class="olama-reg-btn olama-reg-btn--primary" id="olama-reg-save-payment-btn">حفظ وتسجيل السند</button>
                        <button type="button" class="button button-large olama-reg-pay-modal-close">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;

    $(document).on('click', '.olama-reg-pay-invoice-trigger', function() {
        const id = $(this).data('id');
        const no = $(this).data('no');
        const bal = parseFloat($(this).data('bal')) || 0;
        const family = $(this).data('family');

        $('#pay_family_search_wrap, #pay_invoice_select_wrap').hide();
        $('#pay_invoice_display_wrap').show();

        $('#pay_invoice_id').val(id);
        $('#pay_family_uid').val(family);
        $('#pay_invoice_no_lbl').text(no);
        $('#pay_invoice_bal_lbl').text(bal.toFixed(2) + ' د.أ');
        $('#pay_amount').val(bal.toFixed(2)).attr('max', bal.toFixed(2));

        const today = new Date().toISOString().split('T')[0];
        $('#pay_date').val(today);

        $('#olama-reg-invoice-drawer').fadeOut(100);
        $('#olama-reg-payment-modal').fadeIn(200);
        initDatepickers($('#olama-reg-payment-modal'));
    });

    $(document).on('click', '#olama-reg-open-general-payment-btn', function() {
        $('#pay_family_search_wrap, #pay_invoice_select_wrap').show();
        $('#pay_invoice_display_wrap').hide();

        $('#pay_invoice_id').val('');
        $('#pay_family_uid').val('');
        $('#pay_invoice_bal_lbl').text('0.00 د.أ');
        $('#pay_amount').val('').removeAttr('max');

        if (typeof $.fn.select2 !== 'undefined' && !$('#pay_search_family').hasClass('select2-hidden-accessible')) {
            $('#pay_search_family').select2({
                dir: 'rtl', width: '100%',
                dropdownParent: $('#olama-reg-payment-modal'),
                ajax: {
                    url: R.ajaxurl, type: 'POST', dataType: 'json', delay: 250,
                    data: function(params) { return { action: 'olama_reg_search', nonce: R.nonce, q: params.term }; },
                    processResults: function(data) {
                        return { results: (data.success && data.data.families) ? data.data.families.map(f => {
                            const name1 = f.father_first_name || '';
                            const name2 = f.father_family_name || '';
                            const fullName = (name1 + ' ' + name2).trim() || 'بدون اسم';
                            return { id: f.family_uid, text: `${fullName} (${f.family_uid})` };
                        }) : [] };
                    }
                },
                placeholder: 'ابحث عن العائلة للوصول إلى فواتيرها...',
                minimumInputLength: 2
            });
        }

        const today = new Date().toISOString().split('T')[0];
        $('#pay_date').val(today);

        $('#olama-reg-payment-modal').fadeIn(200);
        initDatepickers($('#olama-reg-payment-modal'));
    });

    $(document).on('change', '#pay_search_family', function() {
        const familyUid = $(this).val();
        $('#pay_family_uid').val(familyUid);
        const $invSelect = $('#pay_select_invoice');
        $invSelect.empty().append('<option value="">جاري تحميل الفواتير...</option>');
        $('#pay_invoice_id').val('');
        $('#pay_invoice_bal_lbl').text('0.00 د.أ');
        $('#pay_amount').val('').removeAttr('max');

        if (!familyUid) {
            $invSelect.empty().append('<option value="">-- اختر الفاتورة --</option>');
            return;
        }
        ajax('olama_reg_get_family_billing', { family_uid: familyUid, academic_year_id: 0 })
            .done(res => {
                $invSelect.empty().append('<option value="">-- اختر الفاتورة --</option>');
                if (res.success && res.data.invoices) {
                    res.data.invoices.forEach(inv => {
                        if (parseFloat(inv.balance) > 0 && inv.status !== 'cancelled' && inv.status !== 'draft') {
                            $invSelect.append(`<option value="${inv.id}" data-bal="${inv.balance}">${inv.invoice_number} (المتبقي: ${parseFloat(inv.balance).toFixed(2)})</option>`);
                        }
                    });
                }
            });
    });

    $(document).on('change', '#pay_select_invoice', function() {
        const id = $(this).val();
        $('#pay_invoice_id').val(id);
        const bal = parseFloat($(this).find(':selected').data('bal')) || 0;
        $('#pay_invoice_bal_lbl').text(bal.toFixed(2) + ' د.أ');
        $('#pay_amount').val(bal.toFixed(2)).attr('max', bal.toFixed(2));
    });

    $(document).on('click', '.olama-reg-pay-modal-close', function () {
        $('#olama-reg-payment-modal').fadeOut(200);
    });

    $(document).on('submit', '#olama-reg-payment-form', function (e) {
        e.preventDefault();
        const $btn = $('#olama-reg-save-payment-btn');
        const formData = {};
        $(this).find('[name]').each(function () {
            formData[this.name] = $(this).val();
        });

        ajax('olama_reg_record_payment', formData, $btn)
            .done(res => {
                if (res.success) {
                    showNotice(R.strings.saved);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotice(res.data?.message || R.strings.error, true);
                }
            })
            .fail(() => showNotice(R.strings.error, true));
    });

    // ── Billing - Family Account Tab Overlay ─────────────────────────────────

    function renderFamilyBilling(data, familyUid, yearId) {
        const $sec = $('#olama-reg-family-billing-section');
        $sec.empty();

        const sum = data.summary || { total_invoiced: 0, total_paid: 0, balance: 0 };
        const totalInvoiced = parseFloat(sum.total_invoiced || 0).toFixed(2);
        const totalPaid = parseFloat(sum.total_paid || 0).toFixed(2);
        const balance = parseFloat(sum.balance || 0).toFixed(2);

        let summaryHtml = `
        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-bottom:25px; margin-top:20px;">
            <div style="background:#FFFBF3; border:1.5px solid #E0C090; border-radius:10px; padding:16px; text-align:center; box-shadow: 0 2px 6px rgba(232,146,10,0.05);">
                <div style="font-size:12px; font-weight:700; color:#C4780A; margin-bottom:6px;">إجمالي الفواتير والمطالبات</div>
                <div style="font-size:24px; font-weight:800; color:#1a1a2e;">${totalInvoiced} د.أ</div>
            </div>
            <div style="background:#E8F5E9; border:1.5px solid #a5d6a7; border-radius:10px; padding:16px; text-align:center; box-shadow: 0 2px 6px rgba(46,125,50,0.05);">
                <div style="font-size:12px; font-weight:700; color:#2E7D32; margin-bottom:6px;">إجمالي السداد الفعلي</div>
                <div style="font-size:24px; font-weight:800; color:#2E7D32;">${totalPaid} د.أ</div>
            </div>
            <div style="background:#FFF3E0; border:1.5px solid #ffcc80; border-radius:10px; padding:16px; text-align:center; box-shadow: 0 2px 6px rgba(232,146,10,0.1);">
                <div style="font-size:12px; font-weight:700; color:#E8920A; margin-bottom:6px;">المتبقي ذمة مستحقة</div>
                <div style="font-size:24px; font-weight:800; color:#E8920A;">${balance} د.أ</div>
            </div>
        </div>`;

        $sec.append(summaryHtml);

        let invoicesHtml = `
        <div class="olama-reg-section" style="margin-bottom: 25px;">
            <h3 class="olama-reg-section-title">
                <span class="dashicons dashicons-media-text"></span>
                الفواتير والمطالبات المصدرة
            </h3>
            <div class="olama-reg-table-wrap">
                <table class="olama-reg-fin-table">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>تاريخ الإصدار</th>
                            <th>العام الدراسي</th>
                            <th>الإجمالي</th>
                            <th>المدفوع</th>
                            <th>المتبقي</th>
                            <th>الحالة</th>
                            <th>الخيارات</th>
                        </tr>
                    </thead>
                    <tbody>`;

        if (!data.invoices || data.invoices.length === 0) {
            invoicesHtml += `
                        <tr>
                            <td colspan="8" class="olama-reg-empty-state">لا يوجد فواتير مصدرة لهذه العائلة خلال العام الدراسي المحدد.</td>
                        </tr>`;
        } else {
            data.invoices.forEach(inv => {
                let statusClass = 'olama-reg-badge--inactive';
                let statusLabel = 'مسودة';
                switch (inv.status) {
                    case 'issued':
                        statusClass = 'olama-reg-badge--active';
                        statusLabel = 'صادرة';
                        break;
                    case 'partial':
                        statusClass = 'olama-reg-badge--active';
                        statusLabel = 'جزئية';
                        break;
                    case 'paid':
                        statusClass = 'olama-reg-badge--active';
                        statusLabel = 'مدفوعة';
                        break;
                    case 'overdue':
                        statusClass = 'olama-reg-badge--blacklist';
                        statusLabel = 'متأخرة';
                        break;
                    case 'cancelled':
                        statusClass = 'olama-reg-badge--inactive';
                        statusLabel = 'ملغاة';
                        break;
                }

                const adminUrl = R.ajaxurl.replace('admin-ajax.php', 'admin.php');
                invoicesHtml += `
                        <tr>
                            <td><strong>${inv.invoice_number}</strong></td>
                            <td>${inv.issue_date}</td>
                            <td>${inv.academic_year_name || '—'}</td>
                            <td style="font-weight:700;">${parseFloat(inv.total).toFixed(2)}</td>
                            <td style="color:#2e7d32; font-weight:700;">${parseFloat(inv.amount_paid).toFixed(2)}</td>
                            <td class="olama-reg-balance-cell">${parseFloat(inv.balance).toFixed(2)}</td>
                            <td><span class="olama-reg-badge ${statusClass}">${statusLabel}</span></td>
                            <td>
                                <button class="button button-small olama-reg-view-invoice-btn" data-id="${inv.id}">
                                    <span class="dashicons dashicons-visibility" style="font-size:16px;vertical-align:middle;margin-top:2px;"></span>
                                </button>
                                <a href="${adminUrl}?page=olama-registration-invoices&action=print&id=${inv.id}" target="_blank" class="button button-small">
                                    <span class="dashicons dashicons-printer" style="font-size:16px;vertical-align:middle;margin-top:2px;"></span>
                                </a>
                            </td>
                        </tr>`;
            });
        }

        invoicesHtml += `
                    </tbody>
                </table>
            </div>
        </div>`;

        $sec.append(invoicesHtml);

        let paymentsHtml = `
        <div class="olama-reg-section">
            <h3 class="olama-reg-section-title">
                <span class="dashicons dashicons-money-alt"></span>
                السندات والمدفوعات المستلمة
            </h3>
            <div class="olama-reg-table-wrap">
                <table class="olama-reg-fin-table">
                    <thead>
                        <tr>
                            <th>رقم السند</th>
                            <th>تاريخ القبض</th>
                            <th>الفاتورة المربوطة</th>
                            <th>طريقة الدفع</th>
                            <th>رقم المرجع / الشيك</th>
                            <th>القيمة المقبوضة</th>
                            <th>المستلم</th>
                            <th>إيصال</th>
                        </tr>
                    </thead>
                    <tbody>`;

        if (!data.payments || data.payments.length === 0) {
            paymentsHtml += `
                        <tr>
                            <td colspan="8" class="olama-reg-empty-state">لا يوجد دفعات مسجلة لهذه العائلة.</td>
                        </tr>`;
        } else {
            const payMethods = {
                cash: 'نقدي',
                bank_transfer: 'تحويل بنكي',
                cheque: 'شيك',
                online: 'دفع إلكتروني'
            };

            const adminUrl = R.ajaxurl.replace('admin-ajax.php', 'admin.php');
            data.payments.forEach(pay => {
                paymentsHtml += `
                        <tr>
                            <td>#${pay.id}</td>
                            <td>${pay.payment_date}</td>
                            <td><strong>${pay.invoice_number || '—'}</strong></td>
                            <td>${payMethods[pay.method] || pay.method}</td>
                            <td>${pay.reference || '—'}</td>
                            <td style="font-weight:700; color:#2e7d32;">${parseFloat(pay.amount).toFixed(2)}</td>
                            <td>${pay.received_by_name || '—'}</td>
                            <td>
                                <a href="${adminUrl}?page=olama-registration-payments&action=print_receipt&id=${pay.id}" target="_blank" class="button button-small">
                                    <span class="dashicons dashicons-printer" style="font-size:16px;vertical-align:middle;margin-top:2px;"></span>
                                </a>
                            </td>
                        </tr>`;
            });
        }

        paymentsHtml += `
                    </tbody>
                </table>
            </div>
        </div>`;

        $sec.append(paymentsHtml);
    }

    function loadFamilyBilling() {
        const familyUid = $('#olama-reg-family-uid').val();
        const yearId    = $('#olama-reg-fin-year').val() || 0;
        if (!familyUid) return;

        if ($('#olama-reg-family-billing-section').length === 0) {
            $('#tab-financial').append('<div id="olama-reg-family-billing-section" style="margin-top: 30px;"></div>');
        }

        const $sec = $('#olama-reg-family-billing-section');
        $sec.html('<div class="olama-reg-empty-state"><span class="dashicons dashicons-update spin"></span> جاري تحميل الحساب المالي...</div>');

        ajax('olama_reg_get_family_billing', { family_uid: familyUid, academic_year_id: yearId })
            .done(res => {
                if (res.success) {
                    renderFamilyBilling(res.data, familyUid, yearId);
                } else {
                    $sec.html(`<div class="olama-reg-notice olama-reg-notice--error" style="display:block;">${res.data?.message || 'خطأ في تحميل البيانات الماليّة.'}</div>`);
                }
            })
            .fail(() => {
                $sec.html('<div class="olama-reg-notice olama-reg-notice--error" style="display:block;">خطأ في الاتصال بالخادم.</div>');
            });
    }

    // Reload family billing in sync when the year changes
    $(document).on('change', '#olama-reg-fin-year', function () {
        const familyUid = $(this).data('family-uid');
        const yearId    = $(this).val();
        ajax('olama_reg_get_financial', { family_uid: familyUid, academic_year_id: yearId })
            .done(res => {
                if (res.success) {
                    renderFinTable(res.data.rows, res.data.totals, familyUid, yearId);
                    loadFamilyBilling();
                }
            });
    });

    // ── Init Helpers ──────────────────────────────────────────────────────────

    function initDatepickers($ctx) {
        if (typeof $.fn.datepicker === 'undefined') return;
        $ctx.find('.olama-reg-datepicker').each(function () {
            if (!$(this).hasClass('hasDatepicker')) {
                $(this).datepicker({ dateFormat: 'yy-mm-dd', changeYear: true, changeMonth: true, yearRange: '1970:2050' });
            }
        });
    }

    function initSelect2($ctx) {
        if (typeof $.fn.select2 === 'undefined') return;
        $ctx.find('.olama-reg-select2').each(function () {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({ dir: 'rtl', width: '100%' });
            }
        });
    }

    // ── Page Init ─────────────────────────────────────────────────────────────

    $(document).ready(function () {
        if ($('#olama-reg-payment-modal').length === 0) {
            $('body').append(paymentModalHtml);
        }

        initDatepickers($('.olama-reg-wrap'));
        initSelect2($('.olama-reg-wrap'));

        if ($('.olama-reg-tab.active[data-tab="financial"]').length) {
            loadFamilyBilling();
        }
    });

    // =========================================================================
    // Custom Payments
    // =========================================================================
    if ($('#cp_family_search').length) {
        if (typeof $.fn.select2 !== 'undefined') {
            $('#cp_family_search').select2({
                dir: 'rtl', width: '100%',
                ajax: {
                    url: R.ajaxurl, type: 'POST', dataType: 'json', delay: 250,
                    data: function(params) { return { action: 'olama_reg_search', nonce: R.nonce, q: params.term }; },
                    processResults: function(data) {
                        return { results: (data.success && data.data.families) ? data.data.families.map(f => {
                            const name1 = f.father_first_name || '';
                            const name2 = f.father_family_name || '';
                            const fullName = (name1 + ' ' + name2).trim() || 'بدون اسم';
                            return { id: f.family_uid, text: `${fullName} (${f.family_uid})` };
                        }) : [] };
                    }
                },
                placeholder: '-- ابحث باسم الأب، الأم، أو رقم العائلة --',
                minimumInputLength: 3
            });
        }

        // Toggle Internal/External Customer UI
        $('input[name="customer_type"]').on('change', function() {
            if ($(this).val() === 'external') {
                $('#cp_internal_customer_wrap').hide();
                $('#cp_external_customer_wrap').show();
                $('#cp_family_search').prop('required', false);
                $('#cp_students_container').hide();
                $('.cp-student-check').prop('checked', false);
                calculateCustomTotal();
            } else {
                $('#cp_internal_customer_wrap').show();
                $('#cp_external_customer_wrap').hide();
                $('#cp_family_search').prop('required', true);
                if ($('#cp_family_uid').val()) {
                    $('#cp_students_container').show();
                }
            }
        });

        // Add External Child
        let extChildIndex = 0;
        $('#cp_ext_add_child_btn').on('click', function() {
            const name = $('#cp_ext_child_name').val().trim();
            const grade = $('#cp_ext_child_grade').val().trim();
            if (!name) return;
            
            extChildIndex++;
            const html = `
                <label style="display:flex; align-items:center; gap:8px; padding:10px; background:#fff; border:1px solid #cbd5e1; border-radius:6px; cursor:pointer;" id="ext_child_lbl_${extChildIndex}">
                    <input type="checkbox" class="cp-ext-student-check" data-name="${name}" data-grade="${grade}" checked />
                    <span>
                        <span style="display:block; font-weight:700;">${name}</span>
                        <span style="display:block; font-size:11px; color:#64748b;">${grade || 'بدون صف'}</span>
                    </span>
                    <button type="button" class="button-link" style="color:#dc2626; margin-right:auto; padding:0;" onclick="$('#ext_child_lbl_${extChildIndex}').remove(); $('#cp_amount').trigger('change');">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </label>
            `;
            $('#cp_ext_students_list').append(html);
            $('#cp_ext_child_name').val('');
            $('#cp_ext_child_grade').val('');
            calculateCustomTotal();
        });

        $('#cp_family_search').on('change', function() {
            const familyUid = $(this).val();
            $('#cp_family_uid').val(familyUid);
            const $container = $('#cp_students_container');
            const $list = $('#cp_students_list');
            
            $list.empty();
            if (!familyUid) {
                $container.hide();
                calculateCustomTotal();
                return;
            }

            $container.show();
            $list.html('<div style="grid-column: 1/-1; text-align: center; color: var(--reg-primary);"><span class="spinner is-active" style="float:none;"></span> جاري تحميل الطلاب...</div>');

            ajax('olama_reg_get_family_students', { family_uid: familyUid })
                .done(res => {
                    $list.empty();
                    if (res.success && res.data.students && res.data.students.length > 0) {
                        res.data.students.forEach(st => {
                            const gradeName = st.grade_name || '';
                            const html = `
                                <label style="display:flex; align-items:center; gap:8px; padding:10px; background:#fff; border:1px solid #cbd5e1; border-radius:6px; cursor:pointer;">
                                    <input type="checkbox" name="student_uids[]" value="${st.student_uid}" class="cp-student-check" checked />
                                    <span>
                                        <span style="display:block; font-weight:700;">${st.student_name || 'بدون اسم'}</span>
                                        <span style="display:block; font-size:11px; color:#64748b;">${gradeName}</span>
                                    </span>
                                </label>
                            `;
                            $list.append(html);
                        });
                        calculateCustomTotal();
                    } else {
                        $list.html('<div style="grid-column: 1/-1; color: #dc2626;">لا يوجد طلاب نشطين مسجلين لهذه العائلة.</div>');
                    }
                })
                .fail(() => {
                    $list.html('<div style="grid-column: 1/-1; color: #dc2626;">حدث خطأ أثناء جلب البيانات.</div>');
                });
        });

        $(document).on('change', '.cp-student-check, .cp-ext-student-check, #cp_amount, #cp_discount', calculateCustomTotal);
        $(document).on('input', '#cp_amount, #cp_discount', calculateCustomTotal);

        $('#cp_fee_template').on('change', function() {
            const amount = $(this).find(':selected').data('amount');
            if (amount !== undefined) {
                $('#cp_amount').val(parseFloat(amount).toFixed(2));
                calculateCustomTotal();
            }
        });

        function calculateCustomTotal() {
            const checkedCount = $('.cp-student-check:checked, .cp-ext-student-check:checked').length;
            const amount = parseFloat($('#cp_amount').val()) || 0;
            const discount = parseFloat($('#cp_discount').val()) || 0;
            const total = Math.max(0, (checkedCount * amount) - discount);
            $('#cp_total_display').text(total.toFixed(2) + ' د.أ');
            
            if (checkedCount > 0) {
                $('#cp_students_error').hide();
            }
        }

        $('#olama-reg-custom-payment-form').on('submit', function(e) {
            e.preventDefault();
            
            const isExternal = $('input[name="customer_type"]:checked').val() === 'external';
            const internalCount = $('.cp-student-check:checked').length;
            const externalCount = $('.cp-ext-student-check:checked').length;
            
            if (!isExternal && internalCount === 0) {
                $('#cp_students_error').show();
                return;
            }
            if (isExternal && externalCount === 0) {
                alert('يجب إضافة واختيار ابن واحد على الأقل للعميل الخارجي.');
                return;
            }

            const $form = $(this);
            const $btn = $('#cp_submit_btn');
            const $loading = $('#cp_loading');
            const $msg = $('#cp_response_msg');

            $btn.prop('disabled', true);
            $loading.show();
            $msg.hide();

            const processPayment = function(familyUid, studentUids = []) {
                $('#cp_family_uid').val(familyUid);
                
                // Inject student UIDs if passed
                $('.cp_temp_student_uid').remove();
                studentUids.forEach(uid => {
                    $form.append(`<input type="hidden" name="student_uids[]" value="${uid}" class="cp_temp_student_uid">`);
                });

                // Serialize again now that family_uid and student_uids are set
                let formData = $form.serialize();

                $.post(R.ajaxurl, 'action=olama_reg_save_custom_payment&nonce=' + R.nonce + '&' + formData)
                .done(res => {
                    if (res.success) {
                        $msg.html('<div class="notice notice-success inline" style="padding:10px;"><p>' + res.data.message + '</p></div>').fadeIn();
                        $('#olama-reg-custom-payment-form')[0].reset();
                        if (typeof $.fn.select2 !== 'undefined') {
                            $('#cp_family_search').val('').trigger('change');
                        }
                        calculateCustomTotal();
                        
                        if (res.data.payment_id) {
                            const url = new URL(window.location.href);
                            url.searchParams.set('page', 'olama-registration-payments');
                            url.searchParams.set('action', 'print_receipt');
                            url.searchParams.set('id', res.data.payment_id);
                            window.open(url.toString(), '_blank');
                        }
                    } else {
                        $msg.html('<div class="notice notice-error inline" style="padding:10px;"><p>' + (res.data.message || 'Error occurred.') + '</p></div>').fadeIn();
                    }
                })
                .always(() => {
                    $btn.prop('disabled', false);
                    $loading.hide();
                });
            };

            if (isExternal) {
                const extName = $('#cp_ext_name').val();
                const extPhone = $('#cp_ext_phone').val();
                
                if (!extName || !extPhone) {
                    $msg.html('<div class="notice notice-error inline" style="padding:10px;"><p>يرجى إدخال اسم العميل ورقم الهاتف.</p></div>').fadeIn();
                    $btn.prop('disabled', false);
                    $loading.hide();
                    return;
                }

                const children = [];
                $('.cp-ext-student-check:checked').each(function() {
                    children.push({
                        name: $(this).data('name'),
                        grade: $(this).data('grade')
                    });
                });

                $.post(R.ajaxurl, {
                    action: 'olama_reg_create_external_customer',
                    nonce: R.nonce,
                    name: extName,
                    phone: extPhone,
                    children: JSON.stringify(children)
                }).done(res => {
                    if (res.success && res.data.family_uid) {
                        processPayment(res.data.family_uid, res.data.student_uids || []);
                    } else {
                        $msg.html('<div class="notice notice-error inline" style="padding:10px;"><p>' + (res.data?.message || 'Error creating external customer.') + '</p></div>').fadeIn();
                        $btn.prop('disabled', false);
                        $loading.hide();
                    }
                }).fail(() => {
                    $msg.html('<div class="notice notice-error inline" style="padding:10px;"><p>حدث خطأ في الاتصال بالخادم عند إنشاء العميل.</p></div>').fadeIn();
                    $btn.prop('disabled', false);
                    $loading.hide();
                });
            } else {
                processPayment($('#cp_family_uid').val());
            }
        });
    }

})(jQuery);
