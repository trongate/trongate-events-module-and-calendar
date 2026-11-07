<?php
/**
 * Events_model - Handles data operations for event records.
 *
 * Demonstrates proper data conversion patterns and separation
 * of concerns between database operations and presentation logic.
 */
class Events_model extends Model {
    
    private string $table_name = 'events';

    /**
     * Return the list of searchable column names (used by the controller for validation).
     *
     * @return array<string>
     */
    public function get_searchable_columns(): array {
        return ['event_title'];
    }

    /**
     * Get event form data from POST and prepare it for database or view display.
     *
     * @return array Form data with proper types
     */
    public function get_data_from_post(): array {
        return [
            'event_title' => post('event_title', true),
            'event_description' => trim(post('event_description')),
            'date_and_time' => post('date_and_time', true),
            'important' => (int) (bool) post('important', true)
        ];
    }

    /**
     * Retrieve a single record from the database by ID.
     *
     * @param int $update_id Record ID.
     * @param bool $prepare_for_display Whether to pass the record through prepare_record_for_display().
     * @return array|bool Associative array on success or false on failure.
     */
    public function get_data_from_db(int $update_id, bool $prepare_for_display = false): array|bool {
        $record_obj = $this->db->get_where($update_id, $this->table_name);

        if ($record_obj === false) {
            return false;
        }

        if ($prepare_for_display === true) {
            $record_obj = $this->prepare_record_for_display($record_obj);
        }

        return (array) $record_obj;
    }

    /**
     * Retrieve a record for editing or delete confirmation (no display transformations).
     *
     * @param int $update_id Record ID.
     * @return array|bool Associative array on success or false on failure.
     */
    public function get_data_for_edit(int $update_id): array|bool {
        return $this->get_data_from_db($update_id, false);
    }

    /**
     * Find a record by ID and return it as an object.
     *
     * @param int $update_id Record ID.
     * @return object|bool Record object on success or false on failure.
     */
    public function find_by_id(int $update_id): object|bool {
        return $this->db->get_where($update_id, $this->table_name);
    }

    /**
     * Fetch paginated events records.
     *
     * @param int $limit Records per page
     * @param int $offset Records to skip
     * @return array<object>
     */
    public function fetch_records(int $limit, int $offset): array {
        $sql = 'SELECT * FROM '.$this->table_name.' ORDER BY date_and_time DESC LIMIT '.$limit.' OFFSET '.$offset;
        return $this->db->query($sql, 'object');
    }
    
    /**
     * Count all events records in the database table.
     *
     * @return int Total number of events records.
     */
    public function count_all(): int {
        return $this->db->count($this->table_name);
    }

    /**
     * Search events records across searchable columns.
     *
     * @param string $query The search query string.
     * @param string $column Specific column to search, or empty string to search all.
     * @param int $limit Maximum records to return.
     * @param int $offset Records to skip.
     * @return array<object>
     */
    public function search_records(string $query, string $column, int $limit, int $offset): array {
        $searchable_columns = $this->get_searchable_columns();

        // Validate column against whitelist before interpolating into SQL.
        if ($column !== '' && !in_array($column, $searchable_columns, true)) {
            $column = '';
        }

        if ($column !== '') {
            $sql = 'SELECT * FROM '.$this->table_name.' WHERE '.$column.' LIKE :query ORDER BY date_and_time DESC LIMIT '.$limit.' OFFSET '.$offset;
        } else {
            $conditions = [];
            foreach ($searchable_columns as $col) {
                $conditions[] = $col.' LIKE :query';
            }
            $sql = 'SELECT * FROM '.$this->table_name.' WHERE ('.implode(' OR ', $conditions).') ORDER BY date_and_time DESC LIMIT '.$limit.' OFFSET '.$offset;
        }

        return $this->db->query_bind($sql, ['query' => '%'.$query.'%'], 'object');
    }

