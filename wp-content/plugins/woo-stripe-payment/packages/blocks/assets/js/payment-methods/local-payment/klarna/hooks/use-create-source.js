import {useEffect, useState, useRef, useCallback} from '@wordpress/element';
import {useStripe} from "@stripe/react-stripe-js";
import {useStripeError} from "../../../hooks";
import {getDefaultSourceArgs, getRoute, isAddressValid, StripeError, storeInCache, getFromCache} from "../../../util";
import apiFetch from "@wordpress/api-fetch";

export const useCreateSource = (
    {
        getData,
        billing,
        shippingData,
    }) => {
    const stripe = useStripe();
    const [error, setError] = useStripeError();
    const abortController = useRef(new AbortController());
    const currentSourceArgs = useRef({});
    const oldSourceArgs = useRef({});
    const [isLoading, setIsLoading] = useState(false);
    const [source, setSource] = useState(false);
    const {billingData, cartTotal, cartTotalItems, currency} = billing;
    const isCheckoutValid = useCallback(({billingData, shippingData}) => {
        const {needsShipping, shippingAddress} = shippingData;
        if (isAddressValid(billingData)) {
            if (needsShipping) {
                return isAddressValid(shippingAddress);
            }
            return true;
        }
        return false;
    }, []);
    const getLineItems = useCallback((cartTotalItems, currency) => {
        const items = [];
        cartTotalItems.forEach(item => {
            items.push({
                amount: item.value,
                currency,
                description: item.label,
                quantity: 1
            });
        });
        return items;
    }, []);

    const getSourceArgs = useCallback(({cartTotal, cartTotalItems, billingData, currency, shippingData}) => {
        const {first_name, last_name, country} = billingData;
        const {needsShipping, shippingAddress} = shippingData;
        let args = getDefaultSourceArgs({
            type: getData('paymentType'),
            amount: cartTotal.value,
            billingData,
            currency: currency.code,
            returnUrl: getData('returnUrl')
        });
        args = {
            ...args, ...{
                source_order: {
                    items: getLineItems(cartTotalItems, currency.code)
                },
                klarna: {
                    locale: getData('locale'),
                    product: 'payment',
                    purchase_country: country,
                    first_name,
                    last_name
                }
            }
        }
        if (country == 'US') {
            args.klarna.custom_payment_methods = 'payin4,installments';
        }
        if (needsShipping) {
            args.klarna = {
                ...args.klarna, ...{
                    shipping_first_name: shippingAddress.first_name,
                    shipping_last_name: shippingAddress.last_name
                }
            }
            args.source_order.shipping = {
                address: {
                    city: shippingAddress.city || '',
                    country: shippingAddress.country || '',
                    line1: shippingAddress.address_1 || '',
                    line2: shippingAddress.address_2 || '',
                    postal_code: shippingAddress.postcode || '',
                    state: shippingAddress.state || ''
                }
            }
        }
        oldSourceArgs.current = currentSourceArgs.current;
        currentSourceArgs.current = args;
        return args;
    }, []);
    const filterUpdateSourceArgs = (args) => {
        return ['type', 'currency', 'statement_descriptor', 'redirect', 'klarna.product', 'klarna.locale', 'klarna.custom_payment_methods'].reduce((obj, k) => {
            if (k.indexOf('.') > -1) {
                let keys = k.split('.');
                let obj2 = keys.slice(0, keys.length - 1).reduce((obj, k) => {
                    return obj[k];
                }, obj);
                k = keys[keys.length - 1];
                delete obj2[k];
                return obj;
            }
            delete obj[k];
            return obj;
        }, args);
    }

    const compareSourceArgs = useCallback((args, args2) => {
        const getArgs = (args1, args2, key = false) => {
            let newArgs = {};
            if (args1 && typeof args1 === 'object' && !Array.isArray(args1)) {
                for (let key of Object.keys(args1)) {
                    if (typeof args1[key] === 'object' && !Array.isArray(args1[key])) {
                        newArgs[key] = getArgs(args1[key], args2[key]);
                    } else {
                        newArgs[key] = args2[key];
                    }
                }
            } else {
                newArgs = args1;
            }

            return newArgs;
        }
        const newArgs = getArgs(args, args2);
        return JSON.stringify(args) == JSON.stringify(newArgs);
    }, []);
    const createSource = useCallback(async (
        {
            billingData,
            shippingData,
            cartTotal,
            cartTotalItems,
            currency,
        }) => {
        let args = getSourceArgs({
            cartTotal,
            cartTotalItems,
            billingData,
            currency,
            shippingData
        });
        try {
            let result = await stripe.createSource(args);
            if (result.error) {
                throw new StripeError(result.error);
            }
            storeInCache('klarna:source', {[currency.code]: {source: result.source, args: currentSourceArgs.current}});
            setSource(result.source);
        } catch (err) {
            console.log(err);
            setError(err.error);
        }
    }, [stripe]);

    const updateSource = useCallback(async ({source, updates, currency}) => {

        const data = {
            updates,
            source_id: source.id,
            client_secret: source.client_secret,
            payment_method: getData('name')
        };
        try {
            abortController.current.abort();
            abortController.current = new AbortController();
            let result = await apiFetch({
                url: getRoute('update/source'),
                method: 'POST',
                data,
                signal: abortController.current.signal
            });
            if (result.source) {
                storeInCache('klarna:source', {[currency]: {source, args: currentSourceArgs.current}});
                setSource(result.source);
            }
        } catch (err) {
            console.log('update aborted');
        }
    }, []);

    // Create the source if the required data is available
    useEffect(() => {
        if (!source) {
            if (getFromCache('klarna:source')?.[currency.code]) {
                const {source, args} = getFromCache('klarna:source')[currency.code];
                currentSourceArgs.current = args;
                setSource(source);
            } else {
                if (stripe && isCheckoutValid({billingData, shippingData})) {
                    setIsLoading(true);
                    createSource({
                        billingData,
                        shippingData,
                        cartTotal,
                        cartTotalItems,
                        currency
                    }).then(() => setIsLoading(false));
                }
            }
        }
    }, [
        stripe,
        source?.id,
        createSource,
        billingData,
        cartTotal.value,
        shippingData,
        setIsLoading,
        cartTotalItems,
        currency.code
    ]);

    // update the source if data has changed and the source exists
    useEffect(() => {
        if (stripe && source) {
            // perform a comparison to see if the source needs to be updated
            const updates = filterUpdateSourceArgs(getSourceArgs({
                billingData,
                cartTotal,
                cartTotalItems,
                currency,
                shippingData
            }));
            if (!compareSourceArgs(updates, oldSourceArgs.current)) {
                updateSource({source, updates, currency: currency.code});
            }
        }
    }, [
        stripe,
        source?.id,
        billingData,
        cartTotal.value,
        cartTotalItems,
        shippingData,
        currency.code
    ]);

    return {source, setSource, isLoading};
}