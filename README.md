# Monday Jobs Lite (WordPress Plugin)

Ultra‑lean **monday.com → WordPress** jobs list renderer. Drop a shortcode on your Careers page and pull in open roles directly from a monday.com board using the GraphQL API.

> **Shortcode:** `[monday_jobs]`

---

## What it does

- Connects to **monday.com GraphQL** (`https://api.monday.com/v2`) with your **Personal API Token**.
- Reads a single **Board** and maps your job data from specific **column IDs**.
- Outputs a clean, accessible **HTML list** you can style to match your site.
- Optional **JSON‑LD JobPosting** schema for better SEO.
- Built‑in **caching** to avoid rate limits and speed up page loads.

---

## Requirements

- WordPress 5.8+ (tested up to recent versions)
- PHP 7.4+
- A monday.com account with a **Personal API Token**
- A monday.com **Board** containing your jobs

---

## Installation

1. Copy `monday-jobs-lite.php` to a folder under your site’s plugins directory, e.g.:  
   `wp-content/plugins/monday-jobs-lite/monday-jobs-lite.php`
2. In WP Admin, go to **Plugins → Monday Jobs Lite → Activate**.

> This plugin is a single‑file, zero‑dependency implementation for quick installs.

---

## Configuration (WP Admin)

Navigate to **Settings → Monday Jobs Lite** and fill out the fields below.

### API & Board Settings
| Field | What it is |
|---|---|
| **Personal API Token** (`token`) | Your monday.com **Personal API Token**. |
| **Board ID** (`board_id`) | The numeric ID of the board that holds your job items. |

### Column Mapping (IDs from API)
> Use the monday.com API to find **column IDs** (see “Finding IDs” below).

| Field | What it maps |
|---|---|
| **Location column ID** (`col_location`) | Column whose **text** is the job location. |
| **Date column ID** (`col_date`) | Column representing the **posted date** (or any date field you prefer). |
| **Description column ID** (`col_description`) | Rich/long text **description**. |
| **Apply URL column ID** (`col_apply`) | A **link** column pointing to your application URL. |
| **Status column ID** (`col_status`) | A **status** column you use to indicate “Open”. Items marked **Open** are shown. |

### Display, Cache & UX
| Field | Purpose |
|---|---|
| **Cache (minutes)** (`cache_minutes`) | How long to cache API results. |
| **Max items (limit)** (`limit`) | Upper bound on number of jobs to render. |
| **Date format (PHP)** (`date_format`) | PHP date format used for the display date. |
| **Description word limit** (`desc_words`) | Truncates long descriptions for the list view. |
| **Apply button label** (`apply_label`) | Text for the Apply CTA. |
| **“No openings” text** (`empty_text`) | Message shown when no jobs are available. |
| **Show count header** (`show_count`) | If enabled, prints a “X Open Roles” header. |
| **Enable JobPosting schema** (`enable_schema`) | Outputs JSON‑LD for each job. |

> There’s also a **Flush Cache** control in the settings to clear cached results when needed.

---

## Usage

1. Create or edit your Careers page.
2. Add the shortcode:  
   ```text
   [monday_jobs]
   ```
3. Save and view the page. If your token/board/columns are correct, your jobs will render.

> The job **title** comes from the Monday item **name**. All other fields come from the mapped columns above.

---

## Front‑end Markup (CSS hooks)

The plugin renders a minimal structure with predictable class names:

```html
<div class="mjl-list">
  <div class="mjl-item">
    <h3 class="mjl-title">Senior Developer</h3>
    <div class="mjl-meta">
      <span class="mjl-location">Atlanta, GA</span>
      <span class="mjl-date">Aug 20, 2025</span>
    </div>
    <p class="mjl-desc">Short summary of the role…</p>
    <a class="mjl-apply" href="https://example.com/apply" rel="noopener">Apply</a>
  </div>
</div>

<!-- Empty / error states -->
<p class="mjl-empty">No openings at the moment.</p>
<p class="mjl-error">Could not fetch jobs. Please try again later.</p>
```

> You can fully style these classes from your theme. No CSS is bundled by default.

---

## Finding Board & Column IDs (quick GraphQL)

In the **monday GraphQL API Playground**, run a query like:

```graphql
query ($board: [Int], $limit: Int = 50) {
  boards(ids: $board) {
    id
    name
    columns { id title type }
    items_page(limit: $limit) {
      items {
        id
        name
        column_values { id text value }
      }
    }
  }
}
```

Use the board **id** for **Board ID**, and pick the appropriate **column IDs** for Location, Date, Description, Apply URL, and Status.

---

## Caching & Performance

- Responses are cached for `cache_minutes` to reduce API calls and speed up rendering.
- Use the **Flush Cache** control in settings after changing board data if you need an immediate refresh.

---

## Security Notes

- Your **Personal API Token** is stored in WordPress options and shown only to admins with `manage_options` capability.
- All network calls use WordPress HTTP APIs (`wp_remote_post`) and standard timeouts.
- Avoid exposing your token in client‑side code or templates.

---

## Troubleshooting

- **Nothing displays**: verify your **token**, **Board ID**, and **column IDs**. Try **Flush Cache**.
- **Wrong fields**: double‑check column IDs; monday can rename columns while IDs stay stable.
- **Dates look off**: adjust **Date format (PHP)** in settings.
- **Too many jobs**: lower **Max items (limit)**.

---

## Changelog

### 0.1.4
- Initial public “lite” build
- Shortcode rendering: `[monday_jobs]`
- Settings UI for API token, board, column mapping, display and caching
- Optional JSON‑LD JobPosting schema
- Basic error & empty states


