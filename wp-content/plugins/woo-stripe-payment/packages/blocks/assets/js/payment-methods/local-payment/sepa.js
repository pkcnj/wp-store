import {registerPaymentMethod} from '@woocommerce/blocks-registry';
import {getSettings, cartContainsPreOrder, cartContainsSubscription} from "../util";
import {LocalPaymentSourceContent} from './local-payment-method';
import {PaymentMethodLabel, PaymentMethod} from "../../components/checkout";
import {canMakePayment} from "./local-payment-method";
import {IbanElement} from "@stripe/react-stripe-js";

const getData = getSettings('stripe_sepa_data');

const getSourceArgs = (args, {billingData}) => {
    args.mandate = {
        notification_method: billingData.email ? 'email' : 'manual',
        interval: cartContainsSubscription() || cartContainsPreOrder() ? 'scheduled' : 'one_time'
    }
    if (args.mandate.interval === 'scheduled') {
        delete args.amount;
    }
    return args;
}

const LocalPaymentMethod = (PaymentMethod) => ({getData, ...props}) => {
    return (
        <>
            <PaymentMethod {...{...props, getData}}/>
            <div className={'wc-stripe-blocks-sepa__mandate'}>
                {getData('mandate')}
            </div>
        </>
    )
}

const SepaPaymentMethod = LocalPaymentMethod(PaymentMethod);

if (getData()) {
    registerPaymentMethod({
        name: getData('name'),
        label: <PaymentMethodLabel
            title={getData('title')}
            paymentMethod={getData('name')}
            icons={getData('icon')}/>,
        ariaLabel: 'SEPA',
        placeOrderButtonLabel: getData('placeOrderButtonLabel'),
        canMakePayment: canMakePayment(getData),
        content: <SepaPaymentMethod
            content={LocalPaymentSourceContent}
            getData={getData}
            element={IbanElement}
            getSourceArgs={getSourceArgs}/>,
        edit: <LocalPaymentSourceContent getData={getData}/>,
        supports: {
            showSavedCards: false,
            showSaveOption: false,
            features: getData('features')
        }
    })
}