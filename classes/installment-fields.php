<?php

$list_of_banks = array();

if ($installment_plans) {
    function cc_bank_availables($data)
    {
        if (preg_match("/land\s*bank/i", $data["issuer_name"])) {
            $image_id = "landbank";
        } elseif (preg_match("/security\s*bank/i", $data["issuer_name"])) {
            $image_id = "security_bank";
        } elseif (preg_match("/asia\s*united\s*bank/i", $data["issuer_name"]) or preg_match("/\baub\b/i", $data["issuer_name"])) {
            $image_id = "aub";
        } else {
            $image_id = null;
        }

        $percentage_formatter = new NumberFormatter(get_locale(), NumberFormatter::PERCENT);

        return array(
            ...$data,
            'image_url' => $image_id ? CYNDER_PAYMONGO_PLUGIN_URL . "/assets/images/" . $image_id . ".png" : "",
            'auth_amount' => wc_price($data['auth_amount'] / 100),
            'bank_interest_rate' => $percentage_formatter->format($data['bank_interest_rate'] / 100),
            'interest_amount_charged' => wc_price($data['interest_amount_charged'] / 100),
            'loan_amount' => wc_price($data['loan_amount'] / 100),
            'processing_fee_percent' => $percentage_formatter->format($data['processing_fee_percent'] / 100),
            'processing_fee_value' => wc_price($data['processing_fee_value'] / 100),
            'monthly_installment' => wc_price($data['monthly_installment'] / 100),
        );
    }

    $list_of_banks = array_map("cc_bank_availables", $installment_plans);
}

$installment_plan_json = json_encode($list_of_banks);

?>

<?php $percentage_formatter = new NumberFormatter(get_locale(), NumberFormatter::PERCENT); ?>

<div class="">
    <input hidden id="installment-data" name="installment-data" value='<?php echo $installment_plan_json ?>' />
    <div class="form-row form-row-wide">
        <label>Card Number <span class="required">*</span></label>
        <input id="paymongo_cc_installment_ccNo" class="paymongo_ccNo" type="text" autocomplete="off">
    </div>
    <div class="form-row form-row-first">
        <label>Expiry Date <span class="required">*</span></label>
        <input id="paymongo_cc_installment_expdate" class="paymongo_expdate" type="text" autocomplete="off" placeholder="MM / YY">
    </div>
    <div class="form-row form-row-last">
        <label>Card Code (CVC) <span class="required">*</span></label>
        <input id="paymongo_cc_installment_cvv" class="paymongo_cvv" type="password" autocomplete="off" placeholder="CVC">
    </div>
    <div class="clear"></div>

    <div class="my-1">
        <div id="installment-container" class="">
            <div class="my-1">
                <h3>Payment Information</h3>
                <div class="d-block my-1">
                    <label for="paymongo_cc_installment_issuer" class="bank-label">Select a bank <span class="required">*</span></label>
                    <select name="paymongo_cc_installment_issuer" id="paymongo_cc_installment_issuer" class="d-block">
                    </select>
                </div>

                <div class="my-1 d-inline-flex">
                    <div id="cc_bank_logo_div">
                        <img id="cc_bank_logo" src="" />
                    </div>
                    <div>
                        <h5 id="cc_bank_name"></h5>
                        <span id="cc_bank_interest_rate"></span>
                    </div>
                </div>
            </div>

            <div class="my-1">
                <h3>Choose your Installment Plan <span class="required">*</span></h3>
                <ul id="installment_list" class="woocommerce-PaymentMethods payment_methods methods wc_payment_methods">
                </ul>
            </div>

            <table class="my-1">
                <thead>
                    <tr>
                        <th colspan="2">Installment Plan Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Principal Amount</td>
                        <td id="auth_amount"><?php echo (wc_price(0)) ?></td>
                    </tr>
                    <tr>
                        <td>Total Interest (<span id="bank_interest_rate"><?php echo ($percentage_formatter->format(0 / 100)) ?> </span>)</td>
                        <td id="interest_amount_charged"><?php echo (wc_price(0)) ?></td>
                    </tr>
                    <tr>
                        <td>Gross Amount</td>
                        <td id="loan_amount"><?php echo (wc_price(0)) ?></td>
                    </tr>
                    <tr>
                        <td>Processing Fee (<span id="processing_fee_percent">0</span>)</td>
                        <td id="processing_fee_value"><?php echo (wc_price(0)) ?></td>
                    </tr>
                    <tr>
                        <td>Installment Period</td>
                        <td id="cc_tenure">0 months</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Monthly Payment</th>
                        <th id="monthly_installment">
                            <?php echo (wc_price(0)) ?>
                        </th>
                    </tr>
                </tfoot>
            </table>

            <div>
                <input name="paymongo_cc_installment_tc" id="paymongo_cc_installment_tc" type="checkbox" value="yes" />
                <label for="paymongo_cc_installment_tc" id="cc_terms_and_conditions"><span class="required">*</span></label>
            </div>
        </div>
    </div>
</div>