This updated PRD reflects the shift from an external bridge to a **Self-Contained WordPress Plugin**. This approach is more robust because it leverages the native WordPress environment to handle data processing, PDF generation, and job tracking in one place.

---

# PRD: Gravity Forms Smart Print & Tracker (PDF Edition)

## 1. Project Goal

To create a WordPress plugin that intercepts Gravity Form submissions, generates a **high-fidelity PDF label** (including a Base64-embedded QR code), and sends it to a remote printer via the **PrintNode API**—all while providing a real-time tracking dashboard within WordPress.

---

## 2. Updated Architecture (Internal Logic)

The plugin will no longer act as a "dumb pipe." It will actively manage the document lifecycle:

1. **The Trigger:** `gform_after_submission` hook fires.
2. **The Processor:** \* Fetches the HTML template from a specific Gravity Forms field.

- Replaces all merge tags (Name, Company, etc.) with real entry data.
- **The QR Engine:** Identifies the QR code image URL, downloads it, and converts it into a **Base64 string** to embed directly into the HTML `<img>` tag.

3. **The PDF Engine:** Uses the **Dompdf** library to convert the finalized HTML into a binary PDF specifically sized for thermal labels (e.g., 4" x 2").
4. **The Courier:** Base64-encodes the final PDF and transmits it to PrintNode using `wp_remote_post`.

---

## 3. Functional Requirements

### 3.1 Advanced Label Formatting

- **Merge Tag Support:** Must support standard Gravity Forms merge tags and third-party tags (like GP QR Code).
- **Base64 Injection:** Automatically converts any remote image URL found in the label HTML into an embedded Base64 string to ensure the PDF is self-contained.

### 3.2 The "Control Tower" Dashboard

A new top-level menu in WordPress: **"Print Logs"**.

- **Live Status List:** Shows Guest Name, Entry ID, Time, and Status (`Pending`, `Sent`, `Printed`, or `Error`).
- **PrintNode Job ID:** Displays the unique ID for every job for easy cross-referencing in the PrintNode dashboard.
- **Smart Reprint:** A button that re-triggers the _entire_ logic (regeneration and sending) without requiring a new form entry.

### 3.3 Plugin Settings Page

- **API Credentials:** PrintNode API Key and default Printer ID.
- **PDF Settings:** Define custom paper sizes (Width/Height in points or mm).
- **Debugging Mode:** A log viewer to see the raw JSON sent to PrintNode for troubleshooting.

---

## 4. Technical Specifications

### 4.1 Database Logic

Upon activation, the plugin creates `wp_checkin_print_logs`:
| Column | Type | Purpose |
| :--- | :--- | :--- |
| `id` | BIGINT | Auto-increment primary key. |
| `entry_id` | INT | The Gravity Forms Entry ID. |
| `status` | VARCHAR | `success`, `failed`, `queued`. |
| `job_id` | INT | PrintNode’s unique Job ID. |
| `response` | TEXT | Stores the raw API response for error auditing. |

### 4.2 PDF Generation Logic (Internal)

- **Library:** Bundled **Dompdf** (v2.0+).
- **Resolution:** 72 or 96 DPI (standard for thermal rendering).
- **Asset Management:** The plugin will use `file_get_contents()` with a timeout to fetch QR codes from the WordPress uploads folder.

---

## 5. Critical Considerations & Safety

### ⚠️ Performance vs. User Experience

Since PDF generation takes server resources, the plugin must fire the print job **after** the entry is saved but **before** the confirmation message is displayed, or ideally, via an asynchronous background process so the guest doesn't wait for the printer to respond.

### ⚠️ Thermal Scaling

Thermal printers are strictly 1-bit (Black/White).

- **Constraint:** The plugin will force-inject CSS into the HTML to ensure no "gray" shadows or gradients are used, which can cause "fuzzy" prints.

### ⚠️ Security

- **Capability Check:** Only users with `manage_options` (Admins) or a custom `checkin_manager` role can view the Print Logs.
- **Data Retention:** Option to "Auto-Delete Logs" every 7 days to keep the database lean.

---

## 6. Success Criteria

1. **No Popups:** Total bypass of the browser's `window.print()`.
2. **Visual Fidelity:** The printed QR code must be scannable by a standard smartphone from 12 inches away.
3. **Traceability:** Every "Submit" click must result in either a successful Job ID or a logged Error Reason.

---
