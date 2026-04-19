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
        initDatepickers($('.olama-reg-wrap'));
        initSelect2($('.olama-reg-wrap'));
    });

})(jQuery);
