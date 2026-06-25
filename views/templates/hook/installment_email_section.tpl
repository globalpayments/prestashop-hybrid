<div class="v1shadow v1wrapper-container" style="box-shadow: 0 20px 30px 0 rgba(0, 0, 0, 0.1); background: #ffffff; background-color: #ffffff; margin: 0px auto; border-radius: 4px; max-width: 604px">
    <div style="font-family: Open sans,arial,sans-serif; font-size: 14px; line-height: 25px; text-align: left; color: #363A41;margin: 10px;padding: 10px;">
        <span style="font-weight:700;font-size:15px;">Installment Payment Details</span>
        <table border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:8px;font-family:Open sans,arial,sans-serif;font-size:14px;color:#363A41;" width="100%">
            <tbody>
                <tr><td style="padding:3px 0;"><span style="font-weight:700;">Installments:</span> {$installments|escape:'html':'UTF-8'}</td></tr>
                <tr><td style="padding:3px 0;"><span style="font-weight:700;">Total Financed Amount:</span> {$financedAmount|escape:'html':'UTF-8'}{$currencyDisplay|escape:'html':'UTF-8'}</td></tr>
                <tr><td style="padding:3px 0;"><span style="font-weight:700;">Finance Fee:</span> {$financeFee|escape:'html':'UTF-8'}{$currencyDisplay|escape:'html':'UTF-8'}</td></tr>
                <tr><td style="padding:3px 0;"><span style="font-weight:700;">Monthly Amount:</span> {$monthlyAmount|escape:'html':'UTF-8'}{$currencyDisplay|escape:'html':'UTF-8'}</td></tr>
            </tbody>
        </table>
    </div>
</div>
