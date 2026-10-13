<?php

namespace Tests\Feature\Fintech\Reconciliation;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\State;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader;

/**
 * One financial year of books for "Sahyadri Traders", Pune (27-Maharashtra,
 * regular GST), written through the real HTTP routes in date order with the
 * clock moved to each document's moment (IST). Every figure below is worked
 * out by hand; the test classes assert the reports against these numbers and
 * against each other.
 *
 * Masters (factories): states MH 27, KA 29, GJ 24, Foreign 96.
 *   Customers  PAT Patil Traders (MH, GSTIN)    RAO Rao Industries (KA, GSTIN)
 *              SEZ Shah SEZ Unit (GJ, GSTIN)     ACME Acme Inc (96, abroad)
 *              WIP Walk-in Pune (MH, no GSTIN)   BLR Kumar Retail (KA, no GSTIN)
 *              DES Desai Pharma (MH, GSTIN; buys security services under RCM)
 *   Vendors    V1 Mumbai Metals (MH, GSTIN)  V2 Mysore Motors (KA, GSTIN)
 *              V3 Fast Freight GTA (MH, none) V4 Hotel Annapurna (MH, GSTIN)
 *              V5 Local Kirana (MH, none)     V6 Adv. Mehta (MH, none)
 *   Items (via /products) P1 Steel bracket 73269099 NOS, sells 1,000 @18%,
 *              cost 600, opening 100 | P2 Packaged tea 09023020 KGS, sells
 *              400 @5%, no cost (first bill sets 300), opening 50 | P3
 *              Generator 85023100 NOS, sells 1,50,000 @18%, no cost, opening 3.
 *
 * SALES (numbers SAH/{FY}/nnnn; tax per line: intra each head = taxable x
 * rate/2, inter IGST = taxable x rate; grand total rounded to the rupee)
 *   X0  31-03-2026 23:59 PAT B2B intra  P1 2x1,000@18    2,000  C180 S180         = 2,360   SAH/25-26/0001
 *   A1  01-04 00:01 WIP B2CS intra P1 1x1,000@18          1,000  C90 S90           = 1,180   SAH/26-27/0001
 *   A2  10-04 RAO B2B inter, invoice discount 2,000 split 1,000/1,000:
 *              P1 10x1,000-1,000 = 9,000@18 IGST 1,620; P2 25x400-1,000 = 9,000@5 IGST 450
 *                                                         18,000 I2,070            = 20,070  0002
 *   A4  20-04 BLR B2CL P3 1x1,50,000@18                  1,50,000 I27,000          = 1,77,000 0003
 *   A5  25-04 WIP B2CS intra veg 2x500@0 (nil 1,000) + P1 1x1,000@18  2,000 C90 S90 = 2,180  0004
 *   A6  28-04 PAT DRAFT 1x99,999@18 - never issued
 *   M1  05-05 PAT B2B intra svc 3x3,333.33 = 9,999.99@18; C 899.9991->900.00, S 900.00;
 *              11,799.99 -> 11,800 (round off +0.01)                               0005
 *   M2  12-05 ACME export under LUT svc 1x2,00,000@18, no tax  = 2,00,000          0006
 *   M3  18-05 SEZ with IGST P1 20x1,000@18  20,000 I3,600       = 23,600           0007
 *   M4  22-05 WIP P1 5x1,000@18 = 5,900, CANCELLED 23-05                           0008
 *   M5  31-05 DES B2B reverse charge svc 1x50,000@18, no tax collected = 50,000    0009
 *   J1  05-06 RAO B2B inter P2 100x400@5   40,000 I2,000        = 42,000           0010
 *   J2  15-06 BLR inter unregistered 1x84,745.76@18, I 15,254.2368->15,254.24
 *              = exactly 1,00,000.00: NOT over 1 lakh -> B2CS (29-KA)               0011
 *   J3  20-06 BLR inter unregistered 1x95,239.05@5, I 4,761.9525->4,761.95
 *              = 1,00,001.00: over 1 lakh -> B2CL                                   0012
 *   J4  30-06 23:59 PAT B2B intra P1 5x1,000@18  5,000 C450 S450 = 5,900           0013
 *   L1  01-07 00:01 WIP B2CS intra P2 10x400@5  4,000 C100 S100 = 4,200            0014
 *   L2  10-07 RAO B2B inter svc 1x7,777.77@18 (I 1,399.9986->1,400.00) + P1 3x1,000@18 (I 540)
 *              10,777.77 I1,940; 12,717.77 -> 12,718 (round off +0.23)             0015
 *   L3  20-07 ACME export with IGST P1 50x1,000@18  50,000 I9,000 = 59,000        0016
 *   G1  05-08 PAT B2B intra, invoice discount 1,000 split 500/500:
 *              P2 20x400-500 = 7,500@5 C187.50 S187.50; P1 8x1,000-500 = 7,500@18 C675 S675
 *                                                         15,000 C862.50 S862.50   = 16,725  0017
 *   G2  14-08 WIP B2CS intra svc 1x2,500@18   2,500 C225 S225 = 2,950             0018
 *   G3  20-08 BLR DRAFT - never issued
 *   S1  08-09 SEZ under LUT svc 1x75,000@18, no tax = 75,000                       0019
 *   S2  15-09 PAT B2B nil-rated veg 10x150@0  1,500 = 1,500                        0020
 *   S4  30-09 RAO B2B inter svc 2x12,500@18   25,000 I4,500 = 29,500               0021
 *   O1  05-10 WIP B2CS intra P2 15x400@5      6,000 C150 S150 = 6,300              0022
 *   O2  20-10 PAT B2B intra drinks 24x50@40   1,200 C240 S240 = 1,680              0023
 *   O3  31-10 BLR 1x3,000@18 I540 = 3,540, CANCELLED the same day                  0024
 *   N1  10-11 RAO B2B inter P1 30x1,000@18    30,000 I5,400 = 35,400              0025
 *   N2  20-11 DES B2B reverse charge svc 1x40,000@18 = 40,000                      0026
 *   N3  30-11 WIP B2CS intra 7x142.85 = 999.95@18, C 89.9955->90.00, S 90.00;
 *              1,179.95 -> 1,180 (round off +0.05)                                 0027
 *   D1  15-12 ACME export with IGST svc 1x1,00,000@18  I18,000 = 1,18,000         0028
 *   D2  31-12 BLR B2CS inter 1x10,000@5       10,000 I500 = 10,500                 0029
 *   Ja1 10-01-2027 PAT B2B intra P1 12x1,000@18  12,000 C1,080 S1,080 = 14,160    0030
 *   Ja2 20-01 SEZ with IGST svc 1x30,000@18   30,000 I5,400 = 35,400               0031
 *   F1  05-02 WIP B2CS intra veg 2x250@0 (nil 500) + P2 5x400@5 (C50 S50) + P1 2x1,000@18 (C180 S180)
 *                                                         4,500 C230 S230 = 4,960  0032
 *   F2  14-02 RAO B2B inter svc 1x50,000@18   50,000 I9,000 = 59,000               0033
 *   F3  28-02 BLR B2CL P1 100x1,000@18        1,00,000 I18,000 = 1,18,000          0034
 *   Mr1 10-03 PAT B2B intra svc 1x20,000@18   20,000 C1,800 S1,800 = 23,600        0035
 *   Mr2 31-03-2027 23:59 WIP B2CS intra 1x1,000@18  1,000 C90 S90 = 1,180          0036
 *   Y1  01-04-2027 00:01 WIP 1x500@18 = 590 (next FY)                              SAH/27-28/0001
 *
 * CREDIT NOTES (no lines: pro rata to amount / (taxable + tax); CRN-nnnn by date)
 *   cnX0  15-04 on X0 (FY 25-26 invoice)  236:  ratio 0.1 -> 200, C18 S18          CRN-0001
 *   cnJ4  01-07 on J4 1,180 (crosses Q1->Q2): ratio 0.2 -> 1,000, C90 S90          CRN-0002
 *   cnM1  25-07 on M1 1,180: 9,999.99 x 1,180/11,799.99 = 999.9998 -> 1,000.00,
 *              900 x 1,180/11,799.99 = 90.00008 -> C90.00 S90.00                    CRN-0003
 *   cnG1  30-09 on G1 1,000: ratio 1,000/16,725 -> taxable 896.8610->896.86,
 *              C 51.5695->51.57, S 51.57 (GSTR-1 per rate: 448.43 C11.21 / 448.43 C40.36) CRN-0004
 *   cnO1  12-10 on O1 SALES RETURN line: 5 of 15 kg -> 2,000, C50 S50 = 2,100, +5 kg stock CRN-0005
 *   cnL3  31-12 on L3 (export, July) 5,900: ratio 0.1 -> 5,000, I900               CRN-0006
 *   cnJa2 25-01 on Ja2 (SEZ) 3,540: ratio 0.1 -> 3,000, I540                       CRN-0007
 *   cnF1  20-02 on F1 goodwill 100: ratio 100/4,960 = 0.0201613 -> C 4.6371->4.64, S 4.64,
 *              taxable the rest 100 - 9.28 = 90.72 (90.7258 rounded on its own would
 *              make the note 100.01)                                               CRN-0008
 *   cnF3  15-03 on F3 (B2CL) 11,800: ratio 0.1 -> 10,000, I1,800                    CRN-0009
 *
 * RECEIPTS (amount is gross; TDS is part of it)
 *   X0 2,360 on 03-04-2026 | A1 1,180 on 01-04 00:01 | A2 20,070 on 10-05 (TDS 360, 194C)
 *   A4 1,77,000 on 20-04 | A5 1,000 on 30-04 (part) | M1 5,000 on 20-05 (part)
 *   M2 2,00,000 on 30-05 | M5 50,000 on 15-06 | J1 42,000 on 30-06 (TDS 40, 194Q)
 *   J3 1,00,001 on 20-06 | J4 4,720 on 15-07 | L1 4,200 on 01-07 | L2 12,718 on 25-07 (TDS 155.56, 194J)
 *   G2 2,950 on 14-08 | S1 75,000 on 30-09 | S2 1,500 on 15-09 | O1 4,200 on 12-10
 *   O2 1,680 on 20-10 | N1 35,400 on 10-12 (TDS 30) | N2 40,000 on 30-11 | N3 1,180 on 30-11
 *   D1 1,18,000 on 31-12 | F1 4,860 on 20-02 | F2 50,000 on 28-02 (part)
 *   Mr1 23,600 on 01-04-2027 | Mr2 1,180 on 01-04-2027 00:01        (both next FY)
 *   FY 2026-27 receipts: 9,55,019.00 gross, TDS 585.56.
 *
 * PURCHASES (owed to vendor; RCM bills owe only the taxable value)
 *   B1  02-04 V1 P1 100x600@18 60,000 C5,400 S5,400 = 70,800 eligible, paid 30-04
 *   B2  08-04 V2 P2 200x300@5 60,000 I3,000 = 63,000 eligible, paid 31,500 on 08-05
 *   B3  30-04 V3 GTA freight RCM 10,000@5 C250 S250, owes 10,000, eligible, paid now
 *   B4  15-05 V4 staff lunch 10,000@5 C250 S250 = 10,500 BLOCKED, paid now
 *   B5  31-05 V5 packing 2,000@0 = 2,000, paid now
 *   B6  20-06 V6 legal RCM 25,000@18 C2,250 S2,250, owes 25,000, eligible, paid 10-07
 *   B18 25-06 V2 club membership 50,000@18 I9,000 = 59,000 BLOCKED, paid 9,000 now
 *   B7  30-06 V1 sheet metal 33,333.33@18 C/S 2,999.9997->3,000.00; 39,333.33 -> 39,333, paid 01-07
 *   B8  01-07 V2 P2 100x300@5 30,000 I1,500 = 31,500 eligible, unpaid
 *   B17 15-07 V5 cleaning 1,500@0, paid now
 *   B9  15-08 V1 P1 50x600@18 30,000 C2,700 S2,700 = 35,400 eligible, paid 20,000 on 15-09
 *   B10 30-09 V3 GTA RCM 8,000@5 C200 S200, eligible, paid now
 *   B11 10-10 V2 CNC spindle 1,00,000@18 I18,000 = 1,18,000 eligible, unpaid
 *   B12 05-11 V4 Diwali gifts 20,000@18 C1,800 S1,800 = 23,600 BLOCKED, paid now
 *   B13 20-12 V1 P1 100x600@18 60,000 C5,400 S5,400 = 70,800 eligible, unpaid
 *   B14 15-01 V6 legal RCM 15,000@18 C1,350 S1,350, eligible, paid now
 *   B15 10-02 V2 P2 50x300@5 15,000 I750 = 15,750 eligible, paid now
 *   B20 28-02 V3 GTA RCM 6,000@5 C150 S150, eligible, paid now
 *   B19 05-03 V2 P2 40x310@5 12,400 I620 = 13,020 eligible, unpaid
 *   B16 31-03 V1 repairs 5,000@18 C450 S450 = 5,900 eligible, unpaid
 *   FY vendor payments: 2,87,983.00
 *
 * EXPENSES  E1 30-04 rent 30,000 + GST 5,400 intra eligible | E2 31-05 power 4,000
 *   E3 30-06 software 10,000 + 1,800 inter eligible | E4 31-07 staff food 3,000 + 150 BLOCKED
 *   E5 31-08 salaries 50,000 | E6 30-09 rent 30,000 + 5,400 eligible | E7 31-12 CA fees 20,000 + 3,600 eligible
 *   E8 31-03-2027 bank charges 1,000 + 180 eligible
 * CASH MEMOS  CM1 18-06 hardware 3x333.33 = 999.99@18 intra eligible, GST 179.9982->180.00
 *   (C90 S90), 1,179.99 -> 1,180 | CM2 22-11 tea stall 500@5 BLOCKED (C12.50 S12.50) = 525
 * STOCK  adjustments: P1 -3 damaged 15-09-2026; P2 +2 count 31-03-2027.
 */
