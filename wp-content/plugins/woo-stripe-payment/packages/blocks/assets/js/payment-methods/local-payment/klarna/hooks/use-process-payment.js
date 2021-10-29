import {useEffect} from '@wordpress/element';
import {ensureErrorResponse, ensureSuccessResponse, deleteFromCache} from "../../../util";
import {__} from "@wordpress/i18n";

export const useProcessPayment = (
    {
        payment_method,
        source_id,
        paymentCategory,
        onPaymentProcessing,
        responseTypes
    }) => {
    useEffect(() => {
            const unsubscribe = onPaymentProcessing(() => {
                return new Promise(resolve => {
                    // authorize the Klarna payment
                    Klarna.Payments.authorize({
                        payment_method_category: paymentCategory
                    }, (response) => {
                        if (response.approved) {
                            deleteFromCache('klarna:source');
                            // add the source to the response
                            resolve(ensureSuccessResponse(responseTypes, {
                                meta: {
                                    paymentMethodData: {
                                        [`${payment_method}_token_key`]: source_id
                                    }
                                }
                            }));
                        } else {
                            resolve(ensureErrorResponse(responseTypes, response.error || __('Your purchase is not approved.', 'woo-stripe-payment')));
                        }
                    });
                });
            });
            return () => unsubscribe();
        }, [
            source_id,
            paymentCategory,
            onPaymentProcessing
        ]
    );
}