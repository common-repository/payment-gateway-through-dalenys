jQuery(document).ready(function ($) {
    // Define some style properties to apply on hosted-fields inputs
    var style = {
        "input": {
            "font-size": "1em",
        },
        "::placeholder": {
            "font-size": "1em",
            "color": "#777",
            "font-style": "italic"
        }
    };

// Initialize the hosted-fields library
    var hfields = dalenys.hostedFields({
        // SDK api key
        key: {
            id: DalenysAjax.api_key_id,
            value: DalenysAjax.api_key
        },
        // Manages each hosted-field container
        fields: {
            'brand': {
                id: 'brand-container',
                style: style
            },
            'card': {
                id: 'card-container',
                enableAutospacing: true,
                style: style
            },
            'expiry': {
                id: 'expiry-container',
                placeholder: '',
                style: style
            },
            'cryptogram': {
                id: 'cvv-container',
                style: style
            }
        }
    });

// Load the hosted-fields library
    hfields.load();

    $(document).on('updated_checkout', function () {

        //Re-init card fields after refreshing checkout form
        hfields.dispose();
        hfields.load();
    });

// Manage the token creation
    function tokenizeHandler() {
        hfields.createToken(function (result) {
            if (result.execCode == '0000') {
                document.getElementById('hf-token').value = result.hfToken;
                document.getElementById('selected-brand').value = result.selectedBrand;
                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url: '/?wc-ajax=checkout',
                    data: jQuery('form.checkout.woocommerce-checkout').serialize(),
                    beforeSend: function (data) {
                        $('form.checkout.woocommerce-checkout button[name="woocommerce_checkout_place_order"]').prop('disabled', true);
                    },
                    success: function (data) {
                        if (data.result == 'failure') {
                            let form = $('form.checkout');
                            // Remove notices from all sources
                            $('.woocommerce-NoticeGroup.woocommerce-NoticeGroup-checkout').remove();

                            // Add new errors returned by this event
                            if (data.messages) {
                                $(form).prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + data.messages + '</div>');
                            } else {
                                $(form).prepend(data);
                            }

                            // Lose focus for all fields
                            $(form).find('.input-text, select, input:checkbox').trigger('validate').blur()

                            let scrollElement = $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout');

                            if (!scrollElement.length) {
                                scrollElement = $('.form.checkout');
                            }
                            $.scroll_to_notices(scrollElement);
                        } else if (data.result == 'redirect') {
                            $('#dalenys-3ds-form-wrap').html(data.html);
                            $('#dalenys-3ds-form-wrap form').submit();

                        } else {
                            window.location.href = data.url;
                        }
                        $('form.checkout.woocommerce-checkout button[name="woocommerce_checkout_place_order"]').prop('disabled', false);
                    }
                });
            } else {
                let form = $('form.checkout');
                // Remove notices from all sources
                $('.woocommerce-error, .woocommerce-message').remove();

                // Add new errors returned by this event
                $(form).prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><div class="woocommerce-error">' + result.execCode + ': ' + result.message + '</div></div>');

                // Lose focus for all fields
                $(form).find('.input-text, select, input:checkbox').trigger('validate').blur()

                let scrollElement = $('.woocommerce-NoticeGroup-checkout');

                if (!scrollElement.length) {
                    scrollElement = $('.form.checkout');
                }
                $.scroll_to_notices(scrollElement);
            }
        });
        return false;
    }

    $('body').on('click', 'form.checkout.woocommerce-checkout button[name="woocommerce_checkout_place_order"]', function (e) {
        if ($('input#payment_method_dalenys').prop('checked') == true) {
            e.preventDefault();
            tokenizeHandler();
        }
    });
});
