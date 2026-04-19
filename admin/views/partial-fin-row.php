<?php /* Financial row partial — used on initial load and via JS template */
if ( ! defined( 'ABSPATH' ) ) exit; ?>
<tr data-fin-id="<?php echo (int)( $row->id ?? 0 ); ?>" class="olama-reg-fin-row">
    <td><input type="text" name="entitlement_date" value="<?php echo esc_attr($row->entitlement_date ?? ''); ?>" class="olama-reg-inline-input olama-reg-datepicker"></td>
    <td><input type="text" name="calculation_method" value="<?php echo esc_attr($row->calculation_method ?? ''); ?>" class="olama-reg-inline-input"></td>
    <td><input type="number" name="percentage" value="<?php echo esc_attr($row->percentage ?? ''); ?>" class="olama-reg-inline-input" step="0.01" min="0" max="100"></td>
    <td><input type="number" name="amount_due" value="<?php echo esc_attr($row->amount_due ?? '0.00'); ?>" class="olama-reg-inline-input olama-reg-fin-calc" step="0.01" min="0"></td>
    <td><input type="number" name="amount_paid" value="<?php echo esc_attr($row->amount_paid ?? '0.00'); ?>" class="olama-reg-inline-input olama-reg-fin-calc" step="0.01" min="0"></td>
    <td><input type="number" name="payments_revolving" value="<?php echo esc_attr($row->payments_revolving ?? '0.00'); ?>" class="olama-reg-inline-input olama-reg-fin-calc" step="0.01"></td>
    <td class="olama-reg-fin-balance olama-reg-balance-cell">
        <?php
            $bal = ( (float)($row->amount_due ?? 0) - (float)($row->amount_paid ?? 0) + (float)($row->payments_revolving ?? 0) );
            echo number_format( $bal, 2 );
        ?>
    </td>
    <td><input type="text" name="payment_reference" value="<?php echo esc_attr($row->payment_reference ?? ''); ?>" class="olama-reg-inline-input"></td>
    <td><input type="text" name="fin_notes" value="<?php echo esc_attr($row->fin_notes ?? ''); ?>" class="olama-reg-inline-input"></td>
    <td><button type="button" class="button button-small olama-reg-save-fin-row"><?php esc_html_e('حفظ','olama-registration'); ?></button></td>
    <td><button type="button" class="button button-small olama-reg-delete-fin-row" style="color:#c0392b"><?php esc_html_e('حذف','olama-registration'); ?></button></td>
</tr>
