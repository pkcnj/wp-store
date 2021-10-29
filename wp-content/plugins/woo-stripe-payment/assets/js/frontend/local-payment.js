(function ($, wc_stripe) {

    /**
     * [LocalPayment description]
     */
    function LocalPayment(params) {
        wc_stripe.BaseGateway.call(this, params);
        wc_stripe.CheckoutGateway.call(this);

        $(document.body).on('click', '#place_order', this.place_order.bind(this));

        if (this.is_current_page('order_pay')) {
            $('#order_review').on('submit', this.process_order_pay.bind(this));
        }

        this.maybe_hide_gateway();
    }

    LocalPayment.prototype = $.extend({}, wc_stripe.BaseGateway.prototype, wc_stripe.CheckoutGateway.prototype);


    LocalPayment.prototype.initialize = function () {
        this.mount_button();
    }

    LocalPayment.prototype.elementType = null;

    LocalPayment.prototype.is_active = function () {
        return $('#wc_stripe_local_payment_' + this.gateway_id).data('active');
    }

    LocalPayment.prototype.maybe_hide_gateway = function () {
        if (!this.is_active()) {
            $(this.container).hide();
        } else {
            $(this.container).show();
        }
    }

    /**
     * [createSource description]
     * @return {[type]} [description]
     */
    LocalPayment.prototype.createSource = function () {
        return new Promise(function (resolve, reject) {
            var handler = function (result) {
                if (result.error) {
                    this.submit_error(result.error);
                } else {
                    this.payment_token_received = true;
                    this.set_nonce(result.source.id);
                    this.get_form().submit();
                }
                resolve();
            }.bind(this);
            if (this.elementType != null) {
                if (this.confirmation_method) {
                    if (this.confirmation_obj) {
                        this.processConfirmation(this.confirmation_obj);
                    } else {
                        if (this.isValidElement()) {
                            this.payment_token_received = true;
                            this.get_form().submit();
                        } else {
                            return this.submit_error({
                                code: 'empty_element_' + this.params.local_payment_type,
                                message: wc_stripe_messages.empty_element
                            });
                        }
                    }
                } else {
                    this.stripe.createSource(this.element, this.getSourceArgs()).then(handler).catch(function (e) {
                        this.submit_error(e.message);
                    }.bind(this))
                }
            } else {
                this.payment_token_received = true;
                this.get_form().submit();
            }
        }.bind(this));
    }

    LocalPayment.prototype.place_order = function (e) {
        if (this.is_gateway_selected()) {
            if (!this.payment_token_received && !this.is_saved_method_selected()) {
                e.preventDefault();
                this.createSource();
            }
        }
    }

    LocalPayment.prototype.process_order_pay = function (e) {
        if (this.is_gateway_selected()) {
            e.preventDefault();
            var data = this.get_form().serializeArray();
            data.push({name: '_wpnonce', value: this.params.rest_nonce});
            data.push({name: 'order_id', value: this.params.order_id});
            var search = window.location.search;
            var match = this.params.routes.order_pay.match(/\?/);
            if (match) {
                search = '&' + search.substr(1);
            }
            $.ajax({
                url: this.params.routes.order_pay + search,
                method: 'POST',
                dataType: 'json',
                data: $.param(data)
            }).done(function (response) {
                if (response.success) {
                    window.location.href = response.redirect;
                } else {
                    this.submit_error(response.message);
                }
            }.bind(this)).fail(function (jqXHR, textStatus, errorThrown) {
                this.submit_error(errorThrown);
            }.bind(this))
        }
    }

    LocalPayment.prototype.show_payment_button = function () {
        this.show_place_order();
    }

    /**
     * [Leave empty so that the place order button is not hidden]
     * @return {[type]} [description]
     */
    LocalPayment.prototype.hide_place_order = function () {

    }

    LocalPayment.prototype.show_place_order = function () {
        wc_stripe.CheckoutGateway.prototype.show_place_order.apply(this, arguments);
        if (this.payment_token_received) {
            $('#place_order').text($('#place_order').data('value'));
        }
    }

    LocalPayment.prototype.getSourceArgs = function () {
        return {
            type: this.params.local_payment_type,
            amount: this.get_total_price_cents(),
            currency: this.get_currency(),
            owner: {
                name: this.get_customer_name('billing'),
                email: this.fields.get('billing_email', null)
            },
            redirect: {
                return_url: this.params.return_url
            }
        }
    }

    LocalPayment.prototype.updated_checkout = function () {
        this.mount_button();
        this.maybe_hide_gateway();
    }

    LocalPayment.prototype.mount_button = function () {
        var id = '#wc_stripe_local_payment_' + this.gateway_id;
        if ($(id).length && this.elementType != null) {
            $(id).empty();
            if (!this.element) {
                this.element = this.elements.create(this.elementType, this.params.element_params);
                this.element.on('change', this.handleElementChange.bind(this));
            }
            this.elementEmpty = true;
            this.element.mount(id);
        }

    }

    LocalPayment.prototype.handleElementChange = function (e) {
        this.elementEmpty = e.empty;
    }

    LocalPayment.prototype.load_external_script = function (url) {
        var script = document.createElement('script');
        script.type = "text/javascript";
        script.src = url;
        script.onload = function () {
            this.script_loaded = true;
        }.bind(this);
        document.body.appendChild(script);
    }

    LocalPayment.prototype.hashChange = function (e) {
        if (this.is_gateway_selected()) {
            var match = e.newURL.match(/response=(.*)/);
            if (match) {
                history.pushState({}, '', window.location.pathname);
                var obj = JSON.parse(window.atob(decodeURIComponent(match[1])));
                this.processConfirmation(obj);
            }
        }
    }

    LocalPayment.prototype.processConfirmation = function (obj) {
        this.stripe[this.confirmation_method](obj.client_secret, this.get_confirmation_args(obj)).then(function (result) {
            if (result.error) {
                this.confirmation_obj = obj;
                this.payment_token_received = false;
                return this.submit_error(result.error.message);
            }
            window.location.href = decodeURI(obj.order_received_url);
        }.bind(this))
    }

    LocalPayment.prototype.get_confirmation_args = function (obj) {
        var args = {
            payment_method: {
                billing_details: this.get_billing_details()
            },
            return_url: obj.return_url
        };
        if (this.elementType) {
            args.payment_method[this.params.local_payment_type] = this.element;
        }
        return args;
    }

    LocalPayment.prototype.isValidElement = function () {
        if (this.element) {
            return !this.elementEmpty;
        }
        return true;
    }

    LocalPayment.prototype.delete_order_source = function () {
        return new Promise(function (resolve, reject) {
            $.ajax({
                url: this.params.routes.delete_order_source,
                method: 'DELETE',
                dataType: 'json',
                beforeSend: this.ajax_before_send.bind(this)
            }).done(function (response) {
                resolve(response);
            }.bind(this)).fail(function () {
                reject();
            }.bind(this))
        }.bind(this))
    }

    LocalPayment.prototype.update_source = function (args) {
        return new Promise(function (resolve, reject) {
            if (this.updateSourceXhr) {
                this.updateSourceXhr.abort();
            }
            this.updateSourceXhr = $.ajax({
                url: this.params.routes.update_source,
                method: 'POST',
                dataType: 'json',
                data: {
                    _wpnonce: this.params.rest_nonce,
                    updates: args,
                    source_id: this.source.id,
                    client_secret: this.source.client_secret,
                    payment_method: this.gateway_id
                }
            }).done(function (response) {
                resolve(response.source);
            }.bind(this)).fail(function () {
                reject();
            });
        }.bind(this));
    }

    /*********** iDEAL ***********/
    function IDEAL(params) {
        this.elementType = 'idealBank';
        this.confirmation_method = 'confirmIdealPayment';
        LocalPayment.call(this, params);
        window.addEventListener('hashchange', this.hashChange.bind(this));
    }

    /*********** P24 ***********/
    function P24(params) {
        this.elementType = 'p24Bank';
        this.confirmation_method = 'confirmP24Payment';
        LocalPayment.call(this, params);
        window.addEventListener('hashchange', this.hashChange.bind(this));
    }

    /******* Sepa *******/
    function Sepa(params) {
        this.elementType = 'iban';
        LocalPayment.call(this, params);
    }

    /****** Klarna ******/
    function Klarna(params) {
        LocalPayment.call(this, params);
        $(document.body).on('change', '.wc-stripe-klarna-category', this.category_change.bind(this));
        $('form.checkout').on('change', '.form-row:not(.address-field):not(#account_password_field) .input-text, .form-row:not(.address-field) select', this.input_change.bind(this));
    }

    function FPX(params) {
        this.elementType = 'fpxBank';
        this.confirmation_method = 'confirmFpxPayment';
        LocalPayment.call(this, params);
        window.addEventListener('hashchange', this.hashChange.bind(this));
    }

    function WeChat(params) {
        LocalPayment.call(this, params);
        window.addEventListener('hashchange', this.hashChange.bind(this));
    }

    function BECS(params) {
        this.elementType = 'auBankAccount';
        this.confirmation_method = 'confirmAuBecsDebitPayment';
        LocalPayment.call(this, params);
        window.addEventListener('hashchange', this.hashChange.bind(this));
    }

    function GrabPay(params) {
        this.confirmation_method = 'confirmGrabPayPayment';
        LocalPayment.call(this, params);
        window.addEventListener('hashchange', this.hashChange.bind(this));
    }

    function Afterpay(params) {
        this.confirmation_method = 'confirmAfterpayClearpayPayment';
        LocalPayment.call(this, params);
        window.addEventListener('hashchange', this.hashChange.bind(this));
    }

    function Boleto(params) {
        this.confirmation_method = 'confirmBoletoPayment';
        LocalPayment.call(this, params);
        window.addEventListener('hashchange', this.hashChange.bind(this));
    }

    function OXXO(params) {
        this.confirmation_method = 'confirmOxxoPayment';
        LocalPayment.call(this, params);
        window.addEventListener('hashchange', this.hashChange.bind(this));
    }

    Klarna.prototype.disable_place_order = function () {
        $('#place_order').prop('disabled', true);
    }

    Klarna.prototype.enable_place_order = function () {
        $('#place_order').prop('disabled', false);
    }

    Klarna.prototype.category_change = function (e) {
        var change_category = function () {
            $('[id^="klarna-instance-"]').slideUp();
            var category = $('[name="klarna_category"]:checked').val();
            $('#klarna-instance-' + category).slideDown();
        }.bind(this);
        // if this is triggered via code, set delay to prevent rapid change
        if (!e.originalEvent) {
            clearTimeout(this.categoryChangeTimer);
            this.categoryChangeTimer = setTimeout(change_category, 500);
        } else {
            change_category();
        }
    }

    Klarna.prototype.processConfirmation = function (source) {
        window.Klarna.Payments.init({
            client_token: source.klarna.client_token
        }, function (response) {

        }.bind(this));
        this.payment_categories = source.klarna.payment_method_categories.split(",");
        this.render_ui(true).then(function () {
            this.enable_place_order();
        }.bind(this));
    }

    Klarna.prototype.hashchange = function () {
        if (this.is_gateway_selected()) {
            history.pushState({}, '', window.location.pathname);
            this.get_form().removeClass('processing');
            this.get_form().submit();
        }
    }

    Klarna.prototype.render_ui = function (trigger_change) {
        return new Promise(function (resolve) {
            if (this.payment_categories.length > 0) {
                $('#place_order').prop('disabled', true);
                var promises = [];
                $('#wc_stripe_local_payment_stripe_klarna').show();
                for (var i = 0; i < this.payment_categories.length; i++) {
                    var container = '#klarna-instance-' + this.payment_categories[i];
                    $(container).empty();
                    if ($('#klarna-category-' + this.payment_categories[i]).length) {
                        if (this.params.translate) {
                            var text = this.source.klarna[this.payment_categories[i] + '_name'];
                            $('label[for="klarna_' + this.payment_categories[i] + '"').text(text);
                        }
                        $('#klarna-category-' + this.payment_categories[i]).show();
                        try {
                            promises.push(new Promise(function (resolve) {
                                window.Klarna.Payments.load({
                                    container: container,
                                    payment_method_category: this.payment_categories[i],
                                    instance_id: 'klarna-instance-' + this.payment_categories[i]
                                }, function (response) {
                                    if (!response.show_form) {
                                        this.source = null;
                                    }
                                    resolve();
                                }.bind(this));
                            }.bind(this)));
                        } catch (e) {
                            window.alert(e);
                            resolve();
                        }
                    }
                }
                Promise.all(promises).then(function () {
                    resolve();
                });
                if (trigger_change) {
                    $('[id^="klarna-category-"]:visible [name="klarna_category"]').first().prop('checked', true).trigger('change');
                }
            } else {
                resolve();
            }
        }.bind(this));
    }

    Klarna.prototype.place_order = function (e) {
        if (this.is_gateway_selected()) {
            e.preventDefault();
            if (this.checkout_fields_valid()) {
                window.Klarna.Payments.authorize({
                    instance_id: 'klarna-instance-' + $('[name="klarna_category"]:checked').val()
                }, function (res) {
                    if (res.approved) {
                        this.set_nonce(this.source.id);
                        this.payment_token_received = true;
                        this.get_form().submit();
                    } else {
                        if (res.error) {
                            this.submit_error(res.error);
                        } else {
                            this.submit_error(this.params.messages.klarna_error);
                        }
                    }
                }.bind(this));
            }
        }
    }

    Klarna.prototype.klarna_fields_valid = function () {
        if (this.fields.validateFields('billing')) {
            if (this.needs_shipping()) {
                if ('billing' === this.get_shipping_prefix() || ('shipping' === this.get_shipping_prefix() && this.fields.validateFields('shipping'))) {
                    return true;
                }
            } else {
                return true;
            }
        }
    }

    Klarna.prototype.initialize = function () {
        if (this.is_gateway_selected() && this.is_active() && !this.source) {
            // if fields valid create source
            if (this.klarna_fields_valid()) {
                this.createSource();
            }
        }
    }

    Klarna.prototype.createSource = function () {
        // prevents multiple calls to the createSource function
        // from interfering with eachother
        if (this.sourceCreated) {
            return;
        }
        this.sourceCreated = true;
        this.show_loader();
        this.disable_place_order();
        this.stripe.createSource(this.getSourceArgs()).then(function (response) {
            // create payment sections
            this.hide_loader();
            if (response.error) {
                return this.submit_error(response.error.message);
            }
            this.source = response.source;
            this.set_nonce(this.source.id);
            this.filter_payment_method_categories();
            this.processConfirmation(this.source);
        }.bind(this)).catch(function (err) {
            this.sourceCreated = false;
            this.enable_place_order();
            this.hide_loader();
            this.submit_error(err.message);
        }.bind(this));
    }

    Klarna.prototype.getSourceArgs = function () {
        return $.extend(true, {}, this.get_gateway_data().source_args, (function () {
            var args = {
                owner: {
                    name: this.fields.get('billing_first_name') + ' ' + this.fields.get('billing_last_name'),
                    email: this.fields.get('billing_email'),
                    address: {
                        city: this.fields.get('billing_city'),
                        country: this.fields.get('billing_country'),
                        line1: this.fields.get('billing_address_1'),
                        line2: this.fields.get('billing_address_2'),
                        postal_code: this.fields.get('billing_postcode'),
                        state: this.fields.get('billing_state')
                    }
                },
                klarna: {
                    purchase_country: this.fields.get('billing_country'),
                    first_name: this.fields.get('billing_first_name'),
                    last_name: this.fields.get('billing_last_name')
                }
            }
            if (this.needs_shipping()) {
                var prefix = this.get_shipping_prefix();
                args.klarna.shipping_first_name = this.fields.get('first_name', prefix);
                args.klarna.shipping_last_name = this.fields.get('last_name', prefix);
                args.source_order = {
                    shipping: {
                        address: {
                            city: this.fields.get('city', prefix),
                            country: this.fields.get('country', prefix),
                            line1: this.fields.get('address_1', prefix),
                            line2: this.fields.get('address_2', prefix),
                            postal_code: this.fields.get('postcode', prefix),
                            state: this.fields.get('state', prefix)
                        }
                    }
                }
            }
            return args;
        }.bind(this)()));
    }

    Klarna.prototype.updated_checkout = function () {
        LocalPayment.prototype.updated_checkout.apply(this, arguments);
        if (this.source && this.is_active()) {
            // update the source
            this.update_source();

        } else {
            if (this.is_gateway_selected() && this.is_active() && this.klarna_fields_valid()) {
                this.createSource();
            }
        }
    }

    Klarna.prototype.update_source = function () {
        var args = this.get_source_update_args(this.getSourceArgs());
        this.show_loader();
        this.disable_place_order();
        LocalPayment.prototype.update_source.call(this, args).then(function (source) {
            this.source = source;
            this.filter_payment_method_categories();
            this.hide_loader();
            this.render_ui().then(this.enable_place_order.bind(this));
        }.bind(this)).catch(this.enable_place_order.bind(this));
    }

    Klarna.prototype.checkout_error = function () {
        LocalPayment.prototype.checkout_error.apply(this, arguments);
        if (this.is_gateway_selected()) {
            this.createSource();
        }
    }

    Klarna.prototype.show_loader = function () {
        $(this.container).find('.wc-stripe-klarna-loader').remove();
        $(this.container).find('label[for="payment_method_' + this.gateway_id + '" ]').after(this.params.klarna_loader);
    }

    Klarna.prototype.hide_loader = function () {
        $(this.container).find('.wc-stripe-klarna-loader').remove();
    }

    Klarna.prototype.filter_payment_method_categories = function () {
        var categories = this.source.klarna.payment_method_categories.split(',');
        this.source.klarna.payment_method_categories = categories.filter(function (value) {
            return this.get_gateway_data().payment_sections.indexOf(value) > -1;
        }.bind(this)).join(',');
    }

    Klarna.prototype.get_source_update_args = function (args) {
        function reduce(obj, k) {
            if (k.indexOf('.') > -1) {
                var keys = k.split('.');
                var obj2 = keys.slice(0, keys.length - 1).reduce(function (obj, k) {
                    return obj[k];
                }, obj);
                k = keys[keys.length - 1];
                delete obj2[k];
                return obj;
            }
            delete obj[k];
            return obj;
        }

        return ['type', 'currency', 'statement_descriptor', 'redirect', 'klarna.product', 'klarna.locale', 'klarna.custom_payment_methods'].reduce(reduce, args);
    }

    Klarna.prototype.on_payment_method_selected = function (e, payment_method) {
        if (payment_method === this.gateway_id) {
            // only need an email address to create a Klarna source
            if (!this.source) {
                if (this.klarna_fields_valid()) {
                    this.createSource();
                } else {
                    this.submit_error(this.params.messages.required_field);
                }
            }
            wc_stripe.CheckoutGateway.prototype.on_payment_method_selected.apply(this, arguments);
        }
    }

    Klarna.prototype.input_change = function () {
        // if the fields are valid, then create the source if it doesn't exist
        if (this.is_gateway_selected()) {
            if (!this.source) {
                if (this.klarna_fields_valid()) {
                    this.createSource();
                }
            } else {
                this.update_source();
            }
        }
    }

    WeChat.prototype.updated_checkout = function () {
        if (!this.script_loaded && $(this.container).length) {
            this.load_external_script(this.params.qr_script);
        }
        LocalPayment.prototype.updated_checkout.apply(this, arguments);
    }

    WeChat.prototype.hashChange = function (e) {
        if (this.is_gateway_selected()) {
            var match = e.newURL.match(/qrcode=(.*)/);
            if (match) {
                history.pushState({}, '', window.location.pathname);
                this.qrcode = JSON.parse(window.atob(decodeURIComponent(match[1])));
                this.get_form().unblock().removeClass('processing').addClass('wechat');
                var qrCode = new QRCode('wc_stripe_local_payment_stripe_wechat', {
                    text: this.qrcode.code,
                    width: parseInt(this.params.qr_size),
                    height: parseInt(this.params.qr_size),
                    colorDark: '#424770',
                    colorLight: '#f8fbfd',
                    correctLevel: QRCode.CorrectLevel.H,
                });
                $('#wc_stripe_local_payment_stripe_wechat').append('<p class="qrcode-message">' + this.params.qr_message + '</p>');
                this.payment_token_received = true;
                this.show_place_order();
            }
        }
    }

    WeChat.prototype.place_order = function () {
        if (this.get_form().is('.wechat')) {
            window.location = this.qrcode.redirect;
        } else {
            LocalPayment.prototype.place_order.apply(this, arguments);
        }
    }

    Sepa.prototype.getSourceArgs = function () {
        var args = $.extend({}, LocalPayment.prototype.getSourceArgs.apply(this, arguments), {
            mandate: {
                notification_method: 'email',
                interval: this.cart_contains_subscription() || this.is_change_payment_method() ? 'scheduled' : 'one_time'
            }
        });
        if (args.mandate.interval === 'scheduled') {
            delete args.amount;
        }
        return args;
    }

    Afterpay.prototype.updated_checkout = function () {
        this.maybe_hide_gateway();
        this.add_eligibility(this.container, parseFloat(this.get_total_price()));
        // re-insert the messaging
        if (this.msgElement && $(this.container).length) {
            // create new elements object since country code could have changed
            this.elements = this.stripe.elements(this.get_element_options());
            this.initialize_messaging();
        }
    }

    Afterpay.prototype.initialize = function () {
        this.add_eligibility(this.container, parseFloat(this.get_total_price()));
        this.initialize_messaging();
    }

    Afterpay.prototype.initialize_messaging = function () {
        this.msgElement = this.elements.create('afterpayClearpayMessage', $.extend({}, this.params.msg_options, {
            amount: this.get_total_price_cents(),
            currency: this.get_currency()
        }));
        this.mount_message();
    }

    Afterpay.prototype.mount_message = function (update) {
        if (update) {
            this.msgElement.update({
                amount: this.get_total_price_cents(),
                currency: this.get_currency()
            });
        }
        var $el = $('label[for="payment_method_stripe_afterpay"]').find('#wc-stripe-afterpay-msg');
        if (!$el.length) {
            $('label[for="payment_method_stripe_afterpay"]').append('<div id="wc-stripe-afterpay-msg"></div>');
        }
        this.msgElement.mount('#wc-stripe-afterpay-msg');
    }

    Afterpay.prototype.get_element_options = function () {
        var locale = this.params.locale;
        if (this.fields.get('billing_country') == 'GB' && ['fr-FR', 'it-IT', 'es-ES'].indexOf(locale) < 0) {
            locale = 'en-GB';
        } else if (this.params.supported_locales.indexOf(this.params.locale) < 0) {
            locale = 'auto';
        }
        return {
            'locale': locale
        }
    }

    Afterpay.prototype.add_eligibility = function (selector, price) {
        wc_stripe.Afterpay.prototype.add_eligibility.apply(this, arguments);
        if (!this.is_eligible(price)) {
            $(this.container).find('.wc-stripe-afterpay__offsite').addClass('afterpay-ineligible');
        }
    }

    Boleto.prototype.get_confirmation_args = function (obj) {
        var args = LocalPayment.prototype.get_confirmation_args.call(this, obj);
        args.payment_method.boleto = {
            tax_id: this.get_tax_id()
        };
        return args;
    }

    Boleto.prototype.createSource = function () {
        var tax_id = this.get_tax_id();
        if (!tax_id || !tax_id.match(/^(\w{3}\.){2}\w{3}-\w{2}$|^(\w{11}|\w{14})$|^\w{2}\.\w{3}\.\w{3}\/\w{4}-\w{2}$/)) {
            return this.submit_error({code: 'incomplete_boleto_tax_id'});
        } else {
            this.payment_token_received = true;
            this.get_form().submit();
        }
    }

    Boleto.prototype.get_tax_id = function () {
        return $('#wc_stripe_boleto_tax_id').val();
    }

    IDEAL.prototype = $.extend({}, LocalPayment.prototype, IDEAL.prototype);

    P24.prototype = $.extend({}, LocalPayment.prototype, P24.prototype);

    Sepa.prototype = $.extend({}, LocalPayment.prototype, Sepa.prototype);

    Klarna.prototype = $.extend({}, LocalPayment.prototype, Klarna.prototype);

    FPX.prototype = $.extend({}, LocalPayment.prototype, FPX.prototype);

    WeChat.prototype = $.extend({}, LocalPayment.prototype, WeChat.prototype);

    BECS.prototype = $.extend({}, LocalPayment.prototype, BECS.prototype);

    GrabPay.prototype = $.extend({}, LocalPayment.prototype, GrabPay.prototype);

    Afterpay.prototype = $.extend({}, LocalPayment.prototype, wc_stripe.Afterpay.prototype, Afterpay.prototype);

    Boleto.prototype = $.extend({}, LocalPayment.prototype, Boleto.prototype);

    OXXO.prototype = $.extend({}, LocalPayment.prototype, OXXO.prototype);

    /**
     * Local payment types that require JS integration
     * @type {Object}
     */
    var types = {
        'ideal': IDEAL,
        'p24': P24,
        'sepa_debit': Sepa,
        'klarna': Klarna,
        'fpx': FPX,
        'wechat': WeChat,
        'au_becs_debit': BECS,
        'grabpay': GrabPay,
        'afterpay_clearpay': Afterpay,
        'boleto': Boleto,
        'oxxo': OXXO
    }

    for (var i in wc_stripe_local_payment_params.gateways) {
        var params = wc_stripe_local_payment_params.gateways[i];
        if (types[params.local_payment_type]) {
            new types[params.local_payment_type](params);
        } else {
            new LocalPayment(params);
        }
    }

}(jQuery, window.wc_stripe))