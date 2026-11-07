# Trongate Events Module and Calendar

A complete, working events module for [Trongate v2](https://github.com/trongate/trongate-framework) with a built-in month-view calendar. It is designed to be dropped into a Trongate application as a starting point and then adapted — for bookings, appointments, training diaries, schedules, or anything else where records live on dates.

This is not a partial extract or a tutorial. It is a full module: create, read, update, delete, search, pagination, a browsable calendar, and modal day views.

---

## Contents

- [1. Introduction](#1-introduction)
- [2. Installation](#2-installation)
- [3. Database structure](#3-database-structure)
- [4. Configuration](#4-configuration)
- [5. Usage](#5-usage)
- [6. Adaptation](#6-adaptation)
- [7. Known limitations](#7-known-limitations)
- [8. File reference](#8-file-reference)

---

## 1. Introduction

### What the module does

The module manages a table of dated records ("events") and presents them in two ways:

- **A management screen** (`events/manage`) — a paginated, searchable table of records with per-row view, edit and delete actions.
- **A calendar** (`events/calendar`) — a Monday-first month grid in which each day cell shows the day number and a short summary of what is happening on that date.

The calendar and the table are views of the same data. Anything created through the standard forms appears on the calendar immediately, and the calendar is a genuine entry point: clicking an empty day opens the creation form with that date pre-filled, while clicking a day that contains events opens a modal listing those events.

### Functionality provided

- Full CRUD, with validation and CSRF protection via Trongate's own form helpers.
- Month-view calendar with previous/next month navigation and a **Today** button.
- Weekday and weekend styling, days from adjacent months shown greyed out, today's date highlighted.
- Days holding one or more "important" records are highlighted in yellow.
- Each day cell summarises its records: one record shows its title, two or more show a count.
- Click an empty day → create form pre-filled with that date.
- Click a day with records → modal listing those records, each linking to view/edit/delete.
- Paginated management table with a "records per page" selector (10 / 20 / 50 / 100), remembered in the session.
- Search across the searchable columns, with a search modal and result count.
- Record preview modal from the management table, plus dedicated view, edit and delete-confirmation pages.
- Friendly "not found" page for missing records.

### Where it could be useful

The date-grid plus record-list pattern fits a wide range of applications:

- **Events calendar** — what this repository ships with.
- **Bookings** — a holiday cottage, a venue, a piece of equipment: each booking is a record with a date.
- **Appointments** — a clinic, salon or garage diary, where times matter as much as dates.
- **Training diary** — courses, sessions or assessments scheduled across a month.
- **Rotas and schedules** — who is on duty, and when.
- **Deadlines and reminders** — anything with a due date that benefits from a month overview.

---

## 2. Installation

### Requirements

| Requirement | Notes |
|---|---|
| Trongate v2 | Developed and verified against framework version `2.2026.1028` |
| PHP 8.0 or later | The module uses union return types such as `array|bool` |
| MariaDB or MySQL | The schema is MySQL/MariaDB specific (`AUTO_INCREMENT`, `ENGINE=InnoDB`) |
| An authenticated Trongate admin area | Every controller method calls `$this->trongate_security->make_sure_allowed()` |

The module has **no third-party dependencies**. No Composer, no JavaScript libraries, no CSS frameworks. It uses only what ships with Trongate.

### Step 1 — Download

```bash
git clone https://github.com/trongate/trongate-events-module-and-calendar.git
```

Or use **Code → Download ZIP** on the GitHub page and extract it.

### Step 2 — Copy the module into your application

The repository mirrors the layout of a Trongate application, so the copy is a single directory:

```
your-trongate-app/
├── config/
├── engine/
├── modules/
│   └── events/          <-- copy this directory here
└── public/
```

```bash
cp -r trongate-events-module-and-calendar/modules/events your-trongate-app/modules/
```

Nothing outside `modules/events/` needs to be copied. The module does **not** modify `config/config.php`, and it does not require any framework file to be edited.

### Step 3 — Create the database table

Run the SQL in [section 3](#3-database-structure) against your application's database. `sql/events.sql` in this repository contains the same statement, ready to paste into phpMyAdmin.

### Step 4 — Add navigation (recommended)

The module does not alter your application's template, so add your own links. In the standard Trongate admin template — `modules/templates/views/admin.php` — you will find the admin navigation list; add two entries:

```html
<li>
    <a href="events/manage">Manage Events</a>
</li>
<li>
    <a href="events/calendar">Events Calendar</a>
</li>
```

This step is cosmetic. The module works without it — you can simply visit the URLs directly.

### Step 5 — Visit the module

With `BASE_URL` pointing at your application:

- `BASE_URL + events/manage` — the record table
- `BASE_URL + events/calendar` — the calendar

`BASE_URL + events` (no method) redirects to `events/manage`.

### Additional setup requirements

There are none beyond the above. Specifically:

- No Composer install.
- No `config/` changes.
- No new CSS or JavaScript to include — the stylesheet is served from the module itself (see [Configuration](#4-configuration)).
- No routes to register — Trongate maps URLs to modules and methods automatically.

---

## 3. Database structure

The module needs **one table**: `events`.

### SQL

Paste the following into the SQL tab of phpMyAdmin (with your application's database selected) and click **Go**:

```sql
CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_title` varchar(255) NOT NULL,
  `event_description` text DEFAULT NULL,
  `date_and_time` datetime NOT NULL,
  `important` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Fields

| Field | Type | Purpose |
|---|---|---|
| `id` | `int(11)`, auto-increment, primary key | The record identifier. Used in every URL — `events/show/12`, `events/create/12`, `events/delete_conf/12`. |
| `event_title` | `varchar(255)`, `NOT NULL` | The headline of the record. This is the only **searchable** column (see `get_searchable_columns()` in `Events_model.php`), and it is what the calendar prints inside a day cell. Required, minimum 2 characters, maximum 255. |
| `event_description` | `text`, nullable | Free-text detail. Rendered in the view page with `nl2br()` so line breaks survive. Optional, but if supplied it must be at least 2 characters. |
| `date_and_time` | `datetime`, `NOT NULL` | The date and time of the record — the column the entire calendar is built from. A `datetime` was chosen deliberately: it keeps time-of-day available for appointment-style uses while the calendar groups by date alone. Stored as `YYYY-MM-DD HH:MM:SS`. |
| `important` | `tinyint(1)`, default `0` | A boolean flag. `1` marks the record as important; any day containing at least one important record is highlighted yellow in the calendar. The edit form renders it as a checkbox. |

### Notes on the schema

- **No indexes beyond the primary key.** That is intentional and adequate for a demonstration-scale module. If you are adapting this for a large dataset you will want an index on `date_and_time`, because the calendar queries filter and range-scan on it (`WHERE date_and_time >= :start AND date_and_time < :end`). This is the one change worth making early.
- **`utf8mb4` / `utf8mb4_unicode_ci`** matches the rest of a standard Trongate installation and avoids surprises with non-Latin text and emoji.
- **The table name appears in exactly one place** — `private string $table_name = 'events';` in `Events_model.php`. Change it there and the whole module follows.
- **No foreign keys.** Records are self-contained; there is nothing to join to.
- **No seed data is included.** A fresh install shows "There are currently no records to display", which is correct behaviour.

### If you already have a table called `events`

Create the module's table under a different name and update `$table_name` in `Events_model.php` to match. No other file contains the table name.

---

## 4. Configuration

### Configuration files

**None.** The module requires no additions to `config/config.php`, `config/database.php` or any other configuration file. Whatever `BASE_URL` and database credentials your application already uses are all it needs.

### Dependencies on the broader framework

The module calls into these framework-provided parts. All are present in a stock Trongate v2 installation:

| Dependency | Used for | Where |
|---|---|---|
| `trongate_security` module | `make_sure_allowed()` at the top of every public controller method | `Events.php` |
| `trongate_administrators` module | The actual authentication behind `make_sure_allowed()` | indirectly, via `trongate_security` |
| `templates` module | `$this->templates->admin($data)` renders every full page | `Events.php` |
| `pagination` module | `Modules::run('pagination/display', $pagination_data)` | `views/manage.php` |
| `validation` module | Rules in `submit()`, including the framework's `valid_datetime_local` rule | `Events.php` |
| Form helpers | `form_open`, `form_input`, `form_textarea`, `form_datetime_local`, `form_checkbox`, `form_submit`, `form_dropdown`, `form_label`, `form_button` | `views/*` |
| Trongate MX | Calendar month navigation, day modals, record preview modals, search modal | `views/calendar.php`, `views/manage.php` |
| Module asset serving | Delivers `events_module/css/calendar.css` from `modules/events/css/calendar.css` | `Events::calendar()` |

### How the stylesheet is loaded

`Events::calendar()` passes one entry to `templates->admin()`:

```php
$data['additional_includes_top'] = ['events_module/css/calendar.css'];
```

The `_module` part of that path is **not** a directory in the application. It is `MODULE_ASSETS_TRIGGER`, defined in `config/config.php` as `'_module'`. Trongate's router recognises it and serves the file from inside the module directory, which is why the calendar styles live with the module rather than in `public/css/`.

Two consequences worth knowing:

- Do not create an actual `events_module` directory — it would be redundant.
- If you rename the module directory, rename this path to match (`bookings_module/css/calendar.css`, and so on).

### Assumptions the module makes about the host application

1. **The host app has a working Trongate admin area.** Every method calls `$this->trongate_security->make_sure_allowed()`, which authenticates against `trongate_administrators` and `trongate_tokens`. The pages are therefore admin-only.
2. **The host app has at least one active administrator.** On a fresh install with no admin account, access control will fail. Trongate's `setup` module creates the first account.
3. **`ENV` is set as normal** (`define('ENV', 'dev')` or `'production'` in `config/config.php`). Note that in `dev`, Trongate's administrator module auto-logs-in as the first active user — convenient while building, but do not ship a `dev` `ENV` to production.
4. **The `templates` module provides an `admin()` method.** A stock Trongate v2 install does. If you have replaced the `templates` module with an entirely different layout, that is the one call you must adapt.
5. **Trongate MX is available.** It ships with the framework (`public/js/trongate-mx.js`) and is loaded by the stock admin template.
6. **The table is named `events`** unless you change `$table_name`.

### If you want a public, non-admin calendar

The authentication calls are all the same shape:

```php
$this->trongate_security->make_sure_allowed();
```

For a public calendar you would remove that line from whichever methods should be public (`calendar` and `day_events`, most likely), and switch those methods from `$this->templates->admin($data)` to `$this->templates->public($data)`. Nothing else in the module assumes an authenticated user — the module has no per-user filtering and no ownership columns.

---

## 5. Usage

### Getting there

| URL | What you get |
|---|---|
| `events` | Redirects to `events/manage` |
| `events/manage` | Paginated table of all records |
| `events/manage/{page}` | A specific page of the table |
| `events/manage?search_query=…&search_column=…` | Filtered table |
| `events/calendar` | Calendar for the current month |
| `events/calendar/{year}/{month}` | Calendar for a specific month, e.g. `events/calendar/2027/2` |
| `events/create` | Blank creation form |
| `events/create/{date}` | Creation form with the date pre-filled, e.g. `events/create/2027-02-14` |
| `events/create/{id}` | Edit form for an existing record |
| `events/show/{id}` | Read-only detail page |
| `events/delete_conf/{id}` | Delete confirmation page |

Year and month segments are validated (year 1900–2100, month 1–12); anything outside that range falls back to the current month rather than erroring.

### The calendar

The calendar is the centrepiece, so it is worth spelling out:

- **Month header** shows the month and year, e.g. "November 2026".
- **‹ and ›** move to the previous and next month. These are Trongate MX requests: the month is fetched and swapped into the page without a reload, and the URL is pushed so the browser's back button behaves sensibly.
- **Today** appears only when you are not looking at the current month, and returns you to it.
- **Day cells** are Monday-first and always form complete weeks — days from the end of the previous month and the start of the next fill the grid, greyed out.
- Each cell shows the day number, plus a summary line: a single record's title, or "3 events today" when there are several.
- **Empty days are links.** Clicking one goes to `events/create/{date}`, with `date_and_time` pre-filled to that date at 10:00 (from `$default_event_time` in the controller).
- **Days with records are modal triggers.** Clicking one opens a modal headed with the full date, listing each record's title (linking to its view page), date/time, importance, and view/edit/delete buttons. If you arrive at that URL without a modal (e.g. a direct visit to `events/day_events/2026-11-20`), you are redirected to the calendar.
- **Highlighting**: today gets a filled day-number badge, weekends are faintly tinted, and any day holding an important record is yellow.

### Creating a record

1. Go to `events/manage` and click **Create New Event Record**, or click an empty day in the calendar.
2. Fill in **Event Title** (required, 2–255 characters), **Event Description** (optional), **Date And Time** (required, `datetime-local`, so `YYYY-MM-DDTHH:MM`), and tick **Important** if applicable.
3. **Submit**.

If validation fails you are returned to the same form with your input preserved and the errors shown in one block above the form (`validation_errors()`). On success a flash message appears and you are redirected — to `events/manage` for a new record, or to `events/show/{id}` for an update.

Client-side hints (`required`, `minlength`, `maxlength`) mirror the server-side rules, but the server rules are the ones that count.

### Viewing, editing and deleting

- **View** — `events/show/{id}` shows every field plus **Back**, **Edit** and **Delete**. The Back button returns you to the manage page you came from when Trongate's `previous_url()` says that is where you were, otherwise to `events/manage`.
- **Edit** — `events/create/{id}` is both the create and the edit form; the headline and target URL change. Editing an existing record keeps its `id`, and you land on the view page afterwards.
- **Delete** — `events/delete_conf/{id}` asks for confirmation, then posts to `events/submit_delete/{id}`. Deletion is permanent; there is no undo and no soft-delete column. You are returned to `events/manage` with a flash message.
- A missing or deleted record shows a friendly "Event Not Found" page rather than an error.

### Searching

Search is offered when there are more than nine records, or when a search is already active. It opens a modal with a query field and a column dropdown (populated from `get_searchable_columns()` — `event_title` only, by default). Queries must be at least two characters. The active search is echoed above the table, and pagination links keep the query intact.

### Pagination

The **Records Per Page** dropdown offers 10, 20, 50 and 100. The choice is stored in the session (`$_SESSION['selected_per_page']`), so it survives navigation. Default page size is 20.

### Record preview

Clicking a record's title in the management table opens a preview modal containing the record's detail grid, with a **View Record** button to go to the full page. The modal is built by Trongate MX from `events/show/{id}` using an `mx-select` on `.detail-grid`.

---

## 6. Adaptation

This section is the reason the repository exists. The module is written so that the **calendar machinery** can stay put while the **domain** changes underneath it.

### Separating the generic from the specific

Broadly, the code splits like this:

**Generic calendar machinery — normally left alone:**

| Location | What it does |
|---|---|
| `Events::build_calendar_data()` | Builds the Monday-first month grid: leading days, days in month, total cells, previous/next month links, today detection, CSS classes, per-day summaries. This is pure calendar logic with no knowledge of what an "event" is. |
| `Events::get_day_event_summary()` | "One record → its title; several → a count." |
| `Events::calendar()` | Reading year/month from the URL, range validation, MX-vs-full-page rendering. **Only edit:** the stylesheet path if you rename the module. |
| `Events::day_events()` and `Events::is_valid_date()` | The modal-fragment pattern for a single day. |
| `views/calendar.php` | The whole grid, the MX navigation attributes, the modal options. Only the English strings ("Events Calendar", "Add an event on …") are domain-specific. |
| `css/calendar.css` | Presentation of the grid. Domain-neutral except for `.has-important-event`, which is named after the flag it serves. |

**Domain-specific — what you will change:**

| Location | What to change |
|---|---|
| `Events_model::$table_name` | Your table. |
| `Events_model::get_data_from_post()` | The field list and any casting. |
| `Events_model::get_searchable_columns()` | Which columns the search covers. |
| `Events_model::prepare_record_for_display()` | How raw values are formatted for display (`trim()`, the boolean-to-yes/no mapping). |
| `Events_model::get_event_titles_by_date()` | Which column is used as the cell label, and which column drives the "important" highlight. |
| `Events_model::get_events_by_date()` | The per-day query — change if days are defined by something other than a single `datetime`. |
| `Events::submit()` | Validation rules for your fields. |
| `views/create.php` | The form: labels, fields, helper choice, validation constraints. |
| `views/show.php` | The detail grid. |
| `views/manage.php`, `views/search_modal.php`, `views/day_events.php` | Table headings and columns. |

### Renaming the module

To turn `events` into `bookings` (say):

1. Rename `modules/events/` to `modules/bookings/`.
2. Rename `Events.php` → `Bookings.php`, `Events_model.php` → `Bookings_model.php`, and rename the classes inside them to match the filenames — Trongate resolves controllers and models by filename.
3. Replace the URL/module strings. `'events'` appears 58 times in `Events.php`, 9 times in `Events_model.php`, and a handful of times in the views; each occurrence is a route (`events/manage`), a `view_module` value, or a docblock example. A single find-and-replace across the module directory handles all of them, plus the CSS include path in `Events::calendar()`.
4. `css/calendar.css` mentions "events" only inside comments.

### Adapting for **bookings** (e.g. a holiday cottage)

- Rename to `bookings` as above.
- Table: `bookings` with `guest_name`, `notes`, `arrival` / `departure`, `status`.
- **This is the significant structural change**: a booking spans two dates, whereas this module assumes a single `datetime`. You would add a second date column and change the range query in `get_event_titles_by_date()` from "does this datetime fall inside the day" to "does the booking overlap this day":

  ```sql
  WHERE arrival <= :day_end AND departure >= :day_start
  ```

  and read `$grid_start`/`$grid_end` accordingly. The grid-building code above it needs no change at all — it hands you a date range and asks which days have records.
- Repurpose `important` as `confirmed` (or keep it and add `status`), and either keep the yellow highlight for unpaid bookings or rename `.has-important-event` in the CSS to match.
- Day cells: with multi-day bookings a title in the cell may be misleading, so you might replace `get_day_event_summary()` with something like "Available" / "Booked".

### Adapting for **appointments**

The closest fit, and the least work.

- Values in `date_and_time` already carry time-of-day, and `get_events_by_date()` orders by it, so a day's modal lists appointments in time order.
- Add `client_name`, `service`, `duration_mins`, `staff_id` to the table, `get_data_from_post()`, and the three views.
- Change the label for the date field from "Date And Time" to "Appointment Time", and optionally add `step="900"` to the `form_datetime_local` attributes for 15-minute increments.
- The `$default_event_time = '10:00'` property controls what time a click on an empty day pre-fills — change it to suit your opening hours.
- Because `date_and_time` is displayed raw (`2026-11-20 10:00:00`) in `views/manage.php`, `views/day_events.php` and `views/show.php`, this is where you would format it — see the tip below.

### Adapting for a **training diary**

- Same approach as appointments, with `course_title`, `trainer`, `location`.
- Useful extra: filter `get_event_titles_by_date()` by something the current user is entitled to (e.g. a `trainer_id` column) if each trainer should see only their own diary. The controller already establishes an authenticated user, so the value is available.

### Adapting the day cell without touching the grid

`build_calendar_data()` decides what each cell contains. If you want a cell to carry different information:

- Change `get_day_event_summary()` to return a different string — that is the single line of text printed in the cell.
- Change the `$classes` logic to add your own CSS classes; they are passed through in `css_class` and printed verbatim.
- Change `get_event_titles_by_date()` to group by whatever you like, as long as it returns the same two-array shape (`event_titles` keyed by `Y-m-d`, and `important_dates`). That shape is the contract between the model and the calendar.

### Formatting dates and times for display

Three views print `date_and_time` verbatim. To render something friendlier, either format it in `prepare_record_for_display()` (which every list path already runs through) or in the view:

```php
<?= out(date('D j M Y, H:i', strtotime($row->date_and_time))) ?>
```

Note that `prepare_record_for_display()` converts `important` from `1`/`0` to `'yes'`/`'no'` — so `get_data_for_edit()` exists specifically to fetch the raw row for the edit form, where a checkbox needs the number rather than the string. Keep that distinction if you add further boolean fields.

### Changing the calendar's first day of the week

`build_calendar_data()` builds a Monday-first grid using `format('N')` (1 = Monday … 7 = Sunday), and hard-codes the header list:

```php
'weekdays' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
```

For a Sunday-first calendar, change the header list and subtract from `format('w')` instead of `format('N')` when computing `$leading_days`. The weekend tinting test (`in_array($cursor->format('N'), ['6', '7'])`) would need adjusting too.

---

## 7. Known limitations

Stated plainly, so nobody is surprised:

- **One date per record.** There are no multi-day, all-day or recurring events. Extending to multi-day is described under Adaptation; recurrence would need a rule table and generated occurrences.
- **`date_and_time` is printed raw** in the table, day modal and detail page (`2026-11-20 10:00:00`). Formatting is left to the adapter.
- **Search covers `event_title` only.** Adding columns is a one-line change in `get_searchable_columns()`, plus a matching entry in `views/search_modal.php`.
- **No per-user data.** Every authenticated administrator sees every record. There is no ownership column and no filtering.
- **No soft delete and no audit trail.** Deletion removes the row.
- **No indexes on `date_and_time`,** which is fine at demonstration scale and worth adding before real volume.
- **Search is a `LIKE` query,** not full-text.
- **Admin-only as shipped.** See the public-calendar note in Configuration.

---

## 8. File reference

```
trongate-events-module-and-calendar/
├── README.md
├── sql/
│   └── events.sql              Ready-to-paste schema (as shown in section 3)
└── modules/
    └── events/
        ├── Events.php          Controller: CRUD, calendar, search, pagination, not-found
        ├── Events_model.php    Model: queries, display preparation, table name
        ├── css/
        │   └── calendar.css    Calendar grid styling
        └── views/
            ├── calendar.php    Month grid with MX navigation and day modals
            ├── create.php      Create/edit form
            ├── day_events.php  Modal fragment: one day's records
            ├── delete_conf.php Delete confirmation
            ├── manage.php      Paginated record table with search and preview
            ├── not_found.php   Missing-record page
            ├── search_modal.php Search form fragment
            └── show.php        Read-only detail page
```

### Controller methods

| Method | URL | Purpose |
|---|---|---|
| `index()` | `events` | Redirects to `manage` |
| `manage()` | `events/manage[/{page}]` | Paginated, searchable table |
| `calendar()` | `events/calendar[/{year}/{month}]` | Month grid; MX requests get the fragment only |
| `day_events()` | `events/day_events/{date}` | MX fragment: records for one day |
| `create()` | `events/create[/{id}|/{date}]` | Create form, edit form, or date pre-fill |
| `submit()` | `events/submit[/{id}]` | Validates and persists |
| `show()` | `events/show/{id}` | Detail page |
| `delete_conf()` | `events/delete_conf/{id}` | Delete confirmation |
| `submit_delete()` | `events/submit_delete/{id}` | Deletes on confirmation |
| `set_per_page()` | `events/set_per_page/{index}` | Stores page-size preference in session |
| `search_modal()` | `events/search_modal` | MX fragment: search form |
| `submit_search()` | `events/submit_search` | Validates and redirects to a filtered list |

### Model methods

| Method | Purpose |
|---|---|
| `get_searchable_columns()` | Whitelist of searchable columns — also used by the controller to validate input |
| `get_data_from_post()` | Field list from POST |
| `get_data_from_db()` / `get_data_for_edit()` / `find_by_id()` | Single-record fetches (display-prepared, raw, or as object) |
| `fetch_records()` / `count_all()` | Paginated listing |
| `search_records()` / `count_search_results()` | Bound `LIKE` queries |
| `get_event_titles_by_date()` | Date-range fetch grouped by day — feeds the calendar |
| `get_events_by_date()` | One day's records, ordered by time — feeds the day modal |
| `prepare_records_for_display()` / `prepare_record_for_display()` | Display formatting |
| `create_new_record()` / `update_record()` / `delete_record()` | Writes |

---

## Verified

The copy in this repository was tested as an independent module: a fresh Trongate v2 (`2.2026.1028`) installation with only `modules/events/` and the table from `sql/events.sql` added. Calendar rendering and month navigation, month boundaries, the calendar stylesheet, day-cell summaries and important-event highlighting, the day modal fragment, create with date pre-fill, create/edit validation round trip, view page, search modal, delete confirmation and deletion, pagination, and the not-found page were all exercised successfully.
