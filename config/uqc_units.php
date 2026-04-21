<?php

/**
 * Unit Quantity Codes (UQC) — CBIC-notified unit codes used on GST invoices
 * and GSTR-1 filings. Source: CBIC / GSTN taxpayer's manual, Annexure B.
 *
 * Always store the code (e.g. "NOS", "KGS"), show the label in dropdowns.
 * These codes are mandatory on the e-invoice IRN portal and recommended on
 * every tax invoice so that GSTR-1 reconciliation is automatic.
 */

return [

    'codes' => [
        ['code' => 'NOS', 'label' => 'NOS · Numbers / Count'],
        ['code' => 'PCS', 'label' => 'PCS · Pieces'],
        ['code' => 'BOX', 'label' => 'BOX · Box'],
        ['code' => 'BAG', 'label' => 'BAG · Bags'],
        ['code' => 'BTL', 'label' => 'BTL · Bottles'],
        ['code' => 'BDL', 'label' => 'BDL · Bundles'],
        ['code' => 'CAN', 'label' => 'CAN · Cans'],
        ['code' => 'CBM', 'label' => 'CBM · Cubic metre'],
        ['code' => 'CCM', 'label' => 'CCM · Cubic centimetre'],
        ['code' => 'DOZ', 'label' => 'DOZ · Dozen'],
        ['code' => 'DRM', 'label' => 'DRM · Drums'],
        ['code' => 'GMS', 'label' => 'GMS · Grams'],
        ['code' => 'GGK', 'label' => 'GGK · Great Gross'],
        ['code' => 'GRS', 'label' => 'GRS · Gross'],
        ['code' => 'KGS', 'label' => 'KGS · Kilograms'],
        ['code' => 'KLR', 'label' => 'KLR · Kilolitre'],
        ['code' => 'KME', 'label' => 'KME · Kilometre'],
        ['code' => 'LTR', 'label' => 'LTR · Litres'],
        ['code' => 'MLT', 'label' => 'MLT · Millilitre'],
        ['code' => 'MTR', 'label' => 'MTR · Metres'],
        ['code' => 'MTS', 'label' => 'MTS · Metric tons'],
        ['code' => 'PAC', 'label' => 'PAC · Packs'],
        ['code' => 'PRS', 'label' => 'PRS · Pairs'],
        ['code' => 'QTL', 'label' => 'QTL · Quintal'],
        ['code' => 'ROL', 'label' => 'ROL · Rolls'],
        ['code' => 'SET', 'label' => 'SET · Sets'],
        ['code' => 'SQF', 'label' => 'SQF · Square feet'],
        ['code' => 'SQM', 'label' => 'SQM · Square metres'],
        ['code' => 'TBS', 'label' => 'TBS · Tablets'],
        ['code' => 'TON', 'label' => 'TON · Tonnes'],
        ['code' => 'TUB', 'label' => 'TUB · Tubes'],
        ['code' => 'UNT', 'label' => 'UNT · Units'],
        ['code' => 'YDS', 'label' => 'YDS · Yards'],
        ['code' => 'OTH', 'label' => 'OTH · Others'],
    ],

    /** Kind: GOODS need HSN (4/6/8 digits), SERVICES need SAC (starts with 99). */
    'kinds' => [
        'goods'    => 'Goods (HSN code)',
        'service'  => 'Service (SAC code — starts with 99)',
    ],

];
