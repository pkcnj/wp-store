import {useEffect, useState} from '@wordpress/element';
import {registerPaymentMethod} from '@woocommerce/blocks-registry';
import {__} from '@wordpress/i18n';
import {useProcessCheckoutError} from '../../hooks';
import {
    getSettings,
    initStripe,
    isTestMode
} from "../../util";
import {PaymentMethodLabel, PaymentMethod} from "../../../components/checkout";
import {canMakePayment} from "../local-payment-method";
import {Elements} from "@stripe/react-stripe-js";
import {KlarnaPaymentCategories} from "./categories";
import {KlarnaLoader} from "./loader";
import {useCreateSource, useProcessPayment} from "./hooks";
import './styles.scss';

const getData = getSettings('stripe_klarna_data');

const KlarnaContainer = (props) => {
    return (
        <Elements stripe={initStripe}>
            <KlarnaPaymentMethod {...props}/>
        </Elements>
    );
}

const KlarnaPaymentMethod = (
    {
        getData,
        billing,
        shippingData,
        emitResponse,
        eventRegistration
    }) => {
    const {responseTypes} = emitResponse;
    const {onPaymentProcessing, onCheckoutAfterProcessingWithError} = eventRegistration;
    const [selected, setSelected] = useState('');
    const [klarnaInitialized, setKlarnaInitialized] = useState(false);
    const getCategoriesFromSource = (source) => {
        const paymentMethodCategories = source.klarna.payment_method_categories.split(',');
        const categories = [];
        for (let type of Object.keys(getData('categories'))) {
            if (paymentMethodCategories.includes(type)) {
                categories.push({type, label: getData('categories')[type]});
            }
        }
        return categories;
    }

    const {source, isLoading} = useCreateSource({
        getData,
        billing,
        shippingData
    });

    useProcessPayment({
        payment_method: getData('name'),
        source_id: source.id,
        paymentCategory: selected,
        onPaymentProcessing,
        responseTypes
    });

    useProcessCheckoutError({responseTypes, subscriber: onCheckoutAfterProcessingWithError});

    useEffect(() => {
        if (!selected && source) {
            const categories = getCategoriesFromSource(source);
            if (categories.length) {
                setSelected(categories.shift().type);
            }

        }
    }, [source]);

    useEffect(() => {
        if (source) {
            Klarna.Payments.init({
                client_token: source.klarna.client_token
            });
            setKlarnaInitialized(true);
        }
    }, [source?.id]);

    if (source && klarnaInitialized) {
        const categories = getCategoriesFromSource(source);
        return (
            <>
                {isTestMode() &&
                <div className="wc-stripe-klarna__testmode">
                    <label>{__('Test mode sms', 'woo-stripe-payment')}:</label>&nbsp;<span>123456</span>
                </div>}
                <KlarnaPaymentCategories
                    source={source}
                    categories={categories}
                    selected={!selected && categories.length > 0 ? categories[0].type : selected}
                    onChange={setSelected}/>
            </>
        )
    } else {
        if (isLoading) {
            return <KlarnaLoader/>
        }
    }
    return (
        <div className='wc-stripe-blocks-klarna__notice'>
            {__('Please fill out all required fields before paying with Klarna.', 'woo-stripe-payment')}
        </div>
    );
}

if (getData()) {
    registerPaymentMethod({
        name: getData('name'),
        label: <PaymentMethodLabel
            title={getData('title')}
            paymentMethod={getData('name')}
            icons={getData('icon')}/>,
        ariaLabel: 'Klarna',
        placeOrderButtonLabel: getData('placeOrderButtonLabel'),
        canMakePayment: canMakePayment(getData, ({settings, billingData, cartTotals}) => {
            const {country} = billingData;
            const {currency_code: currency} = cartTotals;
            const requiredParams = settings('requiredParams');
            return [currency] in requiredParams && requiredParams[currency].includes(country);
        }),
        content: <PaymentMethod
            getData={getData}
            content={KlarnaContainer}/>,
        edit: <PaymentMethod
            getData={getData}
            content={KlarnaContainer}/>,
        supports: {
            showSavedCards: false,
            showSaveOption: false,
            features: getData('features')
        }
    })
}
