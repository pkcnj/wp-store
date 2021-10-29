import {CardElement} from "@stripe/react-stripe-js";
import {isFieldRequired} from "../../util";
import {useMemo} from '@wordpress/element';

const StripeCardForm = ({getData, billing, onChange}) => {
    const cardOptions = useMemo(() => {
        return {
            ...{
                value: {
                    postalCode: billing?.billingData?.postcode
                },
                hidePostalCode: isFieldRequired('postcode'),
                iconStyle: 'default'
            }, ...getData('cardOptions')
        };
    }, [billing.billingData]);
    return (
        <div className='wc-stripe-inline-form'>
            <CardElement options={cardOptions} onChange={onChange}/>
        </div>
    )
}

export default StripeCardForm;