    /**
     * Count search results for events records.
     *
     * @param string $query The search query string.
     * @param string $column Specific column to search, or empty string to search all.
     * @return int Number of matching records.
     */
    public function count_search_results(string $query, string $column): int {
        $searchable_columns = $this->get_searchable_columns();

        // Validate column against whitelist before interpolating into SQL.
        if ($column !== '' && !in_array($column, $searchable_columns, true)) {
            $column = '';
        }

        if ($column !== '') {
            $sql = 'SELECT COUNT(*) as total FROM '.$this->table_name.' WHERE '.$column.' LIKE :query';
        } else {
            $conditions = [];
            foreach ($searchable_columns as $col) {
                $conditions[] = $col.' LIKE :query';
            }
            $sql = 'SELECT COUNT(*) as total FROM '.$this->table_name.' WHERE ('.implode(' OR ', $conditions).')';
        }

        $result = $this->db->query_bind($sql, ['query' => '%'.$query.'%'], 'object');
        return (int) ($result[0]->total ?? 0);
    }

    /**
     * Fetch event titles between two dates, grouped by date.
     *
     * Used by the calendar so that each day cell can display what is
     * happening on that date, and so that days holding an important
     * event can be highlighted.
     *
     * @param string $start_date Start of range (Y-m-d, inclusive).
     * @param string $end_date End of range (Y-m-d, exclusive).
     * @return array{event_titles: array<string, array<string>>, important_dates: array<string, bool>}
     */
    public function get_event_titles_by_date(string $start_date, string $end_date): array {
        $sql = 'SELECT event_title, date_and_time, important FROM '.$this->table_name.
            ' WHERE date_and_time >= :start_date AND date_and_time < :end_date'.
            ' ORDER BY date_and_time';

        $rows = $this->db->query_bind($sql, ['start_date' => $start_date, 'end_date' => $end_date], 'object');

        $event_titles = [];
        $important_dates = [];

        foreach ($rows as $row) {
            $date = substr($row->date_and_time, 0, 10);
            $event_titles[$date][] = trim($row->event_title);

            if ((int) $row->important === 1) {
                $important_dates[$date] = true;
            }
        }

        return [
            'event_titles' => $event_titles,
            'important_dates' => $important_dates
        ];
    }

    /**
     * Fetch the events taking place on a single date.
     *
     * @param string $date The date to fetch (Y-m-d).
     * @return array<object> Event records, earliest first.
     */
    public function get_events_by_date(string $date): array {
        $start = $date.' 00:00:00';
        $end = (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d').' 00:00:00';

        $sql = 'SELECT * FROM '.$this->table_name.
            ' WHERE date_and_time >= :start AND date_and_time < :end'.
            ' ORDER BY date_and_time';

        return $this->db->query_bind($sql, ['start' => $start, 'end' => $end], 'object');
    }

    /**
     * Prepare multiple events records for display in list views.
     *
     * @param array $rows Array of event record objects from database
     * @return array Array of objects with formatted display fields
     */
    public function prepare_records_for_display(array $rows): array {
        $prepared = [];
        foreach ($rows as $row) {
            $prepared[] = $this->prepare_record_for_display($row);
        }
        return $prepared;
    }

    /**
     * Prepare raw event database data for display in views.
     *
     * @param object $record_obj Raw data from database
     * @return object Enhanced data with formatted fields
     */
    public function prepare_record_for_display(object $record_obj): object {
        $record_obj->event_title = trim($record_obj->event_title);
        $record_obj->event_description = trim($record_obj->event_description);
        $record_obj->important = ($record_obj->important == 1) ? 'yes' : 'no';
        return $record_obj;
    }

    /**
     * Create a new event record.
     *
     * @param array $data Event data
     * @return int Returns the ID of the newly created record
     */
    public function create_new_record(array $data): int {
        return $this->db->insert($data, $this->table_name);
    }

    /**
     * Update an existing event record.
     *
     * @param int $update_id The ID of the record to update
     * @param array $data The data to update
     * @return void
     */
    public function update_record(int $update_id, array $data): void {
        $this->db->update($update_id, $data, $this->table_name);
    }

    /**
     * Delete a event record.
     *
     * @param int $update_id The ID of the record to delete
     * @return void
     */
    public function delete_record(int $update_id): void {
        $this->db->delete($update_id, $this->table_name);
    }
}