trait SeedsFy2026Books
{
    protected User $user;
    protected Company $company;
    /** @var array<string, State> */
    protected array $st = [];
    /** @var array<string, Customer> */
    protected array $cust = [];
    /** @var array<string, Vendor> */
    protected array $vend = [];
    /** @var array<string, Product> */
    protected array $prod = [];
    /** @var array<string, Invoice> */
    protected array $inv = [];
    /** @var array<string, CreditNote> */
    protected array $cn = [];
    /** @var array<string, PurchaseBill> */
    protected array $bill = [];

    /** The day the books are read: a few days after the year closed. */
    protected string $asOn = '2027-04-05 10:00:00';

    protected function seedBooks(): void
    {
        $this->at('2026-03-25 10:00');
        $this->st['MH'] = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra', 'code' => 'MH']);
        $this->st['KA'] = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka', 'code' => 'KA']);
        $this->st['GJ'] = State::factory()->create(['gst_code' => '24', 'name' => 'Gujarat', 'code' => 'GJ']);
        $this->st['XX'] = State::factory()->create(['gst_code' => '96', 'name' => 'Foreign Country', 'code' => 'OT']);

        $this->user = User::factory()->create(['name' => 'Arbaz Shaikh']);
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id, 'name' => 'Sahyadri Traders', 'state_id' => $this->st['MH']->id,
            'gstin' => '27AAKCS1234M1Z5', 'composition_dealer' => false,
            'invoice_prefix' => 'SAH', 'invoice_number_format' => 'SAH/{FY_SHORT}/{N}', 'invoice_number_padding' => 4,
            'invoice_counter' => 0, 'lut_number' => 'AD270326012345X',
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();

        $c = fn (string $state, ?string $gstin, string $name, string $country = 'India') => Customer::factory()->create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'state_id' => $this->st[$state]->id,
            'gstin' => $gstin, 'name' => $name, 'country' => $country, 'phone' => null, 'email' => null,
        ]);
        $this->cust = [
            'PAT' => $c('MH', '27AAACP1234A1Z5', 'Patil Traders'),
            'RAO' => $c('KA', '29AAACR5678B1Z2', 'Rao Industries'),
            'SEZ' => $c('GJ', '24AAACS9012C1Z8', 'Shah SEZ Unit'),
            'ACME' => $c('XX', null, 'Acme Inc', 'United States'),
            'WIP' => $c('MH', null, 'Walk-in Pune'),
            'BLR' => $c('KA', null, 'Kumar Retail Bengaluru'),
            'DES' => $c('MH', '27AAACD3456E1Z1', 'Desai Pharma Pvt Ltd'),
        ];
        $v = fn (string $state, ?string $gstin, string $name) => $this->company->vendors()->create([
            'user_id' => $this->user->id, 'name' => $name, 'state_id' => $this->st[$state]->id, 'gstin' => $gstin,
        ]);
        $this->vend = [
            'V1' => $v('MH', '27AAACM1111F1Z1', 'Mumbai Metals'),
            'V2' => $v('KA', '29AAACM2222G1Z2', 'Mysore Motors'),
            'V3' => $v('MH', null, 'Fast Freight GTA'),
            'V4' => $v('MH', '27AAACH3333H1Z3', 'Hotel Annapurna'),
            'V5' => $v('MH', null, 'Local Kirana'),
            'V6' => $v('MH', null, 'Adv. Mehta'),
        ];

        $this->product('P1', 'Steel bracket', 'SB-01', '73269099', 'NOS', 1000, 18, 100, 600);
        $this->product('P2', 'Packaged tea', 'TEA-1', '09023020', 'KGS', 400, 5, 50, null);
        $this->product('P3', 'Generator', 'GEN-5', '85023100', 'NOS', 150000, 18, 3, null);

        // ---------------- FY 2025-26, last minute ----------------
        $this->issue('X0', 'PAT', '2026-03-31 23:59', [$this->p('P1', 2)], '2026-04-30');

        // ---------------- April 2026 ----------------
        $this->issue('A1', 'WIP', '2026-04-01 00:01', [$this->p('P1', 1)], '2026-04-01');
        $this->pay('A1', '2026-04-01 00:01', 1180, 'cash');
        $this->bill('B1', 'V1', '2026-04-02 11:00', 'MM/101', [$this->bp('P1', 100, 600, 18)], ['due_date' => '2026-05-02', 'expense_category' => 'purchases']);
        $this->pay('X0', '2026-04-03 11:00', 2360, 'neft', 'UTR-X0');
        $this->bill('B2', 'V2', '2026-04-08 11:00', 'MY/2201', [$this->bp('P2', 200, 300, 5)], ['due_date' => '2026-05-08', 'expense_category' => 'purchases']);
        $this->issue('A2', 'RAO', '2026-04-10 11:00', [$this->p('P1', 10), $this->p('P2', 25)], '2026-05-10', ['invoice_discount' => 2000]);
        $this->creditNote('cnX0', 'X0', '2026-04-15 11:00', 236, 'post_sale_discount');
        $this->issue('A4', 'BLR', '2026-04-20 11:00', [$this->p('P3', 1)], '2026-04-20');
        $this->pay('A4', '2026-04-20 11:00', 177000, 'rtgs', 'UTR-A4');
        $this->issue('A5', 'WIP', '2026-04-25 11:00', [$this->l('Fresh vegetables', '07099990', 2, 500, 0, 'KGS'), $this->p('P1', 1)], '2026-05-25');
        $this->draft('A6', 'PAT', '2026-04-28 11:00', [$this->l('Big order', '73269099', 1, 99999, 18)], '2026-05-28');
        $this->pay('A5', '2026-04-30 11:00', 1000, 'cash');
        $this->billPay('B1', '2026-04-30 11:00', 70800, 'NEFT-B1');
        $this->bill('B3', 'V3', '2026-04-30 11:00', 'FF/77', [$this->l('Freight (GTA)', '996511', 1, 10000, 5)],
            ['reverse_charge' => 1, 'expense_category' => 'courier', 'paid_now' => 10000, 'payment_method' => 'cash']);
        $this->expense('2026-04-30 11:00', 'rent', 'Shop rent April', 30000, 5400, false, true);

        // ---------------- May 2026 ----------------
        $this->issue('M1', 'PAT', '2026-05-05 11:00', [$this->l('CNC machining', '998873', 3, 3333.33, 18)], '2026-06-04');
        $this->billPay('B2', '2026-05-08 11:00', 31500, 'NEFT-B2');
        $this->pay('A2', '2026-05-10 11:00', 20070, 'neft', 'UTR-A2', 360, '194C', 2);
        $this->issue('M2', 'ACME', '2026-05-12 11:00', [$this->l('Software development', '998314', 1, 200000, 18)], '2026-06-11', ['supply_type' => 'export_lut']);
        $this->bill('B4', 'V4', '2026-05-15 11:00', 'HA/55', [$this->l('Staff lunch', '996331', 1, 10000, 5)],
            ['itc_eligible' => 0, 'expense_category' => 'food', 'paid_now' => 10500, 'payment_method' => 'upi', 'reference_number' => 'UPI-B4']);
        $this->issue('M3', 'SEZ', '2026-05-18 11:00', [$this->p('P1', 20)], '2026-06-17', ['supply_type' => 'sez_igst']);
        $this->pay('M1', '2026-05-20 11:00', 5000, 'upi', 'UPI-M1');
        $this->issue('M4', 'WIP', '2026-05-22 11:00', [$this->p('P1', 5)], '2026-05-22');
        $this->cancel('M4', '2026-05-23 11:00');
        $this->pay('M2', '2026-05-30 11:00', 200000, 'neft', 'SWIFT-M2');
        $this->issue('M5', 'DES', '2026-05-31 11:00', [$this->l('Security services', '998525', 1, 50000, 18)], '2026-06-15', ['reverse_charge' => 1]);
        $this->bill('B5', 'V5', '2026-05-31 11:00', 'LK/9', [$this->l('Packing material', '48191010', 1, 2000, 0)],
            ['expense_category' => 'office_supplies', 'paid_now' => 2000, 'payment_method' => 'cash']);
        $this->expense('2026-05-31 11:00', 'utilities', 'Electricity May', 4000, 0, false, true);

        // ---------------- June 2026 ----------------
        $this->issue('J1', 'RAO', '2026-06-05 11:00', [$this->p('P2', 100)], '2026-07-05');
        $this->issue('J2', 'BLR', '2026-06-15 11:00', [$this->l('Office chairs', '94013000', 1, 84745.76, 18)], '2026-07-15');
        $this->pay('M5', '2026-06-15 11:00', 50000, 'neft', 'UTR-M5');
        $this->memo('2026-06-18 11:00', 'Local hardware', 18, false, true, 'repairs', [['description' => 'Tools', 'quantity' => 3, 'rate' => 333.33]]);
        $this->issue('J3', 'BLR', '2026-06-20 11:00', [$this->l('Tea chest lot', '09023020', 1, 95239.05, 5, 'BOX')], '2026-06-20');
        $this->pay('J3', '2026-06-20 11:00', 100001, 'upi', 'UPI-J3');
        $this->bill('B6', 'V6', '2026-06-20 11:00', 'ADV/12', [$this->l('Legal services', '998211', 1, 25000, 18)],
            ['reverse_charge' => 1, 'expense_category' => 'professional', 'due_date' => '2026-07-10']);
        $this->bill('B18', 'V2', '2026-06-25 11:00', 'MY/2290', [$this->l('Club membership', '999599', 1, 50000, 18)],
            ['itc_eligible' => 0, 'expense_category' => 'misc', 'due_date' => '2026-07-25', 'paid_now' => 9000, 'payment_method' => 'neft', 'reference_number' => 'NEFT-B18']);
        $this->pay('J1', '2026-06-30 11:00', 42000, 'neft', 'UTR-J1', 40, '194Q', 0.1);
        $this->bill('B7', 'V1', '2026-06-30 11:00', 'MM/188', [$this->l('Sheet metal', '72085100', 1, 33333.33, 18)],
            ['expense_category' => 'purchases', 'due_date' => '2026-07-30']);
        $this->expense('2026-06-30 11:00', 'software', 'Accounting software', 10000, 1800, true, true);
        $this->issue('J4', 'PAT', '2026-06-30 23:59', [$this->p('P1', 5)], '2026-07-30');

        // ---------------- July 2026 ----------------
        $this->issue('L1', 'WIP', '2026-07-01 00:01', [$this->p('P2', 10)], '2026-07-01');
        $this->pay('L1', '2026-07-01 00:01', 4200, 'cash');
        $this->creditNote('cnJ4', 'J4', '2026-07-01 11:00', 1180, 'rate_correction');
        $this->billPay('B7', '2026-07-01 11:00', 39333, 'NEFT-B7');
        $this->bill('B8', 'V2', '2026-07-01 11:00', 'MY/2301', [$this->bp('P2', 100, 300, 5)], ['expense_category' => 'purchases', 'due_date' => '2026-07-31']);
        $this->issue('L2', 'RAO', '2026-07-10 11:00', [$this->l('Installation', '998729', 1, 7777.77, 18), $this->p('P1', 3)], '2026-08-09');
        $this->billPay('B6', '2026-07-10 11:00', 25000, 'NEFT-B6');
        $this->pay('J4', '2026-07-15 11:00', 4720, 'neft', 'UTR-J4');
        $this->bill('B17', 'V5', '2026-07-15 11:00', 'LK/31', [$this->l('Cleaning supplies', '34022090', 1, 1500, 0)],
            ['expense_category' => 'office_supplies', 'paid_now' => 1500, 'payment_method' => 'cash']);
        $this->issue('L3', 'ACME', '2026-07-20 11:00', [$this->p('P1', 50)], '2026-08-19',
            ['supply_type' => 'export_igst', 'export_port_code' => 'INNSA1', 'shipping_bill_number' => '7654321', 'shipping_bill_date' => '2026-07-22']);
        $this->creditNote('cnM1', 'M1', '2026-07-25 11:00', 1180, 'post_sale_discount');
        $this->pay('L2', '2026-07-25 11:00', 12718, 'neft', 'UTR-L2', 155.56, '194J', 2);
        $this->expense('2026-07-31 11:00', 'food', 'Staff food', 3000, 150, false, false);

        // ---------------- August 2026 ----------------
        $this->issue('G1', 'PAT', '2026-08-05 11:00', [$this->p('P2', 20), $this->p('P1', 8)], '2026-09-04', ['invoice_discount' => 1000]);
        $this->issue('G2', 'WIP', '2026-08-14 11:00', [$this->l('Repair service', '998719', 1, 2500, 18)], '2026-08-14');
        $this->pay('G2', '2026-08-14 11:00', 2950, 'upi', 'UPI-G2');
        $this->bill('B9', 'V1', '2026-08-15 11:00', 'MM/240', [$this->bp('P1', 50, 600, 18)], ['expense_category' => 'purchases', 'due_date' => '2026-09-14']);
        $this->draft('G3', 'BLR', '2026-08-20 11:00', [$this->l('Desk', '94033010', 1, 5000, 18)], '2026-09-19');
        $this->expense('2026-08-31 11:00', 'salaries', 'Salaries August', 50000, 0, false, true);

        // ---------------- September 2026 ----------------
        $this->issue('S1', 'SEZ', '2026-09-08 11:00', [$this->l('Software development', '998314', 1, 75000, 18)], '2026-10-08', ['supply_type' => 'sez_lut']);
        $this->issue('S2', 'PAT', '2026-09-15 11:00', [$this->l('Fresh vegetables', '07099990', 10, 150, 0, 'KGS')], '2026-09-15');
        $this->pay('S2', '2026-09-15 11:00', 1500, 'cash');
        $this->billPay('B9', '2026-09-15 11:00', 20000, 'NEFT-B9');
        $this->adjust('P1', '2026-09-15 11:00', 'out', 3, 'Damaged in transit');
        $this->creditNote('cnG1', 'G1', '2026-09-30 11:00', 1000, 'post_sale_discount');
        $this->issue('S4', 'RAO', '2026-09-30 11:00', [$this->l('Fabrication', '998873', 2, 12500, 18)], '2026-10-30');
        $this->pay('S1', '2026-09-30 11:00', 75000, 'neft', 'UTR-S1');
        $this->bill('B10', 'V3', '2026-09-30 11:00', 'FF/140', [$this->l('Freight (GTA)', '996511', 1, 8000, 5)],
            ['reverse_charge' => 1, 'expense_category' => 'courier', 'paid_now' => 8000, 'payment_method' => 'cash']);
        $this->expense('2026-09-30 11:00', 'rent', 'Shop rent September', 30000, 5400, false, true);

        // ---------------- October 2026 ----------------
        $this->issue('O1', 'WIP', '2026-10-05 11:00', [$this->p('P2', 15)], '2026-10-05');
        $this->bill('B11', 'V2', '2026-10-10 11:00', 'MY/2450', [$this->l('CNC spindle', '84661010', 1, 100000, 18)], ['expense_category' => 'misc', 'due_date' => '2026-11-09']);
        $this->creditNote('cnO1', 'O1', '2026-10-12 11:00', null, 'sales_return', [$this->inv['O1']->items->first()->id => 5]);
        $this->pay('O1', '2026-10-12 11:00', 4200, 'cash');
        $this->issue('O2', 'PAT', '2026-10-20 11:00', [$this->l('Aerated drinks', '22021010', 24, 50, 40)], '2026-10-20');
        $this->pay('O2', '2026-10-20 11:00', 1680, 'upi', 'UPI-O2');
        $this->issue('O3', 'BLR', '2026-10-31 11:00', [$this->l('Desk lamp', '94052090', 1, 3000, 18)], '2026-11-30');
        $this->cancel('O3', '2026-10-31 11:00');

        // ---------------- November 2026 ----------------
        $this->bill('B12', 'V4', '2026-11-05 11:00', 'HA/91', [$this->l('Diwali gift hampers', '21069099', 1, 20000, 18)],
            ['itc_eligible' => 0, 'expense_category' => 'marketing', 'paid_now' => 23600, 'payment_method' => 'neft', 'reference_number' => 'NEFT-B12']);
        $this->issue('N1', 'RAO', '2026-11-10 11:00', [$this->p('P1', 30)], '2026-12-10');
        $this->issue('N2', 'DES', '2026-11-20 11:00', [$this->l('Security services', '998525', 1, 40000, 18)], '2026-11-30', ['reverse_charge' => 1]);
        $this->memo('2026-11-22 11:00', 'Tea stall', 5, false, false, 'food', [['description' => 'Tea for staff', 'quantity' => 1, 'rate' => 500]]);
        $this->issue('N3', 'WIP', '2026-11-30 11:00', [$this->l('Cable', '85444999', 7, 142.85, 18)], '2026-11-30');
        $this->pay('N3', '2026-11-30 11:00', 1180, 'cash');
        $this->pay('N2', '2026-11-30 11:00', 40000, 'neft', 'UTR-N2');

        // ---------------- December 2026 ----------------
        $this->pay('N1', '2026-12-10 11:00', 35400, 'neft', 'UTR-N1', 30, '194Q', 0.1);
        $this->issue('D1', 'ACME', '2026-12-15 11:00', [$this->l('Software development', '998314', 1, 100000, 18)], '2027-01-14', ['supply_type' => 'export_igst']);
        $this->bill('B13', 'V1', '2026-12-20 11:00', 'MM/377', [$this->bp('P1', 100, 600, 18)], ['expense_category' => 'purchases', 'due_date' => '2027-01-19']);
        $this->issue('D2', 'BLR', '2026-12-31 11:00', [$this->l('Sugar', '17019990', 1, 10000, 5)], '2027-01-30');
        $this->creditNote('cnL3', 'L3', '2026-12-31 11:00', 5900, 'rate_correction');
        $this->pay('D1', '2026-12-31 11:00', 118000, 'neft', 'SWIFT-D1');
        $this->expense('2026-12-31 11:00', 'professional', 'CA fees', 20000, 3600, false, true);

        // ---------------- January 2027 ----------------
        $this->issue('Ja1', 'PAT', '2027-01-10 11:00', [$this->p('P1', 12)], '2027-02-09');
        $this->bill('B14', 'V6', '2027-01-15 11:00', 'ADV/31', [$this->l('Legal services', '998211', 1, 15000, 18)],
            ['reverse_charge' => 1, 'expense_category' => 'professional', 'paid_now' => 15000, 'payment_method' => 'neft', 'reference_number' => 'NEFT-B14']);
        $this->issue('Ja2', 'SEZ', '2027-01-20 11:00', [$this->l('Software development', '998314', 1, 30000, 18)], '2027-02-19', ['supply_type' => 'sez_igst']);
        $this->creditNote('cnJa2', 'Ja2', '2027-01-25 11:00', 3540, 'deficient_service');

        // ---------------- February 2027 ----------------
        $this->issue('F1', 'WIP', '2027-02-05 11:00', [$this->l('Fresh vegetables', '07099990', 2, 250, 0, 'KGS'), $this->p('P2', 5), $this->p('P1', 2)], '2027-02-05');
        $this->bill('B15', 'V2', '2027-02-10 11:00', 'MY/2630', [$this->bp('P2', 50, 300, 5)],
            ['expense_category' => 'purchases', 'paid_now' => 15750, 'payment_method' => 'neft', 'reference_number' => 'NEFT-B15']);
        $this->issue('F2', 'RAO', '2027-02-14 11:00', [$this->l('Fabrication', '998873', 1, 50000, 18)], '2027-03-16');
        $this->creditNote('cnF1', 'F1', '2027-02-20 11:00', 100, 'post_sale_discount');
        $this->pay('F1', '2027-02-20 11:00', 4860, 'cash');
        $this->issue('F3', 'BLR', '2027-02-28 11:00', [$this->p('P1', 100)], '2027-03-30');
        $this->pay('F2', '2027-02-28 11:00', 50000, 'neft', 'UTR-F2');
        $this->bill('B20', 'V3', '2027-02-28 11:00', 'FF/201', [$this->l('Freight (GTA)', '996511', 1, 6000, 5)],
            ['reverse_charge' => 1, 'expense_category' => 'courier', 'paid_now' => 6000, 'payment_method' => 'cash']);

        // ---------------- March 2027 ----------------
        $this->bill('B19', 'V2', '2027-03-05 11:00', 'MY/2700', [$this->bp('P2', 40, 310, 5)], ['expense_category' => 'purchases', 'due_date' => '2027-04-04']);
        $this->issue('Mr1', 'PAT', '2027-03-10 11:00', [$this->l('Fabrication', '998873', 1, 20000, 18)], '2027-04-09');
        $this->creditNote('cnF3', 'F3', '2027-03-15 11:00', 11800, 'rate_correction');
        $this->bill('B16', 'V1', '2027-03-31 11:00', 'MM/455', [$this->l('Machine repairs', '998719', 1, 5000, 18)], ['expense_category' => 'repairs', 'due_date' => '2027-04-30']);
        $this->expense('2027-03-31 11:00', 'bank_charges', 'Bank charges', 1000, 180, false, true);
        $this->adjust('P2', '2027-03-31 11:00', 'in', 2, 'Physical count');
        $this->issue('Mr2', 'WIP', '2027-03-31 23:59', [$this->l('Cable', '85444999', 1, 1000, 18)], '2027-04-30');

        // ---------------- April 2027 (next year) ----------------
        $this->pay('Mr2', '2027-04-01 00:01', 1180, 'cash');
        $this->issue('Y1', 'WIP', '2027-04-01 00:01', [$this->l('Cable', '85444999', 1, 500, 18)], '2027-04-01');
        $this->pay('Mr1', '2027-04-01 11:00', 23600, 'neft', 'UTR-MR1');

        $this->at($this->asOn);
        foreach ($this->inv as $k => $i) {
            $this->inv[$k] = $i->fresh(['items', 'payments', 'creditNotes']);
        }
    }

    // ------------------------------------------------------------------ writers

    /** Move the clock to a moment of Indian Standard Time. */
    protected function at(string $ist): void
    {
        $this->travelTo(Carbon::parse($ist, 'Asia/Kolkata'));
    }

    /** "Today" as the form fills it in, read off the (moved) clock in IST. */
    protected function today(): string
    {
        return now('Asia/Kolkata')->toDateString();
    }

    protected function product(string $key, string $name, string $sku, string $hsn, string $unit, float $rate, float $gst, float $opening, ?float $cost): void
    {
        $this->actingAs($this->user)->post(route('products.store'), array_filter([
            'name' => $name, 'sku' => $sku, 'kind' => 'goods', 'hsn_sac' => $hsn, 'unit' => $unit,
            'rate' => $rate, 'gst_rate' => $gst, 'track_stock' => 1, 'opening_stock' => $opening, 'purchase_rate' => $cost,
        ], fn ($v) => $v !== null))->assertSessionHasNoErrors()->assertRedirect();
        $this->prod[$key] = Product::where('company_id', $this->company->id)->where('sku', $sku)->firstOrFail();
    }

    /** A free-text line. */
    protected function l(string $desc, string $hsn, float $qty, float $rate, float $gst, string $unit = 'NOS'): array
    {
        return ['description' => $desc, 'hsn_sac' => $hsn, 'quantity' => $qty, 'unit' => $unit, 'rate' => $rate, 'gst_rate' => $gst];
    }

    /** A sales line for an item, at its list price and rate. */
    protected function p(string $key, float $qty): array
    {
        $p = $this->prod[$key];

        return ['product_id' => $p->id, 'description' => $p->name, 'hsn_sac' => $p->hsn_sac, 'quantity' => $qty,
            'unit' => $p->unit, 'rate' => (float) $p->rate, 'gst_rate' => (float) $p->gst_rate];
    }

    /** A purchase line for an item at a cost price. */
    protected function bp(string $key, float $qty, float $rate, float $gst): array
    {
        $p = $this->prod[$key];

        return ['product_id' => $p->id, 'description' => $p->name, 'hsn_sac' => $p->hsn_sac, 'quantity' => $qty,
            'unit' => $p->unit, 'rate' => $rate, 'gst_rate' => $gst];
    }

    protected function draft(string $key, string $cust, string $when, array $items, string $due, array $extra = []): Invoice
    {
        $this->at($when);
        $this->actingAs($this->user)->post(route('invoices.store'), array_merge([
            'customer_id' => $this->cust[$cust]->id, 'invoice_date' => $this->today(), 'due_date' => $due, 'items' => $items,
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();

        return $this->inv[$key] = Invoice::where('company_id', $this->company->id)->latest('id')->firstOrFail();
    }

    protected function issue(string $key, string $cust, string $when, array $items, string $due, array $extra = []): Invoice
    {
        $inv = $this->draft($key, $cust, $when, $items, $due, $extra);
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv))->assertSessionHasNoErrors()->assertRedirect();
        $inv = $inv->fresh(['items']);
        $this->assertNotSame('draft', $inv->status, "{$key} was not issued");

        return $this->inv[$key] = $inv;
    }

    protected function cancel(string $key, string $when): void
    {
        $this->at($when);
        $this->actingAs($this->user)->post(route('invoices.cancel', $this->inv[$key]), ['cancellation_reason' => 'Raised to the wrong party'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('cancelled', $this->inv[$key]->fresh()->status);
    }

    protected function pay(string $key, string $when, float $amount, string $method, ?string $ref = null, ?float $tds = null, ?string $section = null, ?float $rate = null): void
    {
        $this->at($when);
        $this->actingAs($this->user)->post(route('invoices.payments', $this->inv[$key]), array_filter([
            'amount' => $amount, 'method' => $method, 'received_at' => $this->today(), 'reference_number' => $ref,
            'tds_amount' => $tds, 'tds_section' => $section, 'tds_rate' => $rate,
        ], fn ($v) => $v !== null))->assertSessionHasNoErrors()->assertRedirect();
    }

    protected function creditNote(string $key, string $invKey, string $when, ?float $amount, string $reason, array $lines = []): void
    {
        $this->at($when);
        $before = CreditNote::count();
        $this->actingAs($this->user)->post(route('credit-notes.store', $this->inv[$invKey]), array_filter([
            'credit_note_date' => $this->today(), 'amount' => $amount, 'reason' => $reason, 'lines' => $lines ?: null,
        ], fn ($v) => $v !== null))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($before + 1, CreditNote::count(), "{$key} was not issued");
        $this->cn[$key] = CreditNote::latest('id')->firstOrFail();
    }

    protected function bill(string $key, string $vendor, string $when, string $number, array $items, array $extra = []): void
    {
        $this->at($when);
        $this->actingAs($this->user)->post(route('purchases.store'), array_merge([
            'vendor_id' => $this->vend[$vendor]->id, 'bill_number' => $number, 'bill_date' => $this->today(),
            'itc_eligible' => 1, 'reverse_charge' => 0, 'payment_method' => 'neft', 'items' => $items,
            'paid_on' => $this->today(),
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();
        $this->bill[$key] = PurchaseBill::where('company_id', $this->company->id)->latest('id')->firstOrFail();
    }

    protected function billPay(string $key, string $when, float $amount, string $ref): void
    {
        $this->at($when);
        $this->actingAs($this->user)->post(route('purchases.payments.store', $this->bill[$key]), [
            'amount' => $amount, 'method' => 'neft', 'paid_on' => $this->today(), 'reference_number' => $ref,
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    protected function expense(string $when, string $category, string $desc, float $amount, float $gst, bool $inter, bool $eligible): void
    {
        $this->at($when);
        $this->actingAs($this->user)->post(route('finance.expenses.store'), [
            'entry_date' => $this->today(), 'category' => $category, 'description' => $desc, 'amount' => $amount,
            'gst_amount' => $gst, 'is_interstate' => $inter ? '1' : '0', 'itc_eligible' => $eligible ? '1' : '0', 'payment_method' => 'bank',
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    protected function memo(string $when, string $seller, float $gstRate, bool $inter, bool $eligible, string $category, array $items): void
    {
        $this->at($when);
        $this->actingAs($this->user)->post(route('finance.cash-memos.store'), [
            'memo_date' => $this->today(), 'seller_name' => $seller, 'gst_rate' => $gstRate, 'is_interstate' => $inter ? 1 : 0,
            'itc_eligible' => $eligible ? 1 : 0, 'payment_mode' => 'cash', 'expense_category' => $category, 'items' => $items,
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    protected function adjust(string $key, string $when, string $direction, float $qty, string $reason): void
    {
        $this->at($when);
        $this->actingAs($this->user)->post(route('products.stock.adjust', $this->prod[$key]), [
            'direction' => $direction, 'quantity' => $qty, 'moved_on' => $this->today(), 'reason' => $reason,
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    // ------------------------------------------------------------------ readers

    /**
     * The taxable value on cnF1 (₹100 on the mixed-rate bill F1): 90.7258
     * rounds to 90.73, but the note only adds up to its ₹100 at 90.72
     * (DatasetTest::test_every_credit_note_adds_up_to_its_own_amount checks
     * which). Figures downstream take the note as recorded, so one paisa on
     * one document does not fail every report built on it.
     */
    protected function cnF1Taxable(): float
    {
        $v = (float) $this->cn['cnF1']->fresh()->taxable_value;
        $this->assertContains(round($v, 2), [90.72, 90.73], 'cnF1 taxable is 90.7258 rounded one way or the other');

        return $v;
    }

    /** The twelve return months of FY 2026-27, 'Y-m'. */
    protected function months(): array
    {
        $out = [];
        for ($m = Carbon::create(2026, 4, 1); $m->lt(Carbon::create(2027, 4, 1)); $m->addMonth()) {
            $out[] = $m->format('Y-m');
        }

        return $out;
    }

    /** Quarter label => first month. */
    protected function quarters(): array
    {
        return ['Q1' => '2026-04', 'Q2' => '2026-07', 'Q3' => '2026-10', 'Q4' => '2027-01'];
    }

    /** First and last day of a 'Y-m' month. */
    protected function monthRange(string $ym): array
    {
        $d = Carbon::createFromFormat('Y-m-d', $ym . '-01');

        return [$d->toDateString(), $d->copy()->endOfMonth()->toDateString()];
    }

    /** View data of the GSTR-1 page for a month or a quarter. */
    protected function gstr1Page(string $ym, bool $quarterly = false): array
    {
        $res = $this->actingAs($this->user)->get(route('finance.gstr1', array_filter(['month' => $ym, 'frequency' => $quarterly ? 'quarterly' : null])));
        $res->assertOk();

        return $res->original->getData();
    }

    /** View data of the GSTR-3B page for a month or a quarter. */
    protected function gstr3bPage(string $ym, bool $quarterly = false): array
    {
        $res = $this->actingAs($this->user)->get(route('finance.gstr3b', array_filter(['month' => $ym, 'frequency' => $quarterly ? 'quarterly' : null])));
        $res->assertOk();

        return $res->original->getData();
    }

    /** A report screen's rows and totals for a period. */
    protected function report(string $key, array $query = []): array
    {
        $res = $this->actingAs($this->user)->get(route('reports.show', ['report' => $key] + $query));
        $res->assertOk();
        $this->assertFalse($res->viewData('truncated'), "{$key} screen truncated");

        return ['rows' => $res->viewData('rows'), 'totals' => $res->viewData('totals') ?? []];
    }

    /**
     * A register's totals for [from, to], summed from its rows. The rows are
     * fetched for one extra day and cut at `to` here, so the identity checks
     * read the same register rows on every database driver; whether the
     * register's own `to` filter keeps the last day is asserted separately
     * in PeriodBoundaryTest.
     */
    protected function registerTotals(string $key, string $from, string $to, array $query = []): array
    {
        $dayAfter = Carbon::parse($to)->addDay()->toDateString();
        $rows = array_filter($this->report($key, ['from' => $from, 'to' => $dayAfter] + $query)['rows'],
            fn ($r) => $r['date'] instanceof \DateTimeInterface && $r['date']->format('Y-m-d') <= $to);
        $out = [];
        foreach ($rows as $r) {
            foreach ($r as $k => $v) {
                if (is_float($v) || is_int($v)) {
                    $out[$k] = round(($out[$k] ?? 0) + $v, 2);
                }
            }
        }

        return $out + ['_count' => count($rows)];
    }

    /** Parse a downloaded CSV into rows (BOM stripped). */
    protected function csvRows(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $rows = [];
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $content);
        rewind($fh);
        while (($r = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            $rows[] = $r;
        }
        fclose($fh);

        return $rows;
    }

    /** Read every sheet of a downloaded XLSX: sheet name => rows of cell values. */
    protected function xlsxSheets(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'rec') . '.xlsx';
        file_put_contents($path, $content);
        $sheets = [];
        $reader = new Reader();
        $reader->open($path);
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();
        @unlink($path);

        return $sheets;
    }

    /** Collect a mismatch message instead of stopping at the first one. */
    protected function check(array &$failures, string $label, float $expected, float $actual, float $delta = 0.001): void
    {
        if (abs($expected - $actual) > $delta) {
            $failures[] = sprintf('%s: expected %s, got %s', $label, number_format($expected, 2, '.', ''), number_format($actual, 2, '.', ''));
        }
    }
}
