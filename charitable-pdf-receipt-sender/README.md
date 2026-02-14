# Charitable PDF Receipt Sender

Lightweight WordPress plugin that sends one PDF receipt per completed Charitable donation.

## Features

- Hooks only when donation status becomes completed.
- Uses AcroForm field-name mapping (no coordinate text placement).
- Flattens the filled PDF.
- Prevents duplicate sends using donation meta `receipt_sent`.
- Fails silently in donation flow when template is missing or generation fails.
- Admin-only settings, testing, and diagnostics.
- No custom database tables.

## Install

1. Place `charitable-pdf-receipt-sender` in `wp-content/plugins/`.
2. Activate plugin.
3. Go to **Settings → PDF Receipts**.

## Local dependency install

```bash
cd charitable-pdf-receipt-sender
composer install
```

> `vendor/` is not committed. Install dependencies when building releases.

## Build release zip

```bash
cd charitable-pdf-receipt-sender
composer install --no-dev --optimize-autoloader
cd ..
zip -r charitable-pdf-receipt-sender.zip charitable-pdf-receipt-sender -x "*/.git/*" "*/vendor/bin/*"
```

This zip must include `vendor/` for production use.

## Setup

1. **General** tab: keep plugin enabled.
2. **Email** tab: configure sender/from and message templates.
3. **PDF Template** tab: upload a fillable PDF template.
4. **Field Mapping** tab: map internal keys to actual AcroForm field names.

Predefined internal keys:

- receipt_number
- donor_name
- donation_date
- campaign_name
- donation_description
- amount
- sub_total
- payment_method
- donation_purpose
- transaction_id
- total_donation

If mapping value is blank, that field is skipped.

## Testing

### Send Test Email

Use the **Testing** tab:

- Enter test recipient.
- Click **Send Test Email**.
- Plugin generates dummy mapped data, creates PDF, and sends via `wp_mail`.
- Errors are caught and displayed without fataling.

### Diagnostics

Use **Run Diagnostics** button. It stores output in option `cprs_last_diagnostics` and displays:

- enabled value
- template checks
- resolved paths
- class availability (`FPDM`, `FPDF`, `FPDI`)
- handler reached flag
- PDF generation status + byte size
- exception message if any
- `wp_mail` attempted/result

## Duplicate prevention

On completed donation:

- If `resend_if_sent` is off and `receipt_sent` meta exists, send is skipped.
- On successful send, plugin sets `receipt_sent = 1`.

## Notes

- The plugin does **not** load `vendor/autoload.php` on plugin boot.
- Dependency loading is done only inside PDF generation and diagnostics/test paths.
- Temporary files used for email attachment are removed immediately after send.
