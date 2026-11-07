<?php
/**
 * Events Controller
 *
 * Manages event records with full CRUD operations.
 */
class Events extends Trongate {

    private int $default_limit = 20;
    private array $per_page_options = [10, 20, 50, 100];
    private string $default_event_time = '10:00';
    
    /**
     * Default entry point - redirects to manage page
     *
     * @return void
     */
    public function index(): void {
        redirect('events/manage');
    }

    /**
     * Display paginated list of events.
     *
     * Shows records in a table with pagination controls. Includes
     * dropdown for selecting number of records per page.
     *
     * @return void
     */
    public function manage(): void {
        $this->trongate_security->make_sure_allowed();

        $search_query = $_GET['search_query'] ?? '';
        $search_column = $_GET['search_column'] ?? '';

        // Validate column against known searchable columns.
        $allowed_columns = $this->model->get_searchable_columns();
        if ($search_column !== '' && !in_array($search_column, $allowed_columns, true)) {
            $search_column = '';
        }

        $search_active = ($search_query !== '');

        if ($search_active) {
            $total_rows = $this->model->count_search_results($search_query, $search_column);
        } else {
            $total_rows = $this->model->count_all();
        }
        
        $limit = $this->get_limit();
        $offset = $this->get_offset();

        if ($search_active) {
            $rows = $this->model->search_records($search_query, $search_column, $limit, $offset);
        } else {
            $rows = $this->model->fetch_records($limit, $offset);
        }
        $rows = $this->model->prepare_records_for_display($rows);

        $data = [
            'rows' => $rows,
            'pagination_data' => $this->get_pagination_data($total_rows, $limit, $search_query, $search_column),
            'view_module' => 'events',
            'view_file' => 'manage',
            'per_page_options' => $this->per_page_options,
            'selected_per_page' => $this->get_selected_per_page()
        ];

        $data['search_query'] = $search_query;
        $data['search_column'] = $search_column;
        $data['search_active'] = $search_active;
        $this->templates->admin($data);
    }

    /**
     * Display a browsable month-view calendar of events.
     *
     * The year and month are taken from URL segments three and four, e.g.
     * 'events/calendar/2026/11'.  Both fall back to the current month when
     * missing or out of range.  Direct visits receive a full admin page;
     * Trongate MX requests receive the calendar fragment only.
     *
     * @return void
     */
    public function calendar(): void {
        $this->trongate_security->make_sure_allowed();

        $year = segment(3, 'int');
        $month = segment(4, 'int');

        if (($year < 1900) || ($year > 2100) || ($month < 1) || ($month > 12)) {
            $year = (int) date('Y');
            $month = (int) date('n');
        }

        $data = $this->build_calendar_data($year, $month);
        $data['view_module'] = 'events';
        $data['view_file'] = 'calendar';

        if (from_trongate_mx() === true) {
            $this->view('calendar', $data);
            return;
        }

        $data['additional_includes_top'] = ['events_module/css/calendar.css'];
        $this->templates->admin($data);
    }

    /**
     * Display the events for a single day as a Trongate MX fragment.
     *
     * Triggered when a calendar day cell containing one or more events is
     * clicked.  The fragment is rendered into a dynamically built modal.
     * The date is taken from URL segment three, e.g. 'events/day_events/2026-11-10'.
     *
     * @return void
     */
    public function day_events(): void {
        $this->trongate_security->make_sure_allowed();

        $date = (string) segment(3);

        if (($this->is_valid_date($date) === false) || (from_trongate_mx() === false)) {
            redirect('events/calendar');
            return;
        }

        $rows = $this->model->get_events_by_date($date);

        $data = [
            'rows' => $this->model->prepare_records_for_display($rows),
            'day_label' => date('l j F Y', strtotime($date))
        ];

        $this->view('day_events', $data);
    }

    /**
     * Read an optional date from URL segment three.
     *
     * Used by 'events/create/{date}' links originating from the calendar.
     *
     * @return string A valid Y-m-d date, or an empty string when segment three is not a date.
     */
    private function get_preset_date_from_url(): string {
        $segment_value = (string) segment(3);
        return ($this->is_valid_date($segment_value) === true) ? $segment_value : '';
    }

    /**
     * Establish whether a string is a real date in Y-m-d format.
     *
     * @param string $date The string to test.
     * @return bool True for a valid Y-m-d date, otherwise false.
     */
    private function is_valid_date(string $date): bool {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return ($parsed !== false) && ($parsed->format('Y-m-d') === $date);
    }

