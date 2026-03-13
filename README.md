# PDF Attestation Tool

A WordPress Multisite plugin that enforces mandatory accessibility attestation for all PDF uploads. This plugin creates a dedicated, restricted upload workflow that requires users to explicitly attest that PDFs meet current WCAG standards before uploading. All uploads are logged in a permanent audit trail for compliance tracking.

## Overview

The PDF Attestation Tool addresses an important accessibility compliance need: ensuring that PDFs added to your WordPress multisite are accessible. Rather than relying on honor systems or hoping admins remember, this plugin makes accessibility attestation a required, deliberate action for every single PDF upload.

This PDF was originally created for use at Providence College, but the messaging for the landing page can be customized by editing the text in the **class-pdf-attestation-upload.php** file. 

### Key Features

- **Mandatory Attestation**: Every PDF upload requires users to check a box confirming the PDF meets WCAG accessibility standards
- **Dedicated Upload Tool**: PDFs can only be uploaded through a special form, not the standard media library
- **Permanent Audit Trail**: All uploads are recorded with user, date, filename, and blog information in a network-wide database
- **Network Admin Dashboard**: One network-wide attestation table tracks PDFs from all sites in the multisite network. View, search, filter, and export all attestation records from Network Admin
- **No Bypass Mechanisms**: Even administrators must use the dedicated tool—there are no exceptions or overrides
- **CSV Export**: Download attestation records for compliance auditing and legal documentation

## Installation

### Requirements

- WordPress Multisite (WordPress 5.0+)
- PHP 7.2+
- MySQL/MariaDB with InnoDB support

### Installation Steps

1. **Download the plugin** and extract it to `/wp-content/plugins/pdf-attestation-tool/`

2. **Activate the plugin**:
   - Go to Network Admin → Plugins
   - Find "PDF Attestation Tool"
   - Click "Network Activate"

3. **Configure PDF blocking** (manually in Network Admin Settings -- this is a belt-and-suspenders step that is not required for the plugin to run, but should be completed to futureproof use):
   - The plugin requires you to remove `pdf` from your allowed file types
   - Go to Network Admin → Settings → Upload Settings
   - Find the list of allowed file extensions
   - Remove `pdf` from the list (keep other extensions)
   - Save settings
   - This ensures PDFs can only be uploaded via the attestation tool

4. **Verify installation**:
   - The plugin automatically creates a network-wide database table on activation
   - Visit a site in your network and go to Tools → PDF Upload Tool
   - Visit Network Admin → PDF Attestations to view the audit dashboard
  
## For Single-Site WordPress

This plugin is built specifically for WordPress Multisite. If you want to use it on a single-site WordPress installation, you can modify it:

1. Open `/includes/class-pdf-attestation-database.php`
2. Change `$this->wpdb->base_prefix` to `$this->wpdb->prefix` on lines that reference the table
3. In the main plugin file, comment out or remove the multisite check (the block that starts with `if ( ! is_multisite() )`)
4. The plugin should then work on single-site installations, but that's not supported (if you want to fork this repo and do that, have at it!)


## Usage

### Uploading PDFs (For Regular Users)

1. **Navigate to the Upload Tool**:
   - Log in with an account that has standard upload permissions
   - Go to Tools → PDF Upload Tool

2. **Select a PDF file**:
   - Click "Select PDF File"
   - Choose one PDF from your computer (you can only upload one at a time)

3. **Confirm the Accessibility Attestation**:
   - Read the attestation statement and check the box to agree to the attestation
   - Click "Upload PDF"

4. **Success**:
   - If the upload succeeds, you'll see a success message
   - The PDF is now available in your site's media library
   - Your name, the upload date, and the PDF details have been recorded in the audit trail on both the individual site and the Network 

### Viewing Attestation Records (For Network Admins)

1. **Access the Audit Dashboard**:
   - Log in with super-admin/network admin privileges
   - Go to Network Admin → PDF Attestations

2. **View all PDFs uploaded across your network**:
   - The dashboard shows all PDFs uploaded from all sites
   - Each record shows: UID, Site, Username, Filename, Upload Date, and Attestation Status

3. **Search and Filter**:
   - **Search**: Find PDFs by filename or username
   - **From Date / To Date**: Filter by date range
   - **Site**: Filter by specific site in the network
   - **User**: Filter by uploader
   - **Records per page**: Choose 100, 500, or 1000 records per page (but if we have 1000 I might cry)

4. **Export Records**:
   - Click "Export CSV" to download filtered records
   - CSV includes: UID, Site, Blog ID, Username, User ID, Filename, Upload Date, Status, Created At

## How It Works, in Narrative for HiPPOs

### The Upload Process

1. User navigates to the PDF Upload Tool (accessible only to users with upload permissions)
2. User selects a single PDF file
3. User reads the WCAG accessibility attestation statement
4. User checks the confirmation box (form will not submit without it)
5. User clicks "Upload PDF"
6. HIDDEN: Plugin verifies the nonce (CSRF protection)
7. HIDDEN: Plugin validates the file is actually a PDF
8. HIDDEN: Plugin generates a unique UID combining date, blog, user, and filename
9. Plugin adds an attestation record to the network-wide database
10. HIDDEN: Plugin adds the PDF to WordPress's uploads directory
11. Plugin creates a WordPress media attachment so the PDF appears in the media library
12. User sees success message; PDF is ready to use

