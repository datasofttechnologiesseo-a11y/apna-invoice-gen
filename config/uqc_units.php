<?php

/**
 * Unit Quantity Codes (UQC) - CBIC-notified unit codes used on GST invoices
 * and GSTR-1 filings. Source: CBIC / GSTN taxpayer's manual, Annexure B, and
 * the UQC list of the GSTR-1 offline tool (all 45 codes, OTH included).
 *
 * Always store the code (e.g. "NOS", "KGS"), show the label in dropdowns.
 * These codes are mandatory on the e-invoice IRN portal and recommended on
 * every tax invoice so that GSTR-1 reconciliation is automatic.
 *
 * "portal" is the code's description in the offline tool's Excel template
 * ("NOS-NUMBERS"); GSTR-1 Table 12 reports the code itself in the JSON.
 */

return [

    'codes' => [
        ['code' => 'NOS', 'label' => 'NOS · Numbers / Count', 'portal' => 'NUMBERS'],
        ['code' => 'PCS', 'label' => 'PCS · Pieces', 'portal' => 'PIECES'],
        ['code' => 'BOX', 'label' => 'BOX · Box', 'portal' => 'BOX'],
        ['code' => 'BAG', 'label' => 'BAG · Bags', 'portal' => 'BAGS'],
        ['code' => 'BAL', 'label' => 'BAL · Bale', 'portal' => 'BALE'],
        ['code' => 'BTL', 'label' => 'BTL · Bottles', 'portal' => 'BOTTLES'],
        ['code' => 'BDL', 'label' => 'BDL · Bundles', 'portal' => 'BUNDLES'],
        ['code' => 'BKL', 'label' => 'BKL · Buckles', 'portal' => 'BUCKLES'],
        ['code' => 'BOU', 'label' => 'BOU · Billion units', 'portal' => 'BILLION OF UNITS'],
        ['code' => 'BUN', 'label' => 'BUN · Bunches', 'portal' => 'BUNCHES'],
        ['code' => 'CAN', 'label' => 'CAN · Cans', 'portal' => 'CANS'],
        ['code' => 'CBM', 'label' => 'CBM · Cubic metre', 'portal' => 'CUBIC METERS'],
        ['code' => 'CCM', 'label' => 'CCM · Cubic centimetre', 'portal' => 'CUBIC CENTIMETERS'],
        ['code' => 'CMS', 'label' => 'CMS · Centimetres', 'portal' => 'CENTIMETERS'],
        ['code' => 'CTN', 'label' => 'CTN · Cartons', 'portal' => 'CARTONS'],
        ['code' => 'DOZ', 'label' => 'DOZ · Dozen', 'portal' => 'DOZENS'],
        ['code' => 'DRM', 'label' => 'DRM · Drums', 'portal' => 'DRUMS'],
        ['code' => 'GMS', 'label' => 'GMS · Grams', 'portal' => 'GRAMMES'],
        ['code' => 'GGK', 'label' => 'GGK · Great Gross', 'portal' => 'GREAT GROSS'],
        ['code' => 'GRS', 'label' => 'GRS · Gross', 'portal' => 'GROSS'],
        ['code' => 'GYD', 'label' => 'GYD · Gross yards', 'portal' => 'GROSS YARDS'],
        ['code' => 'KGS', 'label' => 'KGS · Kilograms', 'portal' => 'KILOGRAMS'],
        ['code' => 'KLR', 'label' => 'KLR · Kilolitre', 'portal' => 'KILOLITRE'],
        ['code' => 'KME', 'label' => 'KME · Kilometre', 'portal' => 'KILOMETRE'],
        ['code' => 'LTR', 'label' => 'LTR · Litres', 'portal' => 'LITRES'],
        ['code' => 'MLT', 'label' => 'MLT · Millilitre', 'portal' => 'MILILITRE'],
        ['code' => 'MTR', 'label' => 'MTR · Metres', 'portal' => 'METERS'],
        ['code' => 'MTS', 'label' => 'MTS · Metric tons', 'portal' => 'METRIC TON'],
        ['code' => 'PAC', 'label' => 'PAC · Packs', 'portal' => 'PACKS'],
        ['code' => 'PRS', 'label' => 'PRS · Pairs', 'portal' => 'PAIRS'],
        ['code' => 'QTL', 'label' => 'QTL · Quintal', 'portal' => 'QUINTAL'],
        ['code' => 'ROL', 'label' => 'ROL · Rolls', 'portal' => 'ROLLS'],
        ['code' => 'SET', 'label' => 'SET · Sets', 'portal' => 'SETS'],
        ['code' => 'SQF', 'label' => 'SQF · Square feet', 'portal' => 'SQUARE FEET'],
        ['code' => 'SQM', 'label' => 'SQM · Square metres', 'portal' => 'SQUARE METERS'],
        ['code' => 'SQY', 'label' => 'SQY · Square yards', 'portal' => 'SQUARE YARDS'],
        ['code' => 'TBS', 'label' => 'TBS · Tablets', 'portal' => 'TABLETS'],
        ['code' => 'TGM', 'label' => 'TGM · Ten gross', 'portal' => 'TEN GROSS'],
        ['code' => 'THD', 'label' => 'THD · Thousands', 'portal' => 'THOUSANDS'],
        ['code' => 'TON', 'label' => 'TON · Tonnes', 'portal' => 'TONNES'],
        ['code' => 'TUB', 'label' => 'TUB · Tubes', 'portal' => 'TUBES'],
        ['code' => 'UGS', 'label' => 'UGS · US gallons', 'portal' => 'US GALLONS'],
        ['code' => 'UNT', 'label' => 'UNT · Units', 'portal' => 'UNITS'],
        ['code' => 'YDS', 'label' => 'YDS · Yards', 'portal' => 'YARDS'],
        ['code' => 'OTH', 'label' => 'OTH · Others', 'portal' => 'OTHERS'],
    ],

    /**
     * Free-text units on invoice lines that mean a code ("kg" is KGS), so
     * GSTR-1 Table 12 reports them under that code instead of OTH.
     */
    'aliases' => [
        'NO' => 'NOS', 'PC' => 'PCS', 'KG' => 'KGS', 'G' => 'GMS', 'GM' => 'GMS', 'L' => 'LTR', 'ML' => 'MLT',
        'M' => 'MTR', 'UNIT' => 'UNT', 'DOZEN' => 'DOZ', 'PAIR' => 'PRS',
    ],

    /** Kind: GOODS need HSN (4/6/8 digits), SERVICES need SAC (starts with 99). */
    'kinds' => [
        'goods'    => 'Goods (HSN code)',
        'service'  => 'Service (SAC code, starts with 99)',
    ],

];