    /**
     * Display form for creating or editing a event.
     *
     * Shows form with appropriate headline and action URL.
     * Automatically repopulates form with submitted data on validation errors.
     *
     * URL segment three may optionally be a date - e.g. 'events/create/2026-11-10'.
     * The calendar uses this when a day with no events is clicked, so that the
     * date_and_time field arrives pre-filled with that date at the default time.
     *
     * @return void
     */
    public function create(): void {
        $this->trongate_security->make_sure_allowed();

        $preset_date = $this->get_preset_date_from_url();
        $update_id = ($preset_date === '') ? segment(3, 'int') : 0;

        if ((REQUEST_TYPE === 'GET') && ($update_id > 0)) {
            $data = $this->model->get_data_from_db($update_id);
        } else {
            $data = $this->model->get_data_from_post();
        }

        if (($preset_date !== '') && (REQUEST_TYPE === 'GET')) {
            $data['date_and_time'] = $preset_date.'T'.$this->default_event_time;
        }

        // Add view-specific data
        $data['headline'] = ($update_id > 0) ? 'Update Event Record' : 'Create New Event Record';
        $data['cancel_url'] = ($update_id > 0) ? 'events/show/'.$update_id : 'events/manage';
        $data['form_location'] = 'events/submit/'.$update_id;
        $data['view_module'] = 'events';
        $data['view_file'] = 'create';
        $this->templates->admin($data);
    }

    /**
     * Handle form submission for creating/updating events.
     *
     * Validates input, converts checkbox data, and saves to database.
     * Includes automatic CSRF validation and proper checkbox conversion.
     *
     * @return void
     */
    public function submit(): void {
        $this->trongate_security->make_sure_allowed();

        $submit = post('submit', true);

        if ($submit === 'Submit') {
            $this->validation->set_rules('event_title', 'event title', 'required|min_length[2]|max_length[255]');
            $this->validation->set_rules('event_description', 'event description', 'min_length[2]');
            $this->validation->set_rules('date_and_time', 'date and time', 'valid_datetime_local|required');

            if ($this->validation->run()) {
                $update_id = segment(3, 'int');
                $data = $this->model->get_data_from_post();

                if ($update_id > 0) {
                    $this->model->update_record($update_id, $data);
                    $flash_msg = 'Event updated successfully';
                    $finish_url = 'events/show/'.$update_id;
                } else {
                    $update_id = $this->model->create_new_record($data);
                    $flash_msg = 'Event created successfully';
                    $finish_url = 'events/manage';
                }

                set_flashdata($flash_msg);
                redirect($finish_url);
            } else {
                $this->create();
            }
        } else {
            redirect('events/manage');
        }
    }

    /**
     * Display detailed view of a single event.
     *
     * Shows all details with edit/delete options.
     * Automatically handles missing records with 404 page.
     *
     * @return void
     */
    public function show(): void {
        $this->trongate_security->make_sure_allowed();

        $update_id = segment(3, 'int');

        if ($update_id === 0) {
            redirect('events/manage');
        }

        // Fetch record and prepare for display.
        $data = $this->model->get_data_from_db($update_id, true);

        if ($data === false) {
            $this->not_found();
            return;
        }

        // Add additional view data
        $data['update_id'] = $update_id;
        $data['headline'] = 'Event Details';
        $data['back_url'] = $this->get_back_url();
        $data['view_module'] = 'events';
        $data['view_file'] = 'show';
        $this->templates->admin($data);
    }

    /**
     * Display confirmation page before deleting a event.
     *
     * Shows confirmation dialog with details to prevent accidental deletion.
     *
     * @return void
     */
    public function delete_conf(): void {
        $this->trongate_security->make_sure_allowed();

        $update_id = segment(3, 'int');

        if ($update_id === 0) {
            $this->not_found();
            return;
        }

        $data = $this->model->get_data_for_edit($update_id);

        if ($data === false) {
            $this->not_found();
            return;
        }

        $data['update_id'] = $update_id;
        $data['headline'] = 'Delete Event Record';
        $data['cancel_url'] = 'events/show/'.$update_id;
        $data['form_location'] = 'events/submit_delete/'.$update_id;
        $data['view_module'] = 'events';
        $data['view_file'] = 'delete_conf';
        $this->templates->admin($data);
    }