### PDF Blocking

This plugin blocks PDFs from being uploaded through **any other mechanism**:

- ❌ Standard Media Library upload
- ❌ Drag-and-drop media uploader
- ❌ REST API endpoints
- ❌ Any non-attestation method

Only the dedicated attestation tool allows PDF uploads.

### The Audit Trail

Every PDF upload creates a permanent record in the database including:

- **UID**: NOT VISIBLE IN UI: Unique identifier with date, site, user, filename, and random hash
- **Blog ID**: NOT VISIBLE IN UI: Which site in the network the PDF came from
- **Blog Name**: NOT VISIBLE IN UI: Human-readable site name (included in UID for readability)
- **User ID**: NOT VISIBLE IN UI: WordPress user ID who uploaded
- **Username**: WordPress username who uploaded
- **Filename**: Original PDF filename
- **Upload Timestamp**: Exact date and time of upload
- **Attestation Status**: Boolean (always true—only attested files are allowed)
- **Created At**: NOT VISIBLE IN UI: When the record was created

These records are **permanent** and cannot be edited/deleted. This creates an auditable, legally defensible record of all PDFs added to your network.


## Database Schema (for Robots, or People Who Like Databases)

The plugin creates one network-wide table:

```
{$wpdb->base_prefix}pdf_attestations

Columns:
- id (bigint) - Auto-increment primary key
- uid (varchar 255) - Unique identifier, indexed
- blog_id (bigint) - Site/blog ID, indexed
- user_id (bigint) - WordPress user ID, indexed
- username (varchar 100) - WordPress username
- filename (varchar 255) - Original PDF filename
- timestamp (datetime) - Upload date/time, indexed
- attestation_status (tinyint) - Boolean status (1 = attested)
- created_at (datetime) - Record creation timestamp, indexed
```

The table is created automatically on plugin activation. If the table already exists, the plugin uses it as-is (supports reactivation without data loss).

## User Capabilities

The plugin uses WordPress's built-in `upload_files` capability to control access. Users who can:

- Upload to the media library can use the PDF attestation tool
- This typically includes: Editors, Authors, Contributors, Administrators

No new custom capabilities are created—the plugin integrates with existing WordPress permission structures.

## Security Features

✓ **CSRF Protection**: All forms use WordPress nonces  
✓ **Capability Checks**: Only authorized users can access the upload tool  
✓ **File Type Validation**: MIME type checking, not just extension  
✓ **Input Sanitization**: All user input is sanitized before database storage  
✓ **SQL Injection Prevention**: All queries use prepared statements  
✓ **REST API Protection**: PDFs are blocked from REST API uploads  
✓ **No Bypass Mechanisms**: Even super-admins must use the attestation tool  

## Compliance and Legal Considerations

This plugin helps with compliance by:

- Creating a deliberate, documented process for PDF uploads
- Requiring explicit attestation by the person uploading
- Maintaining an audit trail of all uploads
- Tracking which user uploaded which PDF and when
- Providing exportable records for Section 504, ADA, and WCAG audits

**Important**: This plugin enforces the process and records attestations. It does **not** automatically validate that PDFs are actually accessible. Your organization is still responsible for ensuring that PDFs actually meet WCAG standards. Use additional accessibility testing tools and procedures as part of your compliance workflow.

## Troubleshooting

### PDF uploads are not allowed through the standard media library

This is expected! PDFs must be uploaded using the PDF Upload Tool (Tools → PDF Upload Tool). This error appears if you try to upload a PDF through the regular media library.

### Plugin doesn't activate

- Verify you're running WordPress Multisite (not single-site)
- Check that PHP version is 7.2 or higher
- Ensure the plugin folder is at `/wp-content/plugins/pdf-attestation-tool/`
- Check the WordPress error log for detailed error messages

### Attestation records aren't appearing

- Make sure the plugin was network-activated (not just activated on individual sites)
- Check that the database table was created
- Verify PDFs are actually being uploaded successfully
- Check the WordPress error log for any errors

### CSV export is empty

- Use the filters to refine your search
- Check the date range if you're filtering by dates
- Ensure that some PDFs have actually been uploaded to the system

## Code Structure

```
pdf-attestation-tool/
├── pdf-attestation-tool.php              # Main plugin file with hooks
├── includes/
│   ├── class-pdf-attestation-database.php    # Database operations
│   ├── class-pdf-attestation-upload.php      # Upload form and processing
│   ├── class-pdf-attestation-admin.php       # Network admin interface
│   └── functions-pdf-attestation.php         # Helper functions
├── README.md                             # This file
├── QUICK_START.md                        # What it says on the tin
├── LICENSE                               # PolyForm Noncommercial License 1.0.0
└── languages/                            # Localization (for future use)
```

## Performance Considerations

- The plugin stores all attestations in a single network-wide table
- Queries are indexed on frequently-searched columns (uid, blog_id, user_id, timestamp)
- The admin dashboard uses pagination (default 100 records per page)
- Large networks may want to periodically archive old records

## License

Licensed under the [PolyForm Noncommercial License 1.0.0](https://polyformproject.org/licenses/noncommercial/1.0.0/)

This license permits free use for non-commercial purposes. Commercial use requires a separate commercial license.

---

**Last Updated**: March 2026 
**Version**: 1.0.1  
**Compatibility**: WordPress 5.0+ Multisite, PHP 7.2+
