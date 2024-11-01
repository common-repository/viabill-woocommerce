const viabill_official_settings = window.wc.wcSettings.getSetting( 'viabill_official_data', {} );

const viabill_official_label = window.wp.htmlEntities.decodeEntities( viabill_official_settings.title ) || window.wp.i18n.__( 'Viabill - Monthly Payments', 'viabill' );
const ViabillOfficialContent = () => {
    return window.wp.htmlEntities.decodeEntities( viabill_official_settings.description || '' );
};
const Viabill_Block_Gateway = {
    name: 'viabill_official',
    label: viabill_official_label,
    content: Object( window.wp.element.createElement )( ViabillOfficialContent, null ),
    edit: Object( window.wp.element.createElement )( ViabillOfficialContent, null ),
    canMakePayment: () => true,
    ariaLabel: viabill_official_label,
    supports: {
        features: viabill_official_settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Viabill_Block_Gateway );