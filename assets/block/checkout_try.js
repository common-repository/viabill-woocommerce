const viabill_try_settings = window.wc.wcSettings.getSetting( 'viabill_try_data', {} );

const viabill_try_label = window.wp.htmlEntities.decodeEntities( viabill_try_settings.title ) || window.wp.i18n.__( 'Viabill - Monthly Payments', 'viabill' );
const ViabillTryContent = () => {
    return window.wp.htmlEntities.decodeEntities( viabill_try_settings.description || '' );
};
const Viabill_Try_Block_Gateway = {
    name: 'viabill_try',
    label: viabill_try_label,
    content: Object( window.wp.element.createElement )( ViabillTryContent, null ),
    edit: Object( window.wp.element.createElement )( ViabillTryContent, null ),
    canMakePayment: () => true,
    ariaLabel: viabill_try_label,
    supports: {
        features: viabill_try_settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Viabill_Try_Block_Gateway );