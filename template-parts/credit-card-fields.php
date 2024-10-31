<p style="position: relative;">
    <label>Card number</label>
    <!-- Here is the card container -->
    <span class="input-container" id="card-container"></span>
    <span id="brand-container"></span>
</p>

<p class="expiry-container-wrap">
    <label>Card expiry</label>
    <!-- Here is the expiry container -->
    <span class="input-container" id="expiry-container"></span>
</p>

<p class="expiry-container-wrap">
    <label>Card security code</label>
    <!-- Here is the cryptogram container -->
    <span class="input-container" id="cvv-container"></span>
</p>

<p>
    <label>Cardholder full name</label>
    <input name="dalenys-holder" type="text" autocomplete="off">
</p>

<!-- This hidden input will receive the token -->
<input type="hidden" name="hf-token" id="hf-token">

<!-- This hidden input will receive the selected brandtoken -->
<input type="hidden" name="selected-brand" id="selected-brand">