    /**
     * Handle event deletion after confirmation.
     *
     * Verifies confirmation and deletes record from database.
     * Includes safety checks to prevent unauthorized deletion.
     *
     * @return void
     */
    public function submit_delete(): void {
        $this->trongate_security->make_sure_allowed();

        $submit = post('submit', true);

        if ($submit === 'Yes - Delete Now') {
            $update_id = segment(3, 'int');

            if ($update_id === 0) {
                redirect('events/manage');
                return;
            }

            $record = $this->model->find_by_id($update_id);

            if ($record === false) {
                redirect('events/manage');
                return;
            }

            $this->model->delete_record($update_id);

            set_flashdata('The record was successfully deleted');
            redirect('events/manage');
        } else {
            redirect('events/manage');
        }
    }

    /**
     * Set number of records per page for pagination.
     *
     * Stores user preference in session for consistent pagination across requests.
     *
     * @return void
     */
    public function set_per_page(): void {
        $this->trongate_security->make_sure_allowed();

        $selected_index = segment(3, 'int');

        if (!isset($this->per_page_options[$selected_index])) {
            $selected_index = 1;
        }

        $_SESSION['selected_per_page'] = $selected_index;
        redirect('events/manage');
    }

    /**
     * Generate pagination configuration data.
     *
     * @param int    $total_rows    Total number of records
     * @param int    $limit         Number of records per page
     * @param string $search_query  The search query string
     * @param string $search_column The column being searched
     * @return array Pagination configuration for template
     */
    private function get_pagination_data(int $total_rows, int $limit, string $search_query = '', string $search_column = ''): array {
        $pagination_query = '';
        if ($search_query !== '' && $search_column !== '') {
            $pagination_query = 'search_query=' . urlencode($search_query) . '&search_column=' . urlencode($search_column);
        }

        return [
            'total_rows' => $total_rows,
            'limit' => $limit,
            'pagination_root' => 'events/manage',
            'pagination_query' => $pagination_query,
            'record_name_plural' => 'events',
            'include_showing_statement' => true
        ];
    }

