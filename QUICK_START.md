# PDF Attestation Tool - Quick Start Guide

## Installation (5 minutes)

### Step 1: Extract the plugin
- Download the plugin folder: `pdf-attestation-tool`
- Extract it to `/wp-content/plugins/pdf-attestation-tool/`

### Step 2: Network Activate the plugin
1. Log in to WordPress as a super-admin
2. Go to **Network Admin → Plugins**
3. Find "PDF Attestation Tool"
4. Click **"Network Activate"** (not just "Activate")

**Important**: Must be network-activated, not site-activated!

### Step 3: Remove PDF from allowed file types (manual step, although the plugin should block pdfs anyway, this is a belt-and-suspenders step)
1. Go to **Network Admin → Settings**
2. Find the "Upload Settings" section
3. Locate the allowed file extensions list
4. **Remove `pdf`** from the list (keep other extensions like jpg, png, doc, etc.)
5. Click **Save Settings**

This prevents PDFs from being uploaded through the standard media library.

### Step 4: Verify installation
1. Visit any site in your network
2. Go to **Media → PDF Upload Tool** in the admin
3. You should see the PDF upload form with the accessibility attestation checkbox, and after a PDF has been successfully uploaded, you will see a table of all uploaded PDFs **for that site**.
4. Go to **Network Admin → PDF Attestations**
5. You should see the audit dashboard displaying PDFs for **all** sites (currently empty since no PDFs uploaded yet)

**Installation complete!** ✓

---

## Testing the Plugin

### Test 1: Upload a PDF to a site
1. On any site, go to Media → PDF Upload Tool
2. Select a PDF file
3. Try clicking "Upload PDF" WITHOUT checking the box
   - Should show error: "You must attest to the accessibility of this file before uploading"
   - Should force you to select the PDF again (intentional friction!)
4. Check the attestation box
5. Click "Upload PDF"
6. Should see success message, row is added to the table below, and PDF appears in Media Library

### Test 2: Verify PDF blocking
1. Go to Media Library
2. Try uploading a PDF directly
3. Should get error: "PDF uploads are not allowed through the standard media library"

### Test 3: Check the audit trail
1. Go to Network Admin → PDF Attestations
2. Should see the PDF you just uploaded with:
   - Your username
   - The filename
   - The current date/time
   - Site name
   - Status: "Attested"

### Test 4: Export records
1. Go to Network Admin → PDF Attestations
2. Click "Export CSV"
3. A CSV file downloads with all upload records
4. Open in Excel or your preferred spreadsheet app

---

## Key Features to Know

**Only one PDF at a time** - Users must upload one PDF per form submission (friction!)

**Required attestation** - Form will not submit without checking the box

**No admin bypass** - Even super-admins must use the attestation tool

**Permanent audit trail** - All uploads are recorded and cannot be deleted

**Network-wide tracking** - One database table tracks PDFs from all sites

**Searchable and sortable records** - Filter by date range, filename, username, or site; sort by column headers

---

## Typical Workflow

### For Content Editors/Authors
1. Before uploading a PDF, review it for accessibility (run accessibility checker)
2. Go to Media → PDF Upload Tool
3. Select the accessible PDF
4. Check the attestation box confirming it meets WCAG standards
5. Upload

### For Network Admins
1. Regularly check Network Admin → PDF Attestations
2. Use search/filter to monitor uploads
3. Export CSV monthly/quarterly for compliance records
4. Share export with accessibility coordinator or compliance officer

---

## Troubleshooting

### "This plugin requires WordPress Multisite"
- Your WordPress is not set up for multisite
- The plugin only works with multisite (by design)
- See the README file for single-site adaptation instructions (not tested, so not guaranteed to work!)

### PDF Upload Tool doesn't appear
- Make sure you're logged in as a user with upload permissions
- Make sure you're on the site admin, not network admin
- Go to: Media → PDF Upload Tool (not Network Admin)

### Can't upload PDFs through media library
- This is expected! PDFs are blocked from the standard library
- Use Media → PDF Upload Tool instead
- This ensures all PDFs go through attestation

### No records appear in Network Attestations dashboard
- Wait a few seconds after uploading
- Refresh the page
- Make sure at least one PDF has been uploaded
- Check that you're looking at Network Admin (not regular site admin)

### Forgot to remove PDF from allowed file types
- PDFs should still be blocked by the plugin
- But it's recommended to remove it from settings for consistency
- Go to Network Admin → Settings and remove `pdf` from the file list

---

## File Structure

```
/wp-content/plugins/pdf-attestation-tool/
├── pdf-attestation-tool.php              # Main plugin file
├── README.md                             # Full documentation
├── includes/
│   ├── class-pdf-attestation-database.php
│   ├── class-pdf-attestation-upload.php
│   ├── class-pdf-attestation-admin.php
│   └── functions-pdf-attestation.php
└── languages/                            # For future translations
```

All code is heavily commented for easy customization.

Happy attesting! 📋✓