    /**
     * Build the data required to render a month of the calendar.
     *
     * Produces a Monday-first grid, padded with days from the surrounding
     * months so that every week has seven cells.
     *
     * @param int $year  The year to render
     * @param int $month The month to render (1-12)
     * @return array Data for the calendar view
     */
    private function build_calendar_data(int $year, int $month): array {
        $first_of_month = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));

        // Monday-first grid - 'N' returns 1 (Mon) through 7 (Sun).
        $leading_days = (int) $first_of_month->format('N') - 1;
        $days_in_month = (int) $first_of_month->format('t');
        $total_cells = (int) (ceil(($leading_days + $days_in_month) / 7) * 7);

        $today = date('Y-m-d');
        $cursor = $first_of_month->modify('-'.$leading_days.' days');
        $grid_start = $cursor->format('Y-m-d');
        $grid_end = $cursor->modify('+'.$total_cells.' days')->format('Y-m-d');

        $calendar_events = $this->model->get_event_titles_by_date($grid_start, $grid_end);
        $event_titles = $calendar_events['event_titles'];
        $important_dates = $calendar_events['important_dates'];
        $weeks = [];

        for ($cell = 0; $cell < $total_cells; $cell++) {
            $date = $cursor->format('Y-m-d');
            $in_month = ((int) $cursor->format('n') === $month);

            $day_titles = $event_titles[$date] ?? [];
            $has_events = (count($day_titles) > 0);
            $has_important_event = isset($important_dates[$date]);

            $classes = ['cal-day'];
            if ($in_month === false) {
                $classes[] = 'is-outside';
            }
            if (in_array($cursor->format('N'), ['6', '7'], true)) {
                $classes[] = 'is-weekend';
            }
            if ($date === $today) {
                $classes[] = 'is-today';
            }
            if ($has_events === true) {
                $classes[] = 'has-events';
            }
            if ($has_important_event === true) {
                $classes[] = 'has-important-event';
            }

            $weeks[intdiv($cell, 7)][] = [
                'date' => $date,
                'day' => (int) $cursor->format('j'),
                'in_month' => $in_month,
                'is_today' => ($date === $today),
                'has_events' => $has_events,
                'has_important_event' => $has_important_event,
                'day_label' => $cursor->format('D j M Y'),
                'event_summary' => $this->get_day_event_summary($day_titles),
                'css_class' => implode(' ', $classes)
            ];

            $cursor = $cursor->modify('+1 day');
        }

        $prev_month = $first_of_month->modify('-1 month');
        $next_month = $first_of_month->modify('+1 month');
        $now = new DateTimeImmutable('today');

        return [
            'month_label' => $first_of_month->format('F Y'),
            'weekdays' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'weeks' => $weeks,
            'is_current_month' => (((int) $now->format('n') === $month) && ((int) $now->format('Y') === $year)),
            'prev_year' => (int) $prev_month->format('Y'),
            'prev_month' => (int) $prev_month->format('n'),
            'next_year' => (int) $next_month->format('Y'),
            'next_month' => (int) $next_month->format('n'),
            'today_year' => (int) $now->format('Y'),
            'today_month' => (int) $now->format('n')
        ];
    }

    /**
     * Build the short summary shown inside a calendar day cell.
     *
     * One event shows its title; two or more are summarised by a count.
     *
     * @param array<string> $event_titles Titles of events on that date.
     * @return string Summary text, or an empty string when there are no events.
     */
    private function get_day_event_summary(array $event_titles): string {
        $count = count($event_titles);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $event_titles[0];
        }

        return $count.' events today';
    }

    /**
     * Determine appropriate back URL for navigation.
     *
     * Uses previous URL if it was the manage page, otherwise defaults to manage.
     *
     * @return string URL for back button
     */
    private function get_back_url(): string {
        $previous_url = previous_url();
        if ($previous_url !== '' && strpos($previous_url, BASE_URL . 'events/manage') === 0) {
            return $previous_url;
        }
        return BASE_URL . 'events/manage';
    }

    /**
     * Display 404-style not found page for missing events.
     *
     * Shows user-friendly error message with navigation back.
     *
     * @return void
     */
    private function not_found(): void {
        $data = [
            'headline' => 'Event Not Found',
            'message' => 'The event you\'re looking for doesn\'t exist or has been deleted.',
            'back_url' => $this->get_back_url(),
            'back_label' => 'Go Back',
            'view_module' => 'events',
            'view_file' => 'not_found'
        ];
        $this->templates->admin($data);
    }

    /**
     * Get selected per-page index from session.
     *
     * @return int Index of selected per-page option
     */
    private function get_selected_per_page(): int {
        return $_SESSION['selected_per_page'] ?? 1;
    }

    /**
     * Get current pagination limit from session.
     *
     * @return int Number of records to display per page
     */
    private function get_limit(): int {
        if (isset($_SESSION['selected_per_page'])) {
            return $this->per_page_options[$_SESSION['selected_per_page']];
        }
        return $this->default_limit;
    }

    /**
     * Calculate pagination offset based on page number.
     *
     * @return int Database offset for current page
     */
    private function get_offset(): int {
        $page_num = segment(3, 'int');
        return ($page_num > 1) ? ($page_num - 1) * $this->get_limit() : 0;
    }

    /**
     * Handle search form submission for filtering events.
     *
     * Preprocesses text parameters and triggers custom query filtering.
     *
     * @return void
     */
    public function submit_search(): void {
        $this->trongate_security->make_sure_allowed();

        $search_query = post('search_query', true);
        $search_column = post('search_column', true);

        // Validate column against known searchable columns.
        $allowed_columns = $this->model->get_searchable_columns();
        if ($search_column !== '' && !in_array($search_column, $allowed_columns, true)) {
            $search_column = '';
        }

        // Preprocess the query
        $search_query = trim($search_query);
        $search_query = preg_replace('/\s+/', ' ', $search_query);

        // Validate minimum 2 character length
        if (strlen($search_query) < 2) {
            set_flashdata('Search query must be at least 2 characters');
            redirect('events/manage');
        }

        redirect('events/manage?search_query=' . urlencode($search_query) . '&search_column=' . urlencode($search_column));
    }

    /**
     * Display the search modal form for filtering events.
     *
     * Renders a form with a search input and a dropdown of searchable columns.
     *
     * @return void
     */
    public function search_modal(): void {
        $this->trongate_security->make_sure_allowed();

        $data['view_module'] = 'events';
        $data['view_file'] = 'search_modal';
        $this->view('search_modal', $data);
    }


